<?php
/**
 * Configuración central de BD.
 * En XAMPP usa localhost; en Railway lee las variables del servicio MySQL.
 */
if (!defined('HORION_DSN')) {
    $host = getenv('MYSQL_HOST') ?: (getenv('MYSQLHOST') ?: 'localhost');
    $port = getenv('MYSQL_PORT') ?: (getenv('MYSQLPORT') ?: '3306');
    $db   = getenv('MYSQL_DATABASE') ?: (getenv('MYSQLDATABASE') ?: 'horion_time');

    define('HORION_DSN', "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4");
    define('HORION_DB_USER', getenv('MYSQL_USER') ?: (getenv('MYSQLUSER') ?: 'root'));
    define('HORION_DB_PASS', (string)(getenv('MYSQL_PASSWORD') ?: (getenv('MYSQLPASSWORD') ?: '')));
}