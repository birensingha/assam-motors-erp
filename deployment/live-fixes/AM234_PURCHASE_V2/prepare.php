<?php
/**
 * AM234 Parts Purchase UI rebuild, JC picker only.
 * Defaults to dry-run. Requires explicit "apply" to write staging.
 * Does not change database, stock, purchases, or financial services.
 */
declare(strict_types=1);
$root=rtrim($argv[1]??'', '/');
$mode=$argv[2]??'check';
if (!$root || !in_array($mode,['check','apply'],true)) {fwrite(STDERR,"Usage: php prepare.php APP_ROOT [check|apply]\n");exit(2);}
$jsPath="$root/public/js/purchase-entry.js";
$viewPath="$root/resources/views/purchases/create.blade.php";
if (!is_file("$root/artisan") || !is_file($jsPath) || !is_file($viewPath)) {fwrite(STDERR,"Not a verified staging Laravel root. STOP.\n");exit(1);}
$js=file_get_contents($jsPath); $view=file_get_contents($viewPath);
$replacement=file_get_contents(__DIR__.'/jc-picker-function.js');
$css=file_get_contents(__DIR__.'/jc-picker.css');
if ($js===false || $view===false || $replacement===false || $css===false) {fwrite(STDERR,"Missing source or payload. STOP.\n");exit(1);}
$marker='AM234_PURCHASE_JC_PICKER_V2';
if (str_contains($js,$marker)) {echo "ALREADY_APPLIED\n";exit(0);}
$start='  function wireJobFilter(row) {';
$end="\n  function wire(row) {";
if (substr_count($js,$start)!==1 || substr_count($js,$end)!==1 || substr_count($view,"@section('content')")!==1) {
 fwrite(STDERR,"Source contract mismatch; nothing changed.\n");exit(1);
}
$from=strpos($js,$start);$to=strpos($js,$end,$from);
if ($from===false || $to===false || $to<=$from || $to-$from>20000) {fwrite(STDERR,"Picker boundary invalid; STOP.\n");exit(1);}
$newJs=substr($js,0,$from).$replacement.substr($js,$to);
$newView=str_replace("@section('content')","@section('content')\n<style>\n{$css}\n</style>",$view);
$lines=preg_split('/(?<=\n)/',$newView);
$versionCount=0;
foreach ($lines as &$line) {
 if (str_contains($line,'purchase-entry.js')) {
  $line=preg_replace_callback('/\?v=(\d+)/',static fn($m)=>'?v='.((int)$m[1]+1),$line,1,$incremented);
  $versionCount+=$incremented;
 }
}
unset($line);
if ($versionCount!==1) {fwrite(STDERR,"Could not uniquely update purchase JS version. STOP.\n");exit(1);}
$newView=implode('',$lines);
if (substr_count($newJs,$marker)!==1 || substr_count($newView,$marker)!==1) {fwrite(STDERR,"Payload verification failed. STOP.\n");exit(1);}
echo "AM234_PREFLIGHT_PASS\nCurrent-JS-SHA256: ".hash('sha256',$js)."\n";
if ($mode==='check') {echo "DRY_RUN_NO_CHANGES\n";exit(0);}
$backup="$root/storage/app/am-backups/AM234_PURCHASE_JC_".date('Ymd_His');
if (!is_dir($backup) && !mkdir($backup,0750,true)) {fwrite(STDERR,"Backup directory failed. STOP.\n");exit(1);}
if (!copy($jsPath,"$backup/purchase-entry.js") || !copy($viewPath,"$backup/create.blade.php")) {fwrite(STDERR,"Backups incomplete. STOP.\n");exit(1);}
$jsTmp="$jsPath.am234_tmp";$viewTmp="$viewPath.am234_tmp";
if (file_put_contents($jsTmp,$newJs)===false || file_put_contents($viewTmp,$newView)===false) {
 @unlink($jsTmp);@unlink($viewTmp);fwrite(STDERR,"Temporary write failed. STOP.\n");exit(1);
}
if (!rename($jsTmp,$jsPath)) {@unlink($viewTmp);fwrite(STDERR,"JS activation failed. Originals unchanged.\n");exit(1);}
if (!rename($viewTmp,$viewPath)) {copy("$backup/purchase-entry.js",$jsPath);fwrite(STDERR,"View activation failed; JS rolled back.\n");exit(1);}
echo "AM234_INSTALLED\nBackup: $backup\nBrowser smoke still required.\n";
