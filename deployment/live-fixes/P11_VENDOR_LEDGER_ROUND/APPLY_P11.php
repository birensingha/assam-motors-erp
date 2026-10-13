<?php
declare(strict_types=1);
// P11: Vendor Ledger displays batch round-off adjustments so balance matches posted purchase grand total.
$mode=$argv[1]??'check';
if(!in_array($mode,['check','apply'],true)){fwrite(STDERR,"Usage: APPLY_P11.php check|apply\n");exit(2);}
$root=rtrim((string)getenv('HOME'),'/').'/domains/assammotors.com/assam-erp-staging';
if(realpath($root)!==$root||!is_file($root.'/artisan')){fwrite(STDERR,"NO-GO canonical staging missing\n");exit(2);}
$rels=['app/Services/VendorService.php','resources/views/vendors/ledger.blade.php'];$files=[];
function patchP11(string $s,string $old,string $new,string $label):string {
 $n=substr_count($s,$old);if($n!==1)throw new RuntimeException("NO-GO ".$label." count=".$n);
 return str_replace($old,$new,$s);
}
try {
 foreach($rels as $rel){$path=$root.'/'.$rel;if(!is_file($path)||is_link($path))throw new RuntimeException("NO-GO file ".$rel);$files[$rel]=['before'=>(string)file_get_contents($path),'after'=>''];}
 $rel='app/Services/VendorService.php';$s=$files[$rel]['before'];
 if(str_contains($s,'VENDOR_LEDGER_BATCH_P11')){echo "ALREADY_PRESENT: no changes\n";exit(0);}
 $old=<<<'OLD'
        $opening = $openingPurchases + $openingExpenses - $openingLegacyPayments - $openingUnifiedPayments;
OLD;
 $new=<<<'NEW'
        $opening = $openingPurchases + $openingExpenses - $openingLegacyPayments - $openingUnifiedPayments;
        // VENDOR_LEDGER_BATCH_P11: apply posted Purchase Bill round-off to opening balance too.
        $batchDeltas=$this->purchaseBatchDeltas($vendorId);
        if ($from !== '') foreach($batchDeltas as $adj){if($adj['date']<$from)$opening+=$adj['delta'];}
NEW;
 $s=patchP11($s,$old,$new,'opening adjustment');
 $old=<<<'OLD'
        usort($entries, fn($a,$b)=>[$a['sort_date'],$a['sort_type'],$a['id']] <=> [$b['sort_date'],$b['sort_type'],$b['id']]);
OLD;
 $new=<<<'NEW'
        foreach($batchDeltas as $adj) {
            if($from!=='' && $adj['date']<$from)continue;
            if($to!=='' && $adj['date']>$to)continue;
            $delta=(float)$adj['delta'];$periodPurchases+=$delta;
            $entries[]=[
                'sort_date'=>$adj['date'],'sort_type'=>1,'id'=>1000000000+(int)$adj['id'],
                'date'=>$adj['date'],'entry_no'=>$adj['batch_no'],'entry_type'=>'ROUND_OFF','payment_kind'=>'',
                'description'=>'Purchase Bill Batch Round-Off Adjustment',
                'invoice_no'=>$adj['invoice'],'job_no'=>'','vehicle_reg_no'=>'','payment_mode'=>'','reference_no'=>'',
                'debit'=>max(0,-$delta),'credit'=>max(0,$delta)
            ];
        }
        usort($entries, fn($a,$b)=>[$a['sort_date'],$a['sort_type'],$a['id']] <=> [$b['sort_date'],$b['sort_type'],$b['id']]);
NEW;
 $s=patchP11($s,$old,$new,'period batch adjustment entry');
 $helper=<<<'HELPER'

    /** Read-only posted batch delta; purchase lines and financial records remain immutable. */
    private function purchaseBatchDeltas(int $vendorId):array
    {
        $batches=DB::table('workshop_purchase_batches')
            ->where('purchase_type','PART')->where('record_status','ACTIVE')->where('vendor_id',$vendorId)
            ->get(['id','batch_no','purchase_date','supplier_invoice','grand_total']);
        $result=[];
        foreach($batches as $b){
            $rows=DB::table('workshop_purchases')->where('purchase_batch_no',$b->batch_no)->where('purchase_type','PART')
                ->where('vendor_id',$vendorId)->whereRaw("COALESCE(record_status,'ACTIVE') <> 'DELETED'")->get(['total_amount']);
            if($rows->isEmpty())continue;
            $delta=round((float)$b->grand_total-(float)$rows->sum('total_amount'),2);
            if(abs($delta)<0.009)continue;
            $result[]=['id'=>(int)$b->id,'batch_no'=>(string)$b->batch_no,
                'date'=>(string)$b->purchase_date,'invoice'=>(string)($b->supplier_invoice??''),
                'delta'=>$delta];
        }
        return $result;
    }

HELPER;
 $tail=strrpos($s,"\n}");
 if($tail===false || $tail<strlen($s)-12)throw new RuntimeException('NO-GO unexpected VendorService class tail');
 $s=substr($s,0,$tail)."\n".$helper.substr($s,$tail);
 $files[$rel]['after']=$s;
 $rel='resources/views/vendors/ledger.blade.php';$s=$files[$rel]['before'];
 $old="@elseif((\$e['payment_kind'] ?? '')==='UNIFIED')<a class=\"btn small\" href=\"{{ route('erp.payments.show',\$e['id']) }}\">View</a>@else<form";
 $new="@elseif((\$e['payment_kind'] ?? '')==='UNIFIED')<a class=\"btn small\" href=\"{{ route('erp.payments.show',\$e['id']) }}\">View</a>@elseif(\$e['entry_type']==='ROUND_OFF')<span class=\"muted\">Batch adjustment</span>@else<form";
 $s=patchP11($s,$old,$new,'round off ledger row non-destructive UI');
 $files[$rel]['after']=$s;
 foreach($files as $rel=>$pair){
  if(str_ends_with($rel,'.php'))token_get_all($pair['after'],TOKEN_PARSE);
  echo "P11_PRECHECK ".basename($rel)." OLD_SHA256=".hash('sha256',$pair['before'])." NEW_SHA256=".hash('sha256',$pair['after'])."\n";
 }
} catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(2);}
if($mode==='check'){echo "P11_VENDOR_LEDGER_DRY_RUN_PASS: no files or financial data modified\n";exit(0);}
$backup=$root.'/storage/app/vendor_p11_backup_'.date('Ymd_His').'_'.getmypid();
if(!@mkdir($backup,0700,true)){fwrite(STDERR,"NO-GO backup dir\n");exit(2);}
foreach($files as $rel=>$pair){$path=$backup.'/'.str_replace('/','__',$rel);if(!copy($root.'/'.$rel,$path)||hash_file('sha256',$path)!==hash('sha256',$pair['before'])){fwrite(STDERR,"NO-GO backup ".$rel."\n");exit(2);}}
$written=[];
try {
 foreach($files as $rel=>$pair){
  $path=$root.'/'.$rel;if(hash_file('sha256',$path)!==hash('sha256',$pair['before']))throw new RuntimeException("Concurrent source change ".$rel);
  $tmp=tempnam(dirname($path),'.p11_');if(!$tmp||file_put_contents($tmp,$pair['after'],LOCK_EX)!==strlen($pair['after']))throw new RuntimeException("Write failed ".$rel);
  chmod($tmp,fileperms($path)&0777);if(!rename($tmp,$path))throw new RuntimeException("Rename failed ".$rel);
  $written[]=$rel;if(hash_file('sha256',$path)!==hash('sha256',$pair['after']))throw new RuntimeException("Verify failed ".$rel);
 }
} catch(Throwable $e) {
 foreach($written as $rel)copy($backup.'/'.str_replace('/','__',$rel),$root.'/'.$rel);
 fwrite(STDERR,"NO-GO ROLLBACK: ".$e->getMessage()."\n");exit(2);
}
echo "P11_VENDOR_ROUNDOFF_LEDGER_APPLIED: BACKUP=".$backup."\n";
echo "NO FINANCIAL RECORDS MODIFIED.\n";
