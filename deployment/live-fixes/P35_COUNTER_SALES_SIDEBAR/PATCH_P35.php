<?php
declare(strict_types=1);
/**
 * P35: Restore Counter/Direct Sale and Counter Invoice Register links that
 * were lost when the P32 submenu was removed in P34.
 * Only changes sidebar Blade. Never edits sale, invoice or accounting records.
 */
$mode=(string)($argv[1]??'check');
$root=rtrim((string)($argv[2]??''),'/');
if(!in_array($mode,['check','apply'],true)||!is_file($root.'/artisan')){fwrite(STDERR,"P35_USAGE check|apply ROOT\n");exit(2);}
$file=$root.'/resources/views/layouts/erp.blade.php';
if(!is_file($file)||is_link($file)){fwrite(STDERR,"P35_NO_GO_LAYOUT\n");exit(3);}
$before=(string)file_get_contents($file);
if(str_contains($before,'P35_COUNTER_SALES_NATIVE_V1')){
 $checks=["Counter / Direct Sale","Counter Invoice Register","P34_SIDEBAR_V1"];
 foreach($checks as $check)if(!str_contains($before,$check)){fwrite(STDERR,"P35_NO_GO_PARTIAL_INSTALL\n");exit(4);}
 echo "P35_ALREADY_INSTALLED\n";exit(0);
}
if(!str_contains($before,'P34_SIDEBAR_V1')||!str_contains($before,'<nav class="sidebar-nav am-android-nav">')){
 fwrite(STDERR,"P35_NO_GO_P34_LAYOUT_NOT_INSTALLED\n");exit(5);
}
if(str_contains($before,'WORKSHOP OPERATIONS')||str_contains($before,'P32_ERP_SIDEBAR_V3')){
 fwrite(STDERR,"P35_NO_GO_OLD_SUBMENU_PRESENT\n");exit(5);
}
$navAt=strpos($before,'<nav class="sidebar-nav am-android-nav">');
$headingAt=strpos($before,'<div class="nav-group-label">WORKSHOP ERP</div>',$navAt);
$navEnd=$headingAt===false?false:strpos($before,'</nav>',$headingAt);
if($headingAt===false||$navEnd===false){fwrite(STDERR,"P35_NO_GO_WORKSHOP_NAV_NOT_FOUND\n");exit(6);}
$section=substr($before,$headingAt,$navEnd-$headingAt);
$purchase="route('erp.purchases.index')";
$expenses="route('erp.expenses.index')";
if(substr_count($section,$purchase)!==1||substr_count($section,$expenses)!==1||strpos($section,$purchase)>strpos($section,$expenses)){
 fwrite(STDERR,"P35_NO_GO_PURCHASE_EXPENSE_ORDER\n");exit(7);
}
if(str_contains($section,"'/erp/counter-sales")||str_contains($section,'Counter Invoice Register')){
 fwrite(STDERR,"P35_NO_GO_COUNTER_LINK_ALREADY_EXISTS\n");exit(7);
}
$pattern='~<a\b(?:(?!</a>).)*?route\(\x27erp\.purchases\.index\x27\)(?:(?!</a>).)*?</a>~s';
if(preg_match_all($pattern,$section,$matches,PREG_OFFSET_CAPTURE)!==1){
 fwrite(STDERR,"P35_NO_GO_PURCHASE_ANCHOR\n");exit(8);
}
$anchor=$matches[0][0];
$insertAt=$headingAt+$anchor[1]+strlen($anchor[0]);
$links=<<<'BLADE'

        {{-- P35_COUNTER_SALES_NATIVE_V1: direct links, same native sidebar level as Purchase. --}}
        <a href="{{ url('/erp/counter-sales/create') }}" class="{{ request()->is('erp/counter-sales/create*')?'active':'' }}"><span>🛒</span> Counter / Direct Sale</a>
        <a href="{{ url('/erp/counter-sales') }}" class="{{ request()->is('erp/counter-sales*') && !request()->is('erp/counter-sales/create*')?'active':'' }}"><span>🧾</span> Counter Invoice Register</a>
BLADE;
$after=substr_replace($before,$links,$insertAt,0);
$headingEnd=strpos($after,'<div class="nav-group-label">WORKSHOP ERP</div>',$navAt);
$end=strpos($after,'</nav>',$headingEnd);
$checkSection=substr($after,$headingEnd,$end-$headingEnd);
if(substr_count($after,'P35_COUNTER_SALES_NATIVE_V1')!==1 ||
 preg_match_all('/<a\b[^>]*href\s*=/',$checkSection)!==14 ||
 !((strpos($checkSection,'Counter / Direct Sale')>strpos($checkSection,$purchase)) &&
  (strpos($checkSection,'Counter Invoice Register')>strpos($checkSection,'Counter / Direct Sale')) &&
  (strpos($checkSection,$expenses)>strpos($checkSection,'Counter Invoice Register')))){
 fwrite(STDERR,"P35_NO_GO_ORDER_OR_14_LINK_COUNT\n");exit(9);
}
try{
 chdir($root);require 'vendor/autoload.php';$app=require 'bootstrap/app.php';
 $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
 token_get_all(app('blade.compiler')->compileString($after),TOKEN_PARSE);
 echo "P35_BLADE_COMPILE_PASS\n";
}catch(Throwable $e){fwrite(STDERR,"P35_NO_GO_BLADE ".$e->getMessage()."\n");exit(10);}
if($mode==='check'){echo "P35_DRY_RUN_14_LINKS_AND_ORDER_PASS\n";exit(0);}
$backup=$root.'/storage/app/p35_sidebar_backup_'.date('Ymd_His').'_'.getmypid().'.blade.php';
if(!copy($file,$backup)||hash_file('sha256',$backup)!==hash('sha256',$before)){
 fwrite(STDERR,"P35_NO_GO_BACKUP\n");exit(11);
}
if(hash_file('sha256',$file)!==hash('sha256',$before)){
 fwrite(STDERR,"P35_NO_GO_CONCURRENT_EDIT\n");exit(12);
}
$tmp=tempnam(dirname($file),'.p35_');
if(!$tmp||file_put_contents($tmp,$after,LOCK_EX)!==strlen($after)||!chmod($tmp,fileperms($file)&0777)||!rename($tmp,$file)){
 if($tmp)@unlink($tmp);
 fwrite(STDERR,"P35_NO_GO_WRITE\n");exit(13);
}
echo "P35_APPLIED_BACKUP=$backup\nP35_14_NATIVE_LINKS_STAGING_PASS\n";
