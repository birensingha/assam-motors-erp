<?php
declare(strict_types=1);

require_once __DIR__ . '/StaffExpenseFinanceProvider.php';

use AssamMotors\FinanceV1\FinanceContractFailure;
use AssamMotors\FinanceV1\FinanceReadContract;
use AssamMotors\FinanceV1\StaffExpenseFinanceProvider;

/**
 * AM459: deterministic, synthetic-only read-model regression.
 * Absolutely no Laravel bootstrap, SQL, real Staff records or financial writes.
 */
$staff = [['id' => 42, 'name' => 'Example Mechanic']];
$expenses = [
    ['id' => 101, 'staff_id' => 42, 'expense_no' => 'EXAMPLE-A', 'outstanding' => '2000.00'],
    ['id' => 102, 'staff_id' => 42, 'expense_no' => 'EXAMPLE-B', 'outstanding' => '1942.00'],
];
$provider = new StaffExpenseFinanceProvider(
    static function(int $limit) use (&$staff): array { return $staff; },
    static function(string $id, int $limit) use (&$expenses): array { return $expenses; }
);
$finance = new FinanceReadContract($provider);
$tests = 0;

function mustPass(bool $condition, string $label): void {
    global $tests;
    if (!$condition) throw new LogicException('FAIL: '.$label);
    ++$tests;
}
function mustReject(callable $fn, string $label): void {
    global $tests;
    try { $fn(); }
    catch (FinanceContractFailure $e) { ++$tests; return; }
    throw new LogicException('Expected rejection: '.$label);
}

mustReject(fn()=> $finance->payees(false, 'STAFF'), 'not an Admin');
mustReject(fn()=> $finance->payees(true, 'VENDOR'), 'unsupported Vendor adapter');
mustPass(($finance->payees(true, 'STAFF')['payees'][0]['name'] ?? '') ===
    'Example Mechanic', 'Staff payee identity');

$good = $finance->outstanding(true, 'STAFF', '42')['references'];
mustPass(count($good) === 2, 'two outstanding Staff expenses');
mustPass($good[0]['outstanding_amount'] === '2000.00', 'exact first amount');
mustPass($good[1]['outstanding_amount'] === '1942.00', 'exact second amount');
mustPass($good[0]['payee_id'] === '42' &&
    $good[0]['payee_type'] === 'STAFF', 'scoped Staff references');

mustReject(fn()=> $finance->outstanding(true, 'STAFF', '43'), 'cross-staff leak');
mustReject(fn()=> $finance->outstanding(true, 'OSL_VENDOR', '42'), 'unsupported OSL vendor');
mustReject(fn()=> $finance->outstanding(true, 'STAFF', '-2'), 'invalid Staff identity');
mustReject(fn()=> $finance->register(true, 'expenses', ''), 'unmapped Expense register');
mustReject(fn()=> $finance->masters(true, 'PART', ''), 'unmapped master');
mustReject(fn()=> $finance->ledger(true, 'OSL', '5'), 'unmapped OSL ledger');

$original = $expenses;
$expenses[0]['staff_id'] = 43;
mustReject(fn()=> $finance->outstanding(true, 'STAFF', '42'),
    'malformed source cross-staff expense');
$expenses = $original;
$expenses[0]['outstanding'] = '-9.99';
mustReject(fn()=> $finance->outstanding(true, 'STAFF', '42'), 'negative payable');
$expenses = $original;
$expenses[0]['outstanding'] = '2000.001';
mustReject(fn()=> $finance->outstanding(true, 'STAFF', '42'), '3 decimal places');
$expenses = $original;
$expenses[0]['outstanding'] = '0.00';
mustReject(fn()=> $finance->outstanding(true, 'STAFF', '42'), 'paid expense');
$expenses = $original;
$expenses[1]['id'] = 101;
mustReject(fn()=> $finance->outstanding(true, 'STAFF', '42'), 'duplicate expense');
$expenses = $original;
$expenses[1]['expense_no'] = '';
mustReject(fn()=> $finance->outstanding(true, 'STAFF', '42'), 'missing bill number');
$expenses = $original;

$staff = [['id'=>42, 'name'=>'Example Mechanic'], ['id'=>42,'name'=>'Example Duplicate']];
mustReject(fn()=> $finance->payees(true, 'STAFF'), 'duplicate Staff ID');
$staff = [['id'=>42, 'name'=>'Example Mechanic']];
$expenses = array_fill(0, 101, $original[0]);
mustReject(fn()=> $finance->outstanding(true, 'STAFF', '42'),
    'incomplete 100-expense capped list');
$expenses = $original;

mustPass(StaffExpenseFinanceProvider::moneyToCents('3942.00') === 394200,
    '₹3942 in cents');
mustPass(StaffExpenseFinanceProvider::moneyToCents('0.9') === 90,
    'one decimal padded correctly');
mustPass(StaffExpenseFinanceProvider::centsToMoney(394200) === '3942.00',
    'exact cents roundtrip');
mustReject(fn()=> StaffExpenseFinanceProvider::moneyToCents('1e3'),
    'scientific notation disallowed');
mustReject(fn()=> StaffExpenseFinanceProvider::moneyToCents('100.123'),
    'no silent rounding');
mustReject(fn()=> StaffExpenseFinanceProvider::centsToMoney(-1),
    'negative outstanding display');

echo "AM459 FinanceStaffReadProvider PASS ($tests synthetic regression checks)\n";
