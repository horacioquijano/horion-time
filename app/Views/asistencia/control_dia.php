<?php
include __DIR__ . '/../layouts/header.php';
$b = '/horion-time/public';
$detalle = $detalle ?? [];
$resumen = $resumen ?? ['total_programados'=>0,'libres_hoy'=>0,'vacaciones_hoy'=>0,'a_tiempo'=>0,'tarde'=>0,'ausente'=>0,'por_marcar'=>0,'sin_turno'=>0];
$conteos = $conteos ?? ['total'=>0,'a_tiempo'=>0,'tarde'=>0,'ausente'=>0,'por_marcar'=>0,'libre'=>0,'vacaciones'=>0,'sin_turno'=>0];
$fecha = $_GET['fecha'] ?? date('Y-m-d');
$fEstado = $_GET['estado'] ?? '';
$fTurno = $_GET['turno'] ?? '';
$esHoy = ($fecha === date('Y-m-d'));
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:12px;">
    <div>
        <h2 style="font-weight:800;color:var(--text-dark);margin:0;">
            <i class="fas fa-list-check" style="color:var(--primary);"></i> Control del Día
        </h2>
        <p style="color:var(--text-muted);font-size:.85rem;margin:4px 0 0 0;">
            Detalle de asistencia vs Panel de Turnos · <?= $esHoy ? '<b>Hoy</b> — ' : '' ?><?= date('d/m/Y', strtotime($fecha)) ?>
            · Visible para coordinadores y jefes de área
        </p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a class="btn" style="background:var(--bg-body);text-decoration:none;" href="<?= $b ?>/dashboard"><i class="fas fa-chart-line"></i> Dashboard</a>
        <a class="btn btn-primary" style="text-decoration:none;" href="<?= $b ?>/asistencia/exportarControlDia?<?= htmlspecialchars(http_build_query($_GET)) ?>">
            <i class="fas fa-file-csv"></i> Exportar CSV
        </a>
    </div>
</div>

<!-- Filtros -->
<div class="card-3d" style="padding:14px 18px;margin-bottom:18px;">
    <form method="GET" action="<?= $b ?>/asistencia/controlDia" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;">
        <div>
            <label class="form-label" style="font-size:.78rem;">Fecha</label>
            <input type="date" name="fecha" value="<?= htmlspecialchars($fecha) ?>" class="form-input" style="width:auto;">
        </div>
        <div>
            <label class="form-label" style="font-size:.78rem;">Estado</label>
            <select name="estado" class="form-input" style="width:auto;">
                <option value="">Todos los estados</option>
                <option value="a_tiempo"   <?= $fEstado==='a_tiempo' ? 'selected' : '' ?>>✅ A tiempo (<?= (int)$conteos['a_tiempo'] ?>)</option>
                <option value="tarde"      <?= $fEstado==='tarde' ? 'selected' : '' ?>>🟠 Tarde (<?= (int)$conteos['tarde'] ?>)</option>
                <option value="por_marcar" <?= $fEstado==='por_marcar' ? 'selected' : '' ?>>⏳ Por marcar (<?= (int)$conteos['por_marcar'] ?>)</option>
                <option value="ausente"    <?= $fEstado==='ausente' ? 'selected' : '' ?>>🔴 Ausente (<?= (int)$conteos['ausente'] ?>)</option>
                <option value="libre"      <?= $fEstado==='libre' ? 'selected' : '' ?>>⚪ Libre (<?= (int)$conteos['libre'] ?>)</option>
                <option value="vacaciones" <?= $fEstado==='vacaciones' ? 'selected' : '' ?>>🌴 Vacaciones (<?= (int)$conteos['vacaciones'] ?>)</option>
            </select>
        </div>
        <div>
            <label class="form-label" style="font-size:.78rem;">Turno</label>
            <select name="turno" class="form-input" style="width:auto;">
                <option value="">Todos los turnos</option>
                <?php foreach (['C'=>'C - Corrido','N'=>'N - Noche','D'=>'D - Día 8-5','M'=>'M - Media jornada','L'=>'L - Libre','V'=>'V - Vacaciones'] as $k => $et): ?>
                <option value="<?= $k ?>" <?= $fTurno === $k ? 'selected' : '' ?>><?= $et ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-primary" type="submit"><i class="fas fa-filter"></i> Filtrar</button>
        <a class="btn" style="background:var(--bg-body);text-decoration:none;" href="<?= $b ?>/asistencia/controlDia">Limpiar</a>
    </form>
</div>

