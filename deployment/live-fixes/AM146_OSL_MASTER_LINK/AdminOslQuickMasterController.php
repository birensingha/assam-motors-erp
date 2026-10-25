<?php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** STAGING AM146: common OSL master, Admin-only. Does NOT create stock or billing. */
final class AdminOslQuickMasterController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $actor=$request->user();
        abort_unless($actor instanceof ErpAuthUser && $actor->active
          && strtolower(trim((string)$actor->role))==='admin',403,'Admin required for OSL Master creation.');
        $data=$request->validate([
           'name'=>['required','string','min:3','max:180'],
        ]);
        $name=mb_strtoupper(preg_replace('/\s+/u',' ',trim((string)$data['name'])));
        $row=DB::transaction(function()use($name){
            $existing=DB::table('workshop_osl_master')
               ->whereRaw('LOWER(TRIM(osl_name))=?',[mb_strtolower($name)])
               ->lockForUpdate()->first();
            if($existing) {
                if(!(bool)$existing->active) {
                    abort(422,'This OSL Master exists but is inactive. Activate it in Master Configuration.');
                }
                return $existing;
            }
            $id=DB::table('workshop_osl_master')->insertGetId([
               'osl_code'=>'OSL-Q-'.strtoupper(substr(str_replace('-','',(string)Str::uuid()),0,14)),
               'osl_name'=>$name,
               'category'=>'OUTSIDE LABOUR',
               'default_cost'=>0,
               'gst_percent'=>0,
               'active'=>1,
               'created_at'=>now(),'updated_at'=>now(),
            ]);
            return DB::table('workshop_osl_master')->where('id',$id)->first();
        },3);
        return response()->json(['ok'=>true,'id'=>(int)$row->id,
            'code'=>(string)$row->osl_code,'name'=>(string)$row->osl_name]);
    }
}