<?php
declare(strict_types=1);
/** AM212 read-only installed-source + DB record verification; no test writes. */
$root=realpath((string)($argv[1]??''));
if(!$root || basename($root)!=='assam-erp-staging' || !is_file($root.'/artisan')){
    fwrite(STDERR,"AM212_VERIFY_STOP_NOT_STAGING\n");exit(2);
}
chdir($root);
require $root.'/vendor/autoload.php';
$app=require $root.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function vcheck(bool $ok,string $label):void{
    if(!$ok){fwrite(STDERR,"AM212_VERIFY_FAIL_".$label."\n");exit(3);}
}
vcheck(Schema::hasTable('labour_master'),'TABLE');
vcheck(Schema::hasColumn('labour_master','work_procedure'),'WORK_PROCEDURE_COLUMN');
$row=DB::table('labour_master')->where('rot_code','ROT-CUS-0211')->first();
vcheck((bool)$row,'ROT_CUS_0211_NOT_FOUND');
$src=file_get_contents($root.'/resources/views/admin/native/labour.blade.php');
$ctl=file_get_contents($root.'/app/Http/Controllers/Web/AdminNativeModuleController.php');
vcheck(is_string($src)&&is_string($ctl),'SOURCE');
foreach([
  'Parts / Components to Remove &amp; Refit'=>2,
  'Mention only the parts/components that must be removed, disconnected and refitted to perform this ROT.'=>2,
  'Both rear wheels, rear brake drums, handbrake cables, brake hose/pipe brackets, ABS sensor wiring, shock lower bolts, coil springs, torsion beam mounting bolts.'=>2,
  'Workshop reference only. Not printed on Estimate / Invoice.'=>2,
  'name="work_procedure"'=>2,
  'AM212_ROT_WORK_PROCEDURE'=>1,
] as $text=>$count){
    $found=substr_count($src,$text);
    $testName=match($text){
      'Parts / Components to Remove &amp; Refit'=>'LABEL',
      'Mention only the parts/components that must be removed, disconnected and refitted to perform this ROT.'=>'HELPER',
      'Both rear wheels, rear brake drums, handbrake cables, brake hose/pipe brackets, ABS sensor wiring, shock lower bolts, coil springs, torsion beam mounting bolts.'=>'PLACEHOLDER',
      'Workshop reference only. Not printed on Estimate / Invoice.'=>'BOTTOM_NOTE',
      'name="work_procedure"'=>'FIELD',
      default=>'SOURCE_MARKER',
    };
    echo "AM212_VERIFY_UI_".$testName."_COUNT_".$found."_EXPECTED_".$count."\n";
    vcheck($found===$count,'UI_'+$testName+'_COUNT');
}
vcheck(!str_contains($src,'Work Procedure / Technical Instructions'),'OLD_HEADING_STILL_PRESENT');
vcheck(str_contains($ctl,"'work_procedure'"),'BACKEND_CREATE_EDIT_FIELD');
vcheck(str_contains($ctl,'public function createLabour('),'CREATE_HANDLER');
vcheck(str_contains($ctl,'public function updateLabour('),'EDIT_HANDLER');
vcheck(str_contains($src,"route('erp.masters.labour.create')"),'CREATE_ACTION');
vcheck(str_contains($src,'route(\'erp.masters.labour.update\',$r->id)'),'EDIT_ACTION');
vcheck(substr_count($src,'@csrf')>=2,'CSRF');
echo "AM212_SOURCE_CREATE_EDIT_LABELS_VERIFIED\n";
echo "AM212_DB_WORK_PROCEDURE_COLUMN_VERIFIED\n";
echo "AM212_TARGET_ROT_CUS_0211_PRESENT\n";
echo "AM212_TARGET_ROT_SAVED_TEXT_LENGTH_".strlen((string)($row->work_procedure??''))."\n";
echo "AM212_READ_ONLY_SOURCE_AND_DB_TEST_PASS\n";
echo "AM212_AUTHENTICATED_BROWSER_SAVE_REFRESH_TEST_NOT_RUN\n";