<!-- KPIs compactos -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:18px;">
    <?php
    $kpis = [
        ['Programados', $resumen['total_programados'], '#03a950'],
        ['A tiempo', $resumen['a_tiempo'], '#03a950'],
        ['Tarde', $resumen['tarde'], '#f97316'],
        ['Por marcar', $resumen['por_marcar'], '#f59e0b'],
        ['Ausentes', $resumen['ausente'], '#dc2626'],
        ['Libres (L)', $resumen['libres_hoy'], '#94a3b8'],
        ['Vacaciones (V)', $resumen['vacaciones_hoy'], '#ec4899'],
    ];
    foreach ($kpis as $k): ?>
    <div class="card-3d" style="padding:12px 16px;border-left:4px solid <?= $k[2] ?>;">
        <div style="font-size:.72rem;color:var(--text-muted);font-weight:700;text-transform:uppercase;"><?= $k[0] ?></div>
        <div style="font-size:1.5rem;font-weight:900;color=<?= $k[2] ?>;"><?= (int)$k[1] ?></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Tabla detallada -->
<div class="card-3d" style="padding:18px;overflow-x:auto;">
    <h3 style="margin:0 0 12px 0;font-weight:700;">
        <i class="fas fa-users" style="color:var(--primary);"></i> Detalle por empleado (<?= count($detalle) ?>)
    </h3>
    <table class="data-table" style="min-width:1100px;font-size:.84rem;">
        <thead>
            <tr>
                <th style="text-align:left;">Empleado</th>
                <th style="text-align:left;">Servicio</th>
                <th>Turno</th>
                <th>Horario</th>
                <th style="text-align:left;">Sede</th>
                <th>Entrada</th>
                <th>Salida</th>
                <th>Estado</th>
                <th>Diferencia</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($detalle)): ?>
            <tr><td colspan="9" style="text-align:center;color:var(--text-muted);padding:36px;">
                <i class="fas fa-calendar-xmark" style="font-size:1.8rem;opacity:.4;display:block;margin-bottom:8px;"></i>
                Sin empleados programados para esta fecha/filtros
            </td></tr>
        <?php endif; ?>
        <?php foreach ($detalle as $f): $est = $f['estado']; ?>
            <tr>
                <td style="text-align:left;">
                    <div style="font-weight:600;"><?= htmlspecialchars($f['nombre_completo']) ?></div>
                    <div style="font-size:.74rem;color:var(--text-muted);"><?= htmlspecialchars($f['identificacion']) ?></div>
                </td>
                <td style="text-align:left;"><span class="badge" style="background:#f1f5f9;color:#475569;"><?= htmlspecialchars($f['servicio'] ?? '—') ?></span></td>
                <td style="text-align:center;">
                    <?php if (!empty($f['turno_codigo'])): ?>
                        <span style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:8px;font-weight:900;color:#fff;background:<?= htmlspecialchars($f['turno_color'] ?? '#64748b') ?>;"><?= htmlspecialchars($f['turno_codigo']) ?></span>
                        <div style="font-size:.7rem;color:var(--text-muted);margin-top:3px;"><?= htmlspecialchars(mb_strimwidth($f['turno_nombre'] ?? '', 0, 20, '…')) ?></div>
                    <?php else: ?>—<?php endif; ?>
                </td>
                <td style="font-family:monospace;font-weight:600;">
                    <?= !empty($f['hora_entrada']) ? substr($f['hora_entrada'],0,5) . '–' . substr($f['hora_salida'] ?? '',0,5) : '—' ?>
                </td>
                <td style="text-align:left;font-size:.8rem;"><?= htmlspecialchars($f['sede_nombre'] ?? '—') ?></td>
                <td style="font-family:monospace;font-weight:700;"><?= $f['entrada_real'] ? substr($f['entrada_real'],0,5) : '—' ?></td>
                <td style="font-family:monospace;font-weight:700;"><?= $f['salida_real'] ? substr($f['salida_real'],0,5) : '—' ?></td>
                <td>
                    <span style="display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:999px;font-size:.76rem;font-weight:700;background:<?= $est['color'] ?>22;color:<?= $est['color'] ?>;border:1px solid <?= $est['color'] ?>44;">
                        <i class="fas fa-<?= $est['icon'] ?>"></i> <?= htmlspecialchars($est['label']) ?>
                    </span>
                </td>
                <td style="font-weight:700;color:<?= $est['diff'] !== null ? ($est['diff'] > 10 ? '#dc2626' : '#03a950') : 'var(--text-muted)' ?>;">
                    <?= $est['diff'] !== null ? ($est['diff'] > 0 ? '+' . $est['diff'] : $est['diff']) . ' min' : '—' ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p style="font-size:.76rem;color:var(--text-muted);margin:12px 0 0 0;">
        <i class="fas fa-info-circle"></i> Reglas: entrada ≤ hora programada + 10 min = <b>A tiempo</b> · después = <b>Tarde</b> · sin marca y pasadas 4 h del inicio = <b>Ausente</b> · turnos N evalúan cruce de medianoche.
    </p>
</div>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
