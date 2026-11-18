<?php
declare(strict_types=1);
require __DIR__.'/files/app/Services/MasterItemLedgerConsolidator.php';
use App\Services\MasterItemLedgerConsolidator as Consolidator;

function ev(string $date,string $time,int $id,string $type,string $ref,string $url,string $party,
    ?float $in,?float $out,?float $amount,?float $balance,?string $docDate=null): array{
    return [
        'date'=>$date,'sort_time'=>$time,'sort_id'=>$id,'type'=>$type,'reference'=>$ref,
        'url'=>'https://staging.assammotors.com'.$url,'party'=>$party,'vehicle'=>'AS01DU9341',
        'detail'=>'source detail','notes'=>'original audit','status'=>'STOCK POSTED',
        'in'=>$in,'out'=>$out,'amount'=>$amount,'balance'=>$balance,
        'erp_posted_date'=>$date,'document_date'=>$docDate,'date_kind'=>'',
    ];
}
function ensure(bool $okay,string $what):void{if(!$okay)throw new RuntimeException($what);}
$rows=[
    ev('2026-10-03','2026-10-03 08:06:41',38,'STOCK · JOB_ISSUE','JC-2026-0023',
        '/erp/job-cards/23','RAJDEEP DAS',0,1,null,-1),
    ev('2026-10-03','2026-10-03 12:00:00',75,'PURCHASE · BILL','PB-261117155031-835',
        '/erp/purchases/75','SHYAM MOTORS',null,null,3397.44,null,'2026-10-03'),
    ev('2026-10-04','2026-10-04 11:01:41',537,'JC · PART LINE','JC-2026-0023',
        '/erp/job-cards/23','RAJDEEP DAS',null,null,3775,null),
    ev('2026-11-16','2026-11-16 08:02:02',690,'JC · PART LINE','JC-20261116-00060',
        '/erp/job-cards/60','BAPAN SHARMA',null,null,578.84,null),
    ev('2026-11-17','2026-11-17 14:50:56',252,'STOCK · GRN_RECEIPT_IN','GRN-261117145056-NCWOTCC',
        '/erp/procurement/grn/5','JUPITER AUTOMOBILE',1,0,null,0,'2026-11-15'),
    ev('2026-11-17','2026-11-17 15:50:31',270,'STOCK · PURCHASE_IN','SM/GST2627/4829',
        '/erp/purchases/75','SHYAM MOTORS',1,0,null,1,'2026-10-03'),
    ev('2026-11-17','2026-11-17 15:59:40',276,'STOCK · JOB_ISSUE','JC-PART-690-ISSUE',
        '/erp/job-cards/60','BAPAN SHARMA',0,1,null,0),
];
$result=Consolidator::consolidate($rows);
ensure(count($result)===4,'AM268_7_RECORDS_NOT_CONSOLIDATED_TO_4');
$byRef=[];
foreach($result as $row)$byRef[$row['reference']]=$row;
ensure(count($byRef)===4,'AM268_UNEXPECTED_DUPLICATE_MAIN_REFERENCE');
ensure(isset($byRef['JC-2026-0023'],$byRef['JC-20261116-00060'],
    $byRef['PB-261117155031-835'],$byRef['GRN-261117145056-NCWOTCC']),
    'AM268_EXPECTED_JC_PURCHASE_GRN_GROUP_MISSING');
ensure($byRef['JC-2026-0023']['out']===1.0 &&
       $byRef['JC-2026-0023']['amount']===3775.0 &&
       $byRef['JC-2026-0023']['source_count']===2,
       'AM268_JC23_STOCK_VS_BILL_NOT_GROUPED');
ensure($byRef['JC-20261116-00060']['out']===1.0 &&
    abs((float)$byRef['JC-20261116-00060']['amount']-578.84)<.005 &&
    $byRef['JC-20261116-00060']['source_count']===2,
    'AM268_JC60_BILL_AND_ISSUE_NOT_GROUPED');
