<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/partnerships.php';
$user = requireAdmin();
$pageTitle = 'Partnerships';
try {
    $entity = is_string($_GET['entity'] ?? null) ? $_GET['entity'] : 'partner';
    $fields = partnership_fields($entity);
    $id = partnership_id($_GET['id'] ?? 0, true);
    $record = $id ? partnership_record($entity, $id) : null;
    if ($id && !$record) { throw new DomainException('Record not found.'); }
    $partnerId = $entity === 'agreement' ? ($record ? (int) $record['partner_id'] : partnership_id($_GET['partner_id'] ?? null)) : $id;
    $partner = $entity === 'agreement' ? partnership_record('partner', $partnerId) : $record;
    if ($entity === 'agreement' && !$partner) { throw new DomainException('Partner not found.'); }
} catch (DomainException $exception) { http_response_code(404); exit('Record not found.'); }
$values = $record ?? ['is_archived' => '0', 'status' => 'draft', 'agreement_type' => 'MOU', 'revision' => 0];
$old = $_SESSION['partnership_form'] ?? null;
unset($_SESSION['partnership_form']);
if ($old && $old['entity'] === $entity && $old['id'] === $id && $old['partner_id'] === $partnerId) { $values = array_replace($values, $old['values']); }
$formPath = 'admin/partnerships/form.php?' . http_build_query(['entity' => $entity, 'id' => $id, 'partner_id' => $partnerId]);
require __DIR__ . '/../../includes/header.php';
?>
<a class="d-inline-block mb-3" href="<?= escape(url('admin/partnerships/index.php?entity=' . $entity)) ?>">Back to <?= $entity === 'partner' ? 'partners' : 'agreements' ?></a>
<h1 class="h3 mb-3"><?= $id ? 'Edit' : 'Add' ?> <?= $entity === 'partner' ? 'partner institution' : 'MOU / MOA' ?></h1>
<?php if ($entity === 'agreement'): ?><p>Partner: <a href="<?= escape(url('admin/partnerships/form.php?entity=partner&id=' . $partnerId)) ?>"><?= escape($partner['name']) ?></a></p><?php if ($record): ?><p>Current status: <strong><?= escape(ucfirst(agreement_state($record, $partner))) ?></strong>. Effective dates use <?= escape(app_config()['timezone']) ?>; the end date is inclusive.</p><?php endif; ?><?php endif; ?>
<?php if ($record): ?><p class="text-secondary small">Created <?= escape($record['created_at']) ?> · Last updated <?= escape($record['updated_at']) ?></p><?php endif; ?>
<form method="post" action="<?= escape(url('actions/partnerships/save.php')) ?>" class="card card-body mb-4">
<?= csrf_field() ?><input type="hidden" name="entity" value="<?= $entity ?>"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="partner_id" value="<?= $partnerId ?>"><input type="hidden" name="revision" value="<?= (int) $values['revision'] ?>">
<div class="row g-3"><?php foreach ($fields as $key => [$label, $type, $limit, $required]): $value = (string) ($values[$key] ?? ''); ?>
<div class="<?= $type === 'textarea' ? 'col-12' : 'col-md-6' ?>"><label class="form-label" for="<?= $key ?>"><?= escape($label) ?><?= $required ? ' *' : '' ?></label>
<?php if ($type === 'select'): ?><select class="form-select" id="<?= $key ?>" name="<?= $key ?>" required><?php foreach ($limit as $option => $text): ?><option value="<?= escape((string) $option) ?>" <?= $value === (string) $option ? 'selected' : '' ?>><?= escape($text) ?></option><?php endforeach; ?></select>
<?php elseif ($type === 'textarea'): ?><textarea class="form-control" id="<?= $key ?>" name="<?= $key ?>" maxlength="<?= $limit ?>" rows="4"><?= escape($value) ?></textarea>
<?php else: ?><input class="form-control" id="<?= $key ?>" name="<?= $key ?>" type="<?= $type ?>" maxlength="<?= $limit ?>" value="<?= escape($value) ?>" <?= $required ? 'required' : '' ?>><?php endif; ?></div>
<?php endforeach; ?></div>
<p class="form-text mt-3">Archiving retains records and downloads. Archived partners cannot receive new agreements; archived partners or agreements cannot receive uploads. Restore a record by setting its availability to Current.</p>
<div class="d-flex flex-wrap gap-2"><button class="btn btn-primary" type="submit">Save <?= $entity ?></button><a class="btn btn-outline-secondary" href="<?= escape(url($formPath)) ?>">Reload record</a></div>
</form>
<?php if ($entity === 'partner' && $record): ?>
<section class="card card-body"><h2 class="h5">MOU / MOA records</h2><p>Record each agreement separately. For a renewal with a new reference or effective period, add a new agreement to preserve the earlier record.</p><div class="d-flex flex-wrap gap-2"><a class="btn btn-outline-primary" href="<?= escape(url('admin/partnerships/index.php?entity=agreement&partner_id=' . $id)) ?>">View agreements</a><?php if (!$record['is_archived']): ?><a class="btn btn-primary" href="<?= escape(url('admin/partnerships/form.php?entity=agreement&partner_id=' . $id)) ?>">Add agreement</a><?php endif; ?></div></section>
<?php elseif ($entity === 'agreement' && $record): $documents = partnership_documents($id); ?>
<section class="card card-body mb-4"><h2 class="h5">Agreement &amp; supporting documents</h2><p class="text-secondary">Every upload is retained with its uploader and date. Upload a revised copy with a clear description; previous files remain available.</p>
<?php if (!$record['is_archived'] && !$partner['is_archived']): ?>
<form method="post" enctype="multipart/form-data" action="<?= escape(url('actions/partnerships/upload.php')) ?>">
<?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="revision" value="<?= (int) $record['revision'] ?>">
<div class="mb-3"><label class="form-label" for="description">Document description</label><input class="form-control" id="description" name="description" maxlength="200" required></div>
<div class="mb-3"><label class="form-label" for="document">File (PDF, DOC, DOCX, JPG, JPEG, PNG; up to 10 MB)</label><input class="form-control" id="document" name="document" type="file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required></div><button class="btn btn-primary" type="submit">Upload document</button></form>
<?php else: ?><p class="text-secondary">Restore the partner and agreement before adding documents.</p><?php endif; ?></section>
<div class="table-responsive"><table class="table"><thead><tr><th scope="col">Upload</th><th scope="col">Description</th><th scope="col">File</th><th scope="col">Uploaded by / date</th></tr></thead><tbody>
<?php foreach ($documents as $document): ?><tr><td>#<?= (int) $document['version'] ?></td><td><?= escape($document['description']) ?></td><td><a href="<?= escape(url('actions/partnerships/download.php?agreement_id=' . $id . '&id=' . (int) $document['id'])) ?>"><?= escape($document['original_filename']) ?></a><br><small><?= number_format($document['file_size'] / 1024, 1) ?> KB</small></td><td><?= escape($document['uploader']) ?><br><?= escape($document['created_at']) ?></td></tr><?php endforeach; ?>
<?php if (!$documents): ?><tr><td colspan="4">No documents uploaded yet.</td></tr><?php endif; ?></tbody></table></div>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
