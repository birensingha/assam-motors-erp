<?php
declare(strict_types=1);

require_once __DIR__.'/StaffExpenseFinanceProvider.php';

use AssamMotors\FinanceV1\FinanceContractFailure;
use AssamMotors\FinanceV1\FinanceReadContract;
use AssamMotors\FinanceV1\StaffExpenseFinanceProvider;

/** AM470: fake-only, read-only PART / LABOUR / OSL Master search contracts. */
$masters = [
    ['id' => 11, 'type' => 'PART', 'name' => 'Example Part', 'code' => 'PART-EX'],
];
$requestedType = '';
$requestedSearch = '';
$provider = new StaffExpenseFinanceProvider(
    static fn(int $limit): array => [],
    static fn(string $id, int $limit): array => [],
    static function(string $type, string $query, int $limit)
        use (&$masters, &$requestedType, &$requestedSearch): array {
        $requestedType = $type;
        $requestedSearch = $query;
        return $masters;
    }
);
$read = new FinanceReadContract($provider);
$tests = 0;

function passed(bool $condition, string $name): void {
    global $tests;
    if (!$condition) throw new RuntimeException('Master test FAIL: '.$name);
    $tests++;
}
function rejected(callable $fn, string $name): void {
    global $tests;
    try {$fn();}
    catch (FinanceContractFailure $e) {$tests++; return;}
    throw new RuntimeException('Expected Master reject: '.$name);
}

$parts = $read->masters(true, 'PART', 'Example');
passed($parts['resource'] === 'master-items', 'native resource envelope');
passed($parts['items'][0]['type'] === 'PART', 'typed Part returned');
passed($parts['items'][0]['id'] === '11', 'numeric master ID normalized');
passed($parts['items'][0]['code'] === 'PART-EX', 'correct master code');
passed($requestedType === 'PART' && $requestedSearch === 'Example',
    'server search arguments forwarded');
rejected(fn()=> $read->masters(false, 'PART', ''), 'non-admin cannot see items');
rejected(fn()=> $read->masters(true, 'CHEMICAL', ''), 'arbitrary type forbidden');
rejected(fn()=> $read->masters(true, 'PART', '', 41), 'oversize page rejected');

$original = $masters;
$masters[0]['type'] = 'OSL';
rejected(fn()=> $read->masters(true, 'PART', ''), 'no cross-type Part/OSL leak');
$masters = $original;
$masters[0]['id'] = '0';
rejected(fn()=> $read->masters(true, 'PART', ''), 'invalid master ID');
$masters = $original;
$masters[0]['name'] = '';
rejected(fn()=> $read->masters(true, 'PART', ''), 'missing master name');
$masters = $original;
$masters[] = $original[0];
rejected(fn()=> $read->masters(true, 'PART', ''), 'duplicate master ID');
$masters = $original;
$masters = array_fill(0, 41, $original[0]);
rejected(fn()=> $read->masters(true, 'PART', ''), 'too many masters');
$masters = $original;

$masters = [['id'=>'6','type'=>'LABOUR','name'=>'Example ROT','code'=>'ROT-EX']];
passed($read->masters(true,'LABOUR','ROT')['items'][0]['code']==='ROT-EX',
    'Labour/ROT master search available');
$masters = [['id'=>'7','type'=>'OSL','name'=>'Example Outside Service','code'=>'OSL-EX']];
passed($read->masters(true,'OSL','Outside')['items'][0]['type']==='OSL',
    'OSL master search available');
rejected(fn()=> $read->ledger(true,'OSL','7'),
    'ledger remains fail-closed pending verified stock/OSL snapshots');

echo "AM470 Native Master Search PASS ($tests synthetic checks)\n";
