<?php
session_start();
include(__DIR__ . '/../layouts/header.php');
include(__DIR__ . '/../layouts/sidebar.php');

// ================= PATCH (INICIO) =================
// Garantiza que TODAS las claves de $config existan antes de usarse.
// Elimina los warnings "Undefined array key" (incluido resumen_diario_email)
// sin importar lo que envíe el controlador o lo que tenga la tabla.
$config = (isset($config) && is_array($config)) ? $config : [];
$config = array_merge([
    'jornada_semanal_horas'       => 44,
    'tolerancia_entrada_min'      => 10,
    'tolerancia_salida_min'       => 10,
    'permitir_marcacion_temprana' => 1,
    'requerir_foto_marcacion'     => 1,
    'requerir_geolocalizacion'    => 1,
    'radio_gps_metros'            => 200,
    'notificaciones_email'        => 1,
    'resumen_diario_email'        => 1,
    'auditoria_activa'            => 1,
    'dias_descanso'               => [0],
    'festivos_personalizados'     => [],
    'motor_reglas'                => [],
], $config);
if (!is_array($config['dias_descanso']))           $config['dias_descanso'] = [0];
if (!is_array($config['festivos_personalizados'])) $config['festivos_personalizados'] = [];
if (!is_array($config['motor_reglas']))            $config['motor_reglas'] = [];
// Evita warning si el controlador no define el token
$csrf_token = $csrf_token ?? bin2hex(random_bytes(32));
// ================= PATCH (FIN) =================
?>
<main class="main-content">
<div class="top-header">
<h2 style="font-weight: 700; color: var(--text-dark);">
<i class="fas fa-cogs" style="color: var(--primary);"></i>
Configuración y Reglas del Sistema
</h2>
<span class="badge" style="background: rgba(3, 169, 80, 0.1); color: var(--primary);">
<i class="fas fa-lock"></i> Solo Admin
</span>
</div>
<div style="padding: 32px; max-width: 1200px; margin: 0 auto;">
<?php if (isset($_GET['success'])): ?>
<div class="card-3d" style="background: #dcfce7; border-left: 4px solid var(--success); margin-bottom: 24px; color: #166534; display: flex; align-items: center; gap: 10px;">
<i class="fas fa-check-circle"></i> Configuración actualizada exitosamente.
</div>
<?php endif; ?>
<!-- Tabs de Navegación -->
<div style="display: flex; gap: 8px; margin-bottom: 24px; border-bottom: 2px solid var(--border); overflow-x: auto;">
<button class="tab-btn active" onclick="cambiarTab('general', this)">⚙️ General</button>
<button class="tab-btn" onclick="cambiarTab('marcacion', this)">📍 Marcación y GPS</button>
<button class="tab-btn" onclick="cambiarTab('reglas', this)">🧮 Motor de Reglas</button>
<button class="tab-btn" onclick="cambiarTab('calendario', this)">📅 Calendario</button>
<button class="tab-btn" onclick="cambiarTab('sistema', this)">🔒 Sistema y Seguridad</button>
</div>
<form action="/horion-time/public/configuracion/update" method="POST" id="formConfig">
<input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
<!-- TAB 1: GENERAL -->
<div class="tab-content" id="tab-general">
<div class="card-3d">
<h3 style="font-weight: 700; margin-bottom: 20px;">Parámetros de Jornada</h3>
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
<div>
<label class="form-label">Horas Semanales Obligatorias</label>
<input type="number" name="horas_semanales" value="<?= $config['jornada_semanal_horas'] ?>" step="0.5" class="form-input">
</div>
<div>
<label class="form-label">Tolerancia de Entrada (minutos)</label>
<input type="number" name="tolerancia_entrada" value="<?= $config['tolerancia_entrada_min'] ?>" class="form-input">
</div>
<div>
<label class="form-label">Tolerancia de Salida (minutos)</label>
<input type="number" name="tolerancia_salida" value="<?= $config['tolerancia_salida_min'] ?>" class="form-input">
</div>
</div>
</div>
</div>
<!-- TAB 2: MARCACIÓN -->
<div class="tab-content" id="tab-marcacion" style="display: none;">
<div class="card-3d">
<h3 style="font-weight: 700; margin-bottom: 20px;">Seguridad Biométrica y Geolocalización</h3>
<div style="display: flex; flex-direction: column; gap: 16px;">
<?= toggleSwitch('marcacion_temprana', 'Permitir marcación temprana', 'Permite a los empleados marcar entrada antes de la hora programada.', $config['permitir_marcacion_temprana']) ?>
<?= toggleSwitch('requerir_foto', 'Exigir foto en cada marcación', 'Obliga a capturar evidencia fotográfica al marcar entrada/salida.', $config['requerir_foto_marcacion']) ?>
<?= toggleSwitch('requerir_gps', 'Exigir geolocalización GPS', 'Valida que el empleado esté dentro del radio permitido de la sede.', $config['requerir_geolocalizacion']) ?>
<div style="margin-top: 10px; padding: 16px; background: #f8fafc; border-radius: 10px;">
<label class="form-label">Radio de validez GPS (metros)</label>
<input type="number" name="radio_gps" value="<?= $config['radio_gps_metros'] ?>" class="form-input" style="max-width: 300px;">
<small style="color: var(--text-muted);">Distancia máxima permitida desde la sede para validar la marcación.</small>
</div>
</div>
</div>
</div>
<!-- TAB 3: MOTOR DE REGLAS -->
<div class="tab-content" id="tab-reglas" style="display: none;">
<div class="card-3d">
<h3 style="font-weight: 700; margin-bottom: 8px;">Motor de Reglas y Recargos Salariales</h3>
<p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 24px;">Configura los porcentajes de recargo y horarios de jornada según la ley.</p>
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
<!-- Jornada Diurna -->
<div class="rule-card">
<div class="rule-header" style="background: #fef3c7; color: #92400e;">☀️ Jornada Diurna</div>
<div class="rule-body">
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;">
<div><label class="form-label">Inicio</label><input type="time" name="reg_jd_ini" value="<?= $config['motor_reglas']['jornada_diurna']['inicio'] ?? '06:00' ?>" class="form-input"></div>
<div><label class="form-label">Fin</label><input type="time" name="reg_jd_fin" value="<?= $config['motor_reglas']['jornada_diurna']['fin'] ?? '22:00' ?>" class="form-input"></div>
</div>
<label class="form-label">Recargo (%)</label>
<input type="number" name="reg_jd_rec" value="<?= $config['motor_reglas']['jornada_diurna']['recargo'] ?? 0 ?>" class="form-input" readonly style="background: #f1f5f9;">
</div>
</div>
<!-- Jornada Nocturna -->
<div class="rule-card">
<div class="rule-header" style="background: #e0e7ff; color: #3730a3;">🌙 Jornada Nocturna</div>
<div class="rule-body">
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;">
<div><label class="form-label">Inicio</label><input type="time" name="reg_jn_ini" value="<?= $config['motor_reglas']['jornada_nocturna']['inicio'] ?? '22:00' ?>" class="form-input"></div>
<div><label class="form-label">Fin</label><input type="time" name="reg_jn_fin" value="<?= $config['motor_reglas']['jornada_nocturna']['fin'] ?? '06:00' ?>" class="form-input"></div>
</div>
<label class="form-label">Recargo (%)</label>
<input type="number" name="reg_jn_rec" value="<?= $config['motor_reglas']['jornada_nocturna']['recargo'] ?? 35 ?>" class="form-input">
</div>
</div>
<!-- Hora Extra Diurna -->
<div class="rule-card">
<div class="rule-header" style="background: #dcfce7; color: #166534;">⏱️ Hora Extra Diurna</div>
<div class="rule-body">
<label class="form-label">Recargo (%)</label>
<input type="number" name="reg_hed_rec" value="<?= $config['motor_reglas']['hora_extra_diurna']['recargo'] ?? 25 ?>" class="form-input">
</div>
</div>
<!-- Hora Extra Nocturna -->
<div class="rule-card">
<div class="rule-header" style="background: #f3e8ff; color: #6b21a8;">⏱️ Hora Extra Nocturna</div>
<div class="rule-body">
<label class="form-label">Recargo (%)</label>
<input type="number" name="reg_hen_rec" value="<?= $config['motor_reglas']['hora_extra_nocturna']['recargo'] ?? 75 ?>" class="form-input">
</div>
</div>
<!-- Dominical -->
<div class="rule-card">
<div class="rule-header" style="background: #fce7f3; color: #9d174d;">📅 Trabajo Dominical</div>
<div class="rule-body">
<label class="form-label">Recargo (%)</label>
<input type="number" name="reg_dom_rec" value="<?= $config['motor_reglas']['dominical']['recargo'] ?? 75 ?>" class="form-input">
</div>
</div>
<!-- Festivo -->
<div class="rule-card">
<div class="rule-header" style="background: #fee2e2; color: #991b1b;">🎉 Trabajo Festivo</div>
<div class="rule-body">
<label class="form-label">Recargo (%)</label>
<input type="number" name="reg_fest_rec" value="<?= $config['motor_reglas']['festivo']['recargo'] ?? 100 ?>" class="form-input">
</div>
</div>
</div>
</div>
</div>
<!-- TAB 4: CALENDARIO -->
<div class="tab-content" id="tab-calendario" style="display: none;">
<div class="card-3d">
<h3 style="font-weight: 700; margin-bottom: 20px;">Días de Descanso Semanal</h3>
<div style="display: flex; gap: 10px; flex-wrap: wrap;">
<?php
$dias = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
foreach ($dias as $i => $dia):
$checked = in_array($i, $config['dias_descanso']) ? 'checked' : '';
?>
<label class="dia-check">
<input type="checkbox" name="descanso[]" value="<?= $i ?>" <?= $checked ?>>
<span><?= $dia ?></span>
</label>
<?php endforeach; ?>
</div>
<h3 style="font-weight: 700; margin: 32px 0 16px 0;">Festivos Personalizados</h3>
<p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 12px;">Agrega fechas festivas adicionales a las nacionales.</p>
<textarea name="festivos_text" rows="4" class="form-input" placeholder="Una fecha por línea (YYYY-MM-DD)&#10;2026-08-07&#10;2026-11-02"><?= implode("\n", $config['festivos_personalizados']) ?></textarea>
<input type="hidden" name="festivos_json" id="festivos_json">
</div>
</div>
<!-- TAB 5: SISTEMA -->
<div class="tab-content" id="tab-sistema" style="display: none;">
<div class="card-3d">
<h3 style="font-weight: 700; margin-bottom: 20px;">Preferencias del Sistema</h3>
<div style="display: flex; flex-direction: column; gap: 16px;">
<?= toggleSwitch('notificaciones_email', 'Activar notificaciones por Email', 'Envía alertas y resúmenes diarios a los correos registrados.', $config['notificaciones_email']) ?>
<?= toggleSwitch('resumen_diario_email', 'Resumen diario por Email', 'Envía cada noche el consolidado de asistencia del día.', $config['resumen_diario_email']) ?>
<?= toggleSwitch('auditoria_activa', 'Auditoría y Logs Activos', 'Registra todas las acciones críticas de los usuarios en el sistema.', $config['auditoria_activa']) ?>
</div>
</div>
</div>
<!-- Botón Flotante de Guardar -->
<div style="position: sticky; bottom: 24px; display: flex; justify-content: flex-end; margin-top: 24px; z-index: 10;">
<button type="submit" class="btn btn-primary" style="padding: 14px 32px; font-size: 1.05rem; box-shadow: 0 10px 25px rgba(3, 169, 80, 0.3);">
<i class="fas fa-save"></i> Guardar Configuración
</button>
</div>
</form>
</div>
</main>
<style>
/* Estilos específicos para Configuración */
.tab-btn {
padding: 12px 20px; background: none; border: none; border-bottom: 3px solid transparent;
font-weight: 600; color: var(--text-muted); cursor: pointer; transition: all 0.2s; white-space: nowrap; font-size: 0.95rem;
}
.tab-btn:hover { color: var(--primary); }
.tab-btn.active { color: var(--primary); border-bottom-color: var(--primary); }
/* Toggle Switch Futurista */
.toggle-row { display: flex; justify-content: space-between; align-items: center; padding: 16px; background: #f8fafc; border-radius: 12px; border: 1px solid var(--border); }
.toggle-info h4 { margin: 0 0 4px 0; font-size: 0.95rem; color: var(--text-dark); }
.toggle-info p { margin: 0; font-size: 0.8rem; color: var(--text-muted); }
.switch { position: relative; display: inline-block; width: 50px; height: 26px; }
.switch input { opacity: 0; width: 0; height: 0; }
.slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .4s; border-radius: 34px; }
.slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
input:checked + .slider { background-color: var(--primary); }
input:checked + .slider:before { transform: translateX(24px); }
/* Rule Cards */
.rule-card { background: var(--bg-surface); border-radius: 12px; overflow: hidden; border: 1px solid var(--border); box-shadow: var(--shadow-sm); transition: transform 0.2s; }
.rule-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }
.rule-header { padding: 12px 16px; font-weight: 700; font-size: 0.9rem; }
.rule-body { padding: 16px; }
/* Días de descanso */
.dia-check { position: relative; cursor: pointer; }
.dia-check input { display: none; }
.dia-check span { display: block; padding: 10px 16px; background: #f1f5f9; border-radius: 8px; font-weight: 600; color: var(--text-muted); transition: all 0.2s; border: 2px solid transparent; }
.dia-check input:checked + span { background: rgba(3, 169, 80, 0.1); color: var(--primary); border-color: var(--primary); }
</style>
<?php
// Helper para generar toggles
function toggleSwitch($name, $title, $desc, $checked) {
$isChecked = $checked ? 'checked' : '';
return "
<div class='toggle-row'>
<div class='toggle-info'>
<h4>$title</h4>
<p>$desc</p>
</div>
<label class='switch'>
<input type='checkbox' name='$name' $isChecked>
<span class='slider'></span>
</label>
</div>";
}
?>
<script>
function cambiarTab(tabId, btn) {
document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
document.querySelectorAll('.tab-content').forEach(c => c.style.display = 'none');
btn.classList.add('active');
document.getElementById('tab-' + tabId).style.display = 'block';
}
// Convertir textarea de festivos a JSON antes de enviar
document.getElementById('formConfig').addEventListener('submit', function(e) {
const text = document.querySelector('[name="festivos_text"]').value;
const fechas = text.split('\n').map(f => f.trim()).filter(f => f !== '');
document.getElementById('festivos_json').value = JSON.stringify(fechas);
});
</script>
<?php include(__DIR__ . '/../layouts/footer.php'); ?>