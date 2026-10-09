<?php
if ($argc < 2) { fwrite(STDERR,"Usage: php PATCH_FIX37.php /path/to/app\n"); exit(2); }
$root=rtrim($argv[1],'/');

function replaceOnce(string $path,string $old,string $new,string $label): void {
    $c=file_get_contents($path);
    if (str_contains($c,$new)) return;
    $n=substr_count($c,$old);
    if ($n!==1) throw new RuntimeException($label." marker count=".$n." in ".$path);
    file_put_contents($path,str_replace($old,$new,$c));
}
function insertBeforeOnce(string $path,string $marker,string $insert,string $id): void {
    $c=file_get_contents($path);
    if (str_contains($c,$id)) return;
    $n=substr_count($c,$marker);
    if ($n!==1) throw new RuntimeException($id." marker count=".$n." in ".$path);
    file_put_contents($path,str_replace($marker,$insert.$marker,$c));
}

function replacePhpMethodOnce(string $source,string $methodName,string $replacement): string {
    $needle='public function '.$methodName.'(';
    $needlePos=strpos($source,$needle);
    if ($needlePos===false) throw new RuntimeException($methodName." method start not found");

    $lineStart=strrpos(substr($source,0,$needlePos),"\n");
    $start=($lineStart===false)?0:$lineStart+1;
    $open=strpos($source,'{',$needlePos);
    if ($open===false) throw new RuntimeException($methodName." opening brace not found");

    $len=strlen($source);
    $depth=0;
    $quote=null;
    $escape=false;
    $lineComment=false;
    $blockComment=false;

    for($i=$open;$i<$len;$i++){
        $ch=$source[$i];
        $nx=($i+1<$len)?$source[$i+1]:'';

        if($lineComment){
            if($ch==="\n") $lineComment=false;
            continue;
        }
        if($blockComment){
            if($ch==='*' && $nx==='/'){ $blockComment=false; $i++; }
            continue;
        }
        if($quote!==null){
            if($escape){ $escape=false; continue; }
            if($ch==='\\'){ $escape=true; continue; }
            if($ch===$quote) $quote=null;
            continue;
        }
        if($ch==="'" || $ch==='"'){ $quote=$ch; continue; }
        if($ch==='/' && $nx==='/'){ $lineComment=true; $i++; continue; }
        if($ch==='/' && $nx==='*'){ $blockComment=true; $i++; continue; }

        if($ch==='{') $depth++;
        elseif($ch==='}'){
            $depth--;
            if($depth===0){
                $end=$i+1;
                while($end<$len && ($source[$end]==="\r" || $source[$end]==="\n")) $end++;
                return substr($source,0,$start).rtrim($replacement,"\r\n")."\n\n".substr($source,$end);
            }
        }
    }
    throw new RuntimeException($methodName." closing brace not found");
}

$p="$root/app/Http/Controllers/Web/AdminJobCardPageController.php";
$c=file_get_contents($p);
if (!str_contains($c,'FIX37_JOB_CARD_CORE')) {
    $lines=preg_split('/\\R/',$c);
    $inserted=false;
    foreach($lines as $i=>$line){
        if(str_contains($line,"$"."data['staff']")){
            $block=[
                "        // FIX37_JOB_CARD_CORE — direct Parts/Labour entry without Estimate.",
                "        \$data['partsMaster']=Schema::hasTable('parts_master')",
                "            ? DB::table('parts_master')->orderBy('part_name')->limit(5000)->get()",
                "            : collect();",
                "        \$data['labourMaster']=Schema::hasTable('labour_master')",
                "            ? DB::table('labour_master')->orderBy('rot_code')->limit(5000)->get()",
                "            : collect();",
            ];
            array_splice($lines,$i+1,0,$block);
            $inserted=true;
            break;
        }
    }
    if(!$inserted) throw new RuntimeException("show() staff anchor not found");
    file_put_contents($p,implode("\n",$lines));
}
echo "PASS controller show() anchor patched\n";

