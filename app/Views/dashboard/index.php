<?php
// Configurar zona horaria Colombia
date_default_timezone_set('America/Bogota');

include __DIR__ . '/../layouts/header.php';

// BLINDAJE: Si las variables no existen, usar valores por defecto
$rol = $_SESSION['rol_nombre'] ?? 'Admin';
$alertas = $alertas ?? [];
$csrf_token = $csrf_token ?? '';
$empresa_nombre = $empresa_nombre ?? 'Mi Empresa';

$resumen_global = $resumen_global ?? ['total_empresas' => 0, 'empresas_activas' => 0, 'total_usuarios' => 0, 'usuarios_activos' => 0];
$asistencia_hoy = $asistencia_hoy ?? ['entradas' => 0, 'pendientes' => 0];
$resumen_dia = $resumen_dia ?? ['total_empleados' => 0, 'presentes' => 0, 'porcentaje_presentes' => 0, 'tardanzas' => 0, 'porcentaje_tardanzas' => 0, 'ausentes' => 0, 'porcentaje_ausentes' => 0, 'con_entrada' => 0, 'con_salida' => 0, 'sin_marcar' => 0];

// Validación estricta de arrays
$asistencia_por_empresa = is_array($asistencia_por_empresa ?? null) ? $asistencia_por_empresa : [];
$tendencias_semanales = is_array($tendencias_semanales ?? null) ? $tendencias_semanales : [];
$asistencia_por_sede = is_array($asistencia_por_sede ?? null) ? $asistencia_por_sede : [];
$incidencias_tipo = is_array($incidencias_tipo ?? null) ? $incidencias_tipo : [];
$top_tardanzas = is_array($top_tardanzas ?? null) ? $top_tardanzas : [];
$horas_trabajadas = is_array($horas_trabajadas ?? null) ? $horas_trabajadas : [];
?>

<!-- Estilos para contenedores de gráficos -->
<style>
.chart-box { position: relative; height: 280px; width: 100%; }
</style>

