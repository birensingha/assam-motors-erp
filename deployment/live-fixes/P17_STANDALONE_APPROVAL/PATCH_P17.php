<?php
declare(strict_types=1);
/**
 * P17 standalone Job Card Admin Approval Desk.
 * Moves P16 approval form to a dedicated route/view, preserving the existing
 * decision, permissions and audit implementation. No business-table writes.
 * php PATCH_P17.php check|apply /absolute/staging/laravel/root
 */
$mode=$argv[1]??'check';
$root=rtrim((string)($argv[2]??''),'/');
if(!in_array($mode,['check','apply'],true)||!is_file($root.'/artisan')){
 fwrite(STDERR,"P17 usage: php PATCH_P17.php check|apply /absolute/staging/laravel/root\n");exit(2);
}
$edit=[
 'routes/web.php',
 'app/Http/Controllers/Web/AdminJobCardUnwindController.php',
 'resources/views/job-cards/unwind.blade.php'
];
$newFile='resources/views/job-cards/reversal-approvals.blade.php';
$payload=__DIR__.'/payload/reversal-approvals.blade.php';
if(!is_file($payload)||is_link($payload)){
 fwrite(STDERR,"P17_NO_GO_MISSING_PAYLOAD\n");exit(3);
}
$source=[];
foreach($edit as $f){
 if(!is_file($root.'/'.$f)||is_link($root.'/'.$f)){
   fwrite(STDERR,"P17_NO_GO_SOURCE_MISSING ".$f."\n");exit(3);
 }
 $source[$f]=(string)file_get_contents($root.'/'.$f);
}
if(is_file($root.'/'.$newFile)||is_link($root.'/'.$newFile)){
 fwrite(STDERR,"P17_NO_GO_NEW_VIEW_ALREADY_EXISTS; manual check to avoid overwrite\n");exit(4);
}
function oneP17(string $text,string $old,string $next,string $label):string{
 $n=substr_count($text,$old);
 if($n!==1)throw new RuntimeException("P17_NO_GO_ANCHOR_".$label."_".$n);
 return str_replace($old,$next,$text);
}
try{
 $r=$source[$edit[0]];
 $post="Route::post('/job-cards/{job}/reversal/reviews/{review}/decision', [\\App\\Http\\Controllers\\Web\\AdminJobCardUnwindController::class,'decideReversalReview'])->whereNumber('job')->whereNumber('review')->name('erp.job-cards.reversal.review-decision');";
 $route=<<<'ROUTE'

    // P17_STANDALONE_APPROVAL_DESK: owner/admin requests and decisions in dedicated page.
    Route::get('/job-cards/{job}/reversal/approvals', [\App\Http\Controllers\Web\AdminJobCardUnwindController::class,'approvalDesk'])->whereNumber('job')->name('erp.job-cards.reversal.approvals');
ROUTE;
 $r=oneP17($r,$post,$post.$route,'new_get_route');

 $c=$source[$edit[1]];
 $beforeMethod='    /** P16_REVERSAL_APPROVAL_DESK: approval is not a reversal transaction. */';
 $get=<<<'METHOD'
    /** P17_STANDALONE_APPROVAL_DESK: reuse admin-protected read-only request data. */
    public function approvalDesk(Request $request,int $job):View {
        // show() performs the same active Owner/Admin permission check and
        // prepares the review queue; this endpoint renders it separately.
        $page=$this->show($request,$job);
        return view('job-cards.reversal-approvals',$page->getData());
    }

METHOD;
 $c=oneP17($c,$beforeMethod,$get.$beforeMethod,'new_controller_get');
 $old="return redirect()->route('erp.job-cards.reversal',\$job)->with('success',\$message);";
 $next="return redirect()->route('erp.job-cards.reversal.approvals',\$job)->with('success',\$message);";
 $c=oneP17($c,$old,$next,'approval_post_redirect');

 $v=$source[$edit[2]];
 $start=' {{-- P16_REVERSAL_APPROVAL_DESK: approval queue for Assam Motors Owner/Admin; no automatic reversals --}}';
 $end=" <section class=\"jcu-card\">\n  <h2>Linked records &amp; cancellation safety</h2>";
 if(substr_count($v,$start)!==1||substr_count($v,$end)!==1)throw new RuntimeException('P17_NO_GO_P16_PANEL_BOUNDARY');
 $i=strpos($v,$start);$j=strpos($v,$end);
 if($i===false||$j===false||$i>=$j)throw new RuntimeException('P17_NO_GO_PANEL_ORDER');
 $section=substr($v,$i,$j-$i);
 if(!str_contains($section,"Save Admin Approval Decision")||!str_contains($section,'@endforelse')
    ||!str_contains($section,'</section>')){
    throw new RuntimeException('P17_NO_GO_P16_PANEL_STRUCTURE_CHANGED');
 }
 $link=<<<'BLADE'
 {{-- P17_STANDALONE_APPROVAL_DESK: shortcut only; forms live on independent page --}}
 <section class="jcu-card" id="p17-approval-shortcut" style="border-left:5px solid #397aa9">
  <h2>Admin Approval Desk</h2>
  <p class="jcu-small">Approval and rejection requests are managed on a separate Owner/Admin page. Review-requested does not mean actual Estimate/Stock/Purchase reversal.</p>
  <p class="jcu-small">Awaiting Admin: {{ $reversalReviews->where('status','REQUESTED')->count() }} · Approved for reconciliation: {{ $reversalReviews->where('status','APPROVED')->count() }}</p>
  <a class="jcu-btn" style="background:#135579;color:#fff" href="{{ route('erp.job-cards.reversal.approvals',$card->id) }}">Open Admin Approval Desk →</a>
 </section>
BLADE;
 $v=substr($v,0,$i).$link."\n".substr($v,$j);
 if(str_contains($v,'id="p16-approval-desk"')||str_contains($v,'Save Admin Approval Decision'))
     throw new RuntimeException('P17_NO_GO_OLD_FORM_STILL_PRESENT');
 $modified=[$edit[0]=>$r,$edit[1]=>$c,$edit[2]=>$v];
 $new=(string)file_get_contents($payload);
 if(!str_contains($new,'P17_STANDALONE_APPROVAL_DESK') || !str_contains($new,'Save Admin Approval Decision'))
     throw new RuntimeException('P17_NO_GO_PAYLOAD_MARKERS');
 token_get_all($r,TOKEN_PARSE);
 token_get_all($c,TOKEN_PARSE);
 chdir($root);
 require 'vendor/autoload.php';
 $app=require 'bootstrap/app.php';
 $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
 token_get_all(app('blade.compiler')->compileString($v),TOKEN_PARSE);
 token_get_all(app('blade.compiler')->compileString($new),TOKEN_PARSE);
 foreach($edit as $f)echo 'P17_PRECHECK '.$f.' OLD='.hash('sha256',$source[$f]).' NEW='.hash('sha256',$modified[$f])."\n";
 echo 'P17_NEW_VIEW_SHA256='.hash('sha256',$new)."\n";
 echo "P17_PHP_AND_BOTH_COMPILED_BLADE_CHECK=PASS\n";
}catch(Throwable $e){
 fwrite(STDERR,'P17_NO_GO_PRECHECK '.$e->getMessage()."\n");exit(5);
}
if($mode==='check'){echo "P17_DRY_RUN_NO_SOURCE_OR_DATA_WRITES\n";exit(0);}
$backup=$root.'/storage/app/p17_separate_desk_backup_'.date('Ymd_His').'_'.getmypid();
if(!mkdir($backup,0700,true)){fwrite(STDERR,"P17_NO_GO_BACKUP_DIR\n");exit(6);}
foreach($edit as $f){
 $dest=$backup.'/'.str_replace('/','__',$f);
 if(!copy($root.'/'.$f,$dest) || hash_file('sha256',$dest)!==hash('sha256',$source[$f])){
     fwrite(STDERR,"P17_NO_GO_BACKUP_VERIFY\n");exit(7);
 }
}
$writes=[];
try{
 foreach($edit as $f){
     $dest=$root.'/'.$f;
     if(hash_file('sha256',$dest)!==hash('sha256',$source[$f]))
         throw new RuntimeException('Concurrent edit: '.$f);
     $tmp=tempnam(dirname($dest),'.p17_');
     if(!$tmp||file_put_contents($tmp,$modified[$f],LOCK_EX)!==strlen($modified[$f]))
         throw new RuntimeException('Temporary write');
     chmod($tmp,fileperms($dest)&0777);
     if(!rename($tmp,$dest))throw new RuntimeException('Atomic replacement failed');
     $writes[]=$f;
     if(hash_file('sha256',$dest)!==hash('sha256',$modified[$f]))
         throw new RuntimeException('Result verification failed');
 }
 if(file_exists($root.'/'.$newFile))throw new RuntimeException('Approval page was concurrently created');
 if(!copy($payload,$root.'/'.$newFile))throw new RuntimeException('New view copy failed');
 $writes[]=$newFile;
 if(hash_file('sha256',$root.'/'.$newFile)!==hash('sha256',$new))
     throw new RuntimeException('New view hash mismatch');
 echo "P17_APPLIED_BACKUP=".$backup."\n";
 echo "P17_SOURCE_ONLY_NO_BUSINESS_DATA_MUTATIONS\n";
}catch(Throwable $e){
 foreach(array_reverse($writes) as $f){
     if($f===$newFile)@unlink($root.'/'.$f);
     else copy($backup.'/'.str_replace('/','__',$f),$root.'/'.$f);
 }
 fwrite(STDERR,'P17_ROLLBACK_EXECUTED '.$e->getMessage()."\n");exit(8);
}
