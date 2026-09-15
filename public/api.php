<?php
/**
 * HORION TIME - API REST Router
 * Punto de entrada único para todas las peticiones API
 */

header('Content-Type: application/json; charset=utf-8');
error_reporting(0); // No mostrar errores en producción

require_once __DIR__ . '/../app/Config/Database.php';
require_once __DIR__ . '/../app/Helpers/JWT.php';
require_once __DIR__ . '/../app/Middleware/ApiAuth.php';
require_once __DIR__ . '/../app/Middleware/ApiRateLimit.php';
require_once __DIR__ . '/../app/Middleware/ApiCors.php';

// Aplicar CORS
App\Middleware\ApiCors::handle();

// Conexión DB
try {
    $db = new PDO('HORION_DSN, HORION_DB_USER, HORION_DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    App\Middleware\ApiAuth::sendError(500, 'Error de conexión a la base de datos');
}

// Obtener ruta
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);
$path = preg_replace('#^/api#', '', $path);
$method = $_SERVER['REQUEST_METHOD'];

// Autoload
spl_autoload_register(function ($class) {
    $baseDir = __DIR__ . '/../app/';
    $file = $baseDir . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) require_once $file;
});

// ============ RUTAS PÚBLICAS (Sin autenticación) ============
$publicRoutes = [
    'POST /auth/login' => function() use ($db) {
        $ctrl = new App\Controllers\Api\AuthApiController($db);
        $ctrl->login();
    },
    'POST /auth/registro-marcacion' => function() use ($db) {
        // Endpoint especial: puede recibir token de API o JWT
        $auth = App\Middleware\ApiAuth::authenticate($db);
        App\Middleware\ApiRateLimit::check($db, $_SERVER['REMOTE_ADDR'] ?? 'unknown', '/auth/registro-marcacion', 'POST');
        $ctrl = new App\Controllers\Api\AsistenciaApiController($db);
        $ctrl->registrarMarcacion($auth);
    }
];

// ============ RUTAS PROTEGIDAS (Requieren autenticación) ============
$protectedRoutes = [
    'POST /auth/refresh' => function($auth) use ($db) {
        $ctrl = new App\Controllers\Api\AuthApiController($db);
        $ctrl->refresh();
    },
    'GET /usuarios/{id}/asistencia' => function($auth, $params) use ($db) {
        App\Middleware\ApiRateLimit::check($db, $auth['usuario_id'] ?? 'anon', '/usuarios/asistencia', 'GET');
        $ctrl = new App\Controllers\Api\AsistenciaApiController($db);
        $ctrl->getAsistenciaUsuario($auth, $params['id']);
    },
    'GET /reportes/asistencia' => function($auth) use ($db) {
        App\Middleware\ApiRateLimit::check($db, $auth['usuario_id'] ?? 'anon', '/reportes/asistencia', 'GET');
        $ctrl = new App\Controllers\Api\ReportesApiController($db);
        $ctrl->getAsistencia($auth);
    },
    'POST /novedades/solicitar' => function($auth) use ($db) {
        // Implementar según Módulo 5
        App\Middleware\ApiAuth::sendError(501, 'Endpoint en desarrollo');
    },
    'POST /horas-extras/solicitar' => function($auth) use ($db) {
        App\Middleware\ApiAuth::sendError(501, 'Endpoint en desarrollo');
    },
    'GET /turnos/semanal' => function($auth) use ($db) {
        App\Middleware\ApiAuth::sendError(501, 'Endpoint en desarrollo');
    }
];

// ============ ENRUTADOR ============
function matchRoute($path, $method, $routes) {
    $key = "$method $path";
    if (isset($routes[$key])) return [$routes[$key], []];

    // Rutas con parámetros
    foreach ($routes as $pattern => $handler) {
        $patternRegex = preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $pattern);
        $patternRegex = "#^$patternRegex$#";
        if (preg_match($patternRegex, "$method $path", $matches)) {
            return [$handler, $matches];
        }
    }
    return null;
}

// Intentar ruta pública
$route = matchRoute($path, $method, $publicRoutes);
if ($route) {
    $handler = $route[0];
    $handler();
    exit;
}

// Rutas protegidas: autenticar primero
try {
    $auth = App\Middleware\ApiAuth::authenticate($db);
} catch (\Exception $e) {
    App\Middleware\ApiAuth::sendError(401, $e->getMessage());
}

$route = matchRoute($path, $method, $protectedRoutes);
if ($route) {
    [$handler, $params] = $route;
    $handler($auth, $params);
    exit;
}

// 404
App\Middleware\ApiAuth::sendError(404, 'Endpoint no encontrado', [
    'metodo' => $method,
    'path' => $path,
    'endpoints_disponibles' => array_keys(array_merge($publicRoutes, $protectedRoutes))
]);