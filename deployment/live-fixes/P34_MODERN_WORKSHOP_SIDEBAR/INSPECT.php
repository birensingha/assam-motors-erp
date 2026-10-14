<?php
declare(strict_types=1);
$root=rtrim((string)($argv[1]??''),'/');
$file=$root.'/resources/views/layouts/erp.blade.php';
if(!is_file($file))exit(2);
$s=file_get_contents($file);
$start=strpos($s,'<div class="nav-group-label">WORKSHOP ERP</div>');
if($start===false){echo "P34_HEADING_MISSING\n";exit(3);}
$end=strpos($s,'<div class="nav-group-label">',$start+30);
if($end===false){echo "P34_NEXT_GROUP_MISSING\n";exit(4);}
echo "P34_WORKSHOP_BEGIN\n";
echo substr($s,$start,min($end-$start,13000));
echo "\nP34_WORKSHOP_END\n";
$navpos=strpos($s,'<nav class="sidebar-nav">');
echo "P34_NAV_FOUND=".($navpos!==false?'YES':'NO')."\n";
$csspos=strpos($s,'.sidebar');
if($csspos!==false)echo "P34_CSS_PREVIEW\n".substr($s,$csspos,3500)."\n";
