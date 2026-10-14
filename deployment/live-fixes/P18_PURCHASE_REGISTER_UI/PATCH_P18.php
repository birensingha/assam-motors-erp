<?php
declare(strict_types=1);
/**
 * P18 Assam Motors Purchase Register modern Android-style UI.
 * ONE VIEW ONLY — no edits to business logic, routes, schema or database.
 * Run: php PATCH_P18.php check|apply /absolute/staging/laravel-root
 */
$mode=$argv[1]??'check';
$root=rtrim((string)($argv[2]??''),'/');
if(!in_array($mode,['check','apply'],true)||!is_file($root.'/artisan')){
 fwrite(STDERR,"Usage: php PATCH_P18.php check|apply /absolute/laravel-root\n");exit(2);
}
$rel='resources/views/purchases/index.blade.php';
$path=$root.'/'.$rel;
if(!is_file($path)||is_link($path)){fwrite(STDERR,"P18_NO_GO_SOURCE_MISSING\n");exit(3);}
$before=(string)file_get_contents($path);
$marker='P18_PURCHASE_REGISTER_ANDROID_UI';
if(str_contains($before,$marker)){echo "P18_ALREADY_INSTALLED_NOOP\n";exit(0);}
function p18Once(string $s,string $old,string $new,string $name):string{
 $n=substr_count($s,$old);
 if($n!==1)throw new RuntimeException('P18_NO_GO_ANCHOR_'.$name.'_COUNT='.$n);
 return str_replace($old,$new,$s);
}
$old=<<<'OLD'
<div class="actions" style="justify-content:flex-end;margin-bottom:12px">
    <a class="btn primary" href="{{ route('erp.purchases.osl.create') }}">
        ＋ New OSL Purchase
    </a>
</div>
<div class="page-head">
    <div><h1>Purchase Register</h1><p>Parts + OSL purchase history. View stays readable and opens as a full ERP page.</p></div>
    <a class="btn primary" href="{{ route('erp.purchases.create') }}">＋ New Parts Purchase</a>
