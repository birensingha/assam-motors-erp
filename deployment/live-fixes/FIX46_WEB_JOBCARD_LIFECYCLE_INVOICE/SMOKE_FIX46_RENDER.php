<?php

declare(strict_types=1);

$root=rtrim((string)($argv[1]??''),'/');
if($root==='' || !is_file($root.'/artisan')){fwrite(STDERR,"Invalid Laravel root\n");exit(2);}
chdir($root);
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\Web\AdminJobCardLifecyclePageController;
use App\Http\Controllers\Web\AdminJobCardPageController;
use App\Models\ErpAuthUser;
use App\Services\JobCardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

$admin=ErpAuthUser::query()->where('active',1)->get()->first(fn($u)=>$u->isAdmin());
if(!$admin){fwrite(STDERR,"No active Admin\n");exit(10);}

function getReq(ErpAuthUser $admin): Request {
    $r=Request::create('/','GET');
    $r->setUserResolver(fn()=>$admin);
    return $r;
}
function requireText(string $html,string $needle,string $name): void {
    if(!str_contains($html,$needle)){
        fwrite(STDERR,"$name missing [$needle]\n");
        exit(20);
    }
    echo "$name: PASS\n";
}

$jobId=(int)(DB::table('job_cards')->orderByDesc('id')->value('id')??0);
if($jobId<=0){fwrite(STDERR,"No Job Card\n");exit(11);}

$jobController=app(AdminJobCardPageController::class);
$jobView=$jobController->show(getReq($admin),$jobId,app(JobCardService::class));
$jobHtml=$jobView->render();
requireText($jobHtml,'Workshop Lifecycle &amp; Billing','job-lifecycle-panel-render');
requireText($jobHtml,'OPEN → WIP → WORK_COMPLETE → QC_PENDING → QC_PASSED → READY_FOR_DELIVERY → CLOSED → INVOICED','job-lifecycle-stepper-render');

$invoiceId=(int)(DB::table('invoices')->whereNotNull('job_card_id')->orderByDesc('id')->value('id')??0);
if($invoiceId>0){
    $life=app(AdminJobCardLifecyclePageController::class);
    $invoiceView=$life->showInvoice(getReq($admin),$invoiceId);
    $invoiceHtml=$invoiceView->render();
    requireText($invoiceHtml,'Invoice Totals','invoice-view-render');
    requireText($invoiceHtml,'Customer Ledger Entries for this Invoice','invoice-ledger-render');

    $printView=$life->printInvoice(getReq($admin),$invoiceId);
    $printHtml=$printView->render();
    requireText($printHtml,'Workshop Tax Invoice','invoice-print-render');
    requireText($printHtml,'Print / Save PDF','invoice-print-pdf-control-render');
}else{
    echo "invoice-render: SKIP (no existing invoice)\n";
}

echo "FIX46 BLADE RENDER SMOKE: PASS\n";
