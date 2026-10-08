<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/resources.php';
$user = requireAdmin();
$pageTitle = 'Resources';
$resources = resource_admin_list();
$categories = resource_categories();
require __DIR__ . '/../../includes/header.php';
?>
<div class="mb-4">
    <p class="eyebrow">Public information</p>
    <h1 class="h3">Resources &amp; forms</h1>
    <p class="text-secondary mb-0">Upload blank forms (e.g., IAS Form 15), guidelines, and reports. Published files appear on the landing page and in the client information page. Do not upload documents containing personal data.</p>
</div>
<form method="post" enctype="multipart/form-data" action="<?= escape(url('actions/resources/save.php')) ?>" class="card card-body mb-4">
    <?= csrf_field() ?><input type="hidden" name="operation" value="add">
    <h2 class="h5">Add a file</h2>
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label" for="title">Title</label><input class="form-control" id="title" name="title" maxlength="200" required></div>
        <div class="col-md-3"><label class="form-label" for="category">Category</label>
            <select class="form-select" id="category" name="category"><?php foreach ($categories as $key => $label): ?><option value="<?= escape($key) ?>"><?= escape($label) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><label class="form-label" for="document">File (PDF/DOC/DOCX/JPG/PNG, max 10 MB)</label><input class="form-control" id="document" name="document" type="file" required accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"></div>
        <div class="col-12"><label class="form-label" for="description">Description <span class="text-secondary">(optional)</span></label><input class="form-control" id="description" name="description" maxlength="500"></div>
        <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" id="published" name="is_published" value="1" checked><label class="form-check-label" for="published">Publish immediately</label></div></div>
    </div>
    <div class="mt-3"><button class="btn btn-primary" type="submit">Upload</button></div>
</form>
<div class="card"><div class="table-responsive"><table class="table align-middle mb-0">
    <thead><tr><th scope="col">Title</th><th scope="col">Category</th><th scope="col">File</th><th scope="col">Status</th><th scope="col">Action</th></tr></thead>
    <tbody>
    <?php foreach ($resources as $row): ?>
        <tr>
            <td><strong><?= escape($row['title']) ?></strong><?php if ($row['description'] !== ''): ?><br><span class="small text-secondary"><?= escape($row['description']) ?></span><?php endif; ?></td>
            <td><?= escape($categories[$row['category']] ?? $row['category']) ?></td>
            <td><a href="<?= escape(url('actions/resources/download.php?id=' . (int) $row['id'])) ?>"><?= escape($row['original_filename']) ?></a><br><span class="small text-secondary"><?= escape(resource_size_label((int) $row['file_size'])) ?></span></td>
            <td><span class="badge text-bg-<?= $row['is_published'] ? 'success' : 'secondary' ?>"><?= $row['is_published'] ? 'Published' : 'Hidden' ?></span></td>
            <td>
                <form method="post" action="<?= escape(url('actions/resources/save.php')) ?>">
                    <?= csrf_field() ?><input type="hidden" name="operation" value="toggle"><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><input type="hidden" name="publish" value="<?= $row['is_published'] ? '0' : '1' ?>">
                    <button class="btn btn-sm btn-outline-primary" type="submit"><?= $row['is_published'] ? 'Hide' : 'Publish' ?><span class="visually-hidden"> <?= escape($row['title']) ?></span></button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$resources): ?><tr><td colspan="5" class="p-4 text-center text-secondary">No files uploaded yet.</td></tr><?php endif; ?>
    </tbody></table></div></div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
