<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/requests/workflow.php';
require __DIR__ . '/http.php';
$baseUrl = rtrim($argv[1] ?? 'http://127.0.0.1:8080/elia-system', '/');
if (!in_array(parse_url($baseUrl, PHP_URL_HOST), ['127.0.0.1','localhost'], true)) { exit("Local development servers only.\n"); }
$pdo = database(); $checks = 0; $accounts = []; $types = []; $ids = [];
$suffix = bin2hex(random_bytes(8)); $password = bin2hex(random_bytes(16));
$runtime = __DIR__ . '/.runtime'; if (!is_dir($runtime)) { mkdir($runtime, 0700, true); }
$fixture = $runtime . '/request-' . $suffix . '.pdf';
file_put_contents($fixture, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n");
$badFile = $runtime . '/request-' . $suffix . '.txt'; file_put_contents($badFile, '<?php echo "blocked"; ?>');
$largeFile = $runtime . '/large-' . $suffix . '.pdf'; file_put_contents($largeFile, "%PDF-1.4\n" . str_repeat('x', 10 * 1024 * 1024));
$docx = $runtime . '/request-' . $suffix . '.docx';
$zip = new ZipArchive(); $zip->open($docx, ZipArchive::CREATE);
$zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
$zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p/></w:body></w:document>'); $zip->close();

function row(int $id): array
{
    $statement = database()->prepare('SELECT * FROM requests WHERE id=:id'); $statement->execute(['id'=>$id]); return $statement->fetch();
}
function details(CurlHandle $browser, string $role, int $id): array
{
    $response = request($browser, "$role/requests/view.php?id=$id");
    if ($response['status'] !== 200) { throw new RuntimeException('Details page unavailable: ' . $response['status']); }
    return $response;
}
function action(CurlHandle $browser, string $role, int $id, string $path, array $fields): array
{
    $page = details($browser, $role, $id);
    return request($browser, 'actions/requests/' . $path, $fields + ['id'=>$id, 'revision'=>row($id)['revision'], 'csrf_token'=>token($page)]);
}
function state(CurlHandle $browser, string $role, int $id, string $next, string $remarks = ''): array
{
    return action($browser, $role, $id, 'status.php', ['status'=>$next,'remarks'=>$remarks]);
}
function upload(CurlHandle $browser, int $id, int $requirement, string $path, string $name = 'document.pdf'): array
{
    return action($browser, 'client', $id, 'upload.php', ['requirement_id'=>$requirement,'document'=>new CURLFile($path, 'application/octet-stream', $name)]);
}

try {
    foreach (['admin','a','b'] as $key) {
        $statement = $pdo->prepare('INSERT INTO users (full_name,email,password_hash,role) VALUES (:name,:email,:hash,:role)');
        $statement->execute(['name'=>"Request test $key",'email'=>"$key.$suffix@example.test",'hash'=>password_hash($password,PASSWORD_DEFAULT),'role'=>$key === 'admin' ? 'admin' : 'client']);
        $accounts[$key] = (int) $pdo->lastInsertId();
        $browsers[$key] = browser();
        $csrf = token(request($browsers[$key], 'login.php'));
        check(request($browsers[$key], 'actions/login.php', ['csrf_token'=>$csrf,'email'=>"$key.$suffix@example.test",'password'=>$password])['status'] === 303, "$key signs in");
    }
    $client = $browsers['a']; $other = $browsers['b']; $admin = $browsers['admin'];
    foreach (['Conference fixture','Post-travel fixture'] as $name) {
        $statement = $pdo->prepare('INSERT INTO request_types (name,checklist_ready) VALUES (:name,1)'); $statement->execute(['name'=>"$name $suffix"]); $types[]=(int)$pdo->lastInsertId();
    }
    foreach ([[$types[0],'Required letter',1],[$types[0],'Optional itinerary',0],[$types[1],'Travel report',1]] as [$type,$name,$required]) {
        $statement=$pdo->prepare('INSERT INTO requirement_templates (request_type_id,requirement_name,is_required) VALUES (:type,:name,:required)'); $statement->execute(compact('type','name','required'));
    }
    $page=request($client,'client/requests/create.php?type='.$types[0]);
    check(str_contains($page['body'],'Required letter') && !str_contains($page['body'],'Travel report'), 'Checklist loads only selected type requirements');
    $fields=['request_type_id'=>$types[0],'title'=>'<script>Client activity</script>','purpose'=>'Conference participation','destination'=>'Test campus','country'=>'Philippines','start_date'=>'2026-10-01','end_date'=>'2026-10-03','csrf_token'=>token($page),'user_id'=>$accounts['b'],'status'=>'approved'];
    $response=request($client,'actions/requests/save.php',$fields);
    check($response['status']===303 && preg_match('/view\.php\?id=(\d+)/',$response['headers'],$match), 'Client creates a draft');
    $id=(int)$match[1]; $ids[]=$id;
    check(row($id)['status']==='draft' && (int)row($id)['user_id']===$accounts['a'], 'Forged owner and status ignored');
    $snapshot=request_requirements($id); $required=(int)$snapshot[0]['id']; $optional=(int)$snapshot[1]['id'];
    check(count($snapshot)===2,'Checklist snapshot contains selected requirements');
    check(str_contains(details($client,'client',$id)['body'],'&lt;script&gt;Client activity&lt;/script&gt;'),'Request text is escaped');
    check(request($other,'client/requests/view.php?id='.$id)['status']===404,'Other client cannot view request');
    check(!str_contains(request($other,'client/requests/index.php')['body'],'Client activity'),'Other client cannot list request');
    $otherToken=token(request($other,'client/requests/create.php?type='.$types[0]));
    check(request($other,'actions/requests/save.php',$fields+['id'=>$id,'revision'=>1])['status']===403,'Cross-session CSRF rejected');
    check(request($other,'actions/requests/status.php',['id'=>$id,'revision'=>1,'status'=>'submitted','csrf_token'=>$otherToken])['status']===404,'Other client cannot change request status');
    check(request($other,'actions/requests/save.php',array_replace($fields,['id'=>$id,'revision'=>1,'csrf_token'=>$otherToken]))['status']===404,'Other client cannot edit request');
    check(request($client,'admin/requests/index.php')['status']===403,'Client cannot access admin request list');
    check(request($client,'admin/request-types.php')['status']===403,'Client cannot configure requirements');
    check(request($client,'actions/requests/configure.php',['csrf_token'=>token(details($client,'client',$id)),'operation'=>'new_type','name'=>'Forbidden'])['status']===403,'Client cannot post checklist configuration');
    check(request($client,'actions/requests/review-document.php',['csrf_token'=>token(details($client,'client',$id)),'id'=>$id])['status']===403,'Client cannot verify documents');
    check(request($client,'actions/requests/save.php')['status']===405,'Request mutation rejects GET');
    check(request($client,'actions/requests/status.php',['id'=>$id,'status'=>'submitted'])['status']===403,'Status updates require CSRF');
    state($client,'client',$id,'approved'); check(row($id)['status']==='draft','Client cannot approve own request');
    state($client,'client',$id,'submitted');
    check(row($id)['status']==='draft' && str_contains(details($client,'client',$id)['body'],'Required letter (upload required)'), 'Submission identifies missing required document');
    upload($client,$id,$required,$badFile,'payload.php'); check(!request_requirements($id)[0]['document_id'],'Executable extension rejected');
    upload($client,$id,$required,$badFile,'fake.pdf'); check(!request_requirements($id)[0]['document_id'],'Spoofed PDF content rejected');
    upload($client,$id,$required,$largeFile); check(!request_requirements($id)[0]['document_id'],'File above 10 MB rejected');
    upload($client,$id,$required,$fixture,'letter.pdf'); $snapshot=request_requirements($id); $firstDoc=(int)$snapshot[0]['document_id'];
    check($firstDoc>0 && $snapshot[0]['document_status']==='pending','Valid document upload remains pending');
    $statement=$pdo->prepare('SELECT stored_filename FROM request_documents WHERE id=:id'); $statement->execute(['id'=>$firstDoc]); $stored=$statement->fetchColumn();
    check((bool)preg_match('/^[a-f0-9]{64}\.pdf$/',$stored),'Stored filename is securely generated');
    check(request($client,'uploads/'.$stored)['status']===403,'Direct upload URL is denied');
    check(request($other,"actions/requests/download.php?request_id=$id&id=$firstDoc")['status']===404,'Other client cannot download document');
    check(request($other,'actions/requests/upload.php',['id'=>$id,'revision'=>row($id)['revision'],'requirement_id'=>$required,'csrf_token'=>$otherToken,'document'=>new CURLFile($fixture,'application/pdf','letter.pdf')])['status']===404,'Other client cannot upload into request');
    check(request($client,"actions/requests/download.php?request_id=$id&id=$firstDoc")['body']===file_get_contents($fixture),'Owner can download exact uploaded content');
    $statement=$pdo->prepare('UPDATE requirement_templates SET requirement_name=:name WHERE id=:id'); $statement->execute(['name'=>'Changed future template','id'=>$snapshot[0]['requirement_template_id']]);
    check(request_requirements($id)[0]['requirement_name']==='Required letter','Template edits do not alter existing request checklist');
    $oldRevision=(int)row($id)['revision'];
    action($client,'client',$id,'save.php',array_replace($fields,['id'=>$id,'title'=>'Updated activity','csrf_token'=>token(details($client,'client',$id))]));
    check(row($id)['title']==='Updated activity','Client edits draft');
    action($client,'client',$id,'status.php',['status'=>'submitted','revision'=>$oldRevision]); check(row($id)['status']==='draft','Stale form cannot change request');
    state($client,'client',$id,'submitted');
    check(row($id)['status']==='submitted' && preg_match('/^ELIA-\d{4}-\d{6,}$/',row($id)['reference_no']), 'Submission generates reference; missing optional document does not block');
    $reference=row($id)['reference_no'];
    $countBefore=count(request_versions($id)); upload($client,$id,$required,$fixture);
    check(count(request_versions($id))===$countBefore,'Submitted request documents cannot be overwritten');
    check(str_contains(request($admin,'admin/requests/index.php?status=submitted&search='.urlencode($reference))['body'],$reference),'Admin finds submitted request');
    check(!str_contains(request($admin,'admin/requests/index.php?status=completed&search='.urlencode($reference))['body'],'Updated activity'),'Admin status filter excludes nonmatching request');
    state($admin,'admin',$id,'approved'); check(row($id)['status']==='submitted','Admin cannot skip review');
    state($admin,'admin',$id,'under_review'); check(row($id)['status']==='under_review','Admin starts review');
    state($admin,'admin',$id,'approved'); check(row($id)['status']==='under_review','Approval requires individual document verification');
    action($admin,'admin',$id,'review-document.php',['document_id'=>$firstDoc,'status'=>'for_revision','remarks'=>'Please sign the letter.']);
    state($admin,'admin',$id,'for_revision','Please correct the indicated document.');
    check(row($id)['status']==='for_revision' && str_contains(details($client,'client',$id)['body'],'Please sign the letter.'),'Client sees document and request revision remarks');
    state($client,'client',$id,'resubmitted'); check(row($id)['status']==='for_revision','Uncorrected revision cannot be resubmitted');
    upload($client,$id,$required,$fixture,'signed-letter.pdf');
    $snapshot=request_requirements($id); $secondDoc=(int)$snapshot[0]['document_id'];
    check((int)$snapshot[0]['version']===2 && count(request_versions($id))===2,'Replacement keeps prior version and review remarks');
    state($client,'client',$id,'resubmitted'); state($admin,'admin',$id,'under_review');
    action($admin,'admin',$id,'review-document.php',['document_id'=>$firstDoc,'status'=>'verified','remarks'=>'Stale']);
    check(request_requirements($id)[0]['document_status']==='pending','Historical document cannot be reviewed as latest');
    action($admin,'admin',$id,'review-document.php',['document_id'=>$secondDoc,'status'=>'verified','remarks'=>'Signed copy verified.']);
    check(request_requirements($id)[0]['document_status']==='verified','Admin verifies latest document individually');
    state($admin,'admin',$id,'approved','Approved for the stated activity.'); check(row($id)['status']==='approved' && row($id)['approved_at']!==null,'Admin approves reviewed request');
    state($client,'client',$id,'cancelled','Not allowed now'); check(row($id)['status']==='approved','Cancellation is blocked after approval');
    state($admin,'admin',$id,'in_progress'); state($admin,'admin',$id,'post_travel'); state($admin,'admin',$id,'completed','All monitoring complete.');
    check(row($id)['status']==='completed' && row($id)['completed_at']!==null,'Request progresses through post-travel to completion');
    $history=request_history($id);
    check(array_column($history,'new_status')===['draft','submitted','under_review','for_revision','resubmitted','under_review','approved','in_progress','post_travel','completed'],'Complete ordered status history retained');
    check(row($id)['reference_no']===$reference,'Reference stays stable across revisions');
    $statement=$pdo->prepare('SELECT COUNT(*) FROM notifications WHERE request_id=:id AND user_id=:user'); $statement->execute(['id'=>$id,'user'=>$accounts['a']]);
    check((int)$statement->fetchColumn()===9,'Client notifications created for workflow changes');
    check(unread_notifications($accounts['admin'])>=2,'Admin notified of submissions and resubmissions');
    check(str_contains(request($client,'notifications.php')['body'],$reference),'Client can read notification list');
    $statement=$pdo->prepare('SELECT id FROM notifications WHERE request_id=:id AND user_id=:user LIMIT 1'); $statement->execute(['id'=>$id,'user'=>$accounts['a']]); $notificationId=$statement->fetchColumn();
    request($other,'actions/notifications/read.php',['id'=>$notificationId,'csrf_token'=>$otherToken]);
    $statement=$pdo->prepare('SELECT read_at FROM notifications WHERE id=:id'); $statement->execute(['id'=>$notificationId]); check($statement->fetchColumn()===null,'Other client cannot mark notification read');
    request($client,'actions/notifications/read.php',['id'=>$notificationId,'csrf_token'=>token(request($client,'notifications.php'))]);
    $statement->execute(['id'=>$notificationId]); check($statement->fetchColumn()!==null,'Owner can mark notification read');
    $page=request($client,'client/requests/create.php?type='.$types[1]);
    $response=request($client,'actions/requests/save.php',array_replace($fields,['request_type_id'=>$types[1],'csrf_token'=>token($page)]));
    preg_match('/view\.php\?id=(\d+)/',$response['headers'],$match); $secondId=(int)$match[1]; $ids[]=$secondId;
    $secondRequirements=request_requirements($secondId);
    check(count($secondRequirements)===1 && $secondRequirements[0]['requirement_name']==='Travel report','Second request type has distinct requirements');
    upload($client,$secondId,$required,$fixture); check(!request_requirements($secondId)[0]['document_id'],'Foreign requirement cannot be attached to another request');
    upload($client,$secondId,(int)$secondRequirements[0]['id'],$docx,'report.docx'); check(request_requirements($secondId)[0]['document_id']!==null,'DOCX archive validated and uploaded');
    state($client,'client',$secondId,'submitted'); state($admin,'admin',$secondId,'under_review'); state($admin,'admin',$secondId,'rejected','Not eligible.');
    check(row($secondId)['status']==='rejected','Admin can reject request with explanation');
    check(row($secondId)['reference_no']!==$reference,'Separate submissions have distinct reference numbers');
    try {
        $statement=$pdo->prepare('UPDATE requests SET reference_no=:reference WHERE id=:id'); $statement->execute(['reference'=>$reference,'id'=>$secondId]);
        check(false,'Reference uniqueness enforced by database');
    } catch (PDOException $exception) { check(($exception->errorInfo[1] ?? null)===1062,'Reference uniqueness enforced by database'); }
    $response=request($client,'actions/requests/save.php',array_replace($fields,['request_type_id'=>$types[1],'csrf_token'=>token(request($client,'client/requests/create.php?type='.$types[1]))]));
    preg_match('/view\.php\?id=(\d+)/',$response['headers'],$match); $thirdId=(int)$match[1]; $ids[]=$thirdId;
    state($client,'client',$thirdId,'cancelled','No longer needed.'); check(row($thirdId)['status']==='cancelled','Client can cancel a draft with history');
    $adminPage=request($admin,'admin/request-types.php?id='.$types[1]);
    check($adminPage['status']===200 && str_contains($adminPage['body'],'Travel report'),'Admin can inspect configurable checklist');
    $configure=['csrf_token'=>token($adminPage),'operation'=>'type','type_id'=>$types[1],'name'=>'Post-travel fixture '.$suffix,'description'=>'Fixture only','status'=>'inactive','checklist_ready'=>'1'];
    check(request($admin,'actions/requests/configure.php',$configure)['status']===303,'Admin can configure request type');
    $response=request($client,'actions/requests/save.php',array_replace($fields,['request_type_id'=>$types[1],'csrf_token'=>token(request($client,'client/requests/create.php?type='.$types[0]))]));
    check(!str_contains($response['headers'],'view.php?id='),'Inactive type cannot create new drafts');
    $statement=$pdo->prepare('UPDATE request_types SET status=:status,checklist_ready=0 WHERE id=:id'); $statement->execute(['status'=>'active','id'=>$types[1]]);
    $response=request($client,'actions/requests/save.php',array_replace($fields,['request_type_id'=>$types[1],'csrf_token'=>token(request($client,'client/requests/create.php?type='.$types[0]))]));
    check(!str_contains($response['headers'],'view.php?id='),'Unconfigured checklist cannot create new drafts');
    $statement=$pdo->prepare('INSERT INTO requests (user_id,request_type_id,title,purpose,status,submitted_at) VALUES (:user,:type,:title,:purpose,:status,NOW())');
    for($index=0;$index<22;$index++){ $statement->execute(['user'=>$accounts['a'],'type'=>$types[0],'title'=>'Pagination fixture '.$suffix,'purpose'=>'Pagination fixture','status'=>'submitted']); }
    $pageOne=request($admin,'admin/requests/index.php?search='.urlencode('Pagination fixture '.$suffix));
    $pageTwo=request($admin,'admin/requests/index.php?search='.urlencode('Pagination fixture '.$suffix).'&page=2');
    check(str_contains($pageOne['body'],'22 requests') && str_contains($pageTwo['body'],'Page 2 of 2'),'Admin list paginates matching requests');
    $filtered=request($admin,'admin/requests/index.php?search='.urlencode('Pagination fixture '.$suffix).'&client='.urlencode('b.'.$suffix.'@example.test'));
    check(str_contains($filtered['body'],'No requests match'),'Client filter excludes unrelated clients');
    $filtered=request($admin,'admin/requests/index.php?search='.urlencode('Pagination fixture '.$suffix).'&from=2000-01-01&to=2000-01-02');
    check(str_contains($filtered['body'],'No requests match'),'Submission date filter works');
    echo "Completed $checks request checks.\n";
} finally {
    foreach ($accounts as $account) {
        $statement=$pdo->prepare('SELECT id FROM requests WHERE user_id=:user'); $statement->execute(['user'=>$account]);
        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $requestId) {
            foreach (request_versions((int)$requestId) as $version) { $path=document_storage_path($version['stored_filename']); if(is_file($path)){unlink($path);} }
            foreach (['notifications','request_status_history','request_documents','request_requirements'] as $table) {
                $delete=$pdo->prepare('DELETE FROM '.$table.' WHERE request_id=:id'); $delete->execute(['id'=>$requestId]);
            }
            $delete=$pdo->prepare('DELETE FROM requests WHERE id=:id'); $delete->execute(['id'=>$requestId]);
        }
    }
    foreach($types as $type) { $statement=$pdo->prepare('DELETE FROM requirement_templates WHERE request_type_id=:id'); $statement->execute(['id'=>$type]); $statement=$pdo->prepare('DELETE FROM request_types WHERE id=:id'); $statement->execute(['id'=>$type]); }
    foreach($accounts as $account) { $statement=$pdo->prepare('DELETE FROM users WHERE id=:id'); $statement->execute(['id'=>$account]); }
    foreach([$fixture,$badFile,$largeFile,$docx] as $path) { if(is_file($path)){unlink($path);} }
}
