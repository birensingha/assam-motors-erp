<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class HrSalaryImpactService
{
    public function __construct(private readonly HrOperationsService $ops) {}

    public function report(string $month, int $staffId=0): array
    {
        $base = $this->ops->report($month,$staffId);
        $people = $base['staff']->keyBy('id');
        $profiles = DB::table('hr_salary_impact_profiles')->where('report_month',$month)
            ->when($staffId>0,fn($q)=>$q->where('staff_id',$staffId))->get()->keyBy('staff_id');
        $records=[];
        $byStaff = $base['attendance']->groupBy('staff_id');
        $performances = collect($base['performance'])->keyBy('staff_id');
        foreach($people as $person) {
            if ($staffId && (int)$person->id!==$staffId) continue;
            $id=(int)$person->id;
            $profile=$profiles->get($id);
            $days=$byStaff->get($id,collect())->filter(fn($a)=>strtolower(trim((string)$a->status))==='absent')
                ->pluck('date')->map(fn($d)=>substr((string)$d,0,10))->unique()->count();
            $p=$performances->get($id);
            $row=[
                'staff_id'=>$id, 'staff_code'=>$person->staff_code,'name'=>$person->name,
                'configuration'=>'not_configured', 'confirmed_absent_days'=>$days,
                'recorded_productive_hours'=>round((float)($p['productive_hours']??0),2),
                'productivity_percent'=>$p['productivity_percent']??null,
                'efficiency_percent'=>$p['efficiency_percent']??null,
                'notes'=>'Only dates explicitly marked Absent are counted. Leave, weekly off and missing attendance are not automatically considered absent.',
            ];
            if($profile){
                $row['configuration']='configured';
                $row+=HrSalaryImpactCalculator::calculate(
                    (float)$profile->monthly_salary,(int)$profile->scheduled_workdays,
                    (float)$profile->target_productive_hours_per_day,
                    $profile->estimated_recovery_rate_per_hour===null?null:(float)$profile->estimated_recovery_rate_per_hour,
                    $days,(float)($p['productive_hours']??0)
                );
            }
            $records[]=$row;
        }
        return ['month'=>$month,'staff'=>$base['staff'],'records'=>$records,
            'is_estimate'=>true,'no_automatic_salary_deduction'=>true];
    }

    public function saveProfile(array $data,int $hrActorStaffId): void
    {
        $month=$this->ops->month((string)$data['report_month']);
        $staffId=(int)$data['staff_id'];
        if(!DB::table('staff')->where('id',$staffId)->where('status','active')->exists()){
            throw ValidationException::withMessages(['staff_id'=>'Active Staff account required']);
        }
        DB::transaction(function() use($data,$hrActorStaffId,$staffId,$month){
            $existing=DB::table('hr_salary_impact_profiles')->where('staff_id',$staffId)->where('report_month',$month)->lockForUpdate()->first();
            $entry=[
                'monthly_salary'=>round((float)$data['monthly_salary'],2),
                'scheduled_workdays'=>(int)$data['scheduled_workdays'],
                'target_productive_hours_per_day'=>round((float)$data['target_productive_hours_per_day'],2),
                'estimated_recovery_rate_per_hour'=>($data['estimated_recovery_rate_per_hour']??null)===''?null:($data['estimated_recovery_rate_per_hour']??null),
                'updated_by_staff_id'=>$hrActorStaffId,'updated_at'=>now(),
            ];
            if($existing){
                DB::table('hr_salary_impact_profiles')->where('id',$existing->id)->update($entry);
                $id=(int)$existing->id;
            } else {
                $id=(int)DB::table('hr_salary_impact_profiles')->insertGetId($entry+[
                    'staff_id'=>$staffId,'report_month'=>$month,'created_at'=>now(),
                ]);
            }
            DB::table('hr_audit_logs')->insert([
                'staff_id'=>$hrActorStaffId,'action'=>'HR_SALARY_IMPACT_PROFILE_UPDATED',
                'subject_type'=>'hr_salary_impact_profile','subject_id'=>$id,
                'details'=>json_encode(['staff_id'=>$staffId,'report_month'=>$month,'before'=>$existing,'after'=>$entry,
                    'reason'=>trim((string)$data['reason'])],JSON_THROW_ON_ERROR),
                'created_at'=>now(),
            ]);
        });
    }
}