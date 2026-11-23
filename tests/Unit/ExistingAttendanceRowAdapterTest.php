<?php

namespace Tests\Unit;

use App\Services\Attendance\ExistingAttendanceRowAdapter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ExistingAttendanceRowAdapterTest extends TestCase
{
    public function test_off_maps_to_weekly_off_not_absent(): void
    {
        $row=(new ExistingAttendanceRowAdapter())->map([
            'staff_id'=>4,'staff_code'=>'AM9472','staff_name'=>'Example',
            'date'=>'2026-11-02','status'=>'off','worked_hours'=>'0.00'
        ]);
        self::assertSame('weekly_off',$row['status']);
        self::assertSame('AM9472',$row['staff_id']);
        self::assertSame(0,$row['work_minutes']);
        self::assertFalse($row['verified']);
    }

    public function test_worked_hours_and_missing_checkout(): void
    {
        $row=(new ExistingAttendanceRowAdapter())->map([
            'staff_id'=>4,'date'=>'2026-11-03','status'=>'present',
            'check_in'=>'2026-11-03 09:00:00','check_out'=>null,
            'worked_hours'=>'7.50','late'=>1
        ]);
        self::assertSame(450,$row['work_minutes']);
        self::assertTrue($row['missing_checkout']);
        self::assertTrue($row['late_flag']);
        self::assertSame(0,$row['late_minutes']);
    }

    public function test_unrecognized_saved_status_is_not_silently_absent(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ExistingAttendanceRowAdapter())->map([
            'staff_id'=>4,'date'=>'2026-11-04','status'=>'something_new'
        ]);
    }
}
