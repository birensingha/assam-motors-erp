<?php
namespace App\Services;

use Illuminate\Validation\ValidationException;

/**
 * FIX70: Supplier OSL line calculation. Purchase tax mode is independent
 * of customer's Job Card selling GST / mark-up.
 * No financial posting here; returns only a deterministic line calculation.
 */
final class OslPurchaseTax
{
    public static function quote(float $qty,float $rate,float $discountPct,float $gstPct,string $mode): array
    {
        $mode=strtoupper(trim($mode));
        if (!in_array($mode,['EXTRA','INCLUDED','NONE'],true)) {
            throw ValidationException::withMessages(['gst_mode'=>'Select GST Extra, GST Included, or GST Not Applicable.']);
        }
        foreach ([$qty,$rate,$discountPct,$gstPct] as $v) {
            if (!is_finite($v))throw ValidationException::withMessages(['gst_percent'=>'Invalid amount or GST value.']);
        }
        if ($qty<=0 || $qty>10000 || $rate<0 || $rate>10000000 ||
            $discountPct<0 || $discountPct>100 || $gstPct<0 || $gstPct>100) {
            throw ValidationException::withMessages(['gst_percent'=>'Invalid OSL Qty, Rate, Discount or GST.']);
        }
        if ($mode==='NONE' && $gstPct>0.00001) {
            throw ValidationException::withMessages(['gst_percent'=>'GST Not Applicable requires GST rate 0.']);
        }
        $gross=round($qty*$rate,2,PHP_ROUND_HALF_UP);
        $discount=round($gross*$discountPct/100,2,PHP_ROUND_HALF_UP);
        $net=round($gross-$discount,2,PHP_ROUND_HALF_UP);
        if($mode==='INCLUDED' && $gstPct>0) {
            $taxable=round($net/(1+$gstPct/100),2,PHP_ROUND_HALF_UP);
            $gst=round($net-$taxable,2,PHP_ROUND_HALF_UP);
            $total=$net;
        } else {
            $taxable=$net;
            $gst=$mode==='EXTRA'?round($taxable*$gstPct/100,2,PHP_ROUND_HALF_UP):0.0;
            $total=round($taxable+$gst,2,PHP_ROUND_HALF_UP);
        }
        return [
            'gross'=>$gross,'discount'=>$discount,'taxable'=>$taxable,
            'gst'=>$gst,'total'=>$total,'mode'=>$mode,
        ];
    }
}
