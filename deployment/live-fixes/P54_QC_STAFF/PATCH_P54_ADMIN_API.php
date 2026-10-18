<?php
declare(strict_types=1);
$mode=(string)($argv[1]??'check');
$root=rtrim((string)($argv[2]??''),'/');
if(!in_array($mode,['check','apply'],true)||!is_file($root.'/artisan')){fwrite(STDERR,"P54_API_USAGE check|apply ROOT\n");exit(2);}
$file="$root/app/Http/Controllers/Api/V1/OwnerWorkshopController.php";
if(!is_file($file)||is_link($file)){fwrite(STDERR,"P54_API_NO_GO_CONTROLLER_MISSING\n");exit(3);}
$src=(string)file_get_contents($file);
if(str_contains($src,'P54_API_QC_ASSIGNED_STAFF_ONLY')){echo "P54_API_ALREADY_GUARDED\n";exit(0);}
$start=strpos($src,'function qc(Request $request, int $job): JsonResponse');
$end=$start===false?false:strpos($src,'public function roadTest(',$start);
if($start===false||$end===false||$end<=$start){fwrite(STDERR,"P54_API_NO_GO_METHOD_BOUNDARY\n");exit(4);}
$method=substr($src,$start,$end-$start);
$anchor=<<<'PHP'
        $id=DB::table('workshop_qc_checks')->insertGetId([
PHP;
if(substr_count($method,$anchor)!==1||!str_contains($method,"'result'=>['required','in:PENDING,PASS,FAIL']")) {
 fwrite(STDERR,"P54_API_NO_GO_EXPECTED_QC_SOURCE\n");exit(4);
}
$guard=<<<'PHP'
        // P54_API_QC_ASSIGNED_STAFF_ONLY:
        // Admin/Owner mobile control can assign QC but must not bypass staff
        // login identity by directly recording PASS/FAIL against any name.
        if($data['result']!=='PENDING'){
            throw \Illuminate\Validation\ValidationException::withMessages([
                'result'=>'QC PASS/FAIL must be submitted by the single assigned QC staff member through the Staff QC Portal. Open /staff/qc/{job}.'
            ]);
        }
        $staffId=(int)($data['assigned_staff_id']??0);
        if($staffId<=0){
            throw \Illuminate\Validation\ValidationException::withMessages([
                'assigned_staff_id'=>'Admin must assign an active Staff member before starting Quality Check.'
            ]);
        }
        $linked=DB::table('staff')->where('id',$staffId)
            ->whereRaw("LOWER(COALESCE(status,'active'))='active'")->exists()
          && DB::table('erp_auth_users')->where('staff_id',$staffId)->where('active',1)->exists();
        if(!$linked){
            throw \Illuminate\Validation\ValidationException::withMessages([
                'assigned_staff_id'=>'Selected staff must be active and have an active Staff Portal login.'
            ]);
        }
        $card=DB::table('job_cards')->where('id',$job)->first();
        if(!$card || strtolower(trim((string)$card->status))!=='completed'
            || !str_contains(strtolower((string)$card->customer_public_status),'work complete')){
            throw \Illuminate\Validation\ValidationException::withMessages([
                'status'=>'Work Complete must be recorded before assigning Quality Check.'
            ]);
        }
        $latest=DB::table('workshop_qc_checks')->where('job_card_id',$job)
            ->orderByDesc('id')->first();
        if($latest && strtoupper(trim((string)$latest->result))==='PENDING'){
            throw \Illuminate\Validation\ValidationException::withMessages([
                'status'=>'QC is already assigned and pending. It can only be completed by its assigned staff.'
            ]);
        }

PHP;
$method=str_replace($anchor,$guard.$anchor,$method);
$next=substr($src,0,$start).$method.substr($src,$end);
try{token_get_all($next,TOKEN_PARSE);echo "P54_API_GUARDED_SOURCE_PHP_PARSE_PASS\n";}catch(Throwable $e){fwrite(STDERR,"P54_API_PARSE_NO_GO ".$e->getMessage()."\n");exit(5);}
if($mode==='check'){echo "P54_API_DRY_RUN_PASS_NO_BUSINESS_WRITES\n";exit(0);}
$backup="$root/storage/app/p54_admin_qc_api_backup_".date('Ymd_His').'_'.getmypid().'.php';
if(!copy($file,$backup)||hash_file('sha256',$backup)!==hash('sha256',$src)){fwrite(STDERR,"P54_API_BACKUP_NO_GO\n");exit(6);}
if(hash_file('sha256',$file)!==hash('sha256',$src)){fwrite(STDERR,"P54_API_CONCURRENT_SOURCE_CHANGE\n");exit(7);}
$tmp=tempnam(dirname($file),'.p54qc_');
if(!$tmp||file_put_contents($tmp,$next,LOCK_EX)!==strlen($next)
   ||!chmod($tmp,fileperms($file)&0777)||!rename($tmp,$file)){
 if($tmp)@unlink($tmp);
 copy($backup,$file);fwrite(STDERR,"P54_API_WRITE_ROLLBACK\n");exit(8);
}
echo "P54_API_ASSIGNED_STAFF_ONLY_QC_APPLIED\nP54_API_BACKUP=$backup\n";
