<?php
/**
 * HORION TIME - Script de Verificación
 * Uso navegador: http://localhost/horion-time/public/verificar.php
 * Uso consola:   php verificar.php
 */
$cli = (php_sapi_name() === 'cli');
$resultados = [];
$fallos = 0;

function check(&$resultados, &$fallos, $nombre, $ok, $detalleOk = 'Correcto', $detalleFail = 'FALLO') {
    $resultados[] = ['nombre' => $nombre, 'ok' => $ok, 'detalle' => $ok ? $detalleOk : $detalleFail];
    if (!$ok) $fallos++;
}

$base = dirname(__DIR__); // raíz horion-time/
$view = $base . '/app/Views/dashboard/index.php';
$header = $base . '/app/Views/layouts/header.php';
$footer = $base . '/app/Views/layouts/footer.php';
$sidebar = $base . '/app/Views/layouts/sidebar.php';
$htaccess = __DIR__ . '/.htaccess';
$css = __DIR__ . '/assets/css/horion-style.css';
$js = __DIR__ . '/assets/js/horion-app.js';

// ===== 1. VERIFICACIONES DEL BUCLE (vista del dashboard) =====
if (file_exists($view)) {
    $v = file_get_contents($view);
    $canvas = substr_count($v, '<canvas');
    $boxes = substr_count($v, 'class="chart-box"');

    check($resultados, $fallos, 'Vista: existe el archivo', true);
    check($resultados, $fallos, "BUCLE: cada canvas tiene contenedor .chart-box ($canvas canvas / $boxes contenedores)",
        ($canvas > 0 && $canvas === $boxes), 'Todos los canvas están envueltos', 'Hay canvas sin contenedor de altura fija');
    check($resultados, $fallos, 'BUCLE: ningún canvas tiene atributo height=',
        !preg_match('/<canvas[^>]*\sheight\s*=/', $v), 'Sin atributo height', 'Aún hay canvas con height= (causa del bucle)');
    check($resultados, $fallos, 'BUCLE: CSS .chart-box con height fijo definido',
        (strpos($v, '.chart-box') !== false && preg_match('/\.chart-box\s*{[^}]*height\s*:\s*\d+px/', $v)),
        'Altura fija definida', 'Falta altura fija en .chart-box');
    check($resultados, $fallos, 'BUCLE: Chart.defaults.animation = false',
        strpos($v, 'animation = false') !== false, 'Animaciones desactivadas', 'Falta desactivar animaciones');
    check($resultados, $fallos, 'BUCLE: Chart.defaults.resizeDelay configurado',
        strpos($v, 'resizeDelay') !== false, 'resizeDelay activo', 'Falta resizeDelay');
    check($resultados, $fallos, 'BUCLE: gráficos dentro de DOMContentLoaded',
        strpos($v, 'DOMContentLoaded') !== false, 'Renderizado tras carga del DOM', 'Falta DOMContentLoaded');
    check($resultados, $fallos, 'BUCLE: validación Array.isArray antes de crear gráficos',
        strpos($v, 'Array.isArray') !== false, 'Datos validados', 'Falta validación de arrays');
    check($resultados, $fallos, 'HTML: sin session_start() duplicado en la vista',
        strpos($v, 'session_start') === false, 'Sin session_start', 'session_start duplicado');
    check($resultados, $fallos, 'HTML: sin etiqueta <main> duplicada en la vista',
        substr_count($v, '<main') === 0, 'Sin <main> duplicado', 'Hay <main> duplicado');
    check($resultados, $fallos, 'HTML: sin include duplicado de sidebar.php en la vista',
        strpos($v, 'sidebar.php') === false, 'Sin sidebar duplicado', 'sidebar.php incluido dos veces');
} else {
    check($resultados, $fallos, 'Vista: existe el archivo', false, '', 'No existe app/Views/dashboard/index.php');
}

