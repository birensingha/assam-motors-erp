<?php
// Staging verification: no persistent business-record changes.
// Invoke real controller validation and update inside a rollback-only transaction.
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
if (!str_ends_with(basename(getcwd()),'staging')) throw new RuntimeException('Staging only');
$cls=\App\Http\Controllers\Web\AdminJobCardPageController::class;
$controller=app($cls);
$ref=new ReflectionClass($controller);
$source=static function($name)use($ref){
 $m=$ref->getMethod($name);
 return implode('',array_slice(file($m->getFileName()),$m->getStartLine()-1,$m->getEndLine()-$m->getStartLine()+1));
};
$update=$source('update');
$audit=$source('auditChange');
$admin=$source('admin');
if (!str_contains($update,'AM_JC_ODOMETER_EDIT_V1') || !str_contains($update,'$this->auditChange(')) throw new RuntimeException('Installed patch missing');
if (!str_contains(file_get_contents('resources/views/job-cards/edit.blade.php'),'name="odometer_km"')) throw new RuntimeException('Input missing');
// Explicitly guard against external side effects and nontransactional tables.
if (preg_match('/\b(dispatch|event|notify|mail|curl|exec|shell_exec|file_put_contents|commit|statement|unprepared)\s*\(/i',$audit.$update)) throw new RuntimeException('Unexpected side-effect path; manual review required');
preg_match_all("/DB::table\\(\\s*['\"]([a-zA-Z0-9_]+)['\"]\\s*\\)/",$audit,$matches);
$auditTables=array_values(array_unique($matches[1]));
if (!$auditTables) throw new RuntimeException('Audit table not statically identifiable');
$tables=DB::select("SELECT TABLE_NAME AS n, ENGINE AS e FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE'");
foreach($tables as $t) if(strtoupper((string)$t->e)!=='INNODB') throw new RuntimeException('Nontransactional database table present; refusing write test');
if(DB::select("SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND EVENT_OBJECT_TABLE IN ('job_cards','".implode("','",$auditTables)."')")) throw new RuntimeException('Triggers on tested tables; refusing write test');
$type=$ref->getMethod('admin')->getReturnType();
if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) throw new RuntimeException('Cannot identify admin model');
$userClass=$type->getName();
$user=null;
foreach($userClass::query()->limit(100)->get() as $candidate) {
 $req=Request::create('/','PUT');
 $req->setUserResolver(fn()=>$candidate);
 try{$m=$ref->getMethod('admin');$m->setAccessible(true);$m->invoke($controller,$req);$user=$candidate;break;}catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){}
}
if(!$user)throw new RuntimeException('No authorized test actor');
$card=DB::table('job_cards')->whereNotIn(DB::raw('LOWER(status)'),['closed','invoiced','cancelled'])->whereNotNull('customer_name')->whereNotNull('vehicle_reg_no')->orderByDesc('id')->first();
if(!$card)throw new RuntimeException('No eligible staging record');
$before=(array)$card;
$original=$card->odometer_km;
$test=((string)$original==='123456')?123457:123456;
$payload=['customer_name'=>$card->customer_name,'customer_phone'=>$card->customer_phone??null,'vehicle_reg_no'=>$card->vehicle_reg_no,'vehicle_make'=>$card->vehicle_make??null,'vehicle_model'=>$card->vehicle_model??null,'vehicle_variant'=>$card->vehicle_variant??null,'complaint'=>$card->customer_complaint??$card->complaint??$card->reported_complaint??$card->job_complaint??null,'floor_supervisor_id'=>$card->floor_supervisor_id??null,'reason'=>'Automated rollback-only odometer verification','odometer_km'=>$test];
$invoke=static function($data)use($controller,$user,$card){
 $request=Request::create('/','PUT',$data);
 $request->setUserResolver(fn()=>$user);
 return $controller->update($request,(int)$card->id);
};
$counts=[];
DB::beginTransaction();
try{
 foreach($auditTables as $table)$counts[$table]=DB::table($table)->count();
 try{$invoke(array_replace($payload,['odometer_km'=>-1]));throw new RuntimeException('Negative value accepted');}catch(ValidationException $e){if(!isset($e->errors()['odometer_km']))throw $e;}
 echo "ODOMETER_NEGATIVE_REJECTED\n";
 $invoke($payload);
 if((string)DB::table('job_cards')->where('id',$card->id)->value('odometer_km')!==(string)$test)throw new RuntimeException('Save verification failed');
 echo "ODOMETER_REAL_CONTROLLER_SAVE_PASS\n";
 $auditFound=false;
 foreach($auditTables as $table){
  if(DB::table($table)->count()>$counts[$table])$auditFound=true;
 }
 if(!$auditFound)throw new RuntimeException('No audit insert observed');
 echo "ODOMETER_AUDIT_INSERT_PASS\n";
 $invoke(array_replace($payload,['odometer_km'=>null]));
 if((string)DB::table('job_cards')->where('id',$card->id)->value('odometer_km')!==(string)$test)throw new RuntimeException('Blank overwrote value');
 echo "ODOMETER_BLANK_PRESERVES_PASS\n";
}finally{
 while(DB::transactionLevel()>0)DB::rollBack();
}
if((array)DB::table('job_cards')->where('id',$card->id)->first()!==$before)throw new RuntimeException('Rollback verification mismatch');
echo "ODOMETER_ROLLBACK_RECORD_VERIFIED\n";
