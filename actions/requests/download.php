<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/bootstrap.php';
$user = current_user();
if (!$user) { redirect('login.php'); }
require __DIR__ . '/../../includes/requests/workflow.php';
$request = request_record(request_id($_GET['request_id'] ?? null), $user);
$statement = database()->prepare('SELECT * FROM request_documents WHERE id=:id AND request_id=:request');
$statement->execute(['id' => request_id($_GET['id'] ?? null), 'request' => $request['id']]);
$document = $statement->fetch();
if (!$document) {
    http_response_code(404); exit('Document not found.');
}
// Attachments are never executed or rendered as active content by this application.
send_document_attachment($document);
