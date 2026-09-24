<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/bootstrap.php';
$user = requireAdmin();
require_post(); verify_csrf();
require __DIR__ . '/../../includes/requests/workflow.php';
$id = request_id(post_string('id'));
try {
    review_request_document($user, $id, request_id(post_string('document_id')), post_string('status'), post_string('remarks'), (int) post_string('revision'));
    flash('success', 'Document review saved. Use the request actions to notify the client and return the request for revision when needed.');
} catch (DomainException $exception) { flash('danger', $exception->getMessage()); }
redirect('admin/requests/view.php?id=' . $id);
