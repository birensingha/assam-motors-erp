<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use App\Services\Am206InspectionMaster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * AM288 STAGING SOURCE ONLY. Dynamic forms require atomic route integration:
 * legacy Staff inspection POST guard + updated AM189 QC findings + migration.
 * Never expose these routes before that entire set has passed preflight.
 */
final class Am288DynamicInspectionController extends Controller
{
    private function actor(Request $request): ErpAuthUser
    {
        $user = $request->user();
        abort_unless($user instanceof ErpAuthUser && (bool)$user->active, 401,
            'Authenticated, active ERP mobile user required.');
        return $user;
    }

    private function admin(Request $request): ErpAuthUser
    {
        $user = $this->actor($request);
        abort_unless($user->isAdmin(), 403, 'ERP Owner/Admin required.');
        return $user;
    }

    private function tables(): void
    {
        foreach ([
            'job_cards', 'staff', 'job_card_inspection',
            'am206_inspection_master_versions', 'am206_job_inspection_forms',
            'am288_inspection_answers', 'am288_inspection_events',
            'am189_inspection_defects', 'am189_inspection_defect_audits'
        ] as $name) {
            abort_unless(Schema::hasTable($name), 503,
                'AM288 prerequisite missing: '.$name);
        }
    }

    private function card(int $id, bool $lock = false): object
    {
        abort_unless($id > 0, 404);
        $query = DB::table('job_cards')->where('id', $id);
        $row = $lock ? $query->lockForUpdate()->first() : $query->first();
        abort_unless($row, 404, 'Job Card not found.');
        return $row;
    }

    private function form(int $job, bool $lock = false): object
    {
        $query = DB::table('am206_job_inspection_forms')->where('job_card_id', $job);
        $row = $lock ? $query->lockForUpdate()->first() : $query->first();
        abort_unless($row, 404, 'No AM288 assigned inspection form for Job Card.');
        return $row;
    }

    private function points(object $form): array
    {
        $points = json_decode((string)$form->points_json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($points) || !array_is_list($points)
            || count($points) < 1 || count($points) > 100)
            abort(409, 'Invalid assigned inspection snapshot.');
        return $points;
    }

    private function fingerprint(object $form): string
    {
        return hash('sha256', (string)$form->points_json);
    }

    private function canView(ErpAuthUser $user, object $form): bool
    {
        return $user->isAdmin() ||
            ((int)($user->staff_id ?? 0) > 0 &&
             (int)($user->staff_id ?? 0) === (int)$form->assigned_staff_id);
    }

    private function event(int $job, ErpAuthUser $user,
                           string $action, array $data): void
    {
        DB::table('am288_inspection_events')->insert([
            'job_card_id' => $job,
            'actor_user_id' => (int)$user->id,
            'actor_staff_id' => (int)($user->staff_id ?? 0) ?: null,
            'action' => $action,
            'payload_json' => json_encode($data, JSON_THROW_ON_ERROR),
            'created_at' => now()
        ]);
    }

