<?php
declare(strict_types=1);
/** Read-only locate version 6.3.4 update metadata/artifact; never print secrets, PII or tokens */
$root=rtrim((string)($argv[1]??''),'/');
if(!is_file($root.'/artisan'))exit(2);
$domain=dirname($root);
echo "P42_STAGING_ROOT_VALID\n";
$locs=[
 $domain.'/public_html/staff-app',
 $domain.'/staging/staff-app',
 $domain.'/public_html/legacy/staff-app',
 $domain.'/staff-app',
 $domain.'/assam-erp-staging/public/staff-app',
 $domain.'/public_html/app-updates'
];
foreach($locs as $dir){
 if(!is_dir($dir))continue;
 echo "P42_UPDATE_DIR_PRESENT ".str_replace($domain,'{DOMAIN}',$dir)."\n";
 $meta=$dir.'/latest.json';
 if(is_file($meta)){
  $j=json_decode((string)file_get_contents($meta),true);
  if(is_array($j)){
   foreach(['version_name','version_code','apk_file','channel','published_at'] as $k){
    echo 'P42_METADATA '.$k.'='.substr((string)($j[$k]??'missing'),0,130)."\n";
   }
   $file=$dir.'/'.basename((string)($j['apk_file']??''));
   if(is_file($file)&&preg_match('/\.apk$/i',$file)){
    echo 'P42_APK_IN_CHANNEL=YES APK_BYTES='.filesize($file)."\n";
   }
  }else echo "P42_METADATA_INVALID\n";
 }
 foreach(glob($dir.'/*.apk')?:[] as $apk){
  echo 'P42_APK_AVAILABLE_NAME='.basename($apk).' SIZE='.filesize($apk)."\n";
 }
}
echo "P42_INSPECTION_READ_ONLY_DONE\n";
