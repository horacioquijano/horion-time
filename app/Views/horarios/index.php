<?php
session_start();
include(__DIR__ . '/../layouts/header.php');
include(__DIR__ . '/../layouts/sidebar.php');

// =====================================================================
// AUTO-ABASTECIMIENTO: turnos agrupados por horario
// =====================================================================
$turnosPorHorario = [];
try {
    if (isset($db) && is_object($db)) {
        $__pdo = $db;
    } else {
        $__pdo = \App\Config\Database::getInstance()->getConnection();
    }
    $__rows = $__pdo->query("SELECT horario_id, dia_semana, hora_entrada, hora_salida, es_descanso FROM turnos ORDER BY horario_id, dia_semana")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($__rows as $__t) {
        $turnosPorHorario[(int)$__t['horario_id']][] = $__t;
    }
} catch (Throwable $e) { /* si falla, la vista igual carga */ }
?>

<main class="main-content">
<div class="top-header">
    <h2 style="font-weight: 700; color: var(--text-dark);">
        <i class="fas fa-clock" style="color: var(--primary);"></i>
        Gestión de Horarios y Turnos
    </h2>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <a href="/horion-time/public/horarios/panelTurnos" class="btn" style="background:rgba(3,169,80,.10);color:var(--primary);font-weight:700;text-decoration:none;">
            <i class="fas fa-calendar-week"></i> Panel de Turnos
        </a>
        <button class="btn btn-primary" onclick="openModal('modalCrearHorario')">
            <i class="fas fa-plus"></i> Crear Horario
        </button>
    </div>
</div>

<div style="padding: 32px;">
    <?php if (isset($_GET['success'])): ?>
    <div class="card-3d" style="background: #dcfce7; border-left: 4px solid var(--success); margin-bottom: 24px; color: #166534;">
        <i class="fas fa-check-circle"></i> Operación realizada exitosamente
    </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['error'])): ?>
    <div class="card-3d" style="background: #fef2f2; border-left: 4px solid #dc2626; margin-bottom: 24px; color: #991b1b;">
        <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($_GET['error']) ?>
    </div>
    <?php endif; ?>
    
    <!-- Tabs -->
    <div style="display: flex; gap: 8px; margin-bottom: 24px; border-bottom: 2px solid var(--border);">
        <button class="tab-btn active" onclick="cambiarTab('horarios', this)">📋 Horarios</button>
        <button class="tab-btn" onclick="cambiarTab('asignaciones', this)">👥 Asignaciones</button>
    </div>
    
    <!-- TAB 1: Horarios -->
    <div class="tab-content" id="tab-horarios">
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 24px;">
            <?php foreach ($horarios as $h): ?>
            <div class="card-3d">
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 16px;">
                    <div>
                        <h3 style="font-weight: 700; margin: 0;"><?= htmlspecialchars($h['nombre']) ?></h3>
                        <p style="color: var(--text-muted); font-size: 0.9rem; margin: 4px 0 0 0;">
                            <?= htmlspecialchars($h['descripcion'] ?? 'Sin descripción') ?>
                        </p>
                    </div>
                    <span class="badge badge-success"><?= $h['empleados_asignados'] ?> empleados</span>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                    <div style="background: #f8fafc; padding: 12px; border-radius: 8px;">
                        <div style="font-size: 0.8rem; color: var(--text-muted);">Horas Semanales</div>
                        <div style="font-size: 1.2rem; font-weight: 700;"><?= $h['horas_semanales'] ?>h</div>
                    </div>
                    <div style="background: #f8fafc; padding: 12px; border-radius: 8px;">
                        <div style="font-size: 0.8rem; color: var(--text-muted);">Estado</div>
                        <div style="font-size: 1.2rem; font-weight: 700; text-transform: capitalize;"><?= $h['estado'] ?></div>
                    </div>
                </div>
                
                <button class="btn" style="background: var(--bg-body); width: 100%;" onclick="verDetalleHorario(<?= $h['id'] ?>)">
                    <i class="fas fa-eye"></i> Ver Turnos
                </button>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- TAB 2: Asignaciones -->
    <div class="tab-content" id="tab-asignaciones" style="display: none;">
        <div class="card-3d">
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Empleado</th>
                            <th>Horario Actual</th>
                            <th>Fecha Inicio</th>
                            <th>Fecha Fin</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($empleados as $emp): ?>
                        <tr>
                            <td>
                                <div style="font-weight: 600;"><?= htmlspecialchars($emp['nombre_completo']) ?></div>
                                <div style="font-size: 0.8rem; color: var(--text-muted);"><?= htmlspecialchars($emp['identificacion']) ?></div>
                            </td>
                            <td>
                                <?php if ($emp['horario_nombre']): ?>
                                <span class="badge badge-info"><?= htmlspecialchars($emp['horario_nombre']) ?></span>
                                <?php else: ?>
                                <span class="badge badge-danger">Sin horario</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $emp['fecha_inicio'] ? date('d/m/Y', strtotime($emp['fecha_inicio'])) : '—' ?></td>
                            <td><?= $emp['fecha_fin'] ? date('d/m/Y', strtotime($emp['fecha_fin'])) : 'Indefinido' ?></td>
                            <td>
                                <button class="btn btn-primary" style="padding: 6px 12px;" onclick="openAsignarModal(<?= $emp['id'] ?>, '<?= htmlspecialchars($emp['nombre_completo']) ?>')">
                                    <i class="fas fa-calendar-plus"></i> Asignar
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</main>

