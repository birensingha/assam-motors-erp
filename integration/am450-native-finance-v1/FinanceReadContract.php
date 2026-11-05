<?php

declare(strict_types=1);

namespace AssamMotors\FinanceV1;

/**
 * AM432: read-only Laravel integration boundary.
 *
 * THIS IS NOT A LIVE ERP DB ADAPTER. Wire an actual transactionally consistent
 * Laravel service ONLY after mapping existing Assam Motors ledgers, permissions
 * and tenant/company scopes. Never return sample/sample-zero financial data.
 */
interface FinanceReadProvider
{
    /** @return list<array<string,mixed>> */
    public function payees(string $type, int $limit): array;

    /** @return list<array<string,mixed>> */
    public function outstanding(string $type, string $payeeId, int $limit): array;

    /** @return array{snapshot_id:string,records:list<array<string,mixed>>,next_cursor?:string,total_count?:int} */
    public function register(string $resource, string $query, int $limit, ?string $cursor): array;

    /** @return list<array<string,mixed>> */
    public function masters(string $type, string $query, int $limit): array;

    /** @return array{snapshot_id:string,balances:array<string,mixed>,transactions:list<array<string,mixed>>,next_cursor?:string} */
    public function ledger(string $type, string $itemId, int $limit, ?string $cursor): array;
}

final class FinanceContractFailure extends \RuntimeException
{
}

/**
 * Strictly validates read-side server responses before they can reach Android.
 * Caller MUST obtain $verifiedAdmin from Laravel Gate/policy on the server.
 * Never accept a role string, checkbox or flag from an Android request.
 */
final class FinanceReadContract
{
    public const CONTRACT = 'am-finance-v1';

    private const PAYEE_TYPES = ['VENDOR', 'OSL_VENDOR', 'CUSTOMER', 'STAFF'];
    private const MASTER_TYPES = ['PART', 'LABOUR', 'OSL'];
    private const REGISTERS = ['purchase-bills', 'expenses', 'vendors', 'estimates'];

    public function __construct(private readonly FinanceReadProvider $provider)
    {
    }

    /** @return array<string,mixed> */
    public function payees(bool $verifiedAdmin, string $type): array
    {
        $this->admin($verifiedAdmin);
        self::allowed($type, self::PAYEE_TYPES);
        $data = $this->provider->payees($type, 100);
        self::limit($data, 100);
        self::unique($data, 'id');
        foreach ($data as $record) {
            self::row($record);
            self::positiveId($record['id'] ?? null);
            self::nonblank($record['name'] ?? null);
            if (($record['type'] ?? '') !== $type) {
                throw new FinanceContractFailure('Cross-payee-type data refused');
            }
        }
        return ['contract' => self::CONTRACT, 'payees' => $data];
    }

    /** @return array<string,mixed> */
    public function outstanding(bool $verifiedAdmin, string $type, string $payeeId): array
    {
        $this->admin($verifiedAdmin);
        self::allowed($type, self::PAYEE_TYPES);
        self::positiveId($payeeId);
        $rows = $this->provider->outstanding($type, $payeeId, 100);
        self::limit($rows, 100);
        self::unique($rows, 'id');
        foreach ($rows as $row) {
            self::row($row);
            self::nonblank($row['id'] ?? null);
            self::nonblank($row['reference_no'] ?? null);
            if ((string) ($row['payee_id'] ?? '') !== $payeeId ||
                ($row['payee_type'] ?? '') !== $type) {
                throw new FinanceContractFailure('Cross-payee outstanding reference refused');
            }
            $value = $row['outstanding_amount'] ?? null;
            if (!is_string($value) ||
                !preg_match('/^(?:0|[1-9][0-9]{0,14})\.[0-9]{2}$/D', $value) ||
                $value === '0.00') {
                throw new FinanceContractFailure('Invalid outstanding DECIMAL INR');
            }
        }
        return ['contract' => self::CONTRACT, 'references' => $rows];
    }

    /** @return array<string,mixed> */
    public function register(
        bool $verifiedAdmin, string $resource, string $query,
        int $limit = 30, ?string $cursor = null
    ): array {
        $this->admin($verifiedAdmin);
        self::allowed($resource, self::REGISTERS);
        self::query($query);
        self::page($limit, $cursor, 30);
        $result = $this->provider->register($resource, $query, $limit, $cursor);
        self::nonblank($result['snapshot_id'] ?? null);
        $rows = $result['records'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new FinanceContractFailure('Register rows missing');
        }
        self::limit($rows, $limit);
        self::unique($rows, 'id');
        $next = self::nextCursor($result, $cursor);
        if (array_key_exists('total_count', $result) &&
            (!is_int($result['total_count']) || $result['total_count'] < count($rows))) {
            throw new FinanceContractFailure('Register total_count invalid');
        }
        return ['contract' => self::CONTRACT, 'resource' => $resource,
            'snapshot_id' => (string) $result['snapshot_id'],
            'records' => $rows, 'next_cursor' => $next] +
            (isset($result['total_count']) ? ['total_count' => $result['total_count']] : []);
    }