ensure($byRef['PB-261117155031-835']['in']===1.0 &&
    abs((float)$byRef['PB-261117155031-835']['amount']-3397.44)<.005 &&
    $byRef['PB-261117155031-835']['date']==='2026-11-17' &&
    $byRef['PB-261117155031-835']['document_date']==='2026-10-03' &&
    $byRef['PB-261117155031-835']['party']==='SHYAM MOTORS',
    'AM268_SHYAM_BILL_AND_STOCK_IN_INCORRECT');
ensure($byRef['GRN-261117145056-NCWOTCC']['in']===1.0 &&
    $byRef['GRN-261117145056-NCWOTCC']['amount']===null &&
    $byRef['GRN-261117145056-NCWOTCC']['party']==='JUPITER AUTOMOBILE',
    'AM268_UNBILLED_JUPITER_GRN_MUST_STAY_SEPARATE');
ensure(array_sum(array_map(fn($x)=>(float)($x['in']??0),$result))===2.0 &&
       array_sum(array_map(fn($x)=>(float)($x['out']??0),$result))===2.0,
       'AM268_STOCK_MOVEMENT_DOUBLE_COUNT');
ensure($result[3]['balance']===0.0,'AM268_LAST_BALANCE_INCORRECT');
echo "AM268_EXACT_PDF_7_EVENTS_TO_4_ROWS_NO_STOCK_OR_AMOUNT_DUPLICATION_PASS\n";

$extra=ev('2026-11-18','2026-11-18 10:00:00',777,'JC · PART LINE',
    'JC-20261116-00060','/erp/job-cards/60','BAPAN SHARMA',
    null,null,200,null);
$issue=ev('2026-11-18','2026-11-18 10:15:00',778,'STOCK · JOB_ISSUE',
    'JC-PART-778-ISSUE','/erp/job-cards/60','BAPAN SHARMA',
    0,2,null,-2);
$r2=Consolidator::consolidate(array_merge($rows,[$extra,$issue]));
ensure(count($r2)===4,'AM268_EXTRA_JC_LINE_CREATED_DUPLICATE_MAIN_ROW');
$jobRows=array_values(array_filter($r2,fn($x)=>$x['reference']==='JC-20261116-00060'));
ensure(count($jobRows)===1 && $jobRows[0]['out']===3.0 &&
    abs((float)$jobRows[0]['amount']-778.84)<.005 &&
    $jobRows[0]['source_count']===4,
    'AM268_MULTIPLE_ISSUES_SAME_JC_NOT_AGGREGATED');
echo "AM268_ADDITIONAL_PARTS_SAME_JOB_ONE_ROW_UPDATES_TOTAL_AND_AUDIT_PASS\n";

$unrelated=ev('2026-11-18','2026-11-18 11:00:00',779,
    'STOCK · MANUAL_ADJUSTMENT','ADJ-779','/erp/job-cards/60','BAPAN SHARMA',
    1,0,null,-1);
$r3=Consolidator::consolidate(array_merge($rows,[$unrelated]));
ensure(count($r3)===5,'AM268_UNLINKED_MANUAL_ADJUSTMENT_INCORRECTLY_MERGED');
echo "AM268_UNRELATED_STOCK_ADJUSTMENT_REMAINS_INDEPENDENT_PASS\n";

$matched=Consolidator::consolidate($rows,[75=>5]);
ensure(count($matched)===3,'AM268_MATCHED_GRN_AND_VENDOR_BILL_NOT_CONSOLIDATED');
$grn=array_values(array_filter($matched,fn($x)=>$x['reference']==='GRN-261117145056-NCWOTCC'));
ensure(count($grn)===1 && $grn[0]['in']===2.0 &&
   abs((float)$grn[0]['amount']-3397.44)<.005 &&
   $grn[0]['source_count']===3,
   'AM268_LINKED_GRN_BILL_AUDIT_WRONG');
echo "AM268_ONLY_EXPLICIT_GRN_BILL_ALLOCATION_PERMITS_CROSS_DOCUMENT_MERGE_PASS\n";
echo "AM268_PURE_LEDGER_CONSOLIDATION_QA_ALL_PASS\n";
