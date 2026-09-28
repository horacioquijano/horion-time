<?php
include __DIR__ . '/../layouts/header.php';
$b = '/horion-time/public';
$mesesEs = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',
            7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
$kpis = $res['kpis']; $empleados = $res['empleados']; $servicios = $res['servicios'];
$modoGlobal = (bool)($_SESSION['modo_global'] ?? false);
$empresas = $empresas ?? [];
$empresaSel = $_GET['empresa'] ?? '';

function pctColor($pct) {
    if ($pct === null) return '#94a3b8';
    if ($pct >= 95) return 'var(--success)';
    if ($pct >= 80) return 'var(--warning)';
    return '#dc2626';
}
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:12px;">
    <div>
        <h2 style="font-weight:800;color:var(--text-dark);margin:0;">
            <i class="fas fa-chart-line" style="color:var(--primary);"></i> Cumplimiento de Turnos
        </h2>
        <p style="color:var(--text-muted);font-size:.85rem;margin:4px 0 0 0;">
            Programado (Panel de Turnos) vs trabajado (marcaciones) · <?= $mesesEs[$mes] ?> <?= $anio ?>
        </p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a class="btn" style="background:var(--bg-body);text-decoration:none;" href="<?= $b ?>/asistencia"><i class="fas fa-list"></i> Marcaciones</a>
        <a class="btn btn-primary" style="text-decoration:none;" href="<?= $b ?>/asistencia/exportarCumplimiento?<?= htmlspecialchars(http_build_query($_GET)) ?>">
            <i class="fas fa-file-excel"></i> Exportar CSV
        </a>
    </div>
</div>

