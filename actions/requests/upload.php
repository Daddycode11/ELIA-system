<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/bootstrap.php';
$user = requireClient();
require_post(); verify_csrf();
require __DIR__ . '/../../includes/requests/workflow.php';
$id = request_id(post_string('id'));
try {
    upload_request_document($user, $id, request_id(post_string('requirement_id')), (int) post_string('revision'), $_FILES['document'] ?? []);
    flash('success', 'Document uploaded. Previous versions have been retained.');
} catch (DomainException $exception) { flash('danger', $exception->getMessage()); }
redirect('client/requests/view.php?id=' . $id);
