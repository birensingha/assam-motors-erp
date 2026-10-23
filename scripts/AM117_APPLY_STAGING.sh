#!/usr/bin/env bash
# AM117: STAGING-only, audited deletion of unstarted/unassigned JC Labour/ROT.
set -Eeuo pipefail
cd "$HOME/domains/assammotors.com/assam-erp-staging" || exit 1
controller='app/Http/Controllers/Web/AdminLabourLineAuditController.php'
show='resources/views/job-cards/show.blade.php'
editor='resources/views/job-cards/rot-alteration.blade.php'
partial='resources/views/job-cards/partials/planned-rot-delete.blade.php'
for f in "$controller" "$show" "$editor"; do [ -f "$f" ] || { echo "STOP: $f missing"; exit 1; }; done
[ ! -e "$partial" ] || { echo "STOP: $partial already exists. No changes made."; exit 1; }
mkdir -p storage/app/am-backups
backup="storage/app/am-backups/AM117_$(date +%Y%m%d_%H%M%S)_$$"
mkdir -p "$backup"
cp -p "$controller" "$backup/AdminLabourLineAuditController.php"
cp -p "$show" "$backup/show.blade.php"
cp -p "$editor" "$backup/rot-alteration.blade.php"
modified=0
rollback() {
  code=$?
  trap - EXIT
  if [ "$code" -ne 0 ] && [ "$modified" -eq 1 ]; then
    cp -p "$backup/AdminLabourLineAuditController.php" "$controller"
    cp -p "$backup/show.blade.php" "$show"
    cp -p "$backup/rot-alteration.blade.php" "$editor"
    rm -f "$partial"
    php artisan view:clear || true
    echo 'AM117 FAILED: all three prior source files restored; new partial removed.'
  fi
  exit "$code"
}
trap rollback EXIT
export AM117_CONTROLLER="$controller" AM117_SHOW="$show" AM117_EDITOR="$editor" AM117_PARTIAL="$partial"
modified=1
php <<'PHP'
<?php
$paths=[getenv('AM117_CONTROLLER'),getenv('AM117_SHOW'),getenv('AM117_EDITOR')];
[$c,$v,$e]=array_map('file_get_contents',$paths);
if(!is_string($c)||!is_string($v)||!is_string($e))exit(2);
function replaceExactly(string $source,string $old,string $new,string $label):string {
    if(substr_count($source,$old)!==1){fwrite(STDERR,"STOP: AM117 anchor mismatch: $label; rolled back.\n");exit(3);}
    return str_replace($old,$new,$source);
}
// Leave all existing session/segment/timing, mechanic, WIP, invoice, purchase and tax validations untouched.
$c=replaceExactly($c,
    '|| !empty($old->rot_session_id)||!empty($old->source_estimate_item_id)',
    '|| !empty($old->rot_session_id)', 'approved estimate guard');
$c=replaceExactly($c,
    "'action'=>'UNSTARTED_LABOUR_DELETE_FIX92'",
    "'action'=>!empty(\$old->source_estimate_item_id) ? 'UNSTARTED_ESTIMATE_ROT_CANCEL_AM117' : 'UNSTARTED_LABOUR_DELETE_FIX92'",
    'audit action');
$c=replaceExactly($c,
    "'after_json'=>null,'reason'=>\$data['reason']",
    "'after_json'=>json_encode(['removed_from_job_card'=>true,'source_estimate_item_id'=>\$old->source_estimate_item_id??null],JSON_INVALID_UTF8_SUBSTITUTE),'reason'=>\$data['reason']",
    'cancellation audit snapshot');
$c=replaceExactly($c,
    'ROT assigned/executed or from approved Estimate/Purchase. Cannot erase mechanic/timing/estimate history. Request an audited ROT/Estimate reversal instead.',
    'Only unassigned, never-started, unlinked ROT can be cancelled. Assigned, worked or purchase-linked ROT needs authorised reversal review.',
    'safe eligibility message');
$c=replaceExactly($c,
    "'Unstarted unassigned Labour deleted with Admin audit.'",
    "'Unstarted unassigned ROT removed from Job Card. Audit saved; approved Estimate preserved.'",
    'success message');
$anchor="            \$execution=strtolower(trim((string)(\$old->execution_status??'planned')));";
$guard=<<<'GUARD'
            if(!$purchaseLinked && Schema::hasTable('workshop_purchases')){
                foreach(['job_card_labour_id','billing_line_id','customer_billing_line_id'] as $column){
                    if(Schema::hasColumn('workshop_purchases',$column)){
                        $pq=DB::table('workshop_purchases')->where($column,$line);
                        if(Schema::hasColumn('workshop_purchases','job_card_id'))$pq->where('job_card_id',$job);
                        if($pq->exists()){$purchaseLinked=true;break;}
                    }
                }
            }