    /**
     * POST /api/v1/admin/mobile/job-cards/{job}/inspection-form/assign
     * Body: staff_id, mode=STANDARD|CUSTOM, [point_keys], reason.
     * A CUSTOM form is a point subset of the published master: types/defect
     * rules stay authoritative; arbitrary unvalidated client types are banned.
     */
    public function assign(Request $request, int $job,
                           Am206InspectionMaster $master): JsonResponse
    {
        $admin = $this->admin($request);
        $this->tables();
        $v = $request->validate([
            'staff_id' => ['required', 'integer', 'min:1'],
            'mode' => ['required', 'in:STANDARD,CUSTOM'],
            'point_keys' => ['nullable', 'array', 'min:1', 'max:100'],
            'point_keys.*' => ['string', 'regex:/^[a-z][a-z0-9_-]{1,39}$/'],
            'reason' => ['required', 'string', 'min:8', 'max:1000'],
        ]);
        $active = $master->active();
        $version = $active['version'] ?? null;
        if (!$version || !is_array($active['points'] ?? null))
            abort(409, 'AM206 published Master required before dynamic assignment.');
        $all = $master->validateDefinition($active['points']);
        $byKey = [];
        foreach ($all as $point) $byKey[$point['key']] = $point;
        $mode = $v['mode'];
        if ($mode === 'STANDARD') {
            if (!empty($v['point_keys']))
                throw ValidationException::withMessages([
                    'point_keys' => 'STANDARD cannot select custom point keys.'
                ]);
            $selected = $all;
        } else {
            $keys = $v['point_keys'] ?? [];
            if (!$keys || count($keys) !== count(array_unique($keys)))
                throw ValidationException::withMessages([
                    'point_keys' => 'CUSTOM requires unique point keys.'
                ]);
            $selected = [];
            // Retain published ordering even if request sends keys out of order.
            foreach ($all as $point) {
                if (in_array($point['key'], $keys, true)) $selected[] = $point;
            }
            if (count($selected) !== count($keys))
                throw ValidationException::withMessages([
                    'point_keys' => 'Some keys are not in the published master.'
                ]);
            $selected = $master->validateDefinition($selected);
        }
        $serialized = json_encode($selected, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return DB::transaction(function () use ($admin, $job, $v, $mode, $serialized, $version, $selected) {
            $card = $this->card($job, true);
            abort_unless(strtolower((string)$card->status) === 'open' &&
                strtolower((string)$card->inspection_status) === 'pending', 409,
                'Only OPEN, inspection-pending Job Cards may receive new forms.');
            abort_unless(DB::table('job_card_inspection')
                ->where('job_card_id',$job)->count() === 0, 409,
                'Existing legacy inspection must not be overwritten.');
            abort_unless(DB::table('am288_inspection_answers')
                ->where('job_card_id',$job)->count() === 0, 409,
                'Existing dynamic answers must not be overwritten.');
            abort_unless(DB::table('am189_inspection_defects')
                ->where('job_card_id',$job)->count() === 0, 409,
                'Existing audited defects require Admin review.');

            $staff = DB::table('staff')->where('id',(int)$v['staff_id'])
                ->whereRaw("LOWER(COALESCE(status,'active'))='active'")->first();
            abort_unless($staff, 422, 'Select an active inspector.');
            $masterVersionId = DB::table('am206_inspection_master_versions')
                ->where('version',(int)$version)->where('status','PUBLISHED')
                ->value('id');
            abort_unless($masterVersionId, 409, 'Master publication changed; refresh.');
            $existing = DB::table('am206_job_inspection_forms')
                ->where('job_card_id',$job)->lockForUpdate()->first();
            abort_if($existing && strtoupper((string)$existing->state) !== 'DRAFT',
                409, 'Assigned/Completed inspection snapshot is immutable.');
            $data = [
                'master_version_id' => (int)$masterVersionId,
                'source' => $mode,
                'points_json' => $serialized,
                'state' => 'ASSIGNED',
                'assigned_staff_id' => (int)$staff->id,
                'locked_at' => now(),
                'configured_by_admin_id' => (int)$admin->id,
                'updated_at' => now()
            ];
            if ($existing) {
                DB::table('am206_job_inspection_forms')
                    ->where('job_card_id',$job)->update($data);
            } else {
                $data['job_card_id'] = $job;
                $data['created_at'] = now();
                DB::table('am206_job_inspection_forms')->insert($data);
            }
            // Do not replace JC mechanic_id: inspector assignment is a
            // separate, locked AM206 form field.
            $this->event($job,$admin,'ASSIGN_DYNAMIC_FORM',[
                'staff_id'=>(int)$staff->id,'mode'=>$mode,'version'=>(int)$version,
                'count'=>count($selected),'hash'=>hash('sha256',$serialized),
                'reason'=>$v['reason']
            ]);
            return response()->json([
                'ok'=>true, 'contract'=>'AM288_DYNAMIC_V1', 'job_card_id'=>$job,
                'state'=>'ASSIGNED', 'staff_id'=>(int)$staff->id,
                'point_count'=>count($selected),'form_hash'=>hash('sha256',$serialized)
            ],201);
        },3);
    }

    /**
     * GET /api/v1/staff/inspections/dynamic/assigned
     * Assigned forms are independent from legacy technician_id inspection
     * lists, so the correct Staff can find the Job Card without changing its
     * mechanic assignment. Read-only and protected by Sanctum + actor().
     */
    public function mine(Request $request): JsonResponse
    {
        $user=$this->actor($request);
        $this->tables();
        $staffId=(int)($user->staff_id??0);
        if (!$user->isAdmin() && $staffId<=0)
            abort(403,'This mobile login is not linked to a Staff ID.');
        $query=DB::table('am206_job_inspection_forms as f')
            ->join('job_cards as j','j.id','=','f.job_card_id')
            ->whereIn('f.state',['ASSIGNED','COMPLETED']);
        if (!$user->isAdmin()) $query->where('f.assigned_staff_id',$staffId);
        $rows=$query->orderByDesc('f.updated_at')->limit(100)
            ->get([
                'f.job_card_id','f.assigned_staff_id','f.state','f.source',
                'f.updated_at','f.points_json',
                'j.job_no','j.vehicle_reg_no','j.customer_name',
                'j.status as job_status','j.inspection_status'
            ]);
        $items=[];
        foreach ($rows as $row) {
            $points=json_decode((string)$row->points_json,true);
            $items[]=[
                'job_card_id'=>(int)$row->job_card_id,
                'job_no'=>(string)($row->job_no??''),
                'vehicle_reg_no'=>(string)($row->vehicle_reg_no??''),
                'customer_name'=>(string)($row->customer_name??''),
                'assigned_staff_id'=>(int)$row->assigned_staff_id,
                'state'=>(string)$row->state,'mode'=>(string)$row->source,
                'point_count'=>is_array($points)?count($points):0,
                'job_status'=>(string)$row->job_status,
                'inspection_status'=>(string)$row->inspection_status,
                'updated_at'=>(string)$row->updated_at
            ];
        }
        return response()->json([
            'ok'=>true,'contract'=>'AM288_DYNAMIC_V1','items'=>$items,
            'count'=>count($items)
        ]);
    }

    /**
     * Admin-only existing AM189 repair workflow for the SAME Job Card.
     * The AM189 service owns transaction locks, staff validation, safety
     * independence and append-only defect audit. No bespoke status update.
     */
    public function repair(Request $request,int $job,int $defect,
                           \App\Services\InspectionQcGuard $guard): JsonResponse
    {
        $admin=$this->admin($request);
        $this->tables();
        $record=DB::table('am189_inspection_defects')
            ->where('job_card_id',$job)->where('id',$defect)->first();
        abort_unless($record,404,'Defect does not belong to this Job Card.');
        $v=$request->validate([
            'repair_staff_id'=>['required','integer','min:1'],
            'repair_note'=>['required','string','min:8','max:2000']
        ]);
        $actualJob=$guard->repair($defect,(int)$admin->id,
            (int)$v['repair_staff_id'],(string)$v['repair_note']);
        abort_unless($actualJob===$job,409,'AM189 repair Job Card mismatch.');
        return response()->json([
            'ok'=>true,'contract'=>'AM288_DYNAMIC_V1','job_card_id'=>$job,
            'defect_id'=>$defect,'status'=>'RECTIFIED',
            'message'=>'AM189 repair recorded. Independent verification still required.'
        ]);
    }

    /**
     * Admin-only independent AM189 verification; FAIL -> OPEN, PASS -> VERIFIED.
     * Existing AM189 service blocks the repair technician or same safety
     * repair Admin from self-verifying where independent safety review applies.
     */
    public function verify(Request $request,int $job,int $defect,
                           \App\Services\InspectionQcGuard $guard): JsonResponse
    {
        $admin=$this->admin($request);
        $this->tables();
        $record=DB::table('am189_inspection_defects')
            ->where('job_card_id',$job)->where('id',$defect)->first();
        abort_unless($record,404,'Defect does not belong to this Job Card.');
        $v=$request->validate([
            'result'=>['required','in:PASS,FAIL'],
            'verification_note'=>['required','string','min:8','max:2000']
        ]);
        $pass=$v['result']==='PASS';
        $actualJob=$guard->verify($defect,(int)$admin->id,
            (int)($admin->staff_id??0),$pass,(string)$v['verification_note']);
        abort_unless($actualJob===$job,409,'AM189 verification Job Card mismatch.');
        return response()->json([
            'ok'=>true,'contract'=>'AM288_DYNAMIC_V1','job_card_id'=>$job,
            'defect_id'=>$defect,'status'=>$pass?'VERIFIED':'OPEN',
            'message'=>$pass?'Independent AM189 verification recorded.':
                'AM189 verification FAILED; defect returned to OPEN.'
        ]);
    }

    /** GET /api/v1/staff/inspections/{job}/dynamic */
    public function show(Request $request,int $job): JsonResponse
    {
        $user = $this->actor($request);
        $this->tables();
        $card = $this->card($job);
        $form = $this->form($job);
        abort_unless($this->canView($user,$form),403,
            'Inspection is assigned to a different Staff ID.');
        $saved = DB::table('am288_inspection_answers')
            ->where('job_card_id',$job)->orderBy('position')->get();
        return response()->json([
            'ok'=>true,'contract'=>'AM288_DYNAMIC_V1',
            'job'=>[
                'id'=>$job,
                'job_no'=>(string)($card->job_no ?? ''),
                'vehicle_reg_no'=>(string)($card->vehicle_reg_no ?? ''),
                'customer_name'=>(string)($card->customer_name ?? ''),
                'status'=>(string)($card->status ?? ''),
                'inspection_status'=>(string)($card->inspection_status ?? '')
            ],
            'form'=>[
                'mode'=>(string)$form->source,
                'state'=>(string)$form->state,
                'assigned_staff_id'=>(int)$form->assigned_staff_id,
                'form_hash'=>$this->fingerprint($form),
                'points'=>$this->points($form)
            ],
            'answers'=>$saved,
            'submitted'=>(string)$form->state==='COMPLETED'
        ]);
    }

    /**
     * POST /api/v1/staff/inspections/{job}/dynamic
     * No automatic re-submission, no update of historical inspection answers.
     * All writes are within one job-locked transaction.
     */
    public function submit(Request $request,int $job): JsonResponse
    {
        $user=$this->actor($request);
        $this->tables();
        $v=$request->validate([
            'form_hash'=>['required','string','regex:/^[a-f0-9]{64}$/D'],
            'items'=>['required','array','min:1','max:100'],
            'items.*.point_key'=>['required','string','max:40'],
            'items.*.value'=>['nullable','string','max:80'],
            'items.*.remarks'=>['nullable','string','max:500']
        ]);
        return DB::transaction(function () use ($job,$user,$v) {
            $card=$this->card($job,true);
            $form=$this->form($job,true);
            abort_unless($this->canView($user,$form),403,
                'Not the assigned inspector.');
            abort_unless((string)$form->state==='ASSIGNED',409,
                'Inspection already completed or no longer editable.');
            abort_unless(strtolower((string)$card->status)==='open' &&
                strtolower((string)$card->inspection_status)==='pending',409,
                'Job Card is no longer inspection pending.');
            abort_unless(hash_equals($this->fingerprint($form),$v['form_hash']),409,
                'Assigned form version changed; reload first.');
            abort_unless(DB::table('job_card_inspection')
                ->where('job_card_id',$job)->count()===0,409,
                'Legacy inspection already exists.');
            abort_unless(DB::table('am288_inspection_answers')
                ->where('job_card_id',$job)->count()===0,409,
                'Dynamic inspection has already been submitted.');
            abort_unless(DB::table('am189_inspection_defects')
                ->where('job_card_id',$job)->count()===0,409,
                'Existing audited defect history blocks overwrite.');

            $points=$this->points($form);
            $items=$v['items'];
            if (count($items)!==count($points))
                throw ValidationException::withMessages([
                    'items'=>'Submit every assigned point in exact snapshot order.'
                ]);
            $types=Am206InspectionMaster::TYPE_OPTIONS;
            $answers=[];$findings=[];
            foreach ($points as $i=>$point) {
                $given=$items[$i];
                if (($given['point_key']??'')!==$point['key'])
                    throw ValidationException::withMessages([
                        'items'=>'Wrong point key/order at index '.($i+1)
                    ]);
                $raw=trim((string)($given['value']??''));
                $value=strtolower($raw);
                $remark=trim((string)($given['remarks']??''));
                if ($value==='' && !empty($point['required']))
                    throw ValidationException::withMessages([
                        'items'=>'Required answer missing: '.$point['label']
                    ]);
                if ($value!=='') {
                    if ($point['type']==='reading') {
                        if (!preg_match('/^-?\d{1,9}(?:\.\d{1,3})?$/D',$value))
                            throw ValidationException::withMessages([
                                'items'=>'Enter a numeric reading: '.$point['label']
                            ]);
                    } elseif (!in_array($value,$types[$point['type']]??[],true)) {
                        throw ValidationException::withMessages([
                            'items'=>'Invalid option: '.$point['label']
                        ]);
                    }
                }
                $defect=$value!=='' && in_array($value,$point['defect_values']??[],true);
                if ($defect && mb_strlen($remark)<3)
                    throw ValidationException::withMessages([
                        'items'=>'Defect observation required: '.$point['label']
                    ]);
                $answers[]=[
                    'job_card_id'=>$job,'position'=>$i+1,
                    'point_key'=>$point['key'],'point_label'=>$point['label'],
                    'point_type'=>$point['type'],'value'=>$value,
                    'remarks'=>$remark,'is_defect'=>$defect?1:0,
                    'created_at'=>now(),'updated_at'=>now()
                ];
                if ($defect) $findings[]=['point'=>$point,'value'=>$value,'remark'=>$remark];
            }
            DB::table('am288_inspection_answers')->insert($answers);
            foreach ($findings as $finding) {
                $p=$finding['point'];
                $pointKey=sha1(mb_strtolower(trim($p['label'])));
                $defectId=DB::table('am189_inspection_defects')->insertGetId([
                    'job_card_id'=>$job,'point_key'=>$pointKey,
                    'point_label'=>$p['label'],
                    'original_condition'=>$finding['value'],
                    'original_observation'=>$finding['remark'],
                    'severity'=>!empty($p['critical'])?'safety':'normal',
                    'status'=>'OPEN','created_at'=>now(),'updated_at'=>now()
                ]);
                DB::table('am189_inspection_defect_audits')->insert([
                    'job_card_id'=>$job,'defect_id'=>$defectId,
                    'actor_admin_id'=>(int)$form->configured_by_admin_id,
                    'action'=>'REGISTERED_TYPED','before_json'=>null,
                    'after_json'=>json_encode([
                        'status'=>'OPEN','original_value'=>$finding['value'],
                        'original_note'=>$finding['remark'],
                        'submitted_by_user_id'=>(int)$user->id,
                        'submitted_by_staff_id'=>(int)($user->staff_id??0),
                        'type'=>$p['type'],'key'=>$p['key']
                    ],JSON_THROW_ON_ERROR),
                    'created_at'=>now()
                ]);
            }
            DB::table('am206_job_inspection_forms')
                ->where('job_card_id',$job)->update([
                    'state'=>'COMPLETED','updated_at'=>now()
                ]);
            DB::table('job_cards')->where('id',$job)->update([
                'inspection_status'=>'completed',
                'customer_public_status'=>'Vehicle Inspection Completed',
                'updated_at'=>now()
            ]);
            $this->event($job,$user,'COMPLETE_DYNAMIC_INSPECTION',[
                'form_hash'=>$v['form_hash'],'points'=>count($answers),
                'open_defects'=>count($findings)
            ]);
            return response()->json([
                'ok'=>true,'contract'=>'AM288_DYNAMIC_V1',
                'job_card_id'=>$job,'inspection_status'=>'completed',
                'state'=>'COMPLETED','saved_points'=>count($answers),
                'open_defects'=>count($findings)
            ],201);
        },3);
    }
}
