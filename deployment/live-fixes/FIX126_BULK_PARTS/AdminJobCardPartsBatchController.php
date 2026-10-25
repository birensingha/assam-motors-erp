<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * FIX126 - One Job Card action issues all eligible parts; another confirms all fitted.
 *
 * Stock balance, per-part movements and audit lines are written atomically.
 * No requisition is needed for bulk issue. Existing individual methods/routes
 * remain installed, but are hidden from the Job Card billing table.
 */
final class AdminJobCardPartsBatchController extends Controller
{
    private function admin(Request $request): ErpAuthUser
    {
        $user=$request->user();
        abort_unless($user instanceof ErpAuthUser && $user->active && $user->isAdmin(),
            403, 'Active Admin login required.');
        return $user;
    }

    private function card(int $job): object
    {
        $card=DB::table('job_cards')->where('id',$job)->lockForUpdate()->first();
        if(!$card) abort(404);
        if(!in_array(strtolower(trim((string)$card->status)),
            ['wip','in-progress','in progress','work in progress'], true) ||
            !empty($card->invoice_id)) {
            throw ValidationException::withMessages([
                'part'=>'Bulk Parts Issue/Fitted is allowed only in WIP, before Invoice.'
            ]);
        }
        return $card;
    }

    private function lines(int $job)
    {
        $rows=DB::table('job_card_parts')->where('job_card_id',$job)
            ->orderBy('id')->lockForUpdate()->get();
        if($rows->isEmpty()) throw ValidationException::withMessages([
            'part'=>'No Parts are listed on this Job Card.'
        ]);
        return $rows;
    }

    private function stockMovement(int $job, object $part): ?object
    {
        $ref='JC-PART-'.$part->id.'-ISSUE';
        $all=DB::table('inventory_movements')->where('job_card_id',$job)
            ->where('reference_no',$ref)->lockForUpdate()->get();
        if($all->count()>1) throw ValidationException::withMessages([
            'part'=>'Duplicate stock movement found for '.$part->part_name.'. Stock review required.'
        ]);
        return $all->first();
    }

    private function ensureMovement(int $job, object $part): void
    {
        $movement=$this->stockMovement($job,$part);
        if(!$movement ||
            (string)$movement->movement_type!=='JOB_ISSUE' ||
            (int)$movement->part_master_id!==(int)$part->part_master_id ||
            abs((float)$movement->qty_out-(float)$part->qty)>0.0001) {
            throw ValidationException::withMessages([
                'part'=>'Stock Issue movement missing or mismatched for '.$part->part_name.
                         '. Manual reconciliation required; no changes made.'
            ]);
        }
    }

    private function audit(int $job, object $part, string $action,
        string $newStatus, string $reason, ErpAuthUser $actor): void
    {
        $before=(array)$part;
        $after=$before;
        $after['issue_status']=$newStatus;
        DB::table('job_card_change_audits')->insert([
            'job_card_id'=>$job,
            'entity_type'=>'JOB_CARD_PART',
            'entity_id'=>$part->id,
            'action'=>$action,
            'before_json'=>json_encode($before,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE),
            'after_json'=>json_encode($after,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE),
            'reason'=>$reason,
            'actor_id'=>$actor->id,
            'actor_name'=>(string)($actor->name??'Admin'),
            'created_at'=>now(),
            'updated_at'=>now(),
        ]);
    }

