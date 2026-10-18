<?php
declare(strict_types=1);
namespace App\Support;

/**
 * P60_SERVER_SINGLE_SOURCE_OF_TRUTH_V636
 * The 30 vehicle inspection entries match the published Android v6.3.9
 * inspection workflow, and are used by both Staff Portal and Admin.
 * Historical 10-item completed checklists remain read-only, unchanged.
 */
final class VehicleInspectionChecklist
{
    public const ITEMS = [
        'Key Condition / Number of Keys',
        'Odometer / Dashboard Kilometres',
        'Fuel Level / Gauge Reading',
        'Spare Wheel / Spare Tyre Condition',
        'Jack / Wheel Wrench / Tool Kit',
        'Documents / Accessories Received',
        'Body Condition / Dents / Scratches',
        'Bumpers / Grille / Exterior Trim',
        'All Glass / Windshield / Windows',
        'Mirrors / Door Handles / Door Locks',
        'Wipers / Washer System',
        'Tyres (All Wheels) / Tread / Pressure',
        'Wheel Rims / Nuts / Alignment Signs',
        'Headlights / Tail / Brake / Indicators',
        'Horn / Lights / Electrical Equipment',
        'Check Engine Light (MIL)',
        'ABS / Airbag / Other Warning Lights',
        'Battery Condition / Battery Serial No',
        'Interior / Dashboard / Seats',
        'Seat Belts / Safety Equipment',
        'AC / Blower / Heating',
        'Engine Oil Level / Oil Condition',
        'Coolant / Radiator / Reservoir Level',
        'Brake / Steering / Other Fluid Levels',
        'Oil Seepage / Fluid Leakage',
        'Engine Bay / Belts / Hoses / Wiring',
        'Engine Sound / Vibration / Smoke',
        'Brake System / Parking Brake',
        'Steering / Suspension',
        'Underbody / Exhaust / Transmission Leaks',
    ];

    public static function items(): array { return self::ITEMS; }
    public static function valid(array $items): bool
    {
        if(count($items)!==count(self::ITEMS))return false;
        $names=array_map(static fn($item)=>trim((string)($item['item_name']??'')),array_values($items));
        if(count(array_unique($names))!==count(self::ITEMS))return false;
        if(array_diff($names,self::ITEMS)||array_diff(self::ITEMS,$names))return false;
        foreach($items as $item)if(!in_array((string)($item['status']??''),['good','fair','poor'],true))return false;
        return true;
    }
}
