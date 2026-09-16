<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

$uri  = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$file = __DIR__ . $uri;

// Archivos estáticos reales
if ($uri !== '/' && is_file($file)) {
    return false;
}

// Raíz → redirige
if ($uri === '/' || $uri === '') {
    header('Location: /horion-time/public/');
    exit;
}

// Mapeo: /horion-time/public/* → /var/www/html/public/index.php
if (strpos($uri, '/horion-time/public') === 0) {
    $front = __DIR__ . '/public/index.php';  // ← CAMBIO CLAVE: sin "horion-time"
    
    if (!is_file($front)) {
        http_response_code(500);
        exit("DIAGNOSTICO: No existe /var/www/html/public/index.php en el contenedor");
    }
    
    $_SERVER['SCRIPT_NAME'] = '/horion-time/public/index.php';
    $_SERVER['PHP_SELF']    = '/horion-time/public/index.php';
    
    try {
        require $front;
    } catch (\Throwable $e) {
        http_response_code(500);
        echo 'ERROR APP: ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine();
    }
    exit;
}

http_response_code(404);
echo '404 - Ruta no encontrada: ' . htmlspecialchars($uri);