    public function issueAll(Request $request, int $job): RedirectResponse
    {
        $actor=$this->admin($request);
        $data=$request->validate([
            'reason'=>['required','string','min:8','max:1000']
        ]);
        $issued=DB::transaction(function () use ($job,$data,$actor): int {
            $this->card($job);
            $rows=$this->lines($job);
            $issueable=['','PLANNED','PENDING','NOT ISSUED','NOT_ISSUED','REQUISITIONED'];
            $already=['ISSUED','FITTED'];
            $excluded=['RETURNED','REVERSED'];
            $pending=[];
            $totals=[];

            // Validate ALL lines and existing movements before any deduction.
            foreach($rows as $part) {
                $state=strtoupper(trim((string)($part->issue_status??'')));
                if(in_array($state,$excluded,true)) continue;
                if(in_array($state,$already,true)) {
                    $this->ensureMovement($job,$part);
                    continue;
                }
                if(!in_array($state,$issueable,true))
                    throw ValidationException::withMessages([
                        'part'=>'Unknown stock status '.$state.' for '.$part->part_name.'. No parts issued.'
                    ]);
                $master=(int)$part->part_master_id;
                $qty=(float)$part->qty;
                if($master<=0 || !is_finite($qty) || $qty<=0)
                    throw ValidationException::withMessages([
                        'part'=>'Invalid Parts Master link/quantity for '.$part->part_name.'.'
                    ]);
                if($this->stockMovement($job,$part))
                    throw ValidationException::withMessages([
                        'part'=>'Existing stock-out movement for planned '.$part->part_name.
                                 '. Duplicate issue prevented.'
                    ]);
                $pending[]=$part;
                $totals[$master]=($totals[$master]??0)+$qty;
            }
            if(!$pending) return 0;

            // Lock every master in deterministic order. All-or-none stock preflight.
            ksort($totals,SORT_NUMERIC);
            $balances=[];
            $missing=[];
            foreach($totals as $masterId=>$requested) {
                $master=DB::table('parts_master')->where('id',$masterId)
                    ->lockForUpdate()->first();
                $available=$master ? $master->stock_qty : null;
                if(!$master || $available===null || !is_numeric($available) ||
                    (float)$available<0 || (float)$available+0.00001<$requested) {
                    $missing[]=(string)($master->part_name??('Master #'.$masterId)).
                        ' (required '.number_format($requested,3).
                        ', available '.($available===null?'not recorded':number_format((float)$available,3)).')';
                    continue;
                }
                $balances[$masterId]=round((float)$available,3);
            }
            if($missing) throw ValidationException::withMessages([
                'part'=>'Bulk Issue stopped. Stock missing/insufficient for: '.
                    implode('; ',array_slice($missing,0,7)).
                    (count($missing)>7?' (and more)':'').
                    '. Enter/reconcile Purchase & Stock first. NO parts issued.'
            ]);

            // Atomic posting: status, stock-out movement and per-part audit.
            foreach($pending as $part) {
                $master=(int)$part->part_master_id;
                $qty=round((float)$part->qty,3);
                $after=round($balances[$master]-$qty,3);
                if($after<0) throw ValidationException::withMessages([
                    'part'=>'Stock calculation changed for '.$part->part_name.'. All changes rolled back.'
                ]);
                DB::table('parts_master')->where('id',$master)
                    ->update(['stock_qty'=>$after]);
                DB::table('inventory_movements')->insert([
                    'part_master_id'=>$master,
                    'job_card_id'=>$job,
                    'movement_type'=>'JOB_ISSUE',
                    'qty_in'=>0,
                    'qty_out'=>$qty,
                    'balance_after'=>$after,
                    'reference_no'=>'JC-PART-'.$part->id.'-ISSUE',
                    'note'=>'FIX126 authorised bulk JC stock issue: '.$data['reason'],
                    'created_at'=>now(),
                ]);
                DB::table('job_card_parts')->where('id',$part->id)
                    ->where('job_card_id',$job)->update(['issue_status'=>'ISSUED']);
                $this->audit($job,$part,'PART_ISSUED','ISSUED',$data['reason'],$actor);
                $balances[$master]=$after;
            }
            return count($pending);
        },3);

        return redirect()->route('erp.job-cards.show',$job)->with('success',
            $issued>0
             ? $issued.' part line(s) issued from stock together. Stock deducted once per line. Use Mark All Fitted after physical installation.'
             : 'All eligible parts were already issued/fitted. No repeat stock deduction.');
    }

    public function fittedAll(Request $request, int $job): RedirectResponse
    {
        $actor=$this->admin($request);
        $data=$request->validate([
            'reason'=>['required','string','min:8','max:1000']
        ]);
        $fitted=DB::transaction(function () use ($job,$data,$actor): int {
            $this->card($job);
            $rows=$this->lines($job);
            $toFit=[];
            foreach($rows as $part) {
                $state=strtoupper(trim((string)($part->issue_status??'')));
                if(in_array($state,['RETURNED','REVERSED'],true)) continue;
                if(!in_array($state,['ISSUED','FITTED'],true)) {
                    throw ValidationException::withMessages([
                        'part'=>'Cannot Mark All Fitted: '.$part->part_name.
                            ' is '.$state.'. Issue All Parts successfully first. NO parts changed.'
                    ]);
                }
                $this->ensureMovement($job,$part);
                if($state==='ISSUED') $toFit[]=$part;
            }
            foreach($toFit as $part) {
                DB::table('job_card_parts')->where('job_card_id',$job)
                    ->where('id',$part->id)->update(['issue_status'=>'FITTED']);
                $this->audit($job,$part,'PART_FITTED','FITTED',$data['reason'],$actor);
            }
            return count($toFit);
        },3);

        return redirect()->route('erp.job-cards.show',$job)->with('success',
            $fitted>0
                ? $fitted.' part line(s) marked FITTED after confirmation. No second stock deduction.'
                : 'All parts were already fitted/returned. No duplicate changes.');
    }
}
