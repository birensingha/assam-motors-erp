<?php
/**
 * AM178: Copy historical PUBLIC hiring applications missing from NEW ERP.
 *
 * READ ONLY: php import.php check LARAVEL_STAGING_ROOT
 * INSERT ONLY: php import.php apply LARAVEL_STAGING_ROOT
 *
 * Two existing applicants (or any already-matching applicant) are preserved.
 * NEVER overwrites records/status/CVs. Preserves original public application IDs,
 * application timestamps and statuses; no emails are sent.
 * Does not print PII, database credentials, application data or resume URLs.
 *
 * Requires CLI; DO NOT put this file under public_html. No external library.
 */
declare(strict_types=1);
ini_set('display_errors', 'stderr');
error_reporting(E_ALL);

$mode = $argv[1] ?? '';
$root = realpath($argv[2] ?? '');
if (!in_array($mode, ['check', 'apply'], true) || !$root || !is_file($root . '/artisan')) {
    fwrite(STDERR, "Usage: php import.php check|apply LARAVEL_STAGING_ROOT\n");
    exit(2);
}
$domain = dirname($root);
$config = $domain . '/public_html/api/config.php';
if (!is_file($config) || !is_file($root . '/vendor/autoload.php')) {
    fwrite(STDERR, "AM178_STOP_REQUIRED_SOURCE_MISSING\n");
    exit(2);
}

function am178Key(array $row, bool $loose=false): string {
    $phone = preg_replace('/\D+/', '', (string) ($row['phone'] ?? ''));
    if (strlen($phone) >= 10) {
        $phone = substr($phone, -10);
    }
    $name = mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) ($row['name'] ?? ''))), 'UTF-8');
    $position = mb_strtolower(trim((string) ($row['position'] ?? '')), 'UTF-8');
    return hash('sha256', json_encode($loose ? [$phone, $position] : [$phone, $name, $position], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
}
function am178Open(PDO $p, string $side): array {
    $schema = $p->query('SHOW COLUMNS FROM job_applications')->fetchAll(PDO::FETCH_ASSOC);
    $cols = array_column($schema, 'Field');
    $required = [
        'id','name','phone','email','position','qualification','experience',
        'address','resume_url','message','status','created_at'
    ];
    if (array_diff($required, $cols)) {
        throw new RuntimeException($side . '_SCHEMA_INCOMPATIBLE');
    }
    $id = array_values(array_filter($schema, static fn($col) => $col['Field'] === 'id'));
    if (count($id) !== 1 || !str_contains(strtolower((string) $id[0]['Extra']), 'auto_increment')) {
        throw new RuntimeException($side . '_ID_NOT_AUTO_INCREMENT');
    }
    $stmt = $p->query('SELECT id,name,phone,email,position,qualification,experience,address,resume_url,message,status,created_at FROM job_applications ORDER BY id');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($rows) > 5000) {
        throw new RuntimeException($side . '_ROW_LIMIT_EXCEEDED');
    }
    return $rows;
}
function am178Plan(array $public, array $erp): array {
    $existing = [];
    $loose = [];
    $matched = 0;
    $statusDiscrepancy = 0;
    $publicGroups = [];
    foreach ($erp as $row) {
        $k = am178Key($row);
        $existing[$k] = $row;
        $loose[am178Key($row, true)] = true;
    }
    $missing = [];
    $similar = 0;
    $idCollisions = 0;
    $erpById = [];
    foreach ($erp as $row) { $erpById[(int) $row['id']] = am178Key($row); }
    foreach ($public as $row) {
        $key = am178Key($row);
        $publicGroups[$key] = ($publicGroups[$key] ?? 0) + 1;
        if (isset($existing[$key])) {
            $matched++;
            if (strtolower(trim((string) $existing[$key]['status'])) !==
                strtolower(trim((string) $row['status']))) {
                $statusDiscrepancy++;
            }
            continue;
        }
        if (isset($loose[am178Key($row,true)])) {
            $similar++;
        }
        if (isset($erpById[(int) $row['id']]) &&
            $erpById[(int) $row['id']] !== $key) {
            $idCollisions++;
        }
        $missing[] = $row;
    }
    $duplicateGroups = count(array_filter($publicGroups, static fn($count) => $count > 1));
    return [
        'missing'=>$missing,'matched'=>$matched,'similar'=>$similar,
        'duplicates'=>$duplicateGroups,'idCollisions'=>$idCollisions,
        'statusDiscrepancy'=>$statusDiscrepancy
    ];
}

