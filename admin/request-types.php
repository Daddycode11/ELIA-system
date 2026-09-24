<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
$user = requireAdmin();
require __DIR__ . '/../includes/requests/workflow.php';
$types = request_types(false);
$typeId = isset($_GET['id']) ? request_id($_GET['id']) : (int) ($types[0]['id'] ?? 0);
$selected = null;
foreach ($types as $type) { if ((int) $type['id'] === $typeId) { $selected = $type; } }
$templates = [];
if ($selected) {
    $statement = database()->prepare('SELECT * FROM requirement_templates WHERE request_type_id=:id ORDER BY sort_order, id');
    $statement->execute(['id' => $typeId]); $templates = $statement->fetchAll();
}
$pageTitle = 'Request types and checklists';
require __DIR__ . '/../includes/header.php';
?>
<h1 class="h3">Request types and checklists</h1>
<p class="text-secondary">Configure the official requirements before marking a checklist ready. Changes apply to new drafts; existing requests retain their original checklist.</p>
<form method="get" class="d-flex gap-2 mb-4"><label class="visually-hidden" for="type-id">Request type</label><select id="type-id" name="id" class="form-select"><?php foreach ($types as $type): ?><option value="<?= (int) $type['id'] ?>" <?= (int) $type['id'] === $typeId ? 'selected' : '' ?>><?= escape($type['name']) ?></option><?php endforeach; ?></select><button class="btn btn-outline-primary" type="submit">Open</button></form>
<?php if ($selected): ?>
<form method="post" action="<?= escape(url('actions/requests/configure.php')) ?>" class="card card-body mb-4">
<?= csrf_field() ?><input type="hidden" name="operation" value="type"><input type="hidden" name="type_id" value="<?= $typeId ?>">
<h2 class="h5">Type settings</h2><label for="type-name" class="form-label">Name</label><input id="type-name" name="name" class="form-control mb-3" maxlength="150" value="<?= escape($selected['name']) ?>" required>
<label for="type-description" class="form-label">Description</label><textarea id="type-description" name="description" class="form-control mb-3" maxlength="5000"><?= escape($selected['description']) ?></textarea>
<label for="type-status" class="form-label">Availability</label><select id="type-status" name="status" class="form-select mb-3"><option value="active" <?= $selected['status'] === 'active' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= $selected['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option></select>
<div class="form-check mb-3"><input id="ready" name="checklist_ready" value="1" type="checkbox" class="form-check-input" <?= $selected['checklist_ready'] ? 'checked' : '' ?>><label class="form-check-label" for="ready">Checklist reviewed and ready for new client requests (including an intentionally empty checklist)</label></div><div><button class="btn btn-primary" type="submit">Save type settings</button></div></form>
<?php $templates[] = ['id'=>0,'requirement_name'=>'','description'=>'','is_required'=>1,'sort_order'=>0,'status'=>'active']; foreach ($templates as $template): ?>
<form method="post" action="<?= escape(url('actions/requests/configure.php')) ?>" class="card card-body mb-3">
<?= csrf_field() ?><input type="hidden" name="operation" value="template"><input type="hidden" name="type_id" value="<?= $typeId ?>"><input type="hidden" name="template_id" value="<?= (int) $template['id'] ?>">
<h2 class="h5"><?= $template['id'] ? 'Edit requirement' : 'Add requirement' ?></h2>
<div class="row g-3"><div class="col-md-6"><label class="form-label" for="name-<?= (int) $template['id'] ?>">Requirement name</label><input class="form-control" id="name-<?= (int) $template['id'] ?>" name="name" maxlength="180" value="<?= escape($template['requirement_name']) ?>" required></div>
<div class="col-md-3"><label class="form-label" for="sort-<?= (int) $template['id'] ?>">Sort order</label><input class="form-control" id="sort-<?= (int) $template['id'] ?>" name="sort_order" type="number" min="0" max="9999" value="<?= (int) $template['sort_order'] ?>" required></div>
<div class="col-md-3"><label class="form-label" for="status-<?= (int) $template['id'] ?>">Status</label><select class="form-select" id="status-<?= (int) $template['id'] ?>" name="status"><option value="active" <?= $template['status'] === 'active' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= $template['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option></select></div>
<div class="col-12"><label class="form-label" for="description-<?= (int) $template['id'] ?>">Instructions</label><textarea class="form-control" id="description-<?= (int) $template['id'] ?>" name="description" maxlength="5000"><?= escape($template['description']) ?></textarea></div>
<div class="col-12"><div class="form-check"><input id="required-<?= (int) $template['id'] ?>" class="form-check-input" type="checkbox" name="is_required" value="1" <?= $template['is_required'] ? 'checked' : '' ?>><label class="form-check-label" for="required-<?= (int) $template['id'] ?>">Required document</label></div></div></div>
<div class="mt-3"><button class="btn btn-outline-primary" type="submit">Save requirement</button></div></form>
<?php endforeach; endif; ?>
<details class="card card-body mt-4"><summary>Add request type</summary><form method="post" action="<?= escape(url('actions/requests/configure.php')) ?>" class="mt-3"><?= csrf_field() ?><input type="hidden" name="operation" value="new_type"><label class="form-label" for="new-type">Name</label><input class="form-control mb-3" id="new-type" name="name" maxlength="150" required><button class="btn btn-primary" type="submit">Add type</button></form></details>
<?php require __DIR__ . '/../includes/footer.php'; ?>
