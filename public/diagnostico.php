<?php
date_default_timezone_set('America/Bogota');
ini_set('display_errors', 1);
error_reporting(E_ALL);
$ok = 0; $fail = 0; $results = [];
function check($name, $cond, $okMsg = 'OK', $failMsg = 'FALLO') {
    global $ok, $fail, $results;
    $results[] = ['name' => $name, 'ok' => $cond, 'msg' => $cond ? $okMsg : $failMsg];
    $cond ? $ok++ : $fail++;
}
$base = dirname(__DIR__);

// 1. Archivos críticos
$files = [
    'public/index.php' => __DIR__ . '/index.php',
    'public/.htaccess' => __DIR__ . '/.htaccess',
    'public/assets/css/horion-style.css' => __DIR__ . '/assets/css/horion-style.css',
    'public/assets/js/horion-app.js' => __DIR__ . '/assets/js/horion-app.js',
    'app/Controllers/DashboardController.php' => $base . '/app/Controllers/DashboardController.php',
    'app/Controllers/EmpresaController.php' => $base . '/app/Controllers/EmpresaController.php',
    'app/Controllers/UsuarioController.php' => $base . '/app/Controllers/UsuarioController.php',
    'app/Controllers/AsistenciaController.php' => $base . '/app/Controllers/AsistenciaController.php',
    'app/Controllers/HorarioController.php' => $base . '/app/Controllers/HorarioController.php',
    'app/Controllers/NovedadController.php' => $base . '/app/Controllers/NovedadController.php',
    'app/Controllers/HoraExtraController.php' => $base . '/app/Controllers/HoraExtraController.php',
    'app/Controllers/ReporteController.php' => $base . '/app/Controllers/ReporteController.php',
    'app/Controllers/AuditoriaController.php' => $base . '/app/Controllers/AuditoriaController.php',
    'app/Controllers/NotificacionController.php' => $base . '/app/Controllers/NotificacionController.php',
    'app/Controllers/PortalController.php' => $base . '/app/Controllers/PortalController.php',
    'app/Controllers/SupervisorController.php' => $base . '/app/Controllers/SupervisorController.php',
    'app/Controllers/ConfiguracionController.php' => $base . '/app/Controllers/ConfiguracionController.php',
    'app/Controllers/DispositivoController.php' => $base . '/app/Controllers/DispositivoController.php',
    'app/Models/DashboardModel.php' => $base . '/app/Models/DashboardModel.php',
    'app/Views/layouts/header.php' => $base . '/app/Views/layouts/header.php',
    'app/Views/layouts/footer.php' => $base . '/app/Views/layouts/footer.php',
    'app/Views/layouts/sidebar.php' => $base . '/app/Views/layouts/sidebar.php',
    'app/Views/dashboard/index.php' => $base . '/app/Views/dashboard/index.php',
];
foreach ($files as $n => $p) check("Archivo: $n", file_exists($p), 'Existe', 'NO EXISTE');

// 2. Rutas de assets en layouts
$header = @file_get_contents($base . '/app/Views/layouts/header.php');
if ($header !== false) {
    check('header.php: usa $basePath dinámico en CSS', strpos($header, '$basePath') !== false, 'Correcto', 'Ruta rota (/assets/)');
    check('header.php: sin ruta absoluta /assets/', !preg_match('#href="/assets/#', $header), 'Correcto', 'Ruta absoluta rota');
} else check('header.php legible', false, '', 'No se pudo leer');
$footer = @file_get_contents($base . '/app/Views/layouts/footer.php');
if ($footer !== false) {
    check('footer.php: usa $basePath dinámico en JS', strpos($footer, '$basePath') !== false, 'Correcto', 'Ruta rota (/assets/)');
} else check('footer.php legible', false, '', 'No se pudo leer');

