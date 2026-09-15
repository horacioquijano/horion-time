<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$pub = __DIR__;
$base = dirname(__DIR__);
$report = [];

/* ============ 1) Agregar marcarLeida() al DashboardController si falta ============ */
$ctrl = $base . '/app/Controllers/DashboardController.php';
$c = file_get_contents($ctrl);
if (strpos($c, 'function marcarLeida') === false) {
    $metodo = <<<'PHP'

    public function marcarLeida() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $_POST['id'] ?? 0;
            $usuario_id = $_SESSION['usuario_id'] ?? 1;
            $this->model->marcarAlertaLeida($id, $usuario_id);
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        }
    }
PHP;
    $pos = strrpos($c, '}');
    $c = substr($c, 0, $pos) . $metodo . "\n" . substr($c, $pos);
    file_put_contents($ctrl, $c);
    $report[] = ['DashboardController: método marcarLeida() AGREGADO', true];
} else {
    $report[] = ['DashboardController: marcarLeida() ya existía (sin cambios)', true];
}

/* ============ 2) Parche CSS de compatibilidad para vistas con <main> propio ============ */
$cssFile = $pub . '/assets/css/horion-style.css';
$patch = <<<'CSS'

/* === COMPAT: vistas de módulos que abren su propio <main> y .top-header dentro del content-wrapper === */
.content-wrapper main.main-content{display:block;flex:none;overflow:visible;min-width:0}
.content-wrapper .top-header{position:static;backdrop-filter:none;-webkit-backdrop-filter:none;background:transparent;border-bottom:none;padding:0 0 16px;box-shadow:none}
CSS;
$css = file_get_contents($cssFile);
if (strpos($css, 'COMPAT: vistas de módulos') === false) {
    file_put_contents($cssFile, $css . $patch);
    $report[] = ['CSS: parche de compatibilidad AGREGADO', true];
} else {
    $report[] = ['CSS: parche ya estaba presente (sin cambios)', true];
}

/* ============ 3) Carpeta uploads ============ */
$up = $pub . '/uploads';
if (!is_dir($up)) {
    mkdir($up, 0777, true);
    $report[] = ['Carpeta public/uploads/ CREADA', true];
} else {
    $report[] = ['Carpeta public/uploads/ ya existía', true];
}
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>Fix Extra</title>
<style>
body{font-family:'Inter',Segoe UI,sans-serif;padding:24px;background:#f4f6f9;color:#2d3748;max-width:900px;margin:0 auto}
h1{color:#03a950}
.box{background:#fff;padding:20px;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,.05);margin:16px 0}
.ok{color:#03a950;font-weight:700}
.big{font-size:1.2rem;font-weight:800;text-align:center;padding:16px;border-radius:12px;background:#dcfce7;color:#166534}
a{display:inline-block;margin:6px 10px 0 0;color:#03a950;font-weight:600;background:#e8f5e9;padding:8px 14px;border-radius:8px;text-decoration:none}
</style></head><body>
<h1>🧩 Parches finales</h1>
<div class="big">✅ Procesado correctamente</div>
<div class="box"><ul>
<?php foreach ($report as $r): ?><li class="ok">✔ <?= htmlspecialchars($r[0]) ?></li><?php endforeach; ?>
</ul></div>
<div class="box">
<h3>🧪 Recorrido de prueba final (uno por uno):</h3>
<a href="/horion-time/public/" target="_blank">Dashboard</a>
<a href="/horion-time/public/asistencia/marcar" target="_blank">Mi Marcación</a>
<a href="/horion-time/public/empresas" target="_blank">Empresas</a>
<a href="/horion-time/public/usuarios" target="_blank">Usuarios</a>
<a href="/horion-time/public/horarios" target="_blank">Horarios</a>
<a href="/horion-time/public/horarios/calendario" target="_blank">Calendario</a>
<a href="/horion-time/public/novedades" target="_blank">Novedades</a>
<a href="/horion-time/public/horas_extras" target="_blank">Horas Extras</a>
<a href="/horion-time/public/asistencia" target="_blank">Registro Asistencia</a>
<a href="/horion-time/public/reportes" target="_blank">Reportes</a>
<a href="/horion-time/public/auditoria" target="_blank">Auditoría</a>
<a href="/horion-time/public/notificaciones" target="_blank">Notificaciones</a>
<a href="/horion-time/public/supervisor" target="_blank">Supervisor</a>
<a href="/horion-time/public/configuracion" target="_blank">Configuración</a>
<a href="/horion-time/public/dispositivos" target="_blank">Dispositivos</a>
<a href="/horion-time/public/portal" target="_blank">Portal Empleado</a>
</div>
</body></html>