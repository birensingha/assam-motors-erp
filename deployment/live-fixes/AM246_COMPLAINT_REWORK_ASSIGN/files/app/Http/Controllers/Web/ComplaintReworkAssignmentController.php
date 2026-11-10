<?php
declare(strict_types=1);
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class ComplaintReworkAssignmentController extends Controller
{
 private function admin(Request $r): ErpAuthUser {
  $u=$r->user();
  abort_unless($u instanceof ErpAuthUser && $u->active && $u->isAdmin(),403,'Active Admin required');
  foreach (['am_rot_work_complaints','am_complaint_rework_links','am_complaint_rework_audits',
            'am_complaint_staff_notifications','rot_sessions','job_cards','staff'] as $t)
   abort_unless(Schema::hasTable($t),503,'AM246 rework schema not ready: '.$t);
  return $u;
 }
 private function case(int $id,bool $lock=false): object {
  $q=DB::table('am_rot_work_complaints')->where('id',$id);
  $row=($lock?$q->lockForUpdate():$q)->first();
  abort_unless($row,404,'Complaint not found');
  return $row;
 }
 private function verify(object $c): void {
  $j=DB::table('job_cards')->where('id',$c->job_card_id)->first();
  abort_unless($j,404);
  if(in_array(strtolower(trim((string)$j->status)),['closed','invoiced','cancelled'],true))
   throw ValidationException::withMessages(['job'=>'Rework requires an open Job Card; complete financial reopening first.']);
  $o=DB::table('rot_sessions')->where('id',(string)$c->rot_session_id)
    ->where('job_card_id',$c->job_card_id)->first();
  if(!$o || strtolower(trim((string)$o->status))!=='completed'
    || (int)$o->staff_id!==(int)$c->staff_id)
   throw ValidationException::withMessages(['rot'=>'Original Completed ROT and its mechanic must be preserved.']);
 }
 public function show(Request $r,int $complaint) {
  $this->admin($r);
  $case=$this->case($complaint);
  $job=DB::table('job_cards')->where('id',$case->job_card_id)->first();
  $link=DB::table('am_complaint_rework_links')->where('complaint_id',$complaint)->first();
  $staff=DB::table('staff')->orderBy('name')->get(['id','name','staff_code']);
  $receiver=$link?DB::table('staff')->where('id',$link->assigned_staff_id)->first():null;
  $actions=DB::table('am_complaint_rework_audits')->where('complaint_id',$complaint)->orderByDesc('id')->limit(20)->get();
  $candidateRots=collect();
  if($link && !$link->rework_rot_session_id)
   $candidateRots=DB::table('rot_sessions')->where('job_card_id',$case->job_card_id)
    ->where('staff_id',$link->assigned_staff_id)->whereIn('status',['ASSIGNED','Assigned','assigned'])
    ->where('id','!=',(string)$case->rot_session_id)->orderByDesc('id')->limit(30)->get();
  $canAssign=$job && !in_array(strtolower(trim((string)$job->status)),['closed','invoiced','cancelled'],true);
  return view('work-complaints.rework-assign',compact('case','job','link','staff','receiver','actions','candidateRots','canAssign'));
 }
 public function assign(Request $r,int $complaint) {
  $admin=$this->admin($r);
  $d=$r->validate(['assigned_staff_id'=>['required','integer','min:1'],
    'instructions'=>['required','string','min:12','max:3000']]);
  DB::transaction(function()use($complaint,$admin,$d){
   $c=$this->case($complaint,true);$this->verify($c);
   if((int)$d['assigned_staff_id']===(int)$c->staff_id)
    throw ValidationException::withMessages(['staff'=>'Select a different mechanic for the rework.']);
   $q=DB::table('staff')->where('id',(int)$d['assigned_staff_id']);
   if(Schema::hasColumn('staff','status'))
    $q->whereRaw("LOWER(TRIM(COALESCE(status,'active')))='active'");
   $staff=$q->first();
   if(!$staff) throw ValidationException::withMessages(['staff'=>'Select an existing active mechanic.']);
   if(DB::table('am_complaint_rework_links')->where('complaint_id',$complaint)->exists())
    throw ValidationException::withMessages(['rework'=>'A rework mechanic is already assigned to this complaint.']);
   $id=DB::table('am_complaint_rework_links')->insertGetId([
    'complaint_id'=>$complaint,'job_card_id'=>$c->job_card_id,
    'original_rot_session_id'=>(string)$c->rot_session_id,'original_staff_id'=>$c->staff_id,
    'rework_rot_session_id'=>null,'assigned_staff_id'=>$staff->id,
    'assigned_by_admin_id'=>$admin->id,'instructions'=>trim($d['instructions']),
    'status'=>'ASSIGNED','assigned_at'=>now('Asia/Kolkata'),
    'created_at'=>now(),'updated_at'=>now()]);
   foreach([
    [(int)$c->staff_id,'ORIGINAL_WORK_COMPLAINT','Your original completed ROT has a separate complaint rework assignment. This does not change your original ROT.'],
    [(int)$staff->id,'COMPLAINT_REWORK_ASSIGNED','Complaint #'.$complaint.' assigned to you. Open Staff Portal for rework instructions. New ROT must be linked for timer.'],
   ] as [$staffId,$type,$message])
    DB::table('am_complaint_staff_notifications')->insert([
     'complaint_id'=>$complaint,'rework_link_id'=>$id,'recipient_staff_id'=>$staffId,
     'notification_type'=>$type,'title'=>str_replace('_',' ',$type),
     'message'=>$message,'created_at'=>now(),'updated_at'=>now()]);
   DB::table('am_complaint_rework_audits')->insert([
    'complaint_id'=>$complaint,'rework_link_id'=>$id,'actor_admin_id'=>$admin->id,
    'action'=>'REWORK_MECHANIC_ASSIGNED',
    'event_json'=>json_encode(['original_rot_session_id'=>(string)$c->rot_session_id,
     'original_staff_id'=>(int)$c->staff_id,'assigned_staff_id'=>(int)$staff->id,
     'instructions'=>trim($d['instructions']),'billing'=>'NO_NEW_BILLING'],JSON_THROW_ON_ERROR),
    'created_at'=>now()]);
  });
  return redirect()->route('erp.rot-work-complaints.rework.show',$complaint)
    ->with('success','New mechanic assigned. Create a separate ROT using existing Job Card controls, then link it below.');
 }
 public function linkRot(Request $r,int $complaint) {
  $admin=$this->admin($r);
  $d=$r->validate(['rework_rot_session_id'=>['required','string','max:191']]);
  DB::transaction(function()use($complaint,$admin,$d){
   $c=$this->case($complaint,true);$this->verify($c);
   $l=DB::table('am_complaint_rework_links')->where('complaint_id',$complaint)->lockForUpdate()->first();
   if(!$l) throw ValidationException::withMessages(['rework'=>'Assign a mechanic first.']);
   if($l->rework_rot_session_id) throw ValidationException::withMessages(['rot'=>'Rework ROT is already linked.']);
   $rotId=trim($d['rework_rot_session_id']);
   if($rotId===(string)$c->rot_session_id)
    throw ValidationException::withMessages(['rot'=>'Original ROT cannot be linked as its own rework.']);
   $rot=DB::table('rot_sessions')->where('id',$rotId)->lockForUpdate()->first();
   if(!$rot || (int)$rot->job_card_id!==(int)$c->job_card_id
    || (int)$rot->staff_id!==(int)$l->assigned_staff_id
    || strtolower(trim((string)$rot->status))!=='assigned')
    throw ValidationException::withMessages(['rot'=>'Only a separate, not-started ASSIGNED ROT on the same Job Card and selected mechanic can be linked.']);
   if(DB::table('am_complaint_rework_links')->where('rework_rot_session_id',$rotId)->exists())
    throw ValidationException::withMessages(['rot'=>'ROT already linked to another complaint.']);
   DB::table('am_complaint_rework_links')->where('id',$l->id)
    ->update(['rework_rot_session_id'=>$rotId,'status'=>'ROT_LINKED','updated_at'=>now()]);
   DB::table('am_complaint_rework_audits')->insert([
    'complaint_id'=>$complaint,'rework_link_id'=>$l->id,'actor_admin_id'=>$admin->id,
    'action'=>'EXISTING_REWORK_ROT_LINKED',
    'event_json'=>json_encode(['rework_rot_session_id'=>$rotId,
      'assigned_staff_id'=>(int)$l->assigned_staff_id,
      'original_rot_session_id'=>(string)$c->rot_session_id,
      'billing'=>'UNCHANGED'],JSON_THROW_ON_ERROR),
    'created_at'=>now()]);
  });
  return redirect()->route('erp.rot-work-complaints.rework.show',$complaint)
   ->with('success','Separate ROT linked. Actual work and productivity hours will be recorded through existing native ROT controls.');
 }
}
