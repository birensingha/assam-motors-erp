<?php
declare(strict_types=1);
/** P32 sidebar navigation only. Safe, idempotent, backed up. */
$mode=$argv[1]??'check';$root=rtrim((string)($argv[2]??''),'/');
if(!in_array($mode,['check','apply'],true)||!is_file($root.'/artisan'))exit(2);
$file=$root.'/resources/views/layouts/erp.blade.php';$routeFile=$root.'/routes/web.php';
if(!is_file($file)||is_link($file)||!is_file($routeFile))exit(3);
$before=(string)file_get_contents($file);$routes=(string)file_get_contents($routeFile);
foreach(['/counter-sales','/counter-sales/create'] as $route){
 if(!str_contains($routes,$route)){fwrite(STDERR,"P32_ROUTE_MISSING $route\n");exit(4);}
}
if(str_contains($before,'P32_ERP_SIDEBAR_V3')){echo "P32_SIDEBAR_ALREADY_INSTALLED\n";exit(0);}
// Move prior P32 section to the proper location; preserve all other sidebar items.
$clean=$before;
$previous='/\{\{-- P32_ERP_SIDEBAR_V2:.*?<\/div>\s*<\/div>/s';
if(preg_match($previous,$clean)===1){$clean=preg_replace($previous,'',$clean,1);}
// Also support first-generation floating menu if it is still present.
$floating='/\{\{-- P32_ERP_NAVIGATION:.*?<\/nav>/s';
if(preg_match($floating,$clean)===1){$clean=preg_replace($floating,'',$clean,1);}
if(str_contains($clean,'P32_ERP_SIDEBAR_V2') || str_contains($clean,'id="am-p32-nav"')){
 fwrite(STDERR,"P32_NO_GO_OLD_MENU_NOT_REMOVED\n");exit(5);
}
// Locate the existing Workshop ERP link in the sidebar. Do not append at the bottom.
$anchorPattern='/<a\b[^>]*>[^<]*Workshop\s+ERP[^<]*<\/a>/i';
if(preg_match_all($anchorPattern,$clean,$anchors,PREG_OFFSET_CAPTURE)!==1){
 fwrite(STDERR,"P32_NO_GO_WORKSHOP_ERP_LINK_ANCHOR\n");exit(6);
}
$anchor=$anchors[0][0];$insertion=$anchor[1]+strlen($anchor[0]);
$block=<<<'BLADE'
{{-- P32_ERP_SIDEBAR_V3: modern Workshop ERP submenu; no floating links. --}}
<style>
.am-p32-workshop{margin:5px 8px 12px;padding:7px;background:rgba(255,255,255,.045);border:1px solid rgba(185,214,244,.17);border-radius:11px}
.am-p32-workshop .am-p32-caption{font-size:10px;letter-spacing:.09em;font-weight:800;color:#a9cce9;padding:8px 10px}
.am-p32-workshop a,.am-p32-workshop a:visited{display:flex;align-items:center;gap:10px;padding:11px 10px;margin:2px 0;border-radius:8px;text-decoration:none!important;color:#f1f6ff!important;font-size:12px;font-weight:600;line-height:1.25}
.am-p32-workshop a:hover,.am-p32-workshop a:focus-visible{background:#214e7d;color:#fff!important;outline:none}
.am-p32-workshop a.active{background:#185d93;color:#fff!important;border-left:3px solid #45cba5}
.am-p32-workshop .am-p32-icon{display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:7px;background:rgba(255,255,255,.09);font-size:13px;flex-shrink:0}
@media print{.am-p32-workshop{display:none!important}}
</style>
<div class="am-p32-workshop">
 <div class="am-p32-caption">WORKSHOP OPERATIONS</div>
 <a class="{{ request()->is('erp/inspection-manager*')?'active':'' }}" href="{{ url('/erp/inspection-manager') }}"><span class="am-p32-icon">✓</span>Inspection Manager</a>
 <a class="{{ request()->is('erp/job-cards*')?'active':'' }}" href="{{ url('/erp/job-cards') }}"><span class="am-p32-icon">⚙</span>Job Card / Lifecycle &amp; QC</a>
 <a class="{{ request()->is('erp/counter-sales/create')?'active':'' }}" href="{{ url('/erp/counter-sales/create') }}"><span class="am-p32-icon">＋</span>Direct Parts Sale</a>
 <a class="{{ request()->is('erp/counter-sales')?'active':'' }}" href="{{ url('/erp/counter-sales') }}"><span class="am-p32-icon">▤</span>Counter Invoice Register</a>
</div>
BLADE;
$after=substr_replace($clean,"\n".$block."\n",$insertion,0);
if(substr_count($after,'P32_ERP_SIDEBAR_V3')!==1)exit(7);
try{
 chdir($root);require 'vendor/autoload.php';$app=require 'bootstrap/app.php';
 $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
 token_get_all(app('blade.compiler')->compileString($after),TOKEN_PARSE);
 echo "P32_SIDEBAR_COMPILE_PASS\n";
}catch(Throwable $e){fwrite(STDERR,"P32_NO_GO_BLADE ".$e->getMessage()."\n");exit(8);}
if($mode==='check'){echo "P32_SIDEBAR_DRY_RUN_PASS\n";exit(0);}
$backup=$root.'/storage/app/p32_sidebar_'.date('Ymd_His').'_'.getmypid().'.blade.php';
if(!copy($file,$backup)||hash_file('sha256',$file)!==hash('sha256',(string)file_get_contents($backup)))exit(9);
if(hash_file('sha256',$file)!==hash('sha256',(string)file_get_contents($backup)))exit(9);
$tmp=tempnam(dirname($file),'.p32_');
if(!$tmp||file_put_contents($tmp,$after,LOCK_EX)!==strlen($after)||!rename($tmp,$file)){@unlink($tmp);copy($backup,$file);exit(10);}
echo "P32_SIDEBAR_APPLIED_BACKUP=$backup\n";
