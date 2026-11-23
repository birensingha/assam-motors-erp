<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AdminAttendanceRangeController extends Controller
{
    /**
     * Independent read-only date-range report. The existing monthly matrix,
     * attendance corrections, check-in/out and staff APIs are NOT modified.
     * Salary figures here are indicative allocation only, NEVER payroll posting.
     */
    public function index(Request $request): View|StreamedResponse
    {
        $actor = $request->user();
        abort_unless(
            $actor instanceof ErpAuthUser &&
            (bool) $actor->active &&
            strtolower(trim((string) $actor->role)) === 'admin',
            403
        );

        $today = now('Asia/Kolkata');
        $filters = Validator::make([
            'from' => $request->query('from', $today->copy()->startOfMonth()->format('Y-m-d')),
            'to' => $request->query('to', $today->format('Y-m-d')),
            'staff_id' => $request->query('staff_id', '0'),
            'department' => $request->query('department', ''),
            'export' => $request->query('export', ''),
        ], [
            'from' => ['required','date_format:Y-m-d'],
            'to' => ['required','date_format:Y-m-d'],
            'staff_id' => ['nullable','integer','min:0'],
            'department' => ['nullable','string','max:100'],
            'export' => ['nullable','in:,csv'],
        ])->validate();

        $from = $filters['from'];
        $to = $filters['to'];
        $start = DateTimeImmutable::createFromFormat('!Y-m-d', $from);
        $end = DateTimeImmutable::createFromFormat('!Y-m-d', $to);
        if (!$start || !$end || $start->format('Y-m-d') !== $from || $end->format('Y-m-d') !== $to
            || $start > $end) {
            return redirect()->route('erp.attendance.range')->withErrors(['from'=>'Invalid From/To date range.']);
        }
        if ($start->diff($end)->days > 1095) {
            return redirect()->route('erp.attendance.range')->withErrors(['from'=>'Select up to 3 years per report.']);
        }

        $staffId = max(0, (int) ($filters['staff_id'] ?? 0));
        $department = trim((string) ($filters['department'] ?? ''));
        $staffOptions = DB::table('staff')->orderBy('name')
            ->get(['id','staff_code','name','department','status']);
        $departments = $staffOptions->pluck('department')->filter()->unique()->sort()->values();

        $query = DB::table('attendance as a')
            ->leftJoin('staff as s', 's.id','=','a.staff_id')
            ->whereBetween('a.date', [$from,$to])
            ->when($staffId > 0, fn ($q) => $q->where('a.staff_id',$staffId))
            ->when($department !== '', fn ($q) => $q->where('s.department',$department))
            ->select([
                'a.id','a.staff_id','a.date','a.check_in','a.check_out',
                'a.worked_hours','a.status','a.late',
                'a.staff_code as saved_code','a.staff_name as saved_name',
                's.name as master_name','s.staff_code as master_code','s.department',
            ])
            ->orderBy('a.date')->orderBy('a.staff_id')->orderBy('a.id');

        // Bounded rows keep shared hosting stable. Never silently export a truncated report.
        $attendance = $query->limit(20001)->get();
        if ($attendance->count() > 20000) {
            return redirect()->route('erp.attendance.range')->withErrors([
                'from'=>'This selection exceeds 20,000 entries. Choose a shorter date range or a staff member.'
            ]);
        }

        $rows = [];
        $summaries = [];
        $seen = [];
        foreach ($attendance as $entry) {
            $id = (int) $entry->staff_id;
            $date = substr((string) $entry->date, 0, 10);
            $key = $id.'|'.$date;
            $seen[$key] = ($seen[$key] ?? 0) + 1;
            $status = strtolower(trim((string) $entry->status));
            $status = in_array($status,['present','absent','off'],true) ? $status : 'unknown';
            $hours = is_numeric($entry->worked_hours) ? max(0.0, (float)$entry->worked_hours) : 0.0;
            $checkIn = (string)($entry->check_in ?? '');
            $checkOut = (string)($entry->check_out ?? '');
            $review = $status === 'unknown'
                || ($status === 'present' && ($checkIn === '' || $checkOut === ''));
            $row = [
                'id' => (int)$entry->id, 'staff_id'=>$id,
                'name'=>(string)($entry->master_name ?: $entry->saved_name ?: 'Staff #'.$id),
                'code'=>(string)($entry->master_code ?: $entry->saved_code ?: $id),
                'date'=>$date, 'department'=>(string)($entry->department ?? ''),
                'check_in'=>$checkIn, 'check_out'=>$checkOut,
                'hours'=>round($hours,2), 'status'=>$status,
                'late'=>(bool)$entry->late, 'review'=>$review,
                'duplicate'=>false,
            ];
            $rows[] = $row;
            if (!isset($summaries[$id])) {
                $summaries[$id] = [
                    'staff_id'=>$id,'name'=>$row['name'],'code'=>$row['code'],
                    'present'=>0,'absent'=>0,'off'=>0,'unknown'=>0,'late'=>0,
                    'hours'=>0.0,'review'=>0,'duplicates'=>0,
                    'salary'=>null,'absence_allocation'=>null,'salary_after_absence_estimate'=>null,
                    'salary_note'=>'Salary profile for selected complete month not configured.',
                ];
            }
        }

        // Preserve all raw attendance rows in detail; exclude duplicate dates from summaries.
        $counted = [];
        foreach ($rows as &$row) {
            $id = $row['staff_id'];
            $key = $id.'|'.$row['date'];
            if ($seen[$key] > 1) {
                $row['duplicate'] = true;
                $row['review'] = true;
            }
            if (isset($counted[$key])) {
                continue;
            }
            $counted[$key] = true;
            $summaries[$id][$row['status']]++;
            $summaries[$id]['hours'] += $row['hours'];
            $summaries[$id]['late'] += (int)$row['late'];
            $summaries[$id]['review'] += (int)$row['review'];
            $summaries[$id]['duplicates'] += (int)$row['duplicate'];
        }
        unset($row);

        // Existing HR "salary impact" is an indicative ANALYSIS profile, not
        // an approved wage contract. We display a bounded absenteeism estimate,
        // not an authorized salary deduction, net salary or payment voucher.
        $fullMonth = $start->format('Y-m') === $end->format('Y-m')
            && $from === $start->format('Y-m-01')
            && $to === $end->format('Y-m-t');
        if ($fullMonth && $summaries) {
            $month = $start->format('Y-m');
            $profiles = DB::table('hr_salary_impact_profiles')
                ->where('report_month',$month)
                ->whereIn('staff_id',array_keys($summaries))
                ->get()->keyBy('staff_id');
            foreach ($summaries as $id => &$sum) {
                $profile = $profiles->get($id);
                if (!$profile || !is_numeric($profile->monthly_salary)
                    || (int)$profile->scheduled_workdays < 1) {
                    continue;
                }
                $salary = max(0.0,(float)$profile->monthly_salary);
                $days = (int)$profile->scheduled_workdays;
                $allocated = min($salary,round($salary / $days * $sum['absent'],2));
                $sum['salary'] = round($salary,2);
                $sum['absence_allocation'] = $allocated;
                $sum['salary_after_absence_estimate'] = round($salary - $allocated,2);
                $sum['salary_note'] = 'Illustrative only. HR must verify absence, paid leave, duty days, advances and salary rules.';
            }
            unset($sum);
        }
        uasort($summaries, static fn(array $a,array $b):int => strcmp($a['name'],$b['name']) ?: ($a['staff_id'] <=> $b['staff_id']));
        $summaries = array_values($summaries);

        if (($filters['export'] ?? '') === 'csv') {
            $filename = 'assam-motors-attendance-'.$from.'-to-'.$to.'.csv';
            return response()->streamDownload(static function () use ($rows):void {
                $stream = fopen('php://output','w');
                fwrite($stream, "\xEF\xBB\xBF");
                fputcsv($stream,['Date','Staff Code','Staff Name','Department','Check In','Check Out','Status','Late','Worked Hours','Review'],',','"','');
                $safe = static function (string $value):string {
                    return preg_match('/^\s*[=+\-@]/u',$value) ? "'".$value : $value;
                };
                foreach ($rows as $r) {
                    fputcsv($stream, [
                        $r['date'],$safe($r['code']),$safe($r['name']),$safe($r['department']),
                        $safe($r['check_in']),$safe($r['check_out']),$r['status'],
                        $r['late'] ? 'Yes':'No', $r['hours'],
                        $r['review'] ? ($r['duplicate']?'Duplicate date':'Needs review'):'OK',
                    ],',','"','');
                }
                fclose($stream);
            },$filename,['Content-Type'=>'text/csv; charset=UTF-8']);
        }

        return view('admin.native.attendance-range',compact(
            'from','to','staffId','department','staffOptions','departments',
            'rows','summaries','fullMonth'
        ));
    }
}
