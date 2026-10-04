<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/users.php';
require __DIR__ . '/http.php';
$baseUrl = rtrim($argv[1] ?? 'http://127.0.0.1:8080/elia-system', '/');
if (!in_array(parse_url($baseUrl, PHP_URL_HOST), ['127.0.0.1', 'localhost'], true)) {
    exit("Use a local development server only.\n");
}
$checks = 0;
$suffix = bin2hex(random_bytes(8));
$password = bin2hex(random_bytes(16));
$emails = [];
$pdo = database();
$handles = [];
try {
    foreach (['admin', 'client'] as $role) {
        $emails[$role] = "$role.$suffix@example.test";
        $statement = $pdo->prepare('INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, ?)');
        $statement->execute(['<script>Fixture</script>', $emails[$role], password_hash($password, PASSWORD_DEFAULT), $role]);
        $ids[$role] = (int) $pdo->lastInsertId();
        $handles[$role] = browser();
        request($handles[$role], 'actions/login.php', ['csrf_token' => token(request($handles[$role], 'login.php')), 'email' => $emails[$role], 'password' => $password]);
    }
    $guest = $handles['guest'] = browser();
    foreach (['admin/users/index.php', 'admin/users/form.php', 'actions/users/save.php'] as $path) {
        check(request($guest, $path)['status'] === 303, "Guest blocked: $path");
        check(request($handles['client'], $path)['status'] === 403, "Client blocked: $path");
    }
    $admin = $handles['admin'];
    $page = request($admin, 'admin/users/index.php?q=' . $suffix);
    $csrf = token($page);
    check($page['status'] === 200 && str_contains($page['body'], '&lt;script&gt;Fixture&lt;/script&gt;'), 'List escapes stored names');
    check(request($admin, 'actions/users/save.php')['status'] === 405, 'Updates reject GET');
    check(request($admin, 'actions/users/save.php', ['id' => 0])['status'] === 403, 'Updates require CSRF');
    check(request($admin, 'actions/users/save.php', ['id' => '-1', 'csrf_token' => $csrf])['status'] === 400, 'Invalid IDs rejected');
    check(request($admin, 'admin/users/form.php?id=999999999')['status'] === 404, 'Missing account returns 404');
    check(request($admin, 'admin/users/form.php?id[]=1')['status'] === 404, 'Malformed ID returns 404');
    $emails['created'] = "created.$suffix@example.test";
    $fields = ['csrf_token' => $csrf, 'id' => '0', 'full_name' => 'Created account', 'email' => $emails['created'], 'role' => 'admin', 'is_active' => '1', 'password' => $password, 'password_confirmation' => $password];
    foreach ([['role' => 'coordinator'], ['is_active' => '2'], ['email' => ['bad']], ['full_name' => ''], ['password' => 'short'], ['password_confirmation' => 'different']] as $invalid) {
        request($admin, 'actions/users/save.php', array_replace($fields, $invalid));
        $statement = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
        $statement->execute([$emails['created']]);
        check((int) $statement->fetchColumn() === 0, 'Invalid account creation has no database changes');
    }
    $response = request($admin, 'actions/users/save.php', $fields);
    $statement = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $statement->execute([$emails['created']]);
    $created = $statement->fetch();
    check($response['status'] === 303 && $created && $created['role'] === 'admin', 'Admin can create an Admin account');
    check(password_verify($password, $created['password_hash']), 'Initial password is hashed correctly');
    request($admin, 'actions/users/save.php', array_replace($fields, ['email' => strtoupper($emails['created'])]));
    check(str_contains(request($admin, 'admin/users/form.php')['body'], 'already assigned'), 'Duplicate email handled without database error');
    $save = function (int $id, array $changes = []) use ($admin, $csrf): array {
        $record = managed_user($id);
        return request($admin, 'actions/users/save.php', array_replace([
            'csrf_token' => $csrf, 'id' => (string) $id, 'full_name' => $record['full_name'],
            'email' => $record['email'], 'role' => $record['role'], 'is_active' => (string) $record['is_active'],
            'version' => managed_user_version($record),
        ], $changes));
    };
    $save($ids['admin'], ['role' => 'client']);
    check(managed_user($ids['admin'])['role'] === 'admin', 'Self-demotion blocked');
    $save($ids['admin'], ['is_active' => '0']);
    check((int) managed_user($ids['admin'])['is_active'] === 1, 'Self-deactivation blocked');
    $before = managed_user($ids['client']);
    $save($ids['client'], ['full_name' => 'Updated client']);
    check(managed_user($ids['client'])['full_name'] === 'Updated client', 'Account details editable');
    $save($ids['client'], ['full_name' => 'Stale edit', 'version' => managed_user_version($before)]);
    check(managed_user($ids['client'])['full_name'] === 'Updated client', 'Stale forms cannot overwrite newer edits');
    $save($ids['client'], ['email' => $emails['admin']]);
    check(managed_user($ids['client'])['email'] === $emails['client'], 'Duplicate email update rolls back');
    $save($ids['client'], ['is_active' => '0']);
    check(request($handles['client'], 'client/dashboard.php')['status'] === 303, 'Deactivation blocks an existing session');
    $login = request($handles['client'], 'login.php');
    request($handles['client'], 'actions/login.php', ['csrf_token' => token($login), 'email' => $emails['client'], 'password' => $password]);
    check(request($handles['client'], 'client/dashboard.php')['status'] === 303, 'Inactive account cannot sign in');
    $save($ids['client'], ['is_active' => '1']);
    request($handles['client'], 'actions/login.php', ['csrf_token' => token(request($handles['client'], 'login.php')), 'email' => $emails['client'], 'password' => $password]);
    check(request($handles['client'], 'client/dashboard.php')['status'] === 200, 'Reactivated account can sign in');
    $save($ids['client'], ['role' => 'admin']);
    check(request($handles['client'], 'admin/users/index.php')['status'] === 200, 'Authorized role update changes access');
    $save($ids['client'], ['role' => 'client']);
    check(request($handles['client'], 'admin/users/index.php')['status'] === 403, 'Demoted account immediately loses Admin access');
    $page = request($admin, 'admin/users/index.php?q=' . $suffix . '&role=client&status=active');
    check(str_contains($page['body'], $emails['client']) && !str_contains($page['body'], 'View or edit Created account'), 'Role/status/search filters combine');
    check(!str_contains(request($handles['client'], 'client/dashboard.php')['body'], 'admin/users/index.php'), 'User management link hidden from Client');
    // Pagination fixtures use no requests or other related records.
    for ($i = 0; $i < 21; $i++) {
        $email = "page$i.$suffix@example.test";
        $emails[] = $email;
        $statement = $pdo->prepare("INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, 'client')");
        $statement->execute(['Pagination', $email, $created['password_hash']]);
    }
    $page = request($admin, 'admin/users/index.php?q=' . $suffix . '&page=2');
    check(str_contains($page['body'], 'Page 2 of 2'), 'Account list paginates');
    check(str_contains(request($admin, 'admin/users/index.php?q=no-match-' . $suffix)['body'], 'No matching accounts.'), 'Empty search is clear');
    // Connection-local shadow table tests the last-admin boundary without touching real accounts.
    $schema = file_get_contents(__DIR__ . '/../database/schema.sql');
    $pdo->exec(str_replace('CREATE TABLE IF NOT EXISTS users', 'CREATE TEMPORARY TABLE users', $schema));
    try {
        $statement = $pdo->prepare("INSERT INTO users (full_name, email, password_hash, role) VALUES ('Only admin', ?, ?, 'admin')");
        $statement->execute(['only@example.test', $created['password_hash']]);
        $onlyId = (int) $pdo->lastInsertId();
        $only = managed_user($onlyId);
        $input = ['full_name' => $only['full_name'], 'email' => $only['email'], 'role' => 'admin', 'is_active' => '1', 'version' => managed_user_version($only)];
        foreach ([['is_active' => '0'], ['role' => 'client']] as $change) {
            $blocked = false;
            try { save_managed_user($onlyId, $onlyId, array_replace($input, $change)); }
            catch (DomainException $exception) { $blocked = str_contains($exception->getMessage(), 'last active administrator'); }
            check($blocked && managed_user($onlyId)['role'] === 'admin' && (int) managed_user($onlyId)['is_active'] === 1, 'Last active administrator is protected');
        }
        $pdo->exec('UPDATE users SET is_active = 0');
        $blocked = false;
        try { save_managed_user($onlyId, $onlyId, $input); }
        catch (DomainException $exception) { $blocked = str_contains($exception->getMessage(), 'no longer has permission'); }
        check($blocked, 'Mutation rechecks actor authority under transaction lock');
    } finally {
        $pdo->exec('DROP TEMPORARY TABLE users');
    }
    echo "\n$checks user management checks passed.\n";
} finally {
    foreach ($handles as $handle) { curl_close($handle); }
    foreach ($emails as $email) {
        $statement = $pdo->prepare('DELETE FROM users WHERE email = ?');
        $statement->execute([$email]);
    }
}