<!-- El contenido va DENTRO del content-wrapper que ya abrió header.php -->
<div style="padding: 32px;">
    <div class="top-header" style="padding: 0; margin-bottom: 24px; background: transparent; border: none;">
        <h2 style="font-weight: 700; color: var(--text-dark);">
            <i class="fas fa-chart-line" style="color: var(--primary);"></i>
            Dashboard Principal
        </h2>
        <div style="display: flex; gap: 12px; align-items: center;">
            <!-- Campana de alertas -->
            <div style="position: relative;">
                <button class="btn" style="background: #f1f5f9; padding: 8px 12px;" onclick="toggleAlertas()">
                    <i class="fas fa-bell"></i>
                    <?php if (count($alertas) > 0): ?>
                    <span style="position: absolute; top: -5px; right: -5px; background: #e53935; color: white; border-radius: 50%; width: 20px; height: 20px; font-size: 0.75rem; display: flex; align-items: center; justify-content: center; font-weight: 700;">
                        <?= count($alertas) ?>
                    </span>
                    <?php endif; ?>
                </button>
                <!-- Dropdown de alertas -->
                <div id="alertasDropdown" style="display: none; position: absolute; top: 100%; right: 0; margin-top: 8px; width: 350px; background: white; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); z-index: 1000; max-height: 400px; overflow-y: auto;">
                    <div style="padding: 16px; border-bottom: 1px solid var(--border); font-weight: 700;">
                        <i class="fas fa-bell" style="color: var(--primary);"></i> Alertas
                    </div>
                    <?php if (empty($alertas)): ?>
                    <div style="padding: 24px; text-align: center; color: var(--text-muted);">
                        <i class="fas fa-check-circle" style="font-size: 2rem; color: var(--success);"></i>
                        <p style="margin-top: 8px;">No hay alertas pendientes</p>
                    </div>
                    <?php else: ?>
                    <?php foreach ($alertas as $alerta): ?>
                    <div style="padding: 12px 16px; border-bottom: 1px solid var(--border); cursor: pointer;" onclick="marcarLeida(<?= $alerta['id'] ?>)">
                        <div style="display: flex; align-items: start; gap: 10px;">
                            <i class="fas fa-<?= $alerta['tipo'] === 'warning' ? 'exclamation-triangle' : ($alerta['tipo'] === 'error' ? 'times-circle' : 'info-circle') ?>"
                               style="color: <?= $alerta['tipo'] === 'warning' ? '#ff9800' : ($alerta['tipo'] === 'error' ? '#e53935' : '#2196f3') ?>; margin-top: 3px;"></i>
                            <div style="flex: 1;">
                                <div style="font-weight: 600; font-size: 0.9rem;"><?= htmlspecialchars($alerta['titulo']) ?></div>
                                <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 4px;"><?= htmlspecialchars($alerta['mensaje']) ?></div>
                                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">
                                    <?= date('d/m/Y H:i', strtotime($alerta['fecha_creacion'])) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <span class="badge" style="background: rgba(3, 169, 80, 0.1); color: var(--primary);">
                <?= htmlspecialchars($rol) ?>
            </span>
        </div>
    </div>

    <?php if ($vista_global ?? false): ?>
    <!-- ============ VISTA SUPER ADMIN ============ -->
    <div style="margin-bottom: 24px;">
        <h3 style="font-weight: 700; color: var(--text-dark);">
            <i class="fas fa-globe" style="color: var(--primary);"></i>
            Vista Global Multiempresa
        </h3>
        <p style="color: var(--text-muted);">Resumen de toda la plataforma</p>
    </div>
    <!-- Cards Resumen Global -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 24px; margin-bottom: 32px;">
        <div class="card-3d">
            <div style="display: flex; justify-content: space-between; align-items: start;">
                <div>
                    <div style="color: var(--text-muted); font-size: 0.9rem;">Total Empresas</div>
                    <div style="font-size: 2.5rem; font-weight: 800; color: var(--primary); margin-top: 8px;">
                        <?= $resumen_global['total_empresas'] ?>
                    </div>
                    <div style="font-size: 0.85rem; color: var(--success); margin-top: 8px;">
                        <i class="fas fa-arrow-up"></i> <?= $resumen_global['empresas_activas'] ?> activas
                    </div>
                </div>
                <div style="background: rgba(3, 169, 80, 0.1); padding: 12px; border-radius: 12px;">
                    <i class="fas fa-building" style="font-size: 1.5rem; color: var(--primary);"></i>
                </div>
            </div>
        </div>
        <div class="card-3d">
            <div style="display: flex; justify-content: space-between; align-items: start;">
                <div>
                    <div style="color: var(--text-muted); font-size: 0.9rem;">Total Usuarios</div>
                    <div style="font-size: 2.5rem; font-weight: 800; color: var(--info); margin-top: 8px;">
                        <?= number_format($resumen_global['total_usuarios']) ?>
                    </div>
                    <div style="font-size: 0.85rem; color: var(--success); margin-top: 8px;">
                        <i class="fas fa-users"></i> <?= number_format($resumen_global['usuarios_activos']) ?> activos
                    </div>
                </div>
                <div style="background: rgba(33, 150, 243, 0.1); padding: 12px; border-radius: 12px;">
                    <i class="fas fa-users" style="font-size: 1.5rem; color: var(--info);"></i>
                </div>
            </div>
        </div>
        <div class="card-3d">
            <div style="display: flex; justify-content: space-between; align-items: start;">
                <div>
                    <div style="color: var(--text-muted); font-size: 0.9rem;">Asistencia Hoy</div>
                    <div style="font-size: 2.5rem; font-weight: 800; color: var(--success); margin-top: 8px;">
                        <?= $asistencia_hoy['entradas'] ?>
                    </div>
                    <div style="font-size: 0.85rem; color: var(--warning); margin-top: 8px;">
                        <i class="fas fa-hourglass-half"></i> <?= $asistencia_hoy['pendientes'] ?> pendientes
                    </div>
                </div>
                <div style="background: rgba(40, 167, 69, 0.1); padding: 12px; border-radius: 12px;">
                    <i class="fas fa-check-circle" style="font-size: 1.5rem; color: var(--success);"></i>
                </div>
            </div>
        </div>
        <div class="card-3d">
            <div style="display: flex; justify-content: space-between; align-items: start;">
                <div>
                    <div style="color: var(--text-muted); font-size: 0.9rem;">Tasa de Asistencia</div>
                    <div style="font-size: 2.5rem; font-weight: 800; color: var(--warning); margin-top: 8px;">
                        <?= $resumen_global['usuarios_activos'] > 0 ? round(($asistencia_hoy['entradas'] / $resumen_global['usuarios_activos']) * 100, 1) : 0 ?>%
                    </div>
                    <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 8px;">
                        <i class="fas fa-percentage"></i> Del total de usuarios
                    </div>
                </div>
                <div style="background: rgba(255, 152, 0, 0.1); padding: 12px; border-radius: 12px;">
                    <i class="fas fa-chart-pie" style="font-size: 1.5rem; color: var(--warning);"></i>
                </div>
            </div>
        </div>
    </div>
    <!-- Gráficos Super Admin -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 32px;">
        <!-- Asistencia por Empresa -->
        <div class="card-3d">
            <h3 style="font-weight: 700; margin-bottom: 16px;">
                <i class="fas fa-chart-bar" style="color: var(--primary);"></i>
                Asistencia por Empresa
            </h3>
            <div class="chart-box"><canvas id="chartEmpresas"></canvas></div>
        </div>
        <!-- Tendencias Semanales -->
        <div class="card-3d">
            <h3 style="font-weight: 700; margin-bottom: 16px;">
                <i class="fas fa-chart-line" style="color: var(--info);"></i>
                Tendencias Semanales
            </h3>
            <div class="chart-box"><canvas id="chartTendencias"></canvas></div>
        </div>
    </div>
    <?php else: ?>
    <!-- ============ VISTA ADMIN EMPRESA ============ -->
    <div style="margin-bottom: 24px;">
        <h3 style="font-weight: 700; color: var(--text-dark);">
            <i class="fas fa-building" style="color: var(--primary);"></i>
            <?= htmlspecialchars($empresa_nombre) ?>
        </h3>
        <p style="color: var(--text-muted);">
            <i class="fas fa-calendar-day"></i> Hoy - <?= date('d/m/Y') ?>
        </p>
    </div>
    <!-- Cards Resumen del Día -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 24px; margin-bottom: 32px;">
        <div class="card-3d" style="border-left: 4px solid var(--primary);">
            <div style="color: var(--text-muted); font-size: 0.9rem;">
                <i class="fas fa-users"></i> Total Empleados
            </div>
            <div style="font-size: 2.5rem; font-weight: 800; color: var(--text-dark); margin-top: 8px;">
                <?= number_format($resumen_dia['total_empleados']) ?>
            </div>
        </div>
        <div class="card-3d" style="border-left: 4px solid var(--success);">
            <div style="color: var(--text-muted); font-size: 0.9rem;">
                <i class="fas fa-check-circle"></i> Presentes
            </div>
            <div style="font-size: 2.5rem; font-weight: 800; color: var(--success); margin-top: 8px;">
                <?= number_format($resumen_dia['presentes']) ?>
            </div>
            <div style="font-size: 0.85rem; color: var(--success); margin-top: 4px;">
                <?= $resumen_dia['porcentaje_presentes'] ?>%
            </div>
        </div>
        <div class="card-3d" style="border-left: 4px solid var(--warning);">
            <div style="color: var(--text-muted); font-size: 0.9rem;">
                <i class="fas fa-clock"></i> Llegadas Tarde
            </div>
            <div style="font-size: 2.5rem; font-weight: 800; color: var(--warning); margin-top: 8px;">
                <?= number_format($resumen_dia['tardanzas']) ?>
            </div>
            <div style="font-size: 0.85rem; color: var(--warning); margin-top: 4px;">
                <?= $resumen_dia['porcentaje_tardanzas'] ?>%
            </div>
        </div>
        <div class="card-3d" style="border-left: 4px solid var(--danger);">
            <div style="color: var(--text-muted); font-size: 0.9rem;">
                <i class="fas fa-times-circle"></i> Ausentes
            </div>
            <div style="font-size: 2.5rem; font-weight: 800; color: var(--danger); margin-top: 8px;">
                <?= number_format($resumen_dia['ausentes']) ?>
            </div>
            <div style="font-size: 0.85rem; color: var(--danger); margin-top: 4px;">
                <?= $resumen_dia['porcentaje_ausentes'] ?>%
            </div>
        </div>
        <div class="card-3d" style="border-left: 4px solid var(--info);">
            <div style="color: var(--text-muted); font-size: 0.9rem;">
                <i class="fas fa-coffee"></i> En Descanso
            </div>
            <div style="font-size: 2rem; font-weight: 800; color: var(--info); margin-top: 8px;">
                <?= max(0, $resumen_dia['con_entrada'] - $resumen_dia['con_salida']) ?>
            </div>
        </div>
        <div class="card-3d" style="border-left: 4px solid #9e9e9e;">
            <div style="color: var(--text-muted); font-size: 0.9rem;">
                <i class="fas fa-question-circle"></i> Sin Marcar
            </div>
            <div style="font-size: 2rem; font-weight: 800; color: #9e9e9e; margin-top: 8px;">
                <?= number_format($resumen_dia['sin_marcar']) ?>
            </div>
        </div>
    </div>
    <!-- Gráficos Admin Empresa -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 32px;">
        <!-- Asistencia por Sede -->
        <div class="card-3d">
            <h3 style="font-weight: 700; margin-bottom: 16px;">
                <i class="fas fa-chart-pie" style="color: var(--primary);"></i>
                Asistencia por Sede
            </h3>
            <div class="chart-box"><canvas id="chartSedes"></canvas></div>
        </div>
        <!-- Incidencias por Tipo -->
        <div class="card-3d">
            <h3 style="font-weight: 700; margin-bottom: 16px;">
                <i class="fas fa-chart-donut" style="color: var(--warning);"></i>
                Incidencias por Tipo (Este Mes)
            </h3>
            <div class="chart-box"><canvas id="chartIncidencias"></canvas></div>
        </div>
    </div>
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
        <!-- Top Tardanzas -->
        <div class="card-3d">
            <h3 style="font-weight: 700; margin-bottom: 16px;">
                <i class="fas fa-exclamation-triangle" style="color: var(--warning);"></i>
                Top 10 Llegadas Tarde
            </h3>
            <div style="max-height: 300px; overflow-y: auto;">
                <?php if (empty($top_tardanzas)): ?>
                <div style="text-align: center; padding: 32px; color: var(--text-muted);">
                    <i class="fas fa-check-circle" style="font-size: 2rem; color: var(--success);"></i>
                    <p style="margin-top: 8px;">¡Excelente! No hay tardanzas hoy</p>
                </div>
                <?php else: ?>
                <?php foreach ($top_tardanzas as $index => $tardanza): ?>
                <div style="display: flex; align-items: center; gap: 12px; padding: 12px; border-bottom: 1px solid var(--border);">
                    <div style="width: 32px; height: 32px; background: <?= $index < 3 ? '#fee2e2' : '#f1f5f9' ?>; color: <?= $index < 3 ? '#dc2626' : '#64748b' ?>; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.9rem;">
                        <?= $index + 1 ?>
                    </div>
                    <div style="flex: 1;">
                        <div style="font-weight: 600; font-size: 0.9rem;"><?= htmlspecialchars($tardanza['empleado']) ?></div>
                        <div style="font-size: 0.8rem; color: var(--text-muted);">
                            Programada: <?= $tardanza['hora_programada'] ?> | Real: <?= $tardanza['hora_real'] ?>
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-weight: 700; color: var(--danger); font-size: 1.1rem;">
                            +<?= $tardanza['minutos_tarde'] ?> min
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <!-- Horas Trabajadas vs Programadas -->
        <div class="card-3d">
            <h3 style="font-weight: 700; margin-bottom: 16px;">
                <i class="fas fa-chart-area" style="color: var(--info);"></i>
                Horas Trabajadas vs Programadas
            </h3>
            <div class="chart-box"><canvas id="chartHoras"></canvas></div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// SOLUCIÓN AL BUCLE: Desactivar animaciones pesadas
