<?php
/**
 * Bootstrap para tests
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Autoload
spl_autoload_register(function ($class) {
    $baseDir = __DIR__ . '/../app/';
    $file = $baseDir . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) require_once $file;
});

// DB de prueba
$db = new PDO('mysql:host=localhost;dbname=horion_time_test;charset=utf8mb4', 'root', '');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);