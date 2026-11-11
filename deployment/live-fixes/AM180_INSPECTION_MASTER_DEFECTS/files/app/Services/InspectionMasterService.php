<?php
declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * AM180 Inspection checklist master. Published versions and per-job snapshots
 * are immutable. It does not modify legacy inspection submission on its own.
 */
final class InspectionMasterService
{
    public const TYPES = [
        'condition' => ['good', 'fair', 'poor'],
        'yes_no' => ['yes', 'no'],
        'availability' => ['available', 'not_available'],
        'fuel' => ['empty', 'quarter', 'half', 'three_quarter', 'full'],
        'reading' => [],
    ];

    public function defaults(): array
    {
        $rows = [
            ['Key Condition / Number of Keys','availability',false],
            ['Odometer / Dashboard Kilometres','reading',false],
            ['Fuel Level / Gauge Reading','fuel',false],
            ['Spare Wheel / Spare Tyre Condition','availability',false],
            ['Jack / Wheel Wrench / Tool Kit','availability',false],
            ['Documents / Accessories Received','availability',false],
            ['Body Condition / Dents / Scratches','condition',false],
            ['Bumpers / Grille / Exterior Trim','condition',false],
            ['All Glass / Windshield / Windows','condition',false],
            ['Mirrors / Door Handles / Door Locks','condition',false],
            ['Wipers / Washer System','yes_no',false],
            ['Tyres (All Wheels) / Tread / Pressure','condition',true],
            ['Wheel Rims / Nuts / Alignment Signs','condition',true],
            ['Headlights / Tail / Brake / Indicators','yes_no',true],
            ['Horn / Lights / Electrical Equipment','yes_no',true],
            ['Check Engine Light (MIL)','yes_no',true],
            ['ABS / Airbag / Other Warning Lights','yes_no',true],
            ['Battery Condition / Battery Serial No','condition',false],
            ['Interior / Dashboard / Seats','condition',false],
            ['Seat Belts / Safety Equipment','yes_no',true],
            ['AC / Blower / Heating','yes_no',false],
            ['Engine Oil Level / Oil Condition','condition',true],
            ['Coolant / Radiator / Reservoir Level','condition',true],
            ['Brake / Steering / Other Fluid Levels','condition',true],
            ['Oil Seepage / Fluid Leakage','yes_no',true],
            ['Engine Bay / Belts / Hoses / Wiring','condition',false],
            ['Engine Sound / Vibration / Smoke','condition',true],
            ['Brake System / Parking Brake','condition',true],
            ['Steering / Suspension','condition',true],
            ['Underbody / Exhaust / Transmission Leaks','yes_no',true],
        ];
        $points = [];
        foreach ($rows as $i => [$label, $type, $critical]) {
            $defects = match ($type) {
                'condition' => ['fair', 'poor'],
                'availability' => ['not_available'],
                'yes_no' => ['no'],
                default => [],
            };
            // A "YES" to warning light or leakage means a problem.
            if (in_array($i, [15, 16, 24, 29], true)) $defects = ['yes'];
            $points[] = [
                'key' => sprintf('p%02d', $i + 1),
                'label' => $label,
                'type' => $type,
                'required' => true,
                'critical' => $critical,
                'weight' => $critical ? 2 : 1,
                'defect_values' => $defects,
            ];
        }
        return $points;
    }

    public function active(): array
    {
        if (!Schema::hasTable('am_inspection_master_versions')) {
            return ['version'=>null, 'points'=>$this->defaults()];
        }
        $version = DB::table('am_inspection_master_versions')
            ->where('status','published')->orderByDesc('version')->first();
        return $version
            ? ['version'=>(int)$version->version, 'points'=>json_decode($version->points_json,true,512,JSON_THROW_ON_ERROR)]
            : ['version'=>null, 'points'=>$this->defaults()];
    }

    public function validateDefinition(array $points): array
    {
        if (count($points) < 1 || count($points) > 100) {
            throw ValidationException::withMessages(['points'=>'Checklist requires 1 to 100 points.']);
        }
        $keys = [];
        $labels = [];
        $clean = [];
        foreach (array_values($points) as $i => $point) {
            if (!is_array($point)) throw ValidationException::withMessages(['points'=>'Invalid point at '.($i+1)]);
            $key = strtolower(trim((string)($point['key']??'')));
            $label = trim((string)($point['label']??''));
            $type = strtolower(trim((string)($point['type']??'')));
            if (!preg_match('/^[a-z][a-z0-9_-]{1,39}$/',$key) || isset($keys[$key])) {
                throw ValidationException::withMessages(['points'=>'Duplicate/invalid point ID at '.($i+1)]);
            }
            if ($label === '' || mb_strlen($label)>160 || isset($labels[mb_strtolower($label)])) {
                throw ValidationException::withMessages(['points'=>'Duplicate/invalid point label at '.($i+1)]);
            }
            if (!array_key_exists($type,self::TYPES)) {
                throw ValidationException::withMessages(['points'=>'Invalid answer type for '.$label]);
            }
            $rawDefects = $point['defect_values'] ?? [];
            if (!is_array($rawDefects)) {
                throw ValidationException::withMessages(['points'=>'Invalid defect selections for '.$label]);
            }
            $defects = array_values(array_unique(array_map(
                static fn ($v) => strtolower(trim((string)$v)), $rawDefects
            )));
            if (array_diff($defects,self::TYPES[$type])) {
                throw ValidationException::withMessages(['points'=>'Defect values not permitted for '.$label]);
            }
            $weight = filter_var($point['weight']??1,FILTER_VALIDATE_INT);
            if ($weight === false || $weight < 1 || $weight > 10) {
                throw ValidationException::withMessages(['points'=>'Weight must be 1-10 for '.$label]);
            }
            $keys[$key] = true;
            $labels[mb_strtolower($label)] = true;
            $clean[] = [
                'key'=>$key,'label'=>$label,'type'=>$type,
                'required'=>(bool)($point['required']??true),
                'critical'=>(bool)($point['critical']??false),
                'weight'=>$weight,'defect_values'=>$defects,
            ];
        }
        return $clean;
    }

