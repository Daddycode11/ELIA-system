<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/announcements.php';
require_once __DIR__ . '/_common.php';

$user = requireAdmin();

$id = null;
if (isset($_GET['id']) && $_GET['id'] !== '') {
    try {
        $id = announcement_id($_GET['id']);
    } catch (DomainException $exception) {
        http_response_code(400);
        exit('Invalid record.');
    }
}

$record = $id !== null ? announcement_find($id) : null;
if ($id !== null && $record === null) {
    http_response_code(404);
    exit('Announcement not found.');
}

$values = [
    'title' => $record['title'] ?? '',
    'summary' => $record['summary'] ?? '',
    'body' => $record['body'] ?? '',
    'category' => $record['category'] ?? 'announcement',
    'status' => $record['status'] ?? 'draft',
];

// Restore what the admin typed if the last save failed validation.
$old = $_SESSION['announcement_form'] ?? null;
unset($_SESSION['announcement_form']);
if (is_array($old) && ($old['id'] ?? null) === $id) {
    $values = array_merge($values, $old['values']);
}

$revision = (int) ($record['revision'] ?? 0);
$pageTitle = $id === null ? 'New announcement' : 'Edit announcement';

require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <h1 class="h3 mb-0"><?= h($pageTitle) ?></h1>
  <a class="btn btn-outline-secondary" href="index.php">&laquo; Back to announcements</a>
</div>

<form method="post" action="save.php" class="card card-body">
  <?= announcement_csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) ($id ?? 0) ?>">
  <input type="hidden" name="revision" value="<?= $revision ?>">

  <div class="mb-3">
    <label for="title" class="form-label">Title</label>
    <input type="text" class="form-control" id="title" name="title" maxlength="200" required value="<?= h($values['title']) ?>">
  </div>

  <div class="mb-3">
    <label for="summary" class="form-label">Short summary <span class="text-muted small">(optional, up to 300 characters)</span></label>
    <input type="text" class="form-control" id="summary" name="summary" maxlength="300" value="<?= h($values['summary']) ?>">
  </div>

  <div class="mb-3">
    <label for="body" class="form-label">Content</label>
    <textarea class="form-control" id="body" name="body" rows="10" maxlength="20000" required><?= h($values['body']) ?></textarea>
  </div>

  <div class="row">
    <div class="col-md-6 mb-3">
      <label for="category" class="form-label">Category</label>
      <select class="form-select" id="category" name="category">
        <?php foreach (announcement_categories() as $key => $label): ?>
          <option value="<?= h($key) ?>"<?= $values['category'] === $key ? ' selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-6 mb-3">
      <label for="status" class="form-label">Status</label>
      <select class="form-select" id="status" name="status">
        <?php foreach (announcement_statuses() as $key => $label): ?>
          <option value="<?= h($key) ?>"<?= $values['status'] === $key ? ' selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <div><button class="btn btn-primary" type="submit">Save announcement</button></div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>