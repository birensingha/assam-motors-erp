<?php
namespace App\Http\Controllers\Admin;

use App\Contracts\AmEngagementLedger;
use App\Contracts\AmFeedbackCustomerSource;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AmCustomerEngagementController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('am_customer_feedback')
            ->leftJoin('am_customer_complaints as fc','fc.feedback_id','=','am_customer_feedback.id')
            ->select('am_customer_feedback.*','fc.status as complaint_status')
            ->orderByDesc('am_customer_feedback.id');
        $search = trim((string)$request->query('q',''));
        if ($search !== '') $query->where(function($q) use ($search) {
            $q->where('customer_name','like','%'.$search.'%')
              ->orWhere('vehicle','like','%'.$search.'%')
              ->orWhere('job_card_ref','like','%'.$search.'%');
        });
        $rating=(string)$request->query('rating','');
        if(in_array($rating,['1','2','3','4','5'],true)) {
            $query->where('am_customer_feedback.rating',(int)$rating);
        }
        $complaintStatus=(string)$request->query('complaint_status','');
        if(in_array($complaintStatus,['open','resolved'],true)) {
            $query->where('fc.status',$complaintStatus);
        } elseif($complaintStatus==='none') {
            $query->whereNull('fc.id');
        }

        $summary=DB::table('am_customer_feedback')
            ->selectRaw('COUNT(*) as total, AVG(rating) as average')->first();
        return view('admin.engagement.index', [
            'feedback' => $query->paginate(20)->withQueryString(),
            'complaints' => DB::table('am_customer_complaints')->orderByDesc('id')->limit(30)->get(),
            'complaintHistory' => DB::table('am_customer_complaint_history as h')
                ->join('am_customer_complaints as c','c.id','=','h.complaint_id')
                ->select('h.*','c.feedback_id')
                ->orderByDesc('h.id')->limit(30)->get(),
            'feedbackCount' => (int)($summary->total ?? 0),
            'averageRating' => $summary && $summary->average !== null
                ? round((float)$summary->average,2) : null,
            'openComplaintCount' => DB::table('am_customer_complaints')->where('status','open')->count(),
        ]);
    }

    public function messaging(Request $request)
    {
        return view('admin.engagement.messaging', [
            'messages' => DB::table('am_customer_message_log')->orderByDesc('id')->limit(50)->get(),
        ]);
    }

    public function customers(Request $request, AmEngagementLedger $ledger)
    {
        $v=$request->validate(['q'=>['required','string','min:2','max:80']]);
        $results=$ledger->searchCustomers(trim($v['q']), 15);
        // Deliberately do not expose phone number or balance in the search endpoint.
        return response()->json(['customers'=>array_map(
            fn(array $customer)=>['id'=>(int)$customer['id'],'name'=>(string)$customer['name']],
            array_slice($results,0,15)
        )])->header('Cache-Control','no-store, private');
    }

    public function feedbackTargets(Request $request, AmFeedbackCustomerSource $source)
    {
        $v=$request->validate(['q'=>['required','string','min:2','max:80']]);
        $q=trim($v['q']);
        abort_unless(mb_strlen($q)>=2,422,'Enter at least 2 characters');
        $results=$source->search($q,15);
        $items=[];
        foreach(array_slice($results,0,15) as $r) {
            if(!is_array($r) || (int)($r['customer_id']??0)<1) continue;
            $items[]=[
                'customer_id'=>(int)$r['customer_id'],
                'customer_name'=>(string)($r['customer_name']??''),
                'vehicle_id'=>!empty($r['vehicle_id'])?(int)$r['vehicle_id']:null,
                'vehicle'=>(string)($r['vehicle']??''),
                'job_card_id'=>!empty($r['job_card_id'])?(int)$r['job_card_id']:null,
                'job_card_ref'=>(string)($r['job_card_ref']??''),
            ];
        }
        return response()->json(['targets'=>$items])
            ->header('Cache-Control','private, no-store');
    }

    public function feedback(Request $request, AmFeedbackCustomerSource $source)
    {
        $v=$request->validate([
            'customer_id'=>['required','integer','min:1'],
            'vehicle_id'=>['nullable','integer','min:1'],
            'job_card_id'=>['nullable','integer','min:1'],
            'rating'=>['required','integer','between:1,5'],
            'comment'=>['required','string','max:3000'],
        ]);
        $customerId=(int)$v['customer_id'];
        $vehicleId=!empty($v['vehicle_id'])?(int)$v['vehicle_id']:null;
        $jobCardId=!empty($v['job_card_id'])?(int)$v['job_card_id']:null;

        // Source must confirm that all selected master entities belong together.
        // Do not accept names, vehicle numbers or job references from browser input.
        $context=$source->resolve($customerId,$vehicleId,$jobCardId);
        abort_unless(
            is_array($context)
            && (int)($context['customer_id']??0)===$customerId
            && trim((string)($context['customer_name']??''))!=='',
            422,'Customer, vehicle or Job Card could not be verified'
        );
        DB::table('am_customer_feedback')->insert([
            'customer_id'=>$customerId,
            'customer_name'=>mb_substr((string)$context['customer_name'],0,160),
            'vehicle'=>mb_substr((string)($context['vehicle']??''),0,32) ?: null,
            'job_card_ref'=>mb_substr((string)($context['job_card_ref']??''),0,64) ?: null,
            'rating'=>(int)$v['rating'],
            'comment'=>$v['comment'],
            'source'=>'internal',
            'created_at'=>now(),
            'updated_at'=>now(),
        ]);
        return back()->with('success','Feedback recorded');
    }

    public function reply(Request $request)
    {
        $v=$request->validate(['feedback_id'=>['required','integer','exists:am_customer_feedback,id'],'reply'=>['required','string','max:2000']]);
        DB::table('am_customer_feedback')->where('id',$v['feedback_id'])->update(['reply_draft'=>$v['reply'],'updated_at'=>now()]);
        return back()->with('success','Reply draft saved; not published to Google');
    }

    public function complaint(Request $request)
    {
        $v=$request->validate([
            'feedback_id'=>['required','integer','exists:am_customer_feedback,id'],
            'action'=>['required',Rule::in(['open','resolve'])],
            'resolution_note'=>['nullable','string','max:2000'],
        ]);
        $message=DB::transaction(function() use ($v,$request) {
            $existing=DB::table('am_customer_complaints')
                ->where('feedback_id',$v['feedback_id'])->lockForUpdate()->first();
            $actorId=(int)$request->user()->id;
            if ($v['action']==='open') {
                if($existing) return 'Complaint already exists; no duplicate created';
                // Unique feedback_id constraint protects concurrent requests.
                $inserted=DB::table('am_customer_complaints')->insertOrIgnore([
                    'feedback_id'=>$v['feedback_id'],'status'=>'open',
                    'created_by'=>$actorId,'created_at'=>now(),'updated_at'=>now()
                ]);
                if($inserted!==1) return 'Complaint already exists; no duplicate created';
                $complaintId=DB::table('am_customer_complaints')
                    ->where('feedback_id',$v['feedback_id'])->value('id');
                DB::table('am_customer_complaint_history')->insert([
                    'complaint_id'=>$complaintId,'action'=>'opened','actor_id'=>$actorId,
                    'note'=>null,'created_at'=>now(),'updated_at'=>now()
                ]);
                return 'Complaint opened';
            }

            abort_unless($existing,404,'Complaint not found');
            $note=trim((string)($v['resolution_note']??''));
            abort_unless($note!=='',422,'Resolution note required');
            $updated=DB::table('am_customer_complaints')
                ->where('id',$existing->id)->where('status','open')->update([
                    'status'=>'resolved','resolution_note'=>$note,
                    'resolved_by'=>$actorId,'resolved_at'=>now(),'updated_at'=>now()
                ]);
            abort_unless($updated===1,409,'Complaint has already been resolved');
            DB::table('am_customer_complaint_history')->insert([
                'complaint_id'=>$existing->id,'action'=>'resolved','actor_id'=>$actorId,
                'note'=>$note,'created_at'=>now(),'updated_at'=>now()
            ]);
            return 'Complaint resolved and audited';
        });
        return back()->with('success',$message);
    }

    public function message(Request $request, AmEngagementLedger $ledger)
    {
        $v=$request->validate([
            'customer_id'=>['required','integer','min:1'],
            'type'=>['required',Rule::in(['balance','reminder','feedback'])],
            'consent'=>['accepted']
        ]);
        $customer=$ledger->customer((int)$v['customer_id']);
        abort_unless($customer && !empty($customer['name']),404,'Customer not found');
        $phone=preg_replace('/\D+/','',(string)($customer['phone']??''));
        if (strlen($phone)===10 && preg_match('/^[6-9]/',$phone)) $phone='91'.$phone;
        abort_unless(preg_match('/^91[6-9]\d{9}$/',$phone),422,'Valid Indian phone required');

        $name=trim((string)$customer['name']);
        $amount=null;
        if ($v['type']==='feedback') {
            $message="Dear {$name}, thank you for visiting Assam Motors. Please share your service feedback with us. - Assam Motors, Guwahati";
        } else {
            // Never derive the amount from a request parameter; query canonical ledger on each send attempt.
            $raw=$ledger->outstanding((int)$v['customer_id']);
            abort_unless(is_numeric($raw),422,'Unverified ledger amount');
            $amount=round((float)$raw,2);
            $formatted=number_format(abs($amount),2,'.',',');
            if ($amount<0) $message="Dear {$name}, your Assam Motors account has a credit balance of INR {$formatted}. Please contact us for details.";
            elseif ($amount==0) $message="Dear {$name}, your Assam Motors account currently has no outstanding balance. Thank you.";
            else $message=($v['type']==='reminder'?'Payment reminder: ':'Account update: ')."Dear {$name}, your current outstanding balance with Assam Motors is INR {$formatted}. If already paid, please contact our office.";
            $message.=" - Assam Motors, Guwahati";
        }
        DB::table('am_customer_message_log')->insert([
            'customer_id'=>$v['customer_id'],'customer_name_snapshot'=>$name,
            'phone_last4'=>substr($phone,-4),'type'=>$v['type'],
            'balance_snapshot'=>$amount,'message'=>$message,
            'status'=>'whatsapp_opened_not_sent','actor_id'=>$request->user()->id,
            'created_at'=>now(),'updated_at'=>now()
        ]);
        return redirect()->away('https://wa.me/'.$phone.'?text='.rawurlencode($message));
    }
}
