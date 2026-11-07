<?php
declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * AM241: link a genuinely created NEW rework session to existing Completed ROT.
 * Does not mutate original ROT or create an unverified ROT from an unknown schema.
 */
final class ComplaintReworkLinkService
{
 public function link(int $complaintId,int $newSessionId,int $assignedStaffId,int $adminId,string $instructions): int
 {
  $instructions=trim($instructions);
  if (mb_strlen($instructions)<12 || mb_strlen($instructions)>3000) {
   throw ValidationException::withMessages(['instructions'=>'Provide actual rework instructions (12–3000 characters).']);
  }
  foreach(['am_rot_work_complaints','am_complaint_rework_links','am_complaint_staff_notifications','am_complaint_rework_audits','rot_sessions'] as $t){
   abort_unless(Schema::hasTable($t),503,'Missing AM241 table '.$t);
  }
  return DB::transaction(function()use($complaintId,$newSessionId,$assignedStaffId,$adminId,$instructions):int{
   $case=DB::table('am_rot_work_complaints')->where('id',$complaintId)->lockForUpdate()->first();
   if (!$case) abort(404);
   $original=DB::table('rot_sessions')->where('id',$case->rot_session_id)->first();
   $new=DB::table('rot_sessions')->where('id',$newSessionId)->lockForUpdate()->first();
   if (!$original || !$new || (int)$original->job_card_id!==(int)$case->job_card_id
       || strtolower((string)$original->status)!=='completed') {
    throw ValidationException::withMessages(['rot'=>'Original complaint ROT must remain Completed and belong to this Job Card.']);
   }
   if ($newSessionId===(int)$original->id
       || (int)$new->job_card_id!==(int)$case->job_card_id
       || (int)$new->staff_id!==$assignedStaffId
       || strtolower((string)$new->status)==='completed') {
    throw ValidationException::withMessages(['rot'=>'Choose a separate, non-completed new ROT session assigned to the selected mechanic on the SAME Job Card.']);
   }
   if (!DB::table('staff')->where('id',$assignedStaffId)
       ->whereRaw("LOWER(COALESCE(status,'active'))='active'")->exists()) {
    throw ValidationException::withMessages(['assigned_staff_id'=>'Select an active mechanic.']);
   }
   $already=DB::table('am_complaint_rework_links')
       ->where('rework_rot_session_id',$newSessionId)->exists();
   if ($already) throw ValidationException::withMessages(['rot'=>'Rework ROT already linked to another complaint.']);
   $now=now('Asia/Kolkata');
   $link=DB::table('am_complaint_rework_links')->insertGetId([
    'complaint_id'=>$complaintId,'job_card_id'=>$case->job_card_id,
    'original_rot_session_id'=>$case->rot_session_id,
    'original_staff_id'=>$case->staff_id,
    'rework_rot_session_id'=>$newSessionId,
    'assigned_staff_id'=>$assignedStaffId,
    'assigned_by_admin_id'=>$adminId,'status'=>'ASSIGNED',
    'instructions'=>$instructions,'assigned_at'=>$now,
    'created_at'=>now(),'updated_at'=>now(),
   ]);
   $people=[
    [(int)$case->staff_id,'ORIGINAL_WORK_COMPLAINT',
     'Complaint reported for your completed ROT',
     'A complaint concerning your completed work has been assigned for rework. This is not a confirmed workmanship fault.'],
    [$assignedStaffId,'COMPLAINT_REWORK_ASSIGNED',
     'Complaint rework assigned',
     'You were assigned a complaint rework ROT linked to a previously completed job. Read the complaint and work instructions.'],
   ];
   foreach($people as [$staff,$type,$title,$message]) {
    DB::table('am_complaint_staff_notifications')->insert([
     'complaint_id'=>$complaintId,'rework_link_id'=>$link,'recipient_staff_id'=>$staff,
     'notification_type'=>$type,'title'=>$title,'message'=>$message,
     'created_at'=>now(),'updated_at'=>now(),
    ]);
   }
   DB::table('am_complaint_rework_audits')->insert([
    'complaint_id'=>$complaintId,'rework_link_id'=>$link,'actor_admin_id'=>$adminId,
    'action'=>'REWORK_LINKED','event_json'=>json_encode([
     'original_rot_session_id'=>$case->rot_session_id,
     'new_rot_session_id'=>$newSessionId,'original_staff_id'=>$case->staff_id,
     'assigned_staff_id'=>$assignedStaffId,'instructions'=>$instructions,
    ],JSON_THROW_ON_ERROR),'created_at'=>now(),
   ]);
   return $link;
  });
 }

 /** Only current staff inbox; unconfirmed reports never automatically score as faults. */
 public function inbox(int $staffId)
 {
  return DB::table('am_complaint_staff_notifications as n')
   ->join('am_rot_work_complaints as c','c.id','=','n.complaint_id')
   ->join('am_complaint_rework_links as l','l.id','=','n.rework_link_id')
   ->where('n.recipient_staff_id',$staffId)
   ->orderByDesc('n.id')
   ->get(['n.id','n.title','n.message','n.notification_type','n.read_at',
    'c.id as complaint_id','c.status as complaint_status','c.outcome',
    'l.job_card_id','l.original_rot_session_id','l.rework_rot_session_id',
    'l.assigned_staff_id','l.original_staff_id','l.instructions']);
 }
}
