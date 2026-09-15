<?php 
session_start();
include(__DIR__ . '/../layouts/header.php'); 
include(__DIR__ . '/../layouts/sidebar.php'); 
?>

<main class="main-content">
    <div class="top-header">
        <h2 style="font-weight: 700; color: var(--text-dark);">
            <i class="fas fa-exclamation-triangle" style="color: var(--primary);"></i>
            Novedades e Incidencias
        </h2>
        <div style="display: flex; gap: 12px;">
            <a href="/horion-time/public/novedades/solicitar" class="btn btn-primary">
                <i class="fas fa-plus"></i> Nueva Solicitud
            </a>
        </div>
    </div>

    <div style="padding: 32px;">
        <?php if (isset($_GET['success'])): ?>
            <div class="card-3d" style="background: #dcfce7; border-left: 4px solid var(--success); margin-bottom: 24px; color: #166534;">
                <i class="fas fa-check-circle"></i> Solicitud registrada exitosamente
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['processed'])): ?>
            <div class="card-3d" style="background: #dcfce7; border-left: 4px solid var(--success); margin-bottom: 24px; color: #166534;">
                <i class="fas fa-check-circle"></i> Novedad procesada correctamente
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="card-3d" style="background: #fef2f2; border-left: 4px solid #dc2626; margin-bottom: 24px; color: #991b1b;">
                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($_GET['error']) ?>
            </div>
        <?php endif; ?>

        <!-- Cards de Resumen -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 24px; margin-bottom: 32px;">
            <div class="card-3d">
                <div style="color: var(--text-muted); font-size: 0.9rem;">Total Novedades</div>
                <div style="font-size: 2rem; font-weight: 800; color: var(--text-dark);"><?= $resumen['total'] ?></div>
            </div>
            <div class="card-3d" style="border-left: 4px solid var(--warning);">
                <div style="color: var(--text-muted); font-size: 0.9rem;">Pendientes</div>
                <div style="font-size: 2rem; font-weight: 800; color: var(--warning);"><?= $resumen['pendientes'] ?></div>
            </div>
            <div class="card-3d" style="border-left: 4px solid var(--success);">
                <div style="color: var(--text-muted); font-size: 0.9rem;">Aprobadas</div>
                <div style="font-size: 2rem; font-weight: 800; color: var(--success);"><?= $resumen['aprobadas'] ?></div>
            </div>
            <div class="card-3d" style="border-left: 4px solid var(--danger);">
                <div style="color: var(--text-muted); font-size: 0.9rem;">Rechazadas</div>
                <div style="font-size: 2rem; font-weight: 800; color: var(--danger);"><?= $resumen['rechazadas'] ?></div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="card-3d" style="margin-bottom: 24px;">
            <form method="GET" action="/horion-time/public/novedades" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; align-items: end;">
                <div>
                    <label class="form-label">Estado</label>
                    <select name="estado" class="form-input">
                        <option value="">Todos</option>
                        <option value="pendiente" <?= ($_GET['estado'] ?? '') === 'pendiente' ? 'selected' : '' ?>>Pendientes</option>
                        <option value="aprobada" <?= ($_GET['estado'] ?? '') === 'aprobada' ? 'selected' : '' ?>>Aprobadas</option>
                        <option value="rechazada" <?= ($_GET['estado'] ?? '') === 'rechazada' ? 'selected' : '' ?>>Rechazadas</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Tipo</label>
                    <select name="tipo" class="form-input">
                        <option value="">Todos</option>
                        <option value="vacaciones">Vacaciones</option>
                        <option value="incapacidad">Incapacidad</option>
                        <option value="permiso">Permiso</option>
                        <option value="licencia">Licencia</option>
                        <option value="remoto">Remoto</option>
                    </select>
                </div>
                <div>
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-filter"></i> Aplicar Filtros
                    </button>
                </div>
            </form>
        </div>

        <!-- Tabla de Novedades -->
        <div class="card-3d">
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Empleado</th>
                            <th>Tipo</th>
                            <th>Período</th>
                            <th>Días</th>
                            <th>Solicitado</th>
                            <th>Estado</th>
                            <th style="text-align: right;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($novedades)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 48px; color: var(--text-muted);">
                                <i class="fas fa-inbox" style="font-size: 2rem; display: block; margin-bottom: 12px;"></i>
                                No hay novedades registradas
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($novedades as $n): ?>
                        <tr>
                            <td style="font-family: monospace; font-weight: 600;">#<?= $n['id'] ?></td>
                            <td>
                                <div style="font-weight: 600;"><?= htmlspecialchars($n['empleado_nombre']) ?></div>
                                <div style="font-size: 0.8rem; color: var(--text-muted);"><?= htmlspecialchars($n['empleado_id']) ?></div>
                            </td>
                            <td>
                                <?php
                                $tipoConfig = [
                                    'incapacidad' => ['icon' => 'fa-briefcase-medical', 'color' => '#e53935'],
                                    'vacaciones' => ['icon' => 'fa-umbrella-beach', 'color' => '#03a950'],
                                    'permiso' => ['icon' => 'fa-file-alt', 'color' => '#2196f3'],
                                    'licencia' => ['icon' => 'fa-certificate', 'color' => '#9c27b0'],
                                    'calamidad' => ['icon' => 'fa-house-crack', 'color' => '#f44336'],
                                    'compensatorio' => ['icon' => 'fa-clock-rotate-left', 'color' => '#4caf50'],
                                    'remoto' => ['icon' => 'fa-laptop-house', 'color' => '#00bcd4'],
                                    'comision' => ['icon' => 'fa-car', 'color' => '#795548'],
                                    'llegada_tarde' => ['icon' => 'fa-clock', 'color' => '#ff9800'],
                                    'salida_anticipada' => ['icon' => 'fa-door-open', 'color' => '#ffc107'],
                                    'correccion_marcacion' => ['icon' => 'fa-pen', 'color' => '#607d8b']
                                ];
                                $config = $tipoConfig[$n['tipo']] ?? ['icon' => 'fa-circle', 'color' => '#999'];
                                ?>
                                <span style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; background: <?= $config['color'] ?>15; color: <?= $config['color'] ?>; border-radius: 20px; font-size: 0.85rem; font-weight: 600;">
                                    <i class="fas <?= $config['icon'] ?>"></i>
                                    <?= ucfirst(str_replace('_', ' ', $n['tipo'])) ?>
                                </span>
                            </td>
                            <td style="font-size: 0.9rem;">
                                <div><?= date('d/m/Y', strtotime($n['fecha_inicio'])) ?></div>
                                <div style="color: var(--text-muted);">al <?= date('d/m/Y', strtotime($n['fecha_fin'])) ?></div>
                            </td>
                            <td style="font-weight: 700; text-align: center;"><?= $n['dias_calculados'] ?></td>
                            <td style="font-size: 0.85rem; color: var(--text-muted);">
                                <?= date('d/m/Y H:i', strtotime($n['fecha_solicitud'])) ?>
                            </td>
                            <td>
                                <?php
                                $estadoConfig = [
                                    'pendiente' => ['class' => 'badge-warning', 'bg' => '#fef3c7', 'color' => '#92400e', 'icon' => 'fa-hourglass-half'],
                                    'aprobada' => ['class' => 'badge-success', 'bg' => '#dcfce7', 'color' => '#166534', 'icon' => 'fa-check-circle'],
                                    'rechazada' => ['class' => 'badge-danger', 'bg' => '#fee2e2', 'color' => '#991b1b', 'icon' => 'fa-times-circle'],
                                    'cancelada' => ['class' => 'badge', 'bg' => '#f1f5f9', 'color' => '#64748b', 'icon' => 'fa-ban']
                                ];
                                $est = $estadoConfig[$n['estado']] ?? $estadoConfig['pendiente'];
                                ?>
                                <span class="badge" style="background: <?= $est['bg'] ?>; color: <?= $est['color'] ?>;">
                                    <i class="fas <?= $est['icon'] ?>"></i> <?= ucfirst($n['estado']) ?>
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <a href="/horion-time/public/novedades/show/<?= $n['id'] ?>" class="btn" style="background: #f1f5f9; padding: 6px 10px;" title="Ver detalle">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if ($n['estado'] === 'pendiente' && in_array($_SESSION['rol_nombre'] ?? '', ['SuperAdmin', 'Admin_Empresa', 'Supervisor', 'RRHH'])): ?>
                                <button class="btn" style="background: var(--primary); color: white; padding: 6px 10px;" onclick="openProcesarModal(<?= $n['id'] ?>, '<?= htmlspecialchars($n['empleado_nombre']) ?>', '<?= ucfirst(str_replace('_', ' ', $n['tipo'])) ?>')" title="Procesar">
                                    <i class="fas fa-gavel"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- Modal de Procesamiento (Aprobar/Rechazar) -->
