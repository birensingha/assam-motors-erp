<?php
declare(strict_types=1);
$root=$argv[1]??null;if(!$root||!is_file($root.'/artisan'))exit(2);
chdir($root);require 'vendor/autoload.php';$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$router=\Illuminate\Support\Facades\Route::class;
foreach($router::getRoutes() as $r){$name=(string)$r->getName();
 if(str_contains($name,'osl')||str_contains($name,'estimate')||str_contains($name,'labour'))
  echo 'FIX83_ROUTE_LIVE '.json_encode(['name'=>$name,'uri'=>$r->uri(),'verbs'=>$r->methods()])."\n";
}
$db=\Illuminate\Support\Facades\DB::class;
foreach(['job_card_labour','job_cards','workshop_purchases','workshop_purchase_lines','estimate_lines','estimates'] as $table){
 if(!\Illuminate\Support\Facades\Schema::hasTable($table))continue;
 echo 'FIX83_DB_SCHEMA '.json_encode(['table'=>$table,'columns'=>\Illuminate\Support\Facades\Schema::getColumnListing($table)])."\n";
}
echo 'FIX83_APP_ROUTE_SCHEMA_READONLY_PASS'."\n";