    public function publish(array $points, string $actor): int
    {
        $clean = $this->validateDefinition($points);
        if (!Schema::hasTable('am_inspection_master_versions')) {
            abort(503,'Inspection Master migration is not installed.');
        }
        return DB::transaction(function () use ($clean,$actor): int {
            // Serialize publish operations, including an empty master table.
            DB::table('am_inspection_master_versions')->lockForUpdate()->get();
            $number = (int) DB::table('am_inspection_master_versions')->max('version') + 1;
            DB::table('am_inspection_master_versions')
                ->where('status','published')->update(['status'=>'superseded','updated_at'=>now()]);
            DB::table('am_inspection_master_versions')->insert([
                'version'=>$number,'status'=>'published','points_json'=>json_encode($clean,JSON_THROW_ON_ERROR),
                'published_by'=>$actor,'published_at'=>now(),
                'created_at'=>now(),'updated_at'=>now()
            ]);
            return $number;
        });
    }

    public function jobSnapshot(int $jobId): array
    {
        if (!Schema::hasTable('am_inspection_job_snapshots')) {
            abort(503,'Inspection snapshot migration missing.');
        }
        return DB::transaction(function () use ($jobId): array {
            $job = DB::table('job_cards')->where('id',$jobId)->lockForUpdate()->first();
            if (!$job) abort(404);
            $existing = DB::table('am_inspection_job_snapshots')->where('job_card_id',$jobId)->first();
            if ($existing) {
                return ['version_id'=>$existing->master_version_id,
                    'points'=>json_decode($existing->points_json,true,512,JSON_THROW_ON_ERROR)];
            }
            // Never reinterpret a historical/completed inspection under a new checklist.
            if (in_array(strtolower((string)($job->inspection_status??'')),['completed','skipped'],true)) {
                throw ValidationException::withMessages(['inspection'=>'Historical completed inspection has no checklist snapshot; retain its original data.']);
            }
            $active = DB::table('am_inspection_master_versions')->where('status','published')->orderByDesc('version')->first();
            $points = $active ? json_decode($active->points_json,true,512,JSON_THROW_ON_ERROR) : $this->defaults();
            DB::table('am_inspection_job_snapshots')->insert([
                'job_card_id'=>$jobId,'master_version_id'=>$active->id??null,
                'points_json'=>json_encode($points,JSON_THROW_ON_ERROR),
                'created_at'=>now()
            ]);
            return ['version_id'=>$active->id??null,'points'=>$points];
        });
    }

    /** Call inside final inspection POST, before marking inspection Completed. */
    public function validateSubmission(array $definition, array $items): array
    {
        $points = $this->validateDefinition($definition);
        $answers = [];
        foreach ($items as $submitted) {
            if (!is_array($submitted)) throw ValidationException::withMessages(['items'=>'Invalid inspection data.']);
            $key = strtolower(trim((string)($submitted['key']??'')));
            if ($key === '' || isset($answers[$key])) throw ValidationException::withMessages(['items'=>'Missing/duplicate point ID.']);
            $answers[$key] = $submitted;
        }
        $allowedKeys = array_column($points,'key');
        if (array_diff(array_keys($answers),$allowedKeys)) {
            throw ValidationException::withMessages(['items'=>'Unknown inspection point submitted. Refresh checklist.']);
        }
        $validated = [];
        foreach ($points as $i=>$point) {
            $input = $answers[$point['key']]??null;
            if ($input === null && !$point['required']) continue;
            if ($input === null) throw ValidationException::withMessages(['items'=>($i+1).'. '.$point['label'].' — answer required.']);
            $value = strtolower(trim((string)($input['value']??'')));
            $remark = trim((string)($input['remarks']??''));
            if ($value === '' && !$point['required']) continue;
            if ($value === '') throw ValidationException::withMessages(['items'=>($i+1).'. '.$point['label'].' — answer required.']);
            if ($point['type']==='reading') {
                if (!preg_match('/^\d{1,9}(\.\d{1,2})?$/',$value)) {
                    throw ValidationException::withMessages(['items'=>($i+1).'. '.$point['label'].' — enter numeric reading.']);
                }
            } elseif (!in_array($value,self::TYPES[$point['type']],true)) {
                throw ValidationException::withMessages(['items'=>($i+1).'. '.$point['label'].' — invalid option.']);
            }
            if (mb_strlen($remark)>1000) throw ValidationException::withMessages(['items'=>($i+1).'. '.$point['label'].' — remarks too long.']);
            $defect = in_array($value,$point['defect_values'],true);
            if ($defect && $remark === '') {
                throw ValidationException::withMessages(['items'=>($i+1).'. '.$point['label'].' — observation required for defect.']);
            }
            $validated[] = ['key'=>$point['key'],'label'=>$point['label'],'value'=>$value,
                'remarks'=>$remark,'defect'=>$defect,'critical'=>$point['critical']];
        }
        return $validated;
    }
}
