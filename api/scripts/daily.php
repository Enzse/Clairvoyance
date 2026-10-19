<?php

/**
 * Usage: php scripts/daily.php
 * Run once a day by cron. Backs up the database to storage/backups (keeping the newest 14)
 * and deletes expired sign-in links and old throttling counters. Exits non-zero on failure,
 * so the cron job's email reports it.
 *
 * Needs mysqldump on the PATH; set MYSQLDUMP in .env to use another path
 * (e.g. C:\xampp\mysql\bin\mysqldump.exe).
 */

if (PHP_SAPI !== 'cli') {
    exit('Command line only.');
}

require_once __DIR__ . '/../autoload.php';

const KEEP_BACKUPS = 14;

$env = Core\Environment::getInstance();
$db = Core\Database::connect($env);
$failed = false;

// 1. Backup
$dir = __DIR__ . '/../storage/backups';
if (!is_dir($dir)) {
    mkdir($dir, 0700, true);
}
$name = $env->get('DB_NAME');
$file = "{$dir}/{$name}-" . gmdate('Y-m-d') . '.sql.gz';
// Credentials go in a private options file, never on the command line where other users can see them
$options = tempnam($dir, 'my');
chmod($options, 0600);
$quote = fn(?string $value) => '"' . addcslashes((string)$value, '"\\') . '"';
file_put_contents($options, "[client]\nhost={$quote($env->get('DB_HOST'))}\nuser={$quote($env->get('DB_USER'))}\npassword={$quote($env->get('DB_PASS'))}\n");

$dump = proc_open(
    [$env->get('MYSQLDUMP', 'mysqldump'), "--defaults-extra-file={$options}", '--single-transaction', '--no-tablespaces', '--routines', $name],
    [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
    $pipes
);
if (!is_resource($dump)) {
    fwrite(STDERR, "Backup failed: could not run mysqldump\n");
    $failed = true;
} else {
    $gz = gzopen("{$file}.part", 'wb6');
    while (!feof($pipes[1])) {
        gzwrite($gz, fread($pipes[1], 1 << 16));
    }
    gzclose($gz);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($dump) !== 0) {
        fwrite(STDERR, "Backup failed: {$errors}\n");
        unlink("{$file}.part");
        $failed = true;
    } else {
        rename("{$file}.part", $file);
        echo 'Backed up to ' . basename($file) . ' (' . round(filesize($file) / 1024) . " KB)\n";
    }
}
unlink($options);

$backups = glob("{$dir}/{$name}-*.sql.gz");
rsort($backups);
foreach (array_slice($backups, KEEP_BACKUPS) as $old) {
    unlink($old);
}

// 2. Tidy up
$tokens = (new Repositories\UserTokenRepository($db))->deleteExpired();
$stmt = $db->prepare('DELETE FROM rate_limits WHERE window_start < ?');
$stmt->execute([time() - 86400]);
echo "Removed {$tokens} used or expired links and {$stmt->rowCount()} old throttling counters\n";

exit($failed ? 1 : 0);
