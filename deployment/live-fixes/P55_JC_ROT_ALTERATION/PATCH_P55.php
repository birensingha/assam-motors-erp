<?php
declare(strict_types=1);
/** P55: guarded source-only integration of Old ERP style Job Card ROT editing. */
$mode=(string)($argv[1]??'check');
$root=rtrim((string)($argv[2]??''),'/');
if(!in_array($mode,['check','apply'],true)||!is_file("$root/artisan")){
 fwrite(STDERR,"P55_USAGE check|apply STAGING_ROOT\n");exit(2);
}
$base=__DIR__;
$files=[
 'app/Http/Controllers/Web/AdminJobCardPageController.php',
 'resources/views/job-cards/edit.blade.php',
 'routes/web.php'
];
$newFiles=[
 'app/Http/Controllers/Web/JobCardRotAlterationController.php'=>'payload/JobCardRotAlterationController.php',
 'resources/views/job-cards/rot-alteration.blade.php'=>'payload/rot-alteration.blade.php',
];
$before=[];$after=[];
foreach($files as $file){
 $fp="$root/$file";
 if(!is_file($fp)||is_link($fp)){fwrite(STDERR,"P55_BASE_FILE_MISSING ".$file."\n");exit(3);}
 $before[$file]=(string)file_get_contents($fp);
 $after[$file]=$before[$file];
}
if(str_contains($before[$files[1]],'P55_ROT_ALTERATION_INCLUDE')){
 if(str_contains($before[$files[2]],'P55_JC_ROT_ALTERATION_ROUTE')){
    echo "P55_ALREADY_INSTALLED\n";exit(0);
 }
 fwrite(STDERR,"P55_PARTIAL_INSTALL_NEEDS_REVIEW\n");exit(4);
}
foreach($newFiles as $target=>$source){
 if(!is_file("$base/$source")||is_file("$root/$target")){
  fwrite(STDERR,"P55_NEW_TARGET_INVALID ".$target."\n");exit(4);
 }
}
function p55swap(string &$text,string $old,string $new,string $code):void{
 $matches=substr_count($text,$old);
 if($matches!==1)throw new RuntimeException("P55_ANCHOR_$code=$matches");
 $text=str_replace($old,$new,$text);
}
try{
 // Direct edit must keep the existing authorised Laravel Admin middleware.
 $ctrl=&$after[$files[0]];
 $old=<<<'PHP'
        return view('job-cards.edit', ['job'=>$card]);
PHP;
 $new=<<<'PHP'
        // P55_COMPLETED_ROT_ALTERATION_DATA: no business record edits on GET.
        $p55Rows=DB::table('job_card_labour')->where('job_card_id',$job)->orderBy('id')->get();
        $p55Staff=DB::table('staff')->whereRaw("LOWER(COALESCE(status,'active'))='active'")
            ->orderBy('name')->get(['id','staff_code','name']);
        $p55Master=Schema::hasTable('labour_master')?
            DB::table('labour_master')->orderBy('rot_code')->limit(4000)->get():collect();
        $p55Sessions=Schema::hasTable('rot_sessions')?
            DB::table('rot_sessions')->where('job_card_id',$job)->get():collect();
        $p55InvoiceLocked=!empty($card->invoice_id) ||
            (Schema::hasTable('invoices') && DB::table('invoices')->where('job_card_id',$job)
               ->whereRaw("LOWER(COALESCE(status,'')) <> 'reversed'")->exists());
        return view('job-cards.edit', compact('card')+[
            'job'=>$card,'p55Rows'=>$p55Rows,'p55Staff'=>$p55Staff,
            'p55Master'=>$p55Master,'p55Sessions'=>$p55Sessions,
            'p55InvoiceLocked'=>$p55InvoiceLocked,
        ]);
PHP;
 p55swap($ctrl,$old,$new,'EDIT_DATA');
 unset($ctrl);
 $blade=&$after[$files[1]];
 p55swap($blade,'@endsection',<<<'BLADE'
{{-- P55_ROT_ALTERATION_INCLUDE: Old ERP style lock → reason/note → unlock → edit → save. --}}
@include('job-cards.rot-alteration')
@endsection
BLADE,'BLADE_INCLUDE');
 unset($blade);
 $routes=&$after[$files[2]];
 $anchor=<<<'PHP'
    Route::post('/job-cards/{job}/labour/{labour}/assign-mechanic', [AdminJobCardPageController::class,'assignMechanic'])->whereNumber('job')->whereNumber('labour')->name('erp.job-cards.labour.assign');
PHP;
 $route=<<<'PHP'
    // P55_JC_ROT_ALTERATION_ROUTE: Admin-authenticated and audited per-line correction.
    Route::post('/job-cards/{job}/labour/{labour}/alteration',
        [\App\Http\Controllers\Web\JobCardRotAlterationController::class,'save'])
        ->whereNumber('job')->whereNumber('labour')->name('erp.job-cards.labour.alter');
PHP;
 p55swap($routes,$anchor,$anchor."\n".$route,'ROT_ROUTE');
 unset($routes);
 // Fail closed on missing accounting and correction source tables.
 chdir($root);
 require "$root/vendor/autoload.php";
 $app=require "$root/bootstrap/app.php";
 $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
 foreach(['job_cards','job_card_labour','job_card_change_audits','staff','rot_sessions'] as $table)
   if(!\Illuminate\Support\Facades\Schema::hasTable($table))
      throw new RuntimeException('P55_MISSING_SCHEMA_'.$table);
 foreach($files as $name){
  $code=str_ends_with($name,'.blade.php')
     ?app('blade.compiler')->compileString($after[$name]):$after[$name];
  token_get_all($code,TOKEN_PARSE);
  echo "P55_PARSED_BASE=".basename($name)."\n";
 }
 foreach($newFiles as $name=>$relative){
  $raw=(string)file_get_contents("$base/$relative");
  $parsed=str_ends_with($name,'.blade.php')?app('blade.compiler')->compileString($raw):$raw;
  token_get_all($parsed,TOKEN_PARSE);
  echo "P55_PARSED_PAYLOAD=".basename($name)."\n";
 }
 echo "P55_ACCOUNTING_AND_ROT_SCHEMA_PREFLIGHT_PASS\n";
}catch(Throwable $e){fwrite(STDERR,"P55_NO_GO ".$e->getMessage()."\n");exit(5);}
if($mode==='check'){echo "P55_DRY_RUN_NO_WRITES\n";exit(0);}
$backup="$root/storage/app/p55_rot_alter_backup_".date('Ymd_His').'_'.getmypid();
if(!mkdir($backup,0700,true)){fwrite(STDERR,"P55_BACKUP_CREATE_FAILED\n");exit(6);}
foreach($files as $file){
 $source="$root/$file";$copy="$backup/".str_replace('/','__',$file);
 if(!copy($source,$copy)||hash_file('sha256',$copy)!==hash('sha256',$before[$file])){
  fwrite(STDERR,"P55_BACKUP_VERIFY_FAIL\n");exit(6);
 }
}
foreach($files as $file)
 if(hash_file('sha256',"$root/$file")!==hash('sha256',$before[$file])){
  fwrite(STDERR,"P55_SOURCE_CHANGED_ABORT\n");exit(7);
 }
try{
 foreach($files as $file){
  $target="$root/$file";$tmp=tempnam(dirname($target),'.p55_');
  if(!$tmp||file_put_contents($tmp,$after[$file],LOCK_EX)!==strlen($after[$file])
     ||!chmod($tmp,fileperms($target)&0777)||!rename($tmp,$target))
    throw new RuntimeException("P55_FILE_WRITE_".basename($file));
 }
 foreach($newFiles as $target=>$rel){
  if(!copy("$base/$rel","$root/$target"))throw new RuntimeException("P55_PAYLOAD_COPY_".$target);
 }
}catch(Throwable $e){
 foreach($files as $file)copy($backup.'/'.str_replace('/','__',$file),"$root/$file");
 foreach($newFiles as $target=>$rel)@unlink("$root/$target");
 fwrite(STDERR,"P55_ROLLED_BACK ".$e->getMessage()."\n");exit(8);
}
echo "P55_INSTALLED_WITH_BACKUP=$backup\n";
echo "P55_NO_RECORD_DATA_WRITES_DURING_INSTALL\n";
