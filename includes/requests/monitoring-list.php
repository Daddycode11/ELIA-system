<?php
require_once __DIR__ . '/workflow.php';
$filters = [];
foreach (['status','attention','search'] as $key) { $filters[$key] = isset($_GET[$key]) && is_string($_GET[$key]) ? mb_substr(trim($_GET[$key]), 0, 200) : ''; }
$page = isset($_GET['page']) && is_scalar($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$result = monitoring_list($user, $filters, $page);
$pageTitle = 'Travel monitoring';
require __DIR__ . '/../header.php';
?>
<h1 class="h3">Pre-departure &amp; post-travel monitoring</h1>
<p class="text-secondary">Track approved requests, ongoing travel, and post-travel requirements. Open a request to manage its checklist and deadline. Overdue means required documents remain unverified after the deadline, including documents awaiting ELIA review.</p>
<form method="get" class="card card-body mb-4"><div class="row g-3">
<div class="col-md-4"><label class="form-label" for="monitor-search">Search reference, title<?= $user['role'] === 'admin' ? ', or client' : '' ?></label><input id="monitor-search" name="search" class="form-control" maxlength="200" value="<?= escape($filters['search']) ?>"></div>
<div class="col-md-4"><label class="form-label" for="monitor-status">Stage</label><select id="monitor-status" name="status" class="form-select">
<?php foreach ([''=>'All stages','approved'=>'Pre-departure','in_progress'=>'Ongoing activity / travel','post_travel'=>'Post-travel','completed'=>'Completed'] as $value=>$label): ?><option value="<?= escape($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= escape($label) ?></option><?php endforeach; ?></select></div>
<div class="col-md-4"><label class="form-label" for="attention">Required documents</label><select id="attention" name="attention" class="form-select"><?php foreach ([''=>'All','setup'=>'Awaiting checklist setup','missing'=>'Missing uploads','pending'=>'Awaiting verification','corrections'=>'Needs correction','overdue'=>'Overdue'] as $value=>$label): ?><option value="<?= escape($value) ?>" <?= $filters['attention'] === $value ? 'selected' : '' ?>><?= escape($label) ?></option><?php endforeach; ?></select></div>
</div><div class="mt-3"><button class="btn btn-primary" type="submit">Apply filters</button> <a class="btn btn-outline-secondary" href="<?= escape(url($user['role'] . '/monitoring.php')) ?>">Clear</a></div></form>
<div class="card"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Request<?= $user['role'] === 'admin' ? ' / Client' : '' ?></th><th>Activity dates</th><th>Status</th><th>Pre-departure</th><th>Post-travel</th><th>Action</th></tr></thead><tbody>
<?php foreach ($result['rows'] as $record): ?><tr>
<td><strong><?= escape($record['reference_no'] ?? '#' . $record['id']) ?></strong><div><?= escape($record['title']) ?></div><?php if ($user['role'] === 'admin'): ?><div class="small text-secondary"><?= escape($record['client_name']) ?></div><?php endif; ?></td>
<td><?= escape($record['start_date']) ?><br><?= escape($record['end_date']) ?></td><td><?= request_badge($record['status']) ?></td>
<?php foreach (monitoring_stages() as $stage=>$label): $prefix = $stage === 'pre_departure' ? 'pre' : 'post'; ?><td>
<?php if (!$record[$stage . '_started_at']): ?><span class="text-secondary">Not initialized</span><?php else: ?>
<div><?= (int) $record[$prefix . '_verified'] ?> / <?= (int) $record[$prefix . '_required'] ?> required verified</div>
<div class="small">Due <?= escape($record[$stage . '_due_date'] ?? 'not required') ?></div>
<div class="small text-secondary"><?= (int) $record[$prefix . '_missing'] ?> missing · <?= (int) $record[$prefix . '_pending'] ?> awaiting review · <?= (int) $record[$prefix . '_corrections'] ?> corrections</div>
<?php if ($record['status'] !== 'completed' && $record[$stage . '_due_date'] && $record[$stage . '_due_date'] < monitoring_today() && $record[$prefix . '_required'] > $record[$prefix . '_verified']): ?><span class="badge text-bg-danger">Overdue</span><?php endif; ?>
<?php endif; ?></td><?php endforeach; ?>
<td><a aria-label="Open monitoring for <?= escape($record['reference_no'] ?? 'request') ?>" href="<?= escape(url($user['role'] . '/requests/view.php?id=' . $record['id'] . '#monitoring')) ?>">Open</a></td></tr><?php endforeach; ?>
<?php if (!$result['rows']): ?><tr><td colspan="6" class="p-4 text-secondary text-center">No requests match these monitoring filters.</td></tr><?php endif; ?></tbody></table></div></div>
<nav class="d-flex flex-wrap gap-3 align-items-center mt-3" aria-label="Monitoring pages"><?php if ($result['page']>1): ?><a href="?<?= escape(http_build_query($filters + ['page'=>$result['page']-1])) ?>">Previous</a><?php endif; ?><span>Page <?= $result['page'] ?> of <?= $result['pages'] ?> · <?= $result['total'] ?> requests</span><?php if ($result['page']<$result['pages']): ?><a href="?<?= escape(http_build_query($filters + ['page'=>$result['page']+1])) ?>">Next</a><?php endif; ?></nav>
<?php require __DIR__ . '/../footer.php'; ?>
