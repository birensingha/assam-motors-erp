<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\HrSalaryImpactController;
use App\Services\HrSalaryImpactService;
use App\Support\HrAccess;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

final class HrSalaryImpactApiController extends Controller
{
    public function index(Request $request,HrSalaryImpactService $service):JsonResponse {
        abort_unless(HrAccess::permitted($request->user()),403);
        return response()->json(['ok'=>true]+$service->report(
            (string)$request->query('month',now('Asia/Kolkata')->format('Y-m')),
            (int)$request->query('staff_id',0)
        ));
    }
    public function save(Request $request,HrSalaryImpactService $service):JsonResponse {
        abort_unless(HrAccess::permitted($request->user()),403);
        $data=$request->validate(HrSalaryImpactController::profileRules());
        $service->saveProfile($data,(int)$request->user()->staff_id);
        return response()->json(['ok'=>true,'message'=>'Salary impact configuration saved; no payroll changes.']);
    }
}