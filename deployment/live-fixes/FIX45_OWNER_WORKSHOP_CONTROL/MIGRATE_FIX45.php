<?php

declare(strict_types=1);

$root = rtrim((string)($argv[1] ?? ''), '/');
if ($root === '' || !is_file($root.'/artisan')) {
    fwrite(STDERR, "Usage: php MIGRATE_FIX45.php /path/to/laravel/app\n");
    exit(2);
}

chdir($root);
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

if (Schema::hasTable('job_cards') && !Schema::hasColumn('job_cards','priority')) {
    Schema::table('job_cards', function (Blueprint $t) {
        $t->string('priority', 20)->nullable()->after('status');
    });
    echo "ADD job_cards.priority\n";
}

if (!Schema::hasTable('workshop_owner_audit_logs')) {
    Schema::create('workshop_owner_audit_logs', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedBigInteger('admin_user_id')->nullable();
        $t->string('admin_name',180)->nullable();
        $t->string('action',100);
        $t->string('entity_type',80);
        $t->string('entity_id',120)->nullable();
        $t->unsignedBigInteger('job_card_id')->nullable()->index();
        $t->unsignedBigInteger('staff_id')->nullable()->index();
        $t->text('reason')->nullable();
        $t->longText('before_json')->nullable();
        $t->longText('after_json')->nullable();
        $t->longText('meta_json')->nullable();
        $t->dateTime('created_at')->index();
    });
    echo "CREATE workshop_owner_audit_logs\n";
}

if (!Schema::hasTable('workshop_qc_checks')) {
    Schema::create('workshop_qc_checks', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedBigInteger('job_card_id')->index();
        $t->unsignedBigInteger('assigned_staff_id')->nullable()->index();
        $t->string('result',20)->default('PENDING')->index();
        $t->text('notes')->nullable();
        $t->longText('failed_items_json')->nullable();
        $t->unsignedBigInteger('checked_by_admin_user_id')->nullable();
        $t->dateTime('checked_at')->nullable();
        $t->timestamps();
    });
    echo "CREATE workshop_qc_checks\n";
}

if (!Schema::hasTable('workshop_road_tests')) {
    Schema::create('workshop_road_tests', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedBigInteger('job_card_id')->index();
        $t->unsignedBigInteger('staff_id')->nullable()->index();
        $t->string('driver_name',150)->nullable();
        $t->decimal('odometer_start',12,1)->nullable();
        $t->decimal('odometer_end',12,1)->nullable();
        $t->string('status',20)->default('PLANNED')->index();
        $t->dateTime('started_at')->nullable();
        $t->dateTime('ended_at')->nullable();
        $t->text('notes')->nullable();
        $t->unsignedBigInteger('created_by_user_id')->nullable();
        $t->timestamps();
    });
    echo "CREATE workshop_road_tests\n";
}

if (!Schema::hasTable('workshop_key_custody')) {
    Schema::create('workshop_key_custody', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedBigInteger('job_card_id')->index();
        $t->unsignedBigInteger('staff_id')->nullable()->index();
        $t->string('holder_name',150)->nullable();
        $t->string('action',30)->index();
        $t->text('notes')->nullable();
        $t->unsignedBigInteger('admin_user_id')->nullable();
        $t->dateTime('created_at')->index();
    });
    echo "CREATE workshop_key_custody\n";
}

if (!Schema::hasTable('workshop_reworks')) {
    Schema::create('workshop_reworks', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedBigInteger('job_card_id')->index();
        $t->unsignedBigInteger('original_job_card_id')->nullable()->index();
        $t->text('reason');
        $t->text('complaint')->nullable();
        $t->string('status',20)->default('OPEN')->index();
        $t->unsignedBigInteger('opened_by_user_id')->nullable();
        $t->dateTime('opened_at')->nullable();
        $t->dateTime('resolved_at')->nullable();
        $t->timestamps();
    });
    echo "CREATE workshop_reworks\n";
}

if (!Schema::hasTable('workshop_parts_requests')) {
    Schema::create('workshop_parts_requests', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedBigInteger('job_card_id')->index();
        $t->unsignedBigInteger('part_master_id')->nullable()->index();
        $t->string('part_code',60)->nullable();
        $t->string('part_name',255);
        $t->decimal('qty',12,3);
        $t->string('status',20)->default('PENDING')->index();
        $t->unsignedBigInteger('requested_by_staff_id')->nullable()->index();
        $t->unsignedBigInteger('requested_by_user_id')->nullable();
        $t->text('note')->nullable();
        $t->unsignedBigInteger('processed_by_user_id')->nullable();
        $t->dateTime('processed_at')->nullable();
        $t->timestamps();
    });
    echo "CREATE workshop_parts_requests\n";
}

echo "FIX45 additive schema: PASS\n";
