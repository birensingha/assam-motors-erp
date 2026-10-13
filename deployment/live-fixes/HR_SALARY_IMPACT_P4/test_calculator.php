<?php
require __DIR__.'/payload/app/Services/HrSalaryImpactCalculator.php';
use App\Services\HrSalaryImpactCalculator as Calc;
$r=Calc::calculate(18000,26,5.0,500.0,2,110.5);
assert($r['absent_days']??2===2);
if ($r['absence_salary_cost_allocation'] !== 1384.62 || $r['estimated_missed_productive_hours'] !== 10.0 || $r['estimated_labour_opportunity_value'] !== 5000.0 || $r['salary_deduction'] !== 0.0) throw new Exception('Expected example mismatch '.json_encode($r));
$r=Calc::calculate(18000,26,5.0,null,0,0);
if ($r['absence_salary_cost_allocation'] !== 0.0 || $r['estimated_labour_opportunity_value'] !== null || $r['salary_cost_per_recorded_productive_hour'] !== null) throw new Exception('Zero case mismatch');
$r=Calc::calculate(18000,26,5.0,500.0,50,15);
if($r['confirmed_absent_days']!==26)throw new Exception('Cap check failed');
try{Calc::calculate(10000,0,5.0,null,0,0);throw new Exception('Missing bounds');}catch(InvalidArgumentException $e){}
echo "PASS: fixed salary allocation, absence opportunity, zero case, clamps, input bounds and no payroll deduction\n";