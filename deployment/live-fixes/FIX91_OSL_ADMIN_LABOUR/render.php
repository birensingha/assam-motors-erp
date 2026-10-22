<?php
/**
 * FIX91 read-only Parts inline UI full Controller::show() render smoke test.
 * READ ONLY: executes actual JC show controller with an existing active
 * Admin account through a synthetic Laravel GET request. No POST, DB writes
 * or session save. This exercises the full Blade (not merely view:cache).
 */
declare(strict_types=1);
$root=$argv[1]??'';
if(!is_file($root.'/artisan'))exit(2);
chdir($root);
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;
$admin=\App\Models\ErpAuthUser::query()->where('active',true)->get()
    ->first(static fn($u)=>strtolower(trim((string)$u->role))==='admin');
if(!$admin){fwrite(STDERR,"FIX91_NO_ACTIVE_ADMIN_FOR_RENDER_SMOKE\n");exit(3);}
Auth::guard()->setUser($admin);
View::share('errors',new ViewErrorBag());
foreach([37,34] as $id){
    $req=Request::create("https://staging.assammotors.com/erp/job-cards/$id",'GET');
    $req->setUserResolver(static fn()=>$admin);
    $session=$app->make('session')->driver();
    $req->setLaravelSession($session);
    $app->instance('request',$req);
    try{
        $page=$app->make(\App\Http\Controllers\Web\AdminJobCardPageController::class)
             ->show($req,$id,$app->make(\App\Services\JobCardService::class));
        $html=$page->render();
        foreach(['Labour Item Selection','OSL Jobs / Outside Services','am83-workspace'] as $needle){
            if(!str_contains($html,$needle))
                throw new RuntimeException("Expected visible content missing: ".$needle);
        }
        if($id===37){
          foreach(['id="am85-part-form"','id="am85-part-search"','id="am85-discount"','class="am85-part-add-row"','name="part_master_id"'] as $needle){
            if(!str_contains($html,$needle))throw new RuntimeException('Missing JC37 inline Parts control: '.$needle);
          }
        }
        if($id===37 && (str_contains($html,'<datalist id="am85-part-options"') || !str_contains($html,'<select id="am85-part-search"')))
            throw new RuntimeException('Parts selector is not the intended native dropdown');
        if(str_contains($html,'Search or add Parts directly. Qty / Rate / Discount / GST / Delete editable until Job Card Close.'))
            throw new RuntimeException('Verbose Parts header should be removed');
        if($id===37){
          foreach(['id="FIX88_COMPACT_EDITABLE_AMOUNTS"','id="am85-discount-rs" class="am88-amount-input"',
                   'id="am85-taxable" class="am88-amount-input"',
                   'id="am85-gst-rs" class="am88-amount-input"',
                   'id="am85-grand" class="am88-amount-input"'] as $needle){
            if(!str_contains($html,$needle))throw new RuntimeException('Editable Parts amount missing: '.$needle);
          }
        }
        if($id===37){
          foreach(['id="FIX89_ARROW_TYPE_PARTS_DROPDOWN"',
                   'am89-query','am89-arrow','am89-reset',
                   '<select id="am85-part-search"'] as $needle){
            if(!str_contains($html,$needle))throw new RuntimeException('Searchable Parts combo missing: '.$needle);
          }
        }
        foreach(['id="FIX90_LABOUR_EXCEL_BILLING_GRID"','id="am90-labour-billing"',
                 'id="am90-table-body"','data-am90-labour-id',
                 'LABOUR</th>','ROT</th>','SAC</th>','STD HRS</th>',
                 'DIS AMT</th>','TAXABLE</th>','GST AMT</th>','FINAL AMT</th>','EDIT</th>',
                 'Mechanic &amp; ROT Work Controls'] as $needle){
            if(!str_contains($html,$needle))
                throw new RuntimeException('FIX91 missing Labour grid or work controls: '.$needle);
        }
        foreach(['id="FIX91_UNIFIED_OSL_ADMIN"', 'id="am90-table-body"', 'data-am91-osl-edit',
                 'am91-add-actions', 'id="am91-add-osl"',
                 'erp/job-cards/' /* route path presence */] as $needle){
            if(!str_contains($html,$needle))
                throw new RuntimeException('FIX91 unified OSL grid or Admin control missing: '.$needle);
        }
        if(str_contains($html,'Direct Job Card Entry') || str_contains($html,'+ Add New Part'))
            throw new RuntimeException('Duplicate direct entry panel found');
        echo 'FIX91_JC_RENDER_PASS id='.$id.' chars='.strlen($html)."\n";
    }catch(Throwable $ex){
        // Do not output customer information from the rendered HTML.
        fwrite(STDERR,'FIX91_RENDER_FAILED id='.$id.' exception='.get_class($ex).
                      ' message='.substr($ex->getMessage(),0,250)."\n");
        exit(4);
    }
}
echo "FIX91_JC37_JC34_PARTS_ROW_HTML_RENDER_PASS\n";
