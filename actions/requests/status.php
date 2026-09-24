<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/bootstrap.php';
$user = current_user();
if (!$user) { redirect('login.php'); }
$user = $user['role'] === 'admin' ? requireAdmin() : requireClient();
require_post(); verify_csrf();
require __DIR__ . '/../../includes/requests/workflow.php';
$id = request_id(post_string('id'));
try {
    transition_request($user, $id, post_string('status'), post_string('remarks'), (int) post_string('revision'));
    flash('success', 'Request status updated.');
} catch (DomainException $exception) { flash('danger', $exception->getMessage()); }
redirect($user['role'] . '/requests/view.php?id=' . $id);
