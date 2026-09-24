<div class="mb-4">
    <p class="eyebrow"><?= escape(ucfirst($user['role'])) ?> dashboard</p>
    <h1 class="h3">Welcome, <?= escape($user['full_name']) ?></h1>
    <p class="text-secondary">Your OMSC Internationalization Affairs workspace.</p>
</div>
<section class="card border-0 shadow-sm" aria-labelledby="workspace-heading">
    <div class="card-body p-4">
        <span class="badge text-bg-success mb-3">Account active</span>
        <h2 class="h5" id="workspace-heading">Your workspace is ready</h2>
        <p class="text-secondary mb-0"><?= $user['role'] === 'admin'
            ? 'Review submitted requests, verify documents, and follow each request through completion.'
            : 'Create a request, upload its requirements, and track updates from the ELIA Office.' ?></p>
        <a class="btn btn-primary mt-3" href="<?= escape(url($user['role'] . '/requests/index.php')) ?>"><?= $user['role'] === 'admin' ? 'Review requests' : 'My requests' ?></a>
    </div>
</section>
