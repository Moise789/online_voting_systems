<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

$name = trim((string) getenv('ADMIN_INITIAL_NAME'));
$email = trim((string) getenv('ADMIN_INITIAL_EMAIL'));
$password = (string) getenv('ADMIN_INITIAL_PASSWORD');

if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12 || strlen($password) > 72) {
    fwrite(STDERR, "Set ADMIN_INITIAL_NAME, ADMIN_INITIAL_EMAIL, and ADMIN_INITIAL_PASSWORD (12 to 72 bytes) in the current terminal environment.\n");
    exit(2);
}

$pdo = pdo();
if ((int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn() !== 0) {
    fwrite(STDERR, "A Main Admin account already exists. The initial account cannot be duplicated.\n");
    exit(3);
}
$check = $pdo->prepare('SELECT COUNT(*) FROM voters WHERE email_normalized = ?');
$check->execute([normalize_email($email)]);
if ((int) $check->fetchColumn() !== 0) {
    fwrite(STDERR, "That email is already associated with a voter account.\n");
    exit(4);
}
$history = $pdo->prepare('SELECT voter_id FROM voter_identifiers WHERE identifier_type = ? AND identifier_hmac = ?');
$history->execute(['email', identifier_hash('email', normalize_email($email))]);
if ($history->fetchColumn()) {
    fwrite(STDERR, "That email is reserved to a voter application.\n");
    exit(4);
}

$stmt = $pdo->prepare('INSERT INTO admins (singleton_key, full_name, email, email_normalized, password_hash) VALUES (1, ?, ?, ?, ?)');
$stmt->execute([$name, $email, normalize_email($email), password_hash($password, PASSWORD_DEFAULT)]);
fwrite(STDOUT, "Initial Main Admin created successfully for {$email}.\n");
