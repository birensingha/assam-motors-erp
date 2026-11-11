<?php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\HrOperationsService;
use App\Support\HrAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class HrOperationsController extends Controller
{
    public function index(Request $request,HrOperationsService $service): View
    {
        abort_unless(HrAccess::permitted($request->user()),403);
        $month=$service->month((string)$request->query('month',now('Asia/Kolkata')->format('Y-m')));
        $staffId=(int)$request->query('staff_id',0);
        abort_if($staffId<0,422);
        return view('hr.operations',$service->report($month,$staffId));
    }

    public function attendance(Request $request,HrOperationsService $service): RedirectResponse
    {
        abort_unless(HrAccess::permitted($request->user()),403);
        $data=$request->validate([
            'staff_id'=>['required','integer','min:1'], 'date'=>['required','date_format:Y-m-d'],
            'status'=>['required','in:present,late,absent,leave,off'],
            'check_in'=>['nullable','date_format:H:i'], 'check_out'=>['nullable','date_format:H:i'],
            'reason'=>['required','string','min:12','max:1000'],
        ]);
        $service->correctAttendance($data,(int)$request->user()->staff_id);
        return back()->with('hr_success','Audited attendance correction saved. Staff App reads the same attendance table.');
    }

    public function leave(Request $request,HrOperationsService $service): RedirectResponse
    {
        abort_unless(HrAccess::permitted($request->user()),403);
        $data=$request->validate([
            'staff_id'=>['required','integer','min:1'],'entry_date'=>['required','date_format:Y-m-d'],
            'kind'=>['required','in:earned,used'],'days'=>['required','numeric','min:0.5','max:31'],
            'approval_status'=>['required_unless:kind,earned','nullable','in:approved,pending,unapproved'],
            'application_status'=>['required_unless:kind,earned','nullable','in:submitted,not_submitted'],
            'reason'=>['required','string','min:12','max:1000'],
        ]);
        $service->recordLeave($data,(int)$request->user()->staff_id);
        return back()->with('hr_success','Leave ledger transaction saved with immutable audit. Negative balances remain visible.');
    }
}