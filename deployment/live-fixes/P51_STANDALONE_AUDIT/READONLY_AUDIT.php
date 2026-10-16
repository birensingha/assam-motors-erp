<?php
declare(strict_types=1);
/**
 * P51 — Read-only, no PII, no environment variables/credential values.
 * Inspects staging route ownership, legacy path coupling, metadata-only table
 * inventory and native Admin/ERP separation readiness. Never writes data.
 */
$root=rtrim((string)($argv[1]??''),'/');
if(!$root||!is_file($root.'/artisan')){fwrite(STDERR,"P51_INVALID_STAGING_ROOT\n");exit(2);}
chdir($root);
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Route;
function state(string $key,mixed $v):void {echo 'P51 '.preg_replace('/[^A-Z0-9_]/','_',strtoupper($key)).'='.((is_scalar($v)||$v===null)?(string)$v:json_encode($v))."\n";}
function scanFiles(string $root,array $dirs):array {
 $extensions=['php','blade.php','js','vue','ts'];
 $result=[];$seen=0;
 foreach($dirs as $dir){
  $abs=$root.'/'.$dir;if(!is_dir($abs))continue;
  $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($abs,FilesystemIterator::SKIP_DOTS));
  foreach($it as $file){
   if(!$file->isFile()||$file->getSize()>1000000)continue;
   $p=$file->getPathname();$relative=substr($p,strlen($root)+1);
   if(!preg_match('~\.(?:php|js|ts|vue)$~',$p))continue;
   if(++$seen>4500)break 2;
   $content=@file_get_contents($p);if($content===false)continue;
   $markers=[];
   if(preg_match('~(?:/legacy/|legacy/workshop|legacy/admin|customers\.php(?:\?action|\\\\\?action)?|staff-inspection\.php)~i',$content))$markers[]='LEGACY_HTTP_OR_PHP';
   if(preg_match('~(?:DB::connection\(|config\(\x27database\.|mysqli_|mysql_connect|new\\s+PDO\\s*\()~i',$content))$markers[]='SECONDARY_DB_OR_RAW_SQL';
   if(preg_match('~(?:file_get_contents\(|include_once|require_once)\s*\(?\s*[\x27\x22][^\x27\x22]*(?:legacy|workshop/)~i',$content))$markers[]='LEGACY_FILE_INCLUDE';
   if($markers)$result[$relative]=$markers;
  }
 }
 return $result;
}
state('MODE','READ_ONLY_METADATA_NO_BUSINESS_ROWS');
state('STAGING_ARTISAN','YES');
$docroot=dirname(dirname($root)).'/public_html';
$candidates=[
 $root.'/public/legacy',
 $docroot.'/staging/legacy',
 $docroot.'/workshop',
 $docroot.'/admin'
];
foreach($candidates as $idx=>$loc)state('LEGACY_PATH_'.$idx,is_dir($loc)?'DIRECTORY_PRESENT':'NOT_FOUND');
$routeStats=['total'=>0,'erp'=>0,'admin'=>0,'legacy'=>0,'staff'=>0,'api'=>0];
$legacyRoutes=[];$adminRoutes=[];$erpRoutes=[];
foreach(Route::getRoutes() as $r){
 $uri=$r->uri();$name=(string)$r->getName();
 $routeStats['total']++;
 if(str_starts_with($uri,'erp/')||$uri==='erp'){$routeStats['erp']++;$erpRoutes[]=$uri;}
 if(str_starts_with($uri,'admin/')||$uri==='admin'){$routeStats['admin']++;$adminRoutes[]=$uri;}
 if(str_contains($uri,'legacy')||str_contains($name,'legacy')){$routeStats['legacy']++;$legacyRoutes[]=$uri;}
 if(str_starts_with($uri,'staff/'))$routeStats['staff']++;
 if(str_starts_with($uri,'api/'))$routeStats['api']++;
}
state('ROUTE_COUNTERS',json_encode($routeStats));
state('ADMIN_ROUTE_SAMPLE',implode(';',array_slice(array_unique($adminRoutes),0,30)));
state('ERP_ROUTE_SAMPLE',implode(';',array_slice(array_unique($erpRoutes),0,16)));
state('LEGACY_ROUTE_SAMPLE',implode(';',array_slice(array_unique($legacyRoutes),0,25)));
$matches=scanFiles($root,['app','routes','resources/views','resources/js','public/js']);
foreach($matches as $path=>$markers)state('COUPLED_FILE',str_replace(['=',"\n"],['_','_'],$path).'|'.implode(',',$markers));
state('COUPLED_FILES_COUNT',count($matches));
$important=[
 'customers','vehicles','vehicle_models','vehicle_makes','vehicle_variants',
 'workshop_customers','workshop_vehicles','workshop_vendors','vendors',
 'job_cards','job_card_parts','job_card_labour','inspections','vehicle_inspections',
 'estimates','estimate_parts','estimate_labour','workshop_purchases','workshop_purchase_batches',
 'workshop_payment_vouchers','workshop_vendor_payments','parts','parts_master',
 'staff','staff_users','staff_attendance','invoices','customer_ledger',
 'workshop_stock_movements','stock_movements','expenses','workshop_expenses',
 'receipts','money_receipts','payments'
];
try{
 $database=DB::connection()->getDatabaseName();
 state('STAGING_DATABASE_CONNECTION_READY','YES');
 state('STAGING_DATABASE_NAME_HASH',substr(hash('sha256',(string)$database),0,10));
 $actual=Schema::getTableListing();
 $tables=[];
 foreach($actual as $table){$table=(string)$table;$tables[]=preg_replace('~^.*\\.~','',$table);}
 $tables=array_values(array_unique($tables));sort($tables);
 state('STAGING_TABLE_COUNT',count($tables));
 foreach($important as $table){
   if(!in_array($table,$tables,true))continue;
   $columns=Schema::getColumnListing($table);
   $count=DB::table($table)->count();
   state('TABLE',json_encode(['name'=>$table,'rows'=>$count,'columns'=>count($columns),
     'primaryId'=>in_array('id',$columns,true),'createdAt'=>in_array('created_at',$columns,true)]));
 }
 state('ALL_STAGING_TABLE_NAMES',implode(';',$tables));
} catch(Throwable $e){state('SCHEMA_AUDIT_ERROR',get_class($e));}
state('DONE','P51_READONLY_INVENTORY_FINISHED_NO_DATA_MODIFICATIONS');
