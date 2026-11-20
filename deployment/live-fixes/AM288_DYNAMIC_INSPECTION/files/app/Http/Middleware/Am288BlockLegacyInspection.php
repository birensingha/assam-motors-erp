<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Attach to the EXISTING legacy fixed-30 POST route (not to GET).
 * Hold the Job Card row lock throughout the legacy save: dynamic assignment
 * obtains that same lock, preventing a simultaneous legacy/dynamic write.
 * This is a route integration requirement, not yet installed on staging.
 */
final class Am288BlockLegacyInspection
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->isMethod('POST')) return $next($request);
        if (!Schema::hasTable('am206_job_inspection_forms')) return $next($request);
        $job=(int)($request->route('job') ??
            $request->route('jobId') ??
            $request->route('jobCard') ??
            $request->route('id') ?? 0);
        abort_unless($job>0,422,'Legacy inspection Job Card route ID is missing.');
        return DB::transaction(function() use ($request,$next,$job) {
            $card=DB::table('job_cards')->where('id',$job)->lockForUpdate()->first();
            abort_unless($card,404,'Job Card not found.');
            $form=DB::table('am206_job_inspection_forms')
                ->where('job_card_id',$job)->first();
            if ($form && strtoupper((string)$form->state)!=='DRAFT') {
                abort(409,
                    'AM288 dynamic inspection assigned: legacy 30-point POST blocked. '.
                    'Use the versioned native inspection endpoint.');
            }
            return $next($request);
        },3);
    }
}
