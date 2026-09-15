<?php
session_start();
include(__DIR__ . '/../layouts/header.php');
include(__DIR__ . '/../layouts/sidebar.php');

// ===== CAMBIO 1: Festivos de Colombia calculados por algoritmo (Computus + Ley Emiliani) =====
$calcFestivos = function (int $anio): array {
    $a=$anio%19; $b=intdiv($anio,100); $c=$anio%100; $d=intdiv($b,4); $e=$b%4;
    $f=intdiv($b+8,25); $g=intdiv($b-$f+1,3); $h=(19*$a+$b-$d-$g+15)%30;
    $i=intdiv($c,4); $k=$c%4; $l=(32+2*$e+2*$i-$h-$k)%7; $m=intdiv($a+11*$h+22*$l,451);
    $mes=intdiv($h+$l-7*$m+114,31); $dia=(($h+$l-7*$m+114)%31)+1;
    $pascua=mktime(12,0,0,$mes,$dia,$anio);
    $sum=fn($dd)=>$pascua+$dd*86400;
    $lun=function($ts){ $t=strtotime('monday this week',$ts); return $t<$ts?$t+7*86400:$t; };
    $fest=[
        "$anio-01-01","$anio-05-01","$anio-07-20","$anio-08-07","$anio-12-08","$anio-12-25",
        date('Y-m-d',$sum(-3)), // Jueves Santo
        date('Y-m-d',$sum(-2)), // Viernes Santo
    ];
    foreach (["$anio-01-06","$anio-03-19",date('Y-m-d',$sum(39)),date('Y-m-d',$sum(60)),date('Y-m-d',$sum(68)),"$anio-10-12","$anio-11-01","$anio-11-11"] as $ff) {
        $fest[] = date('Y-m-d', $lun(strtotime($ff.' 12:00:00'))); // Emiliani: lunes siguiente
    }
    $fest=array_values(array_unique($fest)); sort($fest); return $fest;
};
$anioActual = (int)date('Y');
$festivos = array_values(array_unique(array_merge($calcFestivos($anioActual-1), $calcFestivos($anioActual))));
sort($festivos);
?>
<main class="main-content">
<div class="top-header">
<h2 style="font-weight: 700; color: var(--text-dark);">
<i class="fas fa-file-signature" style="color: var(--primary);"></i>
Solicitar Hora Extra
</h2>
<a href="/horion-time/public/horas_extras" class="btn" style="background: #f1f5f9;">
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
<form action="/horion-time/public/horas_extras/store" method="POST" enctype="multipart/form-data" id="formHoraExtra">
<input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
<input type="hidden" name="usuario_id" value="<?= $_SESSION['usuario_id'] ?? 1 ?>">
<input type="hidden" name="empresa_id" value="<?= $_SESSION['empresa_id'] ?? 1 ?>">
<!-- Fecha -->
<div style="margin-bottom: 20px;">
<label class="form-label">Fecha de la Hora Extra *</label>
<input type="date" name="fecha" required class="form-input" max="<?= date('Y-m-d') ?>" onchange="calcularHoras()">
<div id="infoDiaHE" style="margin-top:8px;font-size:.85rem;color:var(--text-muted);"></div>
</div>
<!-- Horario -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
<div>
<label class="form-label">Hora Inicio *</label>
<input type="time" name="hora_inicio_programada" required class="form-input" onchange="calcularHoras()">
</div>
<div>
<label class="form-label">Hora Fin *</label>
<input type="time" name="hora_fin_programada" required class="form-input" onchange="calcularHoras()">
</div>
</div>
<!-- Horas calculadas -->
<div style="background: rgba(3, 169, 80, 0.05); padding: 16px; border-radius: 12px; margin-bottom: 20px; border: 1px solid var(--primary);">
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
<div>
<label class="form-label">Horas Solicitadas (calculadas)</label>
<input type="number" name="horas_solicitadas" id="horasCalculadas" step="0.5" min="0.5" max="12" class="form-input" readonly style="background: #f8fafc; font-weight: 700; color: var(--primary);">
</div>
<div>
<label class="form-label">Tipo Detectado (Ley 2101/2021)</label>
<input type="text" id="tipoDetectado" class="form-input" readonly style="background: #f8fafc; font-weight: 600;">
<input type="hidden" name="tipo" id="tipoInput">
</div>
</div>
</div>
<!-- Justificación -->
<div style="margin-bottom: 20px;">
<label class="form-label">Justificación *</label>
<textarea name="justificacion" rows="4" required class="form-input" placeholder="Describa el motivo de la hora extra (proyecto urgente, cierre de mes, etc.)..."></textarea>
</div>
<!-- Soporte Documental -->
<div style="margin-bottom: 24px;">
<label class="form-label">
<i class="fas fa-paperclip"></i> Documento Soporte (Opcional)
<span style="font-size: 0.8rem; color: var(--text-muted);">(PDF o imagen, máx 5MB)</span>
</label>
<input type="file" name="soporte" accept=".pdf,.jpg,.jpeg,.png" class="form-input">
<small style="color: var(--text-muted); display: block; margin-top: 6px;">
Adjunte evidencia si aplica (correo de solicitud, autorización de jefe, etc.)
</small>
</div>
<!-- ===== CAMBIO 4: Información de recargos según ley vigente ===== -->
<div style="background: #f8fafc; padding: 16px; border-radius: 12px; margin-bottom: 24px; border-left: 4px solid var(--info);">
<h4 style="font-weight: 600; margin-bottom: 12px; color: var(--info);">
<i class="fas fa-info-circle"></i> Recargos legales vigentes (Colombia)
</h4>
<div style="font-size: 0.9rem; color: var(--text-muted); line-height: 1.8;">
<div>• Jornada diurna: <strong>06:00 – 19:00</strong> · Nocturna: <strong>19:00 – 06:00</strong> (Ley 2101 de 2021)</div>
<div>• <strong>Hora extra diurna</strong> (lun–sáb): +25%</div>
<div>• <strong>Hora extra nocturna</strong> (lun–sáb): +75%</div>
<div>• <strong>Dominical / festivo diurno</strong>: +75% (+ día compensatorio)</div>
<div>• <strong>Dominical / festivo nocturno</strong>: +110% (75% + 35% nocturno)</div>
<div>• Festivos calculados automáticamente con regla Emiliani y Semana Santa del año</div>
</div>
</div>
<!-- Botones -->
<div style="display: flex; justify-content: flex-end; gap: 12px; padding-top: 16px; border-top: 1px solid var(--border);">
<a href="/horion-time/public/horas_extras" class="btn" style="background: #f1f5f9;">Cancelar</a>
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
// ===== CAMBIO 2: festivos servidos por PHP (sin listas hardcodeadas) =====
const FESTIVOS = <?= json_encode($festivos) ?>;

