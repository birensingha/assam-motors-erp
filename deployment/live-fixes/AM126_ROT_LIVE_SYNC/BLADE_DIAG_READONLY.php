<?php
declare(strict_types=1);
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Blade;
$base='resources/views/dashboard/partials/rot-live-clock.blade.php';
$broken=$base.'.AM126_FAILED_COPY';
echo "AM126_BLADE_DIAG_START\n";
foreach([$base,$broken] as $name){
 echo 'FILE='.$name.' EXISTS='.(is_file($name)?'YES':'NO')."\n";
 if(!is_file($name))continue;
 $text=file_get_contents($name);
 echo 'MARKER_AM126='.(str_contains($text,'AM126_COMPLETED_TAT_FROM_CORRECTED_START_END')?'YES':'NO')."\n";
 $compiled=Blade::compileString($text);
 $rows=explode("\n",$compiled);
 foreach(array_slice($rows,0,135) as $i=>$row){
    if(preg_match('/rotClock|am126|<\?php|session\)|\?>|data-am-elapsed/',$row)){
       echo sprintf("%s PHP_LINE_%d=%s\n",basename($name),$i+1,substr(trim($row),0,220));
    }
 }
}
$line=DB::table('job_card_labour')->where('job_card_id',40)->where('rot_code','ROT-CUS-0198')->first();
$session=DB::table('rot_sessions')->where('id',$line->rot_session_id)->first();
$method=new ReflectionMethod(\App\Support\RotLiveClock::class,'snapshot');
echo 'SNAPSHOT_PARAMETER='.(string)$method->getParameters()[0]->getType()."\n";
try{
 $snap=\App\Support\RotLiveClock::snapshot($session);
 echo 'SNAPSHOT_ELAPSED='.(string)($snap['elapsed_seconds']??'n/a')."\n";
}catch(Throwable $e){echo 'SNAPSHOT_ERROR='.get_class($e).' '.$e->getMessage()."\n";}
try{
 $html=view('dashboard.partials.rot-live-clock',['session'=>$session,'line'=>$line])->render();
 echo 'BASELINE_RENDER=PASS LENGTH='.strlen($html)."\n";
}catch(Throwable $e){
 echo 'BASELINE_RENDER_ERROR='.get_class($e).' '.substr($e->getMessage(),0,180)."\n";
}
echo "AM126_BLADE_DIAG_COMPLETE_READONLY\n";
