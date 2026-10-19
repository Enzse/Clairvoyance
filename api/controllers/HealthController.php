<?php

namespace Controllers;

use PDO;

/** GET /health for uptime monitoring: answers only if PHP and the database both work. */
class HealthController
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function check(): array
    {
        $this->db->query('SELECT 1')->fetchColumn();
        return ['ok' => true, 'time' => gmdate('c')];
    }
}
