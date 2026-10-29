<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('workshop_vendor_master_audits')) return;

        Schema::create('workshop_vendor_master_audits', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('vendor_id')->index();
            $table->string('action', 60);
            $table->longText('before_json')->nullable();
            $table->longText('after_json')->nullable();
            $table->string('reason', 500);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_name', 190)->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        // Never discard vendor edit audit history on routine rollback.
        // A deliberate, separately reviewed archival migration is required.
    }
};
