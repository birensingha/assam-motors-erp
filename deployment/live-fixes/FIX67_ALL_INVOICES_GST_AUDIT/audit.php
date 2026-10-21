<?php
/** FIX67 — STAGING invoice vs saved JC vs current item GST audit. Strictly READ ONLY.
 *  Shows no customer names, contacts, vehicle registrations or receipt details.
 *  Historical invoices MUST NOT be rewritten based on mutable current JC lines.
 */
declare(strict_types=1);
$root=(string)($argv[1]??'');
if(!is_file($root.'/artisan')){fwrite(STDERR,"FIX67_BAD_ROOT\n");exit(2);}
chdir($root);
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
function cents($v):int{return (int)round((float)$v*100);}
function value($o,string $k):float{return (float)($o->$k??0);}
function sumBy(array $xs,string $k):float{return round(array_reduce($xs,fn($a,$r)=>$a+value($r,$k),0.0),2);}
function shortId($v):string{return mb_substr(preg_replace('/[^A-Za-z0-9._-]/','',strval($v)),0,48);}
function summary(array $x):void{echo 'FIX67_SUMMARY '.json_encode($x,JSON_INVALID_UTF8_SUBSTITUTE|JSON_UNESCAPED_SLASHES)."\n";}
function runAudit():void{
 if(!Schema::hasTable('invoices')||!Schema::hasTable('job_cards')||!Schema::hasTable('job_card_parts')||!Schema::hasTable('job_card_labour'))throw new RuntimeException('FIX67_MISSING_REQUIRED_TABLES');
 $invoiceCols=Schema::getColumnListing('invoices');
 $jobCols=Schema::getColumnListing('job_cards');
 $partsCols=Schema::getColumnListing('job_card_parts');
 $labourCols=Schema::getColumnListing('job_card_labour');
 $invoices=DB::table('invoices')->orderBy('id')->get();
 $jobsById=[];
 $partsByJob=[];
 $labourByJob=[];
 $ids=$invoices->pluck('job_card_id')->filter()->unique()->values()->all();
 foreach(array_chunk($ids,200) as $idsChunk){
  foreach(DB::table('job_cards')->whereIn('id',$idsChunk)->get() as $r)$jobsById[(int)$r->id]=$r;
  foreach(DB::table('job_card_parts')->whereIn('job_card_id',$idsChunk)->get() as $r)$partsByJob[(int)$r->job_card_id][]=$r;
  $lQuery=DB::table('job_card_labour')->whereIn('job_card_id',$idsChunk);
  if(in_array('billing_included',$labourCols,true))$lQuery->where('billing_included',1);
  foreach($lQuery->get() as $r)$labourByJob[(int)$r->job_card_id][]=$r;
 }
 $counts=['total_invoices'=>$invoices->count(),'active_invoices'=>0,'reversed_invoices'=>0,
   'missing_jc'=>0,'complete_comparison'=>0,'jc_line_vs_invoice_total'=>0,
   'jc_line_vs_invoice_gst'=>0,'invoice_zero_gst_but_jc_gst_positive'=>0,
   'invoice_internal_arithmetic_mismatch'=>0,'saved_jc_total_vs_lines'=>0,
   'invoice_issued_before_latest_item_update'=>0,
   'no_computed_discrepancy'=>0,'both_total_and_gst_mismatch'=>0];
 $statuses=[];
 $totals=['invoice_original_gross_paise'=>0,'invoice_original_gst_paise'=>0,
   'active_invoice_gross_paise'=>0,'active_invoice_gst_paise'=>0,
   'signed_active_gross_vs_lines_difference_paise'=>0,
   'sum_abs_active_gross_difference_paise'=>0];
 $patterns=[];$examples=[];$staleExamples=[];$arithExamples=[];
 $allIssues=[];
 foreach($invoices as $inv){
   $status=strtolower(trim((string)($inv->status??'')));
   $statuses[$status?:'unknown']=($statuses[$status?:'unknown']??0)+1;
   $isReversed=in_array($status,['reversed','void','voided','cancelled','canceled'],true);
   $isActive=!$isReversed;
   $isReversed?$counts['reversed_invoices']++:$counts['active_invoices']++;
   $gross=cents($inv->grand_total??0);
   $gst=cents($inv->gst_total??0);
   $totals['invoice_original_gross_paise']+=$gross;
   $totals['invoice_original_gst_paise']+=$gst;
   if($isActive){$totals['active_invoice_gross_paise']+=$gross;$totals['active_invoice_gst_paise']+=$gst;}
   $job=$jobsById[(int)($inv->job_card_id??0)]??null;
   if(!$job){$counts['missing_jc']++;continue;}
   $jobId=(int)$job->id;
   $partLines=$partsByJob[$jobId]??[];
   $labourLines=$labourByJob[$jobId]??[];
   // Identical taxable/GST/round-off algorithm to the deployed JobCardTaxTotals::fromLines().
   $computed=\App\Services\JobCardTaxTotals::fromLines($partLines,$labourLines);
   $counts['complete_comparison']++;
   $computedGross=cents($computed['grand_total']);
   $computedGst=cents($computed['gst_total']);
   $dGross=$computedGross-$gross;
   $dGst=$computedGst-$gst;
   $isBadGross=abs($dGross)>2;
   $isBadGst=abs($dGst)>2;
   if($isBadGross)$counts['jc_line_vs_invoice_total']++;
   if($isBadGst)$counts['jc_line_vs_invoice_gst']++;
   if($isBadGst&&$isBadGross)$counts['both_total_and_gst_mismatch']++;
   if(!$isBadGross&&!$isBadGst)$counts['no_computed_discrepancy']++;
   $zeroTax=($gst===0 && $computedGst>2);
   if($zeroTax)$counts['invoice_zero_gst_but_jc_gst_positive']++;
   $inc=0;
   if(in_array('round_off',$invoiceCols,true)&&in_array('subtotal',$invoiceCols,true)&&in_array('gst_total',$invoiceCols,true)){
      $invoiceRecalc=cents($inv->subtotal??0)+cents($inv->gst_total??0)+cents($inv->round_off??0);
      if(abs($invoiceRecalc-$gross)>2){$counts['invoice_internal_arithmetic_mismatch']++;$inc=$invoiceRecalc-$gross;}
   }
   $jobMismatch=false;
   foreach(['parts_total','labour_total','gst_total','pre_round_total','round_off','grand_total'] as $name){
      if(in_array($name,$jobCols,true)&&abs(cents($job->$name??0)-cents($computed[$name]))>2){$jobMismatch=true;break;}
   }
   if($jobMismatch)$counts['saved_jc_total_vs_lines']++;
   $late=false;
   if(!empty($inv->created_at)){
     foreach(array_merge($partLines,$labourLines) as $line){
        if(!empty($line->updated_at)&&strtotime((string)$line->updated_at)>strtotime((string)$inv->created_at)+120){$late=true;break;}
     }
   }
   if($late)$counts['invoice_issued_before_latest_item_update']++;
   if($isActive){
     $totals['signed_active_gross_vs_lines_difference_paise']+=$dGross;
     $totals['sum_abs_active_gross_difference_paise']+=abs($dGross);
   }
   $pattern=$zeroTax?'ORIGINAL_GST_ZERO_JC_GST_POSITIVE':
     ($isBadGross&&$isBadGst?'BOTH_AMOUNT_AND_GST_DIFFER':
      ($isBadGross?'AMOUNT_ONLY_DIFF':($isBadGst?'GST_ONLY_DIFF':'MATCH')));
   $patterns[$pattern]=($patterns[$pattern]??0)+1;
   if($isBadGross||$isBadGst||$inc!==0){
      $record=['jc'=>shortId($job->job_no??$jobId),'invoice'=>shortId($inv->invoice_no??$inv->id),
         'invoice_status'=>$status,'jc_status'=>shortId($job->status??''),
         'original_gross'=>$gross/100,'original_gst'=>$gst/100,
         'current_jc_line_gross'=>$computedGross/100,'current_jc_line_gst'=>$computedGst/100,
         'gross_diff'=>$dGross/100,'gst_diff'=>$dGst/100,'invoice_internal_diff'=>$inc/100,
         'current_lines_modified_after_issue'=>$late,'pattern'=>$pattern];
      $allIssues[]=$record;
      if(count($examples)<45)$examples[]=$record;
   }
 }
 summary(['statuses'=>$statuses,'counts'=>$counts,'patterns'=>$patterns,
    'original_invoice_totals_rupees'=>['all_gross'=>$totals['invoice_original_gross_paise']/100,'all_gst'=>$totals['invoice_original_gst_paise']/100,
    'active_gross'=>$totals['active_invoice_gross_paise']/100,'active_gst'=>$totals['active_invoice_gst_paise']/100],
    'active_comparison_only'=>['sum_signed_current_jc_minus_historical_invoice_rupees'=>$totals['signed_active_gross_vs_lines_difference_paise']/100,
       'sum_absolute_current_jc_vs_historical_invoice_gross_difference_rupees'=>$totals['sum_abs_active_gross_difference_paise']/100],
    'limits'=>'Stored ORIGINAL invoice vs CURRENT possibly edited JC lines; historical mismatch does NOT alone prove original invoice incorrect',
    'invoice_fields'=>array_values(array_intersect(['subtotal','gst_total','round_off','grand_total','paid_amount','balance_due'],$invoiceCols))]);
 foreach($examples as $issue)echo 'FIX67_EXCEPTION '.json_encode($issue,JSON_INVALID_UTF8_SUBSTITUTE|JSON_UNESCAPED_SLASHES)."\n";
 if(count($allIssues)>count($examples))echo 'FIX67_EXCEPTION_TRUNCATED '.(count($allIssues)-count($examples))."\n";
 echo "FIX67_READ_ONLY_COMPLETE_NO_CHANGES\n";
}
DB::connection()->statement('START TRANSACTION READ ONLY');
$exit=0;
try{runAudit();}
catch(Throwable $e){fwrite(STDERR,'FIX67_AUDIT_ERROR '.get_class($e).' '.preg_replace('/[\x00-\x1F]+/',' ',$e->getMessage())."\n");$exit=2;}
finally{DB::connection()->rollBack();}
exit($exit);
