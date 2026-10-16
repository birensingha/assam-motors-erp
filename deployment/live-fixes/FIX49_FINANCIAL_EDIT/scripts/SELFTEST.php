<?php
/** Fixed sample GST test with amounts shown on the user's old JC. */
declare(strict_types=1);
function gstIncluded(float $gross,float $pct): array {
    $base=round($gross*100/(100+$pct),2);
    return [$base,round($gross-$base,2)];
}
$items=[[3516,18],[1399.5,18],[400,18]];
$net=0.0;$tax=0.0;
foreach($items as [$amt,$pct]) {[$n,$g]=gstIncluded((float)$amt,(float)$pct);$net+=$n;$tax+=$g;}
$gross=round($net+$tax,2);
$grand=round($gross,0);
if(abs($net-4504.66)>0.01||abs($tax-810.84)>0.01||abs($gross-5315.5)>0.01||abs($grand-5316)>0.01) {
  fwrite(STDERR,"FAIL sample inclusive GST totals $net/$tax/$gross/$grand\n");exit(1);
}
$qty=2;$rate=1489.83;$discount=0;$gst=18;
$taxable=round($qty*$rate*(1-$discount/100),2);
$vat=round($taxable*$gst/100,2);
if(abs($taxable-2979.66)>0.001 || abs($vat-536.34)>0.001)exit(2);
echo "PASS PDF inclusive GST ₹810.84, total ₹5316.00; NEW line taxable 2979.66 + GST 536.34\n";