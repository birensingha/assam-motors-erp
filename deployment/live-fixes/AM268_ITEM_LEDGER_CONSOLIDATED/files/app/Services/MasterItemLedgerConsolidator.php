<?php
declare(strict_types=1);

namespace App\Services;

/**
 * AM268_CONSOLIDATED_ITEM_LEDGER
 *
 * Read-only projection over already-saved Item Transaction Ledger events.
 * One row per reliable PURCHASE document / matched GRN or JOB CARD+PART.
 * Never deletes source events or writes inventory/accounting records.
 *
 * Stock IN/OUT is summed only from physical STOCK movement events.
 * Amount is summed only from financial source rows (purchase/JC invoice lines).
 * Multiple source events remain available in the expandable audit history.
 * Unlinked vendor documents and unmatched receipts must NOT be conflated.
 */
final class MasterItemLedgerConsolidator
{
    /**
     * @param array<int,array<string,mixed>> $events
     * @param array<int,int> $purchaseToGrn unambiguous allocation purchase ID => GRN ID
     * @return array<int,array<string,mixed>>
     */
    public static function consolidate(array $events, array $purchaseToGrn=[]): array
    {
        // The same supplier purchase can have more than one saved line
        // for the same Part. Use the Purchase Batch + vendor as one voucher.
        $purchaseIdToBatch=[];
        foreach($events as $e){
            if((string)($e['type']??'')!=='PURCHASE · BILL')continue;
            $pid=self::urlId((string)($e['url']??''),'purchases');
            if(!$pid)continue;
            $batch=trim((string)($e['reference']??''));
            $supplier=mb_strtolower(trim((string)($e['party']??'')));
            $purchaseIdToBatch[$pid]=($batch!==''?'BATCH:'.$batch:'LINE:'.$pid).'|'.$supplier;
        }

        $groups=[];
        $order=[];
        foreach($events as $n=>$e){
            $type=(string)($e['type']??'');
            $url=(string)($e['url']??'');
            $jobId=self::urlId($url,'job-cards');
            $purchaseId=self::urlId($url,'purchases');
            $grnId=self::urlId($url,'procurement/grn');
            $reference=trim((string)($e['reference']??''));
            $isStock=str_starts_with($type,'STOCK ·');
            $isJobLine=$type==='JC · PART LINE';
            $isJobStock=$isStock && (
                str_contains($type,'JOB_ISSUE') ||
                str_contains($type,'JOB_RETURN') ||
                str_contains($type,'JOB_REVERSAL')
            );
            $isGrnStock=$isStock && (
                str_contains($type,'GRN_RECEIPT') ||
                str_contains($type,'GRN_EDIT')
            );
            $isPurchaseStock=$isStock && str_contains($type,'PURCHASE_');
            $isPurchaseDoc=$type==='PURCHASE · BILL';

            // Do not merge unrelated inventory movement just because it
            // happens to refer to the same Job Card.
            if($jobId && ($isJobLine || $isJobStock)){
                $key='JC:'.$jobId;
                $kind='JC';
            }elseif($grnId && $isGrnStock){
                $key='GRN:'.$grnId;
                $kind='GRN';
            }elseif($purchaseId && ($isPurchaseDoc || $isPurchaseStock)){
                if(isset($purchaseToGrn[$purchaseId])){
                    $key='GRN:'.(int)$purchaseToGrn[$purchaseId];
                    $kind='GRN';
                }else{
                    $key='PURCHASE:'.($purchaseIdToBatch[$purchaseId]??('LINE:'.$purchaseId));
                    $kind='PURCHASE';
                }
            }else{
                // No reliable foreign-key link: preserve as standalone row.
                // Never guess a supplier/JC from a similar date/name.
                $key='UNLINKED:'.$n;
                $kind='OTHER';
            }
            if(!isset($groups[$key])){
                $groups[$key]=[
                    'kind'=>$kind,'key'=>$key,'events'=>[],
                    'in_total'=>0.0,'out_total'=>0.0,'financial_amount'=>0.0,
                    'has_financial'=>false,'has_stock'=>false,
                    'sort_time'=>'','sort_id'=>0,'stock_sort_time'=>'',
                    'last_balance'=>null,'primary_url'=>null,'primary_ref'=>'',
                    'party'=>'','vehicle'=>'','document_date'=>null,'erp_posted_date'=>null,
                    'status_list'=>[],'first_date'=>null,'last_date'=>null,
                    'source_types'=>[],'has_grn'=>false,'has_purchase'=>false,
                    'has_job_bill'=>false,'has_job_issue'=>false,
                ];
                $order[]=$key;
            }
            $g=&$groups[$key];
            $g['events'][]=$e;
            $g['source_types'][$type]=true;
            $g['status_list'][(string)($e['status']??'')]=true;
            $stamp=(string)($e['sort_time']??($e['date']??''));
            $date=(string)($e['date']??'');
            if($date!==''){
                if($g['first_date']===null||$date<$g['first_date'])$g['first_date']=$date;
                if($g['last_date']===null||$date>$g['last_date'])$g['last_date']=$date;
            }
            if($stamp>$g['sort_time']){
                $g['sort_time']=$stamp;
                $g['sort_id']=(int)($e['sort_id']??0);
            }
            if($isStock){
                $g['has_stock']=true;
                $g['in_total']+=($e['in']===null?0.0:(float)$e['in']);
                $g['out_total']+=($e['out']===null?0.0:(float)$e['out']);
                if($stamp>=$g['stock_sort_time']){
                    $g['stock_sort_time']=$stamp;
                    $g['last_balance']=$e['balance']??null;
                    $g['erp_posted_date']=$e['erp_posted_date']??($e['date']??null);
                }
            }
            if($isPurchaseDoc || $isJobLine || $type==='COUNTER · INVOICE'){
                if($e['amount']!==null){
                    $g['financial_amount']+=(float)$e['amount'];
                    $g['has_financial']=true;
                }
            }
            if($isPurchaseDoc){
                $g['has_purchase']=true;
                $g['primary_ref']=$reference;
                $g['party']=(string)($e['party']??'');
                $g['primary_url']=$url?:null;
                if($e['document_date']??null)$g['document_date']=$e['document_date'];
                if(!$g['has_stock'])$g['erp_posted_date']=$e['erp_posted_date']??null;
            }
            if($isJobLine){
                $g['has_job_bill']=true;
                if($reference!=='')$g['primary_ref']=$reference;
                if($url!=='')$g['primary_url']=$url;
                if($e['party']??null)$g['party']=$e['party'];
                if($e['vehicle']??null)$g['vehicle']=$e['vehicle'];
            }
            if($isJobStock){
                $g['has_job_issue']=true;
                if($url!=='' && !$g['primary_url'])$g['primary_url']=$url;
                if(($e['party']??'')!==''&&!$g['party'])$g['party']=$e['party'];
                if(($e['vehicle']??'')!==''&&!$g['vehicle'])$g['vehicle']=$e['vehicle'];
                if(!$g['primary_ref'] && $reference!=='')$g['primary_ref']=$reference;
            }
            if($isGrnStock){
                $g['has_grn']=true;
                $g['primary_ref']=$reference?:$g['primary_ref'];
                if($url!=='')$g['primary_url']=$url;
                if(($e['party']??'')!=='')$g['party']=$e['party'];
                if(($e['vehicle']??'')!=='')$g['vehicle']=$e['vehicle'];
                if($e['document_date']??null)$g['document_date']=$e['document_date'];
            }
            // In normal Purchase Stock IN, the actual vendor and supplier
            // invoice metadata is more authoritative than JC customer.
            if($isPurchaseStock && !$g['has_purchase']){
                if($url!=='')$g['primary_url']=$url;
                if(($e['party']??'')!=='')$g['party']=$e['party'];
                if($e['document_date']??null)$g['document_date']=$e['document_date'];
                if(!$g['primary_ref'])$g['primary_ref']=$reference;
            }
            if(!$g['primary_url'] && $url!=='')$g['primary_url']=$url;
            if(!$g['primary_ref'] && $reference!=='')$g['primary_ref']=$reference;
            if(!$g['party'] && ($e['party']??'')!=='')$g['party']=$e['party'];
            if(!$g['vehicle'] && ($e['vehicle']??'')!=='')$g['vehicle']=$e['vehicle'];
            if(!$g['document_date'] && ($e['document_date']??null))$g['document_date']=$e['document_date'];
            if(!$g['erp_posted_date'] && ($e['erp_posted_date']??null))$g['erp_posted_date']=$e['erp_posted_date'];
            unset($g);
        }

        $rows=[];
        foreach($order as $key){
            $g=$groups[$key];
            if(count($g['events'])===1){
                $single=$g['events'][0];
                $single['source_events']=$g['events'];
                $single['source_count']=1;
                $single['consolidated_kind']=$g['kind'];
                $rows[]=$single;
                continue;
            }
            // Maintain chronology by the LATEST physical movement date.
            // A historical backdated invoice can never move physical stock.
            $time=$g['has_stock']?$g['stock_sort_time']:$g['sort_time'];
            $date=substr((string)$time,0,10);
            $in=$g['has_stock']?round($g['in_total'],3):null;
            $out=$g['has_stock']?round($g['out_total'],3):null;
            if($g['kind']==='JC'){
                $title='OUTWARD · JOB CARD';
                if(!$g['has_stock'])$title='JC · PARTS (NOT ISSUED)';
            }elseif($g['kind']==='GRN'){
                $title='INWARD · GRN';
                if($g['has_purchase'])$title='INWARD · GRN + BILL';
            }elseif($g['kind']==='PURCHASE'){
                $title='INWARD · PURCHASE';
            }else{
                $title='RELATED · '.count($g['events']).' EVENTS';
            }
            $status=$g['has_stock']?'STOCK POSTED':'NO STOCK MOVEMENT';
            if($g['kind']==='JC' && $g['has_job_bill']){
                $status=$g['has_stock']?'ISSUED · JC BILLING LINKED':'JC LINE · ISSUE PENDING';
            }
            if($g['kind']==='GRN' && !$g['has_purchase'])$status='GRN RECEIVED · BILL PENDING';
            if(($g['has_purchase'] && $g['has_stock']))$status='PURCHASE SAVED · STOCK POSTED';
            $details=[];
            if($g['has_stock'])$details[]='Recorded stock IN '.number_format($in,3).
                ' / OUT '.number_format($out,3);
            if($g['has_financial'])$details[]='Saved amount ₹'.number_format($g['financial_amount'],2);
            $details[]=count($g['events']).' linked source events';
            $details[]='Expand for original references, dates and audit';
            $rows[]=[
                'date'=>$date,'sort_time'=>$time,'sort_id'=>$g['sort_id'],
                'type'=>$title,'reference'=>$g['primary_ref'],
                'detail'=>implode(' · ',$details),'party'=>$g['party'],
                'vehicle'=>$g['vehicle'],'in'=>$in,'out'=>$out,
                'balance'=>$g['last_balance'],
                'amount'=>$g['has_financial']?round($g['financial_amount'],2):null,
                'url'=>$g['primary_url'],'status'=>$status,'notes'=>'',
                'document_date'=>$g['document_date'],
                'erp_posted_date'=>$g['erp_posted_date']?:$date,
                'date_kind'=>$g['has_grn']?'Physical receipt':
                    ($g['has_purchase']?'Supplier invoice':'Job Card'),
                'source_events'=>$g['events'],'source_count'=>count($g['events']),
                'consolidated_kind'=>$g['kind'],
            ];
        }
        usort($rows,static fn(array $a,array $b)=>
            [(string)($a['sort_time']??''),(int)($a['sort_id']??0),(string)($a['type']??'')] <=>
            [(string)($b['sort_time']??''),(int)($b['sort_id']??0),(string)($b['type']??'')]);
        return $rows;
    }

    private static function urlId(string $url,string $resource): ?int
    {
        if($url===''||!preg_match('~(?:^|/)erp/'.preg_quote($resource,'~').'/([1-9][0-9]*)(?:[/?#]|$)~',$url,$match))
            return null;
        return (int)$match[1];
    }
}