try {
    // This shared config sets HTTP headers; invoking it in a CLI diagnostic
    // should not emit header warnings or assume a web REQUEST_METHOD exists.
    // Keep the config unchanged for the live website.
    $_SERVER['REQUEST_METHOD'] ??= 'GET';
    ob_start();
    try {
        require_once $config;
    } finally {
        ob_end_clean();
    }
    echo "AM178_HIRING_PUBLIC_TO_NEW_ERP\n";
    if (!function_exists('getDB')) {
        throw new RuntimeException('PUBLIC_GETDB_UNAVAILABLE');
    }
    $public = getDB();
    if (!$public instanceof PDO) {
        throw new RuntimeException('PUBLIC_NOT_PDO');
    }
    require_once $root . '/vendor/autoload.php';
    $app = require $root . '/bootstrap/app.php';
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $erp = $app->make('db')->connection()->getPdo();
    // Source routes/web.php had no literal hiring route in the read-only
    // scan; inspect *runtime* Laravel route names without modifying routes.
    $routeNames = array_keys($app->make('router')->getRoutes()->getRoutesByName());
    $jobRoutes = array_values(array_filter($routeNames, static fn($name) =>
        str_contains(strtolower((string) $name), 'job-applications')
    ));
    echo 'ERP_HIRING_NAMED_ROUTES ', count($jobRoutes), "\n";
    if (!$jobRoutes) {
        echo "ERP_HIRING_ROUTE_WARNING_CHECK_HR_ADMIN_MENU\n";
    }
    if (!$erp instanceof PDO) {
        throw new RuntimeException('ERP_NOT_PDO');
    }
    $public->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $erp->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Do not allow source and destination to resolve to the same DB.
    $pDb = $public->query('SELECT DATABASE()')->fetchColumn();
    $eDb = $erp->query('SELECT DATABASE()')->fetchColumn();
    if (!is_string($pDb) || !is_string($eDb) || $pDb === '' || $eDb === '') {
        throw new RuntimeException('DATABASE_IDENTITY_UNAVAILABLE');
    }
    $pubIdentity = $public->query('SELECT @@hostname,@@port')->fetch(PDO::FETCH_NUM);
    $erpIdentity = $erp->query('SELECT @@hostname,@@port')->fetch(PDO::FETCH_NUM);
    if ($pDb === $eDb && $pubIdentity === $erpIdentity) {
        throw new RuntimeException('SAME_DATABASE_NO_MIGRATION_REQUIRED');
    }
    $sourceRows = am178Open($public, 'PUBLIC');
    $destRows = am178Open($erp, 'ERP');
    $plan = am178Plan($sourceRows, $destRows);
    echo 'PUBLIC_COUNT ', count($sourceRows), "\n";
    echo 'NEW_ERP_BEFORE_COUNT ', count($destRows), "\n";
    echo 'EXACT_MATCHES_PRESERVED ', $plan['matched'], "\n";
    echo 'MISSING_CANDIDATES ', count($plan['missing']), "\n";
    echo 'POTENTIAL_SIMILAR_APPLICANTS ', $plan['similar'], "\n";
    echo 'SOURCE_DUPLICATE_GROUPS ', $plan['duplicates'], "\n";
    echo 'MISSING_SOURCE_ID_COLLISIONS ', $plan['idCollisions'], "\n";
    echo 'EXISTING_STATUS_DIFFERENCES_PRESERVED ', $plan['statusDiscrepancy'], "\n";

    if ($plan['similar'] || $plan['duplicates'] || $plan['idCollisions']) {
        throw new RuntimeException('AMBIGUOUS_APPLICANTS_MANUAL_REVIEW_REQUIRED');
    }
    if ($mode === 'check') {
        echo "AM178_CHECK_PASS_NO_WRITES\n";
        exit(0);
    }

    // This lock only serializes AM178 imports. A single transaction makes
    // all inserts atomic; the plan is recalculated after acquiring the lock.
    $lock = $erp->prepare('SELECT GET_LOCK(?, 15)');
    $lock->execute(['am178_hiring_history_import']);
    if ((int) $lock->fetchColumn() !== 1) {
        throw new RuntimeException('IMPORT_LOCK_UNAVAILABLE');
    }
    $erp->beginTransaction();
    try {
        $latest = am178Open($erp, 'ERP');
        $fresh = am178Plan($sourceRows, $latest);
        if ($fresh['similar'] || $fresh['duplicates'] || $fresh['idCollisions']) {
            throw new RuntimeException('AMBIGUOUS_APPLICANTS_AFTER_RECHECK');
        }
        $insert = $erp->prepare(
            'INSERT INTO job_applications ' .
            '(id,name,phone,email,position,qualification,experience,address,resume_url,message,status,created_at) ' .
            'VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $imported = 0;
        foreach ($fresh['missing'] as $row) {
            $insert->execute([
                $row['id'], $row['name'], $row['phone'], $row['email'], $row['position'],
                $row['qualification'], $row['experience'], $row['address'],
                $row['resume_url'], $row['message'], $row['status'], $row['created_at']
            ]);
            $imported++;
        }
        $erp->commit();
        echo 'AM178_IMPORTED_COUNT ', $imported, "\n";
    } catch (Throwable $e) {
        if ($erp->inTransaction()) {
            $erp->rollBack();
        }
        throw $e;
    } finally {
        $unlock = $erp->prepare('SELECT RELEASE_LOCK(?)');
        $unlock->execute(['am178_hiring_history_import']);
    }
    $finalRows = am178Open($erp, 'ERP');
    echo 'NEW_ERP_AFTER_COUNT ', count($finalRows), "\n";
    $still = am178Plan($sourceRows, $finalRows);
    echo 'REMAINING_MISSING_AFTER_IMPORT ', count($still['missing']), "\n";
    if ($still['missing']) {
        throw new RuntimeException('POST_IMPORT_MISMATCH');
    }
    echo "AM178_IMPORT_PASS_NO_EXISTING_APPLICATIONS_OVERWRITTEN\n";
} catch (Throwable $e) {
    // Never expose passwords, exception traces, emails, CV URLs or SQL values.
    fwrite(STDERR, "AM178_STOP_" . preg_replace('/[^A-Z0-9_]/', '_', strtoupper(
        $e instanceof RuntimeException ? $e->getMessage() : get_class($e)
    )) . "\n");
    exit(1);
}