$c=file_get_contents($p);
if (!str_contains($c,'FIX37_STOCK_SAFE_PART_DELETE')) {
    $method=<<<'PHP'
    public function deletePart(Request $request, int $job, int $part): RedirectResponse
    {
        $user = $this->admin($request);
        $data = $request->validate(['reason'=>['required','string','max:1000']]);

        $result=DB::transaction(function () use ($job,$part,$data,$user) {
            // FIX37_STOCK_SAFE_PART_DELETE
            $card = JobCard::query()->lockForUpdate()->findOrFail($job);
            if (in_array(strtolower((string)$card->status), ['closed','invoiced','cancelled'], true)) {
                throw ValidationException::withMessages(['part'=>'Part cannot be deleted after Job Card is Closed / Invoiced / Cancelled.']);
            }

            $line = JobCardPart::query()->where('job_card_id',$job)->whereKey($part)->lockForUpdate()->firstOrFail();
            $before = $line->toArray();
            $issueStatus = strtolower(trim((string)($line->issue_status ?? 'planned')));
            $planned = in_array($issueStatus,['','planned','pending','not issued','not_issued'],true);
            $stockReversed=false;

            if (!$planned) {
                if (!Schema::hasTable('parts_master') || !Schema::hasTable('inventory_movements')) {
                    throw ValidationException::withMessages(['part'=>'Stock reversal tables are unavailable. Part was NOT deleted.']);
                }
                $partMasterId=(int)($line->part_master_id ?? 0);
                if ($partMasterId<=0) {
                    throw ValidationException::withMessages(['part'=>'Issued/Fitted Part is not linked to Parts Master. Part was NOT deleted.']);
                }

                $master=DB::table('parts_master')->where('id',$partMasterId)->lockForUpdate()->first();
                if (!$master) throw ValidationException::withMessages(['part'=>'Parts Master row not found. Part was NOT deleted.']);

                $outMovement=DB::table('inventory_movements')
                    ->where('part_master_id',$partMasterId)
                    ->where('job_card_id',$job)
                    ->where('qty_out','>',0)
                    ->whereRaw("UPPER(COALESCE(movement_type,'')) NOT LIKE 'PURCHASE%'")
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->first();

                if (!$outMovement) {
                    throw ValidationException::withMessages([
                        'part'=>'Issued/Fitted status exists but matching Job Card stock-out movement was not found. Part was NOT deleted to protect stock. Please correct Stock Issue history first.'
                    ]);
                }

                $reference='JC-PART-'.$part.'-DELETE';
                $already=DB::table('inventory_movements')
                    ->where('part_master_id',$partMasterId)
                    ->where('job_card_id',$job)
                    ->where('movement_type','JOB_PART_DELETE_REVERSAL')
                    ->where('reference_no',$reference)
                    ->exists();
                if ($already) throw ValidationException::withMessages(['part'=>'Stock reversal for this Job Card Part is already recorded. Refresh the Job Card.']);

                $qty=round((float)$line->qty,3);
                $beforeStock=(float)($master->stock_qty ?? 0);
                $afterStock=round($beforeStock+$qty,3);
                DB::table('parts_master')->where('id',$partMasterId)->update(['stock_qty'=>$afterStock]);
                DB::table('inventory_movements')->insert([
                    'part_master_id'=>$partMasterId,
                    'job_card_id'=>$job,
                    'movement_type'=>'JOB_PART_DELETE_REVERSAL',
                    'qty_in'=>$qty,
                    'qty_out'=>0,
                    'balance_after'=>$afterStock,
                    'reference_no'=>$reference,
                    'note'=>'Job Card Part stock reversal before delete · '.$data['reason'],
                    'created_at'=>now(),
                ]);
                $stockReversed=true;
            }

            DB::table('job_card_parts')->where('id',$part)->where('job_card_id',$job)->delete();
            $this->recalculateJobTotals($job);
            $this->auditChange(
                $job,'JOB_CARD_PART',$part,$stockReversed?'STOCK_REVERSE_DELETE':'DELETE',
                $before,null,$data['reason'],$user
            );
            return $stockReversed;
        });

        return redirect()->route('erp.job-cards.show',$job)->with(
            'success',
            $result ? 'Part stock reversed and Part removed from Job Card.' : 'Planned Part deleted from Job Card.'
        );
    }

    public function returnEstimateReview
PHP;
    $new=replacePhpMethodOnce($c,'deletePart',$method);
    file_put_contents($p,$new);
}