<!-- Filtros -->
<div class="card-3d" style="padding:14px 18px;margin-bottom:18px;">
    <form method="GET" action="<?= $b ?>/asistencia/cumplimiento" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;">
        <div>
            <label class="form-label" style="font-size:.78rem;">Mes</label>
            <select name="mes" class="form-input" style="width:auto;">
                <?php foreach ($mesesEs as $nm => $et): ?>
                <option value="<?= $nm ?>" <?= (int)$nm === (int)$mes ? 'selected' : '' ?>><?= $et ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label" style="font-size:.78rem;">Año</label>
            <input type="number" name="anio" value="<?= (int)$anio ?>" min="2020" max="2040" class="form-input" style="width:100px;">
        </div>
        <?php if ($modoGlobal && !empty($empresas)): ?>
        <div>
            <label class="form-label" style="font-size:.78rem;">Empresa</label>
            <select name="empresa" class="form-input" style="width:auto;">
                <option value="">Todas</option>
                <?php foreach ($empresas as $e): ?>
                <option value="<?= (int)$e['id'] ?>" <?= (string)$empresaSel === (string)$e['id'] ? 'selected' : '' ?>><?= htmlspecialchars($e['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <button class="btn btn-primary" type="submit"><i class="fas fa-sync"></i> Ver</button>
    </form>
</div>

<!-- KPIs -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;margin-bottom:20px;">
    <div class="card-3d" style="padding:14px 18px;">
        <div style="color:var(--text-muted);font-size:.78rem;font-weight:600;">EMPLEADOS EVALUADOS</div>
        <div style="font-size:1.6rem;font-weight:800;color:var(--primary);"><?= $kpis['empleados'] ?></div>
    </div>
    <div class="card-3d" style="padding:14px 18px;">
        <div style="color:var(--text-muted);font-size:.78rem;font-weight:600;">CUMPLIMIENTO GLOBAL</div>
        <div style="font-size:1.6rem;font-weight:800;color:<?= pctColor($kpis['pct_global']) ?>;"><?= $kpis['pct_global'] !== null ? $kpis['pct_global'] . '%' : '—' ?></div>
    </div>
    <div class="card-3d" style="padding:14px 18px;">
        <div style="color:var(--text-muted);font-size:.78rem;font-weight:600;">TARDANZAS</div>
        <div style="font-size:1.6rem;font-weight:800;color:var(--warning);"><?= $kpis['tardanzas'] ?></div>
    </div>
    <div class="card-3d" style="padding:14px 18px;">
        <div style="color:var(--text-muted);font-size:.78rem;font-weight:600;">AUSENCIAS</div>
        <div style="font-size:1.6rem;font-weight:800;color:#dc2626;"><?= $kpis['ausencias'] ?></div>
    </div>
    <div class="card-3d" style="padding:14px 18px;">
        <div style="color:var(--text-muted);font-size:.78rem;font-weight:600;">HORAS EXTRA (DÍAS L)</div>
        <div style="font-size:1.6rem;font-weight:800;color:var(--text-dark);"><?= number_format($kpis['horas_extra'], 1) ?></div>
    </div>
    <div class="card-3d" style="padding:14px 18px;">
        <div style="color:var(--text-muted);font-size:.78rem;font-weight:600;">ALERTAS VACACIONES</div>
        <div style="font-size:1.6rem;font-weight:800;color:#92400e;"><?= $kpis['alertas_vacacion'] ?></div>
    </div>
</div>

<?php if (empty($empleados)): ?>
<div class="card-3d" style="padding:40px;text-align:center;color:var(--text-muted);">
    <i class="fas fa-calendar-times" style="font-size:2rem;opacity:.5;display:block;margin-bottom:10px;"></i>
    Sin programación cargada para este mes.<br>Sube el Excel en <b>Panel de Turnos → Carga Masiva</b>.
</div>
<?php else: ?>

<!-- Por servicio -->
<div class="card-3d" style="padding:18px;overflow-x:auto;margin-bottom:20px;">
    <h3 style="margin:0 0 12px 0;font-weight:700;"><i class="fas fa-hospital" style="color:var(--primary);"></i> Cumplimiento por servicio</h3>
    <table class="data-table" style="min-width:700px;font-size:.85rem;">
        <thead><tr><th style="text-align:left;">Servicio</th><th>Empleados</th><th>% Cumpl.</th><th>Tardanzas</th><th>Ausencias</th><th>Horas extra</th></tr></thead>
        <tbody>
        <?php foreach ($servicios as $s): ?>
            <tr>
                <td style="text-align:left;font-weight:600;"><?= htmlspecialchars($s['servicio']) ?></td>
                <td><?= $s['empleados'] ?></td>
                <td style="font-weight:800;color:<?= pctColor($s['pct']) ?>;"><?= $s['pct'] !== null ? $s['pct'] . '%' : '—' ?></td>
                <td><?= $s['tard'] ?></td>
                <td><?= $s['aus'] ?></td>
                <td><?= number_format($s['extra_h'], 1) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Detalle por empleado -->
<div class="card-3d" style="padding:18px;overflow-x:auto;">
    <h3 style="margin:0 0 12px 0;font-weight:700;"><i class="fas fa-users" style="color:var(--primary);"></i> Detalle por empleado</h3>
    <table class="data-table" style="min-width:1150px;font-size:.82rem;">
        <thead>
            <tr>
                <th style="text-align:left;">Empleado</th>
                <th style="text-align:left;">Servicio</th>
                <th>Días prog.</th>
                <th>Asistidos</th>
                <th>Tard.</th>
                <th>S. tempranas</th>
                <th>Ausencias</th>
                <th>Extra (L)</th>
                <th>Horas extra</th>
                <th>Alertas V</th>
                <th>Horas prog.</th>
                <th>Horas trab.</th>
                <th>% Cumpl.</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($empleados as $r): ?>
            <tr>
                <td style="text-align:left;">
                    <div style="font-weight:600;">
                        <?= htmlspecialchars($r['nombre']) ?>
                        <?php if ($r['marcas_sin_turno'] > 0): ?>
                            <span class="badge" style="background:#fef3c7;color:#92400e;font-size:.68rem;" title="Marcaciones sin turno programado"><?= $r['marcas_sin_turno'] ?> sin turno</span>
                        <?php endif; ?>
                    </div>
                    <div style="font-size:.72rem;color:var(--text-muted);"><?= htmlspecialchars($r['identificacion']) ?></div>
                </td>
                <td style="text-align:left;"><?= htmlspecialchars($r['servicio'] ?: '—') ?></td>
                <td><?= $r['dias_prog'] ?></td>
                <td><?= $r['dias_asistidos'] ?></td>
                <td style="color:<?= $r['tardanzas'] ? '#92400e' : 'var(--text-muted)' ?>;font-weight:<?= $r['tardanzas'] ? '700' : '400' ?>;"><?= $r['tardanzas'] ?></td>
                <td style="color:<?= $r['salidas_tempranas'] ? '#92400e' : 'var(--text-muted)' ?>;font-weight:<?= $r['salidas_tempranas'] ? '700' : '400' ?>;"><?= $r['salidas_tempranas'] ?></td>
                <td style="color:<?= $r['ausencias'] ? '#dc2626' : 'var(--text-muted)' ?>;font-weight:<?= $r['ausencias'] ? '700' : '400' ?>;"><?= $r['ausencias'] ?></td>
                <td><?= $r['dias_extra'] ?></td>
                <td><?= number_format($r['horas_extra'], 1) ?></td>
                <td style="color:<?= $r['alertas_vacacion'] ? '#92400e' : 'var(--text-muted)' ?>;font-weight:<?= $r['alertas_vacacion'] ? '700' : '400' ?>;"><?= $r['alertas_vacacion'] ?></td>
                <td><?= number_format($r['horas_prog'], 1) ?></td>
                <td style="font-weight:700;"><?= number_format($r['horas_trab'], 1) ?></td>
                <td>
                    <span class="badge" style="background:<?= pctColor($r['pct']) ?>;color:#fff;font-weight:800;">
                        <?= $r['pct'] !== null ? $r['pct'] . '%' : '—' ?>
                    </span>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
