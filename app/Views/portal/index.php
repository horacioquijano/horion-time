<?php include __DIR__ . '/../layouts/header.php'; 
$jornada = $jornada ?? ['entrada' => null, 'salida_almuerzo' => null, 'regreso_almuerzo' => null, 'salida' => null];
$horas_trabajadas = $horas_trabajadas ?? 0;
$marcaciones_hoy = $marcaciones_hoy ?? [];
$marcaciones_semana = $marcaciones_semana ?? [];
$mis_novedades = $mis_novedades ?? [];
$resumen_mensual = $resumen_mensual ?? ['dias_trabajados' => 0, 'total_entradas' => 0];
$tipos = ['entrada' => '🟢 Entrada', 'salida_almuerzo' => '🍽️ Salida Alm.', 'regreso_almuerzo' => '🍽️ Regreso Alm.', 'salida' => '🔴 Salida'];
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
    <h2 style="font-weight:700;color:var(--text-dark);margin:0;">
        <i class="fas fa-user-circle" style="color:var(--primary);"></i> Portal del Empleado
    </h2>
    <a href="/horion-time/public/asistencia/marcar" class="btn btn-primary"><i class="fas fa-fingerprint"></i> Ir a Marcar</a>
</div>

<div class="card-3d" style="margin-bottom:24px;">
    <h3 style="font-weight:700;margin-bottom:16px;"><i class="fas fa-clock" style="color:var(--primary);"></i> Mi Jornada de Hoy</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:16px;">
        <div style="background:var(--primary-light);padding:16px;border-radius:12px;text-align:center;">
            <div style="font-size:.8rem;color:var(--text-muted);">Entrada</div>
            <div style="font-size:1.4rem;font-weight:800;color:var(--primary);font-family:monospace;"><?= htmlspecialchars($jornada['entrada'] ?? '--:--:--') ?></div>
        </div>
        <div style="background:#fef3c7;padding:16px;border-radius:12px;text-align:center;">
            <div style="font-size:.8rem;color:var(--text-muted);">Salida Almuerzo</div>
            <div style="font-size:1.4rem;font-weight:800;color:#92400e;font-family:monospace;"><?= htmlspecialchars($jornada['salida_almuerzo'] ?? '--:--:--') ?></div>
        </div>
        <div style="background:#dbeafe;padding:16px;border-radius:12px;text-align:center;">
            <div style="font-size:.8rem;color:var(--text-muted);">Regreso Almuerzo</div>
            <div style="font-size:1.4rem;font-weight:800;color:#1e40af;font-family:monospace;"><?= htmlspecialchars($jornada['regreso_almuerzo'] ?? '--:--:--') ?></div>
        </div>
        <div style="background:#fee2e2;padding:16px;border-radius:12px;text-align:center;">
            <div style="font-size:.8rem;color:var(--text-muted);">Salida</div>
            <div style="font-size:1.4rem;font-weight:800;color:#991b1b;font-family:monospace;"><?= htmlspecialchars($jornada['salida'] ?? '--:--:--') ?></div>
        </div>
        <div style="background:linear-gradient(135deg,var(--primary),var(--primary-dark));padding:16px;border-radius:12px;text-align:center;color:#fff;box-shadow:0 4px 15px var(--primary-glow);">
            <div style="font-size:.8rem;opacity:.85;">Horas Trabajadas</div>
            <div style="font-size:1.4rem;font-weight:800;font-family:monospace;"><?= number_format((float)$horas_trabajadas, 2) ?> h</div>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 2fr;gap:24px;margin-bottom:24px;">
    <div class="card-3d">
        <h3 style="font-weight:700;margin-bottom:16px;"><i class="fas fa-calendar-check" style="color:var(--primary);"></i> Resumen del Mes</h3>
        <div style="display:flex;flex-direction:column;gap:12px;">
            <div style="display:flex;justify-content:space-between;padding:12px;background:var(--bg-body);border-radius:10px;">
                <span style="color:var(--text-muted);">Días trabajados</span>
                <strong style="color:var(--primary);"><?= (int)($resumen_mensual['dias_trabajados'] ?? 0) ?></strong>
            </div>
            <div style="display:flex;justify-content:space-between;padding:12px;background:var(--bg-body);border-radius:10px;">
                <span style="color:var(--text-muted);">Entradas registradas</span>
                <strong style="color:var(--info);"><?= (int)($resumen_mensual['total_entradas'] ?? 0) ?></strong>
            </div>
            <div style="display:flex;justify-content:space-between;padding:12px;background:var(--bg-body);border-radius:10px;">
                <span style="color:var(--text-muted);">Marcaciones de hoy</span>
                <strong style="color:var(--warning);"><?= count($marcaciones_hoy) ?></strong>
            </div>
        </div>
    </div>
    <div class="card-3d">
        <h3 style="font-weight:700;margin-bottom:16px;"><i class="fas fa-history" style="color:var(--primary);"></i> Mis Marcaciones (últimos 7 días)</h3>
        <div class="table-container">
            <table class="data-table">
                <thead><tr><th>Fecha</th><th>Hora</th><th>Tipo</th><th>Método</th><th>Estado</th></tr></thead>
                <tbody>
                <?php if (empty($marcaciones_semana)): ?>
                    <tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:32px;">Aún no tienes marcaciones esta semana</td></tr>
                <?php else: foreach ($marcaciones_semana as $m): ?>
                    <tr>
                        <td><?= date('d/m/Y', strtotime($m['fecha'])) ?></td>
                        <td style="font-family:monospace;font-weight:600;"><?= htmlspecialchars($m['hora_registro']) ?></td>
                        <td><?= $tipos[$m['tipo_marcacion']] ?? htmlspecialchars($m['tipo_marcacion']) ?></td>
                        <td><span class="badge" style="background:#e0e7ff;color:#3730a3;"><?= htmlspecialchars(ucfirst($m['metodo_marcacion'])) ?></span></td>
                        <td><span class="badge badge-<?= $m['estado'] === 'validado' ? 'success' : ($m['estado'] === 'pendiente' ? 'warning' : 'danger') ?>"><?= htmlspecialchars(ucfirst($m['estado'])) ?></span></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card-3d">
    <h3 style="font-weight:700;margin-bottom:16px;"><i class="fas fa-exclamation-triangle" style="color:var(--warning);"></i> Mis Novedades</h3>
    <div class="table-container">
        <table class="data-table">
            <thead><tr><th>Tipo</th><th>Desde</th><th>Hasta</th><th>Justificación</th><th>Estado</th></tr></thead>
            <tbody>
            <?php if (empty($mis_novedades)): ?>
                <tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:32px;">No tienes novedades registradas</td></tr>
            <?php else: foreach ($mis_novedades as $n): ?>
                <tr>
                    <td><span class="badge badge-info"><?= htmlspecialchars(ucfirst($n['tipo'] ?? 'N/A')) ?></span></td>
                    <td><?= !empty($n['fecha_inicio']) ? date('d/m/Y', strtotime($n['fecha_inicio'])) : '—' ?></td>
                    <td><?= !empty($n['fecha_fin']) ? date('d/m/Y', strtotime($n['fecha_fin'])) : '—' ?></td>
                    <td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($n['justificacion'] ?? $n['descripcion'] ?? '—') ?></td>
                    <td><span class="badge badge-<?= ($n['estado'] ?? '') === 'aprobada' ? 'success' : (($n['estado'] ?? '') === 'rechazada' ? 'danger' : 'warning') ?>"><?= htmlspecialchars(ucfirst($n['estado'] ?? 'pendiente')) ?></span></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../layouts/footer.php'; ?>