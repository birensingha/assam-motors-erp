<?php
declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * AM179: read-only scoring from the EXISTING submitted Job Card inspection.
 * Generating/viewing a report never changes the job-card lifecycle.
 * This is an indicative inspection condition score, NOT a certified valuation.
 */
final class VehicleHealthReportService
{
    private const SCORE = [
        'good' => 100, 'yes' => 100, 'available' => 100,
        'normal' => 100, 'pass' => 100, 'ok' => 100,
        'fair' => 55, 'low' => 55, 'high' => 55,
        'poor' => 0, 'no' => 0, 'not available' => 0,
        'not_available' => 0, 'fail' => 0, 'faulty' => 0,
    ];

    public function report(int $jobId): array
    {
        $job = DB::table('job_cards')->where('id', $jobId)->first();
        if (!$job) abort(404);
        $inspectionStatus = strtolower(trim((string) ($job->inspection_status ?? '')));
        if ($inspectionStatus !== 'completed') {
            throw ValidationException::withMessages([
                'inspection' => 'Inspection is not submitted to ERP. Vehicle Health Report cannot be generated from a device-only draft.',
            ]);
        }
        if (!Schema::hasTable('job_card_inspection')) {
            throw ValidationException::withMessages(['inspection' => 'Inspection records are unavailable.']);
        }
        $raw = DB::table('job_card_inspection')->where('job_card_id', $jobId)->orderBy('id')->get();
        if ($raw->isEmpty()) {
            throw ValidationException::withMessages(['inspection' => 'No saved inspection points found in ERP.']);
        }
        $groups = [];
        $points = [];
        $defects = [];
        $total = 0.0;
        $weightTotal = 0.0;
        foreach ($raw as $entry) {
            $name = trim((string) ($entry->item_name ?? ''));
            $status = strtolower(trim((string) ($entry->status ?? '')));
            $remarks = trim((string) ($entry->remarks ?? ''));
            $group = $this->category($name);
            $isNA = in_array($status, ['n/a', 'na', 'not applicable', 'not_applicable'], true);
            // Fuel, serial number, odometer and other numeric/text measurements
            // must not be graded as Good/Poor: show as measurements only.
            $score = $isNA ? null : (self::SCORE[$status] ?? null);
            $critical = $this->isCritical($name);
            $weight = $critical ? 2 : 1;
            $defective = in_array($status, ['fair','poor','no','not available','not_available','fail','faulty','low','high'], true);
            $point = [
                'name' => $name, 'status' => $status, 'remarks' => $remarks,
                'category' => $group, 'score' => $score, 'critical' => $critical,
                'defect' => $defective,
            ];
            $points[] = $point;
            if (!isset($groups[$group])) $groups[$group] = ['points'=>0, 'measured'=>0, 'total'=>0.0, 'weight'=>0.0, 'defects'=>0];
            $groups[$group]['points']++;
            if ($score !== null) {
                $total += $score * $weight;
                $weightTotal += $weight;
                $groups[$group]['total'] += $score * $weight;
                $groups[$group]['weight'] += $weight;
                $groups[$group]['measured']++;
            }
            if ($defective) {
                $defects[] = $point;
                $groups[$group]['defects']++;
            }
        }
        if ($weightTotal <= 0) {
            throw ValidationException::withMessages([
                'inspection' => 'No scoreable inspection results. Admin must define graded selections before issuing a Health percentage.',
            ]);
        }
        $groupScores = [];
        foreach ($groups as $name => $row) {
            $groupScores[] = [
                'name' => $name, 'points' => $row['points'], 'defects' => $row['defects'],
                'score' => $row['weight'] > 0 ? (int) round($row['total'] / $row['weight']) : null,
            ];
        }
        $health = (int) round($total / $weightTotal);
        $safetyOpen = count(array_filter($defects, static fn ($p) => $p['critical']));
        $valuation = Schema::hasTable('am_vehicle_health_valuations')
            ? DB::table('am_vehicle_health_valuations')->where('job_card_id', $jobId)->first()
            : null;
        $systemValue = $valuation ? $this->indicativeValue(
            (float) $valuation->reference_market_price,
            (int) ($job->vehicle_year ?? 0),
            (int) $valuation->odometer_km,
            $health,
            (float) $valuation->estimated_repair_cost
        ) : null;
        return [
            'job' => $job, 'points' => $points, 'categories' => $groupScores,
            'health' => $health, 'defects' => $defects, 'safety_open' => $safetyOpen,
            'valuation' => $valuation, 'system_value' => $systemValue,
            'is_final' => $valuation !== null && $valuation->admin_final_value !== null,
            'generated_at' => now()->timezone('Asia/Kolkata')->format('d M Y, h:i A'),
            'report_no' => 'AM-VHR-' . str_pad((string) $jobId, 6, '0', STR_PAD_LEFT),
        ];
    }

    /** Transparently benchmark-based; not a live resale market quotation. */
    public function indicativeValue(float $referencePrice, int $modelYear, int $km, int $health, float $repair): int
    {
        if ($referencePrice <= 0) return 0;
        $age = $modelYear > 1900 ? max(0, (int) date('Y') - $modelYear) : 0;
        $ageFactor = max(.55, 1 - min(15, $age) * .025);
        $kmFactor = max(.7, 1 - min(240000, $km) / 240000 * .3);
        $healthFactor = .65 + (max(0,min(100,$health)) / 100) * .35;
        return (int) (round(max(0, $referencePrice * $ageFactor * $kmFactor * $healthFactor - $repair) / 1000) * 1000);
    }

    private function category(string $name): string
    {
        $s = strtolower($name);
        if (preg_match('/brak|steer|suspension|tyre|tire|wheel|abs|airbag|seat.?belt/i', $s)) return 'Safety & Running Gear';
        if (preg_match('/engine|coolant|oil|leak|exhaust|transmission|clutch|fluid/i', $s)) return 'Engine & Mechanical';
        if (preg_match('/batter|lamp|light|wiper|horn|electri|switch|cel/i', $s)) return 'Electrical & Lighting';
        if (preg_match('/body|glass|paint|door|interior|windshield|mirror|dent/i', $s)) return 'Body & Interior';
        return 'General & Accessories';
    }

    private function isCritical(string $name): bool
    {
        return (bool) preg_match('/brak|steer|suspension|tyre|tire|wheel|airbag|seat.?belt/i', $name);
    }
}
