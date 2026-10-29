<?php

namespace App\Http\Controllers\Web;

use App\Services\VendorReferenceGuard;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class AdminVendorSearchDeleteController extends Controller
{
    private const TABLE = 'workshop_vendors';
    private const AUDIT = 'workshop_vendor_master_audits';

    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) > 80) {
            return response()->json(['error' => 'Search term is too long'], 422);
        }
        $fields = Schema::getColumnListing(self::TABLE);
        $searchColumns = array_values(array_intersect(
            ['vendor_name', 'vendor_code', 'mobile', 'gstin'], $fields
        ));
        $query = DB::table(self::TABLE)->select(
            array_values(array_intersect(['id','vendor_name','vendor_code','mobile'], $fields))
        );
        if ($q !== '' && $searchColumns) {
            $pattern = '%' . addcslashes($q, '%_\\') . '%';
            $query->where(function ($nested) use ($searchColumns, $pattern): void {
                foreach ($searchColumns as $column) {
                    $nested->orWhere($column, 'like', $pattern);
                }
            });
        }
        $rows = $query->orderBy('vendor_name')->limit(25)->get();
        return response()->json([
            'results' => $rows->map(static fn ($v) => [
                'id' => (int) $v->id,
                'name' => (string) $v->vendor_name,
                'code' => (string) ($v->vendor_code ?? ''),
                'mobile' => (string) ($v->mobile ?? ''),
            ])->all(),
        ]);
    }

    public function destroy(Request $request, int $vendor): RedirectResponse
    {
        $values = $request->validate([
            'reason' => ['required','string','min:10','max:500'],
            'confirmation' => ['required','in:DELETE'],
        ]);
        abort_unless(Schema::hasTable(self::TABLE) && Schema::hasTable(self::AUDIT),
            503, 'Vendor Master audit is unavailable.');

        try {
            DB::transaction(function () use ($vendor, $values): void {
                $entry = DB::table(self::TABLE)
                    ->where('id', $vendor)->lockForUpdate()->first();
                if (!$entry) {
                    throw ValidationException::withMessages([
                        'delete' => 'Vendor no longer exists.',
                    ]);
                }

                // Recheck references under the vendor row lock.
                // Historical VOID/CANCELLED transactions are still references.
                $check = VendorReferenceGuard::inspect($entry);
                if (!$check['allowed']) {
                    throw ValidationException::withMessages([
                        'delete' => 'Cannot delete this vendor: records exist in '
                            . implode(', ', array_slice($check['blockers'], 0, 5))
                            . '. Vendor transaction history must be preserved.',
                    ]);
                }

                $actor = Auth::user();
                // Audit full vendor snapshot before hard deletion; audit row
                // survives because it has no FK cascade to workshop_vendors.
                DB::table(self::AUDIT)->insert([
                    'vendor_id' => $vendor,
                    'action' => 'VENDOR_MASTER_DELETE_UNUSED',
                    'before_json' => json_encode((array) $entry,
                        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
                    'after_json' => null,
                    'reason' => trim($values['reason']),
                    'actor_id' => $actor?->id,
                    'actor_name' => (string) ($actor?->name ?? 'ERP Admin'),
                    'created_at' => now(),
                ]);
                $deleted = DB::table(self::TABLE)->where('id', $vendor)->delete();
                if ($deleted !== 1) {
                    throw new \RuntimeException('Vendor deletion failed. No record changed.');
                }
            }, 3);
        } catch (QueryException $e) {
            // If a newly inserted FK reference raced this operation, the DB
            // rejects deletion. Never bypass referential integrity.
            report($e);
            throw ValidationException::withMessages([
                'delete' => 'Vendor has linked records or a database restriction. Deletion cancelled.',
            ]);
        }

        return redirect()->route('erp.vendors.master.manage')
            ->with('success', 'Unused vendor deleted. Audit record retained.');
    }
}
