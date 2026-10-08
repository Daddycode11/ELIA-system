<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/announcements.php';
require_once __DIR__ . '/_common.php';

$user = requireAdmin();
$pageTitle = 'Announcements';

$search = trim((string) ($_GET['q'] ?? ''));
$status = (string) ($_GET['status'] ?? '');
$page = (int) ($_GET['page'] ?? 1);
$list = announcement_admin_list($search, $status, $page);

$query = static fn (int $p): string => '?' . http_build_query(array_filter(
    ['q' => $search, 'status' => $status, 'page' => $p],
    static fn ($v) => $v !== '' && $v !== null
));

require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <h1 class="h3 mb-0">Announcements</h1>
  <a class="btn btn-primary" href="form.php">New announcement</a>
</div>

<form method="get" class="row g-2 mb-3">
  <div class="col-12 col-md-6">
    <input type="search" class="form-control" name="q" value="<?= h($search) ?>" placeholder="Search title or summary">
  </div>
  <div class="col-8 col-md-3">
    <select name="status" class="form-select">
      <option value="">All statuses</option>
      <?php foreach (announcement_statuses() as $key => $label): ?>
        <option value="<?= h($key) ?>"<?= $status === $key ? ' selected' : '' ?>><?= h($label) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-4 col-md-3">
    <button class="btn btn-outline-secondary w-100" type="submit">Filter</button>
  </div>
</form>

<div class="table-responsive">
  <table class="table table-hover align-middle">
    <thead>
      <tr><th>Title</th><th>Category</th><th>Status</th><th>Author</th><th>Published</th><th></th></tr>
    </thead>
    <tbody>
    <?php if (!$list['rows']): ?>
      <tr><td colspan="6" class="text-center text-muted py-4">No announcements found.</td></tr>
    <?php endif; ?>
    <?php foreach ($list['rows'] as $row): ?>
      <?php $badge = ['published' => 'success', 'archived' => 'secondary', 'draft' => 'warning'][$row['status']] ?? 'secondary'; ?>
      <tr>
        <td><?= h($row['title']) ?></td>
        <td><?= h(announcement_categories()[$row['category']] ?? $row['category']) ?></td>
        <td><span class="badge text-bg-<?= $badge ?>"><?= h(announcement_statuses()[$row['status']] ?? $row['status']) ?></span></td>
        <td><?= h($row['author']) ?></td>
        <td><?= h(announcement_date($row['published_at'])) ?></td>
        <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="form.php?id=<?= (int) $row['id'] ?>">Edit</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="d-flex justify-content-between align-items-center">
  <small class="text-muted"><?= (int) $list['total'] ?> result(s) &middot; Page <?= (int) $list['page'] ?> of <?= (int) $list['pages'] ?></small>
  <div class="btn-group">
    <?php if ($list['page'] > 1): ?>
      <a class="btn btn-sm btn-outline-secondary" href="<?= h($query($list['page'] - 1)) ?>">&laquo; Previous</a>
    <?php endif; ?>
    <?php if ($list['page'] < $list['pages']): ?>
      <a class="btn btn-sm btn-outline-secondary" href="<?= h($query($list['page'] + 1)) ?>">Next &raquo;</a>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>