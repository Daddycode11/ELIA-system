<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/partnerships.php';
$user = requireAdmin(); require_post(); verify_csrf();
try {
    $entity = post_string('entity');
    $fields = partnership_fields($entity);
    $id = partnership_id(post_string('id'), true);
    $partnerId = $entity === 'agreement' ? partnership_id(post_string('partner_id')) : $id;
    $revision = partnership_id(post_string('revision'), true);
} catch (DomainException $exception) { http_response_code(400); exit('Invalid record parameters.'); }
$input = [];
foreach ($fields as $key => $definition) { $input[$key] = post_string($key); }
try {
    $savedId = save_partnership($entity, $id, $partnerId, $revision, $input, (int) $user['id']);
    unset($_SESSION['partnership_form']);
    flash('success', ucfirst($entity) . ' saved.');
    redirect('admin/partnerships/form.php?' . http_build_query(['entity' => $entity, 'id' => $savedId]));
} catch (DomainException $exception) {
    $_SESSION['partnership_form'] = ['entity' => $entity, 'id' => $id, 'partner_id' => $partnerId, 'values' => $input + ['revision' => $revision]];
    flash('danger', $exception->getMessage());
    redirect('admin/partnerships/form.php?' . http_build_query(['entity' => $entity, 'id' => $id, 'partner_id' => $partnerId]));
}
