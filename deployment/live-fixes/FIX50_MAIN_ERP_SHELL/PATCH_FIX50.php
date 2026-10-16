<?php
declare(strict_types=1);
/**
 * FIX50: native Web ERP shell — visible links to FIX49, aligned sidebar/topbar.
 * Works on current deployed P34+P35 Blade layout. No DB or finance writes.
 */
$mode = (string)($argv[1]??'check');
$root = rtrim((string)($argv[2]??''), '/');
if (!in_array($mode,['check','apply'],true) || !is_file($root.'/artisan')) {
    fwrite(STDERR,"FIX50_USAGE check|apply STAGING_ERP_ROOT\n");exit(2);
}
$path=$root.'/resources/views/layouts/erp.blade.php';
$theme=$root.'/resources/views/layouts/erp-shell-fix50.blade.php';
if(!is_file($path) || is_link($path) || !is_file($theme)) {
    fwrite(STDERR,"FIX50_NO_GO_LAYOUT_OR_THEME_NOT_PRESENT\n");exit(3);
}
$before=(string)file_get_contents($path);
if(str_contains($before,'FIX50_ERP_NAV_V1')) {
    $markers=['Workshop Finance','JC Amounts','OSL Purchases','Vendor Database','Voucher Editing','am50-top-shortcuts',"@include('layouts.erp-shell-fix50')"];
    foreach($markers as $marker){
        if(!str_contains($before,$marker)){fwrite(STDERR,"FIX50_NO_GO_PARTIAL_INSTALL\n");exit(4);}
    }
    echo "FIX50_ALREADY_INSTALLED\n";exit(0);
}
$required=['P34_SIDEBAR_V1','P35_COUNTER_SALES_NATIVE_V1','<nav class="sidebar-nav am-android-nav">','<header class="erp-topbar">',
    '<div class="topbar-live">',"<div class=\"topbar-title\">@yield('topbar_title','ASSAM MOTORS')</div>",'<main class="page-shell'];
foreach($required as $anchor){
    if(substr_count($before,$anchor)!==1){fwrite(STDERR,"FIX50_NO_GO_ANCHOR ".substr($anchor,0,75)."\n");exit(5);}
}
if(substr_count($before,'</nav>')!==1 || substr_count($before,'</head>')!==1 ||
   substr_count($before,'</aside>')!==1 || str_contains($before,'am50-top-shortcuts')) {
    fwrite(STDERR,"FIX50_NO_GO_NON_UNIQUE_LAYOUT\n");exit(5);
}
$finance=<<<'BLADE'
        {{-- FIX50_ERP_NAV_V1: discoverable links, native Web ERP sidebar. --}}
        <div class="nav-group-label am50-section">WORKSHOP FINANCE</div>
        <a class="am50-finance {{ request()->is('erp/job-cards*') || request()->is('erp/workshop-tools/job-cards*') ? 'active':'' }}" href="{{ route('erp.job-cards.index') }}"><span>💰</span> JC Amounts (select JC)</a>
        <a class="am50-finance {{ request()->is('erp/workshop-tools/osl*') ? 'active':'' }}" href="{{ route('erp.fix48.osl-index') }}"><span>🧰</span> OSL Purchases</a>
        <a class="am50-finance {{ request()->is('erp/workshop-tools/vendor-database') ? 'active':'' }}" href="{{ route('erp.fix48.vendor-database') }}"><span>🏢</span> Vendor Database</a>
        <a class="am50-finance {{ request()->is('erp/workshop-tools/payment-vouchers*') ? 'active':'' }}" href="{{ route('erp.fix49.payment-register') }}"><span>🧾</span> Voucher Editing</a>
BLADE;
$quick=<<<'BLADE'
    <nav class="am50-top-shortcuts" aria-label="Workshop finance shortcuts">
      <a href="{{ route('erp.job-cards.index') }}" class="{{ request()->is('erp/job-cards*') || request()->is('erp/workshop-tools/job-cards*') ? 'active':'' }}">JC Amounts</a>
      <a href="{{ route('erp.fix48.osl-index') }}" class="{{ request()->is('erp/workshop-tools/osl*') ? 'active':'' }}">OSL Purchases</a>
      <a href="{{ route('erp.fix48.vendor-database') }}" class="{{ request()->is('erp/workshop-tools/vendor-database') ? 'active':'' }}">Vendor Database</a>
      <a href="{{ route('erp.fix49.payment-register') }}" class="{{ request()->is('erp/workshop-tools/payment-vouchers*') ? 'active':'' }}">Voucher Editing</a>
    </nav>
