<?php
declare(strict_types=1);
/**
 * P25_ESTIMATE_BLADE_INLINE_PHP_500
 * The first @php(...) compiled into invalid "<?php(" (HTML), which swallowed
 * subsequent @php/$initial assignment, triggering an Undefined variable 500.
 *
 * check|apply /absolute/staging-root
 */
$mode=$argv[1]??'check';$root=rtrim((string)($argv[2]??''),'/');
if(!in_array($mode,['check','apply'],true)||!is_file($root.'/artisan')){
 fwrite(STDERR,"Usage: PATCH_P25.php check|apply /staging-root\n");exit(2);
}
$relative='resources/views/estimates/create.blade.php';
$file=$root.'/'.$relative;
if(!is_file($file)||is_link($file)){fwrite(STDERR,"P25_NO_GO_SOURCE_MISSING\n");exit(3);}
$before=(string)file_get_contents($file);
$oldSha='fe2dff57648b2051d568ab4dabf443096bc9ad99c94dfcc8b53fff142a306147';
if(str_contains($before,'P25_ESTIMATE_BLADE_INLINE_PHP_500')){
 echo "P25_ALREADY_INSTALLED_NOOP\n";exit(0);
}
if(hash('sha256',$before)!==$oldSha){
 fwrite(STDERR,"P25_NO_GO_SOURCE_HASH_CHANGED\n");exit(4);
}
$needle="@php(\$amEstimateWip=in_array(strtolower(trim((string)\$jobCard->status)),['in progress','in-progress','work in progress','wip']))";
$fix=<<<'BLADE'
{{-- P25_ESTIMATE_BLADE_INLINE_PHP_500: safe multi-line initialization with explicitly closed PHP block. --}}
@php
    $amEstimateWip = in_array(strtolower(trim((string)$jobCard->status)), ['in progress','in-progress','work in progress','wip'], true);
@endphp
BLADE;
if(substr_count($before,$needle)!==1 ||substr_count($before,'$initial = $items->map(')!==1 || substr_count($before,"old('items', \$initial)")!==1){
 fwrite(STDERR,"P25_NO_GO_BLADE_GUARDS\n");exit(5);
}
$after=str_replace($needle,$fix,$before);
try{
 chdir($root);require 'vendor/autoload.php';
 $app=require 'bootstrap/app.php';
 $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
 $php=app('blade.compiler')->compileString($after);
 token_get_all($php,TOKEN_PARSE);
 if(str_contains($php,'<?php(')||str_contains($php,'@if($amEstimateWip)') || str_contains($php,'@php')){
    throw new RuntimeException('Blade compiler failed to process raw directives');
 }
 if(!str_contains($php,'$amEstimateWip = in_array(') || !str_contains($php,'$initial = $items->map(')
     || !str_contains($php,"old('items', \$initial)") || !str_contains($php,'<?php if($amEstimateWip): ?>')){
    throw new RuntimeException('Required compiled blocks missing');
 }
 echo "P25_COMPILED_PHP_TOKEN_PARSE_PASS\n";
 echo "P25_INITIAL_DECLARED_BEFORE_USE_PASS\n";
 echo "P25_DIRECTIVE_COMPILATION_PASS\n";
}catch(Throwable $e){
 fwrite(STDERR,"P25_NO_GO_BLADE_PREFLIGHT ".$e->getMessage()."\n");exit(6);
}
if($mode==='check'){
 echo "P25_DRY_RUN_NO_FILES_OR_BUSINESS_DATA_WRITTEN\n";exit(0);
}
$backupDir=$root.'/storage/app/p25_estimate_500_backup_'.date('Ymd_His').'_'.getmypid();
if(!mkdir($backupDir,0700,true)){fwrite(STDERR,"P25_NO_GO_BACKUP_DIR\n");exit(7);}
$backup=$backupDir.'/create.blade.php';
if(!copy($file,$backup)||hash_file('sha256',$backup)!==$oldSha){
 fwrite(STDERR,"P25_NO_GO_VERIFIED_BACKUP\n");exit(7);
}
if(hash_file('sha256',$file)!==$oldSha){
 fwrite(STDERR,"P25_NO_GO_CONCURRENT_EDIT\n");exit(8);
}
$tmp=tempnam(dirname($file),'.p25_');
try{
 if(!$tmp||file_put_contents($tmp,$after,LOCK_EX)!==strlen($after))throw new RuntimeException('Temporary write failed');
 chmod($tmp,fileperms($file)&0777);
 if(!rename($tmp,$file))throw new RuntimeException('Atomic write failed');
 if(hash_file('sha256',$file)!==hash('sha256',$after))throw new RuntimeException('Checksum mismatch');
 echo 'P25_APPLIED_BACKUP='.$backup."\n";
 echo "P25_ESTIMATE_VIEW_ONLY_NO_DATABASE_OR_FINANCIAL_WRITES\n";
}catch(Throwable $e){
 @unlink($tmp);copy($backup,$file);
 fwrite(STDERR,'P25_AUTO_ROLLBACK '.$e->getMessage()."\n");exit(9);
}
