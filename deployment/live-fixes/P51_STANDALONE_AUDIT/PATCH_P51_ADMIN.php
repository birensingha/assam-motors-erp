<?php
declare(strict_types=1);
/**
 * P51 Phase 1: create NEW Admin Console as a distinct web experience under
 * /erp/admin, secured by existing auth+EnsureErpAdmin and controller role.
 * No changes to OLD ERP, OLD Admin, databases or legacy APIs.
 */
$mode=(string)($argv[1]??'check');
$root=rtrim((string)($argv[2]??''),'/');
if(!in_array($mode,['check','apply'],true)||!is_file($root.'/artisan')){
 fwrite(STDERR,"P51_USAGE check|apply STAGING_ROOT\n");exit(2);
}
$routes=$root.'/routes/web.php';
$layout=$root.'/resources/views/layouts/erp.blade.php';
$files=[
 'app/Http/Controllers/Web/NewAdminConsoleController.php',
 'resources/views/layouts/new-admin.blade.php',
 'resources/views/admin/new-console.blade.php'
];
if(!is_file($routes)||!is_file($layout)){
 fwrite(STDERR,"P51_NO_GO_ROUTES_LAYOUT_MISSING\n");exit(3);
}
foreach($files as $f)if(!is_file($root.'/'.$f)){
 fwrite(STDERR,"P51_NO_GO_PAYLOAD_MISSING ".basename($f)."\n");exit(3);
}
$beforeRoutes=(string)file_get_contents($routes);
$beforeLayout=(string)file_get_contents($layout);
$marker='P51_NATIVE_ADMIN_CONSOLE_V1';
if(str_contains($beforeRoutes,$marker)){
 if(str_contains($beforeLayout,'P51_NEW_ADMIN_SWITCH_V1')){
   echo "P51_ALREADY_INSTALLED\n";exit(0);
 }
 fwrite(STDERR,"P51_NO_GO_PARTIAL_INSTALL\n");exit(4);
}
$anchor="    Route::get('/dashboard', [AdminDashboardPageController::class,'main'])->name('erp.dashboard');";
if(substr_count($beforeRoutes,$anchor)!==1 ||
   !str_contains($beforeRoutes,"Route::middleware(['auth', EnsureErpAdmin::class])->prefix('erp')")){
 fwrite(STDERR,"P51_NO_GO_EXPECTED_PROTECTED_ERP_ROUTE_CONTEXT\n");exit(5);
}
$addition=<<<'BLADE'

    // P51_NATIVE_ADMIN_CONSOLE_V1: separate native staging Admin interface;
    // inherits the existing auth + EnsureErpAdmin middleware.
    Route::get('/admin', [\App\Http\Controllers\Web\NewAdminConsoleController::class,'dashboard'])->name('erp.admin.console');
BLADE;
$newRoutes=str_replace($anchor,$anchor.$addition,$beforeRoutes);
$start=strpos($beforeLayout,'<div class="sidebar-footer">');
$close=$start===false?false:strpos($beforeLayout,'</div>',$start);
if($start===false||$close===false||substr_count($beforeLayout,'<div class="sidebar-footer">')!==1){
 fwrite(STDERR,"P51_NO_GO_NEW_ERP_FOOTER_NOT_FOUND\n");exit(6);
}
$footer=substr($beforeLayout,$start,$close+6-$start);
if(!str_contains($footer,'Classic Admin')||!str_contains($footer,'Classic Workshop')){
 fwrite(STDERR,"P51_NO_GO_LEGACY_LINKS_ALREADY_CHANGED\n");exit(6);
}
$newFooter=<<<'BLADE'
<div class="sidebar-footer">
  {{-- P51_NEW_ADMIN_SWITCH_V1: native Admin area; do not link NEW ERP to OLD Legacy. --}}
  <a href="{{ route('erp.admin.console') }}" style="background:#235c97;color:#fff;border-color:#5484b1;font-weight:800">▣ NEW Admin Console →</a>
