<?php
/**
 * FIX74: Replace old one-invoice-per-JC unique key with
 * one-ACTIVE-invoice-per-JC uniqueness, preserving reversed invoice history.
 * MariaDB 11.8 PERSISTENT generated column + unique index.
 *
 * A single ALTER TABLE performs all DDL as one atomic statement.
 * down() intentionally no-op: reimposing the old unique would
 * destroy/deny legitimate invoice history.
 */
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

return new class extends Migration {
 public function up(): void {
    if (!Schema::hasTable('invoices'))throw new RuntimeException('FIX74 invoices table missing.');
    $keys=collect(DB::select('SHOW INDEX FROM invoices'));
    $hasOriginal=$keys->contains(fn($x)=>(string)$x->Key_name==='uq_invoice_job'
       && (int)$x->Non_unique===0 && (string)$x->Column_name==='job_card_id');
    $hasNew=$keys->contains(fn($x)=>(string)$x->Key_name==='uq_invoice_active_job');
    if($hasNew && Schema::hasColumn('invoices','active_job_card_id')) {
       if($hasOriginal)throw new RuntimeException('FIX74 new and old uniqueness both exist: manual review');
       return;
    }
    if(!$hasOriginal || $hasNew || Schema::hasColumn('invoices','active_job_card_id')) {
       throw new RuntimeException('FIX74 unexpected invoices schema: abort, do not modify');
    }
    $duplicates=DB::table('invoices')->select('job_card_id')
     ->whereNotNull('job_card_id')
     ->whereRaw("LOWER(COALESCE(status,'')) <> 'reversed'")
     ->groupBy('job_card_id')->havingRaw('COUNT(*) > 1')->exists();
    if($duplicates)throw new RuntimeException('FIX74 multiple active invoices detected. No changes made.');
    // NULL on reversed invoices is intentionally excluded from a SQL UNIQUE index.
    // original job_card_id, invoice ID, reference No. and ledger are NEVER updated.
    DB::statement("ALTER TABLE invoices
      ADD COLUMN active_job_card_id INT GENERATED ALWAYS AS
        (CASE WHEN LOWER(COALESCE(status,'')) = 'reversed' THEN NULL ELSE job_card_id END) PERSISTENT,
      ADD UNIQUE INDEX uq_invoice_active_job (active_job_card_id),
      DROP INDEX uq_invoice_job,
      ADD INDEX idx_invoice_job_history (job_card_id)");
 }
 public function down(): void {
   // Deliberately non-reversible: after a corrected invoice, the historical
   // records have duplicate job_card_id and old index would invalidate them.
 }
};
