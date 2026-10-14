<?php
declare(strict_types=1);
/**
 * P19 — Move Reversal/Cancel shortcut and Delete Empty Job Card to the
 * absolute END of Job Card show content; view-only change, same forms/actions.
 *
 * php PATCH_P19.php check|apply /absolute/staging-laravel-root
 */
$mode=$argv[1]??'check';
$root=rtrim((string)($argv[2]??''),'/');
if(!in_array($mode,['check','apply'],true)||!is_file($root.'/artisan')){
  fwrite(STDERR,"Usage: php PATCH_P19.php check|apply /staging-laravel-root\n"); exit(2);
}
$relative='resources/views/job-cards/show.blade.php';
$file=$root.'/'.$relative;
if(!is_file($file)||is_link($file)){fwrite(STDERR,"P19_NO_GO_SOURCE_MISSING\n");exit(3);}
$source=file_get_contents($file);
if($source===false){fwrite(STDERR,"P19_NO_GO_SOURCE_UNREADABLE\n");exit(3);}
$marker='P19_JOBCARD_ADMIN_PANELS_BOTTOM';
if(str_contains($source,$marker)){
    echo "P19_ALREADY_INSTALLED_NOOP\n";exit(0);
}
// Exact staging source observed in readonly check. Refuse concurrent/cross-patch overwrite.
$expected='43cfc7f1df877cbfed30fede0deb351d30a05a0daa1414cd02ed26acac1089ee';
if(hash('sha256',$source)!==$expected){
    fwrite(STDERR,"P19_NO_GO_STAGING_SOURCE_CHANGED\n");exit(4);
}
try{
    $revNeedle="\n<div class=\"card section-gap\" style=\"border-left:5px solid #ef9b0a;padding:15px\">";
    $deleteNeedle="\n@if(strtolower((string)\$job->status)==='open')";
    $revStart=strpos($source,$revNeedle);
    $delStart=strpos($source,$deleteNeedle);
    if($revStart===false||$delStart===false||$delStart<=$revStart||($delStart-$revStart)>2000){
        throw new RuntimeException('Reversal or Delete header not at expected location');
    }
    $endStart=strpos($source,"\n@endif",$delStart);
    if($endStart===false||($endStart-$delStart)>2400){
        throw new RuntimeException('Delete block has unexpected boundary');
    }
    $end=$endStart+strlen("\n@endif");
    $reversal=substr($source,$revStart,$delStart-$revStart);
    $delete=substr($source,$delStart,$end-$delStart);
    if(substr_count($source,'Job Card Reversal / Cancel Centre')!==1
        ||substr_count($source,'<h3 style="margin:0 0 7px;color:#991b1b">🗑 Delete Empty Job Card</h3>')!==1
        ||substr_count($source,'id="jc-delete-panel"')!==1
        ||substr_count($source,"@endsection")!==1
        ||substr_count($source,"@include('job-cards.lifecycle-panel')")!==1){
        throw new RuntimeException('Duplicate or missing section markers');
    }
    if(!str_contains($reversal,"route('erp.job-cards.reversal',\$job->id)")
       ||!str_contains($delete,"route('erp.job-cards.destroy',\$job->id)")
       ||!str_contains($delete,"@method('DELETE')")
       ||!str_contains($delete,'confirm_job_no')
       ||!str_contains($delete,'delete_reason')
       ||!str_ends_with(rtrim($delete),'@endif')){
       throw new RuntimeException('Panel integrity check failed');
    }
    // Remove ONLY the two original blocks. Insert the unmodified originals
    // immediately before the SINGLE closing @endsection.
    $without=substr_replace($source,"\n",$revStart,$end-$revStart);
    $footer="\n\n{{-- ".$marker." — admin controls last; no route, permission or business logic change --}}\n"
        .$reversal.$delete."\n\n";
    $endTag="\n@endsection";
    if(substr_count($without,$endTag)!==1)throw new RuntimeException('Closing section ambiguous');
    $modified=str_replace($endTag,$footer."@endsection",$without);
    // The two block bodies are byte-for-byte preserved and occur exactly once.
    if(substr_count($modified,$reversal)!==1||substr_count($modified,$delete)!==1
       ||substr_count($modified,'id="jc-delete-panel"')!==1
       ||strpos($modified,$marker)<=strpos($modified,"@include('job-cards.lifecycle-panel')")){
       throw new RuntimeException('Move integrity check failed');
    }
    chdir($root);
    require 'vendor/autoload.php';
    $app=require 'bootstrap/app.php';
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    token_get_all(app('blade.compiler')->compileString($modified),TOKEN_PARSE);
    echo "P19_ORIGINAL_SHA256=".hash('sha256',$source)."\n";
    echo "P19_NEW_SHA256=".hash('sha256',$modified)."\n";
    echo "P19_BOTH_PANELS_MOVED_BEFORE_ENDSECTION=PASS\n";
    echo "P19_COMPILED_BLADE_SYNTAX_PASS\n";
} catch(Throwable $e){
    fwrite(STDERR,"P19_NO_GO_PREFLIGHT ".$e->getMessage()."\n");exit(5);
}
if($mode==='check'){
    echo "P19_DRY_RUN_NO_FILES_OR_DATA_CHANGED\n";exit(0);
}
$dir=$root.'/storage/app/p19_jobcard_footer_backup_'.date('Ymd_His').'_'.getmypid();
if(!mkdir($dir,0700,true)){fwrite(STDERR,"P19_NO_GO_BACKUP_DIR\n");exit(6);}
$backup=$dir.'/show.blade.php';
if(!copy($file,$backup)||hash_file('sha256',$backup)!==hash('sha256',$source)){
    fwrite(STDERR,"P19_NO_GO_BACKUP_VERIFY\n");exit(7);
}
if(hash_file('sha256',$file)!==hash('sha256',$source)){
    fwrite(STDERR,"P19_NO_GO_CONCURRENT_SOURCE_CHANGE\n");exit(8);
}
$tmp=tempnam(dirname($file),'.p19_');
try{
    if(!$tmp||file_put_contents($tmp,$modified,LOCK_EX)!==strlen($modified))
        throw new RuntimeException('temporary write failed');
    chmod($tmp,fileperms($file)&0777);
    if(!rename($tmp,$file))throw new RuntimeException('atomic replace failed');
    if(hash_file('sha256',$file)!==hash('sha256',$modified))throw new RuntimeException('verification hash mismatch');
    echo "P19_APPLIED_BACKUP=".$backup."\n";
    echo "P19_ONLY_JOB_CARD_SHOW_VIEW_CHANGED\n";
    echo "P19_NO_JOB_CARD_OR_FINANCIAL_DATA_MUTATED\n";
} catch(Throwable $e){
    @unlink($tmp);
    copy($backup,$file);
    fwrite(STDERR,"P19_ROLLBACK_COMPLETED ".$e->getMessage()."\n");exit(9);
}
