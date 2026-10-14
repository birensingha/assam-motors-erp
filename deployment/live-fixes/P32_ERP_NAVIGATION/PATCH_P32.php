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
if(str_contains($before,'P32_ERP_SIDEBAR_V2')){echo "P32_SIDEBAR_ALREADY_INSTALLED\n";exit(0);}
// Remove only the previous P32 floating navigation block, never other UI.
$oldStart='{{-- P32_ERP_NAVIGATION:';
if(str_contains($before,$oldStart)){
 $start=strpos($before,$oldStart);
 $end=strpos($before,'</nav>',$start);
 if($end===false || !str_contains(substr($before,$start,$end-$start),'id="am-p32-nav"')){
  fwrite(STDERR,"P32_UNSAFE_PREVIOUS_BLOCK\n");exit(5);
 }
 $beforeWithoutFloat=substr_replace($before,'', $start,$end+strlen('</nav>')-$start);
}else{$beforeWithoutFloat=$before;}
$block=<<<'BLADE'
{{-- P32_ERP_SIDEBAR_V2: real ERP left sidebar links, not a floating menu. --}}
<div class="am-p32-sidebar" style="padding:8px 10px;margin:9px 0;border-top:1px solid #d4dde8">
 <div style="font-size:11px;font-weight:800;letter-spacing:.06em;opacity:.8;margin:4px 8px 9px">WORKSHOP &amp; SALES</div>
 <a href="{{ url('/erp/inspection-manager') }}" style="display:block;padding:10px 12px">Inspection Manager</a>
 <a href="{{ url('/erp/job-cards') }}" style="display:block;padding:10px 12px">Job Cards / Lifecycle &amp; QC</a>
 <a href="{{ url('/erp/counter-sales/create') }}" style="display:block;padding:10px 12px">+ Direct Parts Sale</a>
 <a href="{{ url('/erp/counter-sales') }}" style="display:block;padding:10px 12px">Counter Invoice Register</a>
</div>
BLADE;
// Only patch an actual left sidebar element, not arbitrary navigation or content.
$match=null;
foreach([
 '/<aside\\b[^>]*(?:class|id)\\s*=\\s*["\x27][^"\x27]*(?:sidebar|side-nav|side_menu)[^"\x27]*["\x27][^>]*>/i',
 '/<nav\\b[^>]*(?:class|id)\\s*=\\s*["\x27][^"\x27]*(?:sidebar|side-nav|side_menu)[^"\x27]*["\x27][^>]*>/i'
] as $pattern){
 if(preg_match_all($pattern,$beforeWithoutFloat,$matches,PREG_OFFSET_CAPTURE)===1){$match=$matches[0][0];break;}
}
if(!$match){fwrite(STDERR,"P32_NO_GO_SIDEBAR_ANCHOR_NOT_UNIQUE\n");exit(6);}
$tag=strtolower(substr($match[0],1,strcspn(substr($match[0],1)," >")));
$afterOpen=$match[1]+strlen($match[0]);
$closing='</'.$tag.'>';
$closingAt=strpos($beforeWithoutFloat,$closing,$afterOpen);
if($closingAt===false){fwrite(STDERR,"P32_NO_GO_CLOSING_SIDEBAR\n");exit(6);}
$after=substr_replace($beforeWithoutFloat,"\n".$block."\n",$closingAt,0);
if(substr_count($after,'P32_ERP_SIDEBAR_V2')!==1||str_contains($after,'id="am-p32-nav"'))exit(7);
try{
 chdir($root);require 'vendor/autoload.php';$app=require 'bootstrap/app.php';
 $app->make(\\Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
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
