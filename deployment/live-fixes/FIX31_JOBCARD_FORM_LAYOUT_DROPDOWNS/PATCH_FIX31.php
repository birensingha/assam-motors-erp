<?php
if ($argc < 2) { fwrite(STDERR,"Usage: php PATCH_FIX31.php /path/to/app\n"); exit(2); }
$root=rtrim($argv[1],'/');
$path="$root/resources/views/job-cards/form.blade.php";
$c=file_get_contents($path);
if (str_contains($c,'FIX31_JOBCARD_FORM_LAYOUT')) { echo "FIX31 already installed.\n"; exit(0); }

function r1(string &$c,string $old,string $new,string $label): void {
  $n=substr_count($c,$old);
  if($n!==1) throw new RuntimeException("$label marker count=$n");
  $c=str_replace($old,$new,$c);
}

r1(
  $c,
  "  \$inward = old('vehicle_inward_at', \$job->vehicle_inward_at ? \\Carbon\\Carbon::parse(\$job->vehicle_inward_at)->format('Y-m-d\\TH:i') : now('Asia/Kolkata')->format('Y-m-d\\TH:i'));",
  "  \$inward = old('vehicle_inward_at', \$job->vehicle_inward_at ? \\Carbon\\Carbon::parse(\$job->vehicle_inward_at)->format('Y-m-d\\TH:i') : now('Asia/Kolkata')->format('Y-m-d\\TH:i'));\n  \$serviceTypes = ['General Service','Periodic Service','Running Repair','Major Repair','Diagnosis','Electrical Repair','AC Service','Body & Paint','Accident Repair','Inspection','Warranty / Goodwill','Other'];\n  \$currentServiceType = (string)\$value('service_type');",
  'service type options'
);

$style=<<<'BLADE'
<style>
/* FIX31_JOBCARD_FORM_LAYOUT */
.classic-form-shell .form-grid.four-col{
  display:grid!important;
  grid-template-columns:minmax(0,1fr) minmax(0,1fr)!important;
  gap:14px 18px!important;
  align-items:start!important;
}
.classic-form-shell .form-grid.four-col>label{
  display:grid!important;
  grid-template-columns:minmax(0,1fr)!important;
  gap:6px!important;
  min-width:0!important;
  margin:0!important;
  font-weight:700;
  color:#25384d;
}
.classic-form-shell .form-grid.four-col>label.span-2{grid-column:auto!important}
.classic-form-shell .form-grid.four-col>label.span-3,
.classic-form-shell .form-grid.four-col>label.span-4,
.classic-form-shell .form-grid.four-col>label.jc-full{grid-column:1/-1!important}
.classic-form-shell input,
.classic-form-shell select,
.classic-form-shell textarea{
  width:100%!important;
  box-sizing:border-box!important;
  border:1px solid #b8c7d8!important;
  border-radius:8px!important;
  background:#fff!important;
  color:#172b3f!important;
  font:inherit!important;
  line-height:1.25!important;
  box-shadow:none!important;
}
.classic-form-shell input,.classic-form-shell select{
  min-height:44px!important;
  padding:9px 11px!important;
}
.classic-form-shell textarea{
  padding:10px 11px!important;
  resize:vertical!important;
}
.classic-form-shell select{
  appearance:auto!important;
  -webkit-appearance:menulist!important;
  cursor:pointer!important;
}
.classic-form-shell select option{
  background:#fff!important;
  color:#172b3f!important;
}
.classic-form-shell input:focus,
.classic-form-shell select:focus,
.classic-form-shell textarea:focus{
  outline:3px solid rgba(27,111,214,.15)!important;
  border-color:#1b6fd6!important;
}
.classic-form-shell .field-help{
  font-size:.78rem;
  font-weight:500;
  color:#6b7d90;
  margin-top:1px;
}
@media(max-width:860px){
  .classic-form-shell .form-grid.four-col{
    grid-template-columns:minmax(0,1fr)!important;
  }
  .classic-form-shell .form-grid.four-col>label,
  .classic-form-shell .form-grid.four-col>label.span-2,
  .classic-form-shell .form-grid.four-col>label.span-3,
  .classic-form-shell .form-grid.four-col>label.span-4,
  .classic-form-shell .form-grid.four-col>label.jc-full{
    grid-column:1/-1!important;
  }
}
</style>
BLADE;

r1($c,"</div>\n\n<form method=\"post\"","</div>\n\n".$style."\n<form method=\"post\"",'style insertion');

r1($c,'      <label class="span-2">Customer Master','      <label class="jc-full">Customer Master','customer master width');
r1($c,'      <label class="span-3">Address','      <label class="jc-full">Address','address width');
r1($c,'      <label class="span-2">Registered Vehicle','      <label class="jc-full">Registered Vehicle','registered vehicle width');
r1($c,'      <label class="span-2">Chassis No','      <label>Chassis No','chassis width');
r1($c,'      <label class="span-2">Engine No','      <label>Engine No','engine width');
r1($c,'      <label class="span-2">Technician','      <label>Technician','technician width');
r1($c,'      <label class="span-2">Customer App Status','      <label>Customer App Status','public status width');
r1($c,'      <label class="span-4">Customer Complaint','      <label class="jc-full">Customer Complaint','complaint width');
r1($c,'      <label class="span-4">Customer Voice / Notes','      <label class="jc-full">Customer Voice / Notes','voice width');
r1($c,'@if($editing)<label class="span-4">Final Diagnosis','@if($editing)<label class="jc-full">Final Diagnosis','diagnosis width');

$old='      <label>Service Type<input name="service_type" value="{{ $value(\'service_type\') }}" placeholder="General Service / Repair / Diagnosis"></label>';
$new=<<<'BLADE'
      <label>Service Type *
        <select name="service_type" id="jcServiceType" required>
          <option value="">— Select Service Type —</option>
          @if($currentServiceType!=='' && !in_array($currentServiceType,$serviceTypes,true))
            <option value="{{ $currentServiceType }}" selected>{{ $currentServiceType }} (Existing)</option>
          @endif
          @foreach($serviceTypes as $type)
            <option value="{{ $type }}" @selected($currentServiceType===$type)>{{ $type }}</option>
          @endforeach
        </select>
        <span class="field-help">Dropdown se service category select kijiye.</span>
      </label>
BLADE;
r1($c,$old,$new,'service type dropdown');

file_put_contents($path,$c);
echo "FIX31 patch applied.\n";
