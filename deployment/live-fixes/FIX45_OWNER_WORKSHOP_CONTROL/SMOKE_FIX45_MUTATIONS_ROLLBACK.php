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
if(!$admin){fwrite(STDERR,"No active Owner/Admin account\n");exit(10);}
$staff=DB::table('staff')->whereRaw("LOWER(COALESCE(status,'active'))='active'")->orderBy('id')->first();
if(!$staff){fwrite(STDERR,"No active Staff row\n");exit(11);}

function req(ErpAuthUser $admin,string $method,array $body=[]): Request {
    $r=Request::create('/',$method,$body);
    $r->setUserResolver(fn()=>$admin);
    return $r;
}
function dataOf($response,string $name,array $keys=[]): array {
    $code=$response->getStatusCode();
    if($code<200 || $code>=300){
        fwrite(STDERR,"$name HTTP $code: ".$response->getContent()."\n");exit(20);
    }
    $data=json_decode($response->getContent(),true);
    if(!is_array($data)){fwrite(STDERR,"$name invalid JSON\n");exit(21);}
    foreach($keys as $k) if(!array_key_exists($k,$data)){fwrite(STDERR,"$name missing $k\n");exit(22);}
    echo "$name: PASS\n";
    return $data;
}

$c=app(OwnerWorkshopController::class);
DB::beginTransaction();
try {
    $created=dataOf($c->createJob(req($admin,'POST',[
        'job_date'=>now('Asia/Kolkata')->toDateString(),
        'priority'=>'NORMAL',
        'customer_name'=>'FIX45 ROLLBACK TEST',
        'customer_phone'=>'0000000000',
        'vehicle_reg_no'=>'TEST-ROLLBACK',
        'vehicle_make'=>'TEST',
        'vehicle_model'=>'TEST',
        'odometer_km'=>'1',
        'service_type'=>'TEST',
        'technician_id'=>(int)$staff->id,
        'customer_complaint'=>'Rollback-only controller smoke'
    ])),'create-job',['job']);
    $jobId=(int)$created['job']['id'];
    if($jobId<=0) throw new RuntimeException('No created Job Card ID');

    dataOf($c->editJob(req($admin,'PUT',[
        'customer_name'=>'FIX45 ROLLBACK TEST EDIT',
        'customer_phone'=>'0000000000',
        'vehicle_reg_no'=>'TEST-ROLLBACK',
        'vehicle_make'=>'TEST',
        'vehicle_model'=>'TEST2',
        'vehicle_variant'=>'SMOKE',
        'customer_complaint'=>'Rollback-only edit',
        'service_type'=>'TEST',
        'odometer_km'=>'2',
        'reason'=>'Rollback-only mutation smoke'
    ]),$jobId),'edit-job',['job']);

    dataOf($c->priority(req($admin,'POST',[
        'priority'=>'HIGH','reason'=>'Rollback-only priority test'
    ]),$jobId),'priority',['job']);

    dataOf($c->assignTechnician(req($admin,'POST',[
        'staff_id'=>(int)$staff->id,'reason'=>'Rollback-only assignment test'
    ]),$jobId),'assign-technician',['job']);

    dataOf($c->assignInspection(req($admin,'POST',[
        'staff_id'=>(int)$staff->id,'reason'=>'Rollback-only inspection assign'
    ]),$jobId),'inspection-assign',['job']);

    dataOf($c->diagnosis(req($admin,'POST',[
        'action'=>'COMPLETE',
        'final_diagnosis'=>'Rollback-only diagnosis',
        'decision'=>'REPAIR',
        'note'=>'No persistent data',
        'reason'=>'Rollback-only diagnosis test'
    ]),$jobId),'diagnosis-complete',['job']);

    $partReq=dataOf($c->requestPart(req($admin,'POST',[
        'part_name'=>'ROLLBACK TEST PART',
        'part_code'=>'ROLLBACK',
        'qty'=>1,
        'note'=>'Rollback-only parts request'
    ]),$jobId),'parts-request',['request']);
    dataOf($c->partsRequestStatus(req($admin,'POST',[
        'status'=>'APPROVED','reason'=>'Rollback-only parts approval'
    ]),(int)$partReq['request']['id']),'parts-request-approve',['request']);

    $oslReq=dataOf($c->requestOsl(req($admin,'POST',[
        'description'=>'ROLLBACK TEST OSL',
        'vendor_name'=>'ROLLBACK VENDOR',
        'estimated_cost'=>1,
        'notes'=>'Rollback-only OSL request'
    ]),$jobId),'osl-request',['osl_request']);
    $oslId=(int)$oslReq['osl_request']['id'];
    dataOf($c->oslRequestStatus(req($admin,'POST',[
        'status'=>'APPROVED',
        'vendor_name'=>'ROLLBACK VENDOR',
        'estimated_cost'=>1,
        'reason'=>'Rollback-only OSL approval'
    ]),$oslId),'osl-approve',['osl_request']);
    dataOf($c->oslRequestStatus(req($admin,'POST',[
        'status'=>'IN_PROGRESS',
        'vendor_name'=>'ROLLBACK VENDOR',
        'estimated_cost'=>1,
        'reason'=>'Rollback-only vendor work start'
    ]),$oslId),'osl-in-progress',['osl_request']);
    dataOf($c->oslRequestStatus(req($admin,'POST',[
        'status'=>'COMPLETED',
        'vendor_name'=>'ROLLBACK VENDOR',
        'estimated_cost'=>1,
        'reason'=>'Rollback-only OSL completion'
    ]),$oslId),'osl-complete',['osl_request']);

    dataOf($c->qc(req($admin,'POST',[
        'result'=>'PASS',
        'assigned_staff_id'=>(int)$staff->id,
        'notes'=>'Rollback-only QC pass',
        'failed_items'=>[],
        'reason'=>'Rollback-only QC test'
    ]),$jobId),'qc-pass',['qc','job']);

    $road=dataOf($c->roadTest(req($admin,'POST',[
        'action'=>'START',
        'staff_id'=>(int)$staff->id,
        'driver_name'=>$staff->name ?? 'Staff',
        'odometer'=>2,
        'notes'=>'Rollback-only road test',
        'reason'=>'Rollback-only road test start'
    ]),$jobId),'road-start',['road_test']);
    dataOf($c->roadTest(req($admin,'POST',[
        'action'=>'COMPLETE',
        'road_test_id'=>(int)$road['road_test']['id'],
        'staff_id'=>(int)$staff->id,
        'driver_name'=>$staff->name ?? 'Staff',
        'odometer'=>3,
        'notes'=>'Rollback-only road complete',
        'reason'=>'Rollback-only road test complete'
    ]),$jobId),'road-complete',['road_test']);

    dataOf($c->keyCustody(req($admin,'POST',[
        'action'=>'HANDOVER',
        'staff_id'=>(int)$staff->id,
        'holder_name'=>$staff->name ?? 'Staff',
        'notes'=>'Rollback-only key handover',
        'reason'=>'Rollback-only key test'
    ]),$jobId),'key-custody',['key_custody']);

    dataOf($c->transition(req($admin,'POST',[
        'action'=>'WIP','reason'=>'Rollback-only WIP test','override'=>false
    ]),$jobId),'lifecycle-wip',['job','lifecycle']);
    dataOf($c->transition(req($admin,'POST',[
        'action'=>'WORK_COMPLETE','reason'=>'Rollback-only work complete','override'=>false
    ]),$jobId),'lifecycle-work-complete',['job','lifecycle']);
    dataOf($c->transition(req($admin,'POST',[
        'action'=>'READY_FOR_DELIVERY','reason'=>'Rollback-only ready delivery','override'=>false
    ]),$jobId),'lifecycle-ready',['job','lifecycle']);
    dataOf($c->transition(req($admin,'POST',[
        'action'=>'CLOSED','reason'=>'Rollback-only close','override'=>false
    ]),$jobId),'lifecycle-close',['job','lifecycle']);
    dataOf($c->transition(req($admin,'POST',[
        'action'=>'REOPEN','reason'=>'Rollback-only reopen','override'=>false
    ]),$jobId),'lifecycle-reopen',['job','lifecycle']);

    dataOf($c->rework(req($admin,'POST',[
        'reason'=>'Rollback-only rework test',
        'complaint'=>'Rollback repeat complaint'
    ]),$jobId),'rework',['rework','job']);

    $att=DB::table('attendance')->where('staff_id',$staff->id)->orderByDesc('id')->first();
    if($att){
        dataOf($c->correctAttendance(req($admin,'POST',[
            'check_in'=>$att->check_in,
            'check_out'=>$att->check_out,
            'status'=>$att->status,
            'notes'=>$att->notes,
            'reason'=>'Rollback-only attendance correction test'
        ]),(int)$att->id),'attendance-correction',['attendance']);
    } else {
        echo "attendance-correction: SKIP (no row)\n";
    }

    $stockCandidate=DB::table('job_card_parts as p')
        ->join('parts_master as m','m.id','=','p.part_master_id')
        ->whereNotNull('p.part_master_id')
        ->whereIn(DB::raw("UPPER(COALESCE(p.issue_status,'PLANNED'))"),['PLANNED','PENDING'])
        ->where('p.qty','>',0)
        ->whereColumn('m.stock_qty','>=','p.qty')
        ->select('p.id','p.job_card_id','p.qty','p.part_master_id','m.stock_qty')
        ->orderBy('p.id')
        ->first();

    if($stockCandidate){
        dataOf($c->partStatus(req($admin,'POST',[
            'status'=>'ISSUED',
            'reason'=>'Rollback-only stock issue test'
        ]),(int)$stockCandidate->job_card_id,(int)$stockCandidate->id),'part-stock-issue',['part']);
        dataOf($c->partStatus(req($admin,'POST',[
            'status'=>'RETURNED',
            'reason'=>'Rollback-only stock return test'
        ]),(int)$stockCandidate->job_card_id,(int)$stockCandidate->id),'part-stock-return',['part']);
    } else {
        echo "part-stock-issue-return: SKIP (no eligible planned Part with sufficient stock)\n";
    }

    echo "FIX45 MUTATION CONTROLLERS: PASS\n";
} finally {
    DB::rollBack();
}
echo "FIX45 ROLLBACK: PASS (no smoke data persisted)\n";
