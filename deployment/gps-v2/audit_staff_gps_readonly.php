<?php
/**
 * Assam Motors: read-only full staff identity + GPS audit (staging).
 * Usage: php audit_staff_gps_readonly.php /path/to/staging-laravel-root
 * No coordinates, device IDs, passwords, emails or bearer tokens are printed.
 */
declare(strict_types=1);
$root = realpath($argv[1] ?? getcwd());
if (!$root || !is_file($root.'/artisan') || !is_file($root.'/vendor/autoload.php')) {
    fwrite(STDERR, "Usage: php audit_staff_gps_readonly.php /path/to/assam-erp-staging\n");
    exit(2);
}
chdir($root);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = \Illuminate\Support\Facades\DB::class;
$schema = \Illuminate\Support\Facades\Schema::class;
$now = \Carbon\CarbonImmutable::now('Asia/Kolkata');
function col(string $t,string $c): bool {
    return \Illuminate\Support\Facades\Schema::hasColumn($t,$c);
}
function say(string $type,array $fields=[]): void {
    echo $type;
    foreach ($fields as $k=>$v) {
        $value=preg_replace('/[^A-Za-z0-9 ._:-]/','_',strval($v??'-'));
        echo ' '.$k.'='.$value;
    }
    echo "\n";
}
if (!$schema::hasTable('staff') || !col('staff','id') || !col('staff','staff_code'))
    throw new RuntimeException('Staff master table or key missing');
$sc=array_values(array_filter(['id','staff_code','name','role','status','email'],fn($c)=>col('staff',$c)));
$staff=$db::table('staff')->select($sc)->orderBy('id')->get();
$codes=[];$emails=[];$byId=[];$loginLinks=[];
say('READ_ONLY',['server_ist'=>$now->format('Y-m-d_H:i:s'),'staff_count'=>$staff->count()]);
foreach($staff as $s) {
    $id=(int)$s->id; $byId[$id]=$s;
    $code=strtoupper(trim(strval($s->staff_code??'')));
    $codes[$code][]=$id;
    $email=strtolower(trim(strval($s->email??'')));
    if($email!=='') $emails[$email][]=$id;
    if(!preg_match('/^AM[0-9]+$/',$code))
        say('REVIEW',['type'=>'INVALID_STAFF_CODE','master_id'=>$id,'code'=>$code]);
}
foreach($codes as $code=>$ids) {
    if(count($ids)>1) say('REVIEW',['type'=>'DUPLICATE_STAFF_CODE','code'=>$code,'count'=>count($ids)]);
}
foreach($emails as $ids) {
    if(count($ids)>1) say('REVIEW',['type'=>'DUPLICATE_EMAIL','master_ids'=>implode('_',$ids)]);
}
try { $authTable=(new \App\Models\ErpAuthUser)->getTable(); }
catch(\Throwable $e) { $authTable=''; }
if($authTable && $schema::hasTable($authTable) && col($authTable,'id')) {
    $ac=array_values(array_filter(['id','staff_id','active','role','email'],fn($c)=>col($authTable,$c)));
    $accounts=$db::table($authTable)->select($ac)->get();
    say('AUTH_TABLE',['name'=>$authTable,'account_count'=>$accounts->count()]);
    foreach($accounts as $a) {
        if(!isset($a->staff_id) || $a->staff_id===null) continue;
        $sid=(int)$a->staff_id;
        $loginLinks[$sid][]=$a;
        if(!isset($byId[$sid]))
            say('REVIEW',['type'=>'ORPHAN_ERP_LOGIN','account_id'=>$a->id]);
    }
    foreach($loginLinks as $sid=>$linked) {
        $active=0;
        foreach($linked as $a) if(!isset($a->active) || (bool)$a->active) $active++;
        $code=$byId[$sid]->staff_code??('STAFF_ID_'.$sid);
        if($active>1) say('REVIEW',['type'=>'MULTIPLE_ACTIVE_ERP_LOGINS','staff'=>$code,'count'=>$active]);
        if($active>0 && isset($byId[$sid]) &&
           col('staff','status') && strtolower(strval($byId[$sid]->status??''))!=='active')
            say('REVIEW',['type'=>'INACTIVE_STAFF_ACTIVE_LOGIN','staff'=>$code]);
    }
} else say('NOTE',['auth_links'=>'UNKNOWN_TABLE_OR_SCHEMA']);
if($schema::hasTable('staff_location_logs') && col('staff_location_logs','staff_id')) {
    $t='staff_location_logs';
    $lc=array_values(array_filter(['received_at','source','app_version'],fn($c)=>col($t,$c)));
    $fresh=0;$stale=0;$none=0;
    foreach($staff as $s) {
        $q=$db::table($t)->where('staff_id',$s->id);
        if(col($t,'received_at')) $q->orderByDesc('received_at');
        if(col($t,'id')) $q->orderByDesc('id');
        $l=$q->first($lc?:['staff_id']);
        $age='NO_GPS_HISTORY';
        if($l && isset($l->received_at)) {
            $age=(string)(int)abs($now->diffInSeconds(
                \Carbon\CarbonImmutable::parse(strval($l->received_at),'Asia/Kolkata'),false)).'s';
            if((int)$age>300) $stale++; else $fresh++;
        } else $none++;
        say('STAFF',['code'=>$s->staff_code,'name'=>$s->name??'UNKNOWN',
            'status'=>$s->status??'UNKNOWN','role'=>$s->role??'UNKNOWN',
            'linked_erp_accounts'=>count($loginLinks[(int)$s->id]??[]),
            'gps_age'=>$age,'app'=>$l->app_version??'NONE','gps_source'=>$l->source??'NONE']);
    }
    say('GPS_SUMMARY',['fresh_5min'=>$fresh,'stale'=>$stale,'no_history'=>$none]);
    $orphans=$db::table("$t as gps")->leftJoin('staff as master','master.id','=','gps.staff_id')
        ->whereNull('master.id')->distinct()->pluck('gps.staff_id')->all();
    if($orphans) say('REVIEW',['type'=>'GPS_ORPHAN_STAFF_ID','count'=>count($orphans)]);
    if(col($t,'device_id') && col($t,'received_at')) {
        $shared=$db::table($t)->selectRaw('device_id,COUNT(DISTINCT staff_id) as n')
          ->where('received_at','>=',$now->subDay()->format('Y-m-d H:i:s'))
          ->whereNotNull('device_id')->where('device_id','<>','')
          ->groupBy('device_id')->havingRaw('COUNT(DISTINCT staff_id)>1')
          ->limit(30)->get();
        say('DEVICE_SHARING',['groups_24h'=>$shared->count()]);
        foreach($shared as $g) {
            $ids=$db::table($t)->where('device_id',$g->device_id)
              ->where('received_at','>=',$now->subDay()->format('Y-m-d H:i:s'))
              ->distinct()->pluck('staff_id')->all();
            $cs=array_map(fn($id)=>$byId[(int)$id]->staff_code??'UNKNOWN',$ids);
            say('REVIEW',['type'=>'SHARED_PHONE_LOGIN','staff_codes'=>implode('_',$cs)]);
        }
    }
} else say('NOTE',['gps'=>'UNKNOWN_TABLE_OR_SCHEMA']);
say('AUDIT_FINISHED',['writes'=>0,'raw_coordinates'=>0,'tokens'=>0]);