</div>
BLADE;
$newLayout=substr_replace($beforeLayout,$newFooter,$start,strlen($footer));
if(substr_count($newRoutes,'P51_NATIVE_ADMIN_CONSOLE_V1')!==1||
   substr_count($newLayout,'P51_NEW_ADMIN_SWITCH_V1')!==1||
   str_contains(substr($newLayout,$start,400),'Classic Workshop')){
 fwrite(STDERR,"P51_NO_GO_DUPLICATE_ADMIN_ROUTE_OR_LEGACY_LINK\n");exit(7);
}
try{
 chdir($root);require 'vendor/autoload.php';
 $app=require 'bootstrap/app.php';
 $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
 token_get_all($newRoutes,TOKEN_PARSE);
 foreach([$newLayout,
          (string)file_get_contents($root.'/resources/views/layouts/new-admin.blade.php'),
          (string)file_get_contents($root.'/resources/views/admin/new-console.blade.php')] as $s){
  token_get_all(app('blade.compiler')->compileString($s),TOKEN_PARSE);
 }
 $needed=['erp.dashboard','erp.logout','erp.attendance','erp.staff-notifications.index',
          'erp.configuration','erp.masters.parts','erp.vendors.index','erp.fix49.payment-register'];
 foreach($needed as $name)if(!\Illuminate\Support\Facades\Route::has($name))
    throw new RuntimeException('Missing protected native route '.$name);
 $authRoute=\Illuminate\Support\Facades\Route::getRoutes()->getByName('erp.dashboard');
 $middleware=$authRoute ? $authRoute->gatherMiddleware() : [];
 if(!in_array('auth',$middleware,true) ||
    !in_array('App\\Http\\Middleware\\EnsureErpAdmin',$middleware,true))
    throw new RuntimeException('ERP Admin auth protection missing');
 echo "P51_ADMIN_ROUTES_VIEWS_AND_GUARD_DRY_RUN_PASS\n";
}catch(Throwable $e){fwrite(STDERR,"P51_NO_GO_PRECHECK ".get_class($e).' '.$e->getMessage()."\n");exit(8);}
if($mode==='check')exit(0);
$backup=$root.'/storage/app/p51_new_admin_backup_'.date('Ymd_His').'_'.getmypid();
if(!mkdir($backup,0700,true))exit(9);
if(!copy($routes,$backup.'/routes_web.php')||!copy($layout,$backup.'/erp_layout.blade.php')||
    hash_file('sha256',$backup.'/routes_web.php')!==hash('sha256',$beforeRoutes)||
    hash_file('sha256',$backup.'/erp_layout.blade.php')!==hash('sha256',$beforeLayout)){
 fwrite(STDERR,"P51_NO_GO_BACKUP_FAILED\n");exit(9);
}
if(hash_file('sha256',$routes)!==hash('sha256',$beforeRoutes)||
   hash_file('sha256',$layout)!==hash('sha256',$beforeLayout)){
 fwrite(STDERR,"P51_NO_GO_CONCURRENT_CHANGE\n");exit(10);
}
$write=function(string $file,string $content):void{
 $tmp=tempnam(dirname($file),'.p51_');
 if(!$tmp||file_put_contents($tmp,$content,LOCK_EX)!==strlen($content)||
    !chmod($tmp,fileperms($file)&0777)||!rename($tmp,$file)){
   if($tmp)@unlink($tmp);
   throw new RuntimeException('Atomic write failed');
 }
};
try{
 $write($routes,$newRoutes);$write($layout,$newLayout);
 if(hash_file('sha256',$routes)!==hash('sha256',$newRoutes)||
    hash_file('sha256',$layout)!==hash('sha256',$newLayout)){
     throw new RuntimeException('Write checksum mismatch');
 }
}catch(Throwable $e){
 copy($backup.'/routes_web.php',$routes);
 copy($backup.'/erp_layout.blade.php',$layout);
 fwrite(STDERR,"P51_SOURCE_ROLLBACK ".$e->getMessage()."\n");exit(11);
}
echo "P51_ADMIN_CONSOLE_APPLIED\nP51_BACKUP=".$backup."\n";
