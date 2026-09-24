<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = current_user();
if (!$user) { redirect('login.php'); }
$page = isset($_GET['page']) && is_scalar($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$statement = database()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=:user'); $statement->execute(['user'=>$user['id']]);
$total = (int) $statement->fetchColumn(); $pages = max(1, (int) ceil($total / 20)); $page = min($page, $pages);
$statement = database()->prepare('SELECT * FROM notifications WHERE user_id=:user ORDER BY id DESC LIMIT 20 OFFSET ' . (($page - 1) * 20));
$statement->execute(['user'=>$user['id']]); $notifications = $statement->fetchAll();
$pageTitle = 'Notifications'; require __DIR__ . '/includes/header.php';
?>
<h1 class="h3 mb-4">Notifications</h1>
<?php if (!$notifications): ?><div class="card card-body text-secondary">No notifications yet.</div><?php endif; ?>
<?php foreach ($notifications as $notification): ?><article class="card card-body mb-3"><div class="d-flex justify-content-between gap-3 flex-wrap"><a href="<?= escape(url($user['role'] . '/requests/view.php?id=' . $notification['request_id'])) ?>"><?= escape($notification['message']) ?></a><?php if (!$notification['read_at']): ?><span class="badge text-bg-primary">Unread</span><?php endif; ?></div><p class="small text-secondary mt-2"><?= escape($notification['created_at']) ?></p>
<?php if (!$notification['read_at']): ?><form method="post" action="<?= escape(url('actions/notifications/read.php')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $notification['id'] ?>"><button class="btn btn-sm btn-outline-secondary" type="submit">Mark as read</button></form><?php endif; ?></article><?php endforeach; ?>
<nav class="d-flex gap-3" aria-label="Notification pages"><?php if ($page > 1): ?><a href="?page=<?= $page - 1 ?>">Previous</a><?php endif; ?><span>Page <?= $page ?> of <?= $pages ?></span><?php if ($page < $pages): ?><a href="?page=<?= $page + 1 ?>">Next</a><?php endif; ?></nav>
<?php require __DIR__ . '/includes/footer.php'; ?>
