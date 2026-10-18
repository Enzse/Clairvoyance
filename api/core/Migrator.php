<?php

namespace Core;

use PDO;

/**
 * Runs migrations/*.sql in order, once each, recording them in the migrations table.
 */
class Migrator
{
    private PDO $db;
    private string $dir;
    /** @var callable(string): void */
    private $log;

    public function __construct(PDO $db, ?callable $log = null)
    {
        $this->db = $db;
        $this->dir = __DIR__ . '/../migrations';
        $this->log = $log ?? function (string $line): void {
        };
    }

    /** Create the database if it doesn't exist yet (connects without selecting one). */
    public static function ensureDatabase(Environment $env): void
    {
        $name = str_replace('`', '', $env->get('DB_NAME'));
        Database::connect($env, false)->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }

    public function dropAllTables(): void
    {
        $tables = $this->db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        $this->db->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($tables as $table) {
            $this->db->exec('DROP TABLE `' . str_replace('`', '', $table) . '`');
            ($this->log)("Dropped {$table}");
        }
        $this->db->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function migrate(): void
    {
        $this->db->exec(file_get_contents("{$this->dir}/000_create_migration_tracking.sql"));
        $done = $this->db->query('SELECT migration_name FROM migrations')->fetchAll(PDO::FETCH_COLUMN);

        $files = glob("{$this->dir}/*.sql");
        sort($files);
        foreach ($files as $file) {
            $name = basename($file);
            if ($name === '000_create_migration_tracking.sql' || in_array($name, $done, true)) {
                continue;
            }
            $this->db->exec(file_get_contents($file));
            $this->db->prepare('INSERT INTO migrations (migration_name) VALUES (?)')->execute([$name]);
            ($this->log)("Ran {$name}");
        }
    }
}
