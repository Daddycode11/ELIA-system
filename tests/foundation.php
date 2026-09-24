<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../includes/bootstrap.php';

$baseUrl = rtrim($argv[1] ?? 'http://127.0.0.1:8097/elia-system', '/');
if (!in_array(parse_url($baseUrl, PHP_URL_HOST), ['127.0.0.1', 'localhost'], true)) {
    exit("Run these tests against a local development server only.\n");
}
$pdo = database();
$createdIds = [];
$password = bin2hex(random_bytes(16));
$suffix = bin2hex(random_bytes(8));
$registrationEmail = "registration.$suffix@example.test";
$checks = 0;

require __DIR__ . '/http.php';

try {
    foreach (['admin', 'client'] as $role) {
        $statement = $pdo->prepare('INSERT INTO users (full_name, email, password_hash, role) VALUES (:name, :email, :hash, :role)');
        $statement->execute(['name' => '<script>Test</script>', 'email' => "$role.$suffix@example.test",
            'hash' => password_hash($password, PASSWORD_DEFAULT), 'role' => $role]);
        $createdIds[$role] = (int) $pdo->lastInsertId();
    }
    $guest = browser();
    foreach (['admin', 'client'] as $role) {
        $response = request($guest, "$role/dashboard.php");
        check($response['status'] === 303 && str_contains($response['headers'], '/login.php'), "$role dashboard redirects guests");
    }
    curl_close($guest);
    $guest = browser();
    $page = request($guest, 'login.php');
    $csrf = token($page);
    check($page['status'] === 200, 'Login page renders');
    check(str_contains($page['headers'], 'HttpOnly') && str_contains($page['headers'], 'SameSite=Lax'), 'Session cookie protections');
    check(request($guest, 'actions/login.php')['status'] === 405, 'Login rejects GET');
    check(request($guest, 'actions/logout.php')['status'] === 405, 'Logout rejects GET');
    check(request($guest, 'actions/login.php', ['email' => "admin.$suffix@example.test", 'password' => $password])['status'] === 403, 'Login rejects missing CSRF');
    request($guest, 'actions/login.php', ['csrf_token' => $csrf, 'email' => "' OR 1=1 --", 'password' => $password]);
    check(request($guest, 'admin/dashboard.php')['status'] === 303, 'Invalid input cannot authenticate');
    $landing = request($guest, 'index.php');
    check($landing['status'] === 200 && str_contains($landing['body'], '>Get Started</a>'), 'Landing page remains public');
    check(str_contains($landing['body'], '>Login</a>') && str_contains($landing['body'], '>Create Account</a>'), 'Guest navigation offers unified login and registration');
    check(!str_contains($landing['body'], 'admin/login.php') && !str_contains($landing['body'], 'client/login.php'), 'No separate role login links');
    foreach (['id="about"', 'id="features"', 'id="process"', 'Upload Documents', 'ELIA Review', 'Track &amp; Notify', 'data-bs-theme="dark"'] as $content) {
        check(str_contains($landing['body'], $content), "Landing preserves $content");
    }
    $registration = request($guest, 'register.php');
    $registrationFields = ['csrf_token' => token($registration), 'full_name' => 'Public Client',
        'email' => $registrationEmail, 'password' => $password, 'password_confirmation' => $password, 'role' => 'admin'];
    check($registration['status'] === 200 && !str_contains($registration['body'], 'name="role"'), 'Registration has no role selector');
    check(request($guest, 'actions/register.php')['status'] === 405, 'Registration rejects GET');
    check(request($guest, 'actions/register.php', array_replace($registrationFields, ['csrf_token' => 'invalid']))['status'] === 403, 'Registration rejects invalid CSRF');
    foreach ([['full_name' => ''], ['email' => 'invalid'], ['password' => 'short'], ['password_confirmation' => 'mismatch'], ['password' => str_repeat('a', 73)], ['email' => ['invalid']]] as $invalidFields) {
        $response = request($guest, 'actions/register.php', array_replace($registrationFields, $invalidFields));
        check($response['status'] === 303 && str_contains($response['headers'], '/register.php'), 'Invalid registration is rejected');
    }
    $statement = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
    $statement->execute(['email' => $registrationEmail]);
    check((int) $statement->fetchColumn() === 0, 'Invalid submissions create no account');
    $response = request($guest, 'actions/register.php', $registrationFields);
    check($response['status'] === 303 && str_contains($response['headers'], '/login.php'), 'Registration redirects to unified login');
    $statement = $pdo->prepare('SELECT * FROM users WHERE email = :email');
    $statement->execute(['email' => $registrationEmail]);
    $registered = $statement->fetch();
    check($registered && $registered['role'] === 'client', 'Forged admin registration still creates a client');
    check(password_verify($password, $registered['password_hash']) && $registered['password_hash'] !== $password, 'Registration hashes the password');
    $response = request($guest, 'actions/register.php', array_replace($registrationFields, ['email' => strtoupper($registrationEmail)]));
    check($response['status'] === 303 && str_contains($response['headers'], '/register.php'), 'Duplicate email is handled safely');
    $page = request($guest, 'login.php');
    $response = request($guest, 'actions/login.php', ['csrf_token' => token($page), 'email' => $registrationEmail, 'password' => $password]);
    check(str_contains($response['headers'], '/client/dashboard.php'), 'Public account can log in as client');
    check(request($guest, 'admin/dashboard.php')['status'] === 403, 'Public account cannot access admin dashboard');
    $response = request($guest, 'actions/register.php', array_replace($registrationFields, ['csrf_token' => token(request($guest, 'client/dashboard.php'))]));
    check($response['status'] === 303 && str_contains($response['headers'], '/client/dashboard.php'), 'Authenticated users cannot register another account');
    foreach (['admin', 'client'] as $role) {
        $handle = browser();
        $page = request($handle, 'login.php');
        $before = curl_getinfo($handle, CURLINFO_COOKIELIST);
        $response = request($handle, 'actions/login.php', ['csrf_token' => token($page),
            'email' => "$role.$suffix@example.test", 'password' => $password, 'role' => 'admin']);
        check($response['status'] === 303 && str_contains($response['headers'], "/$role/dashboard.php"), "$role login redirects correctly");
        check($before !== curl_getinfo($handle, CURLINFO_COOKIELIST), "$role login regenerates session ID");
        $dashboard = request($handle, "$role/dashboard.php");
        check($dashboard['status'] === 200 && str_contains($dashboard['body'], '&lt;script&gt;Test&lt;/script&gt;'), "$role dashboard escapes user output");
        $landing = request($handle, 'index.php');
        check($landing['status'] === 200 && str_contains($landing['body'], "/$role/dashboard.php") && str_contains($landing['body'], '>Logout</button>') && !str_contains($landing['body'], '>Login</a>'), "$role landing shows dashboard and secure logout");
        $otherRole = $role === 'admin' ? 'client' : 'admin';
        check(request($handle, "$otherRole/dashboard.php")['status'] === 403, "$role cannot access $otherRole dashboard");
        check(request($handle, 'actions/logout.php', ['csrf_token' => 'invalid'])['status'] === 403, 'Logout rejects invalid CSRF');
        check(request($handle, "$role/dashboard.php")['status'] === 200, 'Invalid logout preserves session');
        if ($role === 'client') {
            $statement = $pdo->prepare('UPDATE users SET is_active = 0 WHERE id = :id');
            $statement->execute(['id' => $createdIds[$role]]);
            check(request($handle, "$role/dashboard.php")['status'] === 303, 'Disabling an account revokes dashboard access');
        } else {
            check(request($handle, 'actions/logout.php', ['csrf_token' => token($dashboard)])['status'] === 303, 'Valid logout redirects');
            check(request($handle, "$role/dashboard.php")['status'] === 303, 'Logged-out session loses access');
        }
        curl_close($handle);
    }
    $lockBrowser = browser();
    $csrf = token(request($lockBrowser, 'login.php'));
    for ($attempt = 0; $attempt < 5; $attempt++) {
        request($lockBrowser, 'actions/login.php', ['csrf_token' => $csrf, 'email' => "admin.$suffix@example.test", 'password' => 'incorrect-password']);
    }
    request($lockBrowser, 'actions/login.php', ['csrf_token' => $csrf, 'email' => "admin.$suffix@example.test", 'password' => $password]);
    check(request($lockBrowser, 'admin/dashboard.php')['status'] === 303, 'Account cooldown blocks login after five failures');
    $statement = $pdo->prepare('UPDATE users SET locked_until = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE id = :id');
    $statement->execute(['id' => $createdIds['admin']]);
    $response = request($lockBrowser, 'actions/login.php', ['csrf_token' => $csrf, 'email' => "admin.$suffix@example.test", 'password' => $password]);
    check(str_contains($response['headers'], '/admin/dashboard.php'), 'Login succeeds after cooldown expires');
    foreach (['assets/vendor/bootstrap/bootstrap.min.css', 'assets/vendor/bootstrap/bootstrap.bundle.min.js', 'assets/css/app.css', 'assets/css/landing.css', 'assets/css/theme.css', 'assets/img/elia-logo.png', 'assets/img/omsc-building.jpg'] as $asset) {
        check(request($guest, $asset)['status'] === 200, "$asset is available");
    }
    echo "Completed $checks checks.\n";
} finally {
    $statement = $pdo->prepare('DELETE FROM users WHERE email = :email');
    $statement->execute(['email' => $registrationEmail]);
    $statement = $pdo->prepare('DELETE FROM users WHERE id = :id');
    foreach ($createdIds as $id) {
        $statement->execute(['id' => $id]);
    }
}
