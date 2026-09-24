<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/bootstrap.php';
$user = requireClient();
require_post(); verify_csrf();
require __DIR__ . '/../../includes/requests/workflow.php';
$id = post_string('id') === '' ? null : request_id(post_string('id'));
$typeId = request_id(post_string('request_type_id'));
if ($id !== null) { request_record($id, $user); }
$returnPath = $id ? 'client/requests/view.php?id=' . $id : 'client/requests/create.php?type=' . $typeId;
try {
    $id = save_request($user, $id, $typeId, request_fields($_POST), (int) post_string('revision'));
    unset($_SESSION['request_old']);
    flash('success', 'Request saved. Upload the checklist documents before submitting.');
    redirect('client/requests/view.php?id=' . $id);
} catch (DomainException $exception) {
    $_SESSION['request_old'] = ['id' => $id, 'fields' => array_intersect_key($_POST, array_flip(['title','purpose','destination','country','start_date','end_date']))];
    flash('danger', $exception->getMessage());
    redirect($returnPath);
}
