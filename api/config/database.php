<?php

require_once __DIR__ . '/environment.php';

//set default time zone
date_default_timezone_set("Asia/Manila");

//set time limit of requests
set_time_limit(1000);

// Load environment variables
$env = Environment::getInstance();

// Define database connection parameters
define("SERVER", $env->get('DB_HOST', 'localhost'));
define("DATABASE", $env->get('DB_NAME', 'clairvoyance_v3'));
define("USER", $env->get('DB_USER', 'root'));
define("PASSWORD", $env->get('DB_PASS', ''));
define("DRIVER", "mysql");

class Connection
{
    private $connectionString = DRIVER . ":host=" . SERVER . ";dbname=" . DATABASE . "; charset=utf8mb4";
    private $options = [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        \PDO::ATTR_EMULATE_PREPARES => false
    ];

    /**
     * Connect to the database
     * 
     * @return \PDO PDO connection
     */
    public function connect()
    {
        try {
            return new \PDO($this->connectionString, USER, PASSWORD, $this->options);
        } catch (\PDOException $e) {
            error_log("Database connection error: " . $e->getMessage());
            throw new \Exception("Database connection failed: " . $e->getMessage());
        }
    }
}

?>