<?php
declare(strict_types=1);
/**
 * P60: unify Staff Portal, Admin Manager form, and Staff Android REST
 * inspection submissions on 30 canonical checklist entries.
 * Read-only completed (including legacy 10-point) reports are preserved.
 * No business table modifications in installer/verification.
 */
$mode=(string)($argv[1]??'check');
$root=rtrim((string)($argv[2]??''),'/');
if(!in_array($mode,['check','apply'],true)||!is_file("$root/artisan"))exit(2);
$files=[
 'app/Http/Controllers/Api/V1/StaffExtraController.php',
 'app/Http/Controllers/Web/StaffAssignedInspectionController.php',
 'app/Http/Controllers/Web/ManagerInspectionWorkController.php',
 'resources/views/staff/assigned-inspection.blade.php',
 'resources/views/inspections/manager-work.blade.php',
];
$new=['app/Support/VehicleInspectionChecklist.php'=>__DIR__.'/payload/VehicleInspectionChecklist.php'];
$old=[];$next=[];
foreach($files as $f){
 $path="$root/$f";if(!is_file($path)||is_link($path)){echo "P60_NO_GO_MISSING_$f\n";exit(3);}
 $old[$f]=(string)file_get_contents($path);$next[$f]=$old[$f];
}
if(str_contains($old[$files[0]],'P60_VALIDATED_CANONICAL_30')){echo "P60_ALREADY_INSTALLED\n";exit(0);}
foreach($new as $f=>$src)if(file_exists("$root/$f")||!is_file($src)){echo "P60_NEW_FILE_CONFLICT\n";exit(3);}
function once60(string &$s,string $before,string $after,string $tag):void{
 $n=substr_count($s,$before);
 if($n!==1)throw new RuntimeException('P60_ANCHOR_'.$tag.'_'.(string)$n);
 $s=str_replace($before,$after,$s);
}
try{
 // Staff REST API previously accepted any number of checks and even null
 // conditions. New incompletes MUST match the mobile 30-item official list.
 $f=&$next[$files[0]];
 $apiValidate=<<<'PHP'
        $data=$request->validate(['items'=>['required','array','min:1'],'items.*.item_name'=>['required','string','max:100'],'items.*.status'=>['nullable','in:good,fair,poor'],'items.*.remarks'=>['nullable','string','max:1000']]);
PHP;
 $newApi=<<<'PHP'
        // P60_VALIDATED_CANONICAL_30: Android and Staff Web use same names/count.
        $data=$request->validate(['items'=>['required','array','size:30'],'items.*.item_name'=>['required','string','max:100'],'items.*.status'=>['required','in:good,fair,poor'],'items.*.remarks'=>['nullable','string','max:1000']]);
        if(!\App\Support\VehicleInspectionChecklist::valid($data['items'])){
            throw \Illuminate\Validation\ValidationException::withMessages([
                'items'=>'All 30 official vehicle inspection points must be completed without duplicates. Refresh this form and retry.'
            ]);
        }
PHP;
 once60($f,$apiValidate,$newApi,'STAFF_REST_30');
 unset($f);

 $f=&$next[$files[1]];
 $checks=<<<'PHP'
    private const CHECKS=[
        'Exterior / Body','Engine Bay','Brake System','Tyres / Wheels','Suspension',
        'Lights / Electrical','Battery','Interior / Dashboard','Fluid Levels','Underbody',
    ];
PHP;
 once60($f,$checks,"    // P60_CANONICAL_INSPECTION_SERVER_SOURCE_30 in App\\Support.\n",'PORTAL_CHECKS_REMOVE');
 once60($f,"'checks'=>self::CHECKS","'checks'=>\\App\\Support\\VehicleInspectionChecklist::items()",'PORTAL_RENDER_LIST');
 $oldSize=<<<'PHP'
          'items'=>['required','array',static function ($attribute, $value, $fail) { if (!is_array($value) || !in_array(count($value), [10,14,20,30], true)) { $fail('Unsupported inspection checklist length.'); } }],
PHP;
 once60($f,$oldSize,"          'items'=>['required','array','size:30'],",'PORTAL_VALIDATE_COUNT');
 $check=<<<'PHP'
        if(count(array_unique($names))!==10 || array_diff($names,self::CHECKS) || array_diff(self::CHECKS,$names)){
            throw ValidationException::withMessages(['items'=>'All ten official Inspection checklist items must be completed.']);
        }
PHP;
 $replace=<<<'PHP'
        if(!\App\Support\VehicleInspectionChecklist::valid($data['items'])){
            throw ValidationException::withMessages(['items'=>'All 30 official Inspection checklist points must be completed, without duplicates.']);
        }
PHP;
 once60($f,$check,$replace,'PORTAL_CANONICAL_NAMES');
 unset($f);

 $f=&$next[$files[2]];
 once60($f,"'items'=>['required','array','min:5','max:35'],","'items'=>['required','array','size:30'],",'MANAGER_VALIDATE_COUNT');
 $validNames=<<<'PHP'
        $validNames=[
            'Exterior / Body','Engine Bay','Brake System','Tyres / Wheels','Suspension',
            'Lights / Electrical','Battery','Interior / Dashboard','Fluid Levels','Underbody',
        ];
PHP;
 once60($f,$validNames,"        \$validNames=\\App\\Support\\VehicleInspectionChecklist::items();",'MANAGER_CANONICAL_NAMES');
 once60($f,"'Complete all ten standard inspection checklist items.'","'Complete all 30 standard inspection checklist items.'",'MANAGER_ERROR_TEXT');
 once60($f,"'Ten-item Manager Inspection submitted; diagnosis must follow.'","'Thirty-item Vehicle Inspection submitted; diagnosis must follow.'",'MANAGER_AUDIT_TEXT');
 unset($f);

 $f=&$next[$files[3]];
 once60($f,"Vehicle Inspection Checklist — 10 Points","Vehicle Inspection Checklist — {{ count(\$checks) }} Points",'PORTAL_LABEL_COUNT');
 once60($f,"All ten conditions required.","All {{ count(\$checks) }} conditions required.",'PORTAL_FOOT_NOTE');
 unset($f);

 $f=&$next[$files[4]];
 $m=<<<'BLADE'
   $standardInspectionItems=[
    'Exterior / Body','Engine Bay','Brake System','Tyres / Wheels','Suspension',
    'Lights / Electrical','Battery','Interior / Dashboard','Fluid Levels','Underbody'
   ];
BLADE;
 once60($f,$m,"   \$standardInspectionItems=\\App\\Support\\VehicleInspectionChecklist::items();",'MANAGER_RENDER_30');
 // Never inadvertently widen manager rights: existing Admin view gating remains.
 unset($f);

 chdir($root);require 'vendor/autoload.php';$app=require 'bootstrap/app.php';
 $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
 $canonical=include_once(__DIR__.'/payload/VehicleInspectionChecklist.php');
 if(count(\App\Support\VehicleInspectionChecklist::items())!==30)throw new RuntimeException('P60_CANONICAL_COUNT_NOT_30');
 foreach($next as $name=>$bytes){
  $compiled=str_ends_with($name,'.blade.php')?app('blade.compiler')->compileString($bytes):$bytes;
  token_get_all($compiled,TOKEN_PARSE);
  echo "P60_COMPILE_PASS=".basename($name)."\n";
 }
 $canon=(string)file_get_contents(reset($new));
 token_get_all($canon,TOKEN_PARSE);
 echo "P60_ALL_30_POINT_SOURCE_CHECKS_PASS\n";
}catch(Throwable $e){fwrite(STDERR,"P60_NO_GO_PRECHECK ".$e->getMessage()."\n");exit(4);}
if($mode==='check'){echo "P60_INSPECTION_DRY_RUN_PASS_NO_BUSINESS_WRITES\n";exit(0);}
$backup="$root/storage/app/p60_inspection_30_backup_".date('Ymd_His').'_'.getmypid();
if(!mkdir($backup,0700,true))exit(5);
foreach($files as $f){
 $dest="$backup/".str_replace('/','__',$f);
 if(!copy("$root/$f",$dest)||hash_file('sha256',$dest)!==hash('sha256',$old[$f])){
   echo "P60_BACKUP_FAILED\n";exit(5);
 }
}
foreach($files as $f)if(hash_file('sha256',"$root/$f")!==hash('sha256',$old[$f])){
 echo "P60_CONCURRENT_CHANGES_NO_GO\n";exit(6);
}
$written=[];
try{
 foreach($files as $f){
  $dest="$root/$f";$tmp=tempnam(dirname($dest),'.p60_');
  if(!$tmp||file_put_contents($tmp,$next[$f],LOCK_EX)!==strlen($next[$f])||!chmod($tmp,fileperms($dest)&0777)||!rename($tmp,$dest))
    throw new RuntimeException('WRITE_'.$f);
  $written[]=$f;
 }
 foreach($new as $dest=>$source){
   if(!copy($source,"$root/$dest"))throw new RuntimeException('NEW_FILE_'.$dest);
   $written[]=$dest;
 }
}catch(Throwable $e){
 foreach(array_reverse($written) as $f){
  if(isset($new[$f]))@unlink("$root/$f");
  else copy("$backup/".str_replace('/','__',$f),"$root/$f");
 }
 fwrite(STDERR,"P60_SOURCE_RESTORED_AFTER_ERROR ".$e->getMessage()."\n");exit(7);
}
echo "P60_INSTALLED_BACKUP=$backup\nP60_HISTORICAL_INSPECTION_ROWS_UNCHANGED\n";
