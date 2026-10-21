<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if(Schema::hasTable('workshop_purchases') && !Schema::hasColumn('workshop_purchases','gst_mode')){
            Schema::table('workshop_purchases', function(Blueprint $t){
                $t->string('gst_mode',16)->nullable()->after('gst_percent');
            });
        }
    }
    public function down(): void {
        // Intentionally keep mode on rollback; historical invoice basis must not be lost.
    }
};
