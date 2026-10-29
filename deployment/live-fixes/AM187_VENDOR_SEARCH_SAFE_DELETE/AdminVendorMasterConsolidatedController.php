<?php

namespace App\Http\Controllers\Web;

use App\Services\VendorReferenceGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class AdminVendorMasterConsolidatedController extends Controller
{
    private const TABLE = 'workshop_vendors';
    private const AUDIT = 'workshop_vendor_master_audits';

    private const FIELDS = [
        'vendor_name' => ['Vendor Name',190],
        'mobile' => ['Mobile',20],
        'email' => ['Email',190],
        'gstin' => ['GSTIN',15],
        'address' => ['Address',500],
        'contact_person' => ['Contact Person',150],
        'city' => ['City',100],
        'state' => ['State',100],
        'pincode' => ['PIN Code',10],
        'pan' => ['PAN',10],
        'bank_name' => ['Bank Name',190],
        'bank_account' => ['Bank Account',70],
        'ifsc' => ['IFSC',11],
        'notes' => ['Notes',1000],
    ];

    private function fields(): array
    {
        $columns = Schema::getColumnListing(self::TABLE);
        return array_filter(self::FIELDS, fn ($meta, $field) =>
            in_array($field, $columns, true), ARRAY_FILTER_USE_BOTH);
    }

    public function index(Request $request): View
    {
        abort_unless(Schema::hasTable(self::TABLE), 503, 'Vendor Master unavailable');
        $fields = $this->fields();
        $q = trim((string) $request->query('q', ''));
        $query = DB::table(self::TABLE);
        if ($q !== '') {
            $cols = array_intersect(['vendor_name','vendor_code','mobile','gstin'],
                Schema::getColumnListing(self::TABLE));
            $pattern = '%' . addcslashes($q, '%_\\') . '%';
            $query->where(function ($sub) use ($cols, $pattern): void {
                foreach ($cols as $col) $sub->orWhere($col, 'like', $pattern);
            });
        }
        $vendors = $query->orderBy('vendor_name')->paginate(30)->withQueryString();
        $viewMode = $request->query('mode') === 'edit' ? 'edit' :
            ($request->query('mode') === 'new' ? 'new' : 'view');
        $selected = null;
        $selectedId = filter_var($request->query('vendor', $request->query('edit', 0)),
            FILTER_VALIDATE_INT);
        if ($selectedId && $selectedId > 0) {
            $selected = DB::table(self::TABLE)->where('id', $selectedId)->first();
            abort_unless($selected, 404, 'Vendor not found');
        } elseif ($viewMode !== 'new' && $vendors->count() > 0) {
            $selected = $vendors->first();
        }
        $deleteEligibility = ['allowed' => false, 'blockers' => []];
        if ($selected && $viewMode !== 'new' && Schema::hasTable(self::AUDIT)) {
            try {
                $deleteEligibility = VendorReferenceGuard::inspect($selected);
            } catch (\Throwable $e) {
                report($e);
                $deleteEligibility = [
                    'allowed' => false,
                    'blockers' => ['Reference verification unavailable'],
                ];
            }
        }
        return view('vendors.master-edit', compact('vendors','fields','q','selected',
            'viewMode','deleteEligibility'));
    }

    public function update(Request $request, int $vendor): RedirectResponse
    {
        abort_unless(Schema::hasTable(self::TABLE) && Schema::hasTable(self::AUDIT),
            503, 'Vendor Master audit unavailable');
        $fields = $this->fields();
        $rules = ['reason' => ['required','string','min:10','max:500']];
        foreach ($fields as $field => [$label,$limit]) {
            if (!$request->exists($field)) continue;
            $rules[$field] = [
                $field === 'vendor_name' ? 'required' : 'nullable',
                'string','max:'.$limit,
            ];
            if ($field === 'email') $rules[$field][] = 'email';
            if ($field === 'gstin') $rules[$field][] = 'regex:/^[0-9A-Z]{15}$/';
            if ($field === 'pan') $rules[$field][] = 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/';
            if ($field === 'ifsc') $rules[$field][] = 'regex:/^[A-Z]{4}0[A-Z0-9]{6}$/';
        }
        $input = $request->all();
        foreach (['gstin','pan','ifsc'] as $field) {
            if (isset($input[$field]) && is_string($input[$field])) {
                $input[$field] = strtoupper(trim($input[$field]));
            }
        }
        $data = Validator::make($input, $rules)->validate();
        $reason = trim((string) $data['reason']);
        unset($data['reason']);
        DB::transaction(function () use ($vendor,$data,$reason): void {
            $before = DB::table(self::TABLE)->where('id',$vendor)->lockForUpdate()->first();
            if (!$before) throw ValidationException::withMessages(['vendor_name'=>'Vendor not found']);
            $oldValues=[];$newValues=[];
            foreach ($data as $field => $value) {
                if (!array_key_exists($field,self::FIELDS)) continue;
                $value = is_string($value) ? trim($value) : $value;
                $prev = $before->{$field} ?? null;
                if ((string)($prev ?? '') !== (string)($value ?? '')) {
                    $oldValues[$field] = $prev;
                    $newValues[$field] = $value === '' ? null : $value;
                }
            }
            if (!$newValues) {
                throw ValidationException::withMessages(['reason'=>'No changes to save']);
            }
            if (!empty($newValues['gstin']) &&
                DB::table(self::TABLE)->where('gstin',$newValues['gstin'])
                    ->where('id','<>',$vendor)->exists()) {
                throw ValidationException::withMessages(['gstin'=>'GSTIN already used']);
            }
            $updates=$newValues;
            if (Schema::hasColumn(self::TABLE,'updated_at')) $updates['updated_at']=now();
            DB::table(self::TABLE)->where('id',$vendor)->update($updates);
            $actor=Auth::user();
            DB::table(self::AUDIT)->insert([
                'vendor_id'=>$vendor,'action'=>'VENDOR_MASTER_EDIT',
                'before_json'=>json_encode($oldValues,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE),
                'after_json'=>json_encode($newValues,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE),
                'reason'=>$reason,'actor_id'=>$actor?->id,
                'actor_name'=>(string)($actor?->name ?? 'ERP Admin'),'created_at'=>now(),
            ]);
        });
        return redirect()->route('erp.vendors.index',['vendor'=>$vendor,'mode'=>'view'])
            ->with('success','Vendor changes saved and audited');
    }
}
