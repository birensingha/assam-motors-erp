<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * AM193: Confirm a JC has not entered actual workshop work/financial activity.
 *
 * Safe-to-remove child records are administrative records, inspections and
 * purely PLANNED parts/labour. Everything unrecognized FAILS CLOSED.
 * All child rows are archived by controller before deletion.
 */
final class JobCardDeleteGuard
{
    private const INSPECTION_TABLES = [
        'job_card_inspection', 'job_card_inspection_assignments',
        'job_card_inspection_items', 'job_card_inspection_photos',
        'job_card_inspection_reports', 'job_card_inspection_results',
        'job_card_inspection_checks', 'job_card_inspection_checklist',
        'job_card_change_audits',
    ];

    private const PLANNED_TABLES = ['job_card_parts', 'job_card_labour'];

    private const DISALLOWED_STATUSES = [
        'wip', 'work_in_progress', 'work in progress',
        'in_progress', 'in progress', 'started', 'running',
        'work_completed', 'work completed', 'completed',
        'qc_pending', 'qc pending', 'qc_passed', 'qc passed',
        'ready_for_delivery', 'ready for delivery',
        'closed', 'invoiced', 'cancelled', 'canceled',
        'deleted', 'financial_reopened',
    ];

    private static function present(object $row, string $key): bool
    {
        $value = $row->{$key} ?? null;
        return $value !== null && trim((string) $value) !== '' &&
            !in_array(strtolower(trim((string) $value)), ['0','false','no','none'],true);
    }

    private static function guardRow(object $row): array
    {
        $block = [];
        $status = strtolower(trim((string) ($row->status ?? '')));
        if (in_array($status, self::DISALLOWED_STATUSES, true)) {
            $block[] = "Job Card lifecycle is $status";
        }
        foreach ([
            'work_started_at','wip_started_at','work_start_at',
            'job_started_at','work_completed_at','qc_passed_at',
            'ready_for_delivery_at','closed_at','invoiced_at',
            'invoice_id','invoice_no','invoice_number',
            'deleted_at','deleted_by',
        ] as $column) {
            if (self::present($row,$column)) $block[] = "JC has $column";
        }
        return $block;
    }

    private static function guardPlanned(string $table, array $records): array
    {
        $block = [];
        foreach ($records as $line) {
            $status = strtolower(trim((string) (
                $line->execution_status ?? $line->issue_status ?? $line->status ?? 'planned'
            )));
            if (!in_array($status,[
                '', 'planned','pending','assigned','not started','not_started',
                'not issued','not_issued','draft','unassigned',
            ],true)) {
                $block[] = "$table has a non-planned status ($status)";
                break;
            }
            $start = $table === 'job_card_labour'
                ? ['rot_start','rot_end','started_at','completed_at','start_time',
                   'end_time','actual_start','actual_end','first_started_at',
                   'productive_seconds','elapsed_seconds','total_seconds',
                   'actual_hours','total_hours','time_spent']
                : ['issued_at','fitted_at','installed_at','issue_at',
                   'issued_qty','qty_issued','fitted_qty','qty_fitted',
                   'quantity_issued','stock_movement_id','stock_issue_id'];
            foreach ($start as $field) {
                if (self::present($line,$field)) {
                    $block[] = "$table has started work/stock ($field)";
                    break 2;
                }
            }
        }
        return $block;
    }

    /** @return array{blockers:array,children:array} */
    public static function inspect(object $job): array
    {
        $jobId = (int)$job->id;
        $blockers = self::guardRow($job);
        $children = [];

        // Discover every explicit JC-ID reference, including real foreign keys.
        // No known transaction, credit note, parts issue, payment or other
        // unknown child table is ever auto-deleted.
        $rows = DB::select(
            "SELECT TABLE_NAME, COLUMN_NAME
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA=DATABASE()
               AND COLUMN_NAME IN
                 ('job_card_id','jobcard_id','jc_id','workshop_job_card_id')"
        );
        $fk = DB::select(
            "SELECT TABLE_NAME, COLUMN_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA=DATABASE()
               AND REFERENCED_TABLE_SCHEMA=DATABASE()
               AND REFERENCED_TABLE_NAME='job_cards'"
        );
        $columns = [];
        foreach (array_merge($rows,$fk) as $entry) {
            $table = (string)$entry->TABLE_NAME;
            $field = (string)$entry->COLUMN_NAME;
            if (in_array($table,[
                'job_cards','am193_deleted_job_card_audits'
            ],true)) continue;
            $columns[$table][$field] = true;
        }
        $allowed = array_merge(self::INSPECTION_TABLES,self::PLANNED_TABLES);
        foreach ($columns as $table=>$fields) {
            foreach (array_keys($fields) as $field) {
                if (!in_array($field,[
                    'job_card_id','jobcard_id','jc_id','workshop_job_card_id'
                ],true) && !preg_match('/^[a-z_][a-z0-9_]*$/i',$field)) {
                    throw new \RuntimeException('Unsafe child column');
                }
                $entries = DB::table($table)->where($field,$jobId)->limit(1001)->get();
                if (!$entries->count()) continue;
                if ($entries->count()>1000) {
                    $blockers[] = "$table has too many linked rows to archive safely";
                    continue;
                }
                if (!in_array($table,$allowed,true)) {
                    $blockers[] = "$table contains linked records";
                    continue;
                }
                if (isset($children[$table])) {
                    // Same table with multiple JC reference fields: block rather
                    // than risk deleting a different job's records.
                    $blockers[] = "$table has multiple JC reference fields";
                    continue;
                }
                $array = $entries->all();
                if (in_array($table,self::PLANNED_TABLES,true)) {
                    $blockers = array_merge($blockers,self::guardPlanned($table,$array));
                }
                $children[$table] = ['column'=>$field,'rows'=>array_map(
                    fn($x)=>(array)$x,$array
                )];
            }
        }

        // Check name/number references used by older / legacy modules.
        $jobNumbers = array_values(array_unique(array_filter(array_map(
            fn($field)=>trim((string)($job->{$field} ?? '')),
            ['job_card_no','job_no','job_card_number','jc_no']
        ))));
        if ($jobNumbers) {
            $legacy = DB::select(
                "SELECT TABLE_NAME,COLUMN_NAME
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA=DATABASE()
                   AND COLUMN_NAME IN ('job_card_no','job_no','job_card_number','jc_no')"
            );
            foreach ($legacy as $ref) {
                $table=(string)$ref->TABLE_NAME;
                $field=(string)$ref->COLUMN_NAME;
                if ($table==='job_cards' || $table==='am193_deleted_job_card_audits'
                    || isset($children[$table])) continue;
                if (DB::table($table)->whereIn($field,$jobNumbers)->exists()) {
                    $blockers[]="$table contains linked JC-number records";
                }
            }
        }

        // A job must never be erased with ROT sessions, stock movements,
        // invoices, credit notes or purchases. They are not on the whitelist.
        return ['blockers'=>array_values(array_unique($blockers)),
                'children'=>$children];
    }
}
