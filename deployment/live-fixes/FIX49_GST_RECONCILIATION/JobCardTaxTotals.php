<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/** FIX49_GST_RECONCILIATION: stored line total is taxable AFTER discount. */
final class JobCardTaxTotals
{
    public static function fromLines(iterable $parts, iterable $labour): array
    {
        $partTotal=0.0; $labourTotal=0.0; $gst=0.0;
        foreach ([['kind'=>'part','rows'=>$parts],['kind'=>'labour','rows'=>$labour]] as $section) {
            foreach ($section['rows'] as $line) {
                $amount=round((float)($line->total??0),2);
                $rate=(float)($line->gst_percent??0);
                if (!is_finite($amount) || !is_finite($rate) || $amount<0 || $rate<0 || $rate>100) {
                    throw ValidationException::withMessages(['status'=>'Invalid '.$section['kind'].' taxable amount or GST rate.']);
                }
                if ($section['kind']==='part') $partTotal=round($partTotal+$amount,2);
                else $labourTotal=round($labourTotal+$amount,2);
                $gst=round($gst+round($amount*$rate/100,2),2);
            }
        }
        $subtotal=round($partTotal+$labourTotal,2);
        $preRound=round($subtotal+$gst,2);
        $roundOff=round(round($preRound,0,PHP_ROUND_HALF_UP)-$preRound,2);
        return [
            'parts_total'=>$partTotal,'labour_total'=>$labourTotal,'subtotal'=>$subtotal,
            'gst_total'=>$gst,'pre_round_total'=>$preRound,'round_off'=>$roundOff,
            'grand_total'=>round($preRound+$roundOff,2),
        ];
    }

    public static function forJob(int $jobId): array
    {
        $parts=DB::table('job_card_parts')->where('job_card_id',$jobId)->get(['total','gst_percent']);
        $labour=DB::table('job_card_labour')->where('job_card_id',$jobId);
        // P34_ROT_BILLING_CONTROL: never include No Charge ROT in taxable base/GST.
        if (Schema::hasColumn('job_card_labour','billing_included')) $labour->where('billing_included',1);
        return self::fromLines($parts,$labour->get(['total','gst_percent']));
    }
}
