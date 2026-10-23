#!/usr/bin/env bash
# FIX115: STAGING-only, audited deletion of unstarted/unassigned JC Labour/ROT.
set -Eeuo pipefail
cd "$HOME/domains/assammotors.com/assam-erp-staging" || exit 1
controller='app/Http/Controllers/Web/AdminLabourLineAuditController.php'
show='resources/views/job-cards/show.blade.php'
editor='resources/views/job-cards/rot-alteration.blade.php'
partial='resources/views/job-cards/partials/planned-rot-delete.blade.php'
for f in "$controller" "$show" "$editor"; do [ -f "$f" ] || { echo "STOP: $f missing"; exit 1; }; done
[ ! -e "$partial" ] || { echo "STOP: $partial already exists. No changes made."; exit 1; }
mkdir -p storage/app/am-backups
backup="storage/app/am-backups/FIX115_$(date +%Y%m%d_%H%M%S)_$$"
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
    echo 'FIX115 FAILED: all three prior source files restored; new partial removed.'
  fi
  exit "$code"
}
trap rollback EXIT
export AM115_CONTROLLER="$controller" AM115_SHOW="$show" AM115_EDITOR="$editor" AM115_PARTIAL="$partial"
modified=1
php <<'PHP'
<?php
$paths=[getenv('AM115_CONTROLLER'),getenv('AM115_SHOW'),getenv('AM115_EDITOR')];
[$c,$v,$e]=array_map('file_get_contents',$paths);
if(!is_string($c)||!is_string($v)||!is_string($e))exit(2);
function replaceExactly(string $source,string $old,string $new,string $label):string {
    if(substr_count($source,$old)!==1){fwrite(STDERR,"STOP: FIX115 anchor mismatch: $label; rolled back.\n");exit(3);}
    return str_replace($old,$new,$source);
}
// Leave all existing session/segment/timing, mechanic, WIP, invoice, purchase and tax validations untouched.
$c=replaceExactly($c,
    '|| !empty($old->rot_session_id)||!empty($old->source_estimate_item_id)',
    '|| !empty($old->rot_session_id)', 'approved estimate guard');
$c=replaceExactly($c,
    "'action'=>'UNSTARTED_LABOUR_DELETE_FIX92'",
    "'action'=>!empty(\$old->source_estimate_item_id) ? 'UNSTARTED_ESTIMATE_ROT_CANCEL_FIX115' : 'UNSTARTED_LABOUR_DELETE_FIX92'",
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
                    if(Schema::hasColumn('workshop_purchases',$column) &&
                       DB::table('workshop_purchases')->where('job_card_id',$job)->where($column,$line)->exists()){
                        $purchaseLinked=true; break;
                    }
                }
            }
GUARD;
$c=replaceExactly($c,$anchor,$guard."\n".$anchor,'alternate linked purchases');
$showAnchor='      @endif' . "\n" . '      </div>' . "\n" . '    </div>' . "\n" . '  @empty<div class="empty">No Labour/ROT lines on this Job Card.</div>';
$showReplacement='      @endif' . "\n" . '      </div>' . "\n" . "      {{-- FIX115: audited unstarted ROT cancellation; separate from running-work controls --}}\n" .
    "      @include('job-cards.partials.planned-rot-delete', ['line'=>\$line, 'job'=>\$job, 'linkedRot'=>\$rot])\n" .
    '    </div>' . "\n" . '  @empty<div class="empty">No Labour/ROT lines on this Job Card.</div>';
$v=replaceExactly($v,$showAnchor,$showReplacement,'Job Card ROT card');
$editorAnchor="    </form>\n  </article>\n  @empty";
$editorReplacement="    </form>\n".
    "    {{-- FIX115: independent, audited planned ROT cancellation (not completed-work alteration) --}}\n".
    "    @include('job-cards.partials.planned-rot-delete', ['line'=>\$line, 'job'=>\$job, 'linkedRot'=>\$p55Session])\n".
    "  </article>\n  @empty";
$e=replaceExactly($e,$editorAnchor,$editorReplacement,'Amount Editor ROT card');
$partial=<<<'BLADE'
{{-- FIX115: use the existing strictly-admin labour deletion transaction and audit. --}}
@php
    $am115Wip=in_array(strtolower(trim((string)($job->status??''))),['wip','in progress','in-progress','work in progress'],true);
    $am115State=strtolower(trim((string)($line->execution_status??'planned')));
    $am115Eligible=$am115Wip && empty($job->invoice_id)
        && empty($linkedRot) && empty($line->rot_session_id)
        && empty($line->mechanic_id) && empty($line->rot_start) && empty($line->rot_end)
        && (int)($line->productive_seconds??0)===0
        && in_array($am115State,['','planned','pending','not_started','not started'],true)
        && strtoupper(trim((string)($line->rot_code??'')))!=='OSL'
        && strtoupper(trim((string)($line->mechanic_name??'')))!=='OSL';
@endphp
@if($am115Eligible)
  <details class="am115-delete" style="margin:8px 12px 11px;padding:10px;border:1px solid #efc6c6;border-radius:9px;background:#fffafa">
    <summary style="cursor:pointer;color:#a32727;font-size:12px;font-weight:800">🗑 Delete ROT (Admin Audit)</summary>
    <form class="am115-cancel-form" method="post" action="{{ route('erp.job-cards.labour.delete-admin',[$job->id,$line->id]) }}" style="margin-top:10px" onsubmit="return confirm('Remove this unstarted ROT from the Job Card? This will be audited and update billing totals. Original approved estimate stays unchanged.');">
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
BLADE;
if(strpos($c,'UNSTARTED_ESTIMATE_ROT_CANCEL_FIX115')===false ||
   strpos($v,'FIX115: audited unstarted ROT cancellation')===false ||
   strpos($e,'FIX115: independent')===false ||
   strpos($partial,"@method('DELETE')")===false)exit(4);
$updated=[$c,$v,$e];
foreach($paths as $i=>$path){if(file_put_contents($path,$updated[$i])===false){fwrite(STDERR,"STOP: Cannot write $path\n");exit(5);}}
$p=getenv('AM115_PARTIAL');
if(!is_dir(dirname($p)) && !mkdir(dirname($p),0755,true) && !is_dir(dirname($p)))exit(6);
if(file_put_contents($p,$partial)===false)exit(7);
echo "FIX115 CODE STAGED (no database records changed).\n";
PHP
php -l "$controller"
php artisan view:clear
php artisan view:cache
comp="$(grep -l 'am115-delete' storage/framework/views/*.php 2>/dev/null | head -n 1 || true)"
[ -n "$comp" ] || { echo 'STOP: Compiled audited delete panel not found.'; exit 1; }
php -l "$comp"
echo 'FIX115 SOURCE / BLADE VALIDATION PASSED'
echo "Backups: $backup"
echo 'NEXT: Test one approved-estimate PLANNED/UNASSIGNED ROT in STAGING with real admin confirmation. No ROT is automatically deleted.'