$methods=<<<'PHP'

    // FIX37_DIRECT_JOB_CARD_LINES
    public function addPartDirect(Request $request, int $job): RedirectResponse
    {
        $user=$this->admin($request);
        $data=$request->validate([
            'part_master_id'=>['required','integer','min:1'],
            'qty'=>['required','numeric','gt:0'],
            'rate'=>['nullable','numeric','min:0'],
            'discount_percent'=>['nullable','numeric','between:0,100'],
            'gst_percent'=>['nullable','numeric','between:0,100'],
            'note'=>['nullable','string','max:1000'],
        ]);

        DB::transaction(function() use($job,$data,$user){
            $card=JobCard::query()->lockForUpdate()->findOrFail($job);
            if (in_array(strtolower((string)$card->status),['closed','invoiced','cancelled'],true)) {
                throw ValidationException::withMessages(['part_master_id'=>'Locked Job Card cannot accept a new Part.']);
            }
            abort_unless(Schema::hasTable('parts_master'),404);
            $part=DB::table('parts_master')->where('id',(int)$data['part_master_id'])->first();
            if (!$part) throw ValidationException::withMessages(['part_master_id'=>'Selected Part not found in Parts Master.']);

            $qty=round((float)$data['qty'],3);
            $rate=round((float)($data['rate'] ?? $part->selling_price ?? 0),2);
            $disc=min(100,max(0,(float)($data['discount_percent'] ?? 0)));
            $gst=min(100,max(0,(float)($data['gst_percent'] ?? $part->gst_percent ?? 0)));
            $gross=round($qty*$rate,2);
            $discAmt=round($gross*$disc/100,2);
            $net=round($gross-$discAmt,2);

            $row=[
                'job_card_id'=>$job,'part_master_id'=>(int)$part->id,'part_code'=>$part->part_code ?? '',
                'part_name'=>$part->part_name ?? 'Part','part_number'=>$part->part_number ?? '',
                'hsn_code'=>$part->hsn_code ?? '', 'qty'=>$qty,'rate'=>$rate,
                'discount_percent'=>$disc,'discount_amount'=>$discAmt,'gst_percent'=>$gst,
                'total'=>$net,'part_note'=>trim((string)($data['note'] ?? '')),
                'source_estimate_item_id'=>null,'issue_status'=>'PLANNED',
            ];
            $row=array_filter($row,fn($v,$k)=>Schema::hasColumn('job_card_parts',$k),ARRAY_FILTER_USE_BOTH);
            $id=(int)DB::table('job_card_parts')->insertGetId($row);
            $this->recalculateJobTotals($job);
            $after=(array)DB::table('job_card_parts')->where('id',$id)->first();
            $this->auditChange($job,'JOB_CARD_PART',$id,'DIRECT_ADD',null,$after,'Admin Direct Add without Estimate',$user);
        });
        return redirect()->route('erp.job-cards.show',$job)->with('success','Part added directly to Job Card as PLANNED.');
    }

    public function addLabourDirect(Request $request, int $job): RedirectResponse
    {
        $user=$this->admin($request);
        $data=$request->validate([
            'labour_master_id'=>['required','integer','min:1'],
            'standard_hours'=>['nullable','numeric','gt:0'],
            'labour_rate'=>['nullable','numeric','min:0'],
            'discount_percent'=>['nullable','numeric','between:0,100'],
            'gst_percent'=>['nullable','numeric','between:0,100'],
            'note'=>['nullable','string','max:1000'],
        ]);

        DB::transaction(function() use($job,$data,$user){
            $card=JobCard::query()->lockForUpdate()->findOrFail($job);
            if (in_array(strtolower((string)$card->status),['closed','invoiced','cancelled'],true)) {
                throw ValidationException::withMessages(['labour_master_id'=>'Locked Job Card cannot accept Labour/ROT.']);
            }
            abort_unless(Schema::hasTable('labour_master'),404);
            $lab=DB::table('labour_master')->where('id',(int)$data['labour_master_id'])->first();
            if (!$lab) throw ValidationException::withMessages(['labour_master_id'=>'Selected Labour/ROT not found in Labour Master.']);

            $hours=round((float)($data['standard_hours'] ?? $lab->standard_hours ?? 1),2);
            $rate=round((float)($data['labour_rate'] ?? $lab->labour_rate ?? 0),2);
            $disc=min(100,max(0,(float)($data['discount_percent'] ?? 0)));
            $gst=min(100,max(0,(float)($data['gst_percent'] ?? $lab->gst_percent ?? 0)));
            $gross=round($hours*$rate,2);
            $discAmt=round($gross*$disc/100,2);
            $net=round($gross-$discAmt,2);

            $row=[
                'job_card_id'=>$job,'rot_master_id'=>(int)$lab->id,'rot_code'=>$lab->rot_code ?? '',
                'description'=>$lab->rot_description ?? 'Labour / ROT','standard_hours'=>$hours,
                'labour_rate'=>$rate,'discount_percent'=>$disc,'discount_amount'=>$discAmt,
                'gst_percent'=>$gst,'total'=>$net,'mechanic_id'=>0,'mechanic_name'=>'',
                'rot_start'=>null,'rot_end'=>null,'total_hours'=>0,'productive_seconds'=>0,
                'rot_note'=>trim((string)($data['note'] ?? '')),'source_estimate_item_id'=>null,
                'execution_status'=>'PLANNED',
            ];
            $row=array_filter($row,fn($v,$k)=>Schema::hasColumn('job_card_labour',$k),ARRAY_FILTER_USE_BOTH);
            $id=(int)DB::table('job_card_labour')->insertGetId($row);
            $this->recalculateJobTotals($job);
            $after=(array)DB::table('job_card_labour')->where('id',$id)->first();
            $this->auditChange($job,'JOB_CARD_LABOUR',$id,'DIRECT_ADD',null,$after,'Admin Direct Add without Estimate',$user);
        });
        return redirect()->route('erp.job-cards.show',$job)->with('success','Labour/ROT added directly to Job Card as PLANNED. Assign mechanic when ready.');
    }
