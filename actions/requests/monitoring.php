<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/bootstrap.php';
$user = requireAdmin();
require_post(); verify_csrf();
require __DIR__ . '/../../includes/requests/workflow.php';
$id = request_id(post_string('id'));
try {
    configure_request_monitoring($user, $id, post_string('stage'), post_string('due_date'), post_string('remarks'), (int) post_string('revision'));
    flash('success', 'Monitoring checklist and deadline saved. The client has been notified.');
} catch (DomainException $exception) { flash('danger', $exception->getMessage()); }
redirect('admin/requests/view.php?id=' . $id . '#monitoring');
