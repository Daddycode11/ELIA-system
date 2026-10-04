<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/partnerships.php';
$user = requireAdmin();
$pageTitle = 'Partnerships';
$entity = ($_GET['entity'] ?? '') === 'agreement' ? 'agreement' : 'partner';
$search = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 150) : '';
$status = is_string($_GET['status'] ?? null) ? $_GET['status'] : '';
$type = is_string($_GET['type'] ?? null) ? $_GET['type'] : '';
$partnerId = filter_var($_GET['partner_id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) ?: 0;
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
$partner = $partnerId ? partnership_record('partner', $partnerId) : null;
$result = partnership_list($entity, $search, $status, $type, $partnerId, $page);
$statuses = $entity === 'partner' ? ['current', 'archived'] : ['draft', 'upcoming', 'active', 'expired', 'terminated', 'archived'];
require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between gap-3 mb-4"><div><p class="eyebrow">International linkages</p><h1 class="h3">Partnerships &amp; agreements</h1><p class="text-secondary mb-0">Maintain partner institutions, MOU/MOA records, and supporting documents.</p></div><div><a class="btn btn-primary" href="<?= escape(url('admin/partnerships/form.php?entity=partner')) ?>">Add partner</a></div></div>
<nav aria-label="Partnership records" class="d-flex gap-2 mb-4"><a class="btn <?= $entity === 'partner' ? 'btn-primary' : 'btn-outline-primary' ?>" href="<?= escape(url('admin/partnerships/index.php')) ?>">Partners</a><a class="btn <?= $entity === 'agreement' ? 'btn-primary' : 'btn-outline-primary' ?>" href="<?= escape(url('admin/partnerships/index.php?entity=agreement')) ?>">MOU / MOA</a></nav>
<?php if ($entity === 'agreement' && $partner): ?><p>Agreements for <a href="<?= escape(url('admin/partnerships/form.php?entity=partner&id=' . $partnerId)) ?>"><?= escape($partner['name']) ?></a></p><?php if (!$partner['is_archived']): ?><a class="btn btn-primary mb-3" href="<?= escape(url('admin/partnerships/form.php?entity=agreement&partner_id=' . $partnerId)) ?>">Add agreement</a><?php endif; ?><?php elseif ($entity === 'agreement'): ?><p class="text-secondary">Open a partner record to add an agreement.</p><?php endif; ?>
<form method="get" class="card card-body mb-4"><input type="hidden" name="entity" value="<?= $entity ?>"><?php if ($partnerId): ?><input type="hidden" name="partner_id" value="<?= $partnerId ?>"><?php endif; ?>
<div class="row g-3 align-items-end"><div class="col-md-5"><label class="form-label" for="q"><?= $entity === 'partner' ? 'Institution or country' : 'Reference, title, or institution' ?></label><input class="form-control" type="search" id="q" name="q" maxlength="150" value="<?= escape($search) ?>"></div>
<div class="col-md-3"><label class="form-label" for="status">Status</label><select class="form-select" id="status" name="status"><option value="">All statuses</option><?php foreach ($statuses as $option): ?><option value="<?= $option ?>" <?= $status === $option ? 'selected' : '' ?>><?= ucfirst($option) ?></option><?php endforeach; ?></select></div>
<?php if ($entity === 'agreement'): ?><div class="col-md-2"><label class="form-label" for="type">Type</label><select class="form-select" id="type" name="type"><option value="">All types</option><?php foreach (['MOU', 'MOA'] as $option): ?><option <?= $type === $option ? 'selected' : '' ?>><?= $option ?></option><?php endforeach; ?></select></div><?php endif; ?>
<div class="col-md-2"><button class="btn btn-primary" type="submit">Filter</button></div></div></form>
<p class="text-secondary"><?= $result['total'] ?> record(s)</p>
<div class="card"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr>
<?php if ($entity === 'partner'): ?><th scope="col">Institution</th><th scope="col">Country</th><th scope="col">Status</th><?php else: ?><th scope="col">Agreement</th><th scope="col">Institution</th><th scope="col">Type</th><th scope="col">Effective until</th><th scope="col">Status</th><?php endif; ?><th scope="col">Action</th></tr></thead><tbody>
<?php foreach ($result['rows'] as $record): ?><tr>
<?php if ($entity === 'partner'): ?><td><?= escape($record['name']) ?></td><td><?= escape($record['country']) ?></td><td><?= $record['is_archived'] ? 'Archived' : 'Current' ?></td>
<?php else: ?><td><strong><?= escape($record['reference_no']) ?></strong><br><?= escape($record['title']) ?></td><td><?= escape($record['partner_name']) ?></td><td><?= escape($record['agreement_type']) ?></td><td><?= escape($record['end_date'] ?? 'No end date recorded') ?></td><td><?= escape(ucfirst(agreement_state($record, ['is_archived' => $record['partner_archived']]))) ?></td><?php endif; ?>
<td><a href="<?= escape(url('admin/partnerships/form.php?' . http_build_query(['entity' => $entity, 'id' => $record['id']]))) ?>">View / Edit<span class="visually-hidden"> <?= escape($record['name'] ?? $record['reference_no']) ?></span></a></td></tr><?php endforeach; ?>
<?php if (!$result['rows']): ?><tr><td colspan="<?= $entity === 'partner' ? 4 : 6 ?>" class="p-4 text-center text-secondary">No matching records.</td></tr><?php endif; ?></tbody></table></div></div>
<nav class="d-flex justify-content-between align-items-center mt-3" aria-label="Record pages"><span>Page <?= $result['page'] ?> of <?= $result['pages'] ?></span><div class="d-flex gap-2"><?php foreach ([-1 => 'Previous', 1 => 'Next'] as $step => $label): $next = $result['page'] + $step; if ($next >= 1 && $next <= $result['pages']): ?><a class="btn btn-outline-primary" href="<?= escape(url('admin/partnerships/index.php?' . http_build_query(['entity' => $entity, 'q' => $search, 'status' => $status, 'type' => $type, 'partner_id' => $partnerId, 'page' => $next]))) ?>"><?= $label ?></a><?php endif; endforeach; ?></div></nav>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
