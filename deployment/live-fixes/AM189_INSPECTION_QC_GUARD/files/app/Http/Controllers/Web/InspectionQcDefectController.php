<?php
declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use App\Services\InspectionQcGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class InspectionQcDefectController extends Controller
{
    private function admin(Request $request): ErpAuthUser
    {
        $user=$request->user();
        abort_unless($user instanceof ErpAuthUser && $user->active && $user->isAdmin(),
            403,'Active ERP Owner/Admin login required.');
        return $user;
    }

    public function index(Request $request,int $job,InspectionQcGuard $guard)
    {
        $this->admin($request);
        $card=DB::table('job_cards')->where('id',$job)->first();
        if (!$card) abort(404);
        $staff=DB::table('staff')->whereRaw("LOWER(COALESCE(status,'active'))='active'")
            ->orderBy('name')->get(['id','name','staff_code']);
        return view('inspection-defects.index',[
            'job'=>$card,'findings'=>$guard->findings($job),'staff'=>$staff,
            'identity'=>$this->jobIdentity($card),
        ]);
    }

    /** Read-only header data, compatible with older and staging JC field names. */
    private function firstPresent(object $row,array $names): ?string
    {
        foreach ($names as $name) {
            $value=$row->{$name} ?? null;
            if ($value === null || is_array($value) || is_object($value)) continue;
            $value=trim((string)$value);
            if ($value!=='' && $value!=='0') return $value;
        }
        return null;
    }

    private function relatedById(array $tables,int $id): ?object
    {
        if ($id<=0) return null;
        foreach ($tables as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table,'id')) continue;
            $found=DB::table($table)->where('id',$id)->first();
            if ($found) return $found;
        }
        return null;
    }

    /** Present stored identifiers; never invent a registration or customer. */
    private function jobIdentity(object $card): array
    {
        $jobNo=$this->firstPresent($card,[
            'job_card_number','job_card_no','job_no','jobcard_no','jc_no',
            'jc_number','job_number','jobcard_number','number','card_number'
        ]);
        $vehicle=$this->firstPresent($card,[
            'vehicle_no','vehicle_number','vehicle_reg_no','vehicle_registration_no',
            'vehicle_registration_number','registration_no','registration_number',
            'vehicle_reg_number','reg_no','regn_no','reg_number','vehicle_reg'
        ]);
        $customer=$this->firstPresent($card,[
            'customer_name','customer_full_name','name_of_customer',
            'client_name','owner_name','vehicle_owner_name'
        ]);
        $vehicleId=(int)($this->firstPresent($card,['vehicle_id','customer_vehicle_id'])??0);
        $vehicleRow=null;
        if (!$vehicle || !$customer) {
            $vehicleRow=$this->relatedById([
                'am_erp_vehicles','customer_vehicles','vehicle_master',
                'workshop_vehicles','vehicles'
            ],$vehicleId);
        }
        if (!$vehicle && $vehicleRow) {
            $vehicle=$this->firstPresent($vehicleRow,[
                'registration_no','registration_number','vehicle_no',
                'vehicle_number','reg_no','reg_number','registration',
                'registration_mark','vehicle_reg_no'
            ]);
        }
        $customerId=(int)($this->firstPresent($card,[
            'customer_id','customer_master_id','client_id','owner_id'
        ])??0);
        if ($customerId===0 && $vehicleRow) {
            $customerId=(int)($this->firstPresent($vehicleRow,['customer_id','owner_id'])??0);
        }
        if (!$customer) {
            $customerRow=$this->relatedById([
                'am_erp_customers','customer_master','customers','workshop_customers'
            ],$customerId);
            if ($customerRow) {
                $customer=$this->firstPresent($customerRow,[
                    'customer_name','name','full_name','customer_full_name',
                    'display_name','client_name'
                ]);
            }
        }
        return [
            'job_no'=>$jobNo ?? 'Not recorded',
            'vehicle_no'=>$vehicle ?? 'Not recorded',
            'customer_name'=>$customer ?? 'Not recorded',
        ];
    }

    public function sync(Request $request,int $job,InspectionQcGuard $guard)
    {
        $admin=$this->admin($request);
        $created=$guard->sync($job,(int)$admin->id);
        return redirect()->route('erp.job-cards.inspection-defects',$job)
            ->with('success',$created.' inspection defect(s) registered. No existing inspection answers were altered.');
    }

    public function rectify(Request $request,int $defect,InspectionQcGuard $guard)
    {
        $admin=$this->admin($request);
        $data=$request->validate([
            'repair_staff_id'=>['required','integer','min:1'],
            'repair_note'=>['required','string','min:8','max:2000'],
        ]);
        $job=$guard->repair($defect,(int)$admin->id,
            (int)$data['repair_staff_id'],(string)$data['repair_note']);
        return redirect()->route('erp.job-cards.inspection-defects',$job)
            ->with('success','Repair recorded. Independent verification is still required.');
    }

    public function verify(Request $request,int $defect,InspectionQcGuard $guard)
    {
        $admin=$this->admin($request);
        $data=$request->validate([
            'result'=>['required','in:PASS,FAIL'],
            'verification_note'=>['required','string','min:8','max:2000'],
        ]);
        $job=$guard->verify($defect,(int)$admin->id,(int)($admin->staff_id??0),
            $data['result']==='PASS',(string)$data['verification_note']);
        return redirect()->route('erp.job-cards.inspection-defects',$job)
            ->with('success',$data['result']==='PASS'
                ?'Re-inspection VERIFIED. Original defect and audit preserved.'
                :'Re-inspection failed. Defect returned to OPEN.');
    }
}
