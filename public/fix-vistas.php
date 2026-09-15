<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$viewsDir = dirname(__DIR__) . '/app/Views';
$fixed = [];
$already = [];

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewsDir, FilesystemIterator::SKIP_DOTS));

foreach ($it as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') continue;

    $path = $file->getPathname();
    $fileDir = $file->getPath();

    // Profundidad de la vista respecto a app/Views (para calcular los ../ correctos)
    $rel = str_replace('\\', '/', substr($fileDir, strlen($viewsDir)));
    $depth = ($rel === '' || $rel === '/') ? 0 : count(explode('/', trim($rel, '/')));
    $up = str_repeat('../', $depth);

    $original = file_get_contents($path);

    // Convierte include '../layouts/x.php'  ->  include(__DIR__ . '/../layouts/x.php')
    $pattern = '/\b(include_once|require_once|include|require)\s*\(?\s*[\'"](?:\.\.\/)+layouts\/(header|footer|sidebar)\.php[\'"]\s*\)?/i';
    $nuevo = preg_replace_callback($pattern, function ($m) use ($up) {
        $ruta = $up . 'layouts/' . $m[2] . '.php';
        return $m[1] . '(__DIR__ . ' . var_export($ruta, true) . ')';
    }, $original);

    $nombre = str_replace(dirname(__DIR__) . DIRECTORY_SEPARATOR, '', $path);
    if ($nuevo !== $original) {
        file_put_contents($path, $nuevo);
        $fixed[] = $nombre;
    } else {
        $already[] = $nombre;
    }
}
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>Fix Vistas</title>
<style>
body{font-family:'Inter',Segoe UI,sans-serif;padding:24px;background:#f4f6f9;color:#2d3748;max-width:900px;margin:0 auto}
h1{color:#03a950}
.box{background:#fff;padding:20px;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,.05);margin:16px 0}
.ok{color:#03a950;font-weight:700}
.big{font-size:1.2rem;font-weight:800;text-align:center;padding:16px;border-radius:12px;background:#dcfce7;color:#166534}
ul{columns:2;font-size:.85rem}
a{display:inline-block;margin:6px 10px 0 0;color:#03a950;font-weight:600;background:#e8f5e9;padding:8px 14px;border-radius:8px;text-decoration:none}
</style></head><body>
<h1>🛠️ Reparador de includes en vistas</h1>
<div class="big">✅ <?= count($fixed) ?> vista(s) reparadas | <?= count($already) ?> ya estaban correctas</div>
<div class="box">
<h3>Archivos reparados (include relativo → __DIR__):</h3>
<?php if ($fixed): ?><ul><?php foreach ($fixed as $f): ?><li class="ok">✔ <?= htmlspecialchars($f) ?></li><?php endforeach; ?></ul>
<?php else: ?><p>Ninguno: todas las vistas ya usaban __DIR__ o no tenían includes relativos.</p><?php endif; ?>
</div>
<div class="box">
<h3>🧪 Prueba los módulos ahora:</h3>
<a href="/horion-time/public/asistencia/marcar" target="_blank">Mi Marcación</a>
<a href="/horion-time/public/empresas" target="_blank">Empresas</a>
<a href="/horion-time/public/usuarios" target="_blank">Usuarios</a>
<a href="/horion-time/public/horarios" target="_blank">Horarios</a>
<a href="/horion-time/public/novedades" target="_blank">Novedades</a>
<a href="/horion-time/public/horas_extras" target="_blank">Horas Extras</a>
<a href="/horion-time/public/reportes" target="_blank">Reportes</a>
<a href="/horion-time/public/auditoria" target="_blank">Auditoría</a>
</div>
</body></html>