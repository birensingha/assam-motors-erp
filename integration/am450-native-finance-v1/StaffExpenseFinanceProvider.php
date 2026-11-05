<?php
declare(strict_types=1);

namespace AssamMotors\FinanceV1;

require_once __DIR__ . '/FinanceReadContract.php';

/**
 * AM458: functional, read-only Staff expense adapter for FinanceReadContract.
 *
 * The caller injects *authorized Laravel data loaders*. This class never
 * creates, changes, voids or adjusts a financial record. It refuses every
 * payee class/resource not yet reconciled to its source ledger.
 *
 * No outstanding values are inferred from the staff table or fabricated.
 */
final class StaffExpenseFinanceProvider implements FinanceReadProvider
{
    /**
     * @param \Closure(int):list<array<string,mixed>> $staffLoader
     * @param \Closure(string,int):list<array<string,mixed>> $expenseLoader
     * @param ?\Closure(string,string,int):list<array<string,mixed>> $masterLoader
     */
    public function __construct(
        private readonly \Closure $staffLoader,
        private readonly \Closure $expenseLoader,
        private readonly ?\Closure $masterLoader = null
    ) {}

    /** @return list<array<string,mixed>> */
    public function payees(string $type, int $limit): array
    {
        $this->staffOnly($type);
        self::validLimit($limit);
        $rows = ($this->staffLoader)($limit);
        if (!is_array($rows) || !array_is_list($rows) || count($rows) > $limit) {
            throw new FinanceContractFailure('Staff directory incomplete or oversized');
        }
        $normalized = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !self::positiveId($row['id'] ?? null)) {
                throw new FinanceContractFailure('Staff identity missing or invalid');
            }
            $name = $row['name'] ?? null;
            if (!is_string($name) || trim($name) === '') {
                throw new FinanceContractFailure('Staff name missing');
            }
            $normalized[] = [
                'id' => (string)$row['id'],
                'name' => trim($name),
                'type' => 'STAFF',
            ];
        }
        return $normalized;
    }

    /** @return list<array<string,mixed>> */
    public function outstanding(string $type, string $payeeId, int $limit): array
    {
        $this->staffOnly($type);
        self::validLimit($limit);
        if (!self::positiveId($payeeId)) {
            throw new FinanceContractFailure('Invalid selected Staff ID');
        }

        // The loader must use the already-audited Staff multi-expense ledger,
        // INCLUDING direct + multi allocations against ACTIVE payment vouchers.
        $rows = ($this->expenseLoader)($payeeId, $limit);
        if (!is_array($rows) || !array_is_list($rows) || count($rows) > $limit) {
            throw new FinanceContractFailure('Staff expenses require pagination');
        }
        $result = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !self::positiveId($row['id'] ?? null) ||
                (string)($row['staff_id'] ?? '') !== $payeeId) {
                throw new FinanceContractFailure('Cross-staff or invalid Expense reference');
            }
            $reference = $row['expense_no'] ?? null;
            if (!is_string($reference) || trim($reference) === '') {
                throw new FinanceContractFailure('Staff Expense voucher number missing');
            }
            $cents = self::moneyToCents($row['outstanding'] ?? null);
            if ($cents <= 0) {
                throw new FinanceContractFailure('Closed Expense present in outstanding list');
            }
            $result[] = [
                'id' => (string)$row['id'],
                'payee_id' => $payeeId,
                'payee_type' => 'STAFF',
                'reference_no' => trim($reference),
                'outstanding_amount' => self::centsToMoney($cents),
            ];
        }
        return $result;
    }

    /** @return array<string,mixed> */
    public function register(
        string $resource, string $query, int $limit, ?string $cursor
    ): array {
        throw new FinanceContractFailure(
            'Financial register is not reconciled to a verified ERP ledger'
        );
    }

    /** @return list<array<string,mixed>> */
    public function masters(string $type, string $query, int $limit): array
    {
        if (!in_array($type, ['PART', 'LABOUR', 'OSL'], true)) {
            throw new FinanceContractFailure('Unrecognized ERP Master type');
        }
        if ($limit < 1 || $limit > 40 || strlen($query) > 120) {
            throw new FinanceContractFailure('Invalid ERP Master query/limit');
        }
        if ($this->masterLoader === null) {
            throw new FinanceContractFailure('AM246 Master search has not been mapped');
        }
        $rows = ($this->masterLoader)($type, $query, $limit);
        if (!is_array($rows) || !array_is_list($rows) || count($rows) > $limit) {
            throw new FinanceContractFailure('ERP Master search incomplete/oversized');
        }
        $normalized = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !self::positiveId($row['id'] ?? null) ||
                ($row['type'] ?? null) !== $type ||
                !is_string($row['name'] ?? null) ||
                trim($row['name']) === '') {
                throw new FinanceContractFailure('Cross-type or invalid ERP Master');
            }
            $normalized[] = [
                'id' => (string)$row['id'],
                'type' => $type,
                'name' => trim($row['name']),
                'code' => is_string($row['code'] ?? null) ?
                    trim($row['code']) : '',
            ];
        }
        return $normalized;
    }

    /** @return array<string,mixed> */
    public function ledger(
        string $type, string $itemId, int $limit, ?string $cursor
    ): array {
        throw new FinanceContractFailure(
            'Master stock/OSL ledger snapshot is not ready for mobile API'
        );
    }

    private function staffOnly(string $type): void
    {
        if ($type !== 'STAFF') {
            throw new FinanceContractFailure(
                'This adapter currently supports verified Staff expenses only'
            );
        }
    }

    private static function validLimit(int $limit): void
    {
        if ($limit < 1 || $limit > 100) {
            throw new FinanceContractFailure('Invalid Staff finance page limit');
        }
    }

    private static function positiveId(mixed $id): bool
    {
        return (is_int($id) || is_string($id)) &&
            preg_match('/^[1-9][0-9]*$/D', (string)$id) === 1;
    }

    /**
     * Strict decimal-string parsing, never float math or silent rounding.
     * php string cast of a 2-decimal persisted amount remains acceptable.
     */
    public static function moneyToCents(mixed $amount): int
    {
        if (!is_string($amount) && !is_int($amount) && !is_float($amount)) {
            throw new FinanceContractFailure('Outstanding amount not numeric');
        }
        $value = trim((string)$amount);
        if (!preg_match('/^(0|[1-9][0-9]{0,11})(?:\.([0-9]{1,2}))?$/D', $value, $m)) {
            throw new FinanceContractFailure('Invalid INR decimal or precision');
        }
        return (int)$m[1] * 100 +
            (int)str_pad($m[2] ?? '', 2, '0', STR_PAD_RIGHT);
    }

    public static function centsToMoney(int $cents): string
    {
        if ($cents < 0) {
            throw new FinanceContractFailure('Negative payable not allowed');
        }
        return (string)intdiv($cents, 100) . '.' .
            str_pad((string)($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
