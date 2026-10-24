<?php
declare(strict_types=1);

/**
 * AM127: READ ONLY. Identify why P55 says Not Assigned for completed ROT-DNT-001.
 * No insert/update/delete/DDL/migration/cache command.
 * Run only from STAGING Laravel root. Reports limited field values.
 */
if (!is_file(getcwd().'/artisan')) {fwrite(STDERR,"AM127_STOP_NOT_LARAVEL_STAGING_ROOT\n");exit(2);}
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$code='ROT-DNT-001';
echo "AM127_READ_ONLY_START\n";
echo "TARGET_ROT_CODE=".$code."\n";
function tableColumns(string $name): array {
    try {
      return Schema::hasTable($name) ? Schema::getColumnListing($name) : [];
    } catch (\Throwable $e) {echo "TABLE_{$name}_READ_ERROR=".get_class($e)."\n";return [];}
}
function showKeys(object $row,array $names): void {
    foreach ($names as $name) if(property_exists($row,$name)){
        $v=$row->{$name};
        $s=trim((string)($v??'NULL'));
        if(strlen($s)>135)$s=substr($s,0,135);
        $s=preg_replace('/[^[:print:]]/u',' ',$s)??'[unsupported]';
        echo strtoupper($name).'='.$s."\n";
    }
}

$lineCols=tableColumns('job_card_labour');
$sessionCols=tableColumns('rot_sessions');
echo "LINE_COLUMNS=".implode(',',$lineCols)."\n";
echo "SESSION_MECHANIC_RELATED_COLUMNS=".implode(',',array_values(array_filter($sessionCols,fn($c)=>
    preg_match('/staff|mech|tech|employee|user|assign|line_id|job_card_id|status|session/i',$c)===1)))."\n";
if (!in_array('rot_code',$lineCols,true)) {echo "AM127_STOP_ROT_CODE_MISSING_NO_WRITES\n";exit(2);}
$lines=DB::table('job_card_labour')->where('rot_code',$code)->orderByDesc('id')->limit(8)->get();
echo "MATCHED_LABOUR_LINES=".$lines->count()."\n";
foreach ($lines as $line) {
    echo "--- P55_LABOUR_LINE ---\n";
    showKeys($line,['id','job_card_id','rot_code','description','mechanic_id','mechanic_name','rot_session_id','execution_status','standard_hours','rot_start','rot_end']);
    if ($sessionCols && in_array('job_card_id',$sessionCols,true) && in_array('labour_line_id',$sessionCols,true)){
        $sessions=DB::table('rot_sessions')->where('job_card_id',$line->job_card_id)
            ->where(function($q)use($line){
                $q->where('labour_line_id',$line->id);
                if(!empty($line->rot_session_id))$q->orWhere('id',$line->rot_session_id);
            })->orderByDesc('id')->limit(10)->get();
        echo "LINKED_ROT_SESSIONS=".$sessions->count()."\n";
        foreach($sessions as $session){
            echo "--- ROT_SESSION ---\n";
            showKeys($session,['id','job_card_id','labour_line_id','rot_code','status','staff_id',
                'staff_code','staff_name','staff_user_id','employee_id','user_id','assigned_staff_id',
                'assigned_mechanic_id','mechanic_id','mechanic_name','technician_id',
                'technician_name','first_start_at','start_time','end_time','total_seconds']);
        }
    }else{
        echo "LINKED_SESSION_QUERY_SKIPPED_MISSING_KEYS\n";
    }
}

foreach(['staff','staff_users','erp_users','users','employees'] as $table) {
    $cols=tableColumns($table);
    if($cols)echo "STAFF_LOOKUP_TABLE={$table}; ID_NAME_FIELDS=".
      implode(',',array_values(array_filter($cols,fn($c)=>preg_match('/^(id|name|full_name|staff_code|code|role|active|status|employee_code)$/',$c)))).
      "\n";
}

// Source inspection bounded to relevant lines; no full file/source disclosure.
foreach(['resources/views/job-cards/rot-alteration.blade.php',
         'app/Http/Controllers/Web/JobCardRotAlterationController.php',
         'app/Http/Controllers/Web/AdminJobCardPageController.php'] as $file){
    echo "=== SOURCE: ".$file." ===\n";
    if(!is_file($file)){echo "MISSING\n";continue;}
    $src=file($file);
    $selected=[];
    foreach($src as $n=>$line) {
        if(preg_match('/mechanic_id|mechanic_name|mechanicOptions|mechanicList|p55Rows|p55Mechanic|staff_id|staffUsers|Not Assigned|rot_sessions|select[^>]*mechanic|option[^>]*mechanic/i',$line))$selected[]=$n;
    }
    $selected=array_slice($selected,0,22);
    foreach($selected as $i) {
        $lo=max(0,$i-1);$hi=min(count($src)-1,$i+2);
        for($j=$lo;$j<=$hi;$j++){
            $s=trim($src[$j]);
            if (strlen($s)>215)$s=substr($s,0,215).'…';
            echo ($j+1)." ".str_replace(["\r","\n"],' ',$s)."\n";
        }
    }
}
echo "AM127_READ_ONLY_COMPLETE_NO_DATABASE_WRITES\n";