</div>
OLD;
$new=<<<'BLADE'
{{-- P18_PURCHASE_REGISTER_ANDROID_UI · Frontend-only upgrade, historical records untouched --}}
<style>
.p18-purchase-register{--p18-navy:#102e59;--p18-blue:#195cab;--p18-green:#087d65;--p18-ink:#102e54;--p18-soft:#f1f6fd;--p18-border:#d6e3f0;color:var(--p18-ink)}
.p18-purchase-register .p18-hero{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;padding:20px 22px;background:linear-gradient(120deg,#f6faff,#eef6ff);border:1px solid #d4e4f4;border-radius:20px;box-shadow:0 7px 24px #1639660d;margin:0 0 15px}
.p18-purchase-register .p18-overline{font-size:11px;color:#31658a;font-weight:800;letter-spacing:.095em;text-transform:uppercase;margin:0 0 6px}
.p18-purchase-register .p18-heading h1{margin:0;color:#112f57;font-size:clamp(24px,2.3vw,32px);font-weight:850;line-height:1.15}
.p18-purchase-register .p18-heading p{margin:8px 0 0;font-size:13px;color:#5a728e;line-height:1.5}
.p18-purchase-register .p18-actions{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));align-items:stretch;gap:10px;min-width:min(100%,360px)}
.p18-purchase-register .p18-create{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:49px;padding:10px 15px;border-radius:14px;color:#fff;text-decoration:none;font-weight:800;font-size:13px;white-space:nowrap;border:1px solid transparent;box-shadow:0 5px 12px #15376325;transition:transform .15s ease,box-shadow .15s ease,filter .15s ease}
.p18-purchase-register .p18-create:hover{transform:translateY(-2px);filter:brightness(1.045);box-shadow:0 7px 17px #15376335;color:#fff}
.p18-purchase-register .p18-create:focus-visible{outline:3px solid #f0ba32;outline-offset:3px}
.p18-purchase-register .p18-osl{background:linear-gradient(135deg,#07936f,#006b59)}
.p18-purchase-register .p18-parts{background:linear-gradient(135deg,#2576d8,#164998)}
.p18-purchase-register .p18-plus{display:inline-grid;place-items:center;width:24px;height:24px;flex:0 0 24px;border-radius:8px;font-size:20px;line-height:1;background:#ffffff2d}
.p18-purchase-register .p18-filter{display:flex;align-items:stretch;gap:9px;flex-wrap:wrap;margin-bottom:16px!important;border-radius:16px!important;padding:13px!important;border:1px solid var(--p18-border)!important;background:#fff;box-shadow:0 5px 15px #0f345c0a}
.p18-purchase-register .p18-filter input[name="q"]{flex:1 1 350px;min-width:150px}
.p18-purchase-register .p18-filter select[name="type"]{flex:0 1 175px;min-width:140px}
.p18-purchase-register .p18-filter input,.p18-purchase-register .p18-filter select{background:#f7faff;color:#123858;border:1px solid #c9daee;border-radius:11px;padding:11px 13px;min-height:46px;font-size:14px}
.p18-purchase-register .p18-filter input:focus,.p18-purchase-register .p18-filter select:focus{outline:2px solid #81b5ea;outline-offset:1px}
.p18-purchase-register .p18-filter .btn{border-radius:11px;min-height:46px;padding:11px 17px;font-weight:750}
.p18-purchase-register .p18-filter button.btn{background:#173f76;color:#fff}
.p18-purchase-register .p18-register-table{border:1px solid #d8e5f0;box-shadow:0 5px 20px #0d346110;border-radius:17px;overflow-x:auto;-webkit-overflow-scrolling:touch}
.p18-purchase-register .p18-register-table table{width:100%;min-width:825px;border-collapse:collapse}
.p18-purchase-register .p18-register-table thead th{padding:14px 12px;color:#fff;background:#183d73;white-space:nowrap;font-size:12px;font-weight:800;letter-spacing:.02em;text-align:left}
.p18-purchase-register .p18-register-table thead th.num{text-align:right}
.p18-purchase-register .p18-register-table tbody td{padding:13px 12px;border-bottom:1px solid #e4edf6;vertical-align:middle;font-size:13px}
.p18-purchase-register .p18-register-table tbody tr:nth-child(even){background:#f8fbff}
.p18-purchase-register .p18-register-table tbody tr:hover{background:#edf6ff}
.p18-purchase-register .p18-register-table tbody tr:last-child td{border-bottom:0}
.p18-purchase-register .p18-register-table .actions{display:flex;gap:7px;flex-wrap:nowrap}
.p18-purchase-register .p18-register-table .btn.small{border-radius:9px;white-space:nowrap;padding:7px 10px}
.p18-purchase-register .p18-register-table .badge{border-radius:8px}
@media(min-width:700px){.p18-purchase-register .p18-hero{flex-wrap:nowrap}}
@media(max-width:699px){.p18-purchase-register .p18-hero{padding:16px}.p18-purchase-register .p18-actions{min-width:100%;width:100%}.p18-purchase-register .p18-filter input[name="q"]{flex-basis:100%}.p18-purchase-register .p18-filter select[name="type"]{flex:1 1 140px}}
@media(max-width:410px){.p18-purchase-register .p18-create{font-size:11px;padding:10px 5px;gap:5px;white-space:normal;text-align:center;line-height:1.25}.p18-purchase-register .p18-plus{width:20px;height:20px;flex-basis:20px;font-size:17px}}
@media(prefers-reduced-motion:reduce){.p18-purchase-register .p18-create{transition:none}.p18-purchase-register .p18-create:hover{transform:none}}
</style>
<div class="p18-purchase-register">
 <header class="p18-hero">
  <div class="p18-heading">
   <div class="p18-overline">ASSAM MOTORS · WORKSHOP PURCHASES</div>
   <h1>Purchase Register</h1>
   <p>Parts and OSL purchases · supplier bills, workshop allocation and purchase history</p>
  </div>
  <nav class="p18-actions" aria-label="Create purchase">
   <a class="p18-create p18-osl" href="{{ route('erp.purchases.osl.create') }}"><span class="p18-plus" aria-hidden="true">+</span>New OSL Purchase</a>
   <a class="p18-create p18-parts" href="{{ route('erp.purchases.create') }}"><span class="p18-plus" aria-hidden="true">+</span>New Parts Purchase</a>
  </nav>
 </header>
BLADE;
try{
 $after=p18Once($before,$old,$new,'purchase_header_and_buttons');
 $after=p18Once($after,'<form method="get" class="filterbar card compact">','<form method="get" class="filterbar card compact p18-filter">','search_form');
 $after=p18Once($after,'<div class="card table-card">','<div class="card table-card p18-register-table">','register_table');
 $after=p18Once($after,"\n@endsection","\n</div>\n@endsection",'root_closing');
 if(substr_count($after,$marker)!==1||substr_count($after,'route(\'erp.purchases.osl.create\')')!==1
     ||substr_count($after,'route(\'erp.purchases.create\')')!==1){
      throw new RuntimeException('P18 validation: purchase routes or version marker');
 }
 chdir($root);
 require 'vendor/autoload.php';
 $app=require 'bootstrap/app.php';
 $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
 token_get_all(app('blade.compiler')->compileString($after),TOKEN_PARSE);
 echo 'P18_PRECHECK_OLD_SHA256='.hash('sha256',$before)."\n";
 echo 'P18_PRECHECK_NEW_SHA256='.hash('sha256',$after)."\n";
 echo "P18_COMPILED_BLADE_SYNTAX_PASS\n";
}catch(Throwable $e){
 fwrite(STDERR,"P18_NO_GO_PREFLIGHT ".$e->getMessage()."\n");exit(5);
}
if($mode==='check'){echo "P18_DRY_RUN_PASS_NO_FILES_OR_DATA_CHANGED\n";exit(0);}
$dir=$root.'/storage/app/p18_purchase_register_backup_'.date('Ymd_His').'_'.getmypid();
if(!mkdir($dir,0700,true))exit(6);
$backup=$dir.'/index.blade.php';
if(!copy($path,$backup)||hash_file('sha256',$backup)!==hash('sha256',$before)){
 fwrite(STDERR,"P18_NO_GO_BACKUP_VERIFY\n");exit(7);
}
if(hash_file('sha256',$path)!==hash('sha256',$before)){
 fwrite(STDERR,"P18_NO_GO_CONCURRENT_CHANGE\n");exit(8);
}
$tmp=tempnam(dirname($path),'.p18_');
try{
 if(!$tmp||file_put_contents($tmp,$after,LOCK_EX)!==strlen($after))throw new RuntimeException('temporary write');
 chmod($tmp,fileperms($path)&0777);
 if(!rename($tmp,$path))throw new RuntimeException('atomic replace');
 if(hash_file('sha256',$path)!==hash('sha256',$after))throw new RuntimeException('post-change hash');
 echo "P18_APPLIED_BACKUP=".$backup."\n";
 echo "P18_SOURCE_ONLY_NO_PURCHASE_OR_FINANCIAL_DATA_CHANGED\n";
}catch(Throwable $e){
 @unlink($tmp);
 copy($backup,$path);
 fwrite(STDERR,"P18_ROLLBACK_PERFORMED ".$e->getMessage()."\n");exit(9);
}
