<?php

/**
 * Usage: php scripts/daily.php
 * Run once a day by cron. Backs up the database to storage/backups (keeping the newest 14)
 * and deletes expired sign-in links and old throttling counters. Exits non-zero on failure,
 * so the cron job's email reports it.
 */

if (PHP_SAPI !== 'cli') {
    exit('Command line only.');
}

// Hosts often hide errors on the command line too, which would make a failed run silent
ini_set('display_errors', 'stderr');

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
try {
    $gz = gzopen("{$file}.part", 'wb6');
    if ($gz === false) {
        throw new RuntimeException("could not create {$file}.part");
    }
    (new Core\Backup(Core\Database::connect($env)))->dump(function (string $sql) use ($gz): void {
        if (gzwrite($gz, $sql) !== strlen($sql)) {
            throw new RuntimeException('could not write the file (is the disk full?)');
        }
    });
    if (!gzclose($gz)) {
        throw new RuntimeException('could not finish writing the file');
    }
    rename("{$file}.part", $file);
    echo 'Backed up to ' . basename($file) . ' (' . round(filesize($file) / 1024) . " KB)\n";
} catch (Throwable $e) {
    @unlink("{$file}.part");
    fwrite(STDERR, "Backup failed: {$e->getMessage()}\n");
    $failed = true;
}

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
