<?php
namespace App\Http\Controllers\Admin;

use App\Contracts\AmEngagementLedger;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AmCustomerEngagementController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('am_customer_feedback')->orderByDesc('id');
        $search = trim((string)$request->query('q',''));
        if ($search !== '') $query->where(function($q) use ($search) {
            $q->where('customer_name','like','%'.$search.'%')
              ->orWhere('vehicle','like','%'.$search.'%')
              ->orWhere('job_card_ref','like','%'.$search.'%');
        });
        return view('admin.engagement.index', [
            'feedback' => $query->paginate(20)->withQueryString(),
            'complaints' => DB::table('am_customer_complaints')->orderByDesc('id')->limit(30)->get(),
            'messages' => DB::table('am_customer_message_log')->orderByDesc('id')->limit(30)->get(),
        ]);
    }

    public function feedback(Request $request)
    {
        $v = $request->validate([
            'customer_id'=>['nullable','integer','min:1'],
            'customer_name'=>['required','string','max:160'],
            'vehicle'=>['nullable','string','max:32'],
            'job_card_ref'=>['nullable','string','max:64'],
            'rating'=>['required','integer','between:1,5'],
            'comment'=>['required','string','max:3000'],
        ]);
        DB::table('am_customer_feedback')->insert($v+['source'=>'internal','created_at'=>now(),'updated_at'=>now()]);
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
        DB::transaction(function() use ($v,$request) {
            $existing=DB::table('am_customer_complaints')->where('feedback_id',$v['feedback_id'])->lockForUpdate()->first();
            if ($v['action']==='open' && !$existing) {
                DB::table('am_customer_complaints')->insert([
                    'feedback_id'=>$v['feedback_id'],'status'=>'open',
                    'created_by'=>$request->user()->id,'created_at'=>now(),'updated_at'=>now()
                ]);
            } elseif ($v['action']==='resolve') {
                abort_unless($existing,404);
                abort_unless(strlen(trim($v['resolution_note']??''))>0,422,'Resolution note required');
                DB::table('am_customer_complaints')->where('id',$existing->id)->update([
                    'status'=>'resolved','resolution_note'=>$v['resolution_note'],'updated_at'=>now()
                ]);
            }
        });
        return back()->with('success','Complaint updated');
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
