<?php
declare(strict_types=1);
$root=rtrim((string)($argv[1]??''),'/');
if(!is_file("$root/artisan"))exit(2);
chdir($root);require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Route;
function out(string $key,mixed $data):void{echo "P55_".$key."=".json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE)."\n";}
out('MODE','SOURCE_SCHEMA_READ_ONLY');
foreach(['job_card_labour','job_cards','rot_sessions','rot_admin_corrections','rot_mechanic_segments','rot_session_events','job_card_change_audits','job_card_labour_edits','staff','invoices'] as $t)
 if(Schema::hasTable($t))out('COLUMNS',[$t,Schema::getColumnListing($t)]);
foreach(Route::getRoutes() as $r){
 if(str_contains($r->uri(),'job-cards')&&($r->getName()||str_contains($r->uri(),'labour')))
  out('ROUTE',[$r->uri(),$r->getName(),$r->methods(),$r->getActionName()]);
}
$sections=[
 ['resources/views/job-cards/edit.blade.php',1,300],
 ['app/Http/Controllers/Web/AdminJobCardPageController.php',240,420],
 ['app/Http/Controllers/Web/AdminJobCardPageController.php',420,610],
 ['app/Http/Controllers/Web/AdminJobCardPageController.php',780,930],
 ['app/Http/Controllers/Web/AdminJobCardPageController.php',1010,1200],
 ['app/Services/RotWorkflowService.php',175,355],
 ['app/Services/RotWorkflowService.php',355,510],
 ['app/Http/Controllers/Web/AdminRotPageController.php',1,180],
 ['app/Services/RotWorkflowService.php',1,180],
 ['app/Http/Controllers/Web/AdminWorkshopControlPageController.php',1,150],
];
foreach($sections as [$name,$from,$to]){
 $fp="$root/$name";if(!is_file($fp)){out('MISSING',$name);continue;}
 $ls=file($fp);$rows=[];
 for($i=$from-1;$i<min(count($ls),$to);$i++)$rows[]=($i+1).':'.rtrim($ls[$i]);
 out('SOURCE',[$name,"$from-$to",$rows]);
}
echo "P55_END_READONLY\n";