<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use App\Services\VehicleHealthReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * AM181 Android Health PDF handoff.
 * Mount ONLY inside the existing authenticated native API v1 group.
 * Never produce public PDF URLs or bypass Staff/Owner assignment checks.
 */
final class VehicleHealthMobileController extends Controller
{
    public function pdf(Request $request, int $job, VehicleHealthReportService $service): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof ErpAuthUser && (bool)$user->active, 403, 'Valid ERP login required.');
        $card = DB::table('job_cards')->where('id', $job)->first();
        if (!$card) abort(404);
        $owner = (bool)$user->isAdmin();
        $staffId = (int)($user->staff_id ?? 0);
        if (!$owner) {
            abort_unless(
                $staffId > 0 && ($staffId === (int)($card->technician_id ?? 0)
                    || $staffId === (int)($card->inspection_submitted_by ?? 0)),
                403,
                'Health Report is not assigned to this Staff ID.'
            );
        }
        $report = $service->report($job);
        if (!class_exists(\Dompdf\Dompdf::class)) {
            return response()->json(['ok'=>false, 'message'=>'PDF engine not installed on ERP.'], 503);
        }
        $logoPath = public_path('images/assam-motors-logo.png');
        $report['logo_data'] = is_file($logoPath) && filesize($logoPath) < 500000
            ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath))
            : null;
        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $pdf = new \Dompdf\Dompdf($options);
        $pdf->setPaper('A4', 'portrait');
        $pdf->loadHtml(view('vehicle-health.pdf', $report)->render(), 'UTF-8');
        $pdf->render();
        $bytes = $pdf->output();
        if (strlen($bytes) > 5_000_000 || substr($bytes,0,5) !== '%PDF-') {
            return response()->json(['ok'=>false,'message'=>'Report PDF is invalid or too large to share.'], 422);
        }
        return response()->json([
            'ok'=>true,
            'file_name'=>'Assam-Motors-Vehicle-Health-'.$job.'.pdf',
            'mime_type'=>'application/pdf',
            'report_no'=>$report['report_no'],
            'pdf_base64'=>base64_encode($bytes),
        ])->header('Cache-Control','private, no-store, max-age=0')
          ->header('X-Content-Type-Options','nosniff');
    }
}