GUARD;
$c=replaceExactly($c,$anchor,$guard."\n".$anchor,'alternate linked purchases');
$assignmentButton='<button class="btn small primary">Assign ROT</button>';
$regex='#('.preg_quote($assignmentButton,'#').'\s*</form>)#';
if(preg_match_all($regex,$v)!==1){fwrite(STDERR,"STOP: unexpected number of Assign ROT forms\n");exit(3);}
$v=preg_replace($regex,'$1'."\n".
  "      {{-- AM117: audited planned ROT delete in Assignment card --}}\n".
  "      @include('job-cards.partials.planned-rot-delete', ['line'=>\$line, 'job'=>\$job, 'linkedRot'=>\$rot])",
  $v,1);
$partial=<<<'BLADE'
{{-- AM117: use the existing strictly-admin labour deletion transaction and audit. --}}
@php
    $am117Wip=in_array(strtolower(trim((string)($job->status??''))),['wip','in progress','in-progress','work in progress'],true);
    $am117Actor=request()->user();
    $am117Admin=$am117Actor && (bool)($am117Actor->active??false) && strtolower(trim((string)($am117Actor->role??'')))==='admin';
    $am117State=strtolower(trim((string)($line->execution_status??'planned')));
    $am117Eligible=$am117Admin && $am117Wip && empty($job->invoice_id)
        && empty($linkedRot) && empty($line->rot_session_id)
        && empty($line->mechanic_id) && empty($line->rot_start) && empty($line->rot_end)
        && (int)($line->productive_seconds??0)===0
        && in_array($am117State,['','planned','pending','not_started','not started'],true)
        && strtoupper(trim((string)($line->rot_code??'')))!=='OSL'
        && strtoupper(trim((string)($line->mechanic_name??'')))!=='OSL';
@endphp
@if($am117Eligible)
  <details class="am117-delete" style="display:inline-block;position:relative;margin:3px 5px 3px 0;padding:5px;border:1px solid #efc6c6;border-radius:6px;background:#fffafa">
    <summary style="cursor:pointer;color:#a32727;font-size:12px;font-weight:800">Delete ROT</summary>
    <form class="am117-cancel-form" method="post" action="{{ route('erp.job-cards.labour.delete-admin',[$job->id,$line->id]) }}" style="margin-top:10px" onsubmit="return confirm('Remove this unstarted ROT from the Job Card? This will be audited and update billing totals. Original approved estimate stays unchanged.');">
      @csrf
      @method('DELETE')
      <input type="hidden" name="expected_old_total" value="{{ number_format((float)$line->total,2,'.','') }}">
      <label style="display:block;font-weight:700;font-size:11px;color:#6a3535">Mandatory Deletion Reason (12–500 characters)
        <textarea name="reason" rows="2" required minlength="12" maxlength="500" style="display:block;width:100%;min-height:60px;border:1px solid #d9bcbc;border-radius:7px;padding:8px" placeholder="Why should this unstarted ROT be removed?"></textarea>
      </label>
      <p style="font-size:11px;color:#6e4b4b;margin:8px 0">Approved estimate remains unchanged. ROT cancellation, Admin identity and original values are saved in Job Card audit. Assigned/worked ROTs cannot use this control.</p>
      <button type="submit" style="background:#b3262d;color:#fff;border:0;border-radius:7px;padding:9px 12px;font-weight:800;cursor:pointer">Confirm Delete ROT</button>
    </form>
  </details>
@endif
@once
<style>
.jc-labour-block details.am117-delete[open]{z-index:2000}
.jc-labour-block details.am117-delete[open] form{position:absolute;top:100%;left:0;z-index:2001;
  width:min(355px,calc(100vw - 45px));box-sizing:border-box;background:#fff;border:1px solid #deb4b4;
  padding:12px;border-radius:8px;box-shadow:0 8px 20px rgba(20,40,60,.2)}
.jc-labour-block details.am117-delete textarea{box-sizing:border-box;width:100%}
</style>
@endonce
BLADE;
if(strpos($c,'UNSTARTED_ESTIMATE_ROT_CANCEL_AM117')===false ||
   strpos($v,'AM117: audited planned ROT delete in Assignment card')===false ||
   strpos($partial,"@method('DELETE')")===false)exit(4);
$updated=[$c,$v,$e];
foreach($paths as $i=>$path){if(file_put_contents($path,$updated[$i])===false){fwrite(STDERR,"STOP: Cannot write $path\n");exit(5);}}
$p=getenv('AM117_PARTIAL');
if(!is_dir(dirname($p)) && !mkdir(dirname($p),0755,true) && !is_dir(dirname($p)))exit(6);
if(file_put_contents($p,$partial)===false)exit(7);
echo "AM117 CODE STAGED (no database records changed).\n";
PHP
php -l "$controller"
php artisan view:clear
php artisan view:cache
comp="$(grep -l 'am117-delete' storage/framework/views/*.php 2>/dev/null | head -n 1 || true)"
[ -n "$comp" ] || { echo 'STOP: Compiled audited delete panel not found.'; exit 1; }
php -l "$comp"
echo 'AM117 SOURCE / BLADE VALIDATION PASSED'
echo "Backups: $backup"
echo 'NEXT: Test one approved-estimate PLANNED/UNASSIGNED ROT in STAGING with real admin confirmation. No ROT is automatically deleted.'