<!-- Modal Crear Horario -->
<div class="modal-overlay" id="modalCrearHorario">
    <div class="modal-content" style="max-width: 800px;">
        <h3 style="font-weight: 700; margin-bottom: 24px;">Crear Nuevo Horario</h3>
        <form action="/horion-time/public/horarios/crear" method="POST">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px;">
                <div>
                    <label class="form-label">Nombre del Horario *</label>
                    <input type="text" name="nombre" class="form-input" required placeholder="Ej: Turno Diurno">
                </div>
                <div>
                    <label class="form-label">Horas Semanales</label>
                    <input type="number" name="horas_semanales" class="form-input" value="48" step="0.5">
                </div>
                <div style="grid-column: 1/-1;">
                    <label class="form-label">Descripción</label>
                    <textarea name="descripcion" class="form-input" rows="2" placeholder="Descripción opcional..."></textarea>
                </div>
            </div>
            
            <h4 style="margin-bottom: 16px;">Configurar Turnos por Día</h4>
            <?php
            $dias = ['domingo' => 'Domingo', 'lunes' => 'Lunes', 'martes' => 'Martes', 'miercoles' => 'Miércoles', 'jueves' => 'Jueves', 'viernes' => 'Viernes', 'sabado' => 'Sábado'];
            foreach ($dias as $key => $nombre):
            ?>
            <div style="background: #f8fafc; padding: 16px; border-radius: 10px; margin-bottom: 12px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                    <strong><?= $nombre ?></strong>
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="<?= $key ?>_descanso" value="1" onchange="toggleDia('<?= $key ?>')">
                        <span style="font-size: 0.9rem;">Día de descanso</span>
                    </label>
                </div>
                <div class="dia-config" id="config-<?= $key ?>" style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 12px;">
                    <div>
                        <label class="form-label">Entrada</label>
                        <input type="time" name="<?= $key ?>_entrada" class="form-input">
                    </div>
                    <div>
                        <label class="form-label">Salida</label>
                        <input type="time" name="<?= $key ?>_salida" class="form-input">
                    </div>
                    <div>
                        <label class="form-label">Tolerancia Entrada (min)</label>
                        <input type="number" name="<?= $key ?>_tolerancia_entrada" class="form-input" value="10" min="0">
                    </div>
                    <div>
                        <label class="form-label">Tolerancia Salida (min)</label>
                        <input type="number" name="<?= $key ?>_tolerancia_salida" class="form-input" value="10" min="0">
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 24px;">
                <button type="button" class="btn" style="background: #f1f5f9;" onclick="closeModal('modalCrearHorario')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Crear Horario</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Asignar Horario -->