// ===== 2. LAYOUTS =====
if (file_exists($header)) {
    $h = file_get_contents($header);
    check($resultados, $fallos, 'header.php: ruta de CSS no apunta a la raíz (/assets/)',
        !preg_match('#href="/assets/#', $h), 'Ruta CSS correcta', 'CSS con ruta absoluta rota');
    check($resultados, $fallos, 'header.php: abre <main> y content-wrapper una sola vez',
        substr_count($h, '<main') === 1, 'Estructura correcta', '<main> duplicado en header');
} else {
    check($resultados, $fallos, 'header.php existe', false, '', 'No existe');
}
if (file_exists($footer)) {
    $f = file_get_contents($footer);
    check($resultados, $fallos, 'footer.php: ruta de JS no apunta a la raíz (/assets/)',
        !preg_match('#src="/assets/#', $f), 'Ruta JS correcta', 'JS con ruta absoluta rota');
} else {
    check($resultados, $fallos, 'footer.php existe', false, '', 'No existe');
}
check($resultados, $fallos, 'sidebar.php existe', file_exists($sidebar));

// ===== 3. ASSETS =====
check($resultados, $fallos, 'CSS horion-style.css existe', file_exists($css));
check($resultados, $fallos, 'JS horion-app.js existe', file_exists($js));

// ===== 4. HTACCESS =====
if (file_exists($htaccess)) {
    $ht = file_get_contents($htaccess);
    check($resultados, $fallos, '.htaccess: tiene condición !-f (evita bucle de redirects y permite verificar.php)',
        strpos($ht, '!-f') !== false, 'Condición presente', 'Falta condición !-f (causa bucle de redirects)');
    check($resultados, $fallos, '.htaccess: sin directivas Header (evita Error 500 en Windows)',
        strpos($ht, 'Header always set') === false, 'Sin Header problemáticos', 'Hay Header que rompen Apache');
} else {
    check($resultados, $fallos, '.htaccess existe', false, '', 'No existe (opcional, pero recomendado)');
}

// ===== 5. BASE DE DATOS =====
try {
    $pdo = new PDO('HORION_DSN, HORION_DB_USER, HORION_DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    check($resultados, $fallos, 'BD: conexión a horion_time', true);
    $tablas = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach (['empresas', 'usuarios', 'registros_asistencia', 'alertas'] as $t) {
        check($resultados, $fallos, "BD: tabla '$t' existe", in_array($t, $tablas));
    }
} catch (PDOException $e) {
    check($resultados, $fallos, 'BD: conexión a horion_time', false, '', $e->getMessage());
}

// ===== SALIDA =====
if ($cli) {
    echo "=== VERIFICACIÓN HORION TIME ===\n";
    foreach ($resultados as $r) {
        echo ($r['ok'] ? '[OK]   ' : '[FAIL] ') . $r['nombre'] . ($r['ok'] ? '' : ' -> ' . $r['detalle']) . "\n";
    }
    echo "\nRESULTADO: " . ($fallos === 0 ? 'TODO CORRECTO' : "$fallos PROBLEMA(S)") . "\n";
} else {
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Verificación</title>';
    echo '<style>body{font-family:Segoe UI,Arial;padding:20px;background:#f4f5f7}h1{color:#03a950}.ok{color:#03a950;font-weight:bold}.fail{color:#e53935;font-weight:bold}.box{background:#fff;padding:15px;border-radius:8px;margin:10px 0;box-shadow:0 2px 6px rgba(0,0,0,.06)}table{width:100%;border-collapse:collapse}td,th{padding:8px;border-bottom:1px solid #eee;text-align:left}</style>';
    echo '</head><body><h1>🔎 Verificación HORION TIME</h1><div class="box"><table><tr><th>Estado</th><th>Verificación</th><th>Detalle</th></tr>';
    foreach ($resultados as $r) {
        echo '<tr><td class="' . ($r['ok'] ? 'ok' : 'fail') . '">' . ($r['ok'] ? '✅ OK' : '❌ FAIL') . '</td><td>' . htmlspecialchars($r['nombre']) . '</td><td>' . htmlspecialchars($r['ok'] ? $r['detalle'] : $r['detalle']) . '</td></tr>';
    }
    echo '</table></div><div class="box" style="font-size:1.2rem;font-weight:bold;color:' . ($fallos === 0 ? '#03a950' : '#e53935') . ';">';
    echo $fallos === 0 ? '✅ TODO CORRECTO: el bucle está solucionado y la estructura es válida.' : "❌ Se detectaron $fallos problema(s). Corrige los marcados en rojo.";
    echo '</div></body></html>';
}