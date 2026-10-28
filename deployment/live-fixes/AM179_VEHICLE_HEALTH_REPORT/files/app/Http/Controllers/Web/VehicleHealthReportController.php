<?php
declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\VehicleHealthReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * AM179. Routes MUST be installed inside the EXISTING authorized
 * erp.job-cards and staff.inspections web middleware groups.
 * No anonymous/public PDF endpoints or share tokens are created.
 */
final class VehicleHealthReportController extends Controller
{
    public function adminView(Request $request, int $job, VehicleHealthReportService $service)
    {
        $this->requireLogin($request);
        return view('vehicle-health.report', $service->report($job) + ['is_admin'=>true]);
    }

    public function staffView(Request $request, int $job, VehicleHealthReportService $service)
    {
        $this->authorizeInspector($request, $job);
        return view('vehicle-health.report', $service->report($job) + ['is_admin'=>false]);
    }

    public function adminPdf(Request $request, int $job, VehicleHealthReportService $service): Response
    {
        $this->requireLogin($request);
        return $this->renderPdf($request, $service->report($job) + ['is_admin'=>true]);
    }

    public function staffPdf(Request $request, int $job, VehicleHealthReportService $service): Response
    {
        $this->authorizeInspector($request, $job);
        return $this->renderPdf($request, $service->report($job) + ['is_admin'=>false]);
    }

    public function saveValuation(Request $request, int $job, VehicleHealthReportService $service)
    {
        $this->requireLogin($request);
        if (!Schema::hasTable('am_vehicle_health_valuations')) {
            abort(503, 'Vehicle Health valuation migration is not installed.');
        }
        $data = $request->validate([
            'reference_market_price' => ['required','numeric','min:1','max:100000000'],
            'odometer_km' => ['required','integer','min:0','max:9999999'],
            'estimated_repair_cost' => ['required','numeric','min:0','max:100000000'],
            'admin_final_value' => ['nullable','numeric','min:0','max:100000000'],
            'admin_note' => ['nullable','string','max:1000'],
        ]);
        // Do not allow a final value without traceable rationale.
        if (isset($data['admin_final_value']) && trim((string) ($data['admin_note'] ?? '')) === '') {
            throw ValidationException::withMessages(['admin_note'=>'Reason/market assessment is required when approving the final valuation.']);
        }
        DB::transaction(function () use ($job, $data, $request, $service): void {
            $card = DB::table('job_cards')->where('id', $job)->lockForUpdate()->first();
            if (!$card) abort(404);
            // Completion and score must derive from the saved ERP inspection, not the phone draft.
            $report = $service->report($job);
            $system = $service->indicativeValue(
                (float) $data['reference_market_price'],
                (int) ($card->vehicle_year ?? 0),
                (int) $data['odometer_km'],
                $report['health'],
                (float) $data['estimated_repair_cost']
            );
            $existing = DB::table('am_vehicle_health_valuations')->where('job_card_id', $job)->first();
            $actor = (string) ($request->user()?->name ?? $request->user()?->id ?? 'ERP Admin');
            DB::table('am_vehicle_health_valuations')->updateOrInsert(
                ['job_card_id' => $job],
                [
                    'reference_market_price' => $data['reference_market_price'],
                    'odometer_km' => $data['odometer_km'],
                    'estimated_repair_cost' => $data['estimated_repair_cost'],
                    'indicative_value_snapshot' => $system,
                    'health_snapshot' => $report['health'],
                    'admin_final_value' => $data['admin_final_value'] ?? null,
                    'admin_note' => trim((string) ($data['admin_note'] ?? '')),
                    'approved_by' => isset($data['admin_final_value']) ? $actor : null,
                    'approved_at' => isset($data['admin_final_value']) ? now() : null,
                    'created_at' => $existing->created_at ?? now(),
                    'updated_at' => now(),
                ]
            );
            // Valuation audit: preserve prior values and actor. Never overwrite history.
            DB::table('am_vehicle_health_valuation_audit')->insert([
                'job_card_id' => $job, 'actor' => $actor,
                'previous_json' => json_encode($existing, JSON_UNESCAPED_UNICODE),
                'updated_json' => json_encode($data + ['system_value'=>$system, 'health'=>$report['health']], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
            ]);
        });
        return redirect()->route('erp.job-cards.vehicle-health', ['job'=>$job])
            ->with('success', 'Vehicle Health valuation saved. PDF report is optional.');
    }

    private function requireLogin(Request $request): void
    {
        // Admin routes sit beside the existing AdminJobCardPageController route,
        // but must not depend solely on a potentially broad "auth" middleware.
        // Deny unknown roles rather than accidentally authorizing staff members.
        $user = $request->user();
        abort_unless($user !== null, 403, 'ERP login required.');
        $role = strtolower(trim((string) ($user->role ?? $user->role_name ?? '')));
        $allowed = in_array($role, ['owner','admin','administrator','super admin','super_admin'], true)
            || (bool) ($user->is_admin ?? false) === true;
        abort_unless($allowed, 403, 'Owner/Admin access required for Vehicle Health reports and valuation.');
    }

    private function authorizeInspector(Request $request, int $job): void
    {
        $user = $request->user();
        $staffId = (int) ($user?->staff_id ?? 0);
        abort_if($staffId < 1, 403, 'Staff login required.');
        $card = DB::table('job_cards')->where('id', $job)->first();
        abort_if(!$card, 404);
        abort_unless(
            $staffId === (int) ($card->technician_id ?? 0)
            || $staffId === (int) ($card->inspection_submitted_by ?? 0),
            403, 'Vehicle Inspection is not assigned to you.'
        );
    }

    private function renderPdf(Request $request, array $report): Response
    {
        if (!class_exists(\Dompdf\Dompdf::class)) {
            abort(503, 'PDF engine is unavailable. Install dompdf/dompdf before enabling this feature.');
        }
        $logoPath = public_path('images/assam-motors-logo.png');
        $report['logo_data'] = is_file($logoPath) && filesize($logoPath) < 500000
            ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath))
            : null;
        $html = view('vehicle-health.pdf', $report)->render();
        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $pdf = new \Dompdf\Dompdf($options);
        $pdf->setPaper('A4', 'portrait');
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->render();
        $name = 'Assam-Motors-Vehicle-Health-' . (int)$report['job']->id . '.pdf';
        $disposition = $request->boolean('download') ? 'attachment' : 'inline';
        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$name.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
