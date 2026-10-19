<?php

/**
 * Usage: php scripts/migrate.php [--fresh]
 *   --fresh  drop every table in the database first (all data is lost)
 */

if (PHP_SAPI !== 'cli') {
    exit('Command line only.');
}

require_once __DIR__ . '/../autoload.php';

use Core\Database;
use Core\Environment;
use Core\Migrator;

$env = Environment::getInstance();
$log = fn(string $line) => print("  {$line}\n");

try {
    Migrator::ensureDatabase($env);
    $migrator = new Migrator(Database::connect($env), $log);
    if (in_array('--fresh', $argv, true)) {
        echo "Dropping all tables in {$env->get('DB_NAME')}...\n";
        $migrator->dropAllTables();
    }
    $migrator->migrate();
    echo "Migrations complete.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Migration failed: {$e->getMessage()}\n");
    exit(1);
}
