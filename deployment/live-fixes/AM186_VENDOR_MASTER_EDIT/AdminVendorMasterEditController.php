<?php

namespace App\Http\Controllers\Web;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class AdminVendorMasterEditController extends Controller
{
    private const TABLE = 'workshop_vendors';
    private const AUDIT = 'workshop_vendor_master_audits';

    /** Only permitted master fields; never permit id/vendor_code/financial edits. */
    private const EDITABLE = [
        'vendor_name' => ['Vendor Name', 190],
        'mobile' => ['Mobile', 20],
        'email' => ['Email', 190],
        'gstin' => ['GSTIN', 15],
        'address' => ['Address', 500],
        'address_line1' => ['Address Line 1', 250],
        'address_line2' => ['Address Line 2', 250],
        'city' => ['City', 120],
        'state' => ['State', 120],
        'state_code' => ['State Code', 10],
        'pincode' => ['PIN Code', 10],
        'pin_code' => ['PIN Code', 10],
        'contact_person' => ['Contact Person', 150],
        'contact_name' => ['Contact Person', 150],
        'phone' => ['Alternate Phone', 20],
        'pan' => ['PAN', 10],
        'bank_name' => ['Bank Name', 190],
        'bank_account' => ['Bank Account', 50],
        'bank_account_no' => ['Bank Account', 50],
        'account_holder' => ['Account Holder', 190],
        'ifsc' => ['IFSC', 11],
        'ifsc_code' => ['IFSC', 11],
        'notes' => ['Notes', 1000],
        'remarks' => ['Remarks', 1000],
    ];

    private function editableFields(): array
    {
        $columns = Schema::getColumnListing(self::TABLE);
        $allowed = [];
        foreach (self::EDITABLE as $key => [$label, $limit]) {
            if (in_array($key, $columns, true)) {
                $allowed[$key] = ['label' => $label, 'limit' => $limit];
            }
        }
        return $allowed;
    }

    public function index(Request $request): View
    {
        abort_unless(Schema::hasTable(self::TABLE), 503, 'Vendor Master unavailable');
        $fields = $this->editableFields();
        $q = trim((string) $request->query('q', ''));
        $query = DB::table(self::TABLE);
        if ($q !== '') {
            $terms = array_values(array_intersect(
                ['vendor_name', 'vendor_code', 'mobile', 'gstin'],
                Schema::getColumnListing(self::TABLE)
            ));
            $query->where(function ($sub) use ($terms, $q): void {
                foreach ($terms as $field) {
                    $sub->orWhere($field, 'like', '%' . addcslashes($q, '%_\\') . '%');
                }
            });
        }
        $vendors = $query->orderBy('vendor_name')->paginate(30)->withQueryString();
        $selected = null;
        if ($request->filled('edit')) {
            $id = filter_var($request->query('edit'), FILTER_VALIDATE_INT);
            abort_unless($id && $id > 0, 404);
            $selected = DB::table(self::TABLE)->where('id', $id)->first();
            abort_unless($selected, 404, 'Vendor not found');
        }
        return view('vendors.master-edit', compact('vendors', 'fields', 'selected', 'q'));
    }

    public function update(Request $request, int $vendor): RedirectResponse
    {
        abort_unless(Schema::hasTable(self::AUDIT), 503,
            'Vendor audit table missing. Please run AM186 migration.');
        abort_unless(Schema::hasTable(self::TABLE), 503, 'Vendor Master unavailable');

        $fields = $this->editableFields();
        abort_unless(isset($fields['vendor_name']), 503, 'Vendor name field missing');
        $rules = [
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ];
        foreach ($fields as $name => $meta) {
            if ($request->exists($name)) {
                $rules[$name] = [
                    $name === 'vendor_name' ? 'required' : 'nullable',
                    'string',
                    'max:' . $meta['limit'],
                ];
                if ($name === 'email') $rules[$name][] = 'email';
                if ($name === 'gstin') $rules[$name][] = 'regex:/^[0-9A-Z]{15}$/';
                if ($name === 'pan') $rules[$name][] = 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/';
                if (in_array($name, ['ifsc', 'ifsc_code'], true)) {
                    $rules[$name][] = 'regex:/^[A-Z]{4}0[A-Z0-9]{6}$/';
                }
            }
        }
        $normalized = $request->all();
        foreach (['gstin', 'pan', 'ifsc', 'ifsc_code'] as $name) {
            if (isset($normalized[$name]) && is_string($normalized[$name])) {
                $normalized[$name] = strtoupper(trim($normalized[$name]));
            }
        }
        $data = Validator::make($normalized, $rules)->validate();
        $reason = trim((string) $data['reason']);
        unset($data['reason']);

        DB::transaction(function () use ($vendor, $data, $reason): void {
            $old = DB::table(self::TABLE)->where('id', $vendor)->lockForUpdate()->first();
            if (!$old) {
                throw ValidationException::withMessages(['vendor_name' => 'Vendor not found']);
            }
            $before = [];
            $after = [];
            foreach ($data as $field => $value) {
                if (!array_key_exists($field, self::EDITABLE)) continue;
                $value = is_string($value) ? trim($value) : $value;
                $current = $old->{$field} ?? null;
                if ((string) ($current ?? '') !== (string) ($value ?? '')) {
                    $before[$field] = $current;
                    $after[$field] = $value === '' ? null : $value;
                }
            }
            if (!$after) {
                throw ValidationException::withMessages([
                    'reason' => 'No Vendor Master changes detected.',
                ]);
            }
            // Prevent GSTIN collisions without changing vendor identity.
            if (array_key_exists('gstin', $after) && $after['gstin'] !== null) {
                if (DB::table(self::TABLE)->where('gstin', $after['gstin'])
                    ->where('id', '<>', $vendor)->exists()) {
                    throw ValidationException::withMessages([
                        'gstin' => 'GSTIN is already linked to another vendor.',
                    ]);
                }
            }
            $updates = $after;
            if (Schema::hasColumn(self::TABLE, 'updated_at')) {
                $updates['updated_at'] = now();
            }
            DB::table(self::TABLE)->where('id', $vendor)->update($updates);
            $actor = Auth::user();
            DB::table(self::AUDIT)->insert([
                'vendor_id' => $vendor,
                'action' => 'VENDOR_MASTER_EDIT',
                'before_json' => json_encode($before, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
                'after_json' => json_encode($after, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
                'reason' => $reason,
                'actor_id' => $actor?->id,
                'actor_name' => (string) ($actor?->name ?? 'ERP Admin'),
                'created_at' => now(),
            ]);
        });

        return redirect()->route('erp.vendors.master.manage', ['edit' => $vendor])
            ->with('success', 'Vendor Master updated. Change recorded in audit.');
    }
}
