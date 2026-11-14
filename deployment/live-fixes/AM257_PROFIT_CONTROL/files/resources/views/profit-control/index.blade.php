@extends('layouts.erp')
@section('title','Workshop Profit Control · ASSAM MOTORS')
@section('topbar_title','Workshop Profit Control')
@section('content')
{{-- AM257_PROFIT_CONTROL: live invoice-ledger dashboard, estimated recovery clearly separate from actual. --}}
<style>
.am257{max-width:1590px;margin:0 auto;padding-bottom:26px;color:#1d3c56;font-family:Inter,"Segoe UI",Arial,sans-serif;font-size:12px}
.am257 *{box-sizing:border-box}
.am257 .top{display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:14px;margin:0 0 16px}
.am257 .eyebrow{font-size:10px;letter-spacing:.11em;font-weight:900;color:#0b8175;text-transform:uppercase}
.am257 h1{margin:5px 0 5px;color:#143750;font-size:25px;letter-spacing:-.03em}
.am257 .subtitle{margin:0;font-size:12px;color:#70879a;line-height:1.65}
.am257 .top-actions{display:flex;align-items:center;gap:7px;flex-wrap:wrap}
.am257 .act{display:inline-flex;align-items:center;justify-content:center;padding:9px 12px;border-radius:8px;border:1px solid #c9dce9;background:white;color:#265675;text-decoration:none;font-size:11px;font-weight:850;min-height:36px;cursor:pointer}
.am257 .act.primary{background:#0b766e;border-color:#0b766e;color:white}
.am257 .act:hover{filter:brightness(.96)}
.am257 .notice{padding:11px 13px;border-radius:10px;background:#edf7f4;border:1px solid #c5e9dc;color:#276b59;margin-bottom:13px;font-size:11px;line-height:1.55}
.am257 .notice.warn{background:#fff7e9;border-color:#f1dbb0;color:#866026}
.am257 .notice.err{background:#fff2f2;border-color:#f4ced1;color:#af303b}
.am257 .datebar{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin:0 0 12px;padding:11px 14px;background:white;border:1px solid #dce7ee;border-radius:11px}
.am257 .datebar label{font-weight:850;color:#4e6b80}
.am257 input[type=date],.am257 input[type=number]{height:36px;border:1px solid #c5d7e6;background:#fff;border-radius:7px;padding:7px 10px;color:#1c4662;font-size:12px;min-width:0}
.am257 .datebar input{min-width:170px}
.am257 .datebar button{background:#164f7c;color:#fff;border:0;padding:9px 13px;border-radius:7px;font-weight:850;cursor:pointer;font-size:11px}
.am257 .chip{display:inline-flex;background:#e5f1fa;color:#356383;font-size:10px;padding:5px 9px;border-radius:20px;font-weight:850}
.am257 .kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:11px;margin:12px 0 14px}
.am257 .kpi{background:#fff;border:1px solid #dce7ee;border-radius:12px;padding:15px;min-height:116px;position:relative;overflow:hidden}
.am257 .kpi:before{content:"";position:absolute;left:0;top:0;width:100%;height:3px;background:#b6cedc}
.am257 .kpi.hero:before{background:#0e9685}.am257 .kpi.goal:before{background:#cba059}
.am257 .kpi .kname{font-size:10px;color:#6c8395;font-weight:800;line-height:1.35}
.am257 .kpi strong{display:block;font-size:24px;font-weight:900;margin:12px 0 8px;letter-spacing:-.03em;color:#173c59;white-space:nowrap;font-variant-numeric:tabular-nums}
.am257 .kpi.hero strong{color:#0c776d}.am257 .kpi .detail{font-size:10px;color:#8296a4;line-height:1.5}
.am257 .panel{background:#fff;border:1px solid #d9e5ee;border-radius:12px;padding:16px;min-width:0;margin:0 0 13px}
.am257 .panel h2{color:#1a4665;font-size:15px;margin:0 0 5px}
.am257 .panel .smallnote{color:#748b9b;font-size:10px;line-height:1.6;margin:0}
.am257 .grid2{display:grid;grid-template-columns:minmax(0,1.5fr) minmax(310px,1fr);gap:13px}
.am257 .bar{height:12px;background:#e9eff4;border-radius:99px;overflow:hidden;margin:12px 0 8px}
.am257 .bar span{display:block;height:100%;background:linear-gradient(90deg,#139e87,#5ccab7);border-radius:99px}
.am257 .meta{display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;font-size:11px;color:#6a8294}
.am257 .meta strong{color:#214b66}
.am257 .mini2{display:grid;grid-template-columns:1fr 1fr;gap:9px;margin-top:12px}
.am257 .mini{background:#f5f9fc;padding:11px;border-radius:8px}
.am257 .mini small{color:#758ca0;font-size:10px}
.am257 .mini strong{display:block;margin-top:6px;color:#224965;font-size:18px;font-variant-numeric:tabular-nums}
.am257 .forecast-notice{padding:13px;background:#fff9ee;border:1px solid #f2dfbb;border-radius:9px;margin-top:11px;font-size:11px;color:#7d632f;line-height:1.6}
.am257 .groupcards{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:11px}
.am257 .groupcards .panel{margin:0}
.am257 .groupcards .big{font-size:22px;font-weight:900;color:#214d65;margin:14px 0 4px}
.am257 .groupcards .untracked{font-size:14px;color:#99632a}
.am257 .cfg{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-top:14px}
.am257 .field{display:flex;flex-direction:column;gap:6px;min-width:0}
.am257 .field label{font-size:11px;color:#4e6d84;font-weight:800}
.am257 .field input{width:100%;height:39px;font-size:13px;font-weight:750}
.am257 .field small{font-size:9px;line-height:1.5;color:#8398a8}
.am257 .savebox{display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;margin-top:14px;border-top:1px solid #e1eaf0;padding-top:12px}
.am257 .savebox p{margin:0;color:#688298;font-size:10px;max-width:780px;line-height:1.55}
.am257 .save{border:0;background:#0e786e;border-radius:8px;color:#fff;padding:11px 15px;font-size:12px;font-weight:900;cursor:pointer;min-height:39px}
.am257 .tablebox{overflow:auto;border:1px solid #d9e6ef;border-radius:9px;margin-top:12px;max-height:460px}
.am257 table{width:100%;border-collapse:collapse;min-width:850px}
.am257 th{position:sticky;top:0;background:#173f64;color:#fff;font-size:10px;text-align:left;padding:10px;z-index:1;white-space:nowrap}
.am257 td{padding:10px;border-bottom:1px solid #e8eef3;font-size:11px;vertical-align:top}
.am257 tbody tr:nth-child(even){background:#f7fafc}
.am257 td small{display:block;margin-top:3px;color:#7f90a3;font-size:10px}
.am257 .num{text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums}
.am257 .summary-table{width:100%;min-width:0}
.am257 .summary-table td{border-bottom:1px solid #e8eef3;font-size:11px}
.am257 .summary-table tr.last td{font-weight:900;font-size:13px;color:#104f65;background:#eef8f6}
.am257 .warnline{padding:11px 12px;border-left:3px solid #d3a74f;background:#fff9ef;border-radius:6px;color:#775b28;font-size:11px;line-height:1.7;margin-top:12px}
.am257 .sectiontitle{font-size:17px;font-weight:900;color:#153c58;margin:20px 0 11px}
.am257 .status-tag{display:inline-flex;padding:5px 8px;border-radius:7px;font-size:10px;font-weight:850;background:#e5f6f0;color:#137457}
.am257 .status-tag.planned{background:#fff3dd;color:#8f6228}
.am257 .foot{font-size:10px;color:#8497a5;line-height:1.8;margin:14px 0}
.am257 .muted{color:#7990a3}
@media(max-width:1150px){.am257 .kpis{grid-template-columns:repeat(2,minmax(0,1fr))}.am257 .grid2{grid-template-columns:1fr}.am257 .cfg{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:650px){.am257 .kpis,.am257 .groupcards,.am257 .cfg{grid-template-columns:1fr}.am257 .kpi strong{font-size:22px}.am257 h1{font-size:22px}.am257 .mini2{grid-template-columns:1fr}}
@media print{.am257 .top-actions,.am257 .datebar,.am257 .cfg,.am257 .savebox,.am257 .act{display:none!important}.am257 .panel,.am257 .kpi{break-inside:avoid;box-shadow:none!important}.am257 .tablebox{max-height:none;overflow:visible}.am257 .tablebox table{min-width:0;font-size:9px}.am257{color:#000}}
</style>
@php
  $rupees=static fn($n)=>'₹'.number_format(abs((float)$n),2);
  $signed=static fn($n)=>(float)$n<0?'−₹'.number_format(abs((float)$n),2):'₹'.number_format((float)$n,2);
  $planned=(bool)($forecast['configured']??false);
  $settings=$plan['values'];
@endphp
<div class="am257">
  <div class="top">
    <div>
      <div class="eyebrow">ASSAM MOTORS · FINANCIAL CONTROL CENTRE</div>
      <h1>Workshop Profit & Daily Earning Target</h1>
      <p class="subtitle">Actual saved invoice sales and known discounts · Separately calculated planning targets and estimated recovery</p>
    </div>
    <div class="top-actions">
      <a class="act" href="{{ route('erp.sales-register.index') }}">Sales Register</a>
      <a class="act" href="{{ route('erp.purchases.index') }}">Purchase Register</a>
      <a class="act primary" href="{{ route('erp.profit-control.index',['date'=>$date,'export'=>'csv']) }}">↓ Export CSV</a>
      <button type="button" class="act" onclick="window.print()">Print Report</button>
    </div>
  </div>
  @if(session('success'))<div class="notice" role="status">{{ session('success') }}</div>@endif
  @if($errors->any())
  <div class="notice err"><strong>Please correct:</strong><ul style="margin:5px 0 0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
  @endif
  @if(!$planned)
  <div class="notice warn"><strong>Cost Recovery Setup Required:</strong> Monthly operating cost and contribution-margin target have not been saved. Actual sales are visible below, but no break-even/profit figure will be invented. Fill the planning section and Save Targets.</div>
  @else
  <div class="notice"><strong>Saved planning target active.</strong> Earnings and registered discounts are read from ERP data; contribution and operating surplus remain estimates until historical COGS, OSL discounts and complete overhead classification are integrated.</div>
  @endif

  <form class="datebar" method="get" action="{{ route('erp.profit-control.index') }}">
    <label for="am257-date">Report Date</label>
    <input id="am257-date" name="date" type="date" value="{{ $date }}" required>
    <button type="submit">View Day</button>
    <a href="{{ route('erp.profit-control.index') }}" class="act">Today</a>
    <span class="chip">Asia/Kolkata · GST excluded</span>
    <span class="muted">Report date: {{ \Illuminate\Support\Carbon::parse($date)->format('d M Y') }}</span>
  </form>
  <div class="kpis">
    <div class="kpi hero">
      <div class="kname">Actual Net Sales · Invoice Basis</div>
      <strong>{{ $signed($data['net_sales']) }}</strong>
      <div class="detail">{{ $data['invoice_count'] }} valid invoices · GST excluded · eligible credit adjustments applied</div>
    </div>
    <div class="kpi">
      <div class="kname">Known Customer Discounts · Linked Lines</div>
      <strong>{{ $rupees($data['known_discounts']) }}</strong>
      <div class="detail">Parts & Labour only · current JC values, not frozen invoice discounts</div>
    </div>
    <div class="kpi goal">
      <div class="kname">Required Daily Net Billing · Target</div>
      <strong>{{ $planned?$rupees($forecast['daily_billing_target']):'Not set' }}</strong>
      <div class="detail">Monthly cost + desired profit, divided by working days and expected contribution margin</div>
    </div>
    <div class="kpi">
      <div class="kname">Estimated Daily Operating Surplus</div>
      <strong style="{{ $planned && $forecast['estimated_operating_surplus']<0?'color:#bc4c4f':'' }}">{{ $planned?$signed($forecast['estimated_operating_surplus']):'Not set' }}</strong>
      <div class="detail">Forecast only · not actual audited net profit</div>
    </div>
  </div>

  <div class="grid2">
    <section class="panel">
      <h2>Daily Cost Recovery vs Target</h2>
      <p class="smallnote">Forecast contribution = Actual net sales × configured planned contribution margin.</p>
      @if($planned)
      @php $fill=($forecast['daily_contribution_target']>0)?max(0,min(100,(float)$forecast['achievement_percent'])):0; @endphp
      <div style="display:flex;align-items:flex-end;justify-content:space-between;margin-top:18px;gap:15px">
        <div><div class="smallnote">Estimated Contribution from Invoices</div><div style="font-size:26px;font-weight:900;color:#0a8576;margin-top:5px">{{ $signed($forecast['estimated_contribution']) }}</div></div>
        <span class="status-tag planned">Planning model</span>
      </div>
      <div class="bar"><span style="width:{{ $fill }}%"></span></div>
      <div class="meta"><span>{{ number_format((float)$forecast['achievement_percent'],1) }}% of target</span><strong>Required {{ $rupees($forecast['daily_contribution_target']) }}</strong></div>
      <div class="mini2">
        <div class="mini"><small>Daily Overhead Recovery Needed</small><strong>{{ $rupees($forecast['daily_overhead']) }}</strong></div>
        <div class="mini"><small>Contribution Above / Below Target</small><strong style="{{ $forecast['contribution_gap']<0?'color:#ba4a51':'' }}">{{ $signed($forecast['contribution_gap']) }}</strong></div>
      </div>
      @else
      <div class="forecast-notice">Configure monthly operating cost, profit target, working days and expected contribution margin below to activate daily break-even and estimated recovery.</div>
      @endif
    </section>
    <section class="panel">
      <h2>Actual Invoice & Credit Note Summary</h2>
      <p class="smallnote">Read-only, based on saved financial records for the selected date.</p>
      <table class="summary-table" style="margin-top:14px">
        <tbody>
          <tr><td>Valid Invoice Sales (ex-GST)</td><td class="num">{{ $rupees($data['sales_before_credit']) }}</td></tr>
          <tr><td>Eligible Credit Note Adjustment</td><td class="num">−{{ $rupees($data['credit_adjustment']) }}</td></tr>
          <tr class="last"><td>Net Invoice Sales (ex-GST)</td><td class="num">{{ $signed($data['net_sales']) }}</td></tr>
          <tr><td>Valid Invoices</td><td class="num">{{ $data['invoice_count'] }}</td></tr>
          <tr><td>Credit Notes Posted Today</td><td class="num">{{ $data['credit_note_count'] }}</td></tr>
          <tr><td>All Posted Credit Note Taxable Value</td><td class="num">{{ $rupees($data['credit_notes_posted']) }}</td></tr>
        </tbody>
      </table>
      <div class="warnline">Reversed invoices are excluded from valid invoice sales. A credit note is also subtracted only when its source invoice remains active, to avoid double subtraction. Dates follow the saved financial document dates.</div>
    </section>
  </div>

  <div class="sectiontitle">Discount Control Register — One Place</div>
  <div class="groupcards">
    <div class="panel">
      <div class="smallnote">Parts Discounts · Known</div>
      <div class="big">{{ $rupees($data['discount_parts']) }}</div>
      <p class="smallnote">Linked Job Card Parts + saved Direct Invoice lines</p>
    </div>
    <div class="panel">
      <div class="smallnote">Labour / ROT Discounts · Known</div>
      <div class="big">{{ $rupees($data['discount_labour']) }}</div>
      <p class="smallnote">Current billed Job Card Labour line discounts</p>
    </div>
    <div class="panel">
      <div class="smallnote">OSL Discounts · Verification Required</div>
      <div class="big untracked">Not yet available</div>
      <p class="smallnote">Reliable original OSL invoice-line discount snapshots not found. Not counted as ₹0.</p>
    </div>
  </div>
  <section class="panel" style="margin-top:13px">
    <h2>Discount Detail — Invoice / Customer / Vehicle / Item</h2>
    <p class="smallnote">All currently traceable Parts & Labour discounts for the report day. Values are current Job Card line records and may differ from historical issued-invoice snapshots; do not subtract these discounts again from already net invoice sales.</p>
    <div class="tablebox">
      <table>
        <thead><tr><th>Invoice</th><th>Customer / Vehicle</th><th>Category</th><th>Part / ROT</th><th>Discount %</th><th class="num">Discount Amount</th><th>Source Quality</th></tr></thead>
        <tbody>
          @forelse($data['discount_rows'] as $r)
            <tr>
              <td><strong>{{ $r['invoice_no'] }}</strong></td>
              <td>{{ $r['customer'] }}<small>{{ $r['vehicle'] }}</small></td>
              <td>{{ $r['category'] }}</td>
              <td>{{ $r['description'] }}<small>{{ $r['code'] }}</small></td>
              <td>{{ number_format((float)$r['percent'],2) }}%</td>
              <td class="num"><strong>{{ $rupees($r['amount']) }}</strong></td>
              <td><small>{{ $r['basis'] }}</small></td>
            </tr>
          @empty
            <tr><td colspan="7" style="padding:23px;text-align:center;color:#8196a5">No linked Parts/Labour discount lines on active invoices for this date.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>

  <div class="sectiontitle">Operating Cost & Profit Target Settings</div>
  <section class="panel">
    <h2>Set Your Daily Earning Target</h2>
    <p class="smallnote">Owner/Admin input. These planning assumptions are saved in ERP. They do not modify invoices, purchases, staff salary, tax, stock or ledger amounts.</p>
    <form method="post" action="{{ route('erp.profit-control.save-plan') }}">
      @csrf
      <input type="hidden" name="date" value="{{ $date }}">
      <div class="cfg">
        <div class="field"><label for="am257-monthly">Monthly Operating Cost (₹)</label>
          <input id="am257-monthly" type="number" min="0" max="1000000000" step=".01" name="monthly_operating_cost" value="{{ old('monthly_operating_cost',$settings['monthly_operating_cost']) }}" required>
          <small>Salary, rent, electricity, administration, depreciation; exclude Parts/OSL variable costs covered by margin.</small>
        </div>
        <div class="field"><label for="am257-profit">Desired Monthly Profit (₹)</label>
          <input id="am257-profit" type="number" min="0" max="1000000000" step=".01" name="desired_monthly_profit" value="{{ old('desired_monthly_profit',$settings['desired_monthly_profit']) }}" required>
          <small>Additional operating profit target above recovery of operating cost.</small>
        </div>
        <div class="field"><label for="am257-days">Working Days / Month</label>
          <input id="am257-days" type="number" min="1" max="31" step="1" name="working_days" value="{{ old('working_days',$settings['working_days']) }}" required>
          <small>Days across which the cost & profit target is allocated.</small>
        </div>
        <div class="field"><label for="am257-margin">Expected Contribution Margin (%)</label>
          <input id="am257-margin" type="number" min=".01" max="100" step=".01" name="planned_margin_percent" value="{{ old('planned_margin_percent',$settings['planned_margin_percent']) }}" required>
          <small>After variable job costs, on discounted ex-GST sales. Enter verified target, not GST rate.</small>
        </div>
      </div>
      <div class="savebox">
        <p>One company-wide saved plan. Changes are recorded in Laravel audit logs. If actual workshop expense data is incomplete, enter a reviewed monthly budget; later reconciliation can replace these assumptions with audited job costing.</p>
        <button class="save" type="submit" onclick="return confirm('Save workshop-wide planning assumptions? This does not alter billing, stock or accounting entries.');">Save Cost & Profit Targets</button>
      </div>
    </form>
  </section>
  <div class="grid2">
    <section class="panel">
      <h2>Today's Recorded Expense Entries</h2>
      <p class="smallnote">From Expenses Register, ACTIVE only, ex-GST. This is not necessarily total overhead or all direct job costs.</p>
      <table class="summary-table" style="margin-top:10px">
        <tbody>
          <tr><td>Job-linked Expenses</td><td class="num">{{ $rupees($data['expenses_job']) }}</td></tr>
          <tr><td>Other Recorded Expenses</td><td class="num">{{ $rupees($data['expenses_other']) }}</td></tr>
          <tr class="last"><td>Recorded Expenses Total</td><td class="num">{{ $rupees($data['expenses_job']+$data['expenses_other']) }}</td></tr>
          <tr><td>Active Expense Entries</td><td class="num">{{ $data['expense_entries'] }}</td></tr>
        </tbody>
      </table>
      <div class="warnline">Do not subtract these Expenses Register totals again from forecast contribution and monthly overhead budget. Some represent items already captured in those assumptions.</div>
    </section>
    <section class="panel">
      <h2>Cost Recovery Formula</h2>
      <p class="smallnote">Matches the approved prototype, with exact source vs assumptions distinguished.</p>
      <table class="summary-table" style="margin-top:10px">
        <tbody>
          <tr><td>Actual Net Invoice Sales (ex-GST)</td><td class="num">{{ $signed($data['net_sales']) }}</td></tr>
          <tr><td>× Planned Contribution Margin</td><td class="num">{{ $planned?number_format((float)$settings['planned_margin_percent'],2).'%':'Not set' }}</td></tr>
          <tr><td>= Estimated Job Contribution</td><td class="num">{{ $planned?$signed($forecast['estimated_contribution']):'Not set' }}</td></tr>
          <tr><td>− Budgeted Daily Workshop Operating Cost</td><td class="num">{{ $planned?'−'.$rupees($forecast['daily_overhead']):'Not set' }}</td></tr>
          <tr class="last"><td>= Estimated Operating Surplus / Shortfall</td><td class="num">{{ $planned?$signed($forecast['estimated_operating_surplus']):'Not set' }}</td></tr>
        </tbody>
      </table>
      <div class="warnline">Actual profit requires historical Parts COGS, full OSL billing and vendor cost, salary allocation, complete overhead classification and locked invoice discount snapshots. Those gaps are intentionally not replaced with guessed numbers.</div>
    </section>
  </div>
  <p class="foot"><strong>AM257 · Phase 1 Financial Management Dashboard.</strong> Only saved Admin planning settings are editable here. Invoiced revenue uses saved ex-GST subtotals (with safe fallback), excluding reversed/void invoices; credit note deductions prevent double counting. Discount detail reflects current JC line data, not a frozen invoice ledger. Neither GST collections nor Purchase stock additions are treated as profit. This is not an audited Profit & Loss statement.</p>
</div>
@endsection
