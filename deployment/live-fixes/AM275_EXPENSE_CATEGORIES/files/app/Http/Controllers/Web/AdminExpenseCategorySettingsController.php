<?php
declare(strict_types=1);
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\ExpenseCategoryMasterService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** AM275 Expense Categories — Settings; protected by existing ERP Admin auth. */
final class AdminExpenseCategorySettingsController extends Controller
{
    public function index(Request $request, ExpenseCategoryMasterService $categories):View
    {
        return view('expenses.categories',$categories->listing($request));
    }

    public function create(Request $request, ExpenseCategoryMasterService $categories):RedirectResponse
    {
        $code=$categories->add($request);
        return redirect()->route('erp.expense-categories.index')
            ->with('success','Expense Category '.$code.' created. Available in New Expense Voucher.');
    }

    public function update(Request $request,int $category,ExpenseCategoryMasterService $categories):RedirectResponse
    {
        $code=$categories->edit($request,$category);
        return redirect()->route('erp.expense-categories.index')
            ->with('success','Expense Category '.$code.' updated with audit. Existing vouchers are unchanged.');
    }
}
