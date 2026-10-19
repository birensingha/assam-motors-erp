<?php
namespace App\Http\Controllers\Web;
use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use App\Services\PaidInvoiceAdvanceService;

final class AdminPaidInvoiceAdvancePageController extends Controller {
    public function post(Request $request,int $job,int $invoice): RedirectResponse {
        $actor=$request->user();
        abort_unless($actor instanceof ErpAuthUser && $actor->active && $actor->isAdmin(),403);
        $data=$request->validate([
          'reason'=>['required','string','min:10','max:1000'],
          'credit_date'=>['required','date','before_or_equal:today'],
          'confirmed_finance'=>['required','accepted'],
          'confirmed_advance'=>['required','accepted'],
        ]);
        $result=PaidInvoiceAdvanceService::transfer(
          $job,$invoice,$data['reason'],$data['credit_date'],
          (int)$actor->id,(string)($actor->name??'Admin')
        );
        return redirect()->route('erp.job-cards.credit-correction',$job)
          ->with('success','Credit Note '.$result['credit_note'].' posted; receipt '.
            $result['receipt'].' preserved and Rs.'.
            number_format($result['advance_amount'],2).' placed on customer advance. '.
            'Reopen CLOSED Job Card to WIP with a correction reason.');
    }
}
