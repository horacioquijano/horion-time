<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
$views = dirname(__DIR__) . '/app/Views';
$fixed = []; $correct = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($views, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!$f->isFile() || strtolower($f->getExtension()) !== 'php') continue;
    $p = $f->getPathname();
    $c = file_get_contents($p); $o = $c;
    // Corrige: __DIR__ . '../layouts/x.php'  ->  __DIR__ . '/../layouts/x.php'
    $c = preg_replace("/__DIR__\s*\.\s*'\.\.\//", "__DIR__ . '/../", $c);
    $c = preg_replace('/__DIR__\s*\.\s*"\.\.\//', '__DIR__ . "/../', $c);
    if ($c !== $o) { file_put_contents($p, $c); $fixed[] = str_replace(dirname(__DIR__) . '/', '', $p); }
    else { $correct[] = str_replace(dirname(__DIR__) . '/', '', $p); }
}
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Fix Rutas</title>
<style>body{font-family:Inter,Segoe UI,sans-serif;padding:24px;background:#f4f6f9;max-width:900px;margin:0 auto}
h1{color:#03a950}.box{background:#fff;padding:20px;border-radius:12px;margin:16px 0;box-shadow:0 4px 12px rgba(0,0,0,.05)}
.ok{color:#03a950;font-weight:700}.big{font-size:1.2rem;font-weight:800;text-align:center;padding:16px;border-radius:12px;background:#dcfce7;color:#166534}
ul{columns:2;font-size:.85rem}a{display:inline-block;margin:6px 10px 0 0;color:#03a950;font-weight:600;background:#e8f5e9;padding:8px 14px;border-radius:8px;text-decoration:none}</style>
</head><body>
<h1>🔧 Fix de rutas __DIR__</h1>
<div class="big">✅ <?= count($fixed) ?> archivo(s) corregidos | <?= count($correct) ?> ya correctos</div>
<div class="box"><h3>Corregidos:</h3>
<?php if ($fixed): ?><ul><?php foreach ($fixed as $x): ?><li class="ok">✔ <?= htmlspecialchars($x) ?></li><?php endforeach; ?></ul>
<?php else: ?><p>Ninguno (todos ya tenían la barra correcta).</p><?php endif; ?></div>
<div class="box"><h3>🧪 Prueba:</h3>
<a href="/horion-time/public/asistencia/marcar" target="_blank">Mi Marcación</a>
<a href="/horion-time/public/asistencia" target="_blank">Registro Asistencia</a>
<a href="/horion-time/public/empresas" target="_blank">Empresas</a>
<a href="/horion-time/public/usuarios" target="_blank">Usuarios</a>
</div></body></html>