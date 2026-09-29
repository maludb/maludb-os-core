<?php
declare(strict_types=1);

/**
 * Bootstrap the first organizer. Invite-only registration has a chicken-and-egg problem:
 * nobody can send the first invite until an organizer exists. Run this once per install.
 *
 *   php bin/bootstrap_organizer.php --email you@example.com --name "Your Name" [--password secret]
 *
 * If --password is omitted, a strong random password is generated and printed once.
 * Refuses if a member with that email already exists.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

require_once dirname(__DIR__) . '/app/bootstrap.php';

$opts = getopt('', ['email:', 'name:', 'password:']);
$email = isset($opts['email']) ? normalize_email((string) $opts['email']) : '';
$name  = trim((string) ($opts['name'] ?? ''));
$password = (string) ($opts['password'] ?? '');

if ($email === '' || $name === '') {
    fwrite(STDERR, "Usage: php bin/bootstrap_organizer.php --email you@example.com --name \"Your Name\" [--password secret]\n");
    exit(1);
}
if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    fwrite(STDERR, "Invalid email.\n");
    exit(1);
}

$generated = false;
if ($password === '') {
    $password = bin2hex(random_bytes(9));   // 18 hex chars
    $generated = true;
}
$pwErrors = validate_password($password);
if ($pwErrors !== []) {
    fwrite(STDERR, implode("\n", $pwErrors) . "\n");
    exit(1);
}

$pdo = db();
if (find_member_by_email($pdo, $email) !== null) {
    fwrite(STDERR, "A member already exists for {$email}.\n");
    exit(1);
}

$st = $pdo->prepare(<<<'SQL'
    INSERT INTO members (email, password_hash, display_name, role, email_verified_at)
    VALUES (:e, :h, :n, 'organizer', now())
    RETURNING id
SQL);
$st->execute(['e' => $email, 'h' => hash_password($password), 'n' => $name]);
$id = (int) $st->fetchColumn();

log_activity($pdo, 'member.bootstrap_organizer', 'member', $id, ['source' => 'cron', 'after' => ['email' => $email]]);

echo "Created organizer #{$id}: {$name} <{$email}>\n";
if ($generated) {
    echo "Generated password (store it now, shown once): {$password}\n";
}
echo "Log in at " . app_url('/login.php') . "\n";
