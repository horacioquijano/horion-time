<?php
// MODO DIAGNÓSTICO: los errores reales se ven en el navegador
error_reporting(E_ALL);
ini_set('display_errors', '1');

$uri  = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$file = __DIR__ . $uri;

// Archivos reales (css, js, imágenes, uploads)
if ($uri !== '/' && is_file($file)) {
    return false;
}

// Raíz → a la app
if ($uri === '/' || $uri === '') {
    header('Location: /horion-time/public/');
    exit;
}

// Todo /horion-time/public/* → front controller
if (strpos($uri, '/horion-time/public') === 0) {
    $config = __DIR__ . '/app/config.php';
    $front  = __DIR__ . '/horion-time/public/index.php';

    if (!is_file($front)) {
        http_response_code(500);
        exit('DIAGNOSTICO: FALTA horion-time/public/index.php en el contenedor');
    }
    if (!is_file($config)) {
        http_response_code(500);
        exit('DIAGNOSTICO: FALTA app/config.php en el contenedor');
    }

    $_SERVER['SCRIPT_NAME'] = '/horion-time/public/index.php';
    $_SERVER['PHP_SELF']    = '/horion-time/public/index.php';
    try {
        require $front;
    } catch (\Throwable $e) {
        http_response_code(500);
        echo 'DIAGNOSTICO APP: ' . get_class($e) . ': ' . htmlspecialchars($e->getMessage());
    }
    exit;
}

http_response_code(404);
echo '404 Not Found';
