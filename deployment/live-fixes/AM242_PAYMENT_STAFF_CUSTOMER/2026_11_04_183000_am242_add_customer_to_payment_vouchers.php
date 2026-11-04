<?php
/* AM242_CUSTOMER_PAYOUT_LEDGER: additive nullable identity column.
 * No old Payment Voucher amounts or statuses are modified.
 */
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if(!Schema::hasColumn('workshop_payment_vouchers','customer_id')){
            Schema::table('workshop_payment_vouchers',function(Blueprint $t){
                $t->unsignedBigInteger('customer_id')->nullable()->index('am242_payment_customer_idx');
            });
        }
    }
    public function down(): void {
        if(Schema::hasColumn('workshop_payment_vouchers','customer_id')){
            Schema::table('workshop_payment_vouchers',function(Blueprint $t){
                $t->dropIndex('am242_payment_customer_idx');
                $t->dropColumn('customer_id');
            });
        }
    }
};
