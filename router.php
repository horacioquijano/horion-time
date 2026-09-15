<?php
// Servidor PHP integrado: enruta todo sin Apache ni .htaccess
$uri  = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$file = __DIR__ . $uri;

// Archivos reales (css, js, imágenes, uploads) los sirve PHP directamente
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
    $_SERVER['SCRIPT_NAME'] = '/horion-time/public/index.php';
    $_SERVER['PHP_SELF']    = '/horion-time/public/index.php';
    require __DIR__ . '/horion-time/public/index.php';
    exit;
}

http_response_code(404);
echo '404 Not Found';