    /** @return array<string,mixed> */
    public function masters(
        bool $verifiedAdmin, string $type, string $query, int $limit = 40
    ): array {
        $this->admin($verifiedAdmin);
        self::allowed($type, self::MASTER_TYPES);
        self::query($query);
        self::page($limit, null, 40);
        $rows = $this->provider->masters($type, $query, $limit);
        self::limit($rows, $limit);
        self::unique($rows, 'id');
        foreach ($rows as $row) {
            self::row($row);
            self::nonblank($row['id'] ?? null);
            self::nonblank($row['name'] ?? null);
            if (($row['type'] ?? '') !== $type) {
                throw new FinanceContractFailure('Cross-item master leaked');
            }
        }
        return ['contract' => self::CONTRACT, 'resource' => 'master-items', 'items' => $rows];
    }

    /** @return array<string,mixed> */
    public function ledger(
        bool $verifiedAdmin, string $type, string $itemId,
        int $limit = 50, ?string $cursor = null
    ): array {
        $this->admin($verifiedAdmin);
        self::allowed($type, self::MASTER_TYPES);
        self::nonblank($itemId);
        self::page($limit, $cursor, 50);
        $result = $this->provider->ledger($type, $itemId, $limit, $cursor);
        self::nonblank($result['snapshot_id'] ?? null);
        if (!is_array($result['balances'] ?? null)) {
            throw new FinanceContractFailure('Missing authoritative ledger balances');
        }
        $rows = $result['transactions'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new FinanceContractFailure('Ledger transactions must be an array');
        }
        self::limit($rows, $limit);
        self::unique($rows, 'transaction_id');
        foreach ($rows as $row) {
            self::row($row);
            self::nonblank($row['source_ref'] ?? null);
            if ((string) ($row['item_id'] ?? '') !== $itemId ||
                ($row['item_type'] ?? '') !== $type) {
                throw new FinanceContractFailure('Cross-item transaction refused');
            }
        }
        return ['contract' => self::CONTRACT, 'resource' => 'master-item-ledger',
            'item_id' => $itemId, 'item_type' => $type,
            'snapshot_id' => (string) $result['snapshot_id'],
            'balances' => $result['balances'], 'transactions' => $rows,
            'next_cursor' => self::nextCursor($result, $cursor)];
    }

    private function admin(bool $verifiedAdmin): void
    {
        if (!$verifiedAdmin) {
            throw new FinanceContractFailure('Forbidden: authenticated ERP Admin required');
        }
    }

    /** @param list<string> $allowed */
    private static function allowed(string $value, array $allowed): void
    {
        if (!in_array($value, $allowed, true)) {
            throw new FinanceContractFailure('Invalid finance resource/type');
        }
    }

    private static function positiveId(mixed $id): void
    {
        if ((!is_string($id) && !is_int($id)) ||
            !preg_match('/^[1-9][0-9]*$/D', (string) $id)) {
            throw new FinanceContractFailure('Invalid ERP numeric entity ID');
        }
    }

    private static function nonblank(mixed $value): void
    {
        if (!is_string($value) && !is_int($value)) {
            throw new FinanceContractFailure('Missing required ERP identity');
        }
        if (trim((string) $value) === '') {
            throw new FinanceContractFailure('Blank required ERP identity');
        }
    }

    private static function query(string $value): void
    {
        if (strlen($value) > 200) {
            throw new FinanceContractFailure('Search query too long');
        }
    }

    private static function page(int $limit, ?string $cursor, int $max): void
    {
        if ($limit < 1 || $limit > $max || ($cursor !== null && strlen($cursor) > 512)) {
            throw new FinanceContractFailure('Invalid page size/cursor');
        }
    }

    /** @param array<string,mixed> $result */
    private static function nextCursor(array $result, ?string $current): string
    {
        $next = $result['next_cursor'] ?? '';
        if (!is_string($next) || strlen($next) > 512 ||
            ($next !== '' && $next === $current)) {
            throw new FinanceContractFailure('Invalid/replayed ERP cursor');
        }
        return $next;
    }

    /** @param array<mixed> $rows */
    private static function limit(array $rows, int $max): void
    {
        if (!array_is_list($rows) || count($rows) > $max) {
            throw new FinanceContractFailure('ERP returned an oversized/non-list page');
        }
    }

    private static function row(mixed $value): void
    {
        if (!is_array($value)) {
            throw new FinanceContractFailure('ERP record is not an object');
        }
    }

    /** @param array<mixed> $rows */
    private static function unique(array $rows, string $key): void
    {
        $ids = [];
        foreach ($rows as $row) {
            self::row($row);
            self::nonblank($row[$key] ?? null);
            $id = (string) $row[$key];
            if (isset($ids[$id])) {
                throw new FinanceContractFailure('Duplicate ERP record/transaction ID');
            }
            $ids[$id] = true;
        }
    }
}
