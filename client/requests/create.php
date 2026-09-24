<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/bootstrap.php';
$user = requireClient();
require __DIR__ . '/../../includes/requests/workflow.php';
$types = request_types();
$typeId = isset($_GET['type']) ? request_id($_GET['type']) : 0;
$selectedType = null;
foreach ($types as $type) { if ((int) $type['id'] === $typeId) { $selectedType = $type; } }
$fields = [];
if (isset($_SESSION['request_old']) && $_SESSION['request_old']['id'] === null) {
    $fields = array_filter($_SESSION['request_old']['fields'], 'is_string'); unset($_SESSION['request_old']);
}
$pageTitle = 'Create request';
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h3">Create request</h1>
<form method="get" class="card card-body my-4">
    <label for="type" class="form-label">Request type</label>
    <div class="d-flex gap-2 flex-wrap"><select class="form-select w-auto" name="type" id="type" required><option value="">Select a request type</option>
        <?php foreach ($types as $type): ?><option value="<?= (int) $type['id'] ?>" <?= $typeId === (int) $type['id'] ? 'selected' : '' ?>><?= escape($type['name']) ?></option><?php endforeach; ?>
    </select><button class="btn btn-outline-primary" type="submit">Show requirements</button></div>
</form>
<?php if ($selectedType): ?>
    <h2 class="h5"><?= escape($selectedType['name']) ?></h2><p><?= escape($selectedType['description']) ?></p>
    <?php if (!$selectedType['checklist_ready']): ?>
        <div class="alert alert-warning">ELIA is configuring this checklist. Please contact the office before creating this request type.</div>
    <?php else: ?>
        <div class="card card-body mb-4"><h2 class="h5">Requirements for this request type</h2><ul class="mb-0">
        <?php $templates = request_templates($typeId); foreach ($templates as $template): ?>
            <li><?= escape($template['requirement_name']) ?> — <?= $template['is_required'] ? 'Required' : 'Optional' ?><p class="small text-secondary"><?= escape($template['description']) ?></p></li>
        <?php endforeach; ?>
        <?php if (!$templates): ?><li>No documents are required for this configured request type.</li><?php endif; ?>
        </ul></div>
        <?php require __DIR__ . '/../../includes/requests/form.php'; ?>
    <?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
