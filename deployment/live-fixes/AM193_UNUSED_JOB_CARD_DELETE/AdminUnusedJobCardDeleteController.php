<?php

namespace App\Http\Controllers\Web;

use App\Services\JobCardDeleteGuard;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/** AM193 — Admin-only audited removal of JC before any actual work starts. */
final class AdminUnusedJobCardDeleteController extends Controller
{
    public function destroy(Request $request, int $job): RedirectResponse
    {
        $data=$request->validate([
            'reason'=>['required','string','min:10','max:600'],
            'confirmation'=>['required','in:DELETE'],
        ]);

        abort_unless(Schema::hasTable('am193_deleted_job_card_audits'),
            503,'JC deletion audit table unavailable; no changes made.');

        try {
            DB::transaction(function () use ($job,$data): void {
                $card=DB::table('job_cards')->where('id',$job)->lockForUpdate()->first();
                if (!$card) {
                    throw ValidationException::withMessages([
                        'delete'=>'Job Card not found or already deleted.',
                    ]);
                }
                // Re-evaluate all references, statuses and financial effects
                // UNDER THE JC row lock immediately before any deletion.
                $check=JobCardDeleteGuard::inspect($card);
                if ($check['blockers']) {
                    throw ValidationException::withMessages([
                        'delete'=>'Job Card cannot be deleted because work/transactions '
                            .'exist: '.implode('; ',array_slice($check['blockers'],0,5))
                            .'. Only unused Job Cards can be deleted.',
                    ]);
                }

                $archive=[
                    'job_card'=>(array)$card,
                    'administrative_children'=>$check['children'],
                ];
                $snapshot=json_encode($archive,
                    JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE|
                    JSON_PARTIAL_OUTPUT_ON_ERROR
                );
                if ($snapshot===false || strlen($snapshot)>4*1024*1024) {
                    throw ValidationException::withMessages([
                        'delete'=>'Job Card archive exceeds safe limit. Deletion cancelled.',
                    ]);
                }
                $actor=Auth::user();
                $number=(string)($card->job_card_no ?? $card->job_no ??
                    $card->job_card_number ?? ('JC #'.$job));
                $auditId=DB::table('am193_deleted_job_card_audits')->insertGetId([
                    'job_card_id'=>$job,
                    'job_card_number'=>mb_substr($number,0,120),
                    'actor_id'=>$actor?->id,
                    'actor_name'=>(string)($actor?->name ?? 'ERP Admin'),
                    'reason'=>trim((string)$data['reason']),
                    'snapshot_json'=>$snapshot,
                    'created_at'=>now('Asia/Kolkata'),
                ]);
                if (!$auditId) {
                    throw new \RuntimeException('JC deletion audit not saved');
                }

                // The archive holds ALL the removed administrative child data.
                // Unknown or financial tables are never auto-deleted.
                // Delete inspection photos/checklist first, parent inspection
                // rows afterwards. Any unexpected FK still fails atomically.
                $deleteOrder = [
                    'job_card_inspection_photos'=>0,
                    'job_card_inspection_items'=>1,
                    'job_card_inspection_checks'=>1,
                    'job_card_inspection_checklist'=>1,
                    'job_card_inspection_results'=>1,
                    'job_card_inspection_reports'=>1,
                    'job_card_parts'=>2,
                    'job_card_labour'=>2,
                    'job_card_inspection_assignments'=>3,
                    'job_card_inspection'=>4,
                    'job_card_change_audits'=>5,
                ];
                $toDelete=$check['children'];
                uksort($toDelete,static fn($a,$b) =>
                    ($deleteOrder[$a] ?? 9) <=> ($deleteOrder[$b] ?? 9));
                foreach ($toDelete as $table=>$ref) {
                    $count=DB::table($table)
                        ->where($ref['column'],$job)->delete();
                    if ($count!==count($ref['rows'])) {
                        throw new \RuntimeException(
                            "JC child {$table} changed during delete; transaction rolled back"
                        );
                    }
                }

                $removed=DB::table('job_cards')->where('id',$job)->delete();
                if ($removed!==1) {
                    throw new \RuntimeException('JC record changed during delete');
                }
            },3);
        } catch (QueryException $ex) {
            report($ex);
            throw ValidationException::withMessages([
                'delete'=>'A financial/workshop reference or FK constraint prevents '
                    .'deletion. Job Card preserved. Check linked records.',
            ]);
        }

        return redirect()->route('erp.job-cards.index')
            ->with('success','Unused Job Card deleted. Complete audit snapshot retained.');
    }
}