BLADE;
$after=str_replace('</nav>',$finance."\n    </nav>",$before);
$after=str_replace("<div class=\"topbar-title\">@yield('topbar_title','ASSAM MOTORS')</div>",
    "<div class=\"topbar-title\">@yield('topbar_title','ASSAM MOTORS')</div>\n".$quick,$after);
$after=str_replace('</aside>',"</aside>\n<button class=\"am50-overlay\" type=\"button\" aria-label=\"Close navigation\" onclick=\"document.body.classList.remove('sidebar-open')\"></button>",$after);
$after=str_replace('class="sidebar-toggle" type="button"',
    'class="sidebar-toggle" type="button" aria-label="Toggle ERP navigation" aria-controls="erpSidebar"',$after);
$after=str_replace('</head>',"    @include('layouts.erp-shell-fix50')\n</head>",$after);
if(substr_count($after,'FIX50_ERP_NAV_V1')!==1 ||
   substr_count($after,'class="am50-top-shortcuts"')!==1 ||
   substr_count($after,'class="am50-overlay"')!==1 ||
   substr_count($after,"@include('layouts.erp-shell-fix50')")!==1) {
    fwrite(STDERR,"FIX50_NO_GO_INTEGRATION_MARKERS\n");exit(6);
}
$section=substr($after,strpos($after,'WORKSHOP FINANCE'),strpos($after,'</nav>')-strpos($after,'WORKSHOP FINANCE'));
if(preg_match_all('/<a\b[^>]*href\s*=/',$section)!==4){fwrite(STDERR,"FIX50_NO_GO_FINANCE_LINK_COUNT\n");exit(6);}
try {
    chdir($root);require 'vendor/autoload.php';
    $app=require 'bootstrap/app.php';
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    foreach(['erp.job-cards.index','erp.fix48.osl-index','erp.fix48.vendor-database','erp.fix49.payment-register'] as $route){
        if(!\Illuminate\Support\Facades\Route::has($route))throw new RuntimeException("Missing route ".$route);
    }
    token_get_all(app('blade.compiler')->compileString($after),TOKEN_PARSE);
    echo "FIX50_BLADE_ROUTES_COMPILE_PASS\n";
} catch(Throwable $e){
    fwrite(STDERR,"FIX50_NO_GO_COMPILATION ".$e->getMessage()."\n");exit(7);
}
if($mode==='check'){echo "FIX50_28_LINK_NATIVE_MENU_AND_4_TOP_SHORTCUTS_DRY_RUN_PASS\n";exit(0);}
$backup=$root.'/storage/app/fix50_erp_shell_backup_'.date('Ymd_His').'_'.getmypid().'.blade.php';
if(!copy($path,$backup) || hash_file('sha256',$backup)!==hash('sha256',$before)){
    fwrite(STDERR,"FIX50_NO_GO_BACKUP\n");exit(8);
}
if(hash_file('sha256',$path)!==hash('sha256',$before)){
    fwrite(STDERR,"FIX50_NO_GO_CONCURRENT_LAYOUT_CHANGE\n");exit(9);
}
$tmp=tempnam(dirname($path),'.fix50_');
if(!$tmp || file_put_contents($tmp,$after,LOCK_EX)!==strlen($after) ||
   !chmod($tmp,fileperms($path)&0777) || !rename($tmp,$path)) {
    if($tmp)@unlink($tmp);
    copy($backup,$path);
    fwrite(STDERR,"FIX50_WRITE_FAILED_AND_ROLLBACK_ATTEMPTED\n");exit(10);
}
echo "FIX50_APPLIED_BACKUP=".$backup."\nFIX50_ERP_SHELL_NATIVE_NAV_INSTALLED\n";
