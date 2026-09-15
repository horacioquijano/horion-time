<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('America/Bogota');

try {
    $pdo = new PDO('HORION_DSN, HORION_DB_USER, HORION_DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (PDOException $e) {
    die('❌ Error de conexión: ' . $e->getMessage());
}

// Columnas que los modelos/vistas necesitan. Solo se agregan si NO existen.
$plan = [
    'usuarios' => [
        'identificacion'    => "VARCHAR(50) NULL DEFAULT NULL",
        'nombre_completo'   => "VARCHAR(150) NULL DEFAULT NULL",
        'email'             => "VARCHAR(150) NULL DEFAULT NULL",
        'estado'            => "VARCHAR(20) NOT NULL DEFAULT 'activo'",
        'empresa_id'        => "INT NULL DEFAULT NULL",
        'sede_id'           => "INT NULL DEFAULT NULL",
        'horario_asignado_id' => "INT NULL DEFAULT NULL",
    ],
    'sedes' => [
        'nombre'     => "VARCHAR(150) NULL DEFAULT NULL",
        'empresa_id' => "INT NULL DEFAULT NULL",
    ],
    'horarios' => [
        'jornada_diurna_inicio' => "TIME NULL DEFAULT NULL",
        'jornada_diurna_fin'    => "TIME NULL DEFAULT NULL",
        'tolerancia_entrada'    => "INT NULL DEFAULT 10",
    ],
    'registros_asistencia' => [
        'sede_id'          => "INT NULL DEFAULT NULL",
        'foto_evidencia'   => "VARCHAR(255) NULL DEFAULT NULL",
        'lat'              => "DECIMAL(10,7) NULL DEFAULT NULL",
        'lng'              => "DECIMAL(10,7) NULL DEFAULT NULL",
        'metodo_marcacion' => "VARCHAR(30) NULL DEFAULT 'foto'",
        'estado'           => "VARCHAR(20) NOT NULL DEFAULT 'pendiente'",
        'observaciones'    => "TEXT NULL",
    ],
    'novedades' => [
        'tipo'         => "VARCHAR(50) NULL DEFAULT NULL",
        'fecha_inicio' => "DATE NULL DEFAULT NULL",
        'fecha_fin'    => "DATE NULL DEFAULT NULL",
        'estado'       => "VARCHAR(20) NOT NULL DEFAULT 'pendiente'",
        'empresa_id'   => "INT NULL DEFAULT NULL",
        'usuario_id'   => "INT NULL DEFAULT NULL",
    ],
    'alertas' => [
        'tipo'           => "VARCHAR(20) NULL DEFAULT 'info'",
        'titulo'         => "VARCHAR(150) NULL DEFAULT NULL",
        'mensaje'        => "TEXT NULL",
        'prioridad'      => "INT NULL DEFAULT 2",
        'fecha_creacion' => "DATETIME NULL DEFAULT CURRENT_TIMESTAMP",
        'empresa_id'     => "INT NULL DEFAULT NULL",
        'usuario_id'     => "INT NULL DEFAULT NULL",
    ],
];

$report = [];
foreach ($plan as $tabla => $columnas) {
    try {
        $existentes = $pdo->query("SHOW COLUMNS FROM `$tabla`")->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        $report[] = [$tabla, '(tabla)', '❌ La tabla no existe: ' . $e->getMessage()];
        continue;
    }
    foreach ($columnas as $col => $def) {
        if (in_array($col, $existentes)) {
            $report[] = [$tabla, $col, '⏭️ Ya existía'];
        } else {
            try {
                $pdo->exec("ALTER TABLE `$tabla` ADD COLUMN `$col` $def");
                $report[] = [$tabla, $col, '✅ AGREGADA'];
            } catch (PDOException $e) {
                $report[] = [$tabla, $col, '❌ Error: ' . $e->getMessage()];
            }
        }
    }
}
$agregadas = count(array_filter($report, fn($r) => str_starts_with($r[2], '✅')));
$errores   = count(array_filter($report, fn($r) => str_starts_with($r[2], '❌')));
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Fix Columnas BD</title>
<style>
body{font-family:Inter,Segoe UI,sans-serif;padding:24px;background:#f4f6f9;color:#2d3748;max-width:900px;margin:0 auto}
h1{color:#03a950}.box{background:#fff;padding:20px;border-radius:12px;margin:16px 0;box-shadow:0 4px 12px rgba(0,0,0,.05)}
table{width:100%;border-collapse:collapse}td,th{padding:8px 10px;border-bottom:1px solid #e2e8f0;text-align:left;font-size:.9rem}
.big{font-size:1.2rem;font-weight:800;text-align:center;padding:16px;border-radius:12px;background:#dcfce7;color:#166534}
a{display:inline-block;margin:6px 10px 0 0;color:#03a950;font-weight:600;background:#e8f5e9;padding:8px 14px;border-radius:8px;text-decoration:none}
</style></head><body>
<h1>🗄️ Fix de columnas en Base de Datos</h1>
<div class="big">✅ <?= $agregadas ?> columna(s) agregadas | ❌ <?= $errores ?> error(es)</div>
<div class="box"><table>
<tr><th>Tabla</th><th>Columna</th><th>Resultado</th></tr>
<?php foreach ($report as $r): ?>
<tr><td><?= htmlspecialchars($r[0]) ?></td><td><code><?= htmlspecialchars($r[1]) ?></code></td><td><?= $r[2] ?></td></tr>
<?php endforeach; ?>
</table></div>
<div class="box">
<h3>🧪 Prueba ahora:</h3>
<a href="/horion-time/public/portal" target="_blank">Portal Empleado</a>
<a href="/horion-time/public/asistencia" target="_blank">Registro Asistencia</a>
<a href="/horion-time/public/asistencia/marcar" target="_blank">Mi Marcación</a>
<a href="/horion-time/public/" target="_blank">Dashboard</a>
</div>
</body></html>