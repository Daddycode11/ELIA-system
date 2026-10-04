<section id="monitoring" class="mb-4" aria-labelledby="monitoring-heading">
<h2 id="monitoring-heading" class="h4">Travel monitoring</h2>
<p class="text-secondary">Use the checklists below on this same request. Required pre-departure documents must be verified before departure; required post-travel documents must be verified before completion. Deadlines use Philippine time.</p>
<div class="row g-3">
<?php foreach (monitoring_stages() as $stage => $stageLabel):
    $started = $request[$stage . '_started_at']; $due = $request[$stage . '_due_date'];
    $progress = monitoring_progress($requirements, $stage, $due);
    $stageOpen = monitoring_stage_open($request, $stage);
?>
<div class="col-xl-6"><div class="card card-body h-100">
    <h3 class="h5"><?= escape($stageLabel) ?></h3>
    <?php if (!$started): ?>
        <p class="text-secondary"><?= $request['status'] === 'completed' ? 'No stage checklist was recorded before completion.' : ($stageOpen ? 'Awaiting ELIA checklist setup.' : 'Opens when the request moves to Post Travel.') ?></p>
    <?php else: ?>
        <p class="mb-2">Deadline: <strong><?= escape($due ?? 'No deadline — no required documents') ?></strong>
        <?php if ($progress['overdue'] && $request['status'] !== 'completed'): ?><span class="badge text-bg-danger">Overdue</span><?php endif; ?></p>
        <p class="mb-2"><strong><?= $progress['verified'] ?> / <?= $progress['required'] ?></strong> required documents verified.</p>
        <ul class="small mb-3"><li><?= $progress['missing'] ?> missing uploads</li><li><?= $progress['pending'] ?> awaiting verification</li><li><?= $progress['corrections'] ?> requiring correction</li></ul>
        <?php if ($progress['outstanding'] === 0): ?><p class="text-success">Required checklist complete.</p><?php else: ?><a href="#checklist"><?= $isAdmin ? 'Review outstanding documents' : 'View required actions and upload documents' ?></a><?php endif; ?>
    <?php endif; ?>
    <?php if ($isAdmin && $stageOpen): ?>
    <form method="post" action="<?= escape(url('actions/requests/monitoring.php')) ?>" class="mt-3">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $request['id'] ?>"><input type="hidden" name="revision" value="<?= (int) $request['revision'] ?>"><input type="hidden" name="stage" value="<?= escape($stage) ?>">
        <label class="form-label" for="due-<?= escape($stage) ?>">Document deadline</label><input id="due-<?= escape($stage) ?>" name="due_date" type="date" class="form-control mb-3" value="<?= escape($due) ?>">
        <label class="form-label" for="instructions-<?= escape($stage) ?>"><?= $started ? 'Reason for deadline change' : 'Client instructions' ?></label><textarea id="instructions-<?= escape($stage) ?>" name="remarks" class="form-control mb-3" rows="2" maxlength="5000" required></textarea>
        <button class="btn btn-outline-primary" type="submit"><?= $started ? 'Update deadline' : 'Initialize checklist' ?></button>
        <?php if (!$started): ?><p class="small text-secondary mt-2 mb-0">Uses this request type’s reviewed <?= escape(strtolower($stageLabel)) ?> template. A deadline is required when required documents exist.</p><?php endif; ?>
    </form>
    <?php endif; ?>
</div></div>
<?php endforeach; ?>
</div></section>
