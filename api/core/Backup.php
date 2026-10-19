<?php

namespace Core;

use PDO;

/**
 * Writes the whole database as SQL that the mysql client or phpMyAdmin can import, using only
 * PDO. Shared hosts often disable proc_open and exec, so mysqldump can't be started from PHP.
 * Reads from one consistent snapshot, like mysqldump --single-transaction.
 */
class Backup
{
    private const ROWS_PER_INSERT = 200;

    private PDO $db;

    /** Give it a connection of its own: its settings are changed for the dump. */
    public function __construct(PDO $db)
    {
        $this->db = $db;
        // Values as the server's own text, so nothing is rounded on the way through PHP
        $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
        $db->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, true);
    }

    /** @param callable(string): void $write receives the SQL a piece at a time */
    public function dump(callable $write): void
    {
        $this->db->exec("SET time_zone = '+00:00'");
        $this->db->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $this->db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');
        try {
            $write('-- Clairvoyance backup of ' . $this->db->query('SELECT DATABASE()')->fetchColumn() . ', ' . gmdate('Y-m-d H:i') . " UTC\n"
                . "SET NAMES utf8mb4;\nSET time_zone = '+00:00';\nSET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\nSET FOREIGN_KEY_CHECKS = 0;\n");
            $tables = $this->db->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($tables as $table) {
                $this->dumpTable($table, $write);
            }
            $write("SET FOREIGN_KEY_CHECKS = 1;\n");
        } finally {
            $this->db->exec('COMMIT');
        }
    }

    private function dumpTable(string $table, callable $write): void
    {
        $name = $this->quoteName($table);
        $create = $this->db->query("SHOW CREATE TABLE {$name}")->fetch(PDO::FETCH_NUM)[1];
        $write("\nDROP TABLE IF EXISTS {$name};\n{$create};\n");

        // Generated columns are left out: the server computes them again on import
        $columns = $this->db->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND EXTRA NOT LIKE '%GENERATED%'
            ORDER BY ORDINAL_POSITION");
        $columns->execute([$table]);
        $columns = array_map([$this, 'quoteName'], $columns->fetchAll(PDO::FETCH_COLUMN));
        $list = implode(', ', $columns);

        $rows = $this->db->query("SELECT {$list} FROM {$name}", PDO::FETCH_NUM);
        $batch = [];
        foreach ($rows as $row) {
            $batch[] = '(' . implode(', ', array_map(fn($value) => $value === null ? 'NULL' : $this->db->quote($value), $row)) . ')';
            if (count($batch) === self::ROWS_PER_INSERT) {
                $write("INSERT INTO {$name} ({$list}) VALUES\n" . implode(",\n", $batch) . ";\n");
                $batch = [];
            }
        }
        if ($batch) {
            $write("INSERT INTO {$name} ({$list}) VALUES\n" . implode(",\n", $batch) . ";\n");
        }
    }

    private function quoteName(string $name): string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }
}
