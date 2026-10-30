<?php
declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use App\Services\Am206InspectionMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class Am206InspectionMasterController extends Controller
{
    private function admin(Request $request): ErpAuthUser
    {
        $user=$request->user();
        abort_unless($user instanceof ErpAuthUser && $user->active && $user->isAdmin(),
            403,'Active ERP Owner/Admin login required.');
        return $user;
    }

    public function index(Request $request,Am206InspectionMaster $master)
    {
        $this->admin($request);
        $active=$master->active();
        $history=Schema::hasTable('am206_inspection_master_versions')
            ? DB::table('am206_inspection_master_versions')->orderByDesc('version')
                ->limit(20)->get(['version','status','published_at'])
            : collect();
        return view('inspection-master-am206.index',[
            'points'=>$active['points'],
            'version'=>$active['version'],
            'history'=>$history,
            'types'=>Am206InspectionMaster::TYPE_OPTIONS,
        ]);
    }

    public function publish(Request $request,Am206InspectionMaster $master)
    {
        $user=$this->admin($request);
        $data=$request->validate(['points_json'=>['required','string','max:250000']]);
        try {
            $points=json_decode($data['points_json'],true,512,JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw ValidationException::withMessages(['points_json'=>'Invalid checklist JSON.']);
        }
        if (!is_array($points)) {
            throw ValidationException::withMessages(['points_json'=>'Invalid checklist rows.']);
        }
        $version=$master->publish($points,(int)$user->id);
        return redirect()->route('erp.inspection-master.am206.index')
            ->with('success',"Inspection Master v{$version} published. Existing Staff App still uses the legacy 30-point form until dynamic integration is activated.");
    }
}
