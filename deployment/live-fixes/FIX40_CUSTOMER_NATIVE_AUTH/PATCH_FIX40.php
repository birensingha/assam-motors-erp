<?php
declare(strict_types=1);

$root=rtrim((string)($argv[1] ?? ''),'/');
if($root==='' || !is_file($root.'/artisan')){
    fwrite(STDERR,"Usage: php PATCH_FIX40.php /path/to/laravel/app\n");
    exit(2);
}

$legacy=$root.'/public/legacy/workshop/customers.php';
if(!is_file($legacy)) throw new RuntimeException('Missing legacy customers.php');

$c=file_get_contents($legacy);
$marker='FIX40_DIRECT_BROWSER_REDIRECT';

if(str_contains($c,$marker)){
    echo "FIX40 already installed\n";
    exit(0);
}

$open='<?php';
$pos=strpos($c,$open);
if($pos===false) throw new RuntimeException('PHP opening tag not found');

$insert=<<<'PHP'

/* FIX40_DIRECT_BROWSER_REDIRECT
 * Direct browser navigation belongs to the authenticated Laravel Customer Master.
 * Legacy ?action=... requests must continue below because workshop JavaScript still
 * uses this file as an internal JSON/API endpoint.
 */
$__amFix40Method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$__amFix40Action = trim((string)($_GET['action'] ?? ''));
if (in_array($__amFix40Method, ['GET','HEAD'], true) && $__amFix40Action === '') {
    header('Cache-Control: no-store, private');
    header('Location: /erp/customers', true, 302);
    exit;
}
unset($__amFix40Method, $__amFix40Action);

PHP;

$c=substr($c,0,$pos+strlen($open)).$insert.substr($c,$pos+strlen($open));
file_put_contents($legacy,$c);
echo "PASS direct-browser redirect guard patched\n";
