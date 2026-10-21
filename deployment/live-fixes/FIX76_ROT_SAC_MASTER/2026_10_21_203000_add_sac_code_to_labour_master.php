<?php
/**
 * FIX76: persist the service accounting code independently of GST percent
 * on existing and future Labour/ROT master records.
 * Never mutate already issued invoice / job-card historical records.
 */
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        if(!Schema::hasTable('labour_master')){
            throw new RuntimeException('FIX76 labour_master missing');
        }
        if(!Schema::hasColumn('labour_master','sac_code')){
            Schema::table('labour_master',function(Blueprint $table){
                $table->string('sac_code',8)->nullable()->after('rot_code');
            });
        }
    }
    public function down(): void {
        // Historical master classification intentionally preserved.
    }
};
