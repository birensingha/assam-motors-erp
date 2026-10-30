<?php
declare(strict_types=1);
/**
 * CLI-only secure MariaDB connection settings for external SSH shell dump.
 * No proc_open required. Writes ONLY a chmod 0600 temporary MySQL option file
 * under restricted STAGING storage; never prints passwords or actual data.
 */
$root=realpath((string)($argv[1]??''));
$out=(string)($argv[2]??'');
if(PHP_SAPI!=='cli'||!$root||basename($root)!=='assam-erp-staging'||!is_file($root.'/artisan')){
    fwrite(STDERR,"AM212_BACKUP_STOP_NOT_STAGING\n");exit(2);
}
$backups=realpath($root.'/storage/app/am-backups/AM212_ROT_WORK_PROCEDURE');
$dir=realpath(dirname($out));
if(!$backups||!$dir||$dir!==$backups||is_file($out)){
    fwrite(STDERR,"AM212_BACKUP_STOP_UNSAFE_CONFIG_DESTINATION\n");exit(2);
}
chdir($root);require $root.'/vendor/autoload.php';
$app=require $root.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
if(!str_contains((string)config('app.url',''),'staging.assammotors.com')){
    fwrite(STDERR,"AM212_BACKUP_STOP_APP_URL\n");exit(2);
}
$c=config('database.connections.'.config('database.default'));
if(!is_array($c)||!in_array((string)($c['driver']??''),['mysql','mariadb'],true)){
    fwrite(STDERR,"AM212_BACKUP_STOP_UNSUPPORTED_DB\n");exit(2);
}
$db=(string)($c['database']??'');
if($db==='' || !preg_match('/^[a-zA-Z0-9_$.-]+$/D',$db) || (string)($c['username']??'')===''){
    fwrite(STDERR,"AM212_BACKUP_STOP_DB_SETTINGS_INVALID\n");exit(2);
}
if(!Schema::hasTable('labour_master') ||
    (int)DB::table('labour_master')->where('rot_code','ROT-CUS-0211')->count()!==1){
    fwrite(STDERR,"AM212_BACKUP_STOP_TARGET_ROT_OR_TABLE\n");exit(2);
}
function quoteOption(string $x):string{
    if(str_contains($x,"\n")||str_contains($x,"\r")||str_contains($x,"\0")){
        throw new RuntimeException('Unsafe option file character');
    }
    return '"'.str_replace(['\\','"'],['\\\\','\\"'],$x).'"';
}
$lines=[
    '[client]',
    'user='.quoteOption((string)$c['username']),
    'password='.quoteOption((string)($c['password']??'')),
    'host='.quoteOption((string)($c['host']??'localhost')),
    'port='.(int)($c['port']??3306),
    'default-character-set=utf8mb4',
];
if(!empty($c['unix_socket']))$lines[]='socket='.quoteOption((string)$c['unix_socket']);
if(file_put_contents($out,implode("\n",$lines)."\n",LOCK_EX)===false){
    fwrite(STDERR,"AM212_BACKUP_STOP_CONFIG_WRITE\n");exit(2);
}
@chmod($out,0600);
if((fileperms($out)&077)!==0){
    @unlink($out);fwrite(STDERR,"AM212_BACKUP_STOP_CONFIG_PERMISSIONS\n");exit(2);
}
echo "AM212_BACKUP_TEMP_CONFIG_READY\n";
