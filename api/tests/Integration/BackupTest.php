<?php

namespace Tests\Integration;

use Core\Backup;
use Core\Database;
use Core\Environment;
use Core\Migrator;
use PDO;

/**
 * The daily backup is written in PHP (hosts disable proc_open, so no mysqldump). Restoring it
 * must give back exactly the same database.
 */
class BackupTest extends ApiTestCase
{
    public function testRestoringABackupGivesBackTheSameData(): void
    {
        $this->registerAndLogin('backup@example.com', "O'Brien \"Academy\" \\ ; ✓\nAnnex");
        $unit = $this->createUnit($this->rootId(), 'Ñiño — 100%');
        $kpi = $this->createKpi(['code' => 'enrolment', 'name' => 'Enrolment']);
        // A default target (unit_id NULL) fills the generated unit_key column
        $this->ok('PUT', "/kpis/{$kpi['id']}/targets", [], ['targets' => [
            ['unit_id' => null, 'effective_from' => '2025-01-01', 'value' => 10.125],
            ['unit_id' => $unit, 'effective_from' => '2025-01-01', 'value' => 12],
        ]]);
        $before = $this->everything();

        $sql = '';
        (new Backup(Database::connect(Environment::getInstance())))->dump(function (string $piece) use (&$sql): void {
            $sql .= $piece;
        });
        (new Migrator(self::$db))->dropAllTables();
        // A connection of its own, so the backup's session settings don't leak into other tests
        Database::connect(Environment::getInstance())->exec($sql);

        $this->assertSame($before, $this->everything());
    }

    /** Every row of every table, in a stable order */
    private function everything(): array
    {
        $tables = [];
        foreach (self::$db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
            $rows = array_map('json_encode', self::$db->query("SELECT * FROM `{$table}`")->fetchAll());
            sort($rows);
            $tables[$table] = $rows;
        }
        ksort($tables);
        return $tables;
    }
}
