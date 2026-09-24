<?php
declare(strict_types=1);

function registration_errors(string $name, string $email, string $password, string $confirmation): array
{
    $errors = [];
    if ($name === '' || !mb_check_encoding($name, 'UTF-8') || mb_strlen($name) > 150 || preg_match('/[\x00-\x1F\x7F]/u', $name)) {
        $errors[] = 'Enter your full name (up to 150 characters).';
    }
    if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }
    if (strlen($password) < 12 || strlen($password) > 72 || str_contains($password, "\0")) {
        $errors[] = 'Use a password of 12–72 bytes (at least 12 characters for plain English text).';
    }
    if ($password !== $confirmation) {
        $errors[] = 'The passwords do not match.';
    }
    return $errors;
}

function register_client(string $name, string $email, string $password): bool
{
    try {
        // The public account role is a SQL literal, never a form parameter.
        $statement = database()->prepare("INSERT INTO users (full_name, email, password_hash, role) VALUES (:name, :email, :hash, 'client')");
        $statement->execute(['name' => $name, 'email' => $email, 'hash' => password_hash($password, PASSWORD_DEFAULT)]);
        return true;
    } catch (PDOException $exception) {
        // The unique email index also protects concurrent registration attempts.
        if (($exception->errorInfo[1] ?? null) === 1062) {
            return false;
        }
        throw $exception;
    }
}
