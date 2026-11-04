<?php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use App\Services\StaffMultiExpensePaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** AM245_STAFF_MULTI_EXPENSE — payment form and POST use one audited Staff Payment Voucher. */
final class AdminStaffMultiExpensePaymentController extends Controller
{
    public function create(Request $request, StaffMultiExpensePaymentService $service): View
    {
        $user=$request->user();
        abort_unless($user instanceof ErpAuthUser && $user->active && $user->isAdmin(),403);
        $staff=DB::table('staff')
          ->whereRaw("LOWER(COALESCE(status,'active')) = 'active'")
          ->select(['id','staff_code','name'])
          ->orderBy('name')->get();
        $staffId=(int)$request->query('staff_id',0);
        $staffIds=$staff->pluck('id')->map(fn($x)=>(int)$x)->all();
        if($staffId && !in_array($staffId,$staffIds,true))$staffId=0;
        $rows=$service->outstandingRows();
        return view('payments.staff-expenses-multi',compact('staff','staffId','rows'));
    }

    public function store(Request $request, StaffMultiExpensePaymentService $service): RedirectResponse
    {
        return $service->post($request);
    }
}
