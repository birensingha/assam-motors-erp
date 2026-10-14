<?php
declare(strict_types=1);
/** P32: ERP navigation-only patch. No database or business record changes. */
$mode=$argv[1]??'check';
$root=rtrim((string)($argv[2]??''),'/');
if(!in_array($mode,['check','apply'],true)||!is_file($root.'/artisan')){fwrite(STDERR,"Usage: php PATCH_P32.php check|apply /path/to/erp\n");exit(2);}
$file=$root.'/resources/views/layouts/erp.blade.php';
$routes=$root.'/routes/web.php';
if(!is_file($file)||is_link($file)||!is_file($routes)){fwrite(STDERR,"P32_NO_GO_LAYOUT_OR_ROUTES_MISSING\n");exit(3);}
$before=file_get_contents($file);$routeText=file_get_contents($routes);
if(str_contains($before,'P32_ERP_NAVIGATION')){echo "P32_ALREADY_INSTALLED\n";exit(0);}
foreach(['/counter-sales','/counter-sales/create'] as $required){
 if(!str_contains($routeText,$required)){fwrite(STDERR,"P32_NO_GO_ROUTE_MISSING $required\n");exit(4);}
}
if(substr_count($before,'</body>')!==1){fwrite(STDERR,"P32_NO_GO_BODY_ANCHOR\n");exit(4);}
$block=<<<'BLADE'
{{-- P32_ERP_NAVIGATION: workshop shortcuts, accessible from all ERP pages. --}}
<style>
#am-p32-nav{position:fixed;right:16px;bottom:16px;z-index:999;display:flex;flex-direction:column;align-items:flex-end;gap:8px;font:600 13px Arial,sans-serif}
#am-p32-nav details{border:1px solid #a8c5d3;border-radius:12px;background:#fff;box-shadow:0 5px 18px #09234133;min-width:235px;overflow:hidden}
#am-p32-nav summary{padding:12px 16px;background:#102b50;color:#fff;cursor:pointer}
#am-p32-nav a{display:block;padding:12px 15px;border-bottom:1px solid #e4edf1;color:#123c67;text-decoration:none}
#am-p32-nav a:hover{background:#e9f4fb}
@media print{#am-p32-nav{display:none}}
</style>
<nav id="am-p32-nav" aria-label="Workshop ERP Navigation">
<details><summary>Workshop &amp; Sales Menu</summary>
<a href="{{ url('/erp/inspection-manager') }}">Inspection Manager</a>
<a href="{{ url('/erp/job-cards') }}">Job Cards — Lifecycle / QC / Billing</a>
<a href="{{ url('/erp/counter-sales/create') }}">+ Direct Parts Sale Invoice</a>
<a href="{{ url('/erp/counter-sales') }}">Counter Invoice Register</a>
</details></nav>
BLADE;
$after=str_replace('</body>',$block."\n</body>",$before);
try{
 chdir($root);require 'vendor/autoload.php';$app=require 'bootstrap/app.php';
 $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
 token_get_all(app('blade.compiler')->compileString($after),TOKEN_PARSE);
 echo "P32_BLADE_COMPILE_PASS\n";
}catch(Throwable $e){fwrite(STDERR,"P32_NO_GO_BLADE ".$e->getMessage()."\n");exit(5);}
if($mode==='check'){echo "P32_DRY_RUN_PASS\n";exit(0);}
$backup=$root.'/storage/app/p32_erp_nav_'.date('Ymd_His').'.blade.php';
if(!copy($file,$backup)){fwrite(STDERR,"P32_NO_GO_BACKUP\n");exit(6);}
if(hash_file('sha256',$file)!==hash('sha256',$before)){fwrite(STDERR,"P32_NO_GO_CONCURRENT_CHANGE\n");exit(7);}
$tmp=tempnam(dirname($file),'.p32_');
if(!$tmp||file_put_contents($tmp,$after,LOCK_EX)!==strlen($after)||!rename($tmp,$file)){@unlink($tmp);copy($backup,$file);exit(8);}
echo "P32_APPLIED_BACKUP=$backup\n";
