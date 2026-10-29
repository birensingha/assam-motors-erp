<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Vendor cannot be hard-deleted if any business history references the vendor.
 * Check identifiers across live database columns and legacy name-based ledgers.
 * Fail closed if reference inspection cannot be completed.
 */
final class VendorReferenceGuard
{
    private const ID_COLUMNS = [
        'vendor_id', 'workshop_vendor_id', 'vendor_master_id',
        'payee_vendor_id', 'supplier_vendor_id',
    ];

    private const LEGACY_NAME_COLUMNS = [
        'workshop_purchases' => ['vendor', 'vendor_name'],
        'workshop_purchase_batches' => ['vendor', 'vendor_name'],
        'workshop_payment_vouchers' => ['payee_name'],
        'workshop_vendor_payments' => ['vendor_name'],
        'purchases' => ['vendor', 'vendor_name', 'supplier_name'],
        'purchase_batches' => ['vendor', 'vendor_name'],
        'vendor_ledger' => ['vendor_name'],
        'vendor_ledgers' => ['vendor_name'],
        'osl_purchases' => ['vendor', 'vendor_name'],
    ];

    /**
     * @return array{allowed: bool, blockers: array<int, string>}
     */
    public static function inspect(object $vendor): array
    {
        $id = (int) $vendor->id;
        $blocked = [];
        // Some legacy transactions retained the vendor name rather than ID.
        // Include prior names from audit history to prevent name-change bypass.
        $names = [];
        foreach (['vendor_name', 'vendor_code'] as $field) {
            $name = trim((string) ($vendor->{$field} ?? ''));
            if ($name !== '') $names[] = $name;
        }
        $history = DB::table('workshop_vendor_master_audits')
            ->where('vendor_id', $id)->limit(201)->get(['before_json', 'after_json']);
        if ($history->count() > 200) {
            return ['allowed' => false, 'blockers' => ['Vendor audit history review required']];
        }
        foreach ($history as $event) {
            foreach ([$event->before_json, $event->after_json] as $json) {
                $details = json_decode((string) $json, true);
                if (is_array($details) && isset($details['vendor_name'])) {
                    $name = trim((string) $details['vendor_name']);
                    if ($name !== '') $names[] = $name;
                }
            }
        }
        $names = array_values(array_unique($names));

        $schema = DB::select(
            "SELECT TABLE_NAME, COLUMN_NAME
               FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND COLUMN_NAME IN ('vendor_id','workshop_vendor_id',
                                    'vendor_master_id','payee_vendor_id',
                                    'supplier_vendor_id')"
        );
        $visited = [];
        foreach ($schema as $ref) {
            $table = (string) $ref->TABLE_NAME;
            $field = (string) $ref->COLUMN_NAME;
            if (in_array($table, ['workshop_vendors',
                    'workshop_vendor_master_audits', 'migrations'], true)) continue;
            $visited[$table] ??= [];
            $visited[$table][] = $field;
        }

        // Also discover FK columns pointing at workshop_vendors, even if they
        // use a non-standard identifier name.
        $fks = DB::select(
            "SELECT TABLE_NAME, COLUMN_NAME
               FROM information_schema.KEY_COLUMN_USAGE
              WHERE TABLE_SCHEMA = DATABASE()
                AND REFERENCED_TABLE_SCHEMA = DATABASE()
                AND REFERENCED_TABLE_NAME = 'workshop_vendors'"
        );
        foreach ($fks as $fk) {
            $table = (string) $fk->TABLE_NAME;
            if ($table === 'workshop_vendor_master_audits') continue;
            $visited[$table][] = (string) $fk->COLUMN_NAME;
        }

        foreach ($visited as $table => $columns) {
            foreach (array_unique($columns) as $column) {
                if (DB::table($table)->where($column, $id)->exists()) {
                    $blocked[$table] = $table;
                    break;
                }
            }
        }

        foreach (self::LEGACY_NAME_COLUMNS as $table => $candidates) {
            if (!$names) break;
            $foundTable = DB::selectOne(
                "SELECT COUNT(*) AS c FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?", [$table]
            );
            if (!$foundTable || (int) $foundTable->c === 0) continue;

            $columns = DB::select(
                "SELECT COLUMN_NAME FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?", [$table]
            );
            $present = array_map(fn ($x) => (string) $x->COLUMN_NAME, $columns);
            foreach ($candidates as $field) {
                if (!in_array($field, $present, true)) continue;
                $query = DB::table($table)->whereIn($field, $names);
                // A generic payee_name may reference customers or staff.
                // Check the payee type when available.
                if ($table === 'workshop_payment_vouchers'
                    && $field === 'payee_name'
                    && in_array('payee_type', $present, true)) {
                    $query->whereRaw('LOWER(payee_type) IN (?, ?, ?)',
                        ['vendor','supplier','workshop_vendor']);
                }
                if ($query->exists()) {
                    $blocked[$table] = $table;
                    break;
                }
            }
        }

        return ['allowed' => !$blocked, 'blockers' => array_values($blocked)];
    }
}