Chart.defaults.animation = false;
Chart.defaults.resizeDelay = 250;

// Esperar a que el DOM esté completamente cargado antes de renderizar
document.addEventListener('DOMContentLoaded', function() {
    // Toggle de alertas
    function toggleAlertas() {
        const dropdown = document.getElementById('alertasDropdown');
        dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
    }
    window.toggleAlertas = toggleAlertas;

    // Marcar alerta como leída
    window.marcarLeida = function(id) {
        fetch('/horion-time/public/dashboard/marcarLeida', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'csrf_token=<?= $csrf_token ?>&id=' + id
        }).then(r => r.json()).then(d => { if(d.success) location.reload(); });
    };

    // Cerrar dropdown al hacer clic fuera
    document.addEventListener('click', function(e) {
        const dropdown = document.getElementById('alertasDropdown');
        if (!e.target.closest('.btn') && !e.target.closest('#alertasDropdown')) {
            dropdown.style.display = 'none';
        }
    });

    <?php if ($vista_global ?? false): ?>
    // ============ GRÁFICOS SUPER ADMIN ============
    // Gráfico Asistencia por Empresa - SOLO si hay datos
    const datosEmpresas = <?= json_encode($asistencia_por_empresa ?? []) ?>;
    const canvasEmpresas = document.getElementById('chartEmpresas');
    if (canvasEmpresas && Array.isArray(datosEmpresas) && datosEmpresas.length > 0) {
        new Chart(canvasEmpresas, {
            type: 'bar',
            data: {
                labels: datosEmpresas.map(e => e.empresa),
                datasets: [{
                    label: 'Presentes',
                    data: datosEmpresas.map(e => e.presentes),
                    backgroundColor: 'rgba(3, 169, 80, 0.8)',
                    borderColor: '#03a950',
                    borderWidth: 2,
                    borderRadius: 8
                }, {
                    label: 'Total Empleados',
                    data: datosEmpresas.map(e => e.total_empleados),
                    backgroundColor: 'rgba(200, 200, 200, 0.3)',
                    borderColor: '#cccccc',
                    borderWidth: 2,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'top' } },
                scales: { y: { beginAtZero: true }, x: { grid: { display: false } } }
            }
        });
    } else if (canvasEmpresas) {
        canvasEmpresas.closest('.chart-box').innerHTML = '<p style="text-align:center;padding:40px;color:var(--text-muted);">Sin datos para mostrar</p>';
    }

    // Gráfico Tendencias Semanales - SOLO si hay datos
    const datosTendencias = <?= json_encode($tendencias_semanales ?? []) ?>;
    const canvasTendencias = document.getElementById('chartTendencias');
    if (canvasTendencias && Array.isArray(datosTendencias) && datosTendencias.length > 0) {
        new Chart(canvasTendencias, {
            type: 'line',
            data: {
                labels: datosTendencias.map(t => t.fecha),
                datasets: [{
                    label: 'Entradas',
                    data: datosTendencias.map(t => t.entradas),
                    borderColor: '#03a950',
                    backgroundColor: 'rgba(3, 169, 80, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'top' } },
                scales: { y: { beginAtZero: true }, x: { grid: { display: false } } }
            }
        });
    } else if (canvasTendencias) {
        canvasTendencias.closest('.chart-box').innerHTML = '<p style="text-align:center;padding:40px;color:var(--text-muted);">Sin datos para mostrar</p>';
    }
    <?php else: ?>
    // ============ GRÁFICOS ADMIN EMPRESA ============
    const datosSedes = <?= json_encode($asistencia_por_sede ?? []) ?>;
    const canvasSedes = document.getElementById('chartSedes');
    if (canvasSedes && Array.isArray(datosSedes) && datosSedes.length > 0) {
        new Chart(canvasSedes, {
            type: 'pie',
            data: {
                labels: datosSedes.map(s => s.sede),
                datasets: [{
                    data: datosSedes.map(s => s.presentes),
                    backgroundColor: ['#03a950', '#2196f3', '#ff9800', '#e53935', '#9c27b0', '#00bcd4'],
                    borderWidth: 3,
                    borderColor: '#ffffff'
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right' } } }
        });
    } else if (canvasSedes) {
        canvasSedes.closest('.chart-box').innerHTML = '<p style="text-align:center;padding:40px;color:var(--text-muted);">Sin datos</p>';
    }

    const datosIncidencias = <?= json_encode($incidencias_tipo ?? []) ?>;
    const canvasIncidencias = document.getElementById('chartIncidencias');
    if (canvasIncidencias && Array.isArray(datosIncidencias) && datosIncidencias.length > 0) {
        new Chart(canvasIncidencias, {
            type: 'doughnut',
            data: {
                labels: datosIncidencias.map(i => i.tipo),
                datasets: [{
                    data: datosIncidencias.map(i => i.total),
                    backgroundColor: ['#e53935', '#03a950', '#2196f3', '#ff9800', '#9c27b0', '#00bcd4', '#795548'],
                    borderWidth: 3,
                    borderColor: '#ffffff'
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right' } }, cutout: '60%' }
        });
    } else if (canvasIncidencias) {
        canvasIncidencias.closest('.chart-box').innerHTML = '<p style="text-align:center;padding:40px;color:var(--text-muted);">Sin datos</p>';
    }

    const datosHoras = <?= json_encode($horas_trabajadas ?? []) ?>;
    const canvasHoras = document.getElementById('chartHoras');
    if (canvasHoras && Array.isArray(datosHoras) && datosHoras.length > 0) {
        new Chart(canvasHoras, {
            type: 'line',
            data: {
                labels: datosHoras.map(h => h.fecha),
                datasets: [{
                    label: 'Horas Programadas',
                    data: datosHoras.map(h => h.horas_programadas),
                    borderColor: '#2196f3',
                    backgroundColor: 'rgba(33, 150, 243, 0.1)',
                    borderWidth: 2,
                    fill: false,
                    tension: 0.4
                }, {
                    label: 'Horas Trabajadas',
                    data: datosHoras.map(h => h.horas_trabajadas_estimadas),
                    borderColor: '#03a950',
                    backgroundColor: 'rgba(3, 169, 80, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'top' } },
                scales: { y: { beginAtZero: true } }
            }
        });
    } else if (canvasHoras) {
        canvasHoras.closest('.chart-box').innerHTML = '<p style="text-align:center;padding:40px;color:var(--text-muted);">Sin datos</p>';
    }
    <?php endif; ?>
});
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>