// 3. Vista del dashboard
$view = @file_get_contents($base . '/app/Views/dashboard/index.php');
if ($view !== false) {
    $canvas = substr_count($view, '<canvas');
    check("dashboard: sin session_start duplicado", strpos($view, 'session_start') === false, 'Correcto', 'Duplicado');
    check("dashboard: sin <main> duplicado", substr_count($view, '<main') === 0, 'Correcto', 'Duplicado');
    check("dashboard: Chart.defaults.animation = false", strpos($view, 'animation = false') !== false, 'Correcto', 'Falta (riesgo de bucle)');
    check("dashboard: validación Array.isArray", strpos($view, 'Array.isArray') !== false, 'Correcto', 'Falta');
} else check('dashboard/index.php legible', false, '', 'No se pudo leer');

// 4. .htaccess
$ht = @file_get_contents(__DIR__ . '/.htaccess');
if ($ht !== false) {
    check('.htaccess: condición !-f presente', strpos($ht, '!-f') !== false, 'Correcto', 'Falta (bucle de redirects)');
    check('.htaccess: sin directivas Header', strpos($ht, 'Header always set') === false, 'Correcto', 'Header rompe Apache');
} else check('.htaccess existe', false, '', 'No existe');

// 5. Base de datos
try {
    $pdo = new PDO('HORION_DSN, HORION_DB_USER, HORION_DB_PASS);
    check('BD: conexión horion_time', true);
    $tablas = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    check('BD: tablas creadas (' . count($tablas) . ')', count($tablas) >= 10, 'Correcto', 'Faltan tablas');
} catch (Exception $e) {
    check('BD: conexión', false, '', $e->getMessage());
}

// 6. Carga de clases
try {
    require_once $base . '/app/Helpers/Security.php';
    require_once $base . '/app/Models/DashboardModel.php';
    require_once $base . '/app/Controllers/DashboardController.php';
    check('DashboardController carga sin error', class_exists('App\\Controllers\\DashboardController'));
} catch (Throwable $e) {
    check('DashboardController carga', false, '', $e->getMessage());
}
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>Diagnóstico HORION TIME</title>
<style>
body{font-family:'Inter',sans-serif;padding:24px;background:#f4f6f9;color:#2d3748;max-width:1000px;margin:0 auto}
h1{color:#03a950;border-bottom:3px solid #03a950;padding-bottom:12px}
.box{background:#fff;padding:20px;border-radius:12px;margin:15px 0;box-shadow:0 4px 12px rgba(0,0,0,.05)}
.ok{color:#03a950;font-weight:700}.fail{color:#e53935;font-weight:700}
table{width:100%;border-collapse:collapse}td{padding:10px;border-bottom:1px solid #e2e8f0}
.summary{font-size:1.3rem;font-weight:800;text-align:center;padding:20px;border-radius:12px}
.summary.good{background:#dcfce7;color:#166534}.summary.bad{background:#fee2e2;color:#991b1b}
a{color:#03a950;font-weight:600;margin-right:12px}
</style></head><body>
<h1>🔎 Diagnóstico HORION TIME</h1>
<p>📅 <?= date('d/m/Y H:i:s') ?> (America/Bogota)</p>
<div class="summary <?= $fail === 0 ? 'good' : 'bad' ?>">✅ <?= $ok ?> OK &nbsp;|&nbsp; ❌ <?= $fail ?> FALLO(S)</div>
<div class="box"><table>
<?php foreach ($results as $r): ?>
<tr><td class="<?= $r['ok'] ? 'ok' : 'fail' ?>"><?= $r['ok'] ? '✅' : '❌' ?></td>
<td><?= htmlspecialchars($r['name']) ?></td><td><?= htmlspecialchars($r['msg']) ?></td></tr>
<?php endforeach; ?>
</table></div>
<div class="box">
<a href="/horion-time/public/">← Dashboard</a>
<a href="/horion-time/public/empresas">Empresas</a>
<a href="/horion-time/public/usuarios">Usuarios</a>
<a href="/horion-time/public/horarios">Horarios</a>
<a href="/horion-time/public/novedades">Novedades</a>
<a href="/horion-time/public/horas_extras">Horas Extras</a>
<a href="/horion-time/public/reportes">Reportes</a>
<a href="/horion-time/public/auditoria">Auditoría</a>
</div>
</body></html>