<?php
declare(strict_types=1);
/**
 * P56: move P55 ROT alteration INTO Job Card Amount/GST Editor, and add
 * real navigation/closed-uninvoiced reopening to Reversal Centre.
 * Does NOT pretend an approved finance review is a financial reversal.
 */
$mode=(string)($argv[1]??'check');
$root=rtrim((string)($argv[2]??''),'/');
if(!in_array($mode,['check','apply'],true)||!is_file("$root/artisan")){
 fwrite(STDERR,"P56_USAGE check|apply STAGING_ROOT\n");exit(2);
}
$files=[
 'resources/views/job-cards/edit.blade.php',
 'resources/views/workshop-controls/job-amounts.blade.php',
 'app/Http/Controllers/Web/AdminWorkshopControlPageController.php',
 'app/Http/Controllers/Web/JobCardRotAlterationController.php',
 'resources/views/job-cards/rot-alteration.blade.php',
 'resources/views/job-cards/unwind.blade.php'
];
$before=[];$after=[];
foreach($files as $f){
 $full="$root/$f";if(!is_file($full)||is_link($full)){
  fwrite(STDERR,"P56_NO_GO_FILE_MISSING ".$f."\n");exit(3);
 }
 $before[$f]=(string)file_get_contents($full);
 $after[$f]=$before[$f];
}
if(str_contains($before[$files[1]],'P56_ROT_EDITOR_IN_AMOUNT')){
 $ok=!str_contains($before[$files[0]],'P55_ROT_ALTERATION_INCLUDE');
 echo $ok?"P56_ALREADY_INSTALLED\n":"P56_NO_GO_PARTIAL_INSTALL\n";
 exit($ok?0:4);
}
function p56one(string &$s,string $old,string $new,string $tag):void{
 $n=substr_count($s,$old);
 if($n!==1)throw new RuntimeException('P56_NO_GO_ANCHOR_'.$tag.'_MATCHES_'.$n);
 $s=str_replace($old,$new,$s);
}
try{
 // Move the ROT panel, don't clone it: the source JC Edit remains customer/
 // vehicle details only, without a second or hidden financial editor.
 $edit=&$after[$files[0]];
 $include=<<<'BLADE'
{{-- P55_ROT_ALTERATION_INCLUDE: Old ERP style lock → reason/note → unlock → edit → save. --}}
@include('job-cards.rot-alteration')
BLADE;
 p56one($edit,$include,"{{-- P56_ROT_MOVED_TO_AMOUNT_EDITOR: use Job Card → Edit Amount/GST for ROT alterations. --}}",'remove_duplicate_rot_edit');
 unset($edit);

 $controller=&$after[$files[2]];
 $amountsData=<<<'PHP'
        return view('workshop-controls.job-amounts',[
            'card'=>$card,'invoiced'=>$invoiced,
            'taxBasisUnverified'=>$taxBasisUnverified,
            'parts'=>$parts,'labour'=>$labour,
        ]);
PHP;
 $rotData=<<<'PHP'
        // P56_ROT_EDITOR_IN_AMOUNT: reuse the ONE P55 audited ROT controller.
        // No Job Card data changes on GET. Invoice/closed statuses stay locked.
        $p55Rows=$labour;
        $p55Staff=DB::table('staff')->whereRaw("LOWER(COALESCE(status,'active'))='active'")
            ->orderBy('name')->get(['id','staff_code','name']);
        $p55Master=Schema::hasTable('labour_master')
            ?DB::table('labour_master')->orderBy('rot_code')->limit(4000)->get():collect();
        $p55Sessions=Schema::hasTable('rot_sessions')
            ?DB::table('rot_sessions')->where('job_card_id',$job)->get():collect();
        $p55InvoiceLocked=$invoiced;
        $p55OperationalLocked=!in_array(strtolower(trim((string)$card->status)),
             ['wip','in progress','in-progress','work in progress'],true);
        return view('workshop-controls.job-amounts',[
            'card'=>$card,'invoiced'=>$invoiced,
            'taxBasisUnverified'=>$taxBasisUnverified,
            'parts'=>$parts,'labour'=>$labour,
            'p55Rows'=>$p55Rows,'p55Staff'=>$p55Staff,
            'p55Master'=>$p55Master,'p55Sessions'=>$p55Sessions,
            'p55InvoiceLocked'=>$p55InvoiceLocked,
            'p55OperationalLocked'=>$p55OperationalLocked,
        ]);
PHP;
 p56one($controller,$amountsData,$rotData,'financial_editor_data');
 unset($controller);

 $amount=&$after[$files[1]];
 $anchor='<div class="summary" aria-label="Current financial totals">';
 $alert=<<<'BLADE'
{{-- P56_ROT_EDITOR_IN_AMOUNT: staff ROT timing, labour rates, discounts in the correct billing control page --}}
@if($invoiced)
  <div class="note danger" id="p56-invoice-finance-gate">
    <strong>INVOICED — accounting reversal required before editing.</strong>
    <a href="{{ route('erp.job-cards.reversal',$card->id) }}#p56-finance-control" class="p56-reversal-link">Open Job Card Reversal Centre →</a>
    Issued invoice, payments and ledger are retained; an approved review alone is NOT a reversal.
  </div>
@elseif(!$amAmountsWip)
  <div class="note" id="p56-closed-wip-gate">
    <strong>ROT alterations require Job Card WIP.</strong>
    <a href="{{ route('erp.job-cards.reversal',$card->id) }}#p56-finance-control">Open Reversal / Reopen Controls →</a>
    Closed non-invoiced cards may be reopened through the audited lifecycle action.
  </div>
@endif
BLADE;
 p56one($amount,$anchor,$alert."\n".$anchor,'amounts_invoice_reversal_hint');
 $endSummary='<div class="stat"><small>Grand Total</small><strong>₹{{ number_format((float)($card->grand_total ?? 0),2) }}</strong></div></div>';
 $panel=<<<'BLADE'
{{-- P56_ROT_EDITOR_IN_AMOUNT: P55 lock/reason/note/unlock/save panel right after financial summary. --}}
@include('job-cards.rot-alteration',['job'=>$card])
BLADE;
 p56one($amount,$endSummary,$endSummary."\n".$panel,'rot_panel_location');
 unset($amount);

 // Only WIP Job Cards can alter operational ROT. Invoiced remains blocked;
 // CLOSED non-invoiced: use existing audited lifecycle.reopen route.
 $rotCtrl=&$after[$files[3]];
 $statusLine=<<<'PHP'
            $status=strtolower(trim((string)$card->status));
            if(in_array($status,['closed','invoiced','cancelled'],true)||!empty($card->invoice_id)){
PHP;
 $newStatus=<<<'PHP'
            // P56_ROT_IN_AMOUNT_WIP_SERVER_GATE: front-end unlock is never
            // permission; backend enforces WIP, matching billing controller.
            $status=strtolower(trim((string)$card->status));
            if(!in_array($status,['wip','in progress','in-progress','work in progress'],true)){
                $this->block('ROT alteration requires Job Card WIP. Use authorised reopen lifecycle; invoice-linked Job Cards need posted financial reversal first.');
            }
            if(in_array($status,['closed','invoiced','cancelled'],true)||!empty($card->invoice_id)){
PHP;
 p56one($rotCtrl,$statusLine,$newStatus,'rot_wip_server_gate');
 $returnOld=<<<'PHP'
        return redirect()->route('erp.job-cards.edit',$job)
PHP;
 $returnNew=<<<'PHP'
        return redirect()->route('erp.fix48.job-amounts',$job)
PHP;
 p56one($rotCtrl,$returnOld,$returnNew,'save_go_back_to_amount_editor');
 unset($rotCtrl);

 // All six P55 row controls stay read-only on closed or invoiced cards.
 $rotView=&$after[$files[4]];
 $invoiceLock='@if($p55InvoiceLocked??false)';
 $stateLock="@if((\$p55InvoiceLocked??false) || (\$p55OperationalLocked??false))";
 p56one($rotView,$invoiceLock,$stateLock,'banner_lock_state');
 $invoiceMsg=<<<'BLADE'
    <div class="p55-alert">Invoice already issued: original ROT amounts and completed history are locked. Reverse/revise through authorised finance controls first.</div>
BLADE;
 $stateMsg=<<<'BLADE'
    <div class="p55-alert">
      @if($p55InvoiceLocked??false)
       <strong>INVOICED / ISSUED:</strong> Original ROT rate, discount, dates and billing remain locked until a posted invoice/receipt/ledger financial reversal is complete.
      @else
       <strong>NOT WIP:</strong> Operational ROT changes require the Job Card to be officially reopened to WIP with an audit reason.
      @endif
      <a href="{{ route('erp.job-cards.reversal',$job->id) }}#p56-finance-control">Open Job Card Reversal Centre →</a>
    </div>
BLADE;
 p56one($rotView,$invoiceMsg,$stateMsg,'accurate_locked_reason');
 p56one($rotView,'@if(!($p55InvoiceLocked??false))',
   '@if(!($p55InvoiceLocked??false) && !($p55OperationalLocked??false))','lock_button_operational_state');
 unset($rotView);

 // Replace generic review-only presentation with explicit recovery controls:
 // amount editor (read-only when invoice linked), invoice reference, existing
 // authorised CLOSED->WIP action, and FINANCE review request. Never claim a
 // P16 approval reverses any invoice or clears outstanding payment.
 $reversal=&$after[$files[5]];
 $reversalSection=<<<'BLADE'
 {{-- P56_FINANCE_REVERSAL_CONTROL_CENTRE: real entry points with honest accounting status. --}}
 @php
   $p56Status=strtolower(trim((string)$card->status));
   $p56Invoices=\Illuminate\Support\Facades\DB::table('invoices')
     ->where('job_card_id',$card->id)->orderByDesc('id')
     ->get(['id','invoice_no','status','grand_total','paid_total','balance_total']);
   $p56ActiveInvoices=$p56Invoices->filter(fn($i)=>strtolower((string)$i->status)!=='reversed');
   $p56ReceiptCount=\Illuminate\Support\Facades\DB::table('customer_payments')
      ->where('job_card_id',$card->id)->whereNull('reversed_at')->count();
   $p56LedgerCount=\Illuminate\Support\Facades\DB::table('customer_ledger')
      ->where('job_card_id',$card->id)->count();
   $p56HasIssued=!empty($card->invoice_id)||$p56ActiveInvoices->count()>0||$p56Status==='invoiced';
 @endphp
 <section class="jcu-card" id="p56-finance-control" style="border-top:4px solid #175d95">
   <h2>ROT / Amount Editing and Financial Reversal Controls</h2>
   <p class="jcu-small">ROT editing is now under Job Card Amount/GST Editor. The existing lock → alteration reason → Admin note → unlock → save flow is reused there.</p>
   <div style="display:flex;gap:9px;flex-wrap:wrap;margin:13px 0">
     <a class="jcu-action" href="{{ route('erp.fix48.job-amounts',$card->id) }}">Open Job Card Amount/GST Editor →</a>
     <a class="jcu-action" href="{{ route('erp.job-cards.show',$card->id) }}">Job Card Summary →</a>
   </div>
   @if($p56HasIssued)
     <div class="jcu-note" style="border-left:4px solid #bb742d;padding:11px;background:#fff6e8">
       <strong>INVOICED — original financial record locked.</strong>
       An Admin Reversal Review request, even if approved, is not a posted reversal.
       Don't change the issued invoice, taxes, customer receipts or linked ledger in place.
     </div>
     @foreach($p56Invoices as $row)
       <div class="jcu-item"><strong>{{ $row->invoice_no }} · {{ strtoupper((string)$row->status) }}</strong>
         <div class="jcu-small">Original total ₹{{ number_format((float)$row->grand_total,2) }} · Paid ₹{{ number_format((float)$row->paid_total,2) }} · Balance ₹{{ number_format((float)$row->balance_total,2) }}</div>
         <a href="{{ route('erp.invoices.show',$row->id) }}">View Issued Invoice →</a>
       </div>
     @endforeach
     <p class="jcu-small">Linked non-reversed receipts: {{ $p56ReceiptCount }} · Customer ledger rows: {{ $p56LedgerCount }}. Credit note/reversal and any payment adjustment must be posted in accounts before the Job Card can be reopened.</p>
     <form class="jcu-form" method="post" action="{{ route('erp.job-cards.reversal.request',$card->id) }}">
       @csrf
       <input type="hidden" name="scope" value="FINANCE">
       <label>Request FINANCE / Invoice Reversal Review
         <textarea name="reason" minlength="12" maxlength="500" required rows="2" placeholder="Invoice correction reason, requested changes, GST/receipt adjustments"></textarea>
       </label>
       <button class="jcu-action">Register Finance Reversal Request →</button>
     </form>
     <p class="jcu-small"><strong>Important:</strong> This button records the request only. An actual credit note/payment/ledger reversal action is not yet available in this Centre.</p>
   @elseif($p56Status==='closed')
     <p><strong>CLOSED but not invoiced:</strong> The existing authorised Reopen Job Card action can return this Job Card to WIP after a reason is entered.</p>
     <form class="jcu-form" method="post" action="{{ route('erp.job-cards.lifecycle.reopen',$card->id) }}">
       @csrf
       <label>Reason to Reopen Closed Job Card (required)
         <textarea name="reason" required minlength="5" maxlength="1000" rows="2" placeholder="Why does this Job Card need billing/ROT correction?"></textarea>
       </label>
       <button class="jcu-action" type="submit">Reopen CLOSED Job Card to WIP →</button>
     </form>
   @elseif(in_array($p56Status,['in-progress','in progress','wip','work in progress'],true))
     <p><strong>WIP / EDITABLE:</strong> Open the Amount Editor above; the ROT lock/reason/unlock form is available without any financial reversal.</p>
   @else
     <div class="jcu-note">Current Job Card status: {{ strtoupper((string)$card->status) }}. Open Job Card Summary for authorised WIP lifecycle controls. Do not edit cancelled/locked financial history.</div>
   @endif
 </section>
BLADE;
 $reversalAnchor=' <section class="r15-head" aria-label="Job Card reversal five point progress">';
 p56one($reversal,$reversalAnchor,$reversalSection."\n".$reversalAnchor,'finance_reversal_centre_control_section');
 unset($reversal);

 chdir($root);require "$root/vendor/autoload.php";
 $app=require "$root/bootstrap/app.php";
 $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
 foreach(['job_cards','job_card_labour','customer_ledger','customer_payments','invoices','rot_sessions'] as $table)
   if(!\Illuminate\Support\Facades\Schema::hasTable($table))throw new RuntimeException('P56_TABLE_MISSING_'.$table);
 foreach(['erp.fix48.job-amounts','erp.job-cards.lifecycle.reopen','erp.job-cards.reversal.request','erp.invoices.show'] as $name)
   if(!\Illuminate\Support\Facades\Route::has($name))throw new RuntimeException('P56_ROUTE_MISSING_'.$name);
 foreach($files as $f){
   $code=str_ends_with($f,'.blade.php')?app('blade.compiler')->compileString($after[$f]):$after[$f];
   token_get_all($code,TOKEN_PARSE);
   echo "P56_SOURCE_COMPILE_OK=".basename($f)."\n";
 }
 echo "P56_ROT_IN_AMOUNT_REVERSE_SAFETY_PREFLIGHT_PASS\n";
}catch(Throwable $e){
 fwrite(STDERR,"P56_NO_GO_PRECHECK ".$e->getMessage()."\n");exit(5);
}
if($mode==='check'){
 echo "P56_NO_RECORD_WRITES_DRY_RUN_PASS\n";exit(0);
}
$backup="$root/storage/app/p56_amount_reversal_backup_".date('Ymd_His').'_'.getmypid();
if(!mkdir($backup,0700,true)){
 fwrite(STDERR,"P56_BACKUP_DIR_ERROR\n");exit(6);
}
foreach($files as $f){
 $out="$backup/".str_replace('/','__',$f);
 if(!copy("$root/$f",$out)||hash_file('sha256',$out)!==hash('sha256',$before[$f])){
  fwrite(STDERR,"P56_BACKUP_HASH_ERROR\n");exit(6);
 }
}
foreach($files as $f)if(hash_file('sha256',"$root/$f")!==hash('sha256',$before[$f])){
 fwrite(STDERR,"P56_CONCURRENT_EDITS_ABORT\n");exit(7);
}
try{
 foreach($files as $f){
  $target="$root/$f";$tmp=tempnam(dirname($target),'.p56_');
  if(!$tmp||file_put_contents($tmp,$after[$f],LOCK_EX)!==strlen($after[$f])
    ||!chmod($tmp,fileperms($target)&0777)||!rename($tmp,$target))
    throw new RuntimeException("P56_WRITE_FAIL_".$f);
 }
}catch(Throwable $e){
 foreach($files as $f)copy("$backup/".str_replace('/','__',$f),"$root/$f");
 fwrite(STDERR,"P56_ROLLBACK ".$e->getMessage()."\n");exit(8);
}
echo "P56_INSTALLED_BACKUP=$backup\nP56_NO_FINANCIAL_OR_JC_DATA_MUTATIONS_ON_INSTALL\n";
