<?php
namespace App\Http\Controllers\Web;
use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
final class AdminJobCardBillingCorrectionPageController extends Controller {
    public function index(Request $request,int $job): View {
        $actor=$request->user();
        abort_unless($actor instanceof ErpAuthUser && $actor->active && $actor->isAdmin(),403);
        $card=DB::table('job_cards')->where('id',$job)->first();
        abort_unless($card,404);
        $invoices=DB::table('invoices')->where('job_card_id',$job)->orderByDesc('id')->get();
        $notes=DB::table('invoice_credit_notes')->where('job_card_id',$job)->orderByDesc('id')->get();
        $sessions=DB::table('rot_sessions')->where('job_card_id',$job)->orderByDesc('created_at')->get();
        return view('job-cards.credit-correction',compact('card','invoices','notes','sessions'));
    }
}