<div class="modal-overlay" id="modalProcesar">
    <div class="modal-content" style="max-width: 500px;">
        <h3 style="margin-bottom: 8px; font-weight: 700;">
            <i class="fas fa-gavel" style="color: var(--primary);"></i>
            Procesar Novedad
        </h3>
        <p style="color: var(--text-muted); margin-bottom: 24px;" id="modalInfo"></p>
        
        <form action="" method="POST" id="formProcesar">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <input type="hidden" name="estado" id="inputEstado">
            
            <div style="margin-bottom: 16px;">
                <label class="form-label">Observaciones del Aprobador</label>
                <textarea name="observaciones" rows="4" class="form-input" placeholder="Motivo de la decisión..."></textarea>
            </div>

            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" class="btn" style="background: #f1f5f9;" onclick="closeModal('modalProcesar')">Cancelar</button>
                <button type="button" class="btn" style="background: #fee2e2; color: #dc2626;" onclick="submitProcesar('rechazada')">
                    <i class="fas fa-times"></i> Rechazar
                </button>
                <button type="button" class="btn btn-primary" onclick="submitProcesar('aprobada')">
                    <i class="fas fa-check"></i> Aprobar
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.form-label { display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 6px; color: var(--text-muted); }
.form-input { width: 100%; padding: 10px 14px; border: 1px solid var(--border); border-radius: 10px; outline: none; transition: all 0.2s; font-size: 0.95rem; }
.form-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(3, 169, 80, 0.1); }
</style>

<script>
function openProcesarModal(id, empleado, tipo) {
    document.getElementById('modalInfo').textContent = `Empleado: ${empleado} | Tipo: ${tipo}`;
    document.getElementById('formProcesar').action = `/horion-time/public/novedades/procesar/${id}`;
    openModal('modalProcesar');
}

function submitProcesar(estado) {
    document.getElementById('inputEstado').value = estado;
    document.getElementById('formProcesar').submit();
}
</script>

<?php include(__DIR__ . '/../layouts/footer.php'); ?>