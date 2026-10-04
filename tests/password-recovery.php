<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/password-recovery.php';
require __DIR__ . '/../includes/users.php';
require __DIR__ . '/http.php';
$baseUrl = rtrim($argv[1] ?? 'http://127.0.0.1:8080/elia-system', '/');
if (!in_array(parse_url($baseUrl, PHP_URL_HOST), ['127.0.0.1', 'localhost'], true)) { exit("Local servers only.\n"); }
$pdo = database();
$checks = 0;
$suffix = bin2hex(random_bytes(8));
$password = bin2hex(random_bytes(16));
$newPassword = bin2hex(random_bytes(16));
$accounts = $emails = $handles = $messages = $buckets = [];
$config = array_replace(app_config(), ['app_url' => $baseUrl, 'recovery_mail_enabled' => true, 'mail_from' => 'no-reply@example.test']);
$transport = function (string $email, string $link) use (&$messages): bool { $messages[] = ['email' => $email, 'link' => $link]; return true; };
$clearLimits = function (string $email): void {
    $statement = database()->prepare('DELETE FROM password_reset_limits WHERE bucket = ?');
    $statement->execute([hash('sha256', 'email:' . $email)]);
};
$issue = function (string $key, ?callable $sender = null) use (&$emails, &$messages, &$buckets, $suffix, $transport, $config): ?string {
    $ip = 'fixture-' . $suffix . '-' . $key;
    $buckets[] = hash('sha256', 'ip:' . $ip);
    $before = count($messages);
    request_password_recovery($emails[$key], $ip, $sender ?? $transport, $config);
    if (count($messages) === $before) { return null; }
    parse_str(parse_url(end($messages)['link'], PHP_URL_QUERY), $query);
    return $query['token'];
};
$age = function (int $id, bool $expired = false): void {
    $statement = database()->prepare('UPDATE password_resets SET requested_at = DATE_SUB(NOW(), INTERVAL 2 MINUTE)' . ($expired ? ', expires_at = DATE_SUB(NOW(), INTERVAL 1 SECOND)' : '') . ' WHERE user_id = ?');
    $statement->execute([$id]);
};
try {
    foreach (['admin', 'client', 'extra', 'inactive'] as $key) {
        $emails[$key] = "recovery.$key.$suffix@example.test";
        $statement = $pdo->prepare('INSERT INTO users (full_name, email, password_hash, role, is_active) VALUES (?, ?, ?, ?, ?)');
        $statement->execute(['Recovery fixture', $emails[$key], password_hash($password, PASSWORD_DEFAULT), $key === 'admin' ? 'admin' : 'client', (int) ($key !== 'inactive')]);
        $accounts[$key] = (int) $pdo->lastInsertId();
    }
    $guest = $handles[] = browser();
    check(str_contains(request($guest, 'login.php')['body'], 'forgot-password.php'), 'Login links to password recovery');
    foreach (['forgot-password', 'reset-password'] as $action) {
        check(request($guest, 'actions/' . $action . '.php')['status'] === 405, "$action rejects GET");
        check(request($guest, 'actions/' . $action . '.php', [])['status'] === 403, "$action requires CSRF");
    }
    $forgot = request($guest, 'forgot-password.php');
    $emails['unknown'] = "unknown.$suffix@example.test";
    $responses = [];
    foreach ([$emails['unknown'], $emails['inactive']] as $email) {
        $response = request($guest, 'actions/forgot-password.php', ['csrf_token' => token($forgot), 'email' => $email]);
        $responses[] = request($guest, 'forgot-password.php');
        check($response['status'] === 303, 'Recovery request returns generic redirect');
    }
    check($responses[0]['body'] === $responses[1]['body'] && str_contains($responses[0]['body'], RECOVERY_MESSAGE), 'Unknown and inactive accounts receive identical responses');
    foreach (['admin', 'client'] as $role) {
        $active = $handles[] = browser();
        $login = request($active, 'login.php');
        request($active, 'actions/login.php', ['csrf_token' => token($login), 'email' => $emails[$role], 'password' => $password]);
        check(request($active, "$role/dashboard.php")['status'] === 200, "$role has an existing authenticated session");
        $resetToken = $issue($role);
        check(is_string($resetToken) && strlen($resetToken) === 64, "$role receives a random reset token through the test transport");
        $statement = $pdo->prepare('SELECT token_hash FROM password_resets WHERE user_id = ?');
        $statement->execute([$accounts[$role]]);
        check($statement->fetchColumn() === hash('sha256', $resetToken), 'Database stores only the token hash');
        $resetBrowser = $handles[] = browser();
        $landing = request($resetBrowser, 'reset-password.php?token=' . $resetToken);
        check($landing['status'] === 303 && !str_contains($landing['headers'], $resetToken) && str_contains($landing['headers'], 'Referrer-Policy: no-referrer'), 'Token URL redirects to a clean URL with no referrer');
        $form = request($resetBrowser, 'reset-password.php');
        check(str_contains($form['body'], 'name="password_confirmation"') && !str_contains($form['body'], $resetToken), 'Reset form does not expose token');
        check(recovery_token_record($resetToken) !== null, 'Opening the link does not consume it');
        request($resetBrowser, 'actions/reset-password.php', ['csrf_token' => token($form), 'password' => 'short', 'password_confirmation' => 'short']);
        check(recovery_token_record($resetToken) !== null, 'Invalid password preserves the usable token');
        request($resetBrowser, 'actions/reset-password.php', ['csrf_token' => token($form), 'password' => $newPassword, 'password_confirmation' => 'mismatch']);
        check(recovery_token_record($resetToken) !== null, 'Password confirmation is enforced');
        $response = request($resetBrowser, 'actions/reset-password.php', ['csrf_token' => token($form), 'password' => $newPassword, 'password_confirmation' => $newPassword]);
        check($response['status'] === 303 && str_contains($response['headers'], '/login.php'), 'Successful reset requires normal sign-in');
        check(recovery_token_record($resetToken) === null, 'Successful reset consumes token');
        check(request($active, "$role/dashboard.php")['status'] === 303, 'Password reset revokes the previous session');
        $signIn = request($resetBrowser, 'login.php');
        request($resetBrowser, 'actions/login.php', ['csrf_token' => token($signIn), 'email' => $emails[$role], 'password' => $password]);
        check(request($resetBrowser, "$role/dashboard.php")['status'] === 303, 'Old password no longer authenticates');
        request($resetBrowser, 'actions/login.php', ['csrf_token' => token(request($resetBrowser, 'login.php')), 'email' => $emails[$role], 'password' => $newPassword]);
        check(request($resetBrowser, "$role/dashboard.php")['status'] === 200, 'New password authenticates with original role');
        $blocked = false;
        try { reset_account_password($resetToken, $password, $password); }
        catch (DomainException $exception) { $blocked = true; }
        check($blocked, 'Consumed token cannot be replayed');
    }
    $first = $issue('extra');
    check($issue('extra') === null && recovery_token_record($first) !== null, 'Resend cooldown preserves existing link');
    $age($accounts['extra']);
    $second = $issue('extra');
    check($second !== null && recovery_token_record($first) === null, 'Newly issued link invalidates previous link');
    $age($accounts['extra'], true);
    check(recovery_token_record($second) === null, 'Expired link is rejected');
    $clearLimits($emails['extra']);
    $third = $issue('extra');
    $statement = $pdo->prepare('UPDATE users SET is_active = 0 WHERE id = ?');
    $statement->execute([$accounts['extra']]);
    check(recovery_token_record($third) === null, 'Deactivated accounts cannot use an outstanding link');
    $pdo->prepare('UPDATE users SET is_active = 1 WHERE id = ?')->execute([$accounts['extra']]);
    $record = managed_user($accounts['extra']);
    save_managed_user($accounts['admin'], $accounts['extra'], ['full_name' => $record['full_name'], 'email' => $record['email'], 'role' => 'admin', 'is_active' => '1', 'version' => managed_user_version($record)]);
    check(recovery_token_record($third) === null, 'Admin access changes invalidate pending reset links');
    $clearLimits($emails['extra']);
    $before = count($messages);
    $issue('extra', fn () => false);
    $statement = $pdo->prepare('SELECT COUNT(*) FROM password_resets WHERE user_id = ?');
    $statement->execute([$accounts['extra']]);
    check((int) $statement->fetchColumn() === 0 && count($messages) === $before, 'Failed delivery leaves no usable reset token');
    check($issue('inactive') === null && $issue('unknown') === null, 'No email is delivered for inactive or unknown accounts');
    $clearLimits($emails['extra']);
    $emailToken = $issue('extra');
    $record = managed_user($accounts['extra']);
    $updatedEmail = 'updated.' . $emails['extra'];
    save_managed_user($accounts['admin'], $accounts['extra'], ['full_name' => $record['full_name'], 'email' => $updatedEmail, 'role' => $record['role'], 'is_active' => '1', 'version' => managed_user_version($record)]);
    check(recovery_token_record($emailToken) === null, 'Changing the account email invalidates old-mailbox links');
    $buckets[] = hash('sha256', 'email:' . $emails['extra']);
    $emails['extra'] = $updatedEmail;
    $raceToken = $issue('extra');
    $pdo->prepare('UPDATE users SET failed_login_attempts = 5, locked_until = DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE id = ?')->execute([$accounts['extra']]);
    $statement = $pdo->prepare('SELECT auth_version FROM users WHERE id = ?');
    $statement->execute([$accounts['extra']]);
    $versionBefore = (int) $statement->fetchColumn();
    $multi = curl_multi_init();
    $racers = [];
    for ($i = 0; $i < 2; $i++) {
        $racer = $handles[] = browser();
        request($racer, 'reset-password.php?token=' . $raceToken);
        $form = request($racer, 'reset-password.php');
        curl_setopt_array($racer, [CURLOPT_URL => $baseUrl . '/actions/reset-password.php', CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(['csrf_token' => token($form), 'password' => $newPassword . $i, 'password_confirmation' => $newPassword . $i])]);
        curl_multi_add_handle($multi, $racer);
        $racers[] = $racer;
    }
    do {
        $status = curl_multi_exec($multi, $running);
        if ($running) { curl_multi_select($multi, 0.2); }
    } while ($running && $status === CURLM_OK);
    $successes = 0;
    foreach ($racers as $racer) {
        $response = curl_multi_getcontent($racer);
        $successes += (int) (curl_getinfo($racer, CURLINFO_HTTP_CODE) === 303 && str_contains($response, 'Location: ' . (parse_url($baseUrl, PHP_URL_PATH) ?: '') . '/login.php'));
        curl_multi_remove_handle($multi, $racer);
    }
    curl_multi_close($multi);
    $statement = $pdo->prepare('SELECT auth_version, failed_login_attempts, locked_until FROM users WHERE id = ?');
    $statement->execute([$accounts['extra']]);
    $afterRace = $statement->fetch();
    check($successes === 1 && (int) $afterRace['auth_version'] === $versionBefore + 1, 'Concurrent resets allow exactly one successful password change');
    check((int) $afterRace['failed_login_attempts'] === 0 && $afterRace['locked_until'] === null, 'Verified reset clears login cooldown');
    $key = 'rate-' . $suffix;
    $buckets[] = hash('sha256', 'email:' . $key);
    check(recovery_allow('email', $key, 3) && recovery_allow('email', $key, 3) && recovery_allow('email', $key, 3) && !recovery_allow('email', $key, 3), 'Email rate limit survives multiple requests');
    $buckets[] = hash('sha256', 'ip:' . $key);
    for ($i = 0; $i < 20; $i++) { recovery_allow('ip', $key, 20); }
    check(!recovery_allow('ip', $key, 20), 'IP limit spans multiple target emails');
    $pdo->prepare('UPDATE password_reset_limits SET expires_at = DATE_SUB(NOW(), INTERVAL 1 SECOND) WHERE bucket = ?')->execute([hash('sha256', 'email:' . $key)]);
    check(recovery_allow('email', $key, 3), 'Expired rate limit window resets');
    foreach (['http://evil.example', 'https://good.example/?next=evil', 'https://user:pass@good.example'] as $base) {
        $blocked = false;
        try { recovery_url(str_repeat('a', 64), ['app_url' => $base]); }
        catch (RuntimeException $exception) { $blocked = true; }
        check($blocked, 'Unsafe application URL is rejected');
    }
    check(recovery_token_record("' OR 1=1 --") === null, 'Malformed tokens are rejected');
    check(str_contains(request($guest, 'reset-password.php')['body'], 'invalid or expired'), 'Missing reset token displays safe recovery guidance');
    echo "Completed $checks password recovery checks. No real emails sent.\n";
} finally {
    foreach ($handles as $handle) { curl_close($handle); }
    foreach ($accounts as $id) { $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]); }
    foreach ($emails as $email) { $buckets[] = hash('sha256', 'email:' . $email); }
    foreach ($buckets as $bucket) { $pdo->prepare('DELETE FROM password_reset_limits WHERE bucket = ?')->execute([$bucket]); }
}
