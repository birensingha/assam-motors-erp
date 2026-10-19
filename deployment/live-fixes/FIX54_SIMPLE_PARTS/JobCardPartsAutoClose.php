<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * FIX54_SIMPLE_PARTS: Stock is reconciled automatically, once, on JC Close.
 * Nothing is deducted merely by estimate release or by editing planned lines.
 * Must be called INSIDE the locked Job Card closure DB transaction.
 */
final class JobCardPartsAutoClose
{
    public static function reconcile(int $jobId, int $actorId, string $actorName): array
    {
        if (!Schema::hasTable('job_card_parts') || !Schema::hasTable('parts_master')
            || !Schema::hasTable('inventory_movements')) {
            throw ValidationException::withMessages(['status'=>'Parts inventory tables unavailable; cannot safely close.']);
        }
        $lines=DB::table('job_card_parts')->where('job_card_id',$jobId)->orderBy('id')->lockForUpdate()->get();
        $processed=0;$already=0;
        foreach($lines as $ln){
            $state=strtoupper(trim((string)($ln->issue_status??'PLANNED')));
            if(in_array($state,['RETURNED','REVERSED','CANCELLED'],true)){
                throw ValidationException::withMessages(['status'=>'Part #'.$ln->id.' is returned/reversed but remains in billing. Correct/remove it first.']);
            }
            $masterId=(int)($ln->part_master_id??0);
            $qty=round((float)($ln->qty??0),3);
            if($masterId<=0 || $qty<=0){
                throw ValidationException::withMessages(['status'=>'Part #'.$ln->id.' has missing Parts Master or invalid Qty.']);
            }
            $part=DB::table('parts_master')->where('id',$masterId)->lockForUpdate()->first();
            if(!$part)throw ValidationException::withMessages(['status'=>'Parts Master missing for JC Part #'.$ln->id]);
            $ref='JC-PART-'.$ln->id.'-ISSUE';
            $history=DB::table('inventory_movements')->where('job_card_id',$jobId)
                ->where('part_master_id',$masterId)->where('reference_no',$ref)->lockForUpdate()->get();
            if($history->count()>1){
                throw ValidationException::withMessages(['status'=>'Duplicate stock issue reference found for Part #'.$ln->id.'; manually review.']);
            }
            if($history->count()===1){
                $movement=$history->first();
                if(strtoupper((string)$movement->movement_type)!=='JOB_ISSUE'
                    || abs((float)$movement->qty_out-$qty)>0.0001
                    || abs((float)$movement->qty_in)>0.0001){
                    throw ValidationException::withMessages(['status'=>'Part #'.$ln->id.' stock issue differs from current Qty. Reconcile stock before closing.']);
                }
                if(!in_array($state,['ISSUED','FITTED','AUTO_SETTLED'],true)){
                    throw ValidationException::withMessages(['status'=>'Part #'.$ln->id.' has existing stock movement but still appears planned. Review stock history.']);
                }
                $already++;continue;
            }
            if(in_array($state,['ISSUED','FITTED','AUTO_SETTLED'],true)){
                throw ValidationException::withMessages(['status'=>'Part #'.$ln->id.' is marked issued without a matching stock movement. Cannot deduct twice.']);
            }
            if(!in_array($state,['','PLANNED','PENDING','REQUISITIONED','NOT ISSUED','NOT_ISSUED'],true)){
                throw ValidationException::withMessages(['status'=>'Unsupported Part #'.$ln->id.' status; cannot automatically issue.']);
            }
            // Detect mismatched historical references before creating a new out movement.
            $other=DB::table('inventory_movements')->where('job_card_id',$jobId)
                ->where('reference_no',$ref)->lockForUpdate()->exists();
            if($other)throw ValidationException::withMessages(['status'=>'Part #'.$ln->id.' conflicting inventory movement reference.']);
            $stock=round((float)$part->stock_qty,3);
            if($stock<0 || $qty>$stock+0.00001){
                throw ValidationException::withMessages(['status'=>'Stock insufficient for '.$ln->part_name.'. Required '.$qty.', available '.$stock.'. No stock deducted; Job Card not closed.']);
            }
            $newBalance=round($stock-$qty,3);
            DB::table('parts_master')->where('id',$masterId)->update(['stock_qty'=>$newBalance]);
            DB::table('inventory_movements')->insert([
                'part_master_id'=>$masterId,'job_card_id'=>$jobId,
                'movement_type'=>'JOB_ISSUE','qty_in'=>0,'qty_out'=>$qty,
                'balance_after'=>$newBalance,'reference_no'=>$ref,
                'note'=>'FIX54 automatic parts stock posting at Job Card Close',
                'created_at'=>now('Asia/Kolkata'),
            ]);
            DB::table('job_card_parts')->where('id',$ln->id)->update(['issue_status'=>'AUTO_SETTLED']);
            if(Schema::hasTable('job_card_change_audits')){
                DB::table('job_card_change_audits')->insert([
                    'job_card_id'=>$jobId,'entity_type'=>'JOB_CARD_PART','entity_id'=>$ln->id,
                    'action'=>'PART_AUTO_ISSUED_ON_CLOSE',
                    'before_json'=>json_encode((array)$ln,JSON_INVALID_UTF8_SUBSTITUTE),
                    'after_json'=>json_encode(['issue_status'=>'AUTO_SETTLED','qty'=>$qty,
                        'stock_before'=>$stock,'stock_after'=>$newBalance,'reference_no'=>$ref]),
                    'reason'=>'Automatic single stock deduction at Job Card Close (FIX54 simple parts)',
                    'actor_id'=>$actorId,'actor_name'=>$actorName,
                    'created_at'=>now('Asia/Kolkata'),'updated_at'=>now('Asia/Kolkata')
                ]);
            }
            $processed++;
        }
        return ['automatically_issued'=>$processed,'already_issued'=>$already];
    }
}
