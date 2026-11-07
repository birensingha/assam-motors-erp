<?php
declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class RotWorkComplaintController extends Controller
{
 private function admin(Request $request): ErpAuthUser
 {
  $u=$request->user();
  abort_unless($u instanceof ErpAuthUser && $u->active && $u->isAdmin(),403,'Active ERP Admin required');
  return $u;
 }
 private function tables(): void
 {
  abort_unless(Schema::hasTable('am_rot_work_complaints') && Schema::hasTable('am_rot_work_complaint_audits'),503,'Complaint migration not applied');
  abort_unless(Schema::hasTable('rot_sessions'),503,'ROT session mapping needs review');
  foreach(['job_card_id','staff_id','status'] as $col) {
   abort_unless(Schema::hasColumn('rot_sessions',$col),503,'ROT session field not available: '.$col);
  }
 }
 private function ownedRot(int $job,int $rot): object
 {
  $row=DB::table('rot_sessions')->where('id',$rot)->where('job_card_id',$job)->first();
  abort_unless($row,404,'ROT does not belong to this Job Card');
  return $row;
 }
 public function create(Request $request,int $job,int $rot)
 {
  $this->admin($request); $this->tables();
  abort_unless(DB::table('job_cards')->where('id',$job)->exists(),404);
  $session=$this->ownedRot($job,$rot);
  abort_unless(strtolower(trim((string)$session->status))==='completed',422,'Only completed ROT complaints handled here');
  $staff=DB::table('staff')->where('id',$session->staff_id)->first();
  return view('work-complaints.create',compact('job','rot','session','staff'));
 }
 public function store(Request $request,int $job,int $rot)
 {
  $admin=$this->admin($request);$this->tables();
  $data=$request->validate(['description'=>['required','string','min:12','max:3000']]);
  $id=DB::transaction(function()use($job,$rot,$admin,$data){
   abort_unless(DB::table('job_cards')->where('id',$job)->lockForUpdate()->exists(),404);
   $session=$this->ownedRot($job,$rot);
   if(strtolower(trim((string)$session->status))!=='completed' || (int)$session->staff_id<1){
    throw ValidationException::withMessages(['rot'=>'Completed ROT must have an assigned staff member.']);
   }
   $row=[
    'job_card_id'=>$job,'rot_session_id'=>$rot,'staff_id'=>(int)$session->staff_id,
    'reported_by_admin_id'=>(int)$admin->id,'status'=>'REPORTED',
    'description'=>trim($data['description']),'reported_at'=>now('Asia/Kolkata'),
    'created_at'=>now(),'updated_at'=>now(),
   ];
   $id=DB::table('am_rot_work_complaints')->insertGetId($row);
   DB::table('am_rot_work_complaint_audits')->insert([
    'complaint_id'=>$id,'actor_admin_id'=>(int)$admin->id,'action'=>'REPORTED',
    'previous_json'=>null,'new_json'=>json_encode($row,JSON_THROW_ON_ERROR),'created_at'=>now(),
   ]);
   return $id;
  });
  return redirect()->route('erp.rot-work-complaints.show',$id)->with('success','Complaint registered; no workmanship fault has been confirmed.');
 }
 public function show(Request $request,int $complaint)
 {
  $this->admin($request); $this->tables();
  $case=DB::table('am_rot_work_complaints')->where('id',$complaint)->first();
  abort_unless($case,404);
  $staff=DB::table('staff')->where('id',$case->staff_id)->first();
  return view('work-complaints.show',compact('case','staff'));
 }
 public function review(Request $request,int $complaint)
 {
  $admin=$this->admin($request);$this->tables();
  $data=$request->validate([
   'outcome'=>['required','in:UNDER_REVIEW,WORKMANSHIP_CONFIRMED,PART_FAILURE,CUSTOMER_MISUSE,NOT_WORKMANSHIP,INCONCLUSIVE'],
   'investigation_notes'=>['required','string','min:12','max:3000'],
   'employee_response'=>['nullable','string','max:3000'],
  ]);
  DB::transaction(function()use($complaint,$admin,$data){
   $case=DB::table('am_rot_work_complaints')->where('id',$complaint)->lockForUpdate()->first();
   abort_unless($case,404);
   $before=(array)$case;
   $after=[
    'status'=>$data['outcome']==='UNDER_REVIEW'?'UNDER_REVIEW':'REVIEWED',
    'outcome'=>$data['outcome']==='UNDER_REVIEW'?null:$data['outcome'],
    'investigation_notes'=>trim($data['investigation_notes']),
    'employee_response'=>trim((string)($data['employee_response']??''))?:null,
    'reviewed_by_admin_id'=>(int)$admin->id,
    'reviewed_at'=>now('Asia/Kolkata'),'updated_at'=>now(),
   ];
   DB::table('am_rot_work_complaints')->where('id',$complaint)->update($after);
   DB::table('am_rot_work_complaint_audits')->insert([
    'complaint_id'=>$complaint,'actor_admin_id'=>(int)$admin->id,'action'=>'INVESTIGATION_REVIEW',
    'previous_json'=>json_encode($before,JSON_THROW_ON_ERROR),
    'new_json'=>json_encode($after,JSON_THROW_ON_ERROR),'created_at'=>now(),
   ]);
  });
  return redirect()->route('erp.rot-work-complaints.show',$complaint)->with('success','Investigation saved with audit.');
 }
 public function monthly(Request $request)
 {
  $this->admin($request);$this->tables();
  $data=$request->validate(['month'=>['nullable','date_format:Y-m']]);
  $month=$data['month']??now('Asia/Kolkata')->format('Y-m');
  $start=\Carbon\CarbonImmutable::createFromFormat('!Y-m',$month,'Asia/Kolkata')->startOfMonth();
  $end=$start->addMonth();
  // Reported month is the complaint reporting month, not original ROT completion month.
  $rows=DB::table('am_rot_work_complaints as c')->leftJoin('staff as s','s.id','=','c.staff_id')
   ->where('c.reported_at','>=',$start->format('Y-m-d H:i:s'))
   ->where('c.reported_at','<',$end->format('Y-m-d H:i:s'))
   ->groupBy('c.staff_id','s.name','s.staff_code')
   ->select('c.staff_id','s.name','s.staff_code')
   ->selectRaw('COUNT(*) AS reported_count')
   ->selectRaw("SUM(CASE WHEN c.outcome = 'WORKMANSHIP_CONFIRMED' THEN 1 ELSE 0 END) AS confirmed_count")
   ->selectRaw("SUM(CASE WHEN c.status IN ('REPORTED','UNDER_REVIEW') THEN 1 ELSE 0 END) AS pending_count")
   ->orderBy('s.name')->get();
  return view('work-complaints.monthly',compact('month','rows'));
 }
}
