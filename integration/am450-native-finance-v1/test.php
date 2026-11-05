<?php

declare(strict_types=1);

require_once __DIR__ . '/FinanceReadContract.php';

use AssamMotors\FinanceV1\FinanceContractFailure;
use AssamMotors\FinanceV1\FinanceReadContract;
use AssamMotors\FinanceV1\FinanceReadProvider;

/**
 * AM433: Local, fake-data-only PHP regression suite.
 * Never connects to STAGING, executes SQL or inserts accounting records.
 */
final class StubFinanceProvider implements FinanceReadProvider
{
    public array $payeeRows = [
        ['id' => '42', 'name' => 'Example Staff', 'type' => 'STAFF'],
    ];
    public array $outstandingRows = [
        ['id' => '1001', 'payee_id' => '42', 'payee_type' => 'STAFF',
            'reference_no' => 'EXP-EXAMPLE-1', 'outstanding_amount' => '2000.00'],
        ['id' => '1002', 'payee_id' => '42', 'payee_type' => 'STAFF',
            'reference_no' => 'EXP-EXAMPLE-2', 'outstanding_amount' => '1942.00'],
    ];
    public array $registerPage = [
        'records' => [['id' => '11', 'reference_no' => 'PB-EXAMPLE-11']],
        'next_cursor' => '',
        'total_count' => 1,
    ];
    public array $masterRows = [
        ['id' => '10', 'type' => 'PART', 'name' => 'Example Part'],
    ];
    public array $ledgerPage = [
        'snapshot_id' => 'snap-example-1',
        'balances' => ['opening_qty' => '2.00', 'closing_qty' => '8.00'],
        'transactions' => [
            ['transaction_id' => 'tx-1', 'item_id' => '10', 'item_type' => 'PART',
                'source_ref' => 'PB-EXAMPLE-11', 'qty' => '10.00'],
        ],
        'next_cursor' => '',
    ];

    public function payees(string $type, int $limit): array
    {
        return $this->payeeRows;
    }

    public function outstanding(string $type, string $payeeId, int $limit): array
    {
        return $this->outstandingRows;
    }

    public function register(
        string $resource, string $query, int $limit, ?string $cursor
    ): array {
        return $this->registerPage;
    }

    public function masters(string $type, string $query, int $limit): array
    {
        return $this->masterRows;
    }

    public function ledger(
        string $type, string $itemId, int $limit, ?string $cursor
    ): array {
        return $this->ledgerPage;
    }
}

function shouldFail(callable $function, string $explanation): void
{
    try {
        $function();
        throw new LogicException('Expected finance contract failure: ' . $explanation);
    } catch (FinanceContractFailure $e) {
        // Expected: security gate rejected the malformed/unauthorized response.
    }
}

function confirm(bool $condition, string $message): void
{
    if (!$condition) {
        throw new LogicException('Finance regression failed: ' . $message);
    }
}

$provider = new StubFinanceProvider();
$read = new FinanceReadContract($provider);
$tests = 0;

shouldFail(fn() => $read->payees(false, 'STAFF'), 'deny non-admin'); $tests++;
shouldFail(fn() => $read->payees(true, 'CASHIER'), 'unsupported payee type'); $tests++;
confirm(count($read->payees(true, 'STAFF')['payees']) === 1, 'valid payee'); $tests++;
confirm(count($read->outstanding(true, 'STAFF', '42')['references']) === 2,
    'two Staff expense references'); $tests++;

$original = $provider->outstandingRows;
$provider->outstandingRows[0]['payee_id'] = '43';
shouldFail(fn() => $read->outstanding(true, 'STAFF', '42'), 'cross-staff leak'); $tests++;
$provider->outstandingRows = $original;
$provider->outstandingRows[0]['outstanding_amount'] = '0.00';
shouldFail(fn() => $read->outstanding(true, 'STAFF', '42'), 'closed/zero bill'); $tests++;
$provider->outstandingRows = $original;
$provider->outstandingRows[0]['outstanding_amount'] = '100.001';
shouldFail(fn() => $read->outstanding(true, 'STAFF', '42'), 'illegal fraction'); $tests++;
$provider->outstandingRows = $original;
$provider->outstandingRows[1]['id'] = $original[0]['id'];
shouldFail(fn() => $read->outstanding(true, 'STAFF', '42'), 'duplicate expense'); $tests++;
$provider->outstandingRows = $original;

confirm($read->register(true, 'purchase-bills', '', 30)['resource'] ===
    'purchase-bills', 'registered purchase endpoint'); $tests++;
$provider->registerPage['next_cursor'] = 'cursor123';
shouldFail(fn() => $read->register(true, 'purchase-bills', '', 30, 'cursor123'),
    'cursor replay'); $tests++;
$provider->registerPage['next_cursor'] = '';
shouldFail(fn() => $read->register(true, 'purchase-bills', '', 31),
    'oversized requested limit'); $tests++;

confirm(count($read->masters(true, 'PART', '')['items']) === 1,
    'valid Parts master'); $tests++;
$provider->masterRows[0]['type'] = 'OSL';
shouldFail(fn() => $read->masters(true, 'PART', ''), 'cross-item master'); $tests++;
$provider->masterRows[0]['type'] = 'PART';

confirm($read->ledger(true, 'PART', '10')['snapshot_id'] === 'snap-example-1',
    'valid immutable ledger snapshot'); $tests++;
$provider->ledgerPage['transactions'][0]['item_id'] = '11';
shouldFail(fn() => $read->ledger(true, 'PART', '10'), 'cross-item ledger entry'); $tests++;
$provider->ledgerPage['transactions'][0]['item_id'] = '10';
$provider->ledgerPage['transactions'][] =
    $provider->ledgerPage['transactions'][0];
shouldFail(fn() => $read->ledger(true, 'PART', '10'), 'replayed transaction'); $tests++;
array_pop($provider->ledgerPage['transactions']);
unset($provider->ledgerPage['snapshot_id']);
shouldFail(fn() => $read->ledger(true, 'PART', '10'), 'missing snapshot'); $tests++;

echo "AM433 FinanceV1ReadContract: PASS ($tests synthetic regression cases)\n";
