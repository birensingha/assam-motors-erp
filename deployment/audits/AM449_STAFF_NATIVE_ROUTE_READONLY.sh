#!/usr/bin/env bash
# AM449: read-only proof of native Staff Inspection, ROT, Alerts, Update paths.
set -Eeuo pipefail
cd "${1:?Required staging Laravel root}"
test -f artisan && test -f vendor/autoload.php
php <<'PHP'
<?php
declare(strict_types=1);
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\Route;

$requested=[
 'GET api/v1/staff/inspections',
 'GET api/v1/staff/inspections/{job}',
 'POST api/v1/staff/inspections/{job}',
 'GET api/v1/staff/notifications',
 'GET api/v1/staff/rots',
 'GET api/v1/app-update',
 'GET api/v1/admin/mobile/manager-inspections',
 'GET api/v1/admin/mobile/manager-inspections/{job}',
 'POST api/v1/admin/mobile/manager-inspections/{job}/assign',
 'POST api/v1/admin/mobile/manager-inspections/{job}/complete'
];
$found=[];
foreach(Route::getRoutes() as $route){
 $uri=(string)$route->uri();
 foreach($route->methods() as $verb){
  $found[$verb.' '.$uri]=true;
 }
}
foreach($requested as $candidate){
 echo 'AM449_ROUTE='.($found[$candidate]??false?'PRESENT ':'MISSING ').
    $candidate."\n";
}
echo 'AM449_ROUTE_METADATA_ONLY_NO_WRITES'."\n";
PHP
