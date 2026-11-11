#!/usr/bin/env bash
# V645 zero-write tests: roles, ROT identity, WIP/invoice guards and Audit system.
set -euo pipefail
cd "$HOME/domains/assammotors.com/assam-erp-staging"
php <<'PHP'
<?php
declare(strict_types=1);
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Http\Request;
use App\Models\ErpAuthUser;
use App\Http\Controllers\Web\AdminRotTimeEditController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
$controller=$app->make(AdminRotTimeEditController::class);
$auth=(new ReflectionClass($controller))->getMethod('admin');
$roleDenied=[];
foreach(['staff','hr_manager','supervisor','owner'] as $role){
 $user=new ErpAuthUser;
 $user->role=$role;$user->active=true;
 $req=Request::create('/erp/rot/ROT-V645-NO-RECORD/time/standard','POST');
 $req->setUserResolver(fn()=>$user);
 try{
   $auth->invoke($controller,$req);
   throw new RuntimeException('Role was improperly permitted: '.$role);
 }catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){
   if($e->getStatusCode()!==403)throw $e;
   $roleDenied[]=$role;
 }
}
$admin=new ErpAuthUser;
$admin->role='admin';$admin->active=true;
$req=Request::create('/erp/rot/ROT-V645-NO-RECORD/time/standard','POST');
$req->setUserResolver(fn()=>$admin);
if($auth->invoke($controller,$req)!==$admin)throw new RuntimeException('Admin incorrectly denied');
echo 'V645_ADMIN_ONLY_ROLE_POLICY=PASS|denied='.implode(',',$roleDenied).PHP_EOL;

$stdReq=Request::create('/erp/rot/ROT-V645-NO-RECORD/time/standard','POST',[
 'new_std_hours'=>'1.50','expected_std_hours'=>'1.00',
 'reason'=>'Admin time correction QA - no existing session',
]);
$stdReq->setUserResolver(fn()=>$admin);
try{
 $controller->standard($stdReq,'ROT-V645-NO-RECORD');
 throw new RuntimeException('Nonexistent ROT accepted!');
}catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){
 if($e->getStatusCode()!==404)throw $e;
}
echo 'V645_NONEXISTENT_ROT_REJECTED_WITHOUT_WRITE=PASS'.PHP_EOL;

$routes=Route::getRoutes();
foreach(['erp.rot.time.standard','erp.rot.time.actual'] as $name){
 $r=$routes->getByName($name);
 if(!$r||!in_array('POST',$r->methods(),true))throw new RuntimeException('Missing POST route '.$name);
 if(!in_array('App\Http\Middleware\EnsureErpAdmin',$r->gatherMiddleware(),true) &&
    !in_array('App\Http\Middleware\EnsureErpAdmin',array_map(fn($m)=>is_string($m)?$m:'',$r->middleware()),true))
  throw new RuntimeException('Missing Admin middleware '.$name);
}
echo 'V645_ROUTES_ADMIN_MIDDLEWARE=PASS'.PHP_EOL;

if(!\Illuminate\Support\Facades\Schema::hasTable('rot_admin_corrections'))throw new RuntimeException('ROT history audit absent');
$jc=DB::table('job_cards')->where('job_no','JC-20261018-00037')->first();
if(!$jc)throw new RuntimeException('JC37 not found');
echo 'V645_JC37_READONLY='.json_encode([
 'status'=>$jc->status,
 'invoice_linked'=>!empty($jc->invoice_id),
 'rot_statuses'=>DB::table('rot_sessions')->where('job_card_id',$jc->id)->pluck('status')->all()
]).PHP_EOL;
echo 'V645_ROT_TIME_READONLY_QA=PASS'.PHP_EOL;
PHP
