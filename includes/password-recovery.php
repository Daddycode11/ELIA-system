<?php
declare(strict_types=1);
require_once __DIR__ . '/registration.php';

const RECOVERY_MESSAGE = 'If an eligible account matches that email, a password reset link will be sent. Check your inbox and spam folder. If it does not arrive, wait before trying again or contact the ELIA Office.';

function recovery_url(string $token, array $config): string
{
    $base = rtrim((string) $config['app_url'], '/');
    $parts = parse_url($base);
    if (!$parts || !filter_var($base, FILTER_VALIDATE_URL)
        || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
        || !(($parts['scheme'] ?? '') === 'https'
            || (($parts['scheme'] ?? '') === 'http' && in_array($parts['host'] ?? '', ['localhost', '127.0.0.1', '[::1]'], true)))) {
        throw new RuntimeException('Configure a trusted HTTPS app_url (HTTP is allowed only for localhost).');
    }
    return $base . '/reset-password.php?token=' . rawurlencode($token);
}

function deliver_recovery_email(string $email, string $link, array $config): bool
{
    $from = (string) $config['mail_from'];
    if (!$config['recovery_mail_enabled'] || !filter_var($from, FILTER_VALIDATE_EMAIL)
        || preg_match('/[\r\n]/', $from . $email)) {
        return false;
    }
    $body = "A password reset was requested for your ELIA account.\r\n\r\n"
        . "Open this link to choose a new password (expires in 30 minutes):\r\n$link\r\n\r\n"
        . "If you did not request this, ignore this email. Your password has not changed.\r\n"
        . "ELIA staff will never ask you to share this link or your password.\r\n";
    return mail($email, 'ELIA password reset', $body, [
        'From' => $from, 'MIME-Version' => '1.0', 'Content-Type' => 'text/plain; charset=UTF-8',
    ]);
}

/** Atomic rolling window counter, including requests for unknown/inactive accounts. */
function recovery_allow(string $scope, string $value, int $limit): bool
{
    $key = hash('sha256', $scope . ':' . $value);
    $pdo = database();
    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare('INSERT INTO password_reset_limits (bucket, attempts, expires_at) VALUES (?, 1, DATE_ADD(NOW(), INTERVAL 1 HOUR)) ON DUPLICATE KEY UPDATE attempts = IF(expires_at <= NOW(), 1, LEAST(attempts + 1, 100000)), expires_at = IF(expires_at <= NOW(), DATE_ADD(NOW(), INTERVAL 1 HOUR), expires_at)');
        $statement->execute([$key]);
        $statement = $pdo->prepare('SELECT attempts FROM password_reset_limits WHERE bucket = ?');
        $statement->execute([$key]);
        $allowed = (int) $statement->fetchColumn() <= $limit;
        $pdo->commit();
        return $allowed;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $exception;
    }
}

/** Optional transport/config are for isolated tests; HTTP input never controls them. */
function request_password_recovery(string $email, string $ip, ?callable $transport = null, ?array $config = null): void
{
    $email = strtolower(trim($email));
    $pdo = database();
    $pdo->exec('DELETE FROM password_reset_limits WHERE expires_at <= NOW() LIMIT 100');
    $ipAllowed = recovery_allow('ip', $ip, 20);
    if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) { return; }
    $emailAllowed = recovery_allow('email', $email, 3);
    if (!$ipAllowed || !$emailAllowed) { return; }
    $config ??= app_config();
    $rawToken = bin2hex(random_bytes(32));
    try { $link = recovery_url($rawToken, $config); }
    catch (RuntimeException $exception) { error_log('ELIA password recovery: trusted app_url is not configured.'); return; }
    $hash = hash('sha256', $rawToken);
    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare("SELECT id, email, auth_version FROM users WHERE email = ? AND is_active = 1 AND role IN ('admin', 'client') FOR UPDATE");
        $statement->execute([$email]);
        $account = $statement->fetch();
        if (!$account) { $pdo->commit(); return; }
        $statement = $pdo->prepare('SELECT (requested_at > DATE_SUB(NOW(), INTERVAL 60 SECOND)) FROM password_resets WHERE user_id = ?');
        $statement->execute([$account['id']]);
        if ($statement->fetchColumn()) { $pdo->commit(); return; }
        $statement = $pdo->prepare('INSERT INTO password_resets (user_id, token_hash, email, auth_version, requested_at, expires_at) VALUES (?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 30 MINUTE)) ON DUPLICATE KEY UPDATE token_hash = VALUES(token_hash), email = VALUES(email), auth_version = VALUES(auth_version), requested_at = VALUES(requested_at), expires_at = VALUES(expires_at)');
        $statement->execute([$account['id'], $hash, $account['email'], $account['auth_version']]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $exception;
    }
    try { $sent = ($transport ?? 'deliver_recovery_email')($account['email'], $link, $config); }
    catch (Throwable $exception) { $sent = false; }
    if (!$sent) {
        // Never retain a usable token after a failed hand-off or log the link.
        $statement = $pdo->prepare('DELETE FROM password_resets WHERE user_id = ? AND token_hash = ?');
        $statement->execute([$account['id'], $hash]);
        error_log('ELIA password recovery: email delivery unavailable. Configure the mail relay and sender.');
    }
}

function recovery_token_record(string $token): ?array
{
    if (!preg_match('/\A[a-f0-9]{64}\z/D', $token)) { return null; }
    $statement = database()->prepare("SELECT r.user_id, r.token_hash FROM password_resets r JOIN users u ON u.id = r.user_id WHERE r.token_hash = ? AND r.expires_at > NOW() AND u.is_active = 1 AND u.role IN ('admin', 'client') AND r.email = u.email AND r.auth_version = u.auth_version");
    $statement->execute([hash('sha256', $token)]);
    return $statement->fetch() ?: null;
}

function reset_account_password(string $token, string $password, string $confirmation): void
{
    $errors = password_errors($password, $confirmation);
    if ($errors) { throw new DomainException(implode(' ', $errors)); }
    $record = recovery_token_record($token);
    if (!$record) { throw new DomainException('This reset link is invalid or expired. Request a new link.'); }
    $newHash = password_hash($password, PASSWORD_DEFAULT);
    $pdo = database();
    $pdo->beginTransaction();
    try {
        // Same lock order as issuance: user first, then token. Only one reset can win.
        $statement = $pdo->prepare('SELECT id, email, is_active, role, auth_version FROM users WHERE id = ? FOR UPDATE');
        $statement->execute([$record['user_id']]);
        $account = $statement->fetch();
        $statement = $pdo->prepare('SELECT *, (expires_at > NOW()) AS valid_time FROM password_resets WHERE user_id = ? FOR UPDATE');
        $statement->execute([$record['user_id']]);
        $reset = $statement->fetch();
        if (!$account || !$account['is_active'] || !in_array($account['role'], ['admin', 'client'], true)
            || !$reset || !$reset['valid_time'] || !hash_equals($reset['token_hash'], hash('sha256', $token))
            || $reset['email'] !== $account['email'] || (int) $reset['auth_version'] !== (int) $account['auth_version']) {
            throw new DomainException('This reset link is invalid or expired. Request a new link.');
        }
        $statement = $pdo->prepare('UPDATE users SET password_hash = ?, auth_version = auth_version + 1, failed_login_attempts = 0, locked_until = NULL WHERE id = ?');
        $statement->execute([$newHash, $account['id']]);
        $statement = $pdo->prepare('DELETE FROM password_resets WHERE user_id = ?');
        $statement->execute([$account['id']]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $exception;
    }
}
