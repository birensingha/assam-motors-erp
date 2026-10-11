<?php
declare(strict_types=1);

$root=rtrim((string)($argv[1]??''),'/');
if($root==='' || !is_file($root.'/artisan')){fwrite(STDERR,"Usage: php MIGRATE_FIX46.php /path/to/laravel/app\n");exit(2);}
chdir($root);
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

if(!Schema::hasTable('workshop_qc_checks')){
    Schema::create('workshop_qc_checks',function(Blueprint $t){
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
echo "FIX46 additive schema guard: PASS\n";
