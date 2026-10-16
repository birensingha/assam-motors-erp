<?php
/**
 * Read-only payment-editor deployment gate. Run from Laravel app root with:
 * php /path/to/PREFLIGHT_PAYMENTS.php
 */
declare(strict_types=1);
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$need=['id','payment_date','amount','payment_mode','record_status','source_type','source_reference'];
$table='workshop_payment_vouchers';
if(!Schema::hasTable($table)){fwrite(STDERR,"NO-GO voucher table unavailable\n");exit(4);}
$cols=Schema::getColumnListing($table);
$missing=array_diff($need,$cols);
if($missing){fwrite(STDERR,'NO-GO voucher column mismatch: '.implode(',',$missing)."\n");exit(4);}
$service='app/Services/ExpensePaymentService.php';
$vendor='app/Services/VendorService.php';
if(!is_file($service)||!is_file($vendor)){fwrite(STDERR,"NO-GO authoritative accounting services missing\n");exit(4);}
$source=file_get_contents($vendor);
if(!str_contains($source,'workshop_payment_vouchers')){
  fwrite(STDERR,"NO-GO Vendor Ledger is not demonstrably voucher-derived\n");exit(4);
}
// Guard against silently leaving other materialized ledgers unchanged.
$dbName=DB::connection()->getDatabaseName();
$links=DB::select("SELECT table_name,column_name FROM information_schema.columns WHERE table_schema=? AND column_name IN ('payment_voucher_id','workshop_payment_voucher_id')",[$dbName]);
$allowed=['workshop_payment_edit_audits','workshop_payment_vouchers'];
foreach($links as $link){
  if(!in_array((string)$link->table_name,$allowed,true)){
    fwrite(STDERR,'NO-GO linked payment ledger: '.$link->table_name.'.'.$link->column_name."\n");exit(4);
  }
}
// No unsupported parallel payment journal; source must be reviewed if service
// persists multiple accounting tables. Check only the POST/save block when
// integrating against current deployment, not against a static mock.
$paymentSource=file_get_contents($service);
foreach(['cash_book_entries','bank_ledger_entries','general_ledger_entries','account_journal_entries'] as $materialized){
  if(str_contains($paymentSource,$materialized)){
    fwrite(STDERR,"NO-GO additional journal postings found: $materialized\n");exit(4);
  }
}
if(!in_array('ON_ACCOUNT',\App\Services\ExpensePaymentService::SOURCE_TYPES,true)){
  fwrite(STDERR,"NO-GO P9 voucher source rules absent\n");exit(4);
}
echo "PAYMENT_VOUCHER_EDIT_PREFLIGHT=GO (no writes)\n";