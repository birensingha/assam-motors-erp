<?php
/**
 * FIX85 read-only Parts inline UI full Controller::show() render smoke test.
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
    ->first(static fn($u)=>(bool)$u->isAdmin());
if(!$admin){fwrite(STDERR,"FIX85_NO_ACTIVE_ADMIN_FOR_RENDER_SMOKE\n");exit(3);}
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
        foreach(['Labour Item Selection','OSL Jobs / Outside Services','am83-workspace','id="am85-part-form"','id="am85-part-search"','id="am85-discount"','id="am85-part-options"','class="am85-part-add-row"','name="part_master_id"'] as $needle){
            if(!str_contains($html,$needle))
                throw new RuntimeException("Expected visible content missing: ".$needle);
        }
        if(str_contains($html,'Direct Job Card Entry') || str_contains($html,'+ Add New Part'))
            throw new RuntimeException('Duplicate direct entry panel found');
        echo 'FIX85_JC_RENDER_PASS id='.$id.' chars='.strlen($html)."\n";
    }catch(Throwable $ex){
        // Do not output customer information from the rendered HTML.
        fwrite(STDERR,'FIX85_RENDER_FAILED id='.$id.' exception='.get_class($ex).
                      ' message='.substr($ex->getMessage(),0,250)."\n");
        exit(4);
    }
}
echo "FIX85_JC37_JC34_PARTS_ROW_HTML_RENDER_PASS\n";
