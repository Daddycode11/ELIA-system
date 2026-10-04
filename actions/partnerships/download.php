<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/partnerships.php';
requireAdmin();
try { $id = partnership_id($_GET['id'] ?? null); $agreement = partnership_id($_GET['agreement_id'] ?? null); }
catch (DomainException $exception) { http_response_code(404); exit('Document not found.'); }
$statement = database()->prepare('SELECT * FROM partnership_documents WHERE id = ? AND agreement_id = ?');
$statement->execute([$id, $agreement]);
$document = $statement->fetch();
if (!$document) { http_response_code(404); exit('Document not found.'); }
send_document_attachment($document);
