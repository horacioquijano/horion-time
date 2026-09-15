<?php
session_start();
include(__DIR__ . '/../layouts/header.php');
include(__DIR__ . '/../layouts/sidebar.php');
?>
<?php
// ===== PATCH v2: claves exactas que usa esta vista =====
$recargosBase = [
['tipo_recargo' => 'Hora Extra Diurna',            'hora_inicio' => '06:00', 'hora_fin' => '19:00', 'porcentaje_recargo' => 25,  'descripcion' => 'Lun–sáb 06:00–19:00'],
['tipo_recargo' => 'Hora Extra Nocturna',          'hora_inicio' => '19:00', 'hora_fin' => '06:00', 'porcentaje_recargo' => 75,  'descripcion' => 'Lun–sáb 19:00–06:00'],
['tipo_recargo' => 'Dominical / Festiva Diurna',   'hora_inicio' => '06:00', 'hora_fin' => '19:00', 'porcentaje_recargo' => 75,  'descripcion' => 'Domingos y festivos 06:00–19:00'],
['tipo_recargo' => 'Dominical / Festiva Nocturna', 'hora_inicio' => '19:00', 'hora_fin' => '06:00', 'porcentaje_recargo' => 110, 'descripcion' => 'Domingos y festivos 19:00–06:00'],
];
if (empty($configRecargos) || !is_array($configRecargos)) {
$configRecargos = $recargosBase;
} else {
foreach ($configRecargos as &$__r) {
$__r['tipo_recargo']       = $__r['tipo_recargo'] ?? $__r['nombre'] ?? $__r['tipo'] ?? 'Recargo';
$__r['hora_inicio']        = $__r['hora_inicio'] ?? '06:00';
$__r['hora_fin']           = $__r['hora_fin'] ?? '19:00';
$__r['porcentaje_recargo'] = $__r['porcentaje_recargo'] ?? $__r['porcentaje'] ?? $__r['recargo'] ?? 0;
$__r['descripcion']        = $__r['descripcion'] ?? '';
}
unset($__r);
}
if (!isset($horas_extras) && isset($solicitudes)) $horas_extras = $solicitudes;
$horas_extras = $horas_extras ?? [];
$__labels = ['diurna'=>'Hora Extra Diurna','nocturna'=>'Hora Extra Nocturna','dominical'=>'Dominical','festivo'=>'Festivo','dominical_festivo'=>'Domingo Festivo'];
foreach ($horas_extras as &$__h) {
$__h['empleado_nombre']    = $__h['empleado_nombre'] ?? $__h['nombre_completo'] ?? 'Sin nombre';
$__h['empleado_id']        = $__h['empleado_id'] ?? $__h['usuario_id'] ?? $__h['solicitante_id'] ?? '';
$__h['estado']             = $__h['estado'] ?? 'pendiente';
$__h['horas']              = $__h['horas'] ?? $__h['horas_solicitadas'] ?? $__h['cantidad_horas'] ?? 0;
$__h['horas_solicitadas']  = $__h['horas_solicitadas'] ?? $__h['horas'];
$__h['horas_aprobadas']    = $__h['horas_aprobadas'] ?? null;
$__h['valor_total']        = $__h['valor_total'] ?? null;
$__h['hora_inicio']        = $__h['hora_inicio'] ?? $__h['hora_inicio_programada'] ?? '—';
$__h['hora_fin']           = $__h['hora_fin'] ?? $__h['hora_fin_programada'] ?? '—';
$__h['porcentaje_recargo'] = $__h['porcentaje_recargo'] ?? $__h['porcentaje_aplicado'] ?? $__h['recargo'] ?? 0;
$__h['tipo_recargo']       = $__h['tipo_recargo'] ?? ($__labels[$__h['tipo'] ?? ''] ?? ucfirst(str_replace('_', ' ', $__h['tipo'] ?? '')));
}
unset($__h);
$solicitudes = $horas_extras;
// ===== CAMBIO A: la tabla de abajo recorre $horasExtras =====
$horasExtras = $horasExtras ?? $horas_extras;
// ===== CAMBIO B: token para los modales =====
$csrf_token = $csrf_token ?? bin2hex(random_bytes(32));
// ===== FIN CAMBIOS =====
?>
<main class="main-content">
<div class="top-header">
<h2 style="font-weight: 700; color: var(--text-dark);">
<i class="fas fa-business-time" style="color: var(--primary);"></i>
Gestión de Horas Extras
</h2>
<div style="display: flex; gap: 12px;">
<a href="/horion-time/public/horas_extras/solicitar" class="btn btn-primary">
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
<i class="fas fa-check-circle"></i> Hora extra procesada correctamente
</div>
<?php endif; ?>
<?php if (isset($_GET['paid'])): ?>
<div class="card-3d" style="background: #dbeafe; border-left: 4px solid var(--info); margin-bottom: 24px; color: #1e40af;">
<i class="fas fa-money-bill-wave"></i> Hora extra marcada como pagada
</div>
<?php endif; ?>
<?php if (isset($_GET['error'])): ?>
<div class="card-3d" style="background: #fef2f2; border-left: 4px solid #dc2626; margin-bottom: 24px; color: #991b1b;">
<i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($_GET['error']) ?>
</div>
<?php endif; ?>
<!-- Cards de Resumen -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 24px; margin-bottom: 32px;">
<div class="card-3d">
<div style="color: var(--text-muted); font-size: 0.9rem;">Total Solicitudes</div>
<div style="font-size: 2rem; font-weight: 800; color: var(--text-dark);"><?= $resumen['total'] ?></div>
</div>
<div class="card-3d" style="border-left: 4px solid var(--warning);">
<div style="color: var(--text-muted); font-size: 0.9rem;">Pendientes</div>
<div style="font-size: 2rem; font-weight: 800; color: var(--warning);"><?= $resumen['pendientes'] ?></div>
</div>
<div class="card-3d" style="border-left: 4px solid var(--success);">
<div style="color: var(--text-muted); font-size: 0.9rem;">Aprobadas</div>
<div style="font-size: 2rem; font-weight: 800; color: var(--success);"><?= $resumen['aprobadas'] ?></div>
<div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 4px;">
<?= number_format($resumen['horas_totales_aprobadas'] ?? 0, 1) ?> horas
</div>
</div>
<div class="card-3d" style="border-left: 4px solid var(--info);">
<div style="color: var(--text-muted); font-size: 0.9rem;">Valor Aprobado</div>
<div style="font-size: 1.5rem; font-weight: 800; color: var(--info);">
$<?= number_format($resumen['valor_total_aprobado'] ?? 0, 0, ',', '.') ?>
</div>
</div>
</div>
<!-- Configuración de Recargos (Colapsable) -->
<div class="card-3d" style="margin-bottom: 24px;">
<details>
<summary style="cursor: pointer; font-weight: 700; font-size: 1.1rem; display: flex; align-items: center; gap: 10px;">
<i class="fas fa-cog" style="color: var(--primary);"></i>
Configuración de Recargos
<span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 400;">(Clic para expandir)</span>
</summary>
<div style="margin-top: 16px; overflow-x: auto;">
<table class="data-table">
<thead>
<tr>
<th>Tipo de Recargo</th>
<th>Horario</th>
<th>Porcentaje</th>
<th>Descripción</th>
</tr>
</thead>
<tbody>
<?php foreach ($configRecargos as $recargo): ?>
<tr>
<td style="font-weight: 600;"><?= ucfirst(str_replace('_', ' ', $recargo['tipo_recargo'])) ?></td>
<td style="font-family: monospace;"><?= substr($recargo['hora_inicio'], 0, 5) ?> - <?= substr($recargo['hora_fin'], 0, 5) ?></td>
<td>
<span class="badge" style="background: rgba(3, 169, 80, 0.1); color: var(--primary);">
+<?= $recargo['porcentaje_recargo'] ?>%
</span>
</td>
<td style="font-size: 0.9rem; color: var(--text-muted);"><?= htmlspecialchars($recargo['descripcion']) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</details>
</div>
<!-- Filtros -->
<div class="card-3d" style="margin-bottom: 24px;">
<form method="GET" action="/horion-time/public/horas_extras" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; align-items: end;">
<div>
<label class="form-label">Estado</label>
<select name="estado" class="form-input">
<option value="">Todos</option>
<option value="pendiente" <?= ($_GET['estado'] ?? '') === 'pendiente' ? 'selected' : '' ?>>Pendientes</option>
<option value="aprobada" <?= ($_GET['estado'] ?? '') === 'aprobada' ? 'selected' : '' ?>>Aprobadas</option>
<option value="pagada" <?= ($_GET['estado'] ?? '') === 'pagada' ? 'selected' : '' ?>>Pagadas</option>
<option value="rechazada" <?= ($_GET['estado'] ?? '') === 'rechazada' ? 'selected' : '' ?>>Rechazadas</option>
</select>
</div>
<div>
<label class="form-label">Tipo</label>
<select name="tipo" class="form-input">
<option value="">Todos</option>
<option value="diurna" <?= ($_GET['tipo'] ?? '') === 'diurna' ? 'selected' : '' ?>>Diurna (+25%)</option>
<option value="nocturna" <?= ($_GET['tipo'] ?? '') === 'nocturna' ? 'selected' : '' ?>>Nocturna (+75%)</option>
<option value="dominical" <?= ($_GET['tipo'] ?? '') === 'dominical' ? 'selected' : '' ?>>Dominical (+75%)</option>
<option value="festivo" <?= ($_GET['tipo'] ?? '') === 'festivo' ? 'selected' : '' ?>>Festivo (+75%)</option>
<!-- ===== CAMBIO C: opción faltante del ENUM ===== -->
<option value="dominical_festivo" <?= ($_GET['tipo'] ?? '') === 'dominical_festivo' ? 'selected' : '' ?>>Dom. Festivo (+75%/+110%)</option>
<!-- ===== FIN CAMBIO C ===== -->
</select>
</div>
<div>
<label class="form-label">Fecha Inicio</label>
<input type="date" name="fecha_inicio" value="<?= $_GET['fecha_inicio'] ?? '' ?>" class="form-input">
</div>
<div>
<label class="form-label">Fecha Fin</label>
<input type="date" name="fecha_fin" value="<?= $_GET['fecha_fin'] ?? '' ?>" class="form-input">
</div>
<div>
<button type="submit" class="btn btn-primary" style="width: 100%;">
<i class="fas fa-filter"></i> Filtrar
</button>
</div>
</form>
</div>
<!-- Tabla de Horas Extras -->
<div class="card-3d">
<div class="table-container">
<table class="data-table">
<thead>
<tr>
<th>ID</th>
<th>Empleado</th>
<th>Fecha</th>
<th>Tipo</th>
<th>Horario</th>
<th>Horas</th>
<th>Valor Total</th>
<th>Estado</th>
<th style="text-align: right;">Acciones</th>
</tr>
</thead>
<tbody>
<?php if (empty($horasExtras)): ?>
<tr>
<td colspan="9" style="text-align: center; padding: 48px; color: var(--text-muted);">
<i class="fas fa-clock" style="font-size: 2rem; display: block; margin-bottom: 12px;"></i>
No hay horas extras registradas
</td>
</tr>
<?php else: ?>
<?php foreach ($horasExtras as $he): ?>
<tr>
<td style="font-family: monospace; font-weight: 600;">#<?= $he['id'] ?></td>
<td>
<div style="font-weight: 600;"><?= htmlspecialchars($he['empleado_nombre']) ?></div>
<div style="font-size: 0.8rem; color: var(--text-muted);"><?= htmlspecialchars($he['empleado_id']) ?></div>
</td>
<td style="font-weight: 600;"><?= date('d/m/Y', strtotime($he['fecha'])) ?></td>
<td>
<?php
// ===== CAMBIO D: porcentaje real guardado (porcentaje_aplicado) =====
$tipoConfig = [
'diurna' => ['icon' => 'fa-sun', 'color' => '#f59e0b', 'name' => 'Diurna', 'def' => 25],
'nocturna' => ['icon' => 'fa-moon', 'color' => '#6366f1', 'name' => 'Nocturna', 'def' => 75],
'dominical' => ['icon' => 'fa-calendar-day', 'color' => '#ec4899', 'name' => 'Dominical', 'def' => 75],
'festivo' => ['icon' => 'fa-star', 'color' => '#ef4444', 'name' => 'Festivo', 'def' => 75],
'dominical_festivo' => ['icon' => 'fa-crown', 'color' => '#dc2626', 'name' => 'Dom+Fest', 'def' => 75]
];
$config = $tipoConfig[$he['tipo']] ?? $tipoConfig['diurna'];
$pct = $he['porcentaje_aplicado'] ?? $he['porcentaje_recargo'] ?? $config['def'];
// ===== FIN CAMBIO D =====
?>
<span style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; background: <?= $config['color'] ?>15; color: <?= $config['color'] ?>; border-radius: 20px; font-size: 0.85rem; font-weight: 600;">
<i class="fas <?= $config['icon'] ?>"></i>
<?= $config['name'] ?> +<?= $pct ?>%
</span>
</td>
<td style="font-family: monospace; font-size: 0.9rem;">
<div><?= substr($he['hora_inicio_programada'], 0, 5) ?></div>
<div style="color: var(--text-muted);">a <?= substr($he['hora_fin_programada'], 0, 5) ?></div>
</td>
<td>
<div style="font-weight: 700;"><?= $he['horas_solicitadas'] ?>h</div>
<?php if ($he['horas_aprobadas']): ?>
<div style="font-size: 0.8rem; color: var(--success);">
✓ <?= $he['horas_aprobadas'] ?>h aprobadas
</div>
<?php endif; ?>
</td>
<td style="font-weight: 700; color: var(--primary);">
$<?= number_format($he['valor_total'] ?? 0, 0, ',', '.') ?>
</td>
<td>
<?php
$estadoConfig = [
'pendiente' => ['bg' => '#fef3c7', 'color' => '#92400e', 'icon' => 'fa-hourglass-half'],
'autorizada' => ['bg' => '#dbeafe', 'color' => '#1e40af', 'icon' => 'fa-check-double'],
'aprobada' => ['bg' => '#dcfce7', 'color' => '#166534', 'icon' => 'fa-check-circle'],
'rechazada' => ['bg' => '#fee2e2', 'color' => '#991b1b', 'icon' => 'fa-times-circle'],
'pagada' => ['bg' => '#e0e7ff', 'color' => '#3730a3', 'icon' => 'fa-money-bill-wave']
];
$est = $estadoConfig[$he['estado']] ?? $estadoConfig['pendiente'];
?>
<span class="badge" style="background: <?= $est['bg'] ?>; color: <?= $est['color'] ?>;">
<i class="fas <?= $est['icon'] ?>"></i> <?= ucfirst($he['estado']) ?>
</span>
</td>
<td style="text-align: right;">
<?php if ($he['estado'] === 'pendiente' && in_array($_SESSION['rol_nombre'] ?? '', ['SuperAdmin', 'Admin_Empresa', 'Supervisor', 'RRHH'])): ?>
<button class="btn" style="background: var(--primary); color: white; padding: 6px 10px;" onclick="openProcesarModal(<?= $he['id'] ?>, '<?= htmlspecialchars($he['empleado_nombre']) ?>', <?= $he['horas_solicitadas'] ?>)" title="Procesar">
<i class="fas fa-gavel"></i>
</button>
<?php endif; ?>
<?php if ($he['estado'] === 'aprobada' && in_array($_SESSION['rol_nombre'] ?? '', ['SuperAdmin', 'Admin_Empresa'])): ?>
<button class="btn" style="background: var(--info); color: white; padding: 6px 10px;" onclick="openPagarModal(<?= $he['id'] ?>)" title="Marcar pagada">
<i class="fas fa-money-bill-wave"></i>
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
<!-- Modal de Procesamiento -->
<div class="modal-overlay" id="modalProcesar">
<div class="modal-content" style="max-width: 500px;">
<h3 style="margin-bottom: 8px; font-weight: 700;">
<i class="fas fa-gavel" style="color: var(--primary);"></i>
Procesar Hora Extra
</h3>
<p style="color: var(--text-muted); margin-bottom: 24px;" id="modalInfo"></p>
<form action="" method="POST" id="formProcesar">
<input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
<input type="hidden" name="estado" id="inputEstado">
<div style="margin-bottom: 16px;">
<label class="form-label">Horas Aprobadas</label>
<input type="number" name="horas_aprobadas" id="inputHoras" step="0.5" min="0" class="form-input" required>
<small style="color: var(--text-muted);">Ingrese 0 para rechazar</small>
</div>
<div style="margin-bottom: 16px;">
<label class="form-label">Observaciones</label>
<textarea name="observaciones" rows="3" class="form-input" placeholder="Motivo de la decisión..."></textarea>
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
<!-- Modal de Pago -->
<div class="modal-overlay" id="modalPagar">
<div class="modal-content" style="max-width: 400px;">
<h3 style="margin-bottom: 24px; font-weight: 700;">
<i class="fas fa-money-bill-wave" style="color: var(--info);"></i>
Marcar como Pagada
</h3>
<form action="" method="POST" id="formPagar">
<input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
<div style="margin-bottom: 16px;">
<label class="form-label">Número de Nómina</label>
<input type="text" name="numero_nomina" class="form-input" placeholder="Ej: NOM-2026-09" required>
</div>
<div style="display: flex; gap: 12px; justify-content: flex-end;">
<button type="button" class="btn" style="background: #f1f5f9;" onclick="closeModal('modalPagar')">Cancelar</button>
<button type="submit" class="btn" style="background: var(--info); color: white;">
<i class="fas fa-check"></i> Confirmar Pago
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
function openProcesarModal(id, empleado, horasSolicitadas) {
document.getElementById('modalInfo').textContent = `Empleado: ${empleado} | Horas solicitadas: ${horasSolicitadas}`;
document.getElementById('inputHoras').value = horasSolicitadas;
document.getElementById('formProcesar').action = `/horion-time/public/horas_extras/procesar/${id}`;
openModal('modalProcesar');
}
function submitProcesar(estado) {
document.getElementById('inputEstado').value = estado;
document.getElementById('formProcesar').submit();
}
function openPagarModal(id) {
document.getElementById('formPagar').action = `/horion-time/public/horas_extras/pagar/${id}`;
openModal('modalPagar');
}
</script>
<?php include(__DIR__ . '/../layouts/footer.php'); ?>