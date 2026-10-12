<?php
/** ASSAM MOTORS native HR credential bridge: staging only; unique existing Staff, no password reset. */
declare(strict_types=1);
function stopHr(string $s,int $code=1): never { fwrite($code?STDERR:STDOUT,$s.PHP_EOL);exit($code); }
$mode=$argv[1]??''; if (!in_array($mode,['check','apply'],true))stopHr('Usage: php LINK_EXISTING_HR.php check|apply');
$root=rtrim((string)getenv('HOME'),'/').'/domains/assammotors.com/assam-erp-staging';
if(realpath($root)!==$root || !is_file($root.'/artisan')) stopHr('NO-GO: staging Laravel target missing');
require $root.'/vendor/autoload.php';
$app=require $root.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db=\Illuminate\Support\Facades\DB::class;
$schema=\Illuminate\Support\Facades\Schema::class;
foreach(['staff','erp_auth_users','hr_access_grants','hr_audit_logs'] as $table)if(!$schema::hasTable($table))stopHr('NO-GO: HR schema missing '.$table);
$findStaff=static fn()=> $db::table('staff')
    ->whereRaw('LOWER(TRIM(role))=?',['hr manager'])
    ->whereRaw('LOWER(TRIM(department))=?',['human resources (hr)'])
    ->whereRaw('LOWER(TRIM(status))=?',['active']);
$rows=$findStaff()->get(['id','name','email','password']);
if($rows->count()!==1)stopHr('NO-GO: require exactly one designated active HR Manager staff account');
$s=$rows->first();
$mail=strtolower(trim((string)$s->email));
if(!filter_var($mail,FILTER_VALIDATE_EMAIL))stopHr('NO-GO: HR staff email invalid');
$pass=(string)$s->password;
if(!in_array(password_get_info($pass)['algoName']??'', ['bcrypt','argon2i','argon2id'], true))stopHr('NO-GO: existing HR password cannot be securely reused');
if($db::table('erp_auth_users')->whereRaw('LOWER(TRIM(email))=?',[$mail])->count()!==0)stopHr('NO-GO: auth email already exists; no overwrite');
if($db::table('erp_auth_users')->where('staff_id',(int)$s->id)->count()!==0)stopHr('NO-GO: staff ID already linked; no duplicate auth');
$guard=$root.'/app/Support/HrAccess.php';
$before=(string)file_get_contents($guard);
$anchor=<<<'BLOCK'
            if (!$staffEmail || !hash_equals($staffEmail, strtolower(trim((string) $user->email)))) {
                return false;
            }
BLOCK;
$after=$anchor."\n".<<<'BLOCK'
            // A later Legacy Staff password change invalidates any stale Laravel HR credentials.
            if (!hash_equals((string) ($s->password ?? ''), (string) $user->password)) {
                return false;
            }
BLOCK;
if(substr_count($before,$anchor)!==1 || str_contains($before,'stale Laravel HR credentials'))stopHr('NO-GO: HR authorization guard changed; refuse unsafe patch');
$next=str_replace($anchor,$after,$before);
try { token_get_all($next,TOKEN_PARSE); }catch(\Throwable $e){stopHr('NO-GO: guard parse failed');}
echo 'HR IDENTITY PREFLIGHT PASS: one existing HR staff; no duplicate Laravel email; secure hash compatible.'.PHP_EOL;
if($mode==='check')stopHr('READ ONLY PASS. No staff, password, access or database records modified.',0);
$backup=$root.'/storage/app/hr_identity_guard_'.date('Ymd_His').'_'.getmypid().'.php.bak';
if(!is_dir(dirname($backup)) || file_put_contents($backup,$before)===false)stopHr('NO-GO: guard backup failed');
$temp=tempnam(dirname($guard),'.hrguard_');
if(!$temp || file_put_contents($temp,$next)!==strlen($next))stopHr('NO-GO: cannot stage new guard');
chmod($temp,fileperms($guard)&0777);
if(hash_file('sha256',$guard)!==hash('sha256',$before) || !rename($temp,$guard))stopHr('NO-GO: guard concurrently changed');
try {
    $db::transaction(function ()use($db,$findStaff,$mail,$pass,$s) {
        $current=$findStaff()->lockForUpdate()->get(['id','name','email','password']);
        if($current->count()!==1)throw new \RuntimeException('HR staff changed during transaction');
        $one=$current->first();
        if((int)$one->id!==(int)$s->id || !hash_equals($pass,(string)$one->password) || strtolower(trim($one->email))!==$mail)throw new \RuntimeException('HR source credential changed');
        if($db::table('erp_auth_users')->whereRaw('LOWER(TRIM(email))=?',[$mail])->lockForUpdate()->exists())throw new \RuntimeException('Email linked concurrently');
        if($db::table('erp_auth_users')->where('staff_id',(int)$s->id)->lockForUpdate()->exists())throw new \RuntimeException('Staff linked concurrently');
        $db::table('erp_auth_users')->insert([
            'name'=>mb_substr((string)$s->name,0,150),'email'=>$mail,'password'=>$pass,
            'role'=>'staff','staff_id'=>(int)$s->id,'active'=>1,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        $db::table('hr_access_grants')->insert([
            'staff_id'=>(int)$s->id,'active'=>1,
            'grant_note'=>'Unique HR staff verified; password hash copy invalidated on future legacy password change',
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        $db::table('hr_audit_logs')->insert([
            'staff_id'=>(int)$s->id,'action'=>'HR_IDENTITY_LINKED_AND_GRANTED',
            'subject_type'=>'staff','subject_id'=>(int)$s->id,
            'details'=>json_encode(['source'=>'guarded_automated_staging','legacy_password_change_revokes_access'=>true]),
            'created_at'=>now(),
        ]);
    });
}catch(\Throwable $e){
    copy($backup,$guard);
    stopHr('NO-GO: DB transaction rolled back, original guard restored: '.$e->getMessage());
}
echo 'HR STAGING IDENTITY LINK + GRANT: PASS. Original Staff record and password unchanged; sensitive values not logged.'.PHP_EOL;