<?php
declare(strict_types=1);
/**
 * P34: Restore Workshop ERP as ONE native sidebar list with exactly 12 links.
 * Removes the P32 Workshop Operations popover, retains original ERP routes,
 * applies Android-inspired modern styling with subtle red active highlighting.
 * Source-only, guarded, backed up. No database mutations.
 */
$mode=(string)($argv[1]??'check');
$root=rtrim((string)($argv[2]??''),'/');
if(!in_array($mode,['check','apply'],true) || !is_file($root.'/artisan')){fwrite(STDERR,"P34_USAGE check|apply ERP_ROOT\n");exit(2);}
$file=$root.'/resources/views/layouts/erp.blade.php';
if(!is_file($file)||is_link($file)){fwrite(STDERR,"P34_NO_GO_LAYOUT_MISSING\n");exit(3);}
$before=(string)file_get_contents($file);
if(str_contains($before,'P34_SIDEBAR_V1')){echo "P34_ALREADY_INSTALLED\n";exit(0);}
$clean=$before;
// Strictly remove only the previously deployed P32 Workshop Operations addition.
$oldPattern='~\{\{--\s*P32_ERP_SIDEBAR_V3:.*?</details>\s*~s';
if(preg_match_all($oldPattern,$clean)!==1){fwrite(STDERR,"P34_NO_GO_EXPECTED_P32_BOX_NOT_FOUND_OR_DUPLICATED\n");exit(4);}
$clean=(string)preg_replace($oldPattern,'',$clean,1);
if(str_contains($clean,'am-p32-workshop')||str_contains($clean,'WORKSHOP OPERATIONS')){fwrite(STDERR,"P34_NO_GO_P32_BOX_REMAINS\n");exit(4);}
$heading='<div class="nav-group-label">WORKSHOP ERP</div>';
$nav='<nav class="sidebar-nav">';
if(substr_count($clean,$heading)!==1 || substr_count($clean,$nav)!==1){
 fwrite(STDERR,"P34_NO_GO_HEADING_OR_NAV_NOT_UNIQUE\n");exit(5);
}
$start=strpos($clean,$heading);
$end=strpos($clean,'</nav>',$start+strlen($heading));
if($end===false){fwrite(STDERR,"P34_NO_GO_NAV_CLOSE_MISSING\n");exit(5);}
$section=substr($clean,$start,$end-$start);
$expectedRoutes=[
 'erp.workshop',
 'erp.job-cards.index',
 'erp.vehicle-inspection',
 'erp.inspection-manager.index',
 'erp.estimates.index',
 'erp.rot-performance',
 'erp.customers',
 'erp.purchases.index',
 'erp.expenses.index',
 'erp.payments.index',
 'erp.vendors.index',
 'erp.configuration',
];
$offset=-1;
foreach($expectedRoutes as $route){
 $needle="route('".$route."')";
 if(substr_count($section,$needle)!==1){fwrite(STDERR,"P34_NO_GO_ROUTE_COUNT $route\n");exit(6);}
 $found=strpos($section,$needle);
 if($found<=$offset){fwrite(STDERR,"P34_NO_GO_ORDER $route\n");exit(6);}
 $offset=$found;
}
if(preg_match_all('/<a\b[^>]*href\s*=/',$section)!==12){
 fwrite(STDERR,"P34_NO_GO_EXPECTED_12_WORKSHOP_ITEMS\n");exit(6);
}
$labelMap=[
 ' Job Cards / ROT</a>' => ' Job Cards</a>',
 ' Manager Inspection Work</a>' => ' Manage Inspection Work</a>',
 ' Estimates</a>' => ' Estimate</a>',
 ' Customers</a>' => ' Customer</a>',
 ' Payment Vouchers</a>' => ' Payment</a>',
];
foreach($labelMap as $old=>$new){
 if(substr_count($section,$old)!==1){fwrite(STDERR,"P34_NO_GO_LABEL ".trim($old)."\n");exit(7);}
 $section=str_replace($old,$new,$section);
}
$clean=substr_replace($clean,$section,$start,$end-$start);
$style=<<<'BLADE'
{{-- P34_SIDEBAR_V1: modern Android-style native ERP sidebar; subtle red active state. --}}
<style>
html body .sidebar-nav.am-android-nav{padding-bottom:16px}
html body .sidebar-nav.am-android-nav .nav-group-label{
 margin:17px 12px 8px!important;padding:10px 10px 8px!important;
 color:#95cbe7!important;letter-spacing:.1em;font-size:10px;font-weight:800;
 border-bottom:1px solid rgba(169,204,229,.16)
}
html body .sidebar-nav.am-android-nav a,
html body .sidebar-nav.am-android-nav a:visited{
 display:flex!important;align-items:center!important;gap:12px!important;
 margin:3px 8px!important;padding:11px 12px!important;min-height:44px;
 border:1px solid transparent!important;border-left:3px solid transparent!important;
 border-radius:12px!important;text-decoration:none!important;
 color:#eaf2ff!important;background:transparent!important;
 font-size:12.5px;font-weight:600;line-height:1.3!important;
 transition:background .14s ease,box-shadow .14s ease,transform .14s ease
}
html body .sidebar-nav.am-android-nav a > span:first-child{
 display:inline-flex!important;align-items:center;justify-content:center;flex:0 0 29px;
 width:29px;height:29px;border-radius:9px;font-size:16px;
 background:rgba(126,174,218,.17);color:#fff
}
html body .sidebar-nav.am-android-nav a:hover{
 background:rgba(74,125,172,.22)!important;transform:translateX(2px);
 color:#fff!important
}
html body .sidebar-nav.am-android-nav a.active,
html body .sidebar-nav.am-android-nav a[aria-current="page"]{
 background:linear-gradient(90deg,rgba(212,65,77,.25),rgba(30,79,124,.22))!important;
 border-color:rgba(235,100,110,.17)!important;
 border-left:3px solid #ed636b!important;
 color:#fff!important;
 box-shadow:0 3px 12px rgba(0,0,0,.12)
}
html body .sidebar-nav.am-android-nav a.active > span:first-child{
 background:rgba(237,99,107,.25);color:#ffdce0
}
html body .sidebar-nav.am-android-nav a:focus-visible{
 outline:2px solid #ef858a!important;outline-offset:2px
}
@media(prefers-reduced-motion:reduce){
 html body .sidebar-nav.am-android-nav a{transition:none;transform:none}
}
@media print{html body .sidebar-nav.am-android-nav{display:none!important}}
</style>
BLADE;
$clean=str_replace($nav,$style."\n".'<nav class="sidebar-nav am-android-nav">',$clean);
if(substr_count($clean,'P34_SIDEBAR_V1')!==1||str_contains($clean,'P32_ERP_SIDEBAR_V3')){fwrite(STDERR,"P34_NO_GO_FINAL_MARKERS\n");exit(8);}
try{
 chdir($root);require 'vendor/autoload.php';$app=require 'bootstrap/app.php';
 $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
 $compiled=app('blade.compiler')->compileString($clean);
 token_get_all($compiled,TOKEN_PARSE);
 echo "P34_BLADE_COMPILE_PASS\n";
}catch(Throwable $e){fwrite(STDERR,"P34_NO_GO_BLADE ".$e->getMessage()."\n");exit(9);}
if($mode==='check'){echo "P34_12_ROUTES_ORDERED_NO_DUPLICATES_DRY_RUN_PASS\n";exit(0);}
$backup=$root.'/storage/app/p34_sidebar_backup_'.date('Ymd_His').'_'.getmypid().'.blade.php';
if(!copy($file,$backup)||hash_file('sha256',$backup)!==hash('sha256',$before)){fwrite(STDERR,"P34_NO_GO_BACKUP\n");exit(10);}
if(hash_file('sha256',$file)!==hash('sha256',$before)){fwrite(STDERR,"P34_NO_GO_CONCURRENT_LAYOUT_CHANGE\n");exit(11);}
$tmp=tempnam(dirname($file),'.p34_');
if(!$tmp||file_put_contents($tmp,$clean,LOCK_EX)!==strlen($clean)||!chmod($tmp,fileperms($file)&0777)||!rename($tmp,$file)){
 if($tmp)@unlink($tmp);
 copy($backup,$file);
 fwrite(STDERR,"P34_SOURCE_ROLLBACK\n");exit(12);
}
if(hash_file('sha256',$file)!==hash('sha256',$clean)){
 copy($backup,$file);fwrite(STDERR,"P34_CHECKSUM_ROLLBACK\n");exit(13);
}
echo "P34_APPLIED_BACKUP=$backup\n";
echo "P34_12_NATIVE_LINKS_ANDROID_STYLE_RED_HIGHLIGHT_APPLIED\n";
