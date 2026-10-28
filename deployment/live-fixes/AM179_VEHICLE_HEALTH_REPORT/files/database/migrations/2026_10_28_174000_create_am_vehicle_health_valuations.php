<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('am_vehicle_health_valuations',function(Blueprint $table){
            $table->unsignedBigInteger('job_card_id')->primary();
            $table->decimal('reference_market_price',13,2);
            $table->unsignedInteger('odometer_km')->default(0);
            $table->decimal('estimated_repair_cost',13,2)->default(0);
            $table->decimal('indicative_value_snapshot',13,2);
            $table->unsignedTinyInteger('health_snapshot');
            $table->decimal('admin_final_value',13,2)->nullable();
            $table->text('admin_note')->nullable();
            $table->string('approved_by',190)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
        Schema::create('am_vehicle_health_valuation_audit',function(Blueprint $table){
            $table->bigIncrements('id');
            $table->unsignedBigInteger('job_card_id')->index();
            $table->string('actor',190);
            $table->longText('previous_json')->nullable();
            $table->longText('updated_json');
            $table->timestamp('created_at');
        });
    }
    public function down(): void {
        // Retain audit rows until an explicitly approved data migration.
    }
};
