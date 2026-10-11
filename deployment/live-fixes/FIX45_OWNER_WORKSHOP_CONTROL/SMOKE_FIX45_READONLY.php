<?php

declare(strict_types=1);

$root=rtrim((string)($argv[1]??''),'/');
if($root==='' || !is_file($root.'/artisan')){fwrite(STDERR,"Invalid Laravel root\n");exit(2);}
chdir($root);
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\Api\V1\OwnerWorkshopController;
use App\Models\ErpAuthUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

$admin=ErpAuthUser::query()->where('active',1)->get()->first(fn($u)=>$u->isAdmin());
if(!$admin){fwrite(STDERR,"No active Owner/Admin account for read-only smoke\n");exit(10);}

function req(ErpAuthUser $admin,array $query=[]): Request {
    $r=Request::create('/','GET',$query);
    $r->setUserResolver(fn()=>$admin);
    return $r;
}
function assertJson($response,string $name,array $keys=[]): array {
    if($response->getStatusCode()!==200){
        fwrite(STDERR,"$name HTTP ".$response->getStatusCode()."\n");exit(20);
    }
    $data=json_decode($response->getContent(),true);
    if(!is_array($data)){fwrite(STDERR,"$name invalid JSON\n");exit(21);}
    foreach($keys as $k){
        if(!array_key_exists($k,$data)){fwrite(STDERR,"$name missing key $k\n");exit(22);}
    }
    echo "$name: PASS\n";
    return $data;
}

$c=app(OwnerWorkshopController::class);
assertJson($c->dashboard(req($admin)),'dashboard',['ok','job_cards','staff','rot','alerts']);
assertJson($c->floor(req($admin)),'workshop-floor',['ok','floor']);
assertJson($c->alerts(req($admin)),'alerts',['ok','alerts']);
assertJson($c->auditIndex(req($admin)),'audit',['ok','audit']);

$jobId=(int)(DB::table('job_cards')->orderByDesc('id')->value('id')??0);
if($jobId>0){
    assertJson($c->jobControl(req($admin),$jobId),'job-control',['ok','job','lifecycle','parts','rot_sessions','osl','qc_checks']);
    assertJson($c->history(req($admin),$jobId),'service-history',['ok','job_history']);
}

$staffId=(int)(DB::table('staff')->whereRaw("LOWER(COALESCE(status,'active'))='active'")->orderBy('id')->value('id')??0);
if($staffId>0){
    assertJson($c->staffAttendance(req($admin,['month'=>now('Asia/Kolkata')->format('Y-m')]),$staffId),'staff-attendance',['ok','attendance']);
}

echo "FIX45 READ-ONLY RUNTIME SMOKE: PASS\n";
