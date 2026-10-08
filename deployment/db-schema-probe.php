<?php
/**
 * Assam Motors Laravel DB schema probe.
 *
 * READ ONLY:
 * - boots the existing Laravel application
 * - reads information_schema metadata only
 * - never prints DB credentials
 * - never reads application/business rows
 */

declare(strict_types=1);

$appRoot = $argv[1] ?? '';

if ($appRoot === '' || !is_dir($appRoot)) {
    fwrite(STDERR, "Usage: php db-schema-probe.php /path/to/staging/app\n");
    exit(2);
}

$appRoot = rtrim($appRoot, DIRECTORY_SEPARATOR);

$autoload = $appRoot . '/vendor/autoload.php';
$bootstrap = $appRoot . '/bootstrap/app.php';

if (!is_file($autoload) || !is_file($bootstrap)) {
    fwrite(STDERR, "Laravel vendor/autoload.php or bootstrap/app.php not found.\n");
    exit(2);
}

require $autoload;

$app = require $bootstrap;

try {
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();

    /** @var Illuminate\Database\Connection $connection */
    $connection = Illuminate\Support\Facades\DB::connection();
    $driver = (string) $connection->getDriverName();

    if (!in_array($driver, ['mysql', 'mariadb'], true)) {
        echo json_encode([
            'ok' => false,
            'driver' => $driver,
            'error' => 'Schema probe currently supports MySQL/MariaDB information_schema only.',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        exit(0);
    }

    $database = (string) $connection->getDatabaseName();

    $pattern = '(customer|vendor|supplier|part|item|inventory|labour|labor|vehicle|payment|voucher|job|card|invoice|estimate|purchase|rot|repair|reminder|alert|staff|attendance|location|booking|application|product|user)';

    $tables = $connection->select(
        "SELECT TABLE_NAME, ENGINE
           FROM information_schema.TABLES
          WHERE TABLE_SCHEMA = ?
            AND TABLE_NAME REGEXP ?
          ORDER BY TABLE_NAME",
        [$database, $pattern]
    );

    $columns = $connection->select(
        "SELECT TABLE_NAME,
                ORDINAL_POSITION,
                COLUMN_NAME,
                COLUMN_TYPE,
                IS_NULLABLE,
                COLUMN_KEY,
                EXTRA
           FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = ?
            AND TABLE_NAME REGEXP ?
          ORDER BY TABLE_NAME, ORDINAL_POSITION",
        [$database, $pattern]
    );

    $indexes = $connection->select(
        "SELECT TABLE_NAME,
                INDEX_NAME,
                NON_UNIQUE,
                SEQ_IN_INDEX,
                COLUMN_NAME
           FROM information_schema.STATISTICS
          WHERE TABLE_SCHEMA = ?
            AND TABLE_NAME REGEXP ?
          ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX",
        [$database, $pattern]
    );

    $result = [
        'ok' => true,
        'driver' => $driver,
        'database_name' => '<redacted>',
        'scope' => 'information_schema metadata only; no application rows read',
        'tables' => array_map(static function ($row): array {
            return [
                'table' => (string) $row->TABLE_NAME,
                'engine' => $row->ENGINE !== null ? (string) $row->ENGINE : null,
            ];
        }, $tables),
        'columns' => array_map(static function ($row): array {
            return [
                'table' => (string) $row->TABLE_NAME,
                'position' => (int) $row->ORDINAL_POSITION,
                'column' => (string) $row->COLUMN_NAME,
                'type' => (string) $row->COLUMN_TYPE,
                'nullable' => (string) $row->IS_NULLABLE,
                'key' => (string) $row->COLUMN_KEY,
                'extra' => (string) $row->EXTRA,
            ];
        }, $columns),
        'indexes' => array_map(static function ($row): array {
            return [
                'table' => (string) $row->TABLE_NAME,
                'index' => (string) $row->INDEX_NAME,
                'unique' => ((int) $row->NON_UNIQUE) === 0,
                'sequence' => (int) $row->SEQ_IN_INDEX,
                'column' => (string) $row->COLUMN_NAME,
            ];
        }, $indexes),
    ];

    echo json_encode(
        $result,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    ) . PHP_EOL;
} catch (Throwable $e) {
    echo json_encode([
        'ok' => false,
        'driver' => null,
        'error_class' => get_class($e),
        'error' => $e->getMessage(),
        'note' => 'No credentials were printed. Fix Laravel/DB bootstrapping before retrying.',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(1);
}