function minutosDe(hhmm) {
    const [h, m] = hhmm.split(':').map(Number);
    return h * 60 + m;
}

function calcularHoras() {
    const inicio = document.querySelector('[name="hora_inicio_programada"]').value;
    const fin = document.querySelector('[name="hora_fin_programada"]').value;
    if (inicio && fin) {
        let minutos = minutosDe(fin) - minutosDe(inicio);
        if (minutos < 0) minutos += 24 * 60; // Cruza medianoche
        document.getElementById('horasCalculadas').value = (minutos / 60).toFixed(2);
    }
    calcularTipo();
}

// ===== CAMBIO 3: clasificación por franja minuto a minuto, sin bug de UTC =====
function calcularTipo() {
    const fecha = document.querySelector('[name="fecha"]').value;
    const hi = document.querySelector('[name="hora_inicio_programada"]').value;
    const hf = document.querySelector('[name="hora_fin_programada"]').value;
    const info = document.getElementById('infoDiaHE');
    if (!fecha || !hi || !hf) return;

    // Día de la semana SIN bug de UTC (construye la fecha por componentes)
    const [y, m, d] = fecha.split('-').map(Number);
    const diaSemana = new Date(y, m - 1, d).getDay(); // 0 = Domingo
    const esFestivo = FESTIVOS.includes(fecha);
    const esDominical = (diaSemana === 0) || esFestivo;

    // Minutos en franja diurna (06:00-19:00) vs nocturna (19:00-06:00) — Ley 2101/2021
    let a = minutosDe(hi), b = minutosDe(hf);
    if (b <= a) b += 1440;
    let diurnos = 0, nocturnos = 0;
    for (let mm = a; mm < b; mm++) {
        const x = mm % 1440;
        if (x >= 360 && x < 1140) diurnos++; else nocturnos++;
    }
    const mayorDiurna = diurnos >= nocturnos;

    let tipo, label;
    if (esDominical) {
        tipo  = mayorDiurna ? 'dominical_festiva_diurna' : 'dominical_festiva_nocturna';
        label = mayorDiurna ? 'Dominical / Festiva Diurna (+75%)' : 'Dominical / Festiva Nocturna (+110%)';
    } else {
        tipo  = mayorDiurna ? 'extra_diurna' : 'extra_nocturna';
        label = mayorDiurna ? 'Hora Extra Diurna (+25%)' : 'Hora Extra Nocturna (+75%)';
    }
    document.getElementById('tipoDetectado').value = label;
    document.getElementById('tipoInput').value = tipo;

    const dias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    info.innerHTML = '📅 <strong>' + dias[diaSemana] + '</strong>' +
        (esFestivo ? ' · <span class="badge badge-danger">🎉 Festivo en Colombia</span>' : '') +
        ' · Zona horaria: America/Bogota';
}
</script>
<?php include(__DIR__ . '/../layouts/footer.php'); ?>