<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$name = trim((string) readline('Administrator name: '));
$email = strtolower(trim((string) readline('Administrator email: ')));
$password = getenv('STUDENTHUB_ADMIN_PASSWORD');

if ($name === '' || strlen($name) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254
    || $password === false || !password_meets_policy($password, 12)) {
    fwrite(STDERR, "Enter a valid name and email and set STUDENTHUB_ADMIN_PASSWORD to 12-72 characters with uppercase, lowercase, a number, and a special character.\n");
    exit(1);
}

try {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $statement = portal_db()->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'admin')");
    $statement->bind_param('sss', $name, $email, $hash);
    $statement->execute();
    fwrite(STDOUT, "Administrator account created.\n");
} catch (mysqli_sql_exception $error) {
    error_log('StudentHub admin creation failed: ' . $error->getMessage());
    fwrite(STDERR, $error->getCode() === 1062 ? "That email is already registered.\n" : "Could not create administrator; check database settings.\n");
    exit(1);
}
