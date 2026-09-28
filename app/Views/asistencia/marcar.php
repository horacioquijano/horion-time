<?php
include __DIR__ . '/../layouts/header.php';
$csrf_token = $csrf_token ?? (class_exists('App\Helpers\Security') ? \App\Helpers\Security::generateCsrfToken() : bin2hex(random_bytes(32)));
$marcaciones_hoy = $marcaciones_hoy ?? [];
$sedes = $sedes ?? [];

// =====================================================================
// TURNO DE HOY SEGÚN PANEL DE TURNOS (letra del día → horas reales)
// =====================================================================
$turno_hoy = null;
$noche_en_curso = null;
try {
    $__pdo = (isset($db) && is_object($db)) ? $db : \App\Config\Database::getInstance()->getConnection();
    $__uid = (int)($_SESSION['usuario_id'] ?? 0);
    $__st = $__pdo->prepare("SELECT tc.codigo, tc.servicio, pt.nombre AS turno_nombre, pt.hora_entrada, pt.hora_salida,
                                    pt.horas_trabajadas, pt.es_descanso, pt.es_vacacion, pt.recargo_nocturno, pt.color
                             FROM turnos_calendario tc
                             LEFT JOIN parametros_turnos pt ON pt.codigo = tc.codigo
                             WHERE tc.usuario_id = ? AND tc.fecha = ? LIMIT 1");
    $__st->execute([$__uid, date('Y-m-d')]);
    $turno_hoy = $__st->fetch(PDO::FETCH_ASSOC) ?: null;

    // Madrugada: si hoy no hay letra, revisar si ayer había turno NOCHE (cruce de medianoche)
    if (!$turno_hoy && (int)date('G') < 12) {
        $__st2 = $__pdo->prepare("SELECT tc.codigo, pt.nombre AS turno_nombre, pt.hora_entrada, pt.hora_salida, pt.color
                                  FROM turnos_calendario tc
                                  LEFT JOIN parametros_turnos pt ON pt.codigo = tc.codigo
                                  WHERE tc.usuario_id = ? AND tc.fecha = ? AND pt.recargo_nocturno = 1 LIMIT 1");
        $__st2->execute([$__uid, date('Y-m-d', strtotime('-1 day'))]);
        $noche_en_curso = $__st2->fetch(PDO::FETCH_ASSOC) ?: null;
    }
} catch (Throwable $e) { $turno_hoy = null; }

$mesesEs = [1=>'enero',2=>'febrero',3=>'marzo',4=>'abril',5=>'mayo',6=>'junio',7=>'julio',8=>'agosto',9=>'septiembre',10=>'octubre',11=>'noviembre',12=>'diciembre'];
$diasEs  = ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'];
?>

<div class="kiosk-wrap">

    <!-- ===== Reloj + fecha + turno de hoy ===== -->
    <div class="kiosk-top">
        <div>
            <div id="reloj" class="kiosk-clock">00:00:00</div>
            <div class="kiosk-date"><?= $diasEs[date('w')] ?>, <?= (int)date('j') ?> de <?= $mesesEs[(int)date('n')] ?> de <?= date('Y') ?></div>
        </div>
        <div class="kiosk-turno">
            <?php if ($turno_hoy): ?>
                <span class="kchip" style="--c:<?= htmlspecialchars($turno_hoy['color'] ?? '#64748b') ?>">
                    <i></i> Hoy: <?= htmlspecialchars($turno_hoy['turno_nombre'] ?? $turno_hoy['codigo']) ?>
                    <?php if ($turno_hoy['hora_entrada']): ?>(<?= substr($turno_hoy['hora_entrada'],0,5) ?> – <?= substr($turno_hoy['hora_salida'],0,5) ?>)<?php endif; ?>
                </span>
                <?php if (!empty($turno_hoy['servicio'])): ?><span class="kchip kchip-soft">📍 <?= htmlspecialchars($turno_hoy['servicio']) ?></span><?php endif; ?>
                <?php if ($turno_hoy['es_vacacion']): ?><span class="kchip kchip-warn">🌴 Estás en VACACIONES según el panel</span><?php endif; ?>
                <?php if ($turno_hoy['es_descanso'] && !$turno_hoy['es_vacacion']): ?><span class="kchip kchip-warn">😴 Hoy es tu día LIBRE según el panel</span><?php endif; ?>
            <?php elseif ($noche_en_curso): ?>
                <span class="kchip" style="--c:<?= htmlspecialchars($noche_en_curso['color'] ?? '#3b82f6') ?>">
                    <i></i> Turno NOCHE en curso (inició ayer <?= substr($noche_en_curso['hora_entrada'],0,5) ?>)
                </span>
            <?php else: ?>
                <span class="kchip kchip-warn">⚠️ Sin turno programado para hoy en el Panel de Turnos</span>
            <?php endif; ?>
        </div>
    </div>

    <?php if (isset($_GET['success'])): ?>
    <div class="card-3d" style="background:#dcfce7;border-left:4px solid var(--success);margin-bottom:18px;color:#166534;">
        <i class="fas fa-check-circle"></i> ¡Marcación registrada exitosamente!
    </div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
    <div class="card-3d" style="background:#fef2f2;border-left:4px solid #dc2626;margin-bottom:18px;color:#991b1b;">
        <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($_GET['error']) ?>
    </div>
    <?php endif; ?>

    <!-- ===== Kiosco: cámara + panel mínimo ===== -->
    <div class="kiosk-grid">
        <!-- Cámara -->
        <div class="card-3d kiosk-cam">
            <div class="cam-box">
                <video id="video" autoplay playsinline muted></video>
                <canvas id="canvas" style="display:none;"></canvas>
                <div class="cam-oval"></div>
                <div id="faceStatus" class="cam-status">Iniciando cámara…</div>
            </div>
            <div id="fotoPreview" style="display:none;margin-top:12px;">
                <img id="previewImg" style="width:100%;border-radius:14px;border:3px solid var(--primary);" />
            </div>
            <div style="display:flex;gap:10px;margin-top:12px;">
                <button type="button" class="btn" style="flex:1;background:var(--bg-body);" onclick="iniciarCamara()"><i class="fas fa-video"></i> Reiniciar cámara</button>
                <button type="button" class="btn" style="flex:1;background:var(--bg-body);" onclick="capturarFoto(false)" id="btnCapturar" disabled><i class="fas fa-camera-retro"></i> Captura manual</button>
            </div>
        </div>

        <!-- Panel mínimo -->
        <div class="card-3d kiosk-panel">
            <h3 style="margin:0 0 4px 0;font-weight:800;">Hola, <?= htmlspecialchars(explode(' ', $_SESSION['nombre_completo'] ?? 'colaborador')[0]) ?> 👋</h3>
            <p style="color:var(--text-muted);font-size:.85rem;margin:0 0 18px 0;">Ubícate frente a la cámara. El rostro se detecta y captura solo.</p>

            <form action="/horion-time/public/asistencia/procesar" method="POST" id="formMarcacion">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="foto_data" id="fotoData">
                <input type="hidden" name="lat" id="latInput">
                <input type="hidden" name="lng" id="lngInput">
                <input type="hidden" name="metodo_marcacion" value="facial">
                <input type="hidden" name="tipo_marcacion" id="tipoMarcacionInput" value="entrada">

                <label class="form-label">Sucursal</label>
                <select name="sede_id" id="sedeSelect" class="form-input" style="margin-bottom:16px;" required>
                    <option value="">Seleccione sucursal…</option>
                    <?php
                    $grupos = [];
                    foreach (($sedes ?? []) as $s) { $grupos[(int)$s['empresa_id']][] = $s; }
                    foreach ($grupos as $eid => $lista):
                        $cab = $lista[0]['empresa_nombre'] ?? 'Empresa';
                    ?>
                    <optgroup label="🏢 <?= htmlspecialchars($cab) ?>">
                        <?php foreach ($lista as $s): ?>
                        <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['nombre']) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                    <?php endforeach; ?>
                </select>

                <label class="form-label">Tipo de marcación</label>
                <div class="ktipos">
                    <button type="button" class="ktipo active" data-tipo="entrada" onclick="setTipo(this)">🟢 Entrada</button>
                    <button type="button" class="ktipo" data-tipo="salida_almuerzo" onclick="setTipo(this)">🍽️ Salida almuerzo</button>
                    <button type="button" class="ktipo" data-tipo="regreso_almuerzo" onclick="setTipo(this)">🍽️ Regreso almuerzo</button>
                    <button type="button" class="ktipo" data-tipo="salida" onclick="setTipo(this)">🔴 Salida</button>
                </div>

                <button type="submit" class="btn btn-primary kiosk-submit" onclick="return prepararMarcacion()">
                    <i class="fas fa-fingerprint"></i> REGISTRAR MARCACIÓN
                </button>
                <div id="gpsMini" style="text-align:center;font-size:.75rem;color:var(--text-muted);margin-top:10px;"> Obteniendo ubicación…</div>
            </form>
        </div>
    </div>

    <!-- ===== Mis marcaciones de hoy (compacto) ===== -->
    <div class="card-3d" style="margin-top:20px;padding:16px 20px;">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
            <strong style="font-size:.95rem;"><i class="fas fa-history" style="color:var(--primary);"></i> Mis marcaciones de hoy</strong>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <?php if (empty($marcaciones_hoy)): ?>
                    <span class="kchip kchip-soft">Sin marcaciones aún</span>
                <?php else: foreach ($marcaciones_hoy as $m): ?>
                    <span class="kchip kchip-soft">
                        <?= substr($m['hora_registro'],0,5) ?> · <?= htmlspecialchars(ucfirst(str_replace('_',' ',$m['tipo_marcacion']))) ?>
                        · <em><?= htmlspecialchars(ucfirst($m['estado'])) ?></em>
                    </span>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.kiosk-wrap{max-width:1000px;margin:0 auto;padding:10px 16px 40px;}
.kiosk-top{display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:14px;margin-bottom:18px;}
.kiosk-clock{font-size:clamp(2.2rem,6vw,3.4rem);font-weight:900;font-family:monospace;color:var(--primary);line-height:1;letter-spacing:2px;}
.kiosk-date{color:var(--text-muted);font-size:.95rem;text-transform:capitalize;margin-top:4px;}
.kiosk-turno{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end;}
.kchip{display:inline-flex;align-items:center;gap:7px;background:#fff;border:1px solid var(--border,#e2e8f0);border-radius:999px;padding:7px 14px;font-size:.82rem;font-weight:700;color:var(--text-dark);}
.kchip i{width:10px;height:10px;border-radius:50%;background:var(--c,#64748b);display:inline-block;}
.kchip-soft{background:var(--bg-body,#f1f5f9);font-weight:600;color:var(--text-muted);}
.kchip-warn{background:#fef3c7;border-color:#fde68a;color:#92400e;}
.kiosk-grid{display:grid;grid-template-columns:1.1fr 1fr;gap:20px;}
.kiosk-cam,.kiosk-panel{padding:20px;}
.cam-box{position:relative;background:#000;border-radius:18px;overflow:hidden;aspect-ratio:4/3;}
.cam-box video{width:100%;height:100%;object-fit:cover;}
.cam-oval{position:absolute;top:50%;left:50%;transform:translate(-50%,-52%);width:52%;height:70%;border:3px solid rgba(3,169,80,.85);border-radius:50%;box-shadow:0 0 0 9999px rgba(0,0,0,.25);pointer-events:none;}
.cam-status{position:absolute;bottom:12px;left:50%;transform:translateX(-50%);background:rgba(0,0,0,.72);color:#fff;padding:6px 16px;border-radius:999px;font-size:.82rem;font-weight:700;white-space:nowrap;backdrop-filter:blur(6px);}
.ktipos{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:18px;}
.ktipo{padding:12px 8px;border-radius:12px;border:2px solid var(--border,#e2e8f0);background:var(--bg-body,#f8fafc);font-weight:700;font-size:.85rem;cursor:pointer;transition:.2s;color:var(--text-dark);}
.ktipo.active{border-color:var(--primary);background:rgba(3,169,80,.10);color:var(--primary);}
.kiosk-submit{width:100%;padding:16px;font-size:1.05rem;font-weight:800;border-radius:14px;}
.kiosk-submit.pulse{animation:kpulse 1.2s infinite;}
@keyframes kpulse{0%,100%{box-shadow:0 0 0 0 rgba(3,169,80,.5);}50%{box-shadow:0 0 0 12px rgba(3,169,80,0);}}
@media (max-width:860px){
    .kiosk-grid{grid-template-columns:1fr;}
    .kiosk-top{flex-direction:column;align-items:flex-start;}
    .kiosk-turno{justify-content:flex-start;}
}
</style>

<script>
let stream = null, fotoCapturada = false;
let faceDetector = null, faceInterval = null, caraEstable = 0;
const UMBRAL = 4; // ~2 s de rostro estable → captura automática

function actualizarReloj(){
    document.getElementById('reloj').textContent = new Date().toLocaleTimeString('es-CO',{hour12:false,timeZone:'America/Bogota'});
}
setInterval(actualizarReloj,1000); actualizarReloj();

function setStatus(txt, tipo){
    const el = document.getElementById('faceStatus');
    el.textContent = txt;
    el.style.background = tipo==='ok' ? 'rgba(22,101,52,.92)' : (tipo==='warn' ? 'rgba(146,64,14,.92)' : 'rgba(0,0,0,.72)');
}

async function iniciarCamara(){
    setStatus('Solicitando cámara…','warn');
    if (stream) stream.getTracks().forEach(t=>t.stop());
    try{
        stream = await navigator.mediaDevices.getUserMedia({video:{facingMode:'user',width:{ideal:1280}},audio:false});
        const v = document.getElementById('video');
        v.srcObject = stream;
        document.getElementById('btnCapturar').disabled = false;
        await new Promise(r => v.readyState>=2 ? r() : (v.onloadeddata=r));
        setStatus('✓ Cámara activa — detectando rostro…','ok');
        iniciarDeteccion();
    }catch(e){ setStatus('❌ Sin cámara: ' + e.message,'warn'); }
}

function iniciarDeteccion(){
    if (!('FaceDetector' in window)) { setStatus('📷 Captura manual disponible (sin detección automática)',''); return; }
    try { faceDetector = new FaceDetector({fastMode:true,maxDetectedFaces:1}); } catch(e){ return; }
    caraEstable = 0;
    if (faceInterval) clearInterval(faceInterval);
    faceInterval = setInterval(detectar, 500);
}

async function detectar(){
    if (!faceDetector || !stream) return;
    try{
        const faces = await faceDetector.detect(document.getElementById('video'));
        if (faces.length > 0){
            caraEstable++;
            if (caraEstable >= UMBRAL && !fotoCapturada){
                clearInterval(faceInterval); faceInterval = null;
                setStatus('✓ Rostro reconocido — capturando…','ok');
                setTimeout(()=>capturarFoto(true), 250);
            } else if (!fotoCapturada){
                setStatus('🎯 Rostro detectado — sincronizando…','warn');
            }
        } else {
            caraEstable = 0;
            if (!fotoCapturada) setStatus('Ubique su rostro dentro del óvalo','');
        }
    }catch(e){}
}

function capturarFoto(auto){
    const v = document.getElementById('video'), c = document.getElementById('canvas'), ctx = c.getContext('2d');
    c.width = v.videoWidth; c.height = v.videoHeight; ctx.drawImage(v,0,0);
    const data = c.toDataURL('image/jpeg',0.8);
    fotoCapturada = true;
    document.getElementById('fotoData').value = data;
    document.getElementById('previewImg').src = data;
    document.getElementById('fotoPreview').style.display = 'block';
    if (faceInterval){ clearInterval(faceInterval); faceInterval = null; }
    setStatus(auto ? '✓ Rostro verificado y capturado' : '✓ Foto capturada','ok');
    document.querySelector('.kiosk-submit').classList.add('pulse');
    if (window.showToast) showToast(auto ? 'Rostro reconocido' : 'Foto capturada','success');
}

function setTipo(btn){
    document.querySelectorAll('.ktipo').forEach(b=>b.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('tipoMarcacionInput').value = btn.dataset.tipo;
}

function obtenerUbicacion(){
    const el = document.getElementById('gpsMini');
    if(!navigator.geolocation){ el.textContent='📡 GPS no soportado (se registrará sin ubicación)'; return; }
    navigator.geolocation.getCurrentPosition(p=>{
        document.getElementById('latInput').value = p.coords.latitude;
        document.getElementById('lngInput').value = p.coords.longitude;
        el.textContent = '📡 Ubicación obtenida (±' + Math.round(p.coords.accuracy) + ' m)';
    },()=>{ el.textContent='📡 Sin GPS: se registrará sin ubicación'; },{enableHighAccuracy:true,timeout:10000});
}

function prepararMarcacion(){
    if(!fotoCapturada){ alert('⚠️ Espera la captura del rostro o usa “Captura manual”.'); return false; }
    if(!document.getElementById('sedeSelect').value){ alert('⚠️ Selecciona tu sucursal.'); return false; }
    return true;
}

window.addEventListener('load', ()=>{ obtenerUbicacion(); setTimeout(iniciarCamara, 400); });
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
