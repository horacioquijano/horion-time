<?php
session_start();
include(__DIR__ . '/../layouts/header.php');
include(__DIR__ . '/../layouts/sidebar.php');
// ===== CAMBIO A: respaldos de claves para evitar warnings =====
$resumen = $resumen ?? [];
$resumen += ['porcentaje_asistencia' => 0, 'presentes' => 0, 'tardanzas' => 0, 'ausentes' => 0];
$novedadesPendientes = $novedadesPendientes ?? [];
foreach ($novedadesPendientes as &$__n) {
$__n['empleado_nombre'] = $__n['empleado_nombre'] ?? $__n['nombre_completo'] ?? 'Sin nombre';
$__n['motivo']           = $__n['motivo'] ?? $__n['justificacion'] ?? '';
$__n['tipo']             = $__n['tipo'] ?? '';
$__n['dias_calculados']  = $__n['dias_calculados'] ?? (isset($__n['fecha_inicio'], $__n['fecha_fin']) ? max(1, (int)((strtotime($__n['fecha_fin']) - strtotime($__n['fecha_inicio'])) / 86400) + 1) : 1);
}
unset($__n);
$horasExtrasPendientes = $horasExtrasPendientes ?? [];
foreach ($horasExtrasPendientes as &$__h) {
$__h['empleado_nombre']   = $__h['empleado_nombre'] ?? $__h['nombre_completo'] ?? 'Sin nombre';
$__h['tipo']              = $__h['tipo'] ?? 'diurna';
$__h['horas_solicitadas'] = $__h['horas_solicitadas'] ?? 0;
$__h['valor_total']       = $__h['valor_total'] ?? 0;
}
unset($__h);
$inconsistencias = $inconsistencias ?? [];
$csrf_token = $csrf_token ?? bin2hex(random_bytes(32));
// ===== FIN CAMBIO A =====
?>
<main class="main-content">
<div class="top-header">
<div>
<h2 style="font-weight: 700; color: var(--text-dark); margin: 0;">
<i class="fas fa-user-tie" style="color: var(--primary);"></i>
Panel de Supervisor
</h2>
<p style="color: var(--text-muted); margin: 4px 0 0 0; font-size: 0.9rem;">
Gestión de tu equipo · <?= date('l, d \d\e F \d\e Y') ?>
</p>
</div>
<a href="/horion-time/public/supervisor/equipo" class="btn btn-primary">
<i class="fas fa-users"></i> Ver Equipo Completo
</a>
</div>
<div style="padding: 32px; max-width: 1400px; margin: 0 auto;">
<?php if (isset($_GET['success'])): ?>
<div class="card-3d" style="background: #dcfce7; border-left: 4px solid var(--success); margin-bottom: 24px; color: #166534;">
<i class="fas fa-check-circle"></i> Acción procesada correctamente
</div>
<?php endif; ?>
<?php if (isset($_GET['error'])): ?>
<div class="card-3d" style="background: #fef2f2; border-left: 4px solid #dc2626; margin-bottom: 24px; color: #991b1b;">
<i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($_GET['error']) ?>
</div>
<?php endif; ?>
<!-- RESUMEN DEL DÍA - HERO CARD -->
<div class="card-3d" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); color: white; margin-bottom: 32px; position: relative; overflow: hidden;">
<div style="position: absolute; top: -100px; right: -100px; width: 300px; height: 300px; background: rgba(3, 169, 80, 0.15); border-radius: 50%;"></div>
<div style="position: absolute; bottom: -80px; left: 30%; width: 200px; height: 200px; background: rgba(33, 150, 243, 0.1); border-radius: 50%;"></div>
<div style="position: relative; z-index: 1;">
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
<div>
<h3 style="font-size: 1.5rem; font-weight: 800; margin: 0;">
👥 PERSONAL DE MI ÁREA
</h3>
<p style="margin: 4px 0 0 0; opacity: 0.8;">Resumen en tiempo real</p>
</div>
<div style="text-align: right;">
<div style="font-size: 3.5rem; font-weight: 900; color: var(--primary); line-height: 1;">
<?= $resumen['porcentaje_asistencia'] ?>%
</div>
<div style="font-size: 0.85rem; opacity: 0.8;">Asistencia hoy</div>
</div>
</div>
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 16px;">
<div style="background: rgba(255,255,255,0.08); padding: 20px; border-radius: 12px; backdrop-filter: blur(10px); border-left: 4px solid var(--success);">
<div style="font-size: 0.85rem; opacity: 0.8;">🟢 Presentes</div>
<div style="font-size: 2rem; font-weight: 800;"><?= $resumen['presentes'] ?></div>
</div>
<div style="background: rgba(255,255,255,0.08); padding: 20px; border-radius: 12px; backdrop-filter: blur(10px); border-left: 4px solid var(--warning);">
<div style="font-size: 0.85rem; opacity: 0.8;">🟡 Tardanzas</div>
<div style="font-size: 2rem; font-weight: 800;"><?= $resumen['tardanzas'] ?></div>
</div>
<div style="background: rgba(255,255,255,0.08); padding: 20px; border-radius: 12px; backdrop-filter: blur(10px); border-left: 4px solid var(--danger);">
<div style="font-size: 0.85rem; opacity: 0.8;">🔴 Ausentes</div>
<div style="font-size: 2rem; font-weight: 800;"><?= $resumen['ausentes'] ?></div>
</div>
<div style="background: rgba(255,255,255,0.08); padding: 20px; border-radius: 12px; backdrop-filter: blur(10px); border-left: 4px solid var(--info);">
<div style="font-size: 0.85rem; opacity: 0.8;">⚠️ Pendientes</div>
<div style="font-size: 2rem; font-weight: 800;">
<?= count($novedadesPendientes) + count($horasExtrasPendientes) ?>
</div>
</div>
</div>
</div>
</div>
<!-- ACCIONES RÁPIDAS -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 32px;">
<a href="#novedades" class="card-3d" style="text-decoration: none; color: inherit; display: block; border-left: 4px solid var(--warning);">
<div style="display: flex; align-items: center; gap: 12px;">
<div style="width: 44px; height: 44px; background: rgba(255, 152, 0, 0.15); color: var(--warning); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
<i class="fas fa-gavel"></i>
</div>
<div>
<div style="font-weight: 700;">Aprobar Novedades</div>
<div style="font-size: 0.85rem; color: var(--text-muted);"><?= count($novedadesPendientes) ?> pendientes</div>
</div>
</div>
</a>
<a href="#horas_extras" class="card-3d" style="text-decoration: none; color: inherit; display: block; border-left: 4px solid var(--info);">
<div style="display: flex; align-items: center; gap: 12px;">
<div style="width: 44px; height: 44px; background: rgba(33, 150, 243, 0.15); color: var(--info); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
<i class="fas fa-business-time"></i>
</div>
<div>
<div style="font-weight: 700;">Aprobar Horas Extras</div>
<div style="font-size: 0.85rem; color: var(--text-muted);"><?= count($horasExtrasPendientes) ?> pendientes</div>
</div>
</div>
</a>
<a href="/horion-time/public/supervisor/equipo" class="card-3d" style="text-decoration: none; color: inherit; display: block; border-left: 4px solid var(--success);">
<div style="display: flex; align-items: center; gap: 12px;">
<div style="width: 44px; height: 44px; background: rgba(3, 169, 80, 0.15); color: var(--primary); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
<i class="fas fa-clipboard-check"></i>
</div>
<div>
<div style="font-weight: 700;">Revisar Asistencia</div>
<div style="font-size: 0.85rem; color: var(--text-muted);">Detalle del equipo</div>
</div>
</div>
</a>
<a href="#inconsistencias" class="card-3d" style="text-decoration: none; color: inherit; display: block; border-left: 4px solid var(--danger);">
<div style="display: flex; align-items: center; gap: 12px;">
<div style="width: 44px; height: 44px; background: rgba(229, 57, 53, 0.15); color: var(--danger); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
<i class="fas fa-exclamation-circle"></i>
</div>
<div>
<div style="font-weight: 700;">Ver Inconsistencias</div>
<div style="font-size: 0.85rem; color: var(--text-muted);"><?= count($inconsistencias) ?> detectadas</div>
</div>
</div>
</a>
</div>
<!-- GRID PRINCIPAL -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
<!-- NOVEDADES PENDIENTES -->
<div class="card-3d" id="novedades">
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
<h3 style="font-weight: 700; margin: 0;">
<i class="fas fa-file-signature" style="color: var(--warning);"></i>
Novedades Pendientes
</h3>
<span class="badge badge-warning"><?= count($novedadesPendientes) ?></span>
</div>
<?php if (empty($novedadesPendientes)): ?>
<div style="text-align: center; padding: 32px; color: var(--text-muted);">
<i class="fas fa-check-circle" style="font-size: 2.5rem; color: var(--success); margin-bottom: 12px; display: block;"></i>
<p>¡Excelente! No hay novedades pendientes de aprobación.</p>
</div>
<?php else: ?>
<div style="display: flex; flex-direction: column; gap: 12px; max-height: 400px; overflow-y: auto;">
<?php foreach ($novedadesPendientes as $n): ?>
<div style="background: #f8fafc; padding: 14px; border-radius: 10px; border-left: 3px solid var(--warning);">
<div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 8px;">
<div>
<div style="font-weight: 700; font-size: 0.95rem;"><?= htmlspecialchars($n['empleado_nombre']) ?></div>
<div style="font-size: 0.8rem; color: var(--text-muted);">
<?= ucfirst(str_replace('_', ' ', $n['tipo'])) ?> · <?= date('d/m/Y', strtotime($n['fecha_inicio'])) ?>
</div>
</div>
<span class="badge" style="background: #fef3c7; color: #92400e;">
<?= $n['dias_calculados'] ?> días
</span>
</div>
<p style="font-size: 0.85rem; color: var(--text-muted); margin: 8px 0; font-style: italic;">
"<?= htmlspecialchars(substr($n['motivo'], 0, 100)) ?><?= strlen($n['motivo']) > 100 ? '...' : '' ?>"
</p>
<div style="display: flex; gap: 8px; justify-content: flex-end;">
<button class="btn btn-danger" style="padding: 6px 12px; font-size: 0.8rem;" onclick="procesarNovedad(<?= $n['id'] ?>, 'rechazada')">
<i class="fas fa-times"></i> Rechazar
</button>
<button class="btn btn-primary" style="padding: 6px 12px; font-size: 0.8rem;" onclick="procesarNovedad(<?= $n['id'] ?>, 'aprobada')">
<i class="fas fa-check"></i> Aprobar
</button>
</div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>
<!-- HORAS EXTRAS PENDIENTES -->
<div class="card-3d" id="horas_extras">
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
<h3 style="font-weight: 700; margin: 0;">
<i class="fas fa-business-time" style="color: var(--info);"></i>
Horas Extras Pendientes
</h3>
<span class="badge" style="background: #dbeafe; color: #1e40af;"><?= count($horasExtrasPendientes) ?></span>
</div>
<?php if (empty($horasExtrasPendientes)): ?>
<div style="text-align: center; padding: 32px; color: var(--text-muted);">
<i class="fas fa-check-circle" style="font-size: 2.5rem; color: var(--success); margin-bottom: 12px; display: block;"></i>
<p>No hay horas extras pendientes de revisión.</p>
</div>
<?php else: ?>
<div style="display: flex; flex-direction: column; gap: 12px; max-height: 400px; overflow-y: auto;">
<?php foreach ($horasExtrasPendientes as $he):
// ===== CAMBIO B: etiqueta para dominical_festivo =====
$tipoLabels = [
'diurna' => ['label' => 'Diurna +25%', 'color' => '#f59e0b'],
'nocturna' => ['label' => 'Nocturna +75%', 'color' => '#6366f1'],
'dominical' => ['label' => 'Dominical +75%', 'color' => '#ec4899'],
'festivo' => ['label' => 'Festivo +75%', 'color' => '#ef4444'],
'dominical_festivo' => ['label' => 'Dom. Festivo +75/110%', 'color' => '#dc2626']
];
$tipo = $tipoLabels[$he['tipo']] ?? $tipoLabels['diurna'];
// ===== FIN CAMBIO B =====
?>
<div style="background: #f8fafc; padding: 14px; border-radius: 10px; border-left: 3px solid var(--info);">
<div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 8px;">
<div>
<div style="font-weight: 700; font-size: 0.95rem;"><?= htmlspecialchars($he['empleado_nombre']) ?></div>
<div style="font-size: 0.8rem; color: var(--text-muted);">
<?= date('d/m/Y', strtotime($he['fecha'])) ?> ·
<?= substr($he['hora_inicio_programada'], 0, 5) ?> - <?= substr($he['hora_fin_programada'], 0, 5) ?>
</div>
</div>
<span class="badge" style="background: <?= $tipo['color'] ?>20; color: <?= $tipo['color'] ?>;">
<?= $tipo['label'] ?>
</span>
</div>
<div style="display: flex; justify-content: space-between; align-items: center; margin: 8px 0;">
<div style="font-size: 0.85rem;">
<strong><?= $he['horas_solicitadas'] ?>h</strong> solicitadas ·
<strong style="color: var(--primary);">$<?= number_format($he['valor_total'] ?? 0, 0, ',', '.') ?></strong>
</div>
</div>
<div style="display: flex; gap: 8px; justify-content: flex-end;">
<button class="btn btn-danger" style="padding: 6px 12px; font-size: 0.8rem;" onclick="procesarHE(<?= $he['id'] ?>, 'rechazada')">
<i class="fas fa-times"></i> Rechazar
</button>
<button class="btn btn-primary" style="padding: 6px 12px; font-size: 0.8rem;" onclick="procesarHE(<?= $he['id'] ?>, 'aprobada', <?= $he['horas_solicitadas'] ?>)">
<i class="fas fa-check"></i> Aprobar
</button>
</div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>
</div>
<!-- INCONSISTENCIAS -->
<div class="card-3d" id="inconsistencias" style="margin-top: 24px;">
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
<h3 style="font-weight: 700; margin: 0;">
<i class="fas fa-exclamation-circle" style="color: var(--danger);"></i>
Inconsistencias de Marcación (Últimos 7 días)
</h3>
<span class="badge badge-danger"><?= count($inconsistencias) ?></span>
</div>
<?php if (empty($inconsistencias)): ?>
<div style="text-align: center; padding: 32px; color: var(--text-muted);">
<i class="fas fa-check-double" style="font-size: 2.5rem; color: var(--success); margin-bottom: 12px; display: block;"></i>
<p>No se detectaron inconsistencias en el equipo.</p>
</div>
<?php else: ?>
<div class="table-container">
<table class="data-table">
<thead>
<tr>
<th>Empleado</th>
<th>Fecha</th>
<th>Marcaciones Registradas</th>
<th>Total</th>
<th style="text-align: right;">Acción</th>
</tr>
</thead>
<tbody>
<?php foreach ($inconsistencias as $inc): ?>
<tr>
<td style="font-weight: 600;"><?= htmlspecialchars($inc['empleado']) ?></td>
<td><?= date('d/m/Y', strtotime($inc['fecha'])) ?></td>
<td style="font-family: monospace; font-size: 0.85rem;"><?= htmlspecialchars($inc['marcaciones']) ?></td>
<td>
<span class="badge badge-warning"><?= $inc['total_marcaciones'] ?>/4</span>
</td>
<td style="text-align: right;">
<a href="/horion-time/public/asistencia?usuario_id=<?= $inc['usuario_id'] ?>&fecha=<?= $inc['fecha'] ?>" class="btn" style="background: #f1f5f9; padding: 6px 10px; font-size: 0.8rem;">
<i class="fas fa-eye"></i> Revisar
</a>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>
</div>
</div>
</main>
<!-- Modal para procesar novedad -->
<div class="modal-overlay" id="modalNovedad">
<div class="modal-content" style="max-width: 450px;">
<h3 style="margin-bottom: 16px; font-weight: 700;" id="tituloNovedad">Procesar Novedad</h3>
<form id="formNovedad" method="POST">
<input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
<input type="hidden" name="estado" id="estadoNovedad">
<div style="margin-bottom: 16px;">
<label class="form-label">Observaciones</label>
<textarea name="observaciones" rows="3" class="form-input" placeholder="Motivo de la decisión..."></textarea>
</div>
<div style="display: flex; gap: 12px; justify-content: flex-end;">
<button type="button" class="btn" style="background: #f1f5f9;" onclick="closeModal('modalNovedad')">Cancelar</button>
<button type="submit" class="btn btn-primary">Confirmar</button>
</div>
</form>
</div>
</div>
<!-- Modal para procesar hora extra -->
<div class="modal-overlay" id="modalHE">
<div class="modal-content" style="max-width: 450px;">
<h3 style="margin-bottom: 16px; font-weight: 700;">Procesar Hora Extra</h3>
<form id="formHE" method="POST">
<input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
<input type="hidden" name="estado" id="estadoHE">
<div style="margin-bottom: 16px;">
<label class="form-label">Horas Aprobadas</label>
<input type="number" name="horas_aprobadas" id="horasHE" step="0.5" min="0" class="form-input" required>
</div>
<div style="margin-bottom: 16px;">
<label class="form-label">Observaciones</label>
<textarea name="observaciones" rows="3" class="form-input"></textarea>
</div>
<div style="display: flex; gap: 12px; justify-content: flex-end;">
<button type="button" class="btn" style="background: #f1f5f9;" onclick="closeModal('modalHE')">Cancelar</button>
<button type="submit" class="btn btn-primary">Confirmar</button>
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
function procesarNovedad(id, estado) {
document.getElementById('estadoNovedad').value = estado;
document.getElementById('tituloNovedad').textContent = estado === 'aprobada' ? '✅ Aprobar Novedad' : '❌ Rechazar Novedad';
document.getElementById('formNovedad').action = '/horion-time/public/supervisor/aprobarNovedad/' + id;
openModal('modalNovedad');
}
function procesarHE(id, estado, horas) {
document.getElementById('estadoHE').value = estado;
document.getElementById('horasHE').value = estado === 'aprobada' ? horas : 0;
document.getElementById('formHE').action = '/horion-time/public/supervisor/aprobarHoraExtra/' + id;
openModal('modalHE');
}
</script>
<?php include(__DIR__ . '/../layouts/footer.php'); ?>