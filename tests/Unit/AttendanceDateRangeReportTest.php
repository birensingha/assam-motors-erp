<?php

namespace Tests\Unit;

use App\Services\Attendance\AttendanceDateRangeReport;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AttendanceDateRangeReportTest extends TestCase
{
    public function test_inclusive_date_filter_and_staff_summary(): void
    {
        $result = (new AttendanceDateRangeReport())->build([
            ['staff_id'=>'AM9472','staff_name'=>'Kapil','date'=>'2026-11-01','status'=>'present','work_minutes'=>480],
            ['staff_id'=>'AM9472','staff_name'=>'Kapil','date'=>'2026-11-02','status'=>'half_day','work_minutes'=>240,'late_minutes'=>20],
            ['staff_id'=>'AM7887','staff_name'=>'Firajul','date'=>'2026-11-02','status'=>'present','work_minutes'=>510,'overtime_minutes'=>30],
            ['staff_id'=>'AM9472','staff_name'=>'Kapil','date'=>'2026-11-03','status'=>'absent'],
        ], '2026-11-01', '2026-11-02');
        self::assertSame(3, $result['total_rows']);
        self::assertCount(2, $result['staff_summary']);
        self::assertSame('2026-11-01', $result['rows'][0]['date']);
        $kapil = array_values(array_filter($result['staff_summary'], fn ($row) => $row['staff_id'] === 'AM9472'))[0];
        self::assertSame(720, $kapil['work_minutes']);
        self::assertSame(20, $kapil['late_minutes']);
        self::assertSame(1, $kapil['status_counts']['half_day']);
        self::assertTrue($result['payroll_ready']);
    }

    public function test_staff_filter_and_missing_checkout_review(): void
    {
        $report = (new AttendanceDateRangeReport())->build([
            ['staff_id'=>'AM9472','date'=>'2026-11-03','status'=>'present','check_in'=>'09:15'],
            ['staff_id'=>'AM7887','date'=>'2026-11-03','status'=>'present','check_in'=>'09:00','check_out'=>'18:00'],
        ], '2026-11-01','2026-11-30','AM9472');
        self::assertSame(1, $report['total_rows']);
        self::assertSame(1, $report['staff_summary'][0]['review_days']);
        self::assertFalse($report['payroll_ready']);
    }

    public function test_csv_formula_is_neutralized(): void
    {
        $service = new AttendanceDateRangeReport();
        $report = $service->build([
            ['staff_id'=>'AM1','staff_name'=>'=HYPERLINK("bad")','date'=>'2026-11-02','status'=>'present']
        ], '2026-11-02','2026-11-02');
        $csv = $service->csv($report);
        self::assertStringContainsString("'=HYPERLINK", $csv);
        self::assertStringContainsString('Needs Review', $csv);
    }

    public function test_duplicate_attendance_date_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new AttendanceDateRangeReport())->build([
            ['staff_id'=>'AM9472','date'=>'2026-11-02','status'=>'present'],
            ['staff_id'=>'AM9472','date'=>'2026-11-02','status'=>'present'],
        ], '2026-11-01','2026-11-30');
    }

    public function test_invalid_range_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new AttendanceDateRangeReport())->build([], '2026-11-30','2026-11-01');
    }
}
