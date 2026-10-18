<?php

/**
 * Usage: php scripts/reset_password.php <email>
 * Sets a new random password for the account and prints it. For when a password is
 * forgotten; change it afterwards under Admin › People.
 */

if (PHP_SAPI !== 'cli') {
    exit('Command line only.');
}

require_once __DIR__ . '/../vendor/autoload.php';

$email = $argv[1] ?? null;
if (!$email) {
    fwrite(STDERR, "Usage: php scripts/reset_password.php <email>\n");
    exit(1);
}

$users = new Repositories\UserRepository(Core\Database::connect(Core\Environment::getInstance()));
$user = $users->findByEmail($email);
if (!$user) {
    fwrite(STDERR, "No user with email {$email}\n");
    exit(1);
}

// 12 characters from an alphabet without look-alikes (no 0/O, 1/l/I)
$alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
$password = '';
for ($i = 0; $i < 12; $i++) {
    $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
}

$users->update($user['id'], ['password' => $password]);
echo "New password for {$email} ({$user['role']}): {$password}\n";
