<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/requests/workflow.php';
require __DIR__ . '/http.php';
require __DIR__ . '/request-helpers.php';
$baseUrl = rtrim($argv[1] ?? 'http://127.0.0.1:8080/elia-system', '/');
if (!in_array(parse_url($baseUrl, PHP_URL_HOST), ['127.0.0.1','localhost'], true)) { exit("Local servers only.\n"); }
$pdo=database(); $checks=0; $accounts=[]; $types=[]; $suffix=bin2hex(random_bytes(6)); $password=bin2hex(random_bytes(16));
$runtime=__DIR__.'/.runtime'; if(!is_dir($runtime)){mkdir($runtime,0700,true);}
$fixture=$runtime.'/monitor-'.$suffix.'.pdf'; file_put_contents($fixture,"%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n");
$yesterday=(new DateTimeImmutable(monitoring_today()))->modify('-1 day')->format('Y-m-d');
$tomorrow=(new DateTimeImmutable(monitoring_today()))->modify('+1 day')->format('Y-m-d');
$csrf = '';
try {
    foreach(['admin','a','b'] as $key){
        $statement=$pdo->prepare('INSERT INTO users (full_name,email,password_hash,role) VALUES (:name,:email,:hash,:role)');
        $statement->execute(['name'=>'Monitoring '.$key,'email'=>"monitor.$key.$suffix@example.test",'hash'=>password_hash($password,PASSWORD_DEFAULT),'role'=>$key==='admin'?'admin':'client']);
        $accounts[$key]=(int)$pdo->lastInsertId();$browsers[$key]=browser();$page=request($browsers[$key],'login.php');
        check(request($browsers[$key],'actions/login.php',['email'=>"monitor.$key.$suffix@example.test",'password'=>$password,'csrf_token'=>token($page)])['status']===303,"$key signs in");
    }
    $client=$browsers['a'];$other=$browsers['b'];$admin=$browsers['admin'];
    $name='Monitoring fixture '.$suffix;
    $statement=$pdo->prepare('INSERT INTO request_types (name,checklist_ready) VALUES (:name,1)');$statement->execute(['name'=>$name]);$type=(int)$pdo->lastInsertId();$types[]=$type;
    $templateIds=[];
    foreach([['pre_departure','Endorsement letter',1],['pre_departure','Travel certification',1],['pre_departure','Optional itinerary',0],['post_travel','Travel report',1],['post_travel','Optional photos',0]] as [$stage,$requirementName,$required]){
        $statement=$pdo->prepare('INSERT INTO requirement_templates (request_type_id,stage,requirement_name,is_required) VALUES (:type,:stage,:name,:required)');
        $statement->execute(['type'=>$type,'stage'=>$stage,'name'=>$requirementName,'required'=>$required]);$templateIds[$requirementName]=(int)$pdo->lastInsertId();
    }
    $page=request($client,'client/requests/create.php?type='.$type);
    check(!str_contains($page['body'],'Endorsement letter')&&!str_contains($page['body'],'Travel report'),'Stage requirements do not leak into submission checklist');
    $fields=['request_type_id'=>$type,'title'=>'Monitor activity '.$suffix,'purpose'=>'Academic activity','destination'=>'Campus','country'=>'Philippines','start_date'=>$tomorrow,'end_date'=>$tomorrow,'csrf_token'=>token($page)];
    $response=request($client,'actions/requests/save.php',$fields);preg_match('/view\.php\?id=(\d+)/',$response['headers'],$match);$id=(int)$match[1];
    check(count(request_requirements($id))===0,'Draft snapshots only submission requirements');
    action($admin,'admin',$id,'monitoring.php',['stage'=>'pre_departure','due_date'=>$yesterday,'remarks'=>'Too early']);
    check(row($id)['pre_departure_started_at']===null,'Monitoring cannot initialize before approval');
    state($client,'client',$id,'submitted');state($admin,'admin',$id,'under_review');state($admin,'admin',$id,'approved');
    check(row($id)['status']==='approved','Request approval remains separate from later stage documents');
    check(str_contains(request($client,'client/monitoring.php?attention=setup')['body'],$fields['title']),'Owner sees approved request awaiting setup');
    check(!str_contains(request($other,'client/monitoring.php')['body'],$fields['title']),'Monitoring list enforces client ownership');
    check(request($other,'client/requests/view.php?id='.$id)['status']===404,'Other client cannot open monitoring details');
    check(request($client,'admin/monitoring.php')['status']===403,'Client cannot access admin monitoring list');
    check(request($client,'actions/requests/monitoring.php',['id'=>$id,'csrf_token'=>token(details($client,'client',$id))])['status']===403,'Client cannot set a stage or deadline');
    check(request($admin,'actions/requests/monitoring.php',['id'=>$id])['status']===403,'Monitoring action requires CSRF');
    check(request($admin,'actions/requests/monitoring.php')['status']===405,'Monitoring action rejects GET');
    state($admin,'admin',$id,'in_progress');check(row($id)['status']==='approved','Departure blocked before checklist initialization');
    action($admin,'admin',$id,'monitoring.php',['stage'=>'pre_departure','due_date'=>$yesterday,'remarks'=>'Initialize']);
    check(row($id)['pre_departure_started_at']===null,'Unreviewed template cannot initialize stage');
    $configuration=['operation'=>'type','type_id'=>$type,'name'=>$name,'description'=>'Test fixture','status'=>'active','checklist_ready'=>'1','pre_departure_ready'=>'1','post_travel_ready'=>'1','csrf_token'=>token(request($admin,'admin/request-types.php?id='.$type))];
    request($admin,'actions/requests/configure.php',$configuration);
    $statement=$pdo->prepare('SELECT * FROM request_types WHERE id=:id');$statement->execute(['id'=>$type]);$configured=$statement->fetch();
    check((int)$configured['pre_departure_ready']===1&&(int)$configured['post_travel_ready']===1,'Admin independently confirms stage readiness');
    action($admin,'admin',$id,'monitoring.php',['stage'=>'arbitrary','due_date'=>$yesterday,'remarks'=>'Invalid']);
    check(row($id)['pre_departure_started_at']===null,'Unknown stage rejected');
    action($admin,'admin',$id,'monitoring.php',['stage'=>'post_travel','due_date'=>$yesterday,'remarks'=>'Early']);
    check(row($id)['post_travel_started_at']===null,'Post-travel cannot open during pre-departure');
    action($admin,'admin',$id,'monitoring.php',['stage'=>'pre_departure','due_date'=>'2026-02-31','remarks'=>'Invalid']);
    check(row($id)['pre_departure_started_at']===null,'Impossible deadline rejected');
    action($admin,'admin',$id,'monitoring.php',['stage'=>'pre_departure','due_date'=>'','remarks'=>'No deadline']);
    check(row($id)['pre_departure_started_at']===null&&count(request_requirements($id))===0,'Required deadline failure rolls back snapshot');
    action($admin,'admin',$id,'monitoring.php',['stage'=>'pre_departure','due_date'=>$yesterday,'remarks'=>'Upload official pre-departure requirements.']);
    $pre=request_requirements($id,'pre_departure');
    check(count($pre)===3&&row($id)['pre_departure_due_date']===$yesterday,'Pre-departure snapshot and deadline saved');
    check(count(request_requirements($id,'post_travel'))===0,'Post-travel requirements remain separate until stage opens');
    $first=(int)$pre[0]['id'];$second=(int)$pre[1]['id'];
    $snapshotName=$pre[0]['requirement_name'];
    request($admin,'actions/requests/configure.php',['operation'=>'template','type_id'=>$type,'template_id'=>$templateIds['Endorsement letter'],'stage'=>'pre_departure','name'=>'Future endorsement','description'=>'Future only','status'=>'active','is_required'=>'1','sort_order'=>'0','csrf_token'=>token(request($admin,'admin/request-types.php?id='.$type))]);
    check(request_requirements($id,'pre_departure')[0]['requirement_name']===$snapshotName,'Active monitoring snapshot survives template edits');
    $statement=$pdo->prepare('SELECT * FROM request_types WHERE id=:id');$statement->execute(['id'=>$type]);$configured=$statement->fetch();
    check((int)$configured['pre_departure_ready']===0&&(int)$configured['post_travel_ready']===1&&(int)$configured['checklist_ready']===1,'Template edit resets only affected readiness');
    check(str_contains(request($admin,'admin/monitoring.php?attention=overdue')['body'],$fields['title']),'Overdue monitoring filter finds outstanding required documents');
    check(str_contains(request($client,'client/monitoring.php?attention=missing')['body'],$fields['title']),'Missing upload filter works');
    $before=row($id); $eventsBefore=count(request_history($id));
    action($admin,'admin',$id,'monitoring.php',['stage'=>'pre_departure','due_date'=>$yesterday,'remarks'=>'Unchanged']);
    check(count(request_requirements($id))===3&&count(request_history($id))===$eventsBefore,'Repeated initialization cannot duplicate snapshots or history');
    action($admin,'admin',$id,'monitoring.php',['stage'=>'pre_departure','due_date'=>$tomorrow,'remarks'=>'Extended by ELIA.']);
    check(row($id)['pre_departure_due_date']===$tomorrow&&count(request_requirements($id))===3,'Deadline can change without resnapshotting');
    check(!str_contains(request($client,'client/monitoring.php?attention=overdue')['body'],$fields['title']),'Updated deadline clears overdue filter');
    action($admin,'admin',$id,'monitoring.php',['stage'=>'pre_departure','due_date'=>$yesterday,'remarks'=>'Stale','revision'=>(int)$before['revision']]);
    check(row($id)['pre_departure_due_date']===$tomorrow,'Stale deadline update rejected');
    action($admin,'admin',$id,'monitoring.php',['stage'=>'pre_departure','due_date'=>$yesterday,'remarks'=>'']);
    check(row($id)['pre_departure_due_date']===$tomorrow,'Deadline changes require explanation');
    $otherToken=token(request($other,'client/requests/create.php?type='.$type));
    check(request($other,'actions/requests/upload.php',['id'=>$id,'requirement_id'=>$first,'revision'=>row($id)['revision'],'csrf_token'=>$otherToken,'document'=>new CURLFile($fixture,'application/pdf','letter.pdf')])['status']===404,'Other client cannot upload stage document');
    state($admin,'admin',$id,'in_progress');check(row($id)['status']==='approved','Missing required pre-departure documents block departure');
    upload($client,$id,$first,$fixture,'letter.pdf');$pre=request_requirements($id,'pre_departure');$firstDoc=(int)$pre[0]['document_id'];
    check($firstDoc>0&&$pre[0]['document_status']==='pending','Client uploads pre-departure document on approved request');
    check(str_contains(request($admin,'admin/monitoring.php?attention=pending')['body'],$fields['title']),'Verification filter finds pending stage document');
    check(request($other,"actions/requests/download.php?request_id=$id&id=$firstDoc")['status']===404,'Other client cannot download stage document');
    $adminUnread=unread_notifications($accounts['admin']);check($adminUnread>=2,'Stage uploads notify Admin');
    action($admin,'admin',$id,'review-document.php',['document_id'=>$firstDoc,'status'=>'for_revision','remarks'=>'Please sign this letter.']);
    check(row($id)['status']==='approved','Stage correction does not reset request status');
    check(str_contains(details($client,'client',$id)['body'],'Please sign this letter.'),'Client sees stage correction remarks');
    check(str_contains(request($admin,'admin/monitoring.php?attention=corrections')['body'],$fields['title']),'Correction filter works');
    upload($client,$id,$first,$fixture,'signed.pdf');$pre=request_requirements($id,'pre_departure');$latestDoc=(int)$pre[0]['document_id'];
    check((int)$pre[0]['version']===2&&count(request_versions($id))===2,'Stage re-upload preserves original document version');
    action($admin,'admin',$id,'review-document.php',['document_id'=>$firstDoc,'status'=>'verified','remarks'=>'Old version']);
    check(request_requirements($id,'pre_departure')[0]['document_status']==='pending','Historical stage version cannot be re-reviewed');
    action($admin,'admin',$id,'review-document.php',['document_id'=>$latestDoc,'status'=>'verified','remarks'=>'Signed.']);
    check(request_requirements($id,'pre_departure')[0]['document_status']==='verified','Admin verifies latest stage document');
    upload($client,$id,$first,$fixture,'overwrite.pdf');check((int)request_requirements($id,'pre_departure')[0]['version']===2,'Verified stage document cannot be replaced');
    state($admin,'admin',$id,'in_progress');check(row($id)['status']==='approved','One verified document does not bypass other required documents');
    upload($client,$id,$second,$fixture,'certificate.pdf');$pre=request_requirements($id,'pre_departure');
    state($admin,'admin',$id,'in_progress');check(row($id)['status']==='approved','Pending required document blocks departure');
    action($admin,'admin',$id,'review-document.php',['document_id'=>$pre[1]['document_id'],'status'=>'verified','remarks'=>'Verified.']);
    state($admin,'admin',$id,'in_progress');check(row($id)['status']==='in_progress','Verified pre-departure allows departure with optional upload missing');
    check(str_contains(request($admin,'admin/monitoring.php?status=in_progress')['body'],$fields['title']),'Ongoing travel list shows request');
    state($admin,'admin',$id,'post_travel');check(row($id)['status']==='post_travel','Request enters post-travel monitoring');
    state($admin,'admin',$id,'completed');check(row($id)['status']==='post_travel','Completion blocked before post-travel setup');
    action($admin,'admin',$id,'monitoring.php',['stage'=>'post_travel','due_date'=>$yesterday,'remarks'=>'Submit the post-travel report.']);
    $post=request_requirements($id,'post_travel');check(count($post)===2,'Post-travel checklist linked to original request');
    state($admin,'admin',$id,'completed');check(row($id)['status']==='post_travel','Missing post-travel document blocks completion');
    upload($client,$id,(int)$post[0]['id'],$fixture,'report.pdf');$post=request_requirements($id,'post_travel');$reportDoc=(int)$post[0]['document_id'];
    state($admin,'admin',$id,'completed');check(row($id)['status']==='post_travel','Unverified post-travel document blocks completion');
    action($admin,'admin',$id,'review-document.php',['document_id'=>$reportDoc,'status'=>'rejected','remarks'=>'Report lacks required information.']);
    check(str_contains(details($client,'client',$id)['body'],'Report lacks required information.'),'Rejected post-travel report has actionable remarks');
    upload($client,$id,(int)$post[0]['id'],$fixture,'complete-report.pdf');$post=request_requirements($id,'post_travel');
    action($admin,'admin',$id,'review-document.php',['document_id'=>$post[0]['document_id'],'status'=>'verified','remarks'=>'Report verified.']);
    $progress=monitoring_progress(request_requirements($id),'post_travel',$yesterday);
    check($progress['outstanding']===0&&!$progress['overdue'],'Verified requirements clear overdue status');
    state($admin,'admin',$id,'completed','Monitoring complete.');check(row($id)['status']==='completed','Completion requires both verified stage checklists');
    $beforeCount=count(request_versions($id));upload($client,$id,(int)$post[1]['id'],$fixture,'late-optional.pdf');
    check(count(request_versions($id))===$beforeCount,'Completed request cannot accept further uploads');
    action($admin,'admin',$id,'monitoring.php',['stage'=>'post_travel','due_date'=>$tomorrow,'remarks'=>'Late edit']);
    check(row($id)['post_travel_due_date']===$yesterday,'Completed monitoring deadlines cannot change');
    $history=request_history($id);check(count(array_filter($history,fn($event)=>$event['old_status']===$event['new_status']))===3,'Stage setup and deadline edits retain actor/remarks in history');
    check(str_contains(request($client,'notifications.php')['body'],'Post-travel'),'Stage notifications visible to owner');
    check(str_contains(request($admin,'admin/monitoring.php?status=completed')['body'],$fields['title']),'Completed requests remain inspectable');
    check(!str_contains(request($client,'client/monitoring.php?attention=overdue')['body'],$fields['title']),'Completed request excluded from overdue filter');
    $statement=$pdo->prepare('INSERT INTO requests (user_id,request_type_id,title,purpose,status,submitted_at) VALUES (:user,:type,:title,:purpose,:status,NOW())');
    for($index=0;$index<21;$index++){$statement->execute(['user'=>$accounts['a'],'type'=>$type,'title'=>'Monitoring pagination '.$suffix,'purpose'=>'Fixture','status'=>'approved']);}
    $page=request($admin,'admin/monitoring.php?attention=setup&search='.urlencode('Monitoring pagination '.$suffix).'&page=2');
    check(str_contains($page['body'],'Page 2 of 2')&&str_contains($page['body'],'21 requests'),'Monitoring queue paginates independently of stage document count');
    $guest=browser();check(request($guest,'client/monitoring.php')['status']===303&&request($guest,'admin/monitoring.php')['status']===303,'Monitoring pages require authentication');
    echo "Completed $checks monitoring checks.\n";
} finally {
    foreach($accounts as $account){
        $statement=$pdo->prepare('SELECT id FROM requests WHERE user_id=:user');$statement->execute(['user'=>$account]);
        foreach($statement->fetchAll(PDO::FETCH_COLUMN) as $requestId){
            foreach(request_versions((int)$requestId) as $version){$path=document_storage_path($version['stored_filename']);if(is_file($path)){unlink($path);}}
            foreach(['notifications','request_status_history','request_documents','request_requirements'] as $table){$delete=$pdo->prepare('DELETE FROM '.$table.' WHERE request_id=:id');$delete->execute(['id'=>$requestId]);}
            $delete=$pdo->prepare('DELETE FROM requests WHERE id=:id');$delete->execute(['id'=>$requestId]);
        }
    }
    foreach($types as $type){$statement=$pdo->prepare('DELETE FROM requirement_templates WHERE request_type_id=:id');$statement->execute(['id'=>$type]);$statement=$pdo->prepare('DELETE FROM request_types WHERE id=:id');$statement->execute(['id'=>$type]);}
    foreach($accounts as $account){$statement=$pdo->prepare('DELETE FROM users WHERE id=:id');$statement->execute(['id'=>$account]);}
    if(is_file($fixture)){unlink($fixture);}
}
