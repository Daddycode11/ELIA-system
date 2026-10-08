<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/announcements.php';
$user = requireAdmin(); require_post(); verify_csrf();
try {
    $id = announcement_id(post_string('id'), true) ?: null;
    $revision = announcement_id(post_string('revision'), true);
} catch (DomainException $exception) { http_response_code(400); exit('Invalid record parameters.'); }
$input = [];
foreach (['title', 'summary', 'body', 'category', 'status'] as $key) { $input[$key] = post_string($key); }
try {
    $savedId = announcement_save($id, $revision, $input, (int) $user['id']);
    flash('success', 'Announcement saved.');
    redirect('admin/announcements/form.php?id=' . $savedId);
} catch (DomainException $exception) {
    $_SESSION['announcement_form'] = ['id' => $id, 'values' => $input];
    flash('danger', $exception->getMessage());
    redirect('admin/announcements/form.php' . ($id !== null ? '?id=' . $id : ''));
}
