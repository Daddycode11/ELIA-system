<?php
require_once __DIR__ . '/workflow.php';
$request = request_record(request_id($_GET['id'] ?? null), $user);
$requirements = request_requirements((int) $request['id']);
$history = request_history((int) $request['id']);
$versions = request_versions((int) $request['id']);
$isAdmin = $user['role'] === 'admin';
$editable = !$isAdmin && in_array($request['status'], ['draft','for_revision'], true);
$pageTitle = $request['reference_no'] ?: 'Draft #' . $request['id'];
require __DIR__ . '/../header.php';
?>
<div class="d-flex gap-3 justify-content-between flex-wrap mb-4"><div><h1 class="h3"><?= escape($pageTitle) ?></h1><p class="mb-2"><?= escape($request['type_name']) ?></p><?= request_badge($request['status']) ?></div><a href="<?= escape(url($user['role'] . '/requests/index.php')) ?>">Back to requests</a></div>
<?php if ($request['remarks']): ?><div class="alert alert-info"><strong>Admin request remarks</strong><div class="preserve-lines"><?= escape($request['remarks']) ?></div></div><?php endif; ?>
<?php if ($editable): ?>
    <?php
    $fields = $request; $typeId = (int) $request['request_type_id'];
    if (isset($_SESSION['request_old']) && $_SESSION['request_old']['id'] === (int) $request['id']) {
        $fields = array_replace($fields, array_filter($_SESSION['request_old']['fields'], 'is_string')); unset($_SESSION['request_old']);
    }
    require __DIR__ . '/form.php';
    ?>
<?php else: ?>
<section class="card card-body mb-4"><h2 class="h5">Request information</h2><dl class="row mb-0">
<?php foreach (['client_name'=>'Client','client_email'=>'Email','title'=>'Title','purpose'=>'Purpose','destination'=>'Destination','country'=>'Country','start_date'=>'Start date','end_date'=>'End date','submitted_at'=>'Submitted'] as $key=>$label): ?>
    <dt class="col-md-3"><?= escape($label) ?></dt><dd class="col-md-9 preserve-lines"><?= escape($request[$key] ?? '—') ?></dd>
<?php endforeach; ?></dl></section>
<?php endif; ?>
<section class="card mb-4"><div class="card-body"><h2 class="h5">Requirement checklist</h2><p class="text-secondary small mb-0">PDF, DOC, DOCX, JPG, JPEG, PNG · maximum 10 MB per file. Downloads open as attachments. Documents require individual ELIA verification.</p></div>
<div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Requirement</th><th>Upload / verification</th><th>Admin document remarks</th><th>Action</th></tr></thead><tbody>
<?php foreach ($requirements as $requirement): ?><tr>
<td><strong><?= escape($requirement['requirement_name']) ?></strong><div class="small"><?= $requirement['is_required'] ? 'Required' : 'Optional' ?></div><div class="small text-secondary"><?= escape($requirement['description']) ?></div></td>
<td><?php if ($requirement['document_id']): ?><a href="<?= escape(url('actions/requests/download.php?request_id=' . $request['id'] . '&id=' . $requirement['document_id'])) ?>"><?= escape($requirement['original_filename']) ?></a><div class="small">Version <?= (int) $requirement['version'] ?> · <?= escape($requirement['uploaded_at']) ?></div><?= request_badge($requirement['document_status']) ?><?php else: ?>Not uploaded<?php endif; ?></td>
<td class="preserve-lines"><?= escape($requirement['admin_remarks'] ?? '—') ?></td>
<td>
<?php if ($editable && ($request['status'] === 'draft' || !$requirement['document_id'] || in_array($requirement['document_status'], ['for_revision','rejected'], true))): ?>
<form method="post" enctype="multipart/form-data" action="<?= escape(url('actions/requests/upload.php')) ?>" class="document-action">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $request['id'] ?>"><input type="hidden" name="requirement_id" value="<?= (int) $requirement['id'] ?>"><input type="hidden" name="revision" value="<?= (int) $request['revision'] ?>">
    <label class="form-label small" for="file-<?= (int) $requirement['id'] ?>"><?= $requirement['document_id'] ? 'Replacement document' : 'Select document' ?></label><input class="form-control form-control-sm mb-2" id="file-<?= (int) $requirement['id'] ?>" type="file" name="document" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required><button class="btn btn-sm btn-outline-primary" type="submit"><?= $requirement['document_id'] ? 'Re-upload' : 'Upload' ?></button>
