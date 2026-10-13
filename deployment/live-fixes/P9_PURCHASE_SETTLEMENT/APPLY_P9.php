<?php
declare(strict_types=1);
/**
 * ASSAM MOTORS - FIX PURCHASE BATCH ROUND-OFF + 3 MODES (2026-10-13)
 * Staging only. No DB changes or voucher writes. Exact-source, backup, rollback.
 */
$mode=$argv[1]??'check';
if(!in_array($mode,['check','apply'],true)){fwrite(STDERR,"Usage: php APPLY_PURCHASE_PAYMENT_P9.php check|apply\n");exit(2);}
$root=rtrim((string)getenv('HOME'),'/').'/domains/assammotors.com/assam-erp-staging';
if(realpath($root)!==$root || !is_file($root.'/artisan')){fwrite(STDERR,"NO-GO: canonical staging root unavailable\n");exit(2);}
$files=[
 'app/Services/ExpensePaymentService.php'=>[],
 'app/Http/Controllers/Web/AdminExpensePaymentPageController.php'=>[],
 'resources/views/payments/create.blade.php'=>[],
 'resources/views/payments/index.blade.php'=>[],
 'public/js/payment-voucher.js'=>[],
];
function exactChange(string $source,string $old,string $new,string $label): string {
 $count=substr_count($source,$old);
 if($count!==1)throw new RuntimeException("NO-GO source mismatch ".$label." occurrence=".$count);
 return str_replace($old,$new,$source);
}
try {
 foreach($files as $rel=>&$pair){
  $path=$root.'/'.$rel;
  if(!is_file($path)||is_link($path))throw new RuntimeException("NO-GO source missing/symlink ".$rel);
  $pair=['before'=>(string)file_get_contents($path),'after'=>''];
 }
 unset($pair);
 $pay='app/Services/ExpensePaymentService.php';
 $s=$files[$pay]['before'];
 if(str_contains($s,'PAYMENT_BATCH_GRAND_TOTAL_P9')) {
  echo "ALREADY_PRESENT: Purchase payment batch fix. No source files changed.\n";exit(0);
 }
 $s=exactChange($s,
 "public const SOURCE_TYPES = ['PURCHASE_INVOICE','EXPENSE_VOUCHER','OSL_JOB','ADVANCE','OTHER'];",
 "public const SOURCE_TYPES = ['PURCHASE_INVOICE','EXPENSE_VOUCHER','OSL_JOB','ADVANCE','ON_ACCOUNT','OTHER'];",
 'source type policy');
 $s=exactChange($s,
 "        foreach(\$purchaseGroups as \$p){ \$out=round((float)\$p->total-(float)(\$paidPurchase[\$p->src_ref] ?? 0),2);",
 "        // PAYMENT_BATCH_GRAND_TOTAL_P9: final posted purchase bill amount wins over summed purchase lines.\n".
 "        \$batchRefs=\$purchaseGroups->pluck('src_ref')->filter()->values()->all();\n".
 "        \$batchTotals=DB::table('workshop_purchase_batches')->whereIn('batch_no',\$batchRefs)->where('purchase_type','PART')->where('record_status','ACTIVE')->pluck('grand_total','batch_no');\n".
 "        foreach(\$purchaseGroups as \$p){ \$billTotal=(float)(\$batchTotals[\$p->src_ref] ?? \$p->total); \$out=round(\$billTotal-(float)(\$paidPurchase[\$p->src_ref] ?? 0),2);",
 'payable source uses rounded batch total');
 $s=exactChange($s,
 "            \$total=(float)\$rows->sum('total_amount'); \$first=\$rows->first();",
 "            \$batchTotal=DB::table('workshop_purchase_batches')->where('batch_no',\$ref)->where('purchase_type','PART')->where('record_status','ACTIVE')->lockForUpdate()->value('grand_total');\n".
 "            \$total=\$batchTotal!==null ? (float)\$batchTotal : (float)\$rows->sum('total_amount'); \$first=\$rows->first();",
 'persisted payment validation uses rounded final');
 $s=exactChange($s,
 "        \$jobId=(int)(\$data['job_card_id'] ?? 0); if(\$jobId>0",
 "        if(\$type==='ON_ACCOUNT' && \$vendorId<=0) throw ValidationException::withMessages(['vendor_id'=>'Select a Vendor for On Account payment.']);\n".
 "        \$jobId=(int)(\$data['job_card_id'] ?? 0); if(\$jobId>0",
 'on account must be vendor-linked');
 $files[$pay]['after']=$s;

 $ctrl='app/Http/Controllers/Web/AdminExpensePaymentPageController.php';
 $s=$files[$ctrl]['before'];
 $s=exactChange($s,'PURCHASE_INVOICE,EXPENSE_VOUCHER,OSL_JOB,ADVANCE,OTHER',
     'PURCHASE_INVOICE,EXPENSE_VOUCHER,OSL_JOB,ADVANCE,ON_ACCOUNT,OTHER','voucher request validation');
 $files[$ctrl]['after']=$s;

 $view='resources/views/payments/create.blade.php';
 $s=$files[$view]['before'];
 $s=exactChange($s,'<option value="PURCHASE_INVOICE">PURCHASE INVOICE</option>',
     '<option value="PURCHASE_INVOICE">AGAINST REFERENCE — PURCHASE BILL</option>','purchase payment label');
 $s=exactChange($s,'<option value="ADVANCE">ADVANCE</option><option value="OTHER">OTHER</option>',
     '<option value="ADVANCE">ADVANCE — SUPPLIER</option><option value="ON_ACCOUNT">ON ACCOUNT — UNALLOCATED</option><option value="OTHER">OTHER</option>','3 payment modes');
 $s=exactChange($s,'<label>Vendor (optional)<select name="vendor_id" id="payment-vendor">',
     '<label>Vendor (required for On Account)<select name="vendor_id" id="payment-vendor">','manual vendor label');
 $s=exactChange($s,'<div class="source-preview payment-source-wide" id="source-preview"></div>',
     '<div class="source-preview payment-source-wide" id="source-preview"></div><div class="payment-source-wide" style="border-radius:10px;padding:11px;background:#eff7ff;border:1px solid #c9dfef;color:#174263;font-size:13px" id="settlement-guidance">Against Reference settles the selected Purchase Bill at its final rounded outstanding; Advance/On Account are unallocated vendor payments until formally adjusted.</div>',
     'payment type guidance');
 $files[$view]['after']=$s;

 $index='resources/views/payments/index.blade.php';
 $s=$files[$index]['before'];
 $s=exactChange($s,"'OSL_JOB','ADVANCE','OTHER'", "'OSL_JOB','ADVANCE','ON_ACCOUNT','OTHER'",'payment register filters');
 $files[$index]['after']=$s;

 $js='public/js/payment-voucher.js';
 $s=$files[$js]['before'];
 $s=exactChange($s,"'OSL_JOB','ADVANCE','OTHER'].includes(preset.source)","'OSL_JOB','ADVANCE','ON_ACCOUNT','OTHER'].includes(preset.source)",'JS preset source');
 $s=exactChange($s,"type.addEventListener('change',rebuild); key.addEventListener('change',syncSource); rebuild();",
     "const vendor=document.getElementById('payment-vendor');\n".
     "  function syncVendorRule(){if(vendor)vendor.required=type.value==='ON_ACCOUNT';}\n".
     "  type.addEventListener('change',()=>{rebuild();syncVendorRule();}); key.addEventListener('change',syncSource); rebuild();syncVendorRule();",
     'client vendor rule');
 $files[$js]['after']=$s;
 foreach($files as $rel=>$pair) {
   if($pair['before']===$pair['after'])throw new RuntimeException("NO-GO unchanged ".$rel);
   if(str_ends_with($rel,'.php')){try{token_get_all($pair['after'],TOKEN_PARSE);}catch(Throwable $e){throw new RuntimeException("Invalid PHP ".$rel.": ".$e->getMessage());}}
   echo "PRECHECK ".basename($rel)." SHA_BEFORE=".hash('sha256',$pair['before'])." SHA_AFTER=".hash('sha256',$pair['after'])."\n";
 }
} catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(2);}
if($mode==='check'){echo "PAYMENT_ROUND_OFF_P9_DRY_RUN_PASS: no sources or data modified\n";exit(0);}
$backup=$root.'/storage/app/payment_p9_backup_'.date('Ymd_His').'_'.getmypid();
if(!@mkdir($backup,0700,true)){fwrite(STDERR,"NO-GO backup directory\n");exit(2);}
foreach($files as $rel=>$pair){
 $copy=$backup.'/'.str_replace('/','__',$rel);
 if(!copy($root.'/'.$rel,$copy)||hash_file('sha256',$copy)!==hash('sha256',$pair['before'])){fwrite(STDERR,"NO-GO backup ".$rel."\n");exit(2);}
}
$written=[];
try {
 foreach($files as $rel=>$pair){
  $file=$root.'/'.$rel;
  if(hash_file('sha256',$file)!==hash('sha256',$pair['before']))throw new RuntimeException("Concurrent edit: ".$rel);
  $temp=tempnam(dirname($file),'.p9_');
  if(!$temp||file_put_contents($temp,$pair['after'],LOCK_EX)!==strlen($pair['after']))throw new RuntimeException("Write failed: ".$rel);
  chmod($temp,fileperms($file)&0777);
  if(!rename($temp,$file))throw new RuntimeException("Rename failed: ".$rel);
  $written[]=$rel;
  if(hash_file('sha256',$file)!==hash('sha256',$pair['after']))throw new RuntimeException("Verify failed: ".$rel);
 }
} catch(Throwable $e){
 foreach($written as $rel) copy($backup.'/'.str_replace('/','__',$rel),$root.'/'.$rel);
 fwrite(STDERR,"NO-GO rolled back: ".$e->getMessage()."\n");exit(2);
}
echo "PAYMENT_ROUND_OFF_P9_APPLIED: BACKUP=".$backup."\n";
echo "UNCHANGED: Purchase bill amounts, historical vouchers, ledgers, balances and all Job Cards.\n";
