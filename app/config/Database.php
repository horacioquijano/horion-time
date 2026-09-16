<?php
namespace App\Config;
use PDO;
use PDOException;

class Database {
    private static $instance = null;
    private $connection;
    
    private $host = 'localhost';
    private $dbname = 'horion_time';
    private $username = 'root';
    private $password = '';
    private $charset = 'utf8mb4';
    
    private function __construct() {
        // === RAILWAY: leer variables de entorno si existen ===
        if (getenv('MYSQL_HOST'))     $this->host     = getenv('MYSQL_HOST');
        if (getenv('MYSQL_PORT'))     $this->port     = getenv('MYSQL_PORT');
        if (getenv('MYSQL_DATABASE')) $this->dbname   = getenv('MYSQL_DATABASE');
        if (getenv('MYSQL_USER'))     $this->username = getenv('MYSQL_USER');
        if (getenv('MYSQL_PASSWORD')) $this->password = getenv('MYSQL_PASSWORD');
        // =====================================================

        try {
            $dsn = "mysql:host={$this->host}";
            if (!empty($this->port)) $dsn .= ";port={$this->port}";
            $dsn .= ";dbname={$this->dbname};charset={$this->charset}";

            $this->connection = new PDO(
                $dsn,
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            die("Error de conexión: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
}
