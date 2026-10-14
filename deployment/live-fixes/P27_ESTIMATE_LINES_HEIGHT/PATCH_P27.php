<?php
declare(strict_types=1);
/** P27 — Estimate Lines same 360px minimum row area as Parts Purchase, UI-only.
 * Usage: php PATCH_P27.php check|apply /absolute/staging-laravel-root
 */
$mode=$argv[1]??'check';$root=rtrim((string)($argv[2]??''),'/');
if(!in_array($mode,['check','apply'],true)||!is_file($root.'/artisan')){fwrite(STDERR,"P27_USAGE_ERROR\n");exit(2);}
$file=$root.'/resources/views/estimates/create.blade.php';
if(!is_file($file)||is_link($file)){fwrite(STDERR,"P27_NO_GO_SOURCE_MISSING\n");exit(3);}
$source=file_get_contents($file);
$sourceHash='fdefbc3d13a9b4250e695a72fdca566e486bfbbaa69ffa31ce4ea549b64ecd24';
if(str_contains($source,'P27_ESTIMATE_LINES_PURCHASE_HEIGHT')){echo "P27_ALREADY_INSTALLED_NOOP\n";exit(0);}
if(hash('sha256',$source)!==$sourceHash){fwrite(STDERR,"P27_NO_GO_UNEXPECTED_ESTIMATE_VIEW_HASH\n");exit(4);}
$anchor="@section('content')";
$old='<div class="line-scroll"><table class="line-table estimate-line-table">';
$new='<div class="line-scroll estimate-lines-area"><table class="line-table estimate-line-table">';
$styles=<<<'BLADE'
{{-- P27_ESTIMATE_LINES_PURCHASE_HEIGHT: match purchase item-area height without altering form data. --}}
@push('head')
<style>
#estimate-form .estimate-lines-area {
    width:100%;
    min-height:360px;
    height:auto!important;
    max-height:none!important;
    overflow-x:auto;
    border:1px solid #d6e1ec;
    border-radius:12px;
    background:#fff;
    padding:6px 8px 20px;
    box-sizing:border-box;
}
#estimate-form .estimate-lines-area .estimate-line-table {
    width:100%;
    min-width:1100px;
    border-collapse:separate;
    border-spacing:0 6px;
}
#estimate-form .estimate-lines-area .estimate-line-table tbody td {
    padding:6px 5px;
    vertical-align:top;
}
#estimate-form .estimate-lines-area .estimate-line-table input,
#estimate-form .estimate-lines-area .estimate-line-table select {
    box-sizing:border-box;
    min-height:40px;
}
@media (max-width:700px) {
    #estimate-form .estimate-lines-area {
        min-height:360px;
        padding:5px 5px 18px;
    }
}
</style>
@endpush

BLADE;
if(substr_count($source,$anchor)!==1||substr_count($source,$old)!==1||substr_count($source,'id="estimate-lines"')!==1) {
 fwrite(STDERR,"P27_NO_GO_NON_UNIQUE_ESTIMATE_LINES_ANCHOR\n");exit(5);
}
$changed=str_replace($anchor,$styles.$anchor,$source);
$changed=str_replace($old,$new,$changed);
try {
 chdir($root);require 'vendor/autoload.php';
 $app=require 'bootstrap/app.php';
 $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
 $compiled=app('blade.compiler')->compileString($changed);
 token_get_all($compiled,TOKEN_PARSE);
 if(!str_contains($compiled,'min-height:360px')||!str_contains($compiled,'estimate-lines-area')
   ||!str_contains($compiled,'id="estimate-lines"')||substr_count($compiled,'name="estimate_id"')!==1){
    throw new RuntimeException('Compiled Estimate view guards not found');
 }
 echo "P27_BLADE_COMPILE_SYNTAX_PASS\n";
 echo "P27_ESTIMATE_LINES_MIN_HEIGHT_360PX_PASS\n";
 echo "P27_EXISTING_FORM_LINE_ID_PRESERVED_PASS\n";
} catch(Throwable $e) {
 fwrite(STDERR,"P27_NO_GO_COMPILE ".$e->getMessage()."\n");exit(6);
}
if($mode==='check'){echo "P27_DRY_RUN_NO_FILES_OR_BUSINESS_DATA_CHANGED\n";exit(0);}
$dir=$root.'/storage/app/p27_estimate_lines_backup_'.date('Ymd_His').'_'.getmypid();
if(!mkdir($dir,0700,true)){fwrite(STDERR,"P27_NO_GO_BACKUP_DIR\n");exit(7);}
$backup=$dir.'/create.blade.php';
if(!copy($file,$backup)||hash_file('sha256',$backup)!==$sourceHash){fwrite(STDERR,"P27_NO_GO_BACKUP_VERIFY\n");exit(7);}
if(hash_file('sha256',$file)!==$sourceHash){fwrite(STDERR,"P27_NO_GO_CONCURRENT_EDIT\n");exit(8);}
$tmp=tempnam(dirname($file),'.p27_');
try {
 if(!$tmp || file_put_contents($tmp,$changed,LOCK_EX)!==strlen($changed))throw new RuntimeException('Temp write failed');
 chmod($tmp,fileperms($file)&0777);
 if(!rename($tmp,$file))throw new RuntimeException('Atomic source replace failed');
 if(hash_file('sha256',$file)!==hash('sha256',$changed))throw new RuntimeException('New file checksum mismatch');
 echo "P27_APPLIED_BACKUP=".$backup."\n";
 echo "P27_ONLY_ESTIMATE_CREATE_VIEW_UPDATED_NO_DATABASE_WRITES\n";
} catch(Throwable $e) {
 @unlink($tmp);copy($backup,$file);
 fwrite(STDERR,"P27_VIEW_ROLLBACK ".$e->getMessage()."\n");exit(9);
}