PHP;
insertBeforeOnce(
    $p,
    '    private function recalculateJobTotals(int $jobId): void'."\n",
    $methods."\n",
    'FIX37_DIRECT_JOB_CARD_LINES'
);

$p="$root/routes/web.php";
$r=file_get_contents($p);
if (!str_contains($r,'erp.job-cards.parts.direct')) {
    $lines=preg_split('/\\R/',$r);
    $inserted=false;
    foreach($lines as $i=>$line){
        if(str_contains($line,"name('erp.job-cards.parts.delete')")){
            $block=[
                "    // FIX37_JOB_CARD_CORE — direct entries without Estimate.",
                "    Route::post('/job-cards/{job}/parts/direct', [AdminJobCardPageController::class,'addPartDirect'])->whereNumber('job')->name('erp.job-cards.parts.direct');",
                "    Route::post('/job-cards/{job}/labour/direct', [AdminJobCardPageController::class,'addLabourDirect'])->whereNumber('job')->name('erp.job-cards.labour.direct');",
            ];
            array_splice($lines,$i+1,0,$block);
            $inserted=true;
            break;
        }
    }
    if(!$inserted) throw new RuntimeException("Job Card part delete route anchor not found");
    file_put_contents($p,implode("\n",$lines));
}
echo "PASS direct routes patched\n";

$p="$root/resources/views/job-cards/show.blade.php";
$v=file_get_contents($p);

