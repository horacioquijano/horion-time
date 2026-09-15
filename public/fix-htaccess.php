<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$archivo = __DIR__ . '/.htaccess';
$antes = file_exists($archivo) ? file_get_contents($archivo) : '(el archivo NO existía)';

// Contenido CORRECTO: archivos reales se sirven directo; todo lo demás va al router
$contenido = <<<HT
RewriteEngine On

# 1) Si el archivo o carpeta existe fisicamente, Apache lo sirve tal cual
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d

# 2) Cualquier otra ruta (modulos) la procesa el router index.php
RewriteRule ^ index.php [L]
HT;

$bytes = file_put_contents($archivo, $contenido . "\n");
$despues = file_get_contents($archivo);
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>Fix .htaccess</title>
<style>
body{font-family:'Inter',Segoe UI,sans-serif;padding:24px;background:#f4f6f9;color:#2d3748;max-width:900px;margin:0 auto}
h1{color:#03a950}
.box{background:#fff;padding:20px;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,.05);margin:16px 0}
pre{background:#1e1e24;color:#a5b3ce;padding:14px;border-radius:8px;overflow-x:auto;font-size:.85rem}
.ok{color:#03a950;font-weight:800}.fail{color:#e53935;font-weight:800}
.big{font-size:1.2rem;font-weight:800;text-align:center;padding:16px;border-radius:12px;background:#dcfce7;color:#166534}
a{display:inline-block;margin:6px 10px 0 0;color:#03a950;font-weight:600;text-decoration:none;background:#e8f5e9;padding:8px 14px;border-radius:8px}
</style></head><body>
<h1>🔧 Fix .htaccess</h1>
<div class="big"><?= $bytes !== false ? "✅ .htaccess REESCRITO CORRECTAMENTE ({$bytes} bytes)" : '❌ NO se pudo escribir el .htaccess' ?></div>

<div class="box">
<h3>Contenido ANTES (el que estaba fallando):</h3>
<pre><?= htmlspecialchars($antes) ?></pre>
</div>

<div class="box">
<h3>Contenido AHORA (correcto):</h3>
<pre><?= htmlspecialchars($despues) ?></pre>
</div>

<div class="box">
<h3>🧪 Prueba los módulos ahora mismo (sin reiniciar nada):</h3>
<a href="/horion-time/public/asistencia/marcar" target="_blank">Mi Marcación</a>
<a href="/horion-time/public/empresas" target="_blank">Empresas</a>
<a href="/horion-time/public/usuarios" target="_blank">Usuarios</a>
<a href="/horion-time/public/horarios" target="_blank">Horarios</a>
<a href="/horion-time/public/novedades" target="_blank">Novedades</a>
<a href="/horion-time/public/horas_extras" target="_blank">Horas Extras</a>
<a href="/horion-time/public/reportes" target="_blank">Reportes</a>
<a href="/horion-time/public/auditoria" target="_blank">Auditoría</a>
<a href="/horion-time/public/" target="_blank">Dashboard</a>
</div>
</body></html>