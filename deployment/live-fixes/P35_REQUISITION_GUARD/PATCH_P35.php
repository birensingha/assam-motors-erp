<?php
declare(strict_types=1);
/** P35_WORK_COMPLETE_REQUISITION_GUARD: requisitioned Part is unfinished until Fitted. */
$mode=$argv[1]??'check';$root=rtrim((string)($argv[2]??''),'/');
if(!in_array($mode,['check','apply'],true)||!is_file($root.'/artisan'))exit(2);
$file=$root.'/app/Http/Controllers/Web/AdminJobCardLifecyclePageController.php';
if(!is_file($file)||is_link($file))exit(3);
$before=(string)file_get_contents($file);
if(str_contains($before,'P35_WORK_COMPLETE_REQUISITION_GUARD')){echo "P35_ALREADY_INSTALLED\n";exit(0);}
$old="LOWER(TRIM(COALESCE(issue_status,''))) IN ('','planned','pending','not issued','not_issued','issued')";
$replace="LOWER(TRIM(COALESCE(issue_status,''))) IN ('','planned','pending','not issued','not_issued','requisitioned','issued')";
if(substr_count($before,$old)!==1 || !str_contains($before,'private function unfinishedWork(')){
 fwrite(STDERR,"P35_NO_GO_UNEXPECTED_LIFECYCLE_SOURCE\n");exit(4);
}
$after=str_replace($old,$replace,$before);
$after=str_replace("    private function unfinishedWork(int \$job): array","    // P35_WORK_COMPLETE_REQUISITION_GUARD: requested stock is unfinished until issued and fitted.\n    private function unfinishedWork(int \$job): array",$after);
try{token_get_all($after,TOKEN_PARSE);
 if(!str_contains($after,'P34_ROT_BILLING_CONTROL') || !str_contains($after,"if(\$labourTotal<=0")){
   throw new RuntimeException('Invoice/ROT billing guarded source missing');
 }
 echo "P35_SERVER_REQUISITIONED_BLOCKS_WORK_COMPLETE_QC_READY_PASS\n";
 echo "P35_P34_ROT_INVOICE_CONTROL_PRESERVED_PASS\n";
}catch(Throwable $e){fwrite(STDERR,"P35_NO_GO_SYNTAX ".$e->getMessage()."\n");exit(5);}
if($mode==='check'){echo "P35_DRY_RUN_NO_JOB_STOCK_OR_INVOICE_MUTATIONS\n";exit(0);}
$dir=$root.'/storage/app/p35_lifecycle_parts_backup_'.date('Ymd_His').'_'.getmypid();
if(!mkdir($dir,0700,true))exit(6);
$bak=$dir.'/AdminJobCardLifecyclePageController.php';
if(!copy($file,$bak)||hash_file('sha256',$bak)!==hash('sha256',$before))exit(6);
if(hash_file('sha256',$file)!==hash('sha256',$before))exit(7);
$tmp=tempnam(dirname($file),'.p35_');
try{
 if(!$tmp||file_put_contents($tmp,$after,LOCK_EX)!==strlen($after))throw new RuntimeException('temp write failed');
 chmod($tmp,fileperms($file)&0777);
 if(!rename($tmp,$file))throw new RuntimeException('atomic rename failed');
 if(hash_file('sha256',$file)!==hash('sha256',$after))throw new RuntimeException('checksum');
 echo "P35_APPLIED_BACKUP=".$bak."\n";
 echo "P35_ONLY_LIFECYCLE_CONTROLLER_SOURCE_CHANGED\n";
}catch(Throwable $e){@unlink($tmp);copy($bak,$file);fwrite(STDERR,"P35_AUTO_ROLLBACK ".$e->getMessage()."\n");exit(8);}
