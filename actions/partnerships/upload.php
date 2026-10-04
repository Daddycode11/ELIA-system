<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/partnerships.php';
$user = requireAdmin(); require_post(); verify_csrf();
try { $id = partnership_id(post_string('id')); $revision = partnership_id(post_string('revision')); }
catch (DomainException $exception) { http_response_code(400); exit('Invalid record parameters.'); }
try {
    upload_partnership_document($id, $revision, post_string('description'), is_array($_FILES['document'] ?? null) ? $_FILES['document'] : [], (int) $user['id']);
    flash('success', 'Document uploaded. All previous uploads are retained.');
} catch (DomainException $exception) { flash('danger', $exception->getMessage()); }
redirect('admin/partnerships/form.php?entity=agreement&id=' . $id);
