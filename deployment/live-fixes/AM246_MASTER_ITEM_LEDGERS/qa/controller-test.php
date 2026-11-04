<?php
/** AM246 read-only controller integration against actual staging DB, no mutations. */
declare(strict_types=1);
use App\Http\Controllers\Web\AdminMasterItemLedgerController;
use App\Models\ErpAuthUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
$root=realpath($argv[1]??'');
if(!$root || basename($root)!=='assam-erp-staging' || !is_file($root.'/artisan'))
    throw new RuntimeException('AM246_STOP_INVALID_STAGING_ROOT');
require $root.'/vendor/autoload.php';
$app=require $root.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$admin=ErpAuthUser::query()->where('active',1)->get()->first(static fn($u)=>$u->isAdmin());
if(!$admin)throw new RuntimeException('AM246_NO_ADMIN_FOR_QA');
$controller=app(AdminMasterItemLedgerController::class);
$cases=[
 'PART'=>[
  'table'=>'parts_master',
  'id'=>DB::table('inventory_movements')->orderByDesc('id')->value('part_master_id')
      ?:DB::table('parts_master')->orderByDesc('id')->value('id')
 ],
 'LABOUR'=>[
  'table'=>'labour_master',
  'id'=>DB::table('job_card_labour')->where('rot_master_id','>',0)->orderByDesc('id')->value('rot_master_id')
      ?:DB::table('labour_master')->orderByDesc('id')->value('id')
 ],
 'OSL'=>[
  'table'=>'workshop_osl_master',
  'id'=>DB::table('workshop_purchases')->where('purchase_type','OSL')
       ->whereNotNull('osl_master_id')->orderByDesc('id')->value('osl_master_id')
      ?:DB::table('workshop_osl_master')->orderByDesc('id')->value('id')
 ],
];
foreach($cases as $type=>$entry){
 $id=(int)$entry['id'];
 if(!$id || !DB::table($entry['table'])->where('id',$id)->exists()){
  echo "AM246_".$type."_NO_TESTABLE_MASTER\n";
  continue;
 }
 $q=Request::create('/erp/masters/item-ledger','GET',['type'=>$type,'id'=>$id]);
 $q->setUserResolver(static fn()=>$admin);
 $view=$controller->index($q);
 $data=$view->getData();
 if($view->getName()!=='admin.native.item-ledger'||!$data['master'])
    throw new RuntimeException('AM246_VIEW_NOT_RENDERED_'.$type);
 if((int)$data['master']->id!==$id)throw new RuntimeException('AM246_WRONG_MASTER_'.$type);
 if($type==='PART' && !is_numeric($data['report']['opening']))
    throw new RuntimeException('AM246_MISSING_STOCK_OPENING');
 if($type!=='PART' && $data['report']['opening']!==null)
    throw new RuntimeException('AM246_SERVICE_INVENTORY_WRONG');
 foreach($data['report']['events'] as $event){
  if(!isset($event['reference'],$event['type'],$event['date']))
    throw new RuntimeException('AM246_EVENT_MISSING_FIELDS_'.$type);
 }
 echo 'AM246_'.$type.'_LEDGER_QA_PASS EVENTS='.count($data['report']['events'])."\n";
 $query=Request::create('/erp/masters/item-ledger/search','GET',['type'=>$type,'q'=>'']);
 $query->setUserResolver(static fn()=>$admin);
 $response=$controller->suggest($query);
 $json=json_decode($response->getContent(),true);
 if(!isset($json['items'])||!is_array($json['items']))
  throw new RuntimeException('AM246_SEARCH_RESPONSE_INVALID_'.$type);
 echo 'AM246_'.$type.'_SEARCH_QA_PASS\n';
}
$guest=Request::create('/erp/masters/item-ledger','GET',['type'=>'PART']);
$guest->setUserResolver(static fn()=>null);
try{$controller->index($guest);throw new RuntimeException('AM246_AUTHZ_GUEST_WAS_ALLOWED');}
catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){
 if($e->getStatusCode()!==403)throw $e;
 echo "AM246_AUTHORIZATION_403_PASS\n";
}
echo "AM246_CONTROLLER_READONLY_TEST_FINISHED_NO_MUTATIONS\n";
