<?php
/** FIX76 one-time owner-requested SAC classification of all current 205
 * vehicle workshop ROT services. This is MASTER-ONLY; does not touch any
 * job card labour, issued invoice, credit note or tax amount.
 */
declare(strict_types=1);
$root=$argv[1]??null;$backup=$argv[2]??null;
if(!$root||!$backup||!is_file($root.'/artisan'))exit(2);
chdir($root);
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
if(!Schema::hasColumn('labour_master','sac_code'))throw new RuntimeException('FIX76 missing labour_master.sac_code');
if(file_exists($backup))throw new RuntimeException('FIX76 refusing to overwrite master SAC backup');
$allowedCategories=[
'Periodic / General Service','Engine','Fuel System','Fuel / Emission',
'Cooling System','Clutch / Transmission','Transmission','Brakes',
'Suspension / Steering','Electrical','Diagnostic / Electrical',
'AC / HVAC','Exhaust / Emission','Body / Exterior',
'Diagnostic / Inspection','Denting / Body Work',
'LABOUR','PAINTING','General Service','AC & Heating','Accidental Repair'
];
DB::transaction(function()use($backup,$allowedCategories){
 $rows=DB::table('labour_master')->orderBy('id')->lockForUpdate()
   ->get(['id','rot_code','category','rot_description','sac_code']);
 if($rows->count()!==205)throw new RuntimeException('FIX76 master count changed; review before updating');
 $categories=$rows->map(fn($x)=>(string)$x->category)->unique()->values();
 foreach($categories as $cat){
  if(!in_array($cat,$allowedCategories,true))throw new RuntimeException('FIX76 new category requires classification review');
 }
 if($rows->contains(fn($r)=>trim((string)($r->sac_code??''))!==''))throw new RuntimeException('FIX76 pre-existing classified code found, refuse bulk overwrite');
 if($rows->contains(fn($r)=>preg_match('/technical\s+(automobile|vehicle)\s+inspection|compliance\s+inspection|regulatory\s+inspection/i',
     (string)$r->rot_description)))throw new RuntimeException('FIX76 separate regulatory inspection service found');
 $snapshot=$rows->map(fn($r)=>[
    'id'=>$r->id,'rot_code'=>$r->rot_code,'category'=>$r->category,'original_sac_code'=>$r->sac_code
  ])->all();
 if(file_put_contents($backup,json_encode($snapshot,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT),LOCK_EX)===false)
    throw new RuntimeException('FIX76 cannot preserve original master snapshot');
 chmod($backup,0600);
 $count=DB::table('labour_master')->whereNull('sac_code')->update(['sac_code'=>'998714']);
 if($count!==205)throw new RuntimeException('FIX76 classified row count differs from 205');
 $matched=DB::table('labour_master')->where('sac_code','998714')->count();
 if($matched!==205)throw new RuntimeException('FIX76 post update master count mismatch');
 echo "FIX76_ROT_MASTER_SAC_BACKFILL_PASS updated=205 sac=998714 history_invoices_unchanged=yes\n";
});
