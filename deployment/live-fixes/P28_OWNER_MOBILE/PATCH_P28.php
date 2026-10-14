<?php
declare(strict_types=1);
/**
 * P28_OWNER_NATIVE_ATTENDANCE_INSPECTION
 * Extend already authenticated Owner/Admin mobile route block.
 * No alternate guest login, no geofence bypass for ordinary Staff.
 */
$mode=$argv[1]??'check';
$root=rtrim((string)($argv[2]??''),'/');
if(!in_array($mode,['check','apply'],true)||!is_file($root.'/artisan')){
 fwrite(STDERR,"Usage: php PATCH_P28.php check|apply /staging-laravel-root\n");exit(2);
}
$rel='routes/api.php';
$file=$root.'/'.$rel;
$newRel='app/Http/Controllers/Api/V1/OwnerSelfAttendanceInspectionController.php';
$dest=$root.'/'.$newRel;
$payload=__DIR__.'/payload/OwnerSelfAttendanceInspectionController.php';
if(!is_file($file)||is_link($file)||!is_file($payload)||file_exists($dest)){
 fwrite(STDERR,"P28_NO_GO_SOURCE_OR_TARGET\n");exit(3);
}
$before=(string)file_get_contents($file);
if(str_contains($before,'P28_OWNER_NATIVE_ATTENDANCE_INSPECTION')){
 echo "P28_ALREADY_INSTALLED_ROUTES_ONLY\n";exit(0);
}
$anchor="            Route::get('/admin/mobile/workshop-dashboard', [OwnerWorkshopController::class, 'dashboard']);";
if(substr_count($before,$anchor)!==1){
 fwrite(STDERR,"P28_NO_GO_ROUTE_ANCHOR_COUNT\n");exit(4);
}
$add=<<<'ROUTES'

            // P28_OWNER_NATIVE_ATTENDANCE_INSPECTION: Owner/Admin bearer auth, mapped to linked Staff.
            Route::get('/admin/mobile/my-attendance', [\App\Http\Controllers\Api\V1\OwnerSelfAttendanceInspectionController::class, 'myAttendance']);
            Route::post('/admin/mobile/my-attendance/check-in', [\App\Http\Controllers\Api\V1\OwnerSelfAttendanceInspectionController::class, 'checkIn']);
            Route::post('/admin/mobile/my-attendance/check-out', [\App\Http\Controllers\Api\V1\OwnerSelfAttendanceInspectionController::class, 'checkOut']);
            Route::get('/admin/mobile/manager-inspections', [\App\Http\Controllers\Api\V1\OwnerSelfAttendanceInspectionController::class, 'inspections']);
            Route::get('/admin/mobile/manager-inspections/{job}', [\App\Http\Controllers\Api\V1\OwnerSelfAttendanceInspectionController::class, 'inspection'])->whereNumber('job');
            Route::post('/admin/mobile/manager-inspections/{job}/assign', [\App\Http\Controllers\Api\V1\OwnerSelfAttendanceInspectionController::class, 'assignInspection'])->whereNumber('job');
            Route::post('/admin/mobile/manager-inspections/{job}/complete', [\App\Http\Controllers\Api\V1\OwnerSelfAttendanceInspectionController::class, 'completeInspection'])->whereNumber('job');
ROUTES;
$after=str_replace($anchor,$anchor.$add,$before);
try{
 token_get_all($after,TOKEN_PARSE);
 token_get_all((string)file_get_contents($payload),TOKEN_PARSE);
 chdir($root);require 'vendor/autoload.php';
 $app=require 'bootstrap/app.php';
 $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
 foreach(['attendance','hr_audit_logs','job_card_inspection','job_card_inspection_assignments','job_cards'] as $table){
  if(!\Illuminate\Support\Facades\Schema::hasTable($table))throw new RuntimeException('Schema missing: '.$table);
 }
 foreach(['app/Http/Controllers/Web/AdminSelfAttendanceController.php',
           'app/Http/Controllers/Web/ManagerInspectionWorkController.php'] as $source){
   if(!is_file($root.'/'.$source))throw new RuntimeException('Service controller missing '.$source);
 }
 if(substr_count($after,'P28_OWNER_NATIVE_ATTENDANCE_INSPECTION')!==1)throw new RuntimeException('Route injection marker');
 echo "P28_SERVER_ROUTE_PHP_PARSE_PASS\n";
 echo "P28_LINKED_ATTENDANCE_AND_MANAGER_INSPECTION_SCHEMA_PASS\n";
 echo "P28_AUTHENTICATED_OWNER_ROUTE_GROUP_INSERTION_PASS\n";
}catch(Throwable $e){fwrite(STDERR,"P28_NO_GO_PREFLIGHT ".$e->getMessage()."\n");exit(5);}
if($mode==='check'){echo "P28_DRY_RUN_NO_SERVER_FILES_OR_DATA_CHANGED\n";exit(0);}
$backupDir=$root.'/storage/app/p28_owner_mobile_backup_'.date('Ymd_His').'_'.getmypid();
if(!mkdir($backupDir,0700,true)){fwrite(STDERR,"P28_NO_GO_BACKUP_DIR\n");exit(6);}
$backup=$backupDir.'/api.php';
$sha=hash('sha256',$before);
if(!copy($file,$backup)||hash_file('sha256',$backup)!==$sha){fwrite(STDERR,"P28_NO_GO_BACKUP_VERIFY\n");exit(6);}
if(hash_file('sha256',$file)!==$sha){fwrite(STDERR,"P28_NO_GO_CONCURRENT_ROUTE_EDIT\n");exit(7);}
$tmp=tempnam(dirname($file),'.p28_');
try{
 if(!$tmp || file_put_contents($tmp,$after,LOCK_EX)!==strlen($after))throw new RuntimeException('Routes temp write failed');
 chmod($tmp,fileperms($file)&0777);
 if(!rename($tmp,$file))throw new RuntimeException('Atomic route update failed');
 if(hash_file('sha256',$file)!==hash('sha256',$after))throw new RuntimeException('Route checksum');
 if(!copy($payload,$dest))throw new RuntimeException('New controller copy');
 if(hash_file('sha256',$dest)!==hash_file('sha256',$payload))throw new RuntimeException('Controller checksum');
 echo "P28_APPLIED_BACKUP=".$backup."\n";
 echo "P28_ROUTES_AND_CONTROLLER_ONLY_NO_ATTENDANCE_RECORD_MODIFIED\n";
}catch(Throwable $e){
 @unlink($tmp);copy($backup,$file);@unlink($dest);
 fwrite(STDERR,"P28_ROLLBACK ".$e->getMessage()."\n");exit(8);
}
