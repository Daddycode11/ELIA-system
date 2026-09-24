<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../includes/bootstrap.php';

if ($argc !== 4 || !in_array($argv[1], ['admin', 'client'], true)) {
    fwrite(STDERR, "Usage: php scripts/create-user.php admin|client email \"Full Name\"\nSupply the password through standard input (12–72 bytes).\n");
    exit(1);
}
[, $role, $email, $fullName] = $argv;
$email = strtolower(trim($email));
$fullName = trim($fullName);
fwrite(STDERR, "Password (standard input; terminal input may be visible): ");
$password = rtrim((string) fgets(STDIN), "\r\n");
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254
    || $fullName === '' || mb_strlen($fullName) > 150
    || strlen($password) < 12 || strlen($password) > 72) {
    fwrite(STDERR, "Invalid name, email, or password length (12–72 bytes required).\n");
    exit(1);
}
try {
    $statement = database()->prepare('INSERT INTO users (full_name, email, password_hash, role) VALUES (:name, :email, :hash, :role)');
    $statement->execute(['name' => $fullName, 'email' => $email, 'hash' => password_hash($password, PASSWORD_DEFAULT), 'role' => $role]);
    fwrite(STDOUT, "Account created.\n");
} catch (PDOException $exception) {
    if ($exception->getCode() === '23000') {
        fwrite(STDERR, "An account with that email already exists.\n");
        exit(1);
    }
    throw $exception;
}
