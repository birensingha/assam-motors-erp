<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Assam Motors Vehicle Health Report</title>
<style>
@page{size:A4 portrait;margin:18mm 14mm 18mm 14mm}
body{font-family:DejaVu Sans,Arial,sans-serif;color:#24384c;font-size:9pt;line-height:1.45;margin:0}
.header{background:#123959;color:#fff;padding:13px 15px;border-bottom:4px solid #198d81}
.logo{display:inline-block;vertical-align:middle;margin-right:12px}
.logo img{max-width:57px;max-height:57px}
.brand{display:inline-block;vertical-align:middle}
.brand-name{font-size:23pt;letter-spacing:1px;font-weight:bold}
.address{font-size:7.5pt;line-height:1.45}
.report-title{text-align:center;font-size:17pt;font-weight:bold;color:#153e60;margin:17px 0 3px}
.subtitle{text-align:center;color:#647b8e;font-size:8pt;margin-bottom:14px}
.section-title{font-size:11pt;color:#0d466e;border-left:4px solid #168982;background:#edf4f8;padding:6px 9px;font-weight:bold;margin:17px 0 8px}
.meta,.summary,.valuation,.signatures{width:100%;border-collapse:collapse}
.meta td{border:1px solid #d8e2eb;padding:7px;width:25%;vertical-align:top}
.muted{font-size:7.5pt;color:#697d8d}.value{font-weight:bold;font-size:9pt;margin-top:3px}
.summary td{border:1px solid #d8e2eb;padding:11px;vertical-align:middle}
.health-number{font-size:39pt;font-weight:bold;color:#178569}
.report-table{width:100%;border-collapse:collapse;table-layout:fixed}
.report-table th{background:#e9f1f7;color:#163e5f;text-align:left;font-size:8pt;border:1px solid #d2dfe9;padding:6px}
.report-table td{border:1px solid #d5e1ea;padding:6px;vertical-align:top;font-size:8pt;word-wrap:break-word}
.report-table tr{page-break-inside:avoid}
.red{color:#ad3030;font-weight:bold}.green{color:#14835d;font-weight:bold}
.orange{color:#a46c13;font-weight:bold}
.valuation td{border:1px solid #d5e1ea;padding:9px;width:25%}
.val-number{font-size:11pt;font-weight:bold;margin-top:4px}
.warning{background:#fff7e6;border-left:3px solid #d39b26;padding:10px;color:#5e4a22;font-size:8pt;margin-top:10px}
.disclaimer{font-size:7pt;color:#697d8d;margin-top:13px}
.footer{position:fixed;bottom:-12mm;left:0;right:0;border-top:1px solid #cbd7e2;padding-top:5px;font-size:7pt;color:#687f91}
.signatures td{width:33.33%;padding-top:36px;text-align:center;border-bottom:1px solid #a3b8c9}
</style></head><body>
<div class="footer">ASSAM MOTORS · www.assammotors.com · 9864043620 · {{ $report_no }} · Confidential customer report</div>
<div class="header"><div class="logo">
@if(!empty($logo_data))<img src="{{ $logo_data }}" alt="Assam Motors Logo">
@else<span style="font-size:24pt;font-weight:bold;background:#0b776d;color:white;padding:6px 9px">AM</span>@endif
</div><div class="brand"><div class="brand-name">ASSAM MOTORS</div>
<div class="address">Bishnu Rabha Path, Hockey Stadium Rd, Sawkuchi, Guwahati-781040, Assam<br>
Phone: 9864043620 · Email: service@assammotors.com · GSTIN: 18CAHPS7155P1ZQ<br>
Website: www.assammotors.com · MSME: UDYAM-AS-03-0045843</div></div></div>
<div class="report-title">VEHICLE HEALTH REPORT</div>
<div class="subtitle">{{ $report_no }} · {{ $generated_at }} · Digitally generated from Assam Motors ERP</div>
<table class="meta">
<tr><td><span class="muted">VEHICLE REGISTRATION</span><div class="value">{{ $job->vehicle_reg_no ?? '—' }}</div></td>
<td><span class="muted">JOB CARD</span><div class="value">{{ $job->job_no ?? $job->id }}</div></td>
<td><span class="muted">CUSTOMER</span><div class="value">{{ $job->customer_name ?? '—' }}</div></td>
<td><span class="muted">VEHICLE</span><div class="value">{{ trim(($job->vehicle_make ?? '').' '.($job->vehicle_model ?? '')) }}</div></td></tr>
</table>
<div class="section-title">01 · Overall Vehicle Condition</div>
<table class="summary"><tr><td style="width:32%;text-align:center"><span class="health-number">{{ $health }}%</span><div class="muted">INSPECTION HEALTH SCORE</div></td>
<td style="width:68%"><div style="font-size:10pt;font-weight:bold">Condition Summary</div>
<div style="margin-top:5px">{{ count($points) }} inspection items recorded · {{ count($defects) }} condition issue(s) · {{ $safety_open }} safety-critical finding(s)</div>
<div class="muted" style="margin-top:6px">The score is weighted by inspection category. Non-graded readings and N/A items do not affect percentage.</div></td></tr></table>
<div class="section-title">02 · Category-wise Health</div>
<table class="report-table"><thead><tr><th>Category</th><th>Points</th><th>Health Score</th><th>Observed Issues</th></tr></thead><tbody>
@foreach($categories as $cat)<tr><td>{{ $cat['name'] }}</td><td>{{ $cat['points'] }}</td><td>{{ $cat['score']===null?'N/A':$cat['score'].'%' }}</td><td>{{ $cat['defects'] }}</td></tr>@endforeach
</tbody></table>
<div class="section-title">03 · Vehicle Valuation Assessment</div>
<table class="valuation"><tr>
<td><div class="muted">REFERENCE MARKET VALUE</div><div class="val-number">{{ $valuation ? 'Rs '.number_format((float)$valuation->reference_market_price,0) : 'Pending' }}</div></td>
<td><div class="muted">REPAIR ESTIMATE</div><div class="val-number">{{ $valuation ? 'Rs '.number_format((float)$valuation->estimated_repair_cost,0) : 'Pending' }}</div></td>
<td><div class="muted">SYSTEM INDICATIVE VALUE</div><div class="val-number">{{ $system_value!==null ? 'Rs '.number_format($system_value,0) : 'Not assessed' }}</div></td>
<td><div class="muted">ADMIN-APPROVED VALUE</div><div class="val-number">{{ $is_final ? 'Rs '.number_format((float)$valuation->admin_final_value,0) : 'Pending approval' }}</div></td>
</tr></table>
@if($valuation && $valuation->admin_note)<div style="padding:7px 0"><b>Admin assessment:</b> {{ $valuation->admin_note }}
@if($valuation->approved_by)<span class="muted"> · Approved by {{ $valuation->approved_by }}</span>@endif</div>@endif
<div class="warning"><b>Valuation disclosure:</b> System value is an indicative calculation from a manually entered market benchmark, model year, odometer, inspection score and expected repairs. It is NOT a verified real-time market quotation or independent vehicle appraisal.</div>
@if(count($defects))
<div class="section-title">04 · Observed Defects and Required Attention</div>
<table class="report-table"><thead><tr><th style="width:30%">Inspection Point</th><th style="width:18%">Result</th><th>Observation / Recommendation</th></tr></thead><tbody>
@foreach($defects as $defect)<tr><td>{{ $defect['name'] }}</td><td class="red">{{ strtoupper(str_replace('_',' ',$defect['status'])) }}@if($defect['critical']) · Safety @endif</td><td>{{ $defect['remarks'] ?: 'Inspector observation not recorded' }}</td></tr>@endforeach
</tbody></table>
<div class="warning"><b>Repair pending:</b> Inspection completion does not resolve defects. Repair and re-inspection verification are separate audited steps before QC PASS.</div>
@endif
<div class="section-title">05 · Detailed Point-wise Inspection</div>
<table class="report-table"><thead><tr><th style="width:6%">#</th><th style="width:39%">Inspection Check</th><th style="width:19%">Condition</th><th style="width:36%">Remarks / Reading</th></tr></thead><tbody>
@foreach($points as $i=>$point)<tr><td>{{ $i+1 }}</td><td>{{ $point['name'] }}</td><td class="{{ $point['defect']?'red':'green' }}">{{ strtoupper(str_replace('_',' ',$point['status'])) }}</td><td>{{ $point['remarks'] ?: '—' }}</td></tr>@endforeach
</tbody></table>
<div class="section-title">06 · Confirmation</div>
<table class="signatures"><tr><td>Inspection Staff</td><td>Admin / Service Advisor</td><td>Customer Acknowledgement</td></tr></table>
<div class="disclaimer">Report reflects inspected findings at the recorded time and is not a guarantee of mechanical condition, roadworthiness, title, insurance or market value. No vehicle photos are attached. A pending defect must be resolved and verified through the Job Card workflow.</div>
</body></html>
