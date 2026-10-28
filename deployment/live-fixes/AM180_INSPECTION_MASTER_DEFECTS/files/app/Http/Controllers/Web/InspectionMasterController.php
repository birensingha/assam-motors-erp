<?php
declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\InspectionMasterService;
use App\Services\InspectionDefectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class InspectionMasterController extends Controller
{
    public function index(Request $request, InspectionMasterService $service)
    {
        $this->authorizeAdmin($request);
        $master = $service->active();
        return view('inspection-master.index',[
            'points'=>$master['points'],'version'=>$master['version'],
            'types'=>InspectionMasterService::TYPES,
        ]);
    }

    public function publish(Request $request, InspectionMasterService $service)
    {
        $this->authorizeAdmin($request);
        $data = $request->validate(['points_json'=>['required','string','max:250000']]);
        try {
            $points=json_decode($data['points_json'],true,512,JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw ValidationException::withMessages(['points'=>'Invalid inspection point configuration.']);
        }
        if (!is_array($points)) throw ValidationException::withMessages(['points'=>'Inspection configuration must be a list.']);
        $version=$service->publish($points,$this->actor($request));
        return redirect()->route('erp.inspection-master.index')
            ->with('success',"Inspection Master v{$version} published. New Job Cards use this version; older snapshots are retained.");
    }

    public function jobChecklist(Request $request, int $job, InspectionMasterService $service): JsonResponse
    {
        $this->authorizeAssignedInspector($request,$job);
        // Binds the current published version to this pending Job Card once.
        $data=$service->jobSnapshot($job);
        return response()->json([
            'ok'=>true,'job_id'=>$job,'master_version_id'=>$data['version_id'],
            'items'=>$data['points'],
            'message'=>'Only the ERP successful final submit can complete an inspection.',
        ])->header('Cache-Control','private, no-store');
    }

    public function defects(Request $request, int $job, InspectionDefectService $service): JsonResponse
    {
        $this->authorizeAdminOrAssignedInspector($request,$job);
        if (!Schema::hasTable('am_inspection_defects')) abort(503);
        $rows=DB::table('am_inspection_defects')->where('job_card_id',$job)->orderBy('id')->get();
        return response()->json(['ok'=>true,'job_id'=>$job,'defects'=>$rows])
            ->header('Cache-Control','private, no-store');
    }

    public function rectify(Request $request, int $defect, InspectionDefectService $service)
    {
        $this->authorizeAdmin($request);
        $data=$request->validate(['repair_note'=>['required','string','min:8','max:2000']]);
        $row=DB::table('am_inspection_defects')->where('id',$defect)->first();
        if (!$row) abort(404);
        $service->rectify($defect,$this->actor($request),$data['repair_note']);
        return redirect()->route('erp.job-cards.show',$row->job_card_id)
            ->with('success','Repair recorded. An independent re-inspection is still required before QC PASS.');
    }

    public function verify(Request $request, int $defect, InspectionDefectService $service)
    {
        $this->authorizeAdmin($request);
        $data=$request->validate([
            'verification_note'=>['required','string','min:8','max:2000'],
            'result'=>['required','in:pass,fail'],
        ]);
        $row=DB::table('am_inspection_defects')->where('id',$defect)->first();
        if (!$row) abort(404);
        $service->verify($defect,$this->actor($request),$data['verification_note'],$data['result']==='pass');
        return redirect()->route('erp.job-cards.show',$row->job_card_id)
            ->with('success',$data['result']==='pass'?'Re-inspection verified.':'Re-inspection failed: defect reopened.');
    }

    private function actor(Request $request): string
    {
        $user=$request->user();
        return (string)($user?->staff_code ?? $user?->name ?? $user?->id ?? 'ERP');
    }

    private function authorizeAdmin(Request $request): void
    {
        $user=$request->user();
        abort_unless($user!==null,403,'ERP login required.');
        $role=strtolower(trim((string)($user->role??$user->role_name??'')));
        $admin=in_array($role,['owner','admin','administrator','super admin','super_admin'],true)
            || (bool)($user->is_admin??false)===true;
        abort_unless($admin,403,'Owner/Admin access required.');
    }

    private function authorizeAssignedInspector(Request $request, int $job): void
    {
        $user=$request->user();
        $staff=(int)($user?->staff_id??0);
        abort_unless($staff>0,403,'Staff login required.');
        $row=DB::table('job_cards')->where('id',$job)->first();
        if (!$row) abort(404);
        abort_unless($staff===(int)($row->technician_id??0),403,'Inspection not assigned to this Staff ID.');
    }

    private function authorizeAdminOrAssignedInspector(Request $request, int $job): void
    {
        $user=$request->user();
        $role=strtolower(trim((string)($user?->role??$user?->role_name??'')));
        if ($user && (in_array($role,['owner','admin','administrator','super admin','super_admin'],true)
            || (bool)($user->is_admin??false)===true)) return;
        $staff=(int)($user?->staff_id??0);
        $row=DB::table('job_cards')->where('id',$job)->first();
        if (!$row) abort(404);
        abort_unless($staff>0 &&
            ($staff===(int)($row->technician_id??0) ||
             $staff===(int)($row->inspection_submitted_by??0)),403,'Not authorized for this inspection.');
    }
}
