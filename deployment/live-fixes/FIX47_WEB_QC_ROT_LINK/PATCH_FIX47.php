<?php
declare(strict_types=1);
/**
 * FIX47 - incremental ONLY. No DB migrations / no billing or ledger operations.
 * Expected FIX46 staging source hashes were verified by read-only run 38150604139.
 */
$root=rtrim((string)($argv[1]??''),'/');
if(!$root || !is_file($root.'/artisan')){fwrite(STDERR,"Usage: php PATCH_FIX47.php /laravel/root\n");exit(2);}
$controller=$root.'/app/Http/Controllers/Web/AdminJobCardLifecyclePageController.php';
$panel=$root.'/resources/views/job-cards/lifecycle-panel.blade.php';
$c=file_get_contents($controller); $v=file_get_contents($panel);
if($c===false || $v===false)throw new RuntimeException('Cannot read FIX46 source');
$marker='FIX47_QC_FAILED_ROT_SESSION';
$cHas=str_contains($c,$marker);$vHas=str_contains($v,$marker);
if($cHas && $vHas){echo "FIX47 already installed: NO-OP\n";exit(0);}
if($cHas xor $vHas)throw new RuntimeException('Partial FIX47 installation: manual audit required');
if(hash('sha256',$c)!=='c13c615fd489cc075c631f4f0752067c9560b54be17267cbdad60813ff253135')throw new RuntimeException('Controller changed since verified FIX46: HOLD');
if(hash('sha256',$v)!=='b790daa4f0fbc25d4aebe146821ff1f07cf939b06c3520ace489917b75a65cab')throw new RuntimeException('Lifecycle panel changed since verified FIX46: HOLD');

function replaceExactlyOnce(string $text,string $old,string $new,string $label):string{
    $count=substr_count($text,$old);
    if($count!==1)throw new RuntimeException($label.' exact anchor count='.$count);
    return str_replace($old,$new,$text);
}

$start=strpos($c,'    public function qcResult(');
$end=strpos($c,'    public function readyForDelivery(',$start);
if($start===false || $end===false || $end<=$start)throw new RuntimeException('QC controller method boundaries changed');
$qc=substr($c,$start,$end-$start);
$qc=replaceExactlyOnce($qc,
    "            'failed_item'=>['nullable','string','max:2000'],",
    "            'failed_item'=>['nullable','string','max:2000'],\n            'failed_rot_session_id'=>['nullable','integer','min:1'], // FIX47_QC_FAILED_ROT_SESSION",
    'validation');
$qc=replaceExactlyOnce($qc,
    "        DB::transaction(function() use(\$job,\$data,\$user){",
    "        if(\$data['result']==='FAIL' && trim((string)(\$data['failed_item'] ?? ''))===''){\n".
    "            throw ValidationException::withMessages(['failed_item'=>'Failed item / complaint is required on QC FAIL.']);\n".
    "        }\n\n".
    "        DB::transaction(function() use(\$job,\$data,\$user){",
    'fail description gate');
$qc=replaceExactlyOnce($qc,
    "            if(!empty(\$data['assigned_staff_id'])) \$this->activeStaff((int)\$data['assigned_staff_id']);",
    "            if(!empty(\$data['assigned_staff_id'])) \$this->activeStaff((int)\$data['assigned_staff_id']);\n".
    "            if(!empty(\$data['failed_rot_session_id'])){\n".
    "                \$linked=Schema::hasTable('rot_sessions') && DB::table('rot_sessions')\n".
    "                    ->where('id',(int)\$data['failed_rot_session_id'])\n".
    "                    ->where('job_card_id',\$job)->exists();\n".
    "                if(!\$linked)throw ValidationException::withMessages(['failed_rot_session_id'=>'Selected ROT does not belong to this Job Card.']);\n".
    "            }",
    'ROT ownership gate');
$qc=replaceExactlyOnce($qc,
    "                    'staff_id'=>\$data['assigned_staff_id'] ?? null,",
    "                    'staff_id'=>\$data['assigned_staff_id'] ?? null,\n".
    "                    'rot_session_id'=>\$data['failed_rot_session_id'] ?? null,",
    'failed items JSON');
$c=substr($c,0,$start).$qc.substr($c,$end);

$v=replaceExactlyOnce($v,
    '$amLifeStatus=strtolower(trim((string)$job->status));',
    '$amLifeStatus=strtolower(trim((string)$job->status));'."\n".
    '// FIX47_QC_FAILED_ROT_SESSION: show only current Job Card ROT sessions for QC failure linkage.'."\n".
    '$amLifeQcRotSessions=\Illuminate\Support\Facades\Schema::hasTable(\'rot_sessions\')'."\n".
    '    ? \Illuminate\Support\Facades\DB::table(\'rot_sessions\')->where(\'job_card_id\',$job->id)'."\n".
    '        ->orderByDesc(\'id\')->get([\'id\',\'rot_code\',\'staff_id\',\'status\'])'."\n".
    '    : collect();',
    'QC ROT list');
$v=replaceExactlyOnce($v,
    '              <label>Failed Item / Complaint<textarea name="failed_item" rows="2" required></textarea></label>',
    '              <label>Related ROT / Labour<select name="failed_rot_session_id"><option value="">Not linked / No matching ROT</option>@foreach($amLifeQcRotSessions as $rs)<option value="{{ $rs->id }}">{{ $rs->rot_code ?: (\'ROT #\'.$rs->id) }} — {{ $rs->status }} (Staff ID {{ $rs->staff_id ?: \'—\' }})</option>@endforeach</select></label>'."\n".
    '              <label>Failed Item / Complaint<textarea name="failed_item" rows="2" required></textarea></label>',
    'QC FAIL field');

if(file_put_contents($controller,$c)===false)throw new RuntimeException('Failed to write controller');
if(file_put_contents($panel,$v)===false)throw new RuntimeException('Failed to write lifecycle panel');
echo "FIX47 patch: PASS (QC FAIL links an owned ROT; no invoice/ledger change)\n";
