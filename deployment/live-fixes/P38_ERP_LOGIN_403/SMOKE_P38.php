<?php
declare(strict_types=1);
/** Read-only role-switch controller and middleware test; never submits passwords. */
$root=rtrim((string)($argv[1]??''),'/');
if(!is_file("$root/artisan"))exit(2);
chdir($root);require 'vendor/autoload.php';$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\ErpAuthUser;
use App\Http\Controllers\Web\WebAuthController;
use App\Http\Middleware\EnsureErpAdmin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;
$admin=ErpAuthUser::query()->where('active',1)->get()->first(fn($u)=>$u->isAdmin());
$staff=ErpAuthUser::query()->where('active',1)->get()->first(fn($u)=>!$u->isAdmin());
if(!$admin||!$staff){echo "P38_NO_GO_ROLE_TEST_ACCOUNTS\n";exit(3);}
$middleware=app(EnsureErpAdmin::class);
$controller=app(WebAuthController::class);
$session=app('session')->driver();$session->start();
View::share('errors',new \Illuminate\Support\ViewErrorBag());
function req(string $url,string $method, ErpAuthUser $actor, $session):Request{
 $r=Request::create($url,$method);
 $r->setLaravelSession($session);
 $r->setUserResolver(static fn()=>$actor);
 return $r;
}
Auth::guard('web')->setUser($staff);
$r=req('/erp/login','GET',$staff,$session);
$page=$controller->loginForm($r);
if(!($page instanceof \Illuminate\View\View)){echo "P38_FAIL_STAFF_ERP_LOGIN_FORM\n";exit(4);}
$html=$page->render();
if(!str_contains($html,'password')){echo "P38_FAIL_LOGIN_FORM_FIELDS\n";exit(4);}
echo "P38_STAFF_SESSION_CAN_OPEN_ERP_LOGIN_PASS\n";
$r=req('/erp/dashboard','GET',$staff,$session);
$resp=$middleware->handle($r,static fn($rq)=>new Response('PROTECTED_PAGE'));
if(!($resp instanceof \Illuminate\Http\RedirectResponse) ||
 !str_contains($resp->getTargetUrl(),'/erp/login')){
 echo "P38_FAIL_STAFF_BROWSER_REDIRECT\n";exit(5);
}
echo "P38_STAFF_GET_REDIRECTS_TO_ADMIN_LOGIN_PASS\n";
try{
 $middleware->handle(req('/erp/job-cards/30','POST',$staff,$session),static fn($rq)=>new Response('WRONG'));
 echo "P38_FAIL_STAFF_POST_NOT_DENIED\n";exit(6);
}catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){
 if($e->getStatusCode()!==403){echo "P38_FAIL_POST_STATUS\n";exit(6);}
}
echo "P38_STAFF_POST_STILL_403_PASS\n";
Auth::guard('web')->setUser($admin);
$dashboard=$middleware->handle(req('/erp/dashboard','GET',$admin,$session),static fn($rq)=>new Response('ADMIN_OK'));
if($dashboard->getContent()!=='ADMIN_OK'){echo "P38_FAIL_ADMIN_DASHBOARD\n";exit(7);}
echo "P38_VALID_ADMIN_SESSION_DASHBOARD_PASS\n";
$adminLogin=$controller->loginForm(req('/erp/login','GET',$admin,$session));
if(!($adminLogin instanceof \Illuminate\Http\RedirectResponse)){echo "P38_FAIL_ADMIN_LOGIN_REDIRECT\n";exit(8);}
echo "P38_VALID_ADMIN_LOGIN_REDIRECT_PASS\nP38_NO_BUSINESS_DATA_WRITES\n";
