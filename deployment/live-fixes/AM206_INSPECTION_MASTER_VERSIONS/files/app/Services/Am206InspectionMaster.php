<?php
declare(strict_types=1);

namespace App\Services;

use App\Support\VehicleInspectionChecklist;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * AM206 - versioned inspection form definitions. Does not switch on any
 * assignment or submit route. Existing 30-point legacy workflow remains intact.
 * Per-Job Card snapshot table is reserved for the later atomic assignment.
 */
final class Am206InspectionMaster
{
    public const TYPE_OPTIONS = [
        'condition'=>['good','fair','poor'],
        'yes_no'=>['yes','no'],
        'availability'=>['available','not_available'],
        'fuel'=>['empty','quarter','half','three_quarter','full'],
        'reading'=>[],
    ];

    public function defaults(): array
    {
        $points=[];
        foreach (VehicleInspectionChecklist::items() as $i=>$name) {
            $safety=(bool)preg_match(
                '/brak|steer|suspension|tyre|tire|wheel|airbag|seat.?belt|abs|headlight|indicator/i',
                (string)$name);
            $points[]=[
                'key'=>sprintf('p%02d',$i+1),
                'label'=>(string)$name,
                'type'=>'condition',
                'required'=>true,
                'critical'=>$safety,
                'weight'=>$safety?2:1,
                'defect_values'=>['fair','poor'],
            ];
        }
        return $points;
    }

    public function active(): array
    {
        if (!Schema::hasTable('am206_inspection_master_versions')) {
            return ['version'=>null,'points'=>$this->defaults()];
        }
        $row=DB::table('am206_inspection_master_versions')
            ->where('status','PUBLISHED')->orderByDesc('version')->first();
        if (!$row) return ['version'=>null,'points'=>$this->defaults()];
        return [
            'version'=>(int)$row->version,
            'points'=>json_decode((string)$row->points_json,true,512,JSON_THROW_ON_ERROR),
        ];
    }

    public function validateDefinition(array $points): array
    {
        if (!array_is_list($points) || count($points)<1 || count($points)>100) {
            throw ValidationException::withMessages([
                'points_json'=>'A checklist must contain 1 to 100 ordered points.'
            ]);
        }
        $usedKeys=[];$usedNames=[];$clean=[];
        foreach ($points as $i=>$raw) {
            if (!is_array($raw)) throw ValidationException::withMessages([
                'points_json'=>'Invalid point at position '.($i+1)
            ]);
            $key=strtolower(trim((string)($raw['key']??'')));
            $label=trim((string)($raw['label']??''));
            $type=trim((string)($raw['type']??''));
            if (!preg_match('/^[a-z][a-z0-9_-]{1,39}$/D',$key) || isset($usedKeys[$key])) {
                throw ValidationException::withMessages([
                    'points_json'=>'Duplicate/invalid point key at position '.($i+1)
                ]);
            }
            $canonicalName=mb_strtolower($label);
            // Current legacy row.item_name has a 100-character validation limit;
            // make future migration compatible without truncating labels.
            if ($label==='' || mb_strlen($label)>100 || isset($usedNames[$canonicalName])) {
                throw ValidationException::withMessages([
                    'points_json'=>'Point name blank, too long or duplicated at position '.($i+1)
                ]);
            }
            if (!array_key_exists($type,self::TYPE_OPTIONS)) {
                throw ValidationException::withMessages([
                    'points_json'=>'Invalid answer type: '.$label
                ]);
            }
            $rawDefects=$raw['defect_values']??[];
            if (!is_array($rawDefects)) throw ValidationException::withMessages([
                'points_json'=>'Defect values must be a list: '.$label
            ]);
            $defects=array_values(array_unique(array_map(
                static fn($v)=>strtolower(trim((string)$v)), $rawDefects
            )));
            if (array_diff($defects,self::TYPE_OPTIONS[$type])) {
                throw ValidationException::withMessages([
                    'points_json'=>'Defect answers are incompatible with the type: '.$label
                ]);
            }
            $weight=filter_var($raw['weight']??1,FILTER_VALIDATE_INT);
            if ($weight===false || $weight<1 || $weight>10) {
                throw ValidationException::withMessages([
                    'points_json'=>'Point weight must be 1 to 10: '.$label
                ]);
            }
            $usedKeys[$key]=true;$usedNames[$canonicalName]=true;
            $clean[]=[
                'key'=>$key,'label'=>$label,'type'=>$type,
                'required'=>(bool)($raw['required']??true),
                'critical'=>(bool)($raw['critical']??false),
                'weight'=>(int)$weight,'defect_values'=>$defects,
            ];
        }
        return $clean;
    }

    public function publish(array $points,int $adminId): int
    {
        if ($adminId<=0) abort(403,'Valid ERP Admin required.');
        $validated=$this->validateDefinition($points);
        if (!Schema::hasTable('am206_inspection_master_versions')) {
            abort(503,'AM206 Inspection Master migration is not installed.');
        }
        return DB::transaction(function () use ($validated,$adminId): int {
            // Existing rows are locked. Unique version column prevents
            // publishing the same numbered version concurrently.
            DB::table('am206_inspection_master_versions')->lockForUpdate()->get();
            $version=1+(int)DB::table('am206_inspection_master_versions')->max('version');
            DB::table('am206_inspection_master_versions')
                ->where('status','PUBLISHED')
                ->update(['status'=>'SUPERSEDED','updated_at'=>now()]);
            DB::table('am206_inspection_master_versions')->insert([
                'version'=>$version,'status'=>'PUBLISHED',
                'points_json'=>json_encode($validated,JSON_THROW_ON_ERROR),
                'published_by_admin_id'=>$adminId,'published_at'=>now(),
                'created_at'=>now(),'updated_at'=>now()
            ]);
            return $version;
        },3);
    }
}
