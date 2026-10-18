<?php

/**
 * Usage: php scripts/set_role.php <email> <admin|member>
 * Recovery tool, e.g. if the only admin account is lost.
 */

if (PHP_SAPI !== 'cli') {
    exit('Command line only.');
}

require_once __DIR__ . '/../vendor/autoload.php';

[, $email, $role] = $argv + [null, null, null];
if (!$email || !in_array($role, ['admin', 'member'], true)) {
    fwrite(STDERR, "Usage: php scripts/set_role.php <email> <admin|member>\n");
    exit(1);
}

$users = new Repositories\UserRepository(Core\Database::connect(Core\Environment::getInstance()));
$user = $users->findByEmail($email);
if (!$user) {
    fwrite(STDERR, "No user with email {$email}\n");
    exit(1);
}
$users->update($user['id'], ['role' => $role]);
echo "{$email} is now {$role}.\n";
