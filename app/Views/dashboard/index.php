<?php
date_default_timezone_set('America/Bogota');
include __DIR__ . '/../layouts/header.php';

$rol = $_SESSION['rol_nombre'] ?? 'Admin';
$alertas = $alertas ?? [];
$csrf_token = $csrf_token ?? '';
$empresa_nombre = $empresa_nombre ?? 'Mi Empresa';

$resumen_global = $resumen_global ?? ['total_empresas' => 0, 'empresas_activas' => 0, 'total_usuarios' => 0, 'usuarios_activos' => 0];
$asistencia_hoy = $asistencia_hoy ?? ['entradas' => 0, 'pendientes' => 0];
$resumen_dia = $resumen_dia ?? ['total_empleados' => 0, 'presentes' => 0, 'porcentaje_presentes' => 0, 'tardanzas' => 0, 'porcentaje_tardanzas' => 0, 'ausentes' => 0, 'porcentaje_ausentes' => 0, 'con_entrada' => 0, 'con_salida' => 0, 'sin_marcar' => 0];
$resumen_dia_panel = $resumen_dia_panel ?? ['total_programados'=>0,'libres_hoy'=>0,'vacaciones_hoy'=>0,'a_tiempo'=>0,'tarde'=>0,'ausente'=>0,'por_marcar'=>0,'sin_turno'=>0];
$estado_dia_detallado = is_array($estado_dia_detallado ?? null) ? $estado_dia_detallado : [];
$conteos_estado = $conteos_estado ?? ['total'=>0,'a_tiempo'=>0,'tarde'=>0,'ausente'=>0,'por_marcar'=>0,'libre'=>0,'vacaciones'=>0,'sin_turno'=>0];
$filtros = $filtros ?? ['estado'=>'','turno'=>''];

$asistencia_por_empresa = is_array($asistencia_por_empresa ?? null) ? $asistencia_por_empresa : [];
$tendencias_semanales = is_array($tendencias_semanales ?? null) ? $tendencias_semanales : [];
$asistencia_por_sede = is_array($asistencia_por_sede ?? null) ? $asistencia_por_sede : [];
$incidencias_tipo = is_array($incidencias_tipo ?? null) ? $incidencias_tipo : [];
$top_tardanzas = is_array($top_tardanzas ?? null) ? $top_tardanzas : [];
$horas_trabajadas = is_array($horas_trabajadas ?? null) ? $horas_trabajadas : [];
?>