</form>
<?php elseif ($isAdmin && $request['status'] === 'under_review' && $requirement['document_status'] === 'pending'): ?>
<form method="post" action="<?= escape(url('actions/requests/review-document.php')) ?>" class="document-action">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $request['id'] ?>"><input type="hidden" name="document_id" value="<?= (int) $requirement['document_id'] ?>"><input type="hidden" name="revision" value="<?= (int) $request['revision'] ?>">
    <label class="form-label small" for="remarks-<?= (int) $requirement['id'] ?>">Document remarks</label><textarea id="remarks-<?= (int) $requirement['id'] ?>" name="remarks" class="form-control form-control-sm mb-2" maxlength="5000" rows="2"></textarea>
    <label class="visually-hidden" for="decision-<?= (int) $requirement['id'] ?>">Review decision</label><select id="decision-<?= (int) $requirement['id'] ?>" class="form-select form-select-sm mb-2" name="status"><option value="verified">Verify</option><option value="for_revision">Request revision</option><option value="rejected">Reject document</option></select><button class="btn btn-sm btn-primary" type="submit">Save review</button>
</form>
<?php else: ?>—<?php endif; ?>
</td></tr><?php endforeach; ?>
<?php if (!$requirements): ?><tr><td colspan="4" class="p-4">No documents are required for this request.</td></tr><?php endif; ?>
</tbody></table></div></section>
<?php $transitions = request_transitions($user['role'], $request['status']); if ($transitions): ?>
<form class="card card-body mb-4" method="post" action="<?= escape(url('actions/requests/status.php')) ?>">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $request['id'] ?>"><input type="hidden" name="revision" value="<?= (int) $request['revision'] ?>">
    <h2 class="h5">Request actions</h2><p class="small text-secondary">Save information and upload documents before submitting. Revision, rejection, and cancellation require an explanation.</p>
    <label for="request-remarks" class="form-label">Request-level remarks</label><textarea id="request-remarks" name="remarks" class="form-control mb-3" rows="2" maxlength="5000"></textarea>
    <div class="d-flex gap-2 flex-wrap"><?php $labels = ['submitted'=>'Submit request','resubmitted'=>'Resubmit request','cancelled'=>'Cancel request','under_review'=>'Start review','for_revision'=>'Request revision','approved'=>'Approve request','rejected'=>'Reject request','in_progress'=>'Mark in progress','post_travel'=>'Move to post-travel','completed'=>'Complete request']; foreach ($transitions as $next): ?><button class="btn <?= in_array($next, ['rejected','cancelled'], true) ? 'btn-outline-danger' : 'btn-primary' ?>" type="submit" name="status" value="<?= escape($next) ?>"><?= escape($labels[$next]) ?></button><?php endforeach; ?></div>
</form><?php endif; ?>
<section class="card card-body mb-4"><h2 class="h5">Status history</h2><ol class="request-timeline mb-0">
<?php foreach ($history as $event): ?><li class="mb-3"><strong><?= escape(request_label($event['new_status'])) ?></strong><div class="small text-secondary"><?= escape($event['created_at']) ?> · <?= escape($event['actor']) ?></div><div class="preserve-lines"><?= escape($event['remarks']) ?></div></li><?php endforeach; ?>
</ol></section>
<details class="card card-body"><summary>Document versions and review history (<?= count($versions) ?>)</summary><div class="table-responsive mt-3"><table class="table"><thead><tr><th>Requirement / file</th><th>Version / uploaded</th><th>Decision / reviewer</th><th>Document remarks</th></tr></thead><tbody>
<?php foreach ($versions as $version): ?><tr><td><?= escape($version['requirement_name']) ?><br><a href="<?= escape(url('actions/requests/download.php?request_id=' . $request['id'] . '&id=' . $version['id'])) ?>"><?= escape($version['original_filename']) ?></a></td><td><?= (int) $version['version'] ?><br><?= escape($version['uploaded_at']) ?></td><td><?= request_badge($version['status']) ?><div class="small"><?= escape($version['reviewer'] ?? 'Not reviewed') ?><br><?= escape($version['verified_at']) ?></div></td><td class="preserve-lines"><?= escape($version['admin_remarks']) ?></td></tr><?php endforeach; ?>
<?php if (!$versions): ?><tr><td colspan="4">No uploads yet.</td></tr><?php endif; ?></tbody></table></div></details>
<?php require __DIR__ . '/../footer.php'; ?>
