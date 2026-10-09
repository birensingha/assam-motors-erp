<?php
require_once __DIR__.'/config.php';
date_default_timezone_set('Asia/Kolkata');
if ($_SERVER['REQUEST_METHOD']==='OPTIONS'){ http_response_code(204); exit; }

function amInspectionAuth(): array {
    $headers=function_exists('getallheaders')?getallheaders():[];
    $auth=$headers['Authorization']??$headers['authorization']??($_SERVER['HTTP_AUTHORIZATION']??($_SERVER['REDIRECT_HTTP_AUTHORIZATION']??''));
    if(!preg_match('/Bearer\s+(.+)$/i',(string)$auth,$m)) sendJson(['error'=>'Staff login required'],401);
    $parts=explode('.',$m[1]);
    if(count($parts)!==2) sendJson(['error'=>'Invalid staff session'],401);
    [$data,$sig]=$parts;
    $expected=hash_hmac('sha256',$data,JWT_SECRET);
    if(!hash_equals($expected,$sig)) sendJson(['error'=>'Invalid staff session'],401);
    $payload=json_decode(base64_decode($data),true);
    if(!$payload||empty($payload['staffId'])||($payload['exp']??0)<time()) sendJson(['error'=>'Staff session expired'],401);
    return $payload;
}
function amInspectionTableExists(PDO $db,string $table): bool {
    try{
        $q=$db->prepare("SHOW TABLES LIKE ?");
        $q->execute([$table]);
        return (bool)$q->fetchColumn();
    }catch(Throwable $e){ return false; }
}
$auth=amInspectionAuth();
$staffId=(int)$auth['staffId'];
$db=getDB();
$jobId=(int)($_GET['job_id']??0);

if($_SERVER['REQUEST_METHOD']==='GET'){
    if($jobId>0){
        $q=$db->prepare("SELECT id,job_no,customer_name,customer_phone,vehicle_reg_no,vehicle_make,vehicle_model,vehicle_year,service_type,customer_complaint,inspection_status,inspection_submitted_at,inspection_submitted_by,technician_id,technician FROM job_cards WHERE id=? AND technician_id=? LIMIT 1");
        $q->execute([$jobId,$staffId]);
        $job=$q->fetch(PDO::FETCH_ASSOC);
        if(!$job) sendJson(['error'=>'This Vehicle Inspection is not assigned to you'],403);
        $items=[];
        if(amInspectionTableExists($db,'job_card_inspection')){
            $q=$db->prepare("SELECT * FROM job_card_inspection WHERE job_card_id=? ORDER BY id");
            $q->execute([$jobId]);
            $items=$q->fetchAll(PDO::FETCH_ASSOC);
        }
        sendJson(['ok'=>true,'job'=>$job,'items'=>$items]);
    }

    $q=$db->prepare("
        SELECT id,job_no,customer_name,customer_phone,vehicle_reg_no,vehicle_make,vehicle_model,vehicle_year,service_type,customer_complaint,
               COALESCE(inspection_status,'pending') AS inspection_status,inspection_submitted_at,inspection_submitted_by,technician_id,technician
        FROM job_cards
        WHERE technician_id=?
        ORDER BY CASE WHEN LOWER(COALESCE(inspection_status,'pending')) IN ('completed','skipped') THEN 1 ELSE 0 END ASC, id DESC
        LIMIT 100
    ");
    $q->execute([$staffId]);
    $rows=$q->fetchAll(PDO::FETCH_ASSOC);
    sendJson(['ok'=>true,'inspections'=>$rows,'jobs'=>$rows]);
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    $input=getJsonInput();
    if($jobId<=0) $jobId=(int)($input['job_id']??0);
    if($jobId<=0) sendJson(['error'=>'Job Card ID required'],400);

    $q=$db->prepare("SELECT id,inspection_status FROM job_cards WHERE id=? AND technician_id=? LIMIT 1");
    $q->execute([$jobId,$staffId]);
    $job=$q->fetch(PDO::FETCH_ASSOC);
    if(!$job) sendJson(['error'=>'This Vehicle Inspection is not assigned to you'],403);
    if(in_array(strtolower(trim((string)($job['inspection_status']??''))),['completed','skipped'],true)){
        sendJson(['error'=>'Vehicle Inspection is already completed'],409);
    }

    $items=$input['items']??[];
    if(!is_array($items)||count($items)<1) sendJson(['error'=>'Inspection checklist required'],422);
    $clean=[];
    foreach($items as $it){
        $name=trim((string)($it['item_name']??''));
        $status=strtolower(trim((string)($it['status']??'')));
        $remarks=trim((string)($it['remarks']??''));
        if($name===''||strlen($name)>100) sendJson(['error'=>'Invalid inspection item'],422);
        if(!in_array($status,['good','fair','poor'],true)) sendJson(['error'=>'Invalid inspection status for '.$name],422);
        $clean[]=['item_name'=>$name,'status'=>$status,'remarks'=>mb_substr($remarks,0,1000)];
    }

    try{
        $db->beginTransaction();
        if(amInspectionTableExists($db,'job_card_inspection')){
            $db->prepare("DELETE FROM job_card_inspection WHERE job_card_id=?")->execute([$jobId]);
            $ins=$db->prepare("INSERT INTO job_card_inspection (job_card_id,item_name,status,remarks,photo,created_at) VALUES (?,?,?,?,?,NOW())");
            foreach($clean as $it){
                $ins->execute([$jobId,$it['item_name'],$it['status'],$it['remarks'],'']);
            }
        }
        $u=$db->prepare("UPDATE job_cards SET inspection_status='completed',inspection_submitted_at=NOW(),inspection_submitted_by=?,diagnosis_status='pending',estimate_allowed=0,customer_public_status='Inspection Completed',updated_at=NOW() WHERE id=? AND technician_id=?");
        $u->execute([$staffId,$jobId,$staffId]);
        $db->commit();
        sendJson(['ok'=>true,'message'=>'Vehicle Inspection completed and sent to Job Card.','job_id'=>$jobId,'inspection_status'=>'completed']);
    }catch(Throwable $e){
        if($db->inTransaction()) $db->rollBack();
        sendJson(['error'=>$e->getMessage()],500);
    }
}

sendJson(['error'=>'Method not allowed'],405);
