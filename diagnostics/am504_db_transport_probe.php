<?php
/**
 * AM504: read-only MySQL transport probe for an existing Laravel installation.
 * Run from deployed Laravel root. Two short-lived connections, SELECT 1 only.
 * Never prints DB host, database name, username, password, queries or stack traces.
 */
declare(strict_types=1);

if (!is_file(getcwd().'/artisan') || !is_file(getcwd().'/vendor/autoload.php')) {
    echo "AM504: Run from Laravel application root.\n";
    exit(2);
}
try {
    require_once getcwd().'/vendor/autoload.php';
    $app = require getcwd().'/bootstrap/app.php';
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $connectionName = (string) config('database.default', '');
    $db = config('database.connections.'.$connectionName);
    if (!is_array($db) || !in_array($db['driver'] ?? '', ['mysql','mariadb'], true)
        || !empty($db['url']) || !empty($db['unix_socket'])) {
        echo "AM504: Nonstandard DB transport; manual review needed.\n";
        exit(2);
    }
    $database = (string)($db['database'] ?? '');
    $username = (string)($db['username'] ?? '');
    $password = (string)($db['password'] ?? '');
    $port = (int)($db['port'] ?? 3306);
    if ($database === '' || $username === '' || $port < 1 || $port > 65535) {
        echo "AM504: Database config incomplete.\n";
        exit(2);
    }
} catch (\Throwable $error) {
    echo "AM504: Laravel bootstrap unavailable.\n";
    exit(2);
}
function am504_reason(\Throwable $e): string {
    $s = strtolower($e->getMessage());
    foreach ([
        'operation not permitted'=>'operation_not_permitted',
        'no such file or directory'=>'socket_file_missing',
        'connection refused'=>'connection_refused',
        'timed out'=>'connection_timed_out',
        'access denied'=>'authentication_denied',
        'too many connections'=>'too_many_connections',
        'php_network_getaddresses'=>'dns_failed',
    ] as $text=>$reason) if (str_contains($s, $text)) return $reason;
    return 'other_connection_failure';
}
foreach (['localhost'=>'implicit_socket', '127.0.0.1'=>'tcp_loopback'] as $host=>$label) {
    try {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database);
        $pdo = new \PDO($dsn, $username, $password, [
            \PDO::ATTR_TIMEOUT=>4,
            \PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_PERSISTENT=>false,
        ]);
        $statement = $pdo->query('SELECT 1');
        echo $label, ': ', ($statement && $statement->fetchColumn() == 1 ? 'OK' : 'read_test_failed'), "\n";
        $statement = null;
        $pdo = null;
    } catch (\Throwable $e) {
        echo $label, ': ', am504_reason($e), "\n";
    }
    usleep(1000000);
}
echo "AM504: done (read-only; configuration unchanged)\n";