<div class="modal-overlay" id="modalAsignar">
    <div class="modal-content" style="max-width: 500px;">
        <h3 style="font-weight: 700; margin-bottom: 24px;">Asignar Horario</h3>
        <form action="/horion-time/public/horarios/asignar" method="POST">
            <input type="hidden" name="usuario_id" id="asignar_usuario_id">
            <p style="color: var(--text-muted); margin-bottom: 16px;" id="asignar_empleado_nombre"></p>
            
            <div style="margin-bottom: 16px;">
                <label class="form-label">Horario *</label>
                <select name="horario_id" class="form-input" required>
                    <option value="">Seleccione un horario...</option>
                    <?php foreach ($horarios as $h): ?>
                    <option value="<?= $h['id'] ?>"><?= htmlspecialchars($h['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div style="margin-bottom: 16px;">
                <label class="form-label">Fecha de Inicio *</label>
                <input type="date" name="fecha_inicio" class="form-input" value="<?= date('Y-m-d') ?>" required>
            </div>
            
            <div style="margin-bottom: 16px;">
                <label class="form-label">Fecha de Fin (opcional)</label>
                <input type="date" name="fecha_fin" class="form-input">
                <small style="color: var(--text-muted);">Dejar vacío para asignación indefinida</small>
            </div>
            
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" class="btn" style="background: #f1f5f9;" onclick="closeModal('modalAsignar')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Asignar Horario</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Ver Turnos -->
<div class="modal-overlay" id="modalTurnos">
    <div class="modal-content" style="max-width: 760px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h3 style="font-weight: 700; margin: 0;">Turnos del Horario</h3>
            <button type="button" class="btn" style="background: #f1f5f9; padding: 6px 14px;" onclick="closeModal('modalTurnos')">
                <i class="fas fa-times"></i> Cerrar
            </button>
        </div>
        <div id="modalTurnosBody" style="font-size: .92rem;"></div>
    </div>
</div>

<style>
.tab-btn {
    padding: 12px 20px; background: none; border: none; border-bottom: 3px solid transparent;
    font-weight: 600; color: var(--text-muted); cursor: pointer; transition: all 0.2s;
}
.tab-btn.active { color: var(--primary); border-bottom-color: var(--primary); }

.tabla-turnos { width: 100%; border-collapse: collapse; margin-top: 8px; }
.tabla-turnos th, .tabla-turnos td { padding: 10px 12px; border-bottom: 1px solid #e2e8f0; text-align: left; }
.tabla-turnos th { background: #f8fafc; font-weight: 700; color: #334155; }
.tabla-turnos tr:last-child td { border-bottom: none; }
.badge-descanso { background: #fef3c7; color: #92400e; padding: 3px 10px; border-radius: 6px; font-size: .8rem; font-weight: 600; }
</style>

<script>
// Datos de turnos inyectados desde PHP (NO fetch)
window.turnosPorHorario = <?= json_encode($turnosPorHorario) ?>;
window.nombresDias = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];

function cambiarTab(tabId, btn) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.style.display = 'none');
    btn.classList.add('active');
    document.getElementById('tab-' + tabId).style.display = 'block';
}

function toggleDia(dia) {
    const config = document.getElementById('config-' + dia);
    const checkbox = document.querySelector('[name="' + dia + '_descanso"]');
    config.style.opacity = checkbox.checked ? '0.3' : '1';
    config.style.pointerEvents = checkbox.checked ? 'none' : 'auto';
}

function openAsignarModal(usuarioId, nombre) {
    document.getElementById('asignar_usuario_id').value = usuarioId;
    document.getElementById('asignar_empleado_nombre').textContent = 'Empleado: ' + nombre;
    openModal('modalAsignar');
}

function verDetalleHorario(id) {
    const turnos = window.turnosPorHorario[id] || [];
    let html = '';

    if (turnos.length === 0) {
        html = '<div style="text-align:center; padding: 30px; color: var(--text-muted);">' +
               '<i class="fas fa-calendar-times" style="font-size: 2rem; margin-bottom: 10px;"></i>' +
               '<p>Este horario aún no tiene turnos configurados.</p>' +
               '</div>';
    } else {
        html = '<table class="tabla-turnos">';
        html += '<thead><tr><th>Día</th><th>Entrada</th><th>Salida</th><th>Estado</th></tr></thead><tbody>';
        turnos.forEach(t => {
            const dia = window.nombresDias[parseInt(t.dia_semana)] || ('Día ' + t.dia_semana);
            const estado = parseInt(t.es_descanso) === 1
                ? '<span class="badge-descanso">Descanso</span>'
                : '<span class="badge badge-success">Laboral</span>';
            html += `<tr>
                        <td><strong>${dia}</strong></td>
                        <td>${t.hora_entrada || '—'}</td>
                        <td>${t.hora_salida || '—'}</td>
                        <td>${estado}</td>
                     </tr>`;
        });
        html += '</tbody></table>';
    }

    document.getElementById('modalTurnosBody').innerHTML = html;
    openModal('modalTurnos');
}

// Fallback si no existe openModal/closeModal en el sistema
if (typeof openModal !== 'function') {
    window.openModal = function(id) { document.getElementById(id).style.display = 'flex'; };
    window.closeModal = function(id) { document.getElementById(id).style.display = 'none'; };
}
</script>

<?php include(__DIR__ . '/../layouts/footer.php'); ?>
