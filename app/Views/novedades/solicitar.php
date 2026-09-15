<?php
session_start();
include(__DIR__ . '/../layouts/header.php');
include(__DIR__ . '/../layouts/sidebar.php');

// ================= PATCH 1 (INICIO) =================
// Garantiza que $tipos siempre llegue con la estructura que espera el select
// (codigo / nombre / requiere_soporte), sin depender del controlador.
if (empty($tipos) || !is_array($tipos)) {
    $tipos = [
        ['codigo' => 'incapacidad',    'nombre' => '🏥 Incapacidad Médica',   'requiere_soporte' => 1],
        ['codigo' => 'vacaciones',     'nombre' => '🌴 Vacaciones',            'requiere_soporte' => 0],
        ['codigo' => 'licencia',       'nombre' => '📄 Licencia Remunerada',   'requiere_soporte' => 1],
        ['codigo' => 'permiso',        'nombre' => '⏱️ Permiso Personal',      'requiere_soporte' => 0],
        ['codigo' => 'calamidad',      'nombre' => '🆘 Calamidad Doméstica',   'requiere_soporte' => 1],
        ['codigo' => 'trabajo_remoto', 'nombre' => '💻 Trabajo Remoto',        'requiere_soporte' => 0],
        ['codigo' => 'otra',           'nombre' => '📌 Otra',                  'requiere_soporte' => 0],
    ];
} else {
    // Normaliza cualquier formato que envíe el controlador (asociativo, lista plana, etc.)
    $tipos = array_map(function ($t) {
        if (is_array($t)) {
            return [
                'codigo'           => (string)($t['codigo'] ?? $t['id'] ?? $t['tipo'] ?? ''),
                'nombre'           => (string)($t['nombre'] ?? $t['tipo'] ?? ''),
                'requiere_soporte' => (int)($t['requiere_soporte'] ?? 0),
            ];
        }
        return ['codigo' => (string)$t, 'nombre' => ucfirst(str_replace('_', ' ', (string)$t)), 'requiere_soporte' => 0];
    }, $tipos);
}
// Evita warning si el controlador no define el token
$csrf_token = $csrf_token ?? bin2hex(random_bytes(32));
// ================= PATCH 1 (FIN) =================
?>
<main class="main-content">
<div class="top-header">
<h2 style="font-weight: 700; color: var(--text-dark);">
<i class="fas fa-file-signature" style="color: var(--primary);"></i>
Solicitar Novedad
</h2>
<a href="/horion-time/public/novedades" class="btn" style="background: #f1f5f9;">
<i class="fas fa-arrow-left"></i> Volver al Listado
</a>
</div>
<div style="padding: 32px; max-width: 900px; margin: 0 auto;">
<?php if (isset($_GET['error'])): ?>
<div class="card-3d" style="background: #fef2f2; border-left: 4px solid #dc2626; margin-bottom: 24px; color: #991b1b;">
<i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($_GET['error']) ?>
</div>
<?php endif; ?>
<div class="card-3d">
<h3 style="font-weight: 700; margin-bottom: 24px;">Formulario de Solicitud</h3>
<form action="/horion-time/public/novedades/store" method="POST" enctype="multipart/form-data" id="formNovedad">
<input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
<input type="hidden" name="usuario_id" value="<?= $_SESSION['usuario_id'] ?? 1 ?>">
<input type="hidden" name="empresa_id" value="<?= $_SESSION['empresa_id'] ?? 1 ?>">
<!-- Tipo de Novedad -->
<div style="margin-bottom: 20px;">
<label class="form-label">Tipo de Novedad *</label>
<select name="tipo" id="tipoNovedad" required class="form-input" onchange="toggleCampos()">
<option value="">Seleccione un tipo...</option>
<?php foreach ($tipos as $tipo): ?>
<option value="<?= $tipo['codigo'] ?>" data-requiere-soporte="<?= $tipo['requiere_soporte'] ?>">
<?= htmlspecialchars($tipo['nombre']) ?>
</option>
<?php endforeach; ?>
</select>
</div>
<!-- Subtipo (dinámico) -->
<div style="margin-bottom: 20px; display: none;" id="campoSubtipo">
<label class="form-label">Subtipo</label>
<select name="subtipo" class="form-input">
<option value="">Seleccione...</option>
<option value="maternidad">Maternidad</option>
<option value="paternidad">Paternidad</option>
<option value="luto">Luto</option>
<option value="enfermedad_general">Enfermedad General</option>
<option value="accidente_laboral">Accidente Laboral</option>
</select>
</div>
<!-- Fechas -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
<div>
<label class="form-label">Fecha Inicio *</label>
<input type="date" name="fecha_inicio" required class="form-input" onchange="validarFechas()">
</div>
<div>
<label class="form-label">Fecha Fin *</label>
<input type="date" name="fecha_fin" required class="form-input" onchange="validarFechas()">
</div>
</div>
<!-- Horas (para permisos cortos) -->
<div style="margin-bottom: 20px; display: none;" id="campoHoras">
<label class="form-label">Horas Solicitadas (si aplica)</label>
<input type="number" name="horas_solicitadas" step="0.5" min="0.5" class="form-input" placeholder="Ej: 2.5">
</div>
<!-- Motivo -->
<div style="margin-bottom: 20px;">
<label class="form-label">Motivo / Justificación *</label>
<textarea name="motivo" rows="4" required class="form-input" placeholder="Describa el motivo de la solicitud..."></textarea>
</div>
<!-- Soporte Documental -->
<div style="margin-bottom: 24px;" id="campoSoporte">
<label class="form-label">
<i class="fas fa-paperclip"></i> Documento Soporte
<span style="font-size: 0.8rem; color: var(--text-muted);">(PDF o imagen, máx 5MB)</span>
</label>
<input type="file" name="soporte" accept=".pdf,.jpg,.jpeg,.png" class="form-input">
<small style="color: var(--text-muted); display: block; margin-top: 6px;">
Requerido para: incapacidades, licencias y calamidades
</small>
</div>
<!-- Alerta de superposición -->
<div id="alertaSuperposicion" style="display: none; background: #fef3c7; border-left: 4px solid #f59e0b; padding: 12px; border-radius: 8px; margin-bottom: 20px; color: #92400e;">
<i class="fas fa-exclamation-triangle"></i>
<strong>Atención:</strong> Parece que ya tienes una novedad en este rango de fechas.
</div>
<!-- Botones -->
<div style="display: flex; justify-content: flex-end; gap: 12px; padding-top: 16px; border-top: 1px solid var(--border);">
<a href="/horion-time/public/novedades" class="btn" style="background: #f1f5f9;">Cancelar</a>
<button type="submit" class="btn btn-primary">
<i class="fas fa-paper-plane"></i> Enviar Solicitud
</button>
</div>
</form>
</div>
</div>
</main>
<style>
.form-label { display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 6px; color: var(--text-muted); }
.form-input { width: 100%; padding: 10px 14px; border: 1px solid var(--border); border-radius: 10px; outline: none; transition: all 0.2s; font-size: 0.95rem; }
.form-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(3, 169, 80, 0.1); }
</style>
<script>
function toggleCampos() {
const tipo = document.getElementById('tipoNovedad');
const selected = tipo.options[tipo.selectedIndex];
const requiereSoporte = selected.getAttribute('data-requiere-soporte') === '1';
// Mostrar/ocultar campo de soporte
const campoSoporte = document.getElementById('campoSoporte');
const inputSoporte = campoSoporte.querySelector('input');
if (requiereSoporte) {
campoSoporte.style.display = 'block';
inputSoporte.required = true;
} else {
campoSoporte.style.display = 'none';
inputSoporte.required = false;
}
// ================= PATCH 2 (INICIO) =================
// Compara en minúsculas para que funcione con los códigos de la BD
// (incapacidad, licencia, permiso...) sin romper la lógica original
const valor = (tipo.value || '').toLowerCase();
const esLicencia = valor.indexOf('lic') === 0 || valor.indexOf('inc') === 0;
document.getElementById('campoSubtipo').style.display = esLicencia ? 'block' : 'none';
const esPermiso = valor.indexOf('per') === 0;
document.getElementById('campoHoras').style.display = esPermiso ? 'block' : 'none';
// ================= PATCH 2 (FIN) =================
}
function validarFechas() {
const inicio = document.querySelector('[name="fecha_inicio"]').value;
const fin = document.querySelector('[name="fecha_fin"]').value;
if (inicio && fin && fin < inicio) {
alert('⚠️ La fecha final no puede ser anterior a la fecha inicial');
document.querySelector('[name="fecha_fin"]').value = '';
}
}
// Validación antes de enviar
document.getElementById('formNovedad').addEventListener('submit', function(e) {
const inicio = document.querySelector('[name="fecha_inicio"]').value;
const fin = document.querySelector('[name="fecha_fin"]').value;
if (fin < inicio) {
e.preventDefault();
alert('⚠️ La fecha final no puede ser anterior a la fecha inicial');
return false;
}
});
</script>
<?php include(__DIR__ . '/../layouts/footer.php'); ?>