<style>
.chart-box { position: relative; height: 280px; width: 100%; }
.panel-card { background: linear-gradient(135deg, #ffffff, #f8fafc); border: 1px solid var(--border); border-radius: 16px; padding: 18px; transition: all .2s; position: relative; overflow: hidden; }
.panel-card::before { content: ""; position: absolute; top: 0; left: 0; width: 5px; height: 100%; background: var(--accent, var(--primary)); }
.panel-card .big { font-size: 2rem; font-weight: 900; line-height: 1; color: var(--accent, var(--primary)); }
.panel-card .label { font-size: .78rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 6px; }
.estado-chip { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px; font-size: .78rem; font-weight: 700; }
.letra-chip { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 8px; font-weight: 900; color: #fff; font-size: .85rem; }
</style>

<div style="padding: 32px;">
    <div class="top-header" style="padding: 0; margin-bottom: 24px; background: transparent; border: none;">
        <h2 style="font-weight: 700; color: var(--text-dark);">
            <i class="fas fa-chart-line" style="color: var(--primary);"></i>
            Dashboard Principal
        </h2>
        <div style="display: flex; gap: 12px; align-items: center;">
            <div style="position: relative;">
                <button class="btn" style="background: #f1f5f9; padding: 8px 12px;" onclick="toggleAlertas()">
                    <i class="fas fa-bell"></i>
                    <?php if (count($alertas) > 0): ?>
                    <span style="position: absolute; top: -5px; right: -5px; background: #e53935; color: white; border-radius: 50%; width: 20px; height: 20px; font-size: 0.75rem; display: flex; align-items: center; justify-content: center; font-weight: 700;"><?= count($alertas) ?></span>
                    <?php endif; ?>
                </button>
                <div id="alertasDropdown" style="display: none; position: absolute; top: 100%; right: 0; margin-top: 8px; width: 350px; background: white; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); z-index: 1000; max-height: 400px; overflow-y: auto;">
                    <div style="padding: 16px; border-bottom: 1px solid var(--border); font-weight: 700;">
                        <i class="fas fa-bell" style="color: var(--primary);"></i> Alertas
                    </div>
                    <?php if (empty($alertas)): ?>
                    <div style="padding: 24px; text-align: center; color: var(--text-muted);">
                        <i class="fas fa-check-circle" style="font-size: 2rem; color: var(--success);"></i>
                        <p style="margin-top: 8px;">No hay alertas pendientes</p>
                    </div>
                    <?php else: foreach ($alertas as $alerta): ?>
                    <div style="padding: 12px 16px; border-bottom: 1px solid var(--border); cursor: pointer;" onclick="marcarLeida(<?= $alerta['id'] ?>)">
                        <div style="display: flex; align-items: start; gap: 10px;">
                            <i class="fas fa-<?= $alerta['tipo'] === 'warning' ? 'exclamation-triangle' : ($alerta['tipo'] === 'error' ? 'times-circle' : 'info-circle') ?>"
                               style="color: <?= $alerta['tipo'] === 'warning' ? '#ff9800' : ($alerta['tipo'] === 'error' ? '#e53935' : '#2196f3') ?>; margin-top: 3px;"></i>
                            <div style="flex: 1;">
                                <div style="font-weight: 600; font-size: 0.9rem;"><?= htmlspecialchars($alerta['titulo']) ?></div>
                                <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 4px;"><?= htmlspecialchars($alerta['mensaje']) ?></div>
                                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;"><?= date('d/m/Y H:i', strtotime($alerta['fecha_creacion'])) ?></div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
            <span class="badge" style="background: rgba(3, 169, 80, 0.1); color: var(--primary);"><?= htmlspecialchars($rol) ?></span>
        </div>
    </div>

    <?php if ($vista_global ?? false): ?>
    <!-- ============ VISTA SUPER ADMIN ============ -->
    <div style="margin-bottom: 24px;">
        <h3 style="font-weight: 700; color: var(--text-dark);"><i class="fas fa-globe" style="color: var(--primary);"></i> Vista Global Multiempresa</h3>
        <p style="color: var(--text-muted);">Resumen de toda la plataforma</p>
    </div>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 24px; margin-bottom: 32px;">
        <div class="card-3d"><div style="display:flex;justify-content:space-between;align-items:start;"><div><div style="color:var(--text-muted);font-size:.9rem;">Total Empresas</div><div style="font-size:2.5rem;font-weight:800;color:var(--primary);margin-top:8px;"><?= $resumen_global['total_empresas'] ?></div><div style="font-size:.85rem;color:var(--success);margin-top:8px;"><i class="fas fa-arrow-up"></i> <?= $resumen_global['empresas_activas'] ?> activas</div></div><div style="background:rgba(3,169,80,.1);padding:12px;border-radius:12px;"><i class="fas fa-building" style="font-size:1.5rem;color:var(--primary);"></i></div></div></div>
        <div class="card-3d"><div style="display:flex;justify-content:space-between;align-items:start;"><div><div style="color:var(--text-muted);font-size:.9rem;">Total Usuarios</div><div style="font-size:2.5rem;font-weight:800;color:var(--info);margin-top:8px;"><?= number_format($resumen_global['total_usuarios']) ?></div><div style="font-size:.85rem;color:var(--success);margin-top:8px;"><i class="fas fa-users"></i> <?= number_format($resumen_global['usuarios_activos']) ?> activos</div></div><div style="background:rgba(33,150,243,.1);padding:12px;border-radius:12px;"><i class="fas fa-users" style="font-size:1.5rem;color:var(--info);"></i></div></div></div>
        <div class="card-3d"><div style="display:flex;justify-content:space-between;align-items:start;"><div><div style="color:var(--text-muted);font-size:.9rem;">Asistencia Hoy</div><div style="font-size:2.5rem;font-weight:800;color:var(--success);margin-top:8px;"><?= $asistencia_hoy['entradas'] ?></div><div style="font-size:.85rem;color:var(--warning);margin-top:8px;"><i class="fas fa-hourglass-half"></i> <?= $asistencia_hoy['pendientes'] ?> pendientes</div></div><div style="background:rgba(40,167,69,.1);padding:12px;border-radius:12px;"><i class="fas fa-check-circle" style="font-size:1.5rem;color:var(--success);"></i></div></div></div>
        <div class="card-3d"><div style="display:flex;justify-content:space-between;align-items:start;"><div><div style="color:var(--text-muted);font-size:.9rem;">Tasa de Asistencia</div><div style="font-size:2.5rem;font-weight:800;color:var(--warning);margin-top:8px;"><?= $resumen_global['usuarios_activos'] > 0 ? round(($asistencia_hoy['entradas'] / $resumen_global['usuarios_activos']) * 100, 1) : 0 ?>%</div><div style="font-size:.85rem;color:var(--text-muted);margin-top:8px;"><i class="fas fa-percentage"></i> Del total de usuarios</div></div><div style="background:rgba(255,152,0,.1);padding:12px;border-radius:12px;"><i class="fas fa-chart-pie" style="font-size:1.5rem;color:var(--warning);"></i></div></div></div>
    </div>
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 32px;">
        <div class="card-3d"><h3 style="font-weight:700;margin-bottom:16px;"><i class="fas fa-chart-bar" style="color:var(--primary);"></i> Asistencia por Empresa</h3><div class="chart-box"><canvas id="chartEmpresas"></canvas></div></div>
        <div class="card-3d"><h3 style="font-weight:700;margin-bottom:16px;"><i class="fas fa-chart-line" style="color:var(--info);"></i> Tendencias Semanales</h3><div class="chart-box"><canvas id="chartTendencias"></canvas></div></div>
    </div>
    <?php else: ?>
    <!-- ============ VISTA ADMIN EMPRESA ============ -->
    <div style="margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
        <div>
            <h3 style="font-weight: 700; color: var(--text-dark); margin: 0;">
                <i class="fas fa-building" style="color: var(--primary);"></i> <?= htmlspecialchars($empresa_nombre) ?>
            </h3>
            <p style="color: var(--text-muted); margin: 2px 0 0 0;"><i class="fas fa-calendar-day"></i> Hoy - <?= date('d/m/Y') ?></p>
        </div>
        <a class="btn btn-primary" style="text-decoration:none;" href="/horion-time/public/dashboard/exportarDiaCSV?fecha=<?= date('Y-m-d') ?>">
            <i class="fas fa-file-csv"></i> Exportar reporte del día
        </a>
    </div>

    <!-- ===== FASE G: RESUMEN DEL DÍA SEGÚN PANEL DE TURNOS ===== -->
    <div style="margin: 20px 0 12px 0;">
        <h4 style="font-weight: 800; color: var(--text-dark); margin: 0 0 4px 0; font-size: 1rem;">
            <i class="fas fa-clipboard-check" style="color: var(--primary);"></i>
            Estado del día (según Panel de Turnos)
        </h4>
        <p style="color: var(--text-muted); font-size: .82rem; margin: 0;">
            Cruza las letras programadas (C/N/D/M/L/V) con las marcaciones reales. Tolerancia: 10 min · Ausente tras 4 h desde inicio.
        </p>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(155px, 1fr)); gap: 14px; margin-bottom: 28px;">
        <div class="panel-card" style="--accent:#03a950;">
            <div class="label"><i class="fas fa-users"></i> Programados hoy</div>
            <div class="big"><?= (int)$resumen_dia_panel['total_programados'] ?></div>
        </div>
        <div class="panel-card" style="--accent:#03a950;">
            <div class="label"><i class="fas fa-check-circle"></i> A tiempo</div>
            <div class="big"><?= (int)$resumen_dia_panel['a_tiempo'] ?></div>
        </div>
        <div class="panel-card" style="--accent:#f97316;">
            <div class="label"><i class="fas fa-clock"></i> Llegaron tarde</div>
            <div class="big"><?= (int)$resumen_dia_panel['tarde'] ?></div>
        </div>
        <div class="panel-card" style="--accent:#f59e0b;">
            <div class="label"><i class="fas fa-hourglass-half"></i> Por marcar</div>
            <div class="big"><?= (int)$resumen_dia_panel['por_marcar'] ?></div>
        </div>
        <div class="panel-card" style="--accent:#dc2626;">
            <div class="label"><i class="fas fa-times-circle"></i> Ausentes</div>
            <div class="big"><?= (int)$resumen_dia_panel['ausente'] ?></div>
        </div>
        <div class="panel-card" style="--accent:#94a3b8;">
            <div class="label"><i class="fas fa-bed"></i> Libres (L)</div>
            <div class="big"><?= (int)$resumen_dia_panel['libres_hoy'] ?></div>
        </div>
        <div class="panel-card" style="--accent:#ec4899;">
            <div class="label"><i class="fas fa-umbrella-beach"></i> Vacaciones (V)</div>
            <div class="big"><?= (int)$resumen_dia_panel['vacaciones_hoy'] ?></div>
        </div>
    </div>

    <!-- ===== TABLA ESTADO DEL DÍA ===== -->
    <div class="card-3d" style="padding: 18px; margin-bottom: 28px;">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:14px;">
            <h3 style="font-weight: 700; margin: 0;">
                <i class="fas fa-list-check" style="color: var(--primary);"></i>
                Detalle por empleado (<?= (int)$conteos_estado['total'] ?>)
            </h3>
            <form method="GET" action="/horion-time/public/dashboard" style="display:flex;gap:8px;flex-wrap:wrap;">
                <select name="estado" class="form-input" style="width:auto;padding:7px 10px;">
                    <option value="">Todos los estados</option>
                    <option value="a_tiempo"   <?= $filtros['estado']==='a_tiempo' ? 'selected' : '' ?>>✅ A tiempo (<?= (int)$conteos_estado['a_tiempo'] ?>)</option>
                    <option value="tarde"      <?= $filtros['estado']==='tarde' ? 'selected' : '' ?>>🟠 Tarde (<?= (int)$conteos_estado['tarde'] ?>)</option>
                    <option value="por_marcar" <?= $filtros['estado']==='por_marcar' ? 'selected' : '' ?>>⏳ Por marcar (<?= (int)$conteos_estado['por_marcar'] ?>)</option>
                    <option value="ausente"    <?= $filtros['estado']==='ausente' ? 'selected' : '' ?>>🔴 Ausente (<?= (int)$conteos_estado['ausente'] ?>)</option>
                    <option value="libre"      <?= $filtros['estado']==='libre' ? 'selected' : '' ?>>⚪ Libre (<?= (int)$conteos_estado['libre'] ?>)</option>
                    <option value="vacaciones" <?= $filtros['estado']==='vacaciones' ? 'selected' : '' ?>>🌴 Vacaciones (<?= (int)$conteos_estado['vacaciones'] ?>)</option>
                </select>
                <select name="turno" class="form-input" style="width:auto;padding:7px 10px;">
                    <option value="">Todos los turnos</option>
                    <option value="C" <?= $filtros['turno']==='C' ? 'selected' : '' ?>>C - Corrido</option>
                    <option value="N" <?= $filtros['turno']==='N' ? 'selected' : '' ?>>N - Noche</option>
                    <option value="D" <?= $filtros['turno']==='D' ? 'selected' : '' ?>>D - Día 8-5</option>
                    <option value="M" <?= $filtros['turno']==='M' ? 'selected' : '' ?>>M - Media jornada</option>
                    <option value="L" <?= $filtros['turno']==='L' ? 'selected' : '' ?>>L - Libre</option>
                    <option value="V" <?= $filtros['turno']==='V' ? 'selected' : '' ?>>V - Vacaciones</option>
                </select>
                <button class="btn btn-primary" type="submit" style="padding:7px 14px;"><i class="fas fa-filter"></i> Filtrar</button>
                <a class="btn" style="background:var(--bg-body);text-decoration:none;padding:7px 14px;" href="/horion-time/public/dashboard">Limpiar</a>
            </form>
        </div>

        <div class="table-container">
            <table class="data-table" style="font-size:.85rem;">
                <thead>
                    <tr>
                        <th style="text-align:left;">Empleado</th>
                        <th>Turno</th>
                        <th>Horario</th>
                        <th>Sede</th>
                        <th>Hora entrada</th>
                        <th>Estado</th>
                        <th>Diferencia</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($estado_dia_detallado)): ?>
                    <tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:32px;">
                        <i class="fas fa-calendar-xmark" style="font-size:1.8rem;opacity:.4;display:block;margin-bottom:8px;"></i>
                        No hay empleados programados para hoy en el Panel de Turnos
                    </td></tr>
                <?php else: foreach ($estado_dia_detallado as $f):
                    $est = $f['estado'];
                ?>
                    <tr>
                        <td style="text-align:left;">
                            <div style="font-weight:600;"><?= htmlspecialchars($f['nombre_completo']) ?></div>
                            <div style="font-size:.75rem;color:var(--text-muted);"><?= htmlspecialchars($f['identificacion']) ?></div>
                        </td>
                        <td style="text-align:center;">
                            <?php if (!empty($f['turno_codigo'])): ?>
                                <span class="letra-chip" style="background:<?= htmlspecialchars($f['turno_color'] ?? '#64748b') ?>;"><?= htmlspecialchars($f['turno_codigo']) ?></span>
                                <div style="font-size:.72rem;color:var(--text-muted);margin-top:3px;"><?= htmlspecialchars(substr($f['turno_nombre'] ?? '',0,22)) ?></div>
                            <?php else: ?><span style="color:var(--text-muted);">—</span><?php endif; ?>
                        </td>
                        <td style="font-family:monospace;font-weight:600;">
                            <?php if (!empty($f['hora_entrada'])): ?>
                                <?= substr($f['hora_entrada'],0,5) ?>–<?= substr($f['hora_salida'] ?? '',0,5) ?>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($f['sede_nombre'] ?? '—') ?></td>
                        <td style="font-family:monospace;font-weight:700;">
                            <?= $f['entrada_real'] ? substr($f['entrada_real'],0,5) : '—' ?>
                        </td>
                        <td>
                            <span class="estado-chip" style="background:<?= $est['color'] ?>22;color:<?= $est['color'] ?>;border:1px solid <?= $est['color'] ?>44;">
                                <i class="fas fa-<?= $est['icon'] ?>"></i>
                                <?= htmlspecialchars($est['label']) ?>
                            </span>
                        </td>
                        <td style="font-weight:700;color:<?= $est['diff'] !== null ? ($est['diff'] > 10 ? '#dc2626' : '#03a950') : 'var(--text-muted)' ?>;">
                            <?= $est['diff'] !== null ? ($est['diff'] > 0 ? '+' . $est['diff'] : $est['diff']) . ' min' : '—' ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Cards Resumen del Día (legacy) -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 24px; margin-bottom: 32px;">
        <div class="card-3d" style="border-left: 4px solid var(--primary);"><div style="color:var(--text-muted);font-size:.9rem;"><i class="fas fa-users"></i> Total Empleados</div><div style="font-size:2.5rem;font-weight:800;color:var(--text-dark);margin-top:8px;"><?= number_format($resumen_dia['total_empleados']) ?></div></div>
        <div class="card-3d" style="border-left: 4px solid var(--success);"><div style="color:var(--text-muted);font-size:.9rem;"><i class="fas fa-check-circle"></i> Presentes</div><div style="font-size:2.5rem;font-weight:800;color:var(--success);margin-top:8px;"><?= number_format($resumen_dia['presentes']) ?></div><div style="font-size:.85rem;color:var(--success);margin-top:4px;"><?= $resumen_dia['porcentaje_presentes'] ?>%</div></div>
        <div class="card-3d" style="border-left: 4px solid var(--warning);"><div style="color:var(--text-muted);font-size:.9rem;"><i class="fas fa-clock"></i> Llegadas Tarde</div><div style="font-size:2.5rem;font-weight:800;color:var(--warning);margin-top:8px;"><?= number_format($resumen_dia['tardanzas']) ?></div></div>
        <div class="card-3d" style="border-left: 4px solid var(--danger);"><div style="color:var(--text-muted);font-size:.9rem;"><i class="fas fa-times-circle"></i> Ausentes</div><div style="font-size:2.5rem;font-weight:800;color:var(--danger);margin-top:8px;"><?= number_format($resumen_dia['ausentes']) ?></div></div>
        <div class="card-3d" style="border-left: 4px solid var(--info);"><div style="color:var(--text-muted);font-size:.9rem;"><i class="fas fa-coffee"></i> En Descanso</div><div style="font-size:2rem;font-weight:800;color:var(--info);margin-top:8px;"><?= max(0, $resumen_dia['con_entrada'] - $resumen_dia['con_salida']) ?></div></div>
    </div>
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 32px;">
        <div class="card-3d"><h3 style="font-weight:700;margin-bottom:16px;"><i class="fas fa-chart-pie" style="color:var(--primary);"></i> Asistencia por Sede</h3><div class="chart-box"><canvas id="chartSedes"></canvas></div></div>
        <div class="card-3d"><h3 style="font-weight:700;margin-bottom:16px;"><i class="fas fa-chart-donut" style="color:var(--warning);"></i> Incidencias por Tipo (Este Mes)</h3><div class="chart-box"><canvas id="chartIncidencias"></canvas></div></div>
    </div>
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
        <div class="card-3d">
            <h3 style="font-weight:700;margin-bottom:16px;"><i class="fas fa-exclamation-triangle" style="color:var(--warning);"></i> Top 10 Llegadas Tarde</h3>
            <div style="max-height: 300px; overflow-y: auto;">
                <?php if (empty($top_tardanzas)): ?>
                <div style="text-align:center;padding:32px;color:var(--text-muted);"><i class="fas fa-check-circle" style="font-size:2rem;color:var(--success);"></i><p style="margin-top:8px;">¡Excelente! No hay tardanzas hoy</p></div>
                <?php else: foreach ($top_tardanzas as $index => $tardanza): ?>
                <div style="display:flex;align-items:center;gap:12px;padding:12px;border-bottom:1px solid var(--border);">
                    <div style="width:32px;height:32px;background:<?= $index < 3 ? '#fee2e2' : '#f1f5f9' ?>;color:<?= $index < 3 ? '#dc2626' : '#64748b' ?>;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.9rem;"><?= $index + 1 ?></div>
                    <div style="flex:1;"><div style="font-weight:600;font-size:.9rem;"><?= htmlspecialchars($tardanza['empleado']) ?></div><div style="font-size:.8rem;color:var(--text-muted);">Programada: <?= $tardanza['hora_programada'] ?> | Real: <?= $tardanza['hora_real'] ?></div></div>
                    <div style="text-align:right;"><div style="font-weight:700;color:var(--danger);font-size:1.1rem;">+<?= $tardanza['minutos_tarde'] ?> min</div></div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
        <div class="card-3d"><h3 style="font-weight:700;margin-bottom:16px;"><i class="fas fa-chart-area" style="color:var(--info);"></i> Horas Trabajadas vs Programadas</h3><div class="chart-box"><canvas id="chartHoras"></canvas></div></div>
    </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
Chart.defaults.animation = false;
Chart.defaults.resizeDelay = 250;
document.addEventListener('DOMContentLoaded', function() {
    function toggleAlertas() {
        const d = document.getElementById('alertasDropdown');
        d.style.display = d.style.display === 'none' ? 'block' : 'none';
    }
    window.toggleAlertas = toggleAlertas;
    window.marcarLeida = function(id) {
        fetch('/horion-time/public/dashboard/marcarLeida', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'csrf_token=<?= $csrf_token ?>&id='+id }).then(r=>r.json()).then(d=>{if(d.success)location.reload();});
    };
    document.addEventListener('click', function(e) {
        const d = document.getElementById('alertasDropdown');
        if (d && !e.target.closest('.btn') && !e.target.closest('#alertasDropdown')) d.style.display = 'none';
    });
    <?php if ($vista_global ?? false): ?>
    const datosEmpresas = <?= json_encode($asistencia_por_empresa ?? []) ?>;
    const cE = document.getElementById('chartEmpresas');
    if (cE && Array.isArray(datosEmpresas) && datosEmpresas.length) new Chart(cE, { type:'bar', data:{ labels:datosEmpresas.map(e=>e.empresa), datasets:[{label:'Presentes',data:datosEmpresas.map(e=>e.presentes),backgroundColor:'rgba(3,169,80,.8)',borderColor:'#03a950',borderWidth:2,borderRadius:8},{label:'Total Empleados',data:datosEmpresas.map(e=>e.total_empleados),backgroundColor:'rgba(200,200,200,.3)',borderColor:'#ccc',borderWidth:2,borderRadius:8}]}, options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'top'}},scales:{y:{beginAtZero:true},x:{grid:{display:false}}}} });
    const datosT = <?= json_encode($tendencias_semanales ?? []) ?>;
    const cT = document.getElementById('chartTendencias');
    if (cT && Array.isArray(datosT) && datosT.length) new Chart(cT, { type:'line', data:{ labels:datosT.map(t=>t.fecha), datasets:[{label:'Entradas',data:datosT.map(t=>t.entradas),borderColor:'#03a950',backgroundColor:'rgba(3,169,80,.1)',borderWidth:3,fill:true,tension:.4}]}, options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'top'}},scales:{y:{beginAtZero:true},x:{grid:{display:false}}}} });
    <?php else: ?>
    const dS = <?= json_encode($asistencia_por_sede ?? []) ?>;
    const cS = document.getElementById('chartSedes');
    if (cS && Array.isArray(dS) && dS.length) new Chart(cS, { type:'pie', data:{ labels:dS.map(s=>s.sede), datasets:[{data:dS.map(s=>s.presentes),backgroundColor:['#03a950','#2196f3','#ff9800','#e53935','#9c27b0','#00bcd4'],borderWidth:3,borderColor:'#fff'}]}, options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'right'}}} });
    const dI = <?= json_encode($incidencias_tipo ?? []) ?>;
    const cI = document.getElementById('chartIncidencias');
    if (cI && Array.isArray(dI) && dI.length) new Chart(cI, { type:'doughnut', data:{ labels:dI.map(i=>i.tipo), datasets:[{data:dI.map(i=>i.total),backgroundColor:['#e53935','#03a950','#2196f3','#ff9800','#9c27b0','#00bcd4','#795548'],borderWidth:3,borderColor:'#fff'}]}, options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'right'}},cutout:'60%'} });
    const dH = <?= json_encode($horas_trabajadas ?? []) ?>;
    const cH = document.getElementById('chartHoras');
    if (cH && Array.isArray(dH) && dH.length) new Chart(cH, { type:'line', data:{ labels:dH.map(h=>h.fecha), datasets:[{label:'Horas Programadas',data:dH.map(h=>h.horas_programadas),borderColor:'#2196f3',backgroundColor:'rgba(33,150,243,.1)',borderWidth:2,fill:false,tension:.4},{label:'Horas Trabajadas',data:dH.map(h=>h.horas_trabajadas_estimadas),borderColor:'#03a950',backgroundColor:'rgba(3,169,80,.1)',borderWidth:2,fill:true,tension:.4}]}, options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'top'}},scales:{y:{beginAtZero:true}}} });
    <?php endif; ?>
});
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
