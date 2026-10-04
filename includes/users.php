<?php
declare(strict_types=1);
require_once __DIR__ . '/registration.php';

function managed_user(int $id): ?array
{
    $statement = database()->prepare('SELECT id, full_name, email, role, is_active, created_at, last_login_at FROM users WHERE id = ?');
    $statement->execute([$id]);
    return $statement->fetch() ?: null;
}

function managed_user_version(array $user): string
{
    // Login timestamps/counters do not conflict with account edits.
    return hash('sha256', json_encode([(int) $user['id'], $user['full_name'], $user['email'], $user['role'], (int) $user['is_active']], JSON_THROW_ON_ERROR));
}

function managed_users(string $search, string $role, string $status, int $page): array
{
    $where = ['1=1'];
    $params = [];
    if ($search !== '') {
        $where[] = '(full_name LIKE ? OR email LIKE ?)';
        // Search text is literal, including SQL wildcard characters.
        $term = '%' . strtr($search, ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']) . '%';
        $params = [$term, $term];
    }
    if (in_array($role, ['admin', 'client'], true)) {
        $where[] = 'role = ?';
        $params[] = $role;
    }
    if (in_array($status, ['active', 'inactive'], true)) {
        $where[] = 'is_active = ?';
        $params[] = (int) ($status === 'active');
    }
    $sql = implode(' AND ', $where);
    $statement = database()->prepare('SELECT COUNT(*) FROM users WHERE ' . $sql);
    $statement->execute($params);
    $total = (int) $statement->fetchColumn();
    $pages = max(1, (int) ceil($total / 20));
    $page = max(1, min($page, $pages));
    $offset = ($page - 1) * 20;
    $statement = database()->prepare('SELECT id, full_name, email, role, is_active, created_at, last_login_at FROM users WHERE ' . $sql . ' ORDER BY id DESC LIMIT 20 OFFSET ' . $offset);
    $statement->execute($params);
    return ['rows' => $statement->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages];
}

/** Called only after the HTTP Admin and CSRF guards; recheck authority under lock. */
function save_managed_user(int $actorId, int $id, array $input): int
{
    $errors = $id === 0
        ? registration_errors($input['full_name'], $input['email'], $input['password'], $input['password_confirmation'])
        : account_identity_errors($input['full_name'], $input['email']);
    if (!in_array($input['role'], ['admin', 'client'], true)) {
        $errors[] = 'Choose Admin or Client.';
    }
    if (!in_array($input['is_active'], ['0', '1'], true)) {
        $errors[] = 'Choose an account status.';
    }
    if ($errors) {
        throw new DomainException(implode(' ', $errors));
    }
    $pdo = database();
    $pdo->beginTransaction();
    try {
        // Serialize administrator access changes, including simultaneous demotions.
        $statement = $pdo->prepare("SELECT id, full_name, email, role, is_active FROM users WHERE role = 'admin' OR id = ? OR id = ? ORDER BY id FOR UPDATE");
        $statement->execute([$actorId, $id]);
        $accounts = [];
        $activeAdmins = 0;
        foreach ($statement->fetchAll() as $account) {
            $accounts[(int) $account['id']] = $account;
            $activeAdmins += (int) ($account['role'] === 'admin' && $account['is_active']);
        }
        $actor = $accounts[$actorId] ?? null;
        if (!$actor || $actor['role'] !== 'admin' || !$actor['is_active']) {
            throw new DomainException('Your account no longer has permission to manage users.');
        }
        if ($id > 0) {
            $existing = $accounts[$id] ?? null;
            if (!$existing) {
                throw new DomainException('Account not found.');
            }
            if (!hash_equals(managed_user_version($existing), $input['version'])) {
                throw new DomainException('This account changed since you opened it. Reload the account and try again.');
            }
            $removesAdmin = $input['role'] !== 'admin' || $input['is_active'] !== '1';
            if ($existing['role'] === 'admin' && $existing['is_active'] && $removesAdmin && $activeAdmins <= 1) {
                throw new DomainException('The last active administrator must remain active with the Admin role.');
            }
            if ($id === $actorId && $removesAdmin) {
                throw new DomainException('You cannot deactivate your own account or change your own role.');
            }
            $statement = $pdo->prepare('UPDATE users SET full_name = ?, email = ?, role = ?, is_active = ? WHERE id = ?');
            $statement->execute([$input['full_name'], $input['email'], $input['role'], (int) $input['is_active'], $id]);
            if ($existing['email'] !== $input['email'] || $existing['role'] !== $input['role']
                || (int) $existing['is_active'] !== (int) $input['is_active']) {
                $statement = $pdo->prepare('DELETE FROM password_resets WHERE user_id = ?');
                $statement->execute([$id]);
            }
        } else {
            $statement = $pdo->prepare('INSERT INTO users (full_name, email, password_hash, role, is_active) VALUES (?, ?, ?, ?, ?)');
            $statement->execute([$input['full_name'], $input['email'], password_hash($input['password'], PASSWORD_DEFAULT), $input['role'], (int) $input['is_active']]);
            $id = (int) $pdo->lastInsertId();
        }
        $pdo->commit();
        return $id;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($exception instanceof PDOException && ($exception->errorInfo[1] ?? null) === 1062) {
            throw new DomainException('That email address is already assigned to an account.');
        }
        throw $exception;
    }
}