if (!str_contains($v,'FIX37_JOB_CARD_CORE_UI')) {
    $style=<<<'BLADE'
<style id="FIX37_JOB_CARD_CORE_UI">
.jc-direct-entry{border-top:4px solid #0b315b;background:linear-gradient(180deg,#fff,#f9fbfd)}
.jc-direct-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.jc-entry-box{border:1px solid #d9e4ee;border-radius:14px;padding:14px;background:#fff}.jc-entry-box h3{margin:0 0 4px;color:#12395e}.jc-entry-box p{margin:0 0 12px}
.jc-entry-form{display:grid;grid-template-columns:2fr .7fr 1fr;gap:9px;align-items:end}.jc-entry-form label{font-size:11px;font-weight:800;color:#53677d}.jc-entry-form select,.jc-entry-form input{width:100%;min-height:40px;margin-top:5px;border:1px solid #cfdae6;border-radius:9px;padding:8px;background:#fff}.jc-entry-form .wide{grid-column:1/-1}.jc-entry-form .actions{grid-column:1/-1;justify-content:flex-end}
.jc-purchase-block{overflow:hidden}.jc-purchase-block .line-scroll{border:1px solid #dce5ef;border-radius:12px;overflow:auto}.jc-purchase-block .data-table{min-width:850px;border-collapse:separate;border-spacing:0}.jc-purchase-block .data-table thead th{position:sticky;top:0;background:#0b315b;color:#fff;padding:11px 10px;white-space:nowrap}.jc-purchase-block .data-table tbody td{padding:11px 10px;border-bottom:1px solid #e6edf4;background:#fff;vertical-align:top}.jc-purchase-block .data-table tbody tr:nth-child(even) td{background:#f8fbfd}.jc-parts-block{border-top:4px solid #168445}.jc-labour-block{border-top:4px solid #f59e0b}
.jc-labour-block .rot-card{border-left:5px solid #f59e0b!important;box-shadow:0 2px 8px rgba(15,39,69,.04)}
@media(max-width:860px){.jc-direct-grid{grid-template-columns:1fr}.jc-entry-form{grid-template-columns:1fr 1fr}}@media(max-width:560px){.jc-entry-form{grid-template-columns:1fr}.jc-entry-form .wide{grid-column:1}}
</style>
BLADE;
    $lines=preg_split('/\\R/',$v);
    $inserted=false;
    foreach($lines as $i=>$line){
        if(trim($line)==="@section('content')"){
            array_splice($lines,$i+1,0,preg_split('/\\R/',rtrim($style,"\r\n")));
            $inserted=true;
            break;
        }
    }
    if(!$inserted) throw new RuntimeException("Job Card content section anchor not found");
    $v=implode("\n",$lines);
}

if (!str_contains($v,'FIX37_DIRECT_ENTRY_PANEL')) {
    $panel=<<<'BLADE'
{{-- FIX37_DIRECT_ENTRY_PANEL --}}
@if(!in_array(strtolower((string)$job->status),['closed','invoiced','cancelled']))
<div class="card section-gap jc-direct-entry">
  <div class="section-head"><div><h2 class="section-title">＋ Direct Job Card Entry</h2><p class="muted">Estimate ke bina Part ya Labour / ROT directly add kijiye. Entry audit history me DIRECT_ADD ke roop me save hogi.</p></div></div>
  <div class="jc-direct-grid">
    <div class="jc-entry-box">
      <h3>Parts</h3><p class="muted">Parts Master se PLANNED line add hogi. Stock issue is step par nahi hoga.</p>
      <form method="post" action="{{ route('erp.job-cards.parts.direct',$job->id) }}" class="jc-entry-form">@csrf
        <label class="wide">Part<select name="part_master_id" required><option value="">Select Part…</option>@foreach(($partsMaster ?? collect()) as $m)<option value="{{ $m->id }}">{{ $m->part_code }} · {{ $m->part_name }} · Stock {{ $m->stock_qty ?? 0 }}</option>@endforeach</select></label>
        <label>Qty<input type="number" step="0.001" min="0.001" name="qty" value="1" required></label>
        <label>Rate<input type="number" step="0.01" min="0" name="rate" placeholder="Master rate"></label>
        <label>GST %<input type="number" step="0.01" min="0" max="100" name="gst_percent" placeholder="Master GST"></label>
        <label class="wide">Note<input name="note" placeholder="Reason / work note (optional)"></label>
        <div class="actions"><button class="btn primary">＋ Add Part to Job Card</button></div>
      </form>
    </div>
    <div class="jc-entry-box">
      <h3>Labour / ROT</h3><p class="muted">Labour Master se PLANNED line add hogi. Mechanic existing ROT control se assign hoga.</p>
      <form method="post" action="{{ route('erp.job-cards.labour.direct',$job->id) }}" class="jc-entry-form">@csrf
        <label class="wide">Labour / ROT<select name="labour_master_id" required><option value="">Select Labour / ROT…</option>@foreach(($labourMaster ?? collect()) as $m)<option value="{{ $m->id }}">{{ $m->rot_code }} · {{ $m->rot_description }}</option>@endforeach</select></label>
        <label>Std Hours<input type="number" step="0.01" min="0.01" name="standard_hours" placeholder="Master hours"></label>
        <label>Rate<input type="number" step="0.01" min="0" name="labour_rate" placeholder="Master rate"></label>
        <label>GST %<input type="number" step="0.01" min="0" max="100" name="gst_percent" placeholder="Master GST"></label>
        <label class="wide">Note<input name="note" placeholder="Work note (optional)"></label>
        <div class="actions"><button class="btn primary">＋ Add Labour / ROT</button></div>
      </form>
    </div>
  </div>
</div>
@endif
BLADE;
    $lines=preg_split('/\\R/',$v);
    $partsHeading=-1;
    foreach($lines as $i=>$line){
        if(str_contains($line,'<h2 class="section-title">Parts</h2>')){$partsHeading=$i;break;}
    }
    if($partsHeading<0) throw new RuntimeException("Parts heading not found");
    $insertAt=-1;
    for($i=$partsHeading;$i>=0;$i--){
        if(str_contains($lines[$i],'<div class="card section-gap')){$insertAt=$i;break;}
    }
    if($insertAt<0) throw new RuntimeException("Parts card start not found");
    array_splice($lines,$insertAt,0,preg_split('/\\R/',rtrim($panel,"\r\n")));
    $v=implode("\n",$lines);
}

$lines=preg_split('/\\R/',$v);
$partsHeading=-1;$labourHeading=-1;
foreach($lines as $i=>$line){
    if($partsHeading<0 && str_contains($line,'<h2 class="section-title">Parts</h2>')) $partsHeading=$i;
    if($labourHeading<0 && str_contains($line,'<h2 class="section-title">Labour / ROT Control</h2>')) $labourHeading=$i;
}
if($partsHeading<0 || $labourHeading<0) throw new RuntimeException("Parts/Labour headings not found");
foreach([[$partsHeading,'jc-purchase-block jc-parts-block'],[$labourHeading,'jc-purchase-block jc-labour-block']] as [$heading,$classes]){
    for($i=$heading;$i>=0;$i--){
        if(str_contains($lines[$i],'<div class="card section-gap')){
            if(!str_contains($lines[$i],$classes)){
                $lines[$i]=preg_replace('/class="card section-gap([^\"]*)"/','class="card section-gap$1 '.$classes.'"',$lines[$i],1);
            }
            break;
        }
    }
}
$v=implode("\n",$lines);

if (!str_contains($v,'FIX37_PART_DELETE_LABEL')) {
    $lines=preg_split('/\\R/',$v);
    $inserted=false;
    foreach($lines as $i=>$line){
        if(str_contains($line,'@forelse($parts as $p)')){
            $block=[
                "      @php(\$fix37Issued=!in_array(strtolower(trim((string)(\$p->issue_status ?? 'planned'))),['','planned','pending','not issued','not_issued'],true))",
                "      {{-- FIX37_PART_DELETE_LABEL --}}",
            ];
            array_splice($lines,$i+1,0,$block);
            $inserted=true;
            break;
        }
    }
    if(!$inserted) throw new RuntimeException("Parts loop anchor not found");
    $v=implode("\n",$lines);
}

$v=str_replace(
    '<summary class="btn small danger">🗑 Delete</summary>',
    '<summary class="btn small danger">{{ $fix37Issued ? \'↩ Reverse Stock & Remove\' : \'🗑 Delete\' }}</summary>',
    $v
);
$v=str_replace(
    'placeholder="Why should this planned part be removed?"',
    'placeholder="Reason for removing this Part from the Job Card"',
    $v
);
$v=preg_replace(
    '/(^\\s*)Confirm Delete\\s*$/m',
    '$1{{ $fix37Issued ? \'Reverse Stock & Remove\' : \'Confirm Delete\' }}',
    $v,
    1
);

file_put_contents($p,$v);
echo "PASS Job Card UI patched\n";
echo "FIX37 patch applied.\n";

