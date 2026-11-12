<?php
declare(strict_types=1);

/**
 * AM246 helper for Hostinger SSH shell dumper.
 * Produces a private MySQL option file and expected table count.
 * Never echoes credentials. No proc_open or shell functions.
 */
if (PHP_SAPI !== 'cli' || ($argc ?? 0) !== 3) {
    fwrite(STDERR, "STOP_AM246_DUMP_HELPER_CLI_ARGS\n"); exit(2);
}
$root = realpath((string)$argv[1]);
$tmp = realpath((string)$argv[2]);
if (!$root || basename($root) !== 'assam-erp-staging'
    || !is_file($root.'/artisan') || !$tmp
    || !str_starts_with($tmp.'/', $root.'/storage/app/am-backups/')) {
    fwrite(STDERR, "STOP_AM246_DUMP_HELPER_PATH_GUARD\n"); exit(2);
}
chdir($root);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$appUrl = (string)config('app.url', '');
if (!str_contains($appUrl, 'staging.assammotors.com')) {
    fwrite(STDERR, "STOP_AM246_DUMP_APP_NOT_STAGING\n"); exit(2);
}
$connection = (string)config('database.default');
$cfg = config('database.connections.'.$connection);
if (!is_array($cfg)
    || !in_array((string)($cfg['driver'] ?? ''), ['mysql','mariadb'], true)) {
    fwrite(STDERR, "STOP_AM246_DUMP_NOT_MYSQL\n"); exit(2);
}
$database = (string)($cfg['database'] ?? '');
$username = (string)($cfg['username'] ?? '');
$password = (string)($cfg['password'] ?? '');
$host = (string)($cfg['host'] ?? 'localhost');
$port = (string)($cfg['port'] ?? '3306');
$socket = (string)($cfg['unix_socket'] ?? '');

if (!preg_match('/^[A-Za-z0-9_]+$/D', $database)
    || $username === '' || $host === ''
    || !ctype_digit($port) || (int)$port<1 || (int)$port>65535) {
    fwrite(STDERR, "STOP_AM246_DUMP_DB_CONFIG_INVALID\n"); exit(2);
}
if (!\Illuminate\Support\Facades\Schema::hasTable('am_rot_work_complaints')) {
    fwrite(STDERR, "STOP_AM246_DUMP_COMPLAINTS_NOT_FOUND\n"); exit(2);
}
$r = \Illuminate\Support\Facades\DB::selectOne(
    "SELECT COUNT(*) AS total FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'"
);
$count = (int)($r->total ?? 0);
if ($count < 1) {
    fwrite(STDERR, "STOP_AM246_DUMP_EMPTY_SCHEMA\n"); exit(2);
}
$quote = static function(string $v): string {
    if (strpbrk($v, "\r\n\0") !== false) {
        throw new RuntimeException('Unsafe database config characters');
    }
    return '"'.str_replace(['\\','"'], ['\\\\','\\"'], $v).'"';
};
try {
    $options = "[client]\n";
    $options .= 'user='.$quote($username)."\n";
    $options .= 'password='.$quote($password)."\n";
    $options .= 'host='.$quote($host)."\n";
    $options .= 'port='.$quote($port)."\n";
    if ($socket !== '') $options .= 'socket='.$quote($socket)."\n";
    foreach ([
        '.client.cnf' => $options,
        'database_name' => $database."\n",
        'expected_tables' => (string)$count."\n",
    ] as $name => $value) {
        $p=$tmp.'/'.$name;
        if (file_put_contents($p,$value,LOCK_EX)===false) {
            throw new RuntimeException('Cannot write private dump configuration');
        }
        chmod($p,0600);
    }
} catch (Throwable $e) {
    fwrite(STDERR, "STOP_AM246_DUMP_HELPER_PRIVATE_CONFIG_FAILED\n"); exit(2);
}
echo "AM246_DUMP_SHELL_PRIVATE_CONFIGURATION_READY\n";
echo "AM246_DUMP_EXPECTED_BASE_TABLES ".$count."\n";
