<?php

/**
 * Usage: php scripts/set_role.php <email> <admin|member> [organization-id]
 * Recovery tool, e.g. if an organisation lost its only admin. The organisation id can be left
 * out when the person belongs to just one.
 */

if (PHP_SAPI !== 'cli') {
    exit('Command line only.');
}

require_once __DIR__ . '/../vendor/autoload.php';

[, $email, $role, $orgId] = $argv + [null, null, null, null];
if (!$email || !in_array($role, ['admin', 'member'], true)) {
    fwrite(STDERR, "Usage: php scripts/set_role.php <email> <admin|member> [organization-id]\n");
    exit(1);
}

$db = Core\Database::connect(Core\Environment::getInstance());
$user = (new Repositories\UserRepository($db))->findByEmail($email);
if (!$user) {
    fwrite(STDERR, "No user with email {$email}\n");
    exit(1);
}

$organizations = new Repositories\OrganizationRepository($db);
$theirs = $organizations->forUser($user['id']);
if ($orgId === null) {
    if (count($theirs) !== 1) {
        fwrite(STDERR, "{$email} belongs to " . count($theirs) . " organisations; add the organisation id:\n");
        foreach ($theirs as $o) {
            fwrite(STDERR, "  {$o['id']}  {$o['name']} ({$o['role']})\n");
        }
        exit(1);
    }
    $orgId = $theirs[0]['id'];
}

if (!$organizations->roleOf((int)$orgId, $user['id'])) {
    fwrite(STDERR, "{$email} is not in organisation {$orgId}\n");
    exit(1);
}
$organizations->setRole((int)$orgId, $user['id'], $role);
echo "{$email} is now {$role} of " . $organizations->find((int)$orgId)['name'] . ".\n";
