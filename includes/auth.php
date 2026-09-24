<?php
declare(strict_types=1);

function current_user(): ?array
{
    static $loaded = false;
    static $user = null;
    if (!$loaded) {
        $loaded = true;
        if (isset($_SESSION['user_id'])) {
            $statement = database()->prepare('SELECT id, full_name, email, role, is_active FROM users WHERE id = :id');
            $statement->execute(['id' => $_SESSION['user_id']]);
            $record = $statement->fetch();
            if ($record && $record['is_active'] && in_array($record['role'], ['admin', 'client'], true)) {
                $user = $record;
            } else {
                unset($_SESSION['user_id']);
            }
        }
    }
    return $user;
}

function dashboard_path(string $role): string
{
    return $role === 'admin' ? 'admin/dashboard.php' : 'client/dashboard.php';
}

function requireAdmin(): array
{
    return require_role('admin');
}

function requireClient(): array
{
    return require_role('client');
}

function require_guest(): void
{
    if ($user = current_user()) {
        redirect(dashboard_path($user['role']));
    }
}

function require_role(string $role): array
{
    $user = current_user();
    if ($user === null) {
        redirect('login.php');
    }
    if ($user['role'] !== $role) {
        http_response_code(403);
        $pageTitle = 'Access denied';
        require __DIR__ . '/header.php';
        echo '<div class="alert alert-danger">You do not have permission to view this page.</div>';
        require __DIR__ . '/footer.php';
        exit;
    }
    return $user;
}

function attempt_login(string $email, string $password): bool
{
    $pdo = database();
    $pdo->beginTransaction();
    try {
        // Lock the account so concurrent failures cannot bypass its cooldown.
        $statement = $pdo->prepare('SELECT *, (locked_until > NOW()) AS is_locked FROM users WHERE email = :email FOR UPDATE');
        $statement->execute(['email' => $email]);
        $user = $statement->fetch();
        $validPassword = password_verify($password, $user['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
        if (!$user || !$user['is_active'] || $user['is_locked'] || !$validPassword
            || !in_array($user['role'], ['admin', 'client'], true)) {
            if ($user && !$user['is_locked']) {
                $previousFailures = $user['locked_until'] !== null ? 0 : (int) $user['failed_login_attempts'];
                $failures = min($previousFailures + 1, 5);
                $statement = $pdo->prepare('UPDATE users SET failed_login_attempts = :failures, locked_until = CASE WHEN :lock_account = 1 THEN DATE_ADD(NOW(), INTERVAL 15 MINUTE) ELSE NULL END WHERE id = :id');
                $statement->execute(['failures' => $failures, 'lock_account' => (int) ($failures >= 5), 'id' => $user['id']]);
            }
            $pdo->commit();
            return false;
        }
        $hash = password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)
            ? password_hash($password, PASSWORD_DEFAULT) : $user['password_hash'];
        $statement = $pdo->prepare('UPDATE users SET failed_login_attempts = 0, locked_until = NULL, last_login_at = NOW(), password_hash = :hash WHERE id = :id');
        $statement->execute(['hash' => $hash, 'id' => $user['id']]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
    session_regenerate_id(true);
    $_SESSION = ['user_id' => (int) $user['id'], 'last_activity' => time()];
    return true;
}
