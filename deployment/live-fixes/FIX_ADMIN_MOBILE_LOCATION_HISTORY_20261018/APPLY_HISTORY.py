#!/usr/bin/env python3
"""Read-only authenticated Admin GPS history query added to the existing API.
Only AdminMobileController.php is changed. No tables, migration or GPS records touched.
"""
import hashlib,os,shutil,stat,sys,time
from pathlib import Path
root=Path(sys.argv[1]).resolve()
target=root/'app/Http/Controllers/Api/V1/AdminMobileController.php'
if not (root/'artisan').is_file() or not target.is_file():
    raise SystemExit('SAFE_STOP: wrong Laravel staging directory')
s=target.read_text(encoding='utf-8')
print('ADMIN_MOBILE_BEFORE_SHA',hashlib.sha256(s.encode()).hexdigest()[:16])
marker='AM_ADMIN_LOCATION_HISTORY_PARITY_V1'
if marker in s:
    print('ADMIN_LOCATION_HISTORY_ALREADY_PRESENT')
    raise SystemExit(0)
start=s.find('    public function locations(Request $request): JsonResponse')
end=s.find('    public function jobCards(Request $request): JsonResponse',start)
if start<0 or end<0 or s.count('    public function locations(Request $request): JsonResponse')!=1:
    raise SystemExit('SAFE_STOP: unexpected Admin location method layout')
original=s[start:end]
required=[
    '$this->admin($request);',
    "if (!Schema::hasTable('staff_location_logs'))",
    "$q = DB::table('staff_location_logs as l')",
    "if ($request->filled('staff_id'))",
    "$latestOnly = $request->boolean('latest', true);",
    "return response()->json(['ok' => true, 'locations' => $rows]);",
]
for x in required:
    if x not in original:
        raise SystemExit('SAFE_STOP: existing Admin location API changed ('+x+')')
history=r"""
        // AM_ADMIN_LOCATION_HISTORY_PARITY_V1: authenticated, read-only desktop-style date trail.
        // Preserve the existing latest=1 response exactly for older Android releases.
        if ($request->boolean('history', false)) {
            $staffIdRaw=trim((string)$request->query('staff_id',''));
            $date=trim((string)$request->query('date',''));
            $validDate=\DateTimeImmutable::createFromFormat('!Y-m-d',$date);
            if (!preg_match('/^[1-9][0-9]{0,9}$/',$staffIdRaw) ||
                !$validDate || $validDate->format('Y-m-d')!==$date) {
                return response()->json(['message'=>'Valid Staff and Date (YYYY-MM-DD) required.'],422);
            }
            $staffId=(int)$staffIdRaw;
            $staff=DB::table('staff')->where('id',$staffId)->first(['id','name','staff_code']);
            if (!$staff) return response()->json(['message'=>'Staff not found.'],404);
            $limit=1440;
            $items=DB::table('staff_location_logs as l')
                ->where('l.staff_id',$staffId)
                ->whereDate('l.recorded_at',$date)
                ->orderBy('l.recorded_at')->orderBy('l.id')
                ->limit($limit+1)
                ->get(['l.staff_id','l.latitude','l.longitude','l.recorded_at',
                       'l.received_at','l.accuracy_m','l.quality','l.provider',
                       'l.distance_m','l.inside_geofence','l.active_rot_id']);
            $truncated=$items->count()>$limit;
            $items=$items->take($limit)->values();
            $settings=Schema::hasTable('staff_location_settings')
                ? DB::table('staff_location_settings')->where('id',1)->first() : null;
            $radius=max(20,(int)($settings->radius_m??100));
            $maxAccuracy=max(10,(int)($settings->max_accuracy_m??30));
            $verifiedOutsideSeconds=0;
            $previousOutsideTs=null;
            foreach ($items as $item) {
                $accuracy=$item->accuracy_m===null?null:(float)$item->accuracy_m;
                $distance=$item->distance_m===null?null:(float)$item->distance_m;
                $outside=($item->quality==='OK' && $accuracy!==null &&
                    $accuracy<=$maxAccuracy && $distance!==null &&
                    (int)($item->inside_geofence??1)===0 &&
                    $distance-$accuracy>$radius);
                $ts=strtotime((string)$item->recorded_at);
                if ($outside && $previousOutsideTs!==null && $ts!==false &&
                    $ts>=$previousOutsideTs && $ts-$previousOutsideTs<=300) {
                    $verifiedOutsideSeconds+=$ts-$previousOutsideTs;
                }
                $previousOutsideTs=($outside&&$ts!==false)?$ts:null;
            }
            return response()->json([
                'ok'=>true,'history'=>true,'date'=>$date,
                'staff'=>['id'=>$staff->id,'name'=>$staff->name,'staff_code'=>$staff->staff_code],
                'locations'=>$items,
                'verified_outside_seconds'=>$verifiedOutsideSeconds,
                'truncated'=>$truncated,
            ]);
        }

"""
needle="        if (!Schema::hasTable('staff_location_logs')) return response()->json(['ok' => true, 'locations' => []]);"
if original.count(needle)!=1:
    raise SystemExit('SAFE_STOP: authenticated location table guard mismatched')
replacement=original.replace(needle,needle+'\n'+history,1)
s=s[:start]+replacement+s[end:]
if s.count(marker)!=1 or s.count('public function locations(Request $request)')!=1:
    raise SystemExit('SAFE_STOP: output structure invalid')
backup=target.with_name(target.name+'.pre-history-'+time.strftime('%Y%m%d%H%M%S')+'.bak')
shutil.copy2(target,backup)
tmp=target.with_name(target.name+'.location-history.tmp')
tmp.write_text(s,encoding='utf-8')
os.chmod(tmp,stat.S_IMODE(target.stat().st_mode))
os.replace(tmp,target)
print('ADMIN_MOBILE_HISTORY_APPLIED','BACKUP='+backup.name,'AFTER_SHA='+hashlib.sha256(s.encode()).hexdigest()[:16])
