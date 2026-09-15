<?php
require_once __DIR__ . '/../app/config.php';
date_default_timezone_set('America/Bogota');
if (session_status() === PHP_SESSION_NONE) session_start();
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', 1);

spl_autoload_register(function ($class) {
    $baseDir = __DIR__ . '/../app/';
    $classPath = str_replace('\\', '/', $class);
    if (strpos($classPath, 'App/') === 0) $classPath = substr($classPath, 4);
    $file = $baseDir . $classPath . '.php';
    if (file_exists($file)) require_once $file;
});

foreach (['Security', 'Audit'] as $h) {
    $f = __DIR__ . '/../app/Helpers/' . $h . '.php';
    if (file_exists($f)) require_once $f;
}

try {
    $db = new PDO('HORION_DSN, HORION_DB_USER, HORION_DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die("❌ Error de conexión a la BD: " . $e->getMessage());
}

$BASE = '/horion-time/public';
$path = trim(parse_url(str_replace($BASE, '', $_SERVER['REQUEST_URI']), PHP_URL_PATH), '/');
if ($path === '' || $path === 'index.php') $path = 'dashboard';

// ============ ARCHIVOS ESTÁTICOS ============
if (preg_match('#^(assets|uploads)/#', $path)) {
    $assetFile = __DIR__ . '/' . $path;
    if (is_file($assetFile)) {
        $ext = strtolower(pathinfo($assetFile, PATHINFO_EXTENSION));
        $mimes = ['css'=>'text/css','js'=>'application/javascript','json'=>'application/json','png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','gif'=>'image/gif','svg'=>'image/svg+xml','ico'=>'image/x-icon','webp'=>'image/webp','woff'=>'font/woff','woff2'=>'font/woff2','ttf'=>'font/ttf'];
        if (isset($mimes[$ext])) { header('Content-Type: ' . $mimes[$ext]); readfile($assetFile); exit; }
    }
    http_response_code(404); die('Recurso no encontrado');
}

// ============ ARCHIVOS .PHP REALES ============
if (substr($path, -4) === '.php' && $path !== 'index.php') {
    $real = __DIR__ . '/' . $path;
    if (file_exists($real)) { require $real; exit; }
    http_response_code(404);
    die("❌ El archivo <b>public/" . htmlspecialchars($path) . "</b> NO existe.");
}

// ============ LOGIN ============
if ($path === 'login') {
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = trim($_POST['email'] ?? '');
        $pass  = $_POST['password'] ?? '';
        $st = $db->prepare("SELECT u.*, r.nombre AS rol FROM usuarios u LEFT JOIN roles r ON r.id = u.rol_id WHERE u.email = :e LIMIT 1");
        $st->execute([':e' => $email]);
        $u = $st->fetch();
        $ok = false;
        if ($u) {
            $hash = (string)($u['password_hash'] ?? '');
            $ok = password_verify($pass, $hash) || hash('sha256', $pass) === $hash || md5($pass) === $hash || $pass === $hash;
        }
        if ($ok && ($u['estado'] ?? 'activo') === 'activo') {
            session_regenerate_id(true);
            $_SESSION['usuario_id']      = (int)$u['id'];
            $_SESSION['nombre_completo'] = $u['nombre_completo'];
            $_SESSION['email']           = $u['email'];
            $_SESSION['rol_nombre']      = $u['rol'] ?? 'Empleado';
            $_SESSION['rol']             = $u['rol'] ?? 'Empleado';
            $_SESSION['empresa_id']      = $u['empresa_id'] !== null ? (int)$u['empresa_id'] : 1;
            if ($_SESSION['rol_nombre'] === 'SuperAdmin') {
                $_SESSION['modo_global'] = true;   // arranca en "Todas las empresas"
                $_SESSION['empresa_nombre'] = 'TODAS LAS EMPRESAS';
                $_SESSION['todas_empresas'] = true;
                $_SESSION['es_plataforma']  = ((int)$_SESSION['empresa_id'] === 1);
                $_SESSION['empresas_lista'] = $db->query("SELECT id, nombre FROM empresas ORDER BY nombre")->fetchAll();
            } else {
                $q = $db->prepare("SELECT nombre FROM empresas WHERE id = ?");
                $q->execute([$_SESSION['empresa_id']]);
                $_SESSION['empresa_nombre'] = $q->fetchColumn() ?: 'Mi Empresa';
                $_SESSION['empresas_lista'] = [];
                $_SESSION['es_plataforma']  = false;
            }
            header('Location: ' . $BASE . '/'); exit;
        }
        $error = 'Credenciales incorrectas o usuario inactivo.';
    }
    ?>
    <!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HORION TIME | Ingreso</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800;900&display=swap" rel="stylesheet">
    <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:Inter,sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;background:radial-gradient(circle at 20% 20%, #0b3d24 0%, #06110c 60%, #000 100%);}
    .card{width:380px;background:rgba(255,255,255,.97);border-radius:24px;padding:40px 34px;box-shadow:0 30px 60px rgba(0,0,0,.5), 0 0 40px rgba(3,169,80,.25);animation:up .45s cubic-bezier(.34,1.56,.64,1);}
    @keyframes up{from{transform:translateY(30px);opacity:0}to{transform:translateY(0);opacity:1}}
    .logo{width:64px;height:64px;margin:0 auto 14px;background:linear-gradient(135deg,#03a950,#028a41);color:#fff;border-radius:18px;display:flex;align-items:center;justify-content:center;font-size:1.7rem;box-shadow:0 8px 24px rgba(3,169,80,.45);}
    h1{text-align:center;font-weight:900;color:#03a950;font-size:1.35rem;}
    p.sub{text-align:center;color:#718096;font-size:.85rem;margin:6px 0 26px;}
    label{display:block;font-size:.8rem;font-weight:700;color:#2d3748;margin:0 0 6px 2px;}
    .inp{position:relative;margin-bottom:16px;}
    .inp i{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#03a950;}
    input{width:100%;padding:13px 14px 13px 42px;border:1.5px solid #e2e8f0;border-radius:12px;font-size:.95rem;outline:none;transition:.2s;}
    input:focus{border-color:#03a950;box-shadow:0 0 0 4px rgba(3,169,80,.18);}
    button{width:100%;padding:14px;border:none;border-radius:12px;cursor:pointer;font-weight:800;font-size:1rem;color:#fff;background:linear-gradient(135deg,#03a950,#028a41);box-shadow:0 8px 20px rgba(3,169,80,.4);transition:.2s;}
    button:hover{transform:translateY(-2px);}
    .err{background:#fee2e2;color:#991b1b;padding:10px 14px;border-radius:10px;font-size:.85rem;margin-bottom:14px;font-weight:600;}
    </style></head><body>
    <form class="card" method="POST" action="<?= $BASE ?>/login">
        <div class="logo"><i class="fas fa-clock"></i></div>
        <h1>HORION TIME</h1>
        <p class="sub">Control de Tiempo y Asistencia</p>
        <?php if ($error): ?><div class="err"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
        <label>Correo electrónico</label>
        <div class="inp"><i class="fas fa-envelope"></i><input type="email" name="email" required autofocus></div>
        <label>Contraseña</label>
        <div class="inp"><i class="fas fa-lock"></i><input type="password" name="password" required></div>
        <button type="submit"><i class="fas fa-sign-in-alt"></i> INGRESAR</button>
    </form>
    </body></html>
    <?php
    exit;
}

// ============ LOGOUT ============
if ($path === 'logout') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    header('Location: ' . $BASE . '/login'); exit;
}

// ============ CAMBIAR EMPRESA (SuperAdmin) ============
if ($path === 'cambiarEmpresa') {
    if (($_SESSION['rol_nombre'] ?? '') === 'SuperAdmin') {
        $id = (int)($_GET['id'] ?? 0);
        if ($id === 0) {
            $_SESSION['modo_global']   = true;
            $_SESSION['empresa_id']    = null;
            $_SESSION['empresa_nombre'] = 'TODAS LAS EMPRESAS';
        } else {
            $q = $db->prepare("SELECT nombre FROM empresas WHERE id = ?");
            $q->execute([$id]);
            $n = $q->fetchColumn();
            if ($n) {
                $_SESSION['modo_global']   = false;
                $_SESSION['empresa_id']    = $id;
                $_SESSION['empresa_nombre'] = $n;
            }
        }
    }
    // Volver al módulo actual (referer), NO al dashboard
    $volver = $_SERVER['HTTP_REFERER'] ?? '';
    if ($volver === '' || strpos($volver, $BASE) !== 0) $volver = $BASE . '/';
    header('Location: ' . $volver);
    exit;
}

// ============ GUARDIA DE SESIÓN ============
if (!isset($_SESSION['usuario_id'])) { header('Location: ' . $BASE . '/login'); exit; }

// ============ NUEVO: BLOQUEO DE MÓDULOS POR ROL ============
$permisosRol = [
    'SuperAdmin'    => '*',
    'Admin_Empresa' => '*',
    'RRHH'          => ['dashboard','usuarios','asistencia','horarios','novedades','horas_extras','reportes','notificaciones','portal','jefes','empresas'],
    'Supervisor'    => ['dashboard','supervisor','asistencia','horarios','novedades','horas_extras','portal','notificaciones'],
    'Auditor'       => ['dashboard','reportes','auditoria','portal'],
    'Contador'      => ['dashboard','reportes','horas_extras','portal'],
    'Empleado'      => ['dashboard','asistencia','portal','horarios','novedades','horas_extras'],
];
$parts = explode('/', $path);
$clave = $parts[0];
if ($clave === 'sucursales') $clave = 'empresas';
$listaRol = $permisosRol[$_SESSION['rol_nombre'] ?? 'Empleado'] ?? $permisosRol['Empleado'];
if ($listaRol !== '*' && !in_array($clave, $listaRol, true)) {
    header('Location: ' . $BASE . '/?error=acceso_denegado'); exit;
}

// ============ ENRUTADO DE MÓDULOS ============
$controllerName = ucfirst($parts[0]) . 'Controller';
$method = $parts[1] ?? 'index';
$params = array_slice($parts, 2);
if ($parts[0] === 'jefes' && $method === 'index') $method = 'jefes';

$map = [
    'DashboardController'      => 'App\\Controllers\\DashboardController',
    'EmpresasController'       => 'App\\Controllers\\EmpresaController',
    'SucursalesController'     => 'App\\Controllers\\EmpresaController',
    'JefesController'          => 'App\\Controllers\\UsuarioController',
    'UsuariosController'       => 'App\\Controllers\\UsuarioController',
    'AsistenciaController'     => 'App\\Controllers\\AsistenciaController',
    'HorariosController'       => 'App\\Controllers\\HorarioController',
    'NovedadesController'      => 'App\\Controllers\\NovedadController',
    'Horas_extrasController'   => 'App\\Controllers\\HoraExtraController',
    'HorasextrasController'    => 'App\\Controllers\\HoraExtraController',
    'ReportesController'       => 'App\\Controllers\\ReporteController',
    'AuditoriaController'      => 'App\\Controllers\\AuditoriaController',
    'NotificacionesController' => 'App\\Controllers\\NotificacionController',
    'PortalController'         => 'App\\Controllers\\PortalController',
    'SupervisorController'     => 'App\\Controllers\\SupervisorController',
    'ConfiguracionController'  => 'App\\Controllers\\ConfiguracionController',
    'DispositivosController'   => 'App\\Controllers\\DispositivoController',
    'KioscoController'         => 'App\\Controllers\\KioscoController',
];

if (isset($map[$controllerName])) {
    try {
        $class = $map[$controllerName];
        if (!class_exists($class)) throw new Exception("No existe la clase $class");
        $GLOBALS['currentPage'] = $parts[0];
        $GLOBALS['pageTitle'] = ucfirst(str_replace('_', ' ', $parts[0]));
        $controller = new $class($db);
        if (method_exists($controller, $method)) {
            call_user_func_array([$controller, $method], $params);
        } else {
            $controller->index();
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo "<div style='font-family:Inter,sans-serif;padding:40px;background:#fee2e2;border-left:4px solid #e53935;border-radius:8px;margin:20px;'>
            <h2 style='color:#991b1b;'>❌ Error en el módulo</h2>
            <p><strong>Mensaje:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
            <p><strong>En:</strong> " . $e->getFile() . ":" . $e->getLine() . "</p>
            <a href='$BASE/' style='color:#03a950;'>← Volver al Dashboard</a></div>";
    }
} else {
    http_response_code(404);
    echo "<div style='font-family:Inter,sans-serif;padding:40px;text-align:center;'>
        <h2>❌ Módulo no encontrado: " . htmlspecialchars($controllerName) . "</h2>
        <a href='$BASE/' style='color:#03a950;'>← Volver al Dashboard</a></div>";
}