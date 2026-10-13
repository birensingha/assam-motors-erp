<?php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\HrSalaryImpactService;
use App\Support\HrAccess;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

final class HrSalaryImpactController extends Controller
{
    private static function rules():array {
        return [
            'staff_id'=>['required','integer','min:1'],
            'report_month'=>['required','date_format:Y-m'],
            'monthly_salary'=>['required','numeric','min:0','max:99999999'],
            'scheduled_workdays'=>['required','integer','min:1','max:31'],
            'target_productive_hours_per_day'=>['required','numeric','min:0','max:18'],
            'estimated_recovery_rate_per_hour'=>['nullable','numeric','min:0','max:99999999'],
            'reason'=>['required','string','min:12','max:1000'],
        ];
    }

    public function index(Request $request,HrSalaryImpactService $service):View {
        abort_unless(HrAccess::permitted($request->user()),403);
        $month=(string)$request->query('month',now('Asia/Kolkata')->format('Y-m'));
        $staffId=(int)$request->query('staff_id',0);
        return view('hr.salary-impact',$service->report($month,$staffId));
    }
    public function save(Request $request,HrSalaryImpactService $service):RedirectResponse {
        abort_unless(HrAccess::permitted($request->user()),403);
        $data=$request->validate(self::rules());
        $service->saveProfile($data,(int)$request->user()->staff_id);
        return redirect()->route('erp.hr.salary-impact',['month'=>$data['report_month'],'staff_id'=>$data['staff_id']])
            ->with('hr_success','Saved month-specific salary impact configuration. No salary deduction or payroll posting made.');
    }
    public static function profileRules():array {return self::rules();}
}