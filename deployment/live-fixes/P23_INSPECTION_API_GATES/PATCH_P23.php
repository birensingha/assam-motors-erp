<?php
declare(strict_types=1);
/**
 * P23_MANAGER_INSPECTION_API_GATES
 * Separate inspector from deciding technician in mobile Admin and Staff APIs.
 * No posted Manager can change technician_id; old combined Diagnosis+Decision
 * mobile action is refused, directing Admin to protected sequential web route.
 */
$mode=$argv[1]??'check';$root=rtrim((string)($argv[2]??''),'/');
if(!in_array($mode,['check','apply'],true)||!is_file($root.'/artisan')){fwrite(STDERR,"Usage: php PATCH_P23.php check|apply /staging-root\n");exit(2);}
$paths=['app/Http/Controllers/Api/V1/StaffExtraController.php','app/Http/Controllers/Api/V1/OwnerWorkshopController.php'];
$src=[];
foreach($paths as $p){
 if(!is_file($root.'/'.$p)||is_link($root.'/'.$p)){fwrite(STDERR,"P23_NO_GO_FILE ".$p."\n");exit(3);}
 $src[$p]=(string)file_get_contents($root.'/'.$p);
 if(str_contains($src[$p],'P23_MANAGER_INSPECTION_API_GATES')){fwrite(STDERR,"P23_NO_GO_ALREADY_OR_PARTIAL_INSTALLED\n");exit(3);}
}
function p23Once(string $s,string $needle,string $value,string $name):string{
 $n=substr_count($s,$needle);if($n!==1)throw new RuntimeException('P23_NO_GO_ANCHOR_'.$name.'_'.$n);
 return str_replace($needle,$value,$s);
}
try {
 $staff=$src[$paths[0]];
 $listOld="DB::table('job_cards')->where('technician_id',\$staffId)";
 $listNew=<<<'PHP'
DB::table('job_cards')->whereIn('id',function($q)use($staffId){
            // P23_MANAGER_INSPECTION_API_GATES: inspector is NOT deciding technician.
            $q->select('job_card_id')->from('job_card_inspection_assignments')
              ->where('assigned_staff_id',$staffId);
        })
PHP;
 $staff=p23Once($staff,$listOld,$listNew,'staff_inspection_list');

 $jobOld="DB::table('job_cards')->where('id',\$job)->where('technician_id',\$staffId)->first();";
 if(substr_count($staff,$jobOld)!==2)throw new RuntimeException('P23_NO_GO_DETAIL_AND_SAVE_MATCHES');
 $jobNew="DB::table('job_cards')->where('id',\$job)->whereIn('id',function(\$q)use(\$staffId){\n".
 "            \$q->select('job_card_id')->from('job_card_inspection_assignments')->where('assigned_staff_id',\$staffId);\n".
 "        })->first();";
 $staff=str_replace($jobOld,$jobNew,$staff);

 $check="        abort_unless(\$jc,403,'This Job Card is not assigned to you.');\n";
 if(substr_count($staff,$check)!==2)throw new RuntimeException('P23_NO_GO_ASSIGNEE_VERIFICATION');
 $checks=<<<'PHP'
        abort_unless($jc,403,'This Job Card is not assigned to you.');
PHP;
 // Both GET and POST use a manager-only inspector identity; diagnosis technician is separate.
 $checks.="\n".<<<'PHP'
        $manager=DB::table('staff')->where('id',$staffId)->first();
        abort_unless($manager && strtolower(trim((string)$manager->role))==='manager'
            && strtolower(trim((string)$manager->status))==='active',403,'Assigned active Manager only.');
PHP;
 $checks.="\n";
 $staff=str_replace($check,$checks,$staff);
 // Make save both state-locked and assignment-locked inside the DB transaction.
 $txnAnchor="        DB::transaction(function() use(\$job,\$staffId,\$data){\n";
 $guard=<<<'PHP'
        DB::transaction(function() use($job,$staffId,$data){
            $live=DB::table('job_cards')->where('id',$job)->lockForUpdate()->first();
            $assignment=DB::table('job_card_inspection_assignments')->where('job_card_id',$job)->lockForUpdate()->first();
            if(!$live || !$assignment || (int)$assignment->assigned_staff_id!==$staffId
               || strtolower((string)$live->status)!=='open'
               || strtolower((string)$live->inspection_status)!=='pending'
               || strtolower((string)($live->diagnosis_status??'pending'))==='completed'
               || $assignment->completed_at) {
                abort(422,'Inspection must be assigned to this Manager and pending before Diagnosis.');
            }
            if(Schema::hasTable('job_card_inspection') && DB::table('job_card_inspection')->where('job_card_id',$job)->exists()){
                abort(409,'Existing inspection items must be reconciled before resubmission.');
            }
PHP;
 $staff=p23Once($staff,$txnAnchor,$guard."\n",'staff_save_transaction');
 $saveUpdate="DB::table('job_cards')->where('id',\$job)->update(['inspection_status'=>'completed','inspection_submitted_at'=>now(),'inspection_submitted_by'=>\$staffId,'diagnosis_status'=>'pending','estimate_allowed'=>0,'customer_public_status'=>'Inspection Completed']);";
 $saveNew=<<<'PHP'
DB::table('job_cards')->where('id',$job)->update([
                'inspection_status'=>'completed','inspection_submitted_at'=>now('Asia/Kolkata'),
                'inspection_submitted_by'=>$staffId,'diagnosis_status'=>'pending',
                'decision_status'=>'pending','estimate_allowed'=>0,'customer_public_status'=>'Inspection Completed',
            ]);
            DB::table('job_card_inspection_assignments')->where('job_card_id',$job)->update([
                'completed_by_staff_id'=>$staffId,'completed_at'=>now('Asia/Kolkata'),'updated_at'=>now('Asia/Kolkata'),
            ]);
PHP;
 $staff=p23Once($staff,$saveUpdate,$saveNew,'staff_submit_lock_and_audit');

 $owner=$src[$paths[1]];
 $assignStart=strpos($owner,"    public function assignInspection(Request \$request, int \$job): JsonResponse");
 $diagStart=strpos($owner,"    public function diagnosis(Request \$request, int \$job): JsonResponse");
 if($assignStart===false||$diagStart===false||$diagStart<=$assignStart || $diagStart-$assignStart>6000){
    throw new RuntimeException('P23_NO_GO_OWNER_METHOD_BOUNDARY');
 }
 $oldMethod=substr($owner,$assignStart,$diagStart-$assignStart);
 if(!str_contains($oldMethod,"'technician_id'") || !str_contains($oldMethod,'INSPECTION_TECHNICIAN_ASSIGN')){
    throw new RuntimeException('P23_NO_GO_OWNER_OLD_ASSIGNMENT_BODY');
 }
 $newMethod=<<<'PHP'
    /** P23_MANAGER_INSPECTION_API_GATES: mobile Owner/Admin assignment separate from technician. */
    public function assignInspection(Request $request, int $job): JsonResponse
    {
        $user=$this->admin($request);
        $data=$request->validate(['staff_id'=>['required','integer','min:1'],
            'reason'=>['required','string','min:8','max:500']]);
        $staff=$this->activeStaff((int)$data['staff_id']);
        if(strtolower(trim((string)$staff->role))!=='manager'){
            throw ValidationException::withMessages(['staff_id'=>'Vehicle Inspection can only be assigned to an ACTIVE Manager.']);
        }
        DB::transaction(function() use($job,$data,$staff,$user){
            $card=DB::table('job_cards')->where('id',$job)->lockForUpdate()->first();
            abort_unless($card,404);
            if(strtolower((string)$card->status)!=='open'
                || strtolower((string)$card->inspection_status)!=='pending'
                || strtolower((string)($card->diagnosis_status??'pending'))==='completed'){
                throw ValidationException::withMessages(['inspection'=>'Only OPEN and PENDING Inspection can be assigned.']);
            }
            $table='job_card_inspection_assignments';
            $a=DB::table($table)->where('job_card_id',$job)->lockForUpdate()->first();
            if($a && $a->completed_at)throw ValidationException::withMessages(['inspection'=>'Completed Inspection cannot be reassigned.']);
            $now=now('Asia/Kolkata');
            if($a){
                DB::table($table)->where('id',$a->id)->update([
                    'assigned_staff_id'=>$staff->id,'assigned_by_user_id'=>$user->id,
                    'assigned_at'=>$now,'updated_at'=>$now,
                ]);
            }else{
                DB::table($table)->insert([
                    'job_card_id'=>$job,'assigned_staff_id'=>$staff->id,
                    'assigned_by_user_id'=>$user->id,'assigned_at'=>$now,
                    'created_at'=>$now,'updated_at'=>$now,
                ]);
            }
            $this->audit($user,'INSPECTION_MANAGER_ASSIGNED','JOB_CARD',(string)$job,
                $job,(int)$staff->id,$data['reason'],(array)$card,(array)$card);
        },3);
        return response()->json(['ok'=>true,'assigned_manager_staff_id'=>$staff->id,
            'message'=>'Inspection assigned to Manager without changing the Deciding Technician.']);
    }

PHP;
 $owner=substr_replace($owner,$newMethod,$assignStart,$diagStart-$assignStart);
 $needle="        \$before = (array)\$this->job(\$job);\n";
 // Method-specific injection AFTER validation but before any mutation.
 $diag=strpos($owner,"    public function diagnosis(Request \$request, int \$job): JsonResponse");
 $diagEnd=strpos($owner,"    public function rotAction(", $diag);
 if($diag===false||$diagEnd===false)throw new RuntimeException('P23_NO_GO_MOBILE_DIAG_BOUNDARY');
 $part=substr($owner,$diag,$diagEnd-$diag);
 $part=p23Once($part,$needle,<<<'PHP'
        // P23_MANAGER_INSPECTION_API_GATES: prevent legacy mobile single-step
        // Final Diagnosis + Technician Decision from bypassing mandatory stages.
        if(($data['action']??'')==='COMPLETE'){
            throw ValidationException::withMessages([
                'diagnosis'=>'Combined Diagnosis + Technician Decision is disabled. Complete Final Diagnosis in Web ERP, then assigned Technician must decide from Staff Portal.',
            ]);
        }
        $before = (array)$this->job($job);
PHP
 ."\n",'mobile_combined_diagnosis_stop');
 $owner=substr_replace($owner,$part,$diag,$diagEnd-$diag);

 $out=[$paths[0]=>$staff,$paths[1]=>$owner];
 token_get_all($staff,TOKEN_PARSE);
 token_get_all($owner,TOKEN_PARSE);
 echo "P23_SOURCE_PHP_PARSER_PASS\n";
 foreach($paths as $p)echo 'P23_HASH '.$p.' OLD='.hash('sha256',$src[$p]).' NEW='.hash('sha256',$out[$p])."\n";
}catch(Throwable $e){fwrite(STDERR,'P23_NO_GO_PREFLIGHT '.$e->getMessage()."\n");exit(5);}
if($mode==='check'){echo "P23_DRY_RUN_NO_SOURCE_OR_DATA_MUTATIONS\n";exit(0);}
$bak=$root.'/storage/app/p23_inspection_api_backup_'.date('Ymd_His').'_'.getmypid();
if(!mkdir($bak,0700,true))exit(6);
foreach($paths as $p){
 $copy=$bak.'/'.str_replace('/','__',$p);
 if(!copy($root.'/'.$p,$copy)||hash_file('sha256',$copy)!==hash('sha256',$src[$p])){
    fwrite(STDERR,"P23_NO_GO_BACKUP_VERIFY\n");exit(6);
 }
}
$written=[];
try{
 foreach($paths as $p){
    $dest=$root.'/'.$p;
    if(hash_file('sha256',$dest)!==hash('sha256',$src[$p]))throw new RuntimeException('Concurrent source modification '.$p);
    $tmp=tempnam(dirname($dest),'.p23_');
    if(!$tmp||file_put_contents($tmp,$out[$p],LOCK_EX)!==strlen($out[$p]))throw new RuntimeException('Temp write '.$p);
    chmod($tmp,fileperms($dest)&0777);
    if(!rename($tmp,$dest))throw new RuntimeException('Atomic replace '.$p);
    $written[]=$p;
    if(hash_file('sha256',$dest)!==hash('sha256',$out[$p]))throw new RuntimeException('Hash '.$p);
 }
 echo 'P23_APPLIED_BACKUP='.$bak."\n";
 echo "P23_API_SOURCE_ONLY_NO_INSPECTION_OR_DIAGNOSIS_RECORDS_CHANGED\n";
}catch(Throwable $e){
 foreach(array_reverse($written) as $p)copy($bak.'/'.str_replace('/','__',$p),$root.'/'.$p);
 fwrite(STDERR,'P23_SOURCE_ROLLBACK '.$e->getMessage()."\n");exit(7);
}
