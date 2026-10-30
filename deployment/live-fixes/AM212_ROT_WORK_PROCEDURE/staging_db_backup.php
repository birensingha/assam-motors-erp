<?php
declare(strict_types=1);
/**
 * AM212 one-time full STAGING MariaDB snapshot prior to additive migration.
 * Dump stays under Laravel storage/app/am-backups and is chmod 0600.
 * This is an export/integrity check, not a destructive restore test.
 * No DB user/password/record contents are printed or sent to GitHub.
 */
if (PHP_SAPI !== 'cli') { fwrite(STDERR,"AM212_BACKUP_STOP_CLI_REQUIRED\n"); exit(2); }
$root=realpath((string)($argv[1]??''));
if(!$root || basename($root)!=='assam-erp-staging' || !is_file($root.'/artisan')){
    fwrite(STDERR,"AM212_BACKUP_STOP_NOT_STAGING\n");exit(2);
}
chdir($root);
require $root.'/vendor/autoload.php';
$app=require $root.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
$appUrl=(string)config('app.url','');
if(!str_contains($appUrl,'staging.assammotors.com')){
    fwrite(STDERR,"AM212_BACKUP_STOP_APP_URL_NOT_STAGING\n");exit(2);
}
$connection=(string)config('database.default');
$cfg=config('database.connections.'.$connection);
if(!is_array($cfg)||!in_array((string)($cfg['driver']??''),['mysql','mariadb'],true)){
    fwrite(STDERR,"AM212_BACKUP_STOP_NOT_MYSQL_MARIADB\n");exit(2);
}
$database=(string)($cfg['database']??'');
$user=(string)($cfg['username']??'');
$pw=(string)($cfg['password']??'');
$host=(string)($cfg['host']??'localhost');
$port=(string)($cfg['port']??'3306');
if($database===''||$user===''){
    fwrite(STDERR,"AM212_BACKUP_STOP_DB_CONNECTION_INCOMPLETE\n");exit(2);
}
if(!Schema::hasTable('labour_master')){
    fwrite(STDERR,"AM212_BACKUP_STOP_LABOUR_MASTER_MISSING\n");exit(2);
}
if(DB::table('labour_master')->where('rot_code','ROT-CUS-0211')->count()!==1){
    fwrite(STDERR,"AM212_BACKUP_STOP_TARGET_ROT_MISSING_OR_DUPLICATE\n");exit(2);
}
$bin='';
foreach(['/usr/bin/mariadb-dump','/usr/bin/mysqldump','/usr/local/bin/mariadb-dump','/usr/local/bin/mysqldump','/bin/mysqldump'] as $path){
    if(is_file($path)&&is_executable($path)){$bin=$path;break;}
}
if($bin===''){
    fwrite(STDERR,"AM212_BACKUP_STOP_MYSQLDUMP_NOT_AVAILABLE\n");exit(2);
}
if(!function_exists('proc_open')){
    fwrite(STDERR,"AM212_BACKUP_STOP_PROC_OPEN_DISABLED\n");exit(2);
}
$base=$root.'/storage/app/am-backups/AM212_ROT_WORK_PROCEDURE';
if(!is_dir($base)&&!mkdir($base,0700,true)){
    fwrite(STDERR,"AM212_BACKUP_STOP_PRIVATE_DIR_UNAVAILABLE\n");exit(2);
}
@chmod($base,0700);
$stamp=gmdate('Ymd_His');
$destination=$base.'/staging_before_am212_'.$stamp.'_'.getmypid().'.sql';
$partial=$destination.'.partial';
$cmd=[$bin,'--single-transaction','--quick','--skip-lock-tables',
      '--default-character-set=utf8mb4','--host='.$host,'--port='.$port,'--user='.$user];
if(!empty($cfg['unix_socket']))$cmd[]='--socket='.(string)$cfg['unix_socket'];
$cmd[]=$database;
// Keep password out of command arguments, script output and backup path.
$env=getenv();
if(!is_array($env))$env=[];
$env['MYSQL_PWD']=$pw;
$desc=[
    0=>['pipe','r'],
    1=>['file',$partial,'w'],
    2=>['pipe','w'],
];
$process=@proc_open($cmd,$desc,$pipes,$root,$env,['bypass_shell'=>true]);
if(!is_resource($process)){
    fwrite(STDERR,"AM212_BACKUP_STOP_DUMP_PROCESS_NOT_STARTED\n");exit(2);
}
fclose($pipes[0]);
$stderr=stream_get_contents($pipes[2]);fclose($pipes[2]);
$status=proc_close($process);
@chmod($partial,0600);
if($status!==0){
    @unlink($partial);
    // Don't expose command output that might contain a password or DB host.
    fwrite(STDERR,"AM212_BACKUP_STOP_MYSQLDUMP_EXIT_".$status."\n");exit(2);
}
if(!is_file($partial)||filesize($partial)<4096){
    @unlink($partial);
    fwrite(STDERR,"AM212_BACKUP_STOP_DUMP_TOO_SMALL\n");exit(2);
}
$schemaTables=(int)DB::selectOne(
    'SELECT COUNT(*) AS count_tables FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = ?',
    ['BASE TABLE']
)->count_tables;
$found=0;
$fp=fopen($partial,'rb');
if(!$fp){@unlink($partial);fwrite(STDERR,"AM212_BACKUP_STOP_DUMP_UNREADABLE\n");exit(2);}
while(($line=fgets($fp))!==false){
    if(str_starts_with($line,'CREATE TABLE '))$found++;
}
fclose($fp);
if($schemaTables<1 || $found<$schemaTables){
    @unlink($partial);
    fwrite(STDERR,"AM212_BACKUP_STOP_DUMP_SCHEMA_INCOMPLETE\n");exit(2);
}
if(!rename($partial,$destination)){
    @unlink($partial);
    fwrite(STDERR,"AM212_BACKUP_STOP_ATOMIC_RENAME\n");exit(2);
}
@chmod($destination,0600);
$hash=hash_file('sha256',$destination);
if(!$hash){
    fwrite(STDERR,"AM212_BACKUP_STOP_CHECKSUM_FAILED\n");exit(2);
}
// Log only backup metadata; retain SQL and its hash in private server storage.
$manifest=$destination.'.sha256';
file_put_contents($manifest,$hash."  ".basename($destination)."\n",LOCK_EX);
@chmod($manifest,0600);
echo "AM212_FRESH_STAGING_DB_BACKUP_EXPORT_VERIFIED\n";
echo "AM212_BACKUP_TABLES ".$found."\n";
echo "AM212_BACKUP_BYTES ".filesize($destination)."\n";
echo "AM212_BACKUP_PRIVATE_FILE ".$destination."\n";
echo "AM212_BACKUP_RESTORE_NOT_TESTED\n";
echo "AM212_BACKUP_NO_DB_CHANGES\n";
