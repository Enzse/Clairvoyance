<?php

namespace Core;

use PDO;

class Database
{
    public static function connect(Environment $env, bool $selectDatabase = true): PDO
    {
        $dsn = 'mysql:host=' . $env->get('DB_HOST') . ';charset=utf8mb4';
        if ($selectDatabase) {
            $dsn .= ';dbname=' . $env->get('DB_NAME');
        }

        return new PDO($dsn, $env->get('DB_USER'), $env->get('DB_PASS'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
}
