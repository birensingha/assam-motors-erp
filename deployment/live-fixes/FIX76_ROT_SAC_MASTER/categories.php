<?php
declare(strict_types=1);
$root=$argv[1]??'';if(!is_file($root.'/artisan'))exit(2);chdir($root);
require 'vendor/autoload.php';$a=require 'bootstrap/app.php';$a->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db=\Illuminate\Support\Facades\DB::class;
$c=$db::connection();$c->statement('START TRANSACTION READ ONLY');
try{
$rows=$db::table('labour_master')->select('id','rot_code','rot_description','category','active','gst_percent')->get();
echo 'FIX76_TOTAL '.$rows->count()."\n";
foreach($rows->groupBy(fn($x)=>trim((string)($x->category??''))?:'Uncategorized') as $category=>$items)echo 'FIX76_CATEGORY '.json_encode(['category'=>$category,'count'=>$items->count(),'examples'=>$items->take(3)->map(fn($r)=>$r->rot_code.' '.$r->rot_description)->values()->all()])."\n";
$risks=$rows->filter(fn($r)=>preg_match('/inspection|diagnostic|towing|pickup|drop|transport|sale|washing|detailing|painting|washing|alignment|balanc|scanning|a.c.|testing|tyre|car wash|insurance|admin|handling|service charge|labour fee|ac check|battery charge/i',$r->rot_description.' '.$r->category));
echo 'FIX76_SPECIAL_REVIEW_COUNT '.$risks->count()."\n";
foreach($risks->take(55) as $r)echo 'FIX76_REVIEW '.json_encode(['code'=>$r->rot_code,'description'=>$r->rot_description,'category'=>$r->category])."\n";
echo 'FIX76_CATEGORIES_READONLY_PASS'."\n";
} finally{$c->rollBack();}
