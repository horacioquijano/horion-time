<?php
include __DIR__ . '/../layouts/header.php';
$csrf_token = $csrf_token ?? (class_exists('App\Helpers\Security') ? \App\Helpers\Security::generateCsrfToken() : bin2hex(random_bytes(32)));
$marcaciones_hoy = $marcaciones_hoy ?? [];
$sedes = $sedes ?? [];

// =====================================================================
// 1) TURNO DE HOY SEGÚN PANEL DE TURNOS
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

    if (!$turno_hoy && (int)date('G') < 12) {
        $__st2 = $__pdo->prepare("SELECT tc.codigo, pt.nombre AS turno_nombre, pt.hora_entrada, pt.hora_salida, pt.color
                                  FROM turnos_calendario tc
                                  LEFT JOIN parametros_turnos pt ON pt.codigo = tc.codigo
                                  WHERE tc.usuario_id = ? AND tc.fecha = ? AND pt.recargo_nocturno = 1 LIMIT 1");
        $__st2->execute([$__uid, date('Y-m-d', strtotime('-1 day'))]);
        $noche_en_curso = $__st2->fetch(PDO::FETCH_ASSOC) ?: null;
    }
} catch (Throwable $e) { $turno_hoy = null; }

// =====================================================================
// 2) VERIFICAR SI EL USUARIO TIENE ROSTRO ENROLADO
// =====================================================================
$tiene_rostro = false;
try {
    $__st3 = $__pdo->prepare("SELECT face_descriptor FROM usuarios WHERE id = ? LIMIT 1");
    $__st3->execute([$__uid]);
    $fd = $__st3->fetchColumn();
    $tiene_rostro = !empty($fd);
} catch (Throwable $e) {}

// =====================================================================
// 3) SEDES CON GEOLOCALIZACIÓN
// =====================================================================
$sedes_json = json_encode(array_map(function($s) {
    return [
        'id' => (int)$s['id'],
        'nombre' => $s['nombre'],
        'lat_ref' => $s['lat_ref'] ?? null,
        'lng_ref' => $s['lng_ref'] ?? null,
        'radio_m' => (int)($s['radio_m'] ?? 150),
    ];
}, $sedes));

$mesesEs = [1=>'enero',2=>'febrero',3=>'marzo',4=>'abril',5=>'mayo',6=>'junio',7=>'julio',8=>'agosto',9=>'septiembre',10=>'octubre',11=>'noviembre',12=>'diciembre'];
$diasEs  = ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'];

// FASE C: detectar iOS/Safari
$es_ios = isset($_SERVER['HTTP_USER_AGENT']) && preg_match('/iPad|iPhone|iPod/', $_SERVER['HTTP_USER_AGENT']);
?>

<!-- face-api.js desde CDN -->
<script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>

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
                <canvas id="overlay" style="position:absolute;inset:0;width:100%;height:100%;pointer-events:none;"></canvas>
                <div class="cam-oval"></div>
                <div id="faceStatus" class="cam-status">Cargando modelos IA…</div>
            </div>
            <div id="fotoPreview" style="display:none;margin-top:12px;">
                <img id="previewImg" style="width:100%;border-radius:14px;border:3px solid var(--primary);" />
            </div>
            <div style="display:flex;gap:10px;margin-top:12px;">
                <button type="button" class="btn" style="flex:1;background:var(--bg-body);" onclick="reiniciar()"><i class="fas fa-video"></i> Reiniciar cámara</button>
                <button type="button" class="btn" style="flex:1;background:var(--bg-body);" onclick="capturarFoto(false)" id="btnCapturar" disabled><i class="fas fa-camera-retro"></i> Captura manual</button>
            </div>
        </div>

        <!-- Panel mínimo -->
        <div class="card-3d kiosk-panel">
            <h3 style="margin:0 0 4px 0;font-weight:800;">Hola, <?= htmlspecialchars(explode(' ', $_SESSION['nombre_completo'] ?? 'colaborador')[0]) ?> 👋</h3>
            <p id="panelHint" style="color:var(--text-muted);font-size:.85rem;margin:0 0 14px 0;">
                <?php if ($tiene_rostro): ?>
                    Ubícate frente a la cámara. Tu rostro se verifica automáticamente.
                <?php else: ?>
                    <strong style="color:var(--primary);">Primera vez:</strong> Vamos a enrolar tu rostro. Mira a la cámara, parpadea y sonríe 🙂
                <?php endif; ?>
            </p>

            <!-- FASE C: Banner modo compatibilidad (oculto por defecto) -->
            <div id="compatBanner" style="display:none;background:#fef3c7;border:1px solid #fde68a;color:#92400e;border-radius:10px;padding:10px 12px;font-size:.8rem;margin-bottom:14px;">
                <i class="fas fa-info-circle"></i> <b>Modo compatibilidad activo</b> (iOS/Safari o sin IA).
                Usa el botón <b>Captura manual</b>: tu marcación quedará <b>pendiente de verificación manual</b> con tu foto como evidencia.
                <?php if (!$tiene_rostro): ?>Cuando abras el sistema en Chrome/Edge se enrolará tu rostro automáticamente para futuras verificaciones.<?php endif; ?>
            </div>
            <?php if ($es_ios): ?>
            <div style="background:var(--bg-body,#f1f5f9);border-radius:10px;padding:8px 12px;font-size:.75rem;color:var(--text-muted);margin-bottom:14px;">
                <i class="fab fa-apple"></i> Detectado dispositivo Apple: si la IA no inicia en pocos segundos, el modo compatibilidad se activa solo.
            </div>
            <?php endif; ?>

            <form action="/horion-time/public/asistencia/procesar" method="POST" id="formMarcacion">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="foto_data" id="fotoData">
                <input type="hidden" name="face_descriptor" id="faceDescriptorInput">
                <input type="hidden" name="modo_face" id="modoFaceInput" value="ia">
                <input type="hidden" name="lat" id="latInput">
                <input type="hidden" name="lng" id="lngInput">
                <input type="hidden" name="distancia_m" id="distanciaInput">
                <input type="hidden" name="metodo_marcacion" value="facial">
                <input type="hidden" name="tipo_marcacion" id="tipoMarcacionInput" value="entrada">

                <label class="form-label">Sucursal</label>
                <select name="sede_id" id="sedeSelect" class="form-input" style="margin-bottom:12px;" required onchange="actualizarDistancia()">
                    <option value="">Seleccione sucursal…</option>
                    <?php
                    $grupos = [];
                    foreach (($sedes ?? []) as $s) { $grupos[(int)$s['empresa_id']][] = $s; }
                    foreach ($grupos as $eid => $lista):
                        $cab = $lista[0]['empresa_nombre'] ?? 'Empresa';
                    ?>
                    <optgroup label="🏢 <?= htmlspecialchars($cab) ?>">
                        <?php foreach ($lista as $s): ?>
                        <option value="<?= (int)$s['id'] ?>" 
                                data-lat="<?= htmlspecialchars($s['lat_ref'] ?? '') ?>" 
                                data-lng="<?= htmlspecialchars($s['lng_ref'] ?? '') ?>"
                                data-radio="<?= (int)($s['radio_m'] ?? 150) ?>">
                            <?= htmlspecialchars($s['nombre']) ?>
                        </option>
                        <?php endforeach; ?>
                    </optgroup>
                    <?php endforeach; ?>
                </select>

                <div id="gpsStatus" style="margin-bottom:14px;padding:8px 12px;background:var(--bg-body,#f8fafc);border-radius:8px;font-size:.82rem;color:var(--text-muted);text-align:center;">
                    📡 Obteniendo ubicación…
                </div>

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
.gps-ok{background:#dcfce7 !important;color:#166534 !important;}
.gps-warn{background:#fef3c7 !important;color:#92400e !important;}
.gps-error{background:#fee2e2 !important;color:#991b1b !important;}
@media (max-width:860px){
    .kiosk-grid{grid-template-columns:1fr;}
    .kiosk-top{flex-direction:column;align-items:flex-start;}
    .kiosk-turno{justify-content:flex-start;}
}
</style>

<script>
const TIENE_ROSTRO = <?= $tiene_rostro ? 'true' : 'false' ?>;
const SEDES = <?= $sedes_json ?>;
let stream = null, fotoCapturada = false, faceDescriptorActual = null;
let modelosCargados = false, deteccionActiva = false, modoCompat = false;

// =====================================================================
// RELOJ
// =====================================================================
function actualizarReloj(){
    document.getElementById('reloj').textContent = new Date().toLocaleTimeString('es-CO',{hour12:false,timeZone:'America/Bogota'});
}
setInterval(actualizarReloj,1000); actualizarReloj();

function setStatus(txt, tipo){
    const el = document.getElementById('faceStatus');
    el.textContent = txt;
    el.style.background = tipo==='ok' ? 'rgba(22,101,52,.92)' : (tipo==='warn' ? 'rgba(146,64,14,.92)' : 'rgba(0,0,0,.72)');
}

// =====================================================================
// FASE C: MODO COMPATIBILIDAD
// =====================================================================
function activarCompat(motivo){
    if (modoCompat) return;
    modoCompat = true;
    deteccionActiva = false;
    document.getElementById('modoFaceInput').value = 'compat';
    document.getElementById('compatBanner').style.display = 'block';
    document.getElementById('btnCapturar').disabled = false;
    setStatus('📷 Modo compatibilidad: usa Captura manual', 'warn');
    const hint = document.getElementById('panelHint');
    if (hint) hint.innerHTML = '<b>Modo compatibilidad (' + motivo + '):</b> captura tu foto con el botón y registra. RRHH validará con tu evidencia.';
}

// =====================================================================
// FACE-API.JS: cargar modelos CON TIMEOUT
// =====================================================================
async function cargarModelos() {
    setStatus('Cargando modelos IA…','');
    const timeout = new Promise((_, rej) => setTimeout(() => rej(new Error('timeout de carga')), 15000));
    try {
        if (typeof faceapi === 'undefined') throw new Error('librería no disponible');
        const MODEL_URL = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.12/model/';
        await Promise.race([
            Promise.all([
                faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
                faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
                faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL)
            ]),
            timeout
        ]);
        modelosCargados = true;
        setStatus('✓ Modelos cargados — iniciando cámara…','ok');
        iniciarCamara();
    } catch(e) {
        activarCompat('sin IA: ' + (e.message || 'error'));
        iniciarCamara();
    }
}

// =====================================================================
// CÁMARA
// =====================================================================
async function iniciarCamara(){
    setStatus(modoCompat ? '📷 Cámara lista (modo compatibilidad)' : 'Solicitando cámara…', modoCompat ? 'warn' : '');
    if (stream) stream.getTracks().forEach(t=>t.stop());
    try{
        stream = await navigator.mediaDevices.getUserMedia({video:{facingMode:'user',width:{ideal:1280}},audio:false});
        const v = document.getElementById('video');
        v.srcObject = stream;
        document.getElementById('btnCapturar').disabled = false;
        await new Promise(r => v.readyState>=2 ? r() : (v.onloadeddata=r));
        if (!modoCompat) {
            setStatus(TIENE_ROSTRO ? '✓ Verificando rostro…' : '✓ Capturando rostro para enrolar…','ok');
            iniciarDeteccion();
        }
    }catch(e){ setStatus('❌ Sin cámara: ' + e.message,'warn'); activarCompat('sin acceso a cámara'); }
}

function reiniciar(){
    fotoCapturada = false;
    document.getElementById('fotoPreview').style.display = 'none';
    document.getElementById('fotoData').value = '';
    document.getElementById('faceDescriptorInput').value = '';
    document.querySelector('.kiosk-submit').classList.remove('pulse');
    if (modoCompat) { iniciarCamara(); } else { cargarModelos(); }
}

// =====================================================================
// DETECCIÓN Y RECONOCIMIENTO FACIAL
// =====================================================================
async function iniciarDeteccion() {
    if (deteccionActiva || modoCompat) return;
    deteccionActiva = true;
    const v = document.getElementById('video');
    const overlay = document.getElementById('overlay');
    const ctx = overlay.getContext('2d');
    
    const loop = async () => {
        if (!deteccionActiva || !stream) return;
        try {
            overlay.width = v.videoWidth;
            overlay.height = v.videoHeight;
            ctx.clearRect(0, 0, overlay.width, overlay.height);
            
            const detection = await faceapi.detectSingleFace(v, new faceapi.TinyFaceDetectorOptions())
                .withFaceLandmarks()
                .withFaceDescriptor();
            
            if (detection) {
                const box = detection.detection.box;
                ctx.strokeStyle = '#03a950';
                ctx.lineWidth = 3;
                ctx.strokeRect(box.x, box.y, box.width, box.height);
                
                if (!fotoCapturada) {
                    faceDescriptorActual = detection.descriptor;
                    
                    if (TIENE_ROSTRO) {
                        verificarRostro(detection.descriptor);
                    } else {
                        setStatus('📸 Rostro detectado — mantén la posición…','warn');
                        setTimeout(() => {
                            if (!fotoCapturada && faceDescriptorActual) {
                                capturarFoto(true, faceDescriptorActual);
                            }
                        }, 2000);
                    }
                }
            } else {
                if (!fotoCapturada) {
                    setStatus(TIENE_ROSTRO ? 'Ubique su rostro dentro del óvalo' : 'Mira a la cámara y sonríe 🙂','');
                }
            }
        } catch(e) {
            activarCompat('error de IA en dispositivo');
        }
        
        if (deteccionActiva && !fotoCapturada) {
            setTimeout(loop, 500);
        }
    };
    loop();
}

async function verificarRostro(descriptorActual) {
    try {
        const response = await fetch('/horion-time/public/asistencia/obtenerDescriptor');
        const data = await response.json();
        if (!data.descriptor) {
            setStatus('⚠️ Sin rostro enrolado: capturando…','warn');
            setTimeout(() => capturarFoto(true, descriptorActual), 1500);
            return;
        }
        
        const descriptorGuardado = new Float32Array(JSON.parse(data.descriptor));
        const distancia = faceapi.euclideanDistance(descriptorActual, descriptorGuardado);
        
        if (distancia <= 0.55) {
            setStatus('✓ Rostro VERIFICADO — capturando…','ok');
            deteccionActiva = false;
            setTimeout(() => capturarFoto(true, descriptorActual), 800);
        } else {
            setStatus('⚠️ El rostro no coincide (' + distancia.toFixed(2) + ')','warn');
        }
    } catch(e) {
        activarCompat('sin conexión para verificar');
    }
}

// =====================================================================
// CAPTURA DE FOTO
// =====================================================================
function capturarFoto(auto, descriptor = null){
    const v = document.getElementById('video'), c = document.getElementById('canvas'), ctx = c.getContext('2d');
    c.width = v.videoWidth; c.height = v.videoHeight; ctx.drawImage(v,0,0);
    const data = c.toDataURL('image/jpeg',0.8);
    fotoCapturada = true;
    deteccionActiva = false;
    
    document.getElementById('fotoData').value = data;
    document.getElementById('previewImg').src = data;
    document.getElementById('fotoPreview').style.display = 'block';
    document.getElementById('modoFaceInput').value = descriptor ? 'ia' : 'compat';
    
    if (descriptor) {
        document.getElementById('faceDescriptorInput').value = JSON.stringify(Array.from(descriptor));
    }
    
    setStatus(auto ? (TIENE_ROSTRO ? '✓ Rostro verificado' : '✓ Rostro enrolado') : '✓ Foto capturada','ok');
    document.querySelector('.kiosk-submit').classList.add('pulse');
    if (window.showToast) showToast(auto ? 'Rostro capturado' : 'Foto capturada','success');
}

// =====================================================================
// TIPO DE MARCACIÓN
// =====================================================================
function setTipo(btn){
    document.querySelectorAll('.ktipo').forEach(b=>b.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('tipoMarcacionInput').value = btn.dataset.tipo;
}

// =====================================================================
// GEOLOCALIZACIÓN Y DISTANCIA
// =====================================================================
let latActual = null, lngActual = null;

function obtenerUbicacion(){
    const el = document.getElementById('gpsStatus');
    el.className = '';
    el.textContent = '📡 Obteniendo ubicación…';
    
    if(!navigator.geolocation){ 
        el.textContent='📡 GPS no soportado (se registrará sin ubicación)';
        el.className = 'gps-warn';
        return; 
    }
    
    navigator.geolocation.getCurrentPosition(p=>{
        latActual = p.coords.latitude;
        lngActual = p.coords.longitude;
        document.getElementById('latInput').value = latActual;
        document.getElementById('lngInput').value = lngActual;
        actualizarDistancia();
    }, e => {
        el.textContent='📡 Sin GPS: se registrará sin ubicación';
        el.className = 'gps-error';
    },{enableHighAccuracy:true,timeout:15000,maximumAge:0});
}

function actualizarDistancia() {
    const el = document.getElementById('gpsStatus');
    const sedeSelect = document.getElementById('sedeSelect');
    const sedeId = parseInt(sedeSelect.value);
    
    if (!sedeId || !latActual || !lngActual) {
        if (latActual && lngActual) {
            el.textContent = '📡 Ubicación obtenida — selecciona sucursal';
            el.className = 'gps-ok';
        }
        return;
    }
    
    const sede = SEDES.find(s => s.id === sedeId);
    if (!sede || !sede.lat_ref || !sede.lng_ref) {
        el.textContent = '📡 Ubicación OK — sucursal sin georeferencia (marca normal)';
        el.className = 'gps-ok';
        document.getElementById('distanciaInput').value = '';
        return;
    }
    
    const distancia = calcularHaversine(latActual, lngActual, sede.lat_ref, sede.lng_ref);
    document.getElementById('distanciaInput').value = Math.round(distancia);
    
    if (distancia <= sede.radio_m) {
        el.textContent = `✅ A ${Math.round(distancia)} m de ${sede.nombre} (radio: ${sede.radio_m} m)`;
        el.className = 'gps-ok';
    } else {
        el.textContent = `🚫 A ${Math.round(distancia)} m de ${sede.nombre} (fuera del radio de ${sede.radio_m} m)`;
        el.className = 'gps-error';
    }
}

function calcularHaversine(lat1, lon1, lat2, lon2) {
    const R = 6371000;
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;
    const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
              Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
              Math.sin(dLon/2) * Math.sin(dLon/2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    return R * c;
}

// =====================================================================
// VALIDACIÓN FINAL
// =====================================================================
function prepararMarcacion(){
    if(!fotoCapturada){ 
        alert('⚠️ Espera la captura del rostro o usa "Captura manual".'); 
        return false; 
    }
    if(!document.getElementById('sedeSelect').value){ 
        alert('⚠️ Selecciona tu sucursal.'); 
        return false; 
    }
    
    const sedeId = parseInt(document.getElementById('sedeSelect').value);
    const sede = SEDES.find(s => s.id === sedeId);
    const distancia = parseFloat(document.getElementById('distanciaInput').value);
    
    if (sede && sede.lat_ref && sede.lng_ref && distancia > sede.radio_m) {
        if (!confirm(`⚠️ Estás a ${Math.round(distancia)} m de la sede (radio: ${sede.radio_m} m).\n\n¿Registrar de todos modos? La marcación quedará PENDIENTE para revisión.`)) {
            return false;
        }
    }
    
    return true;
}

// =====================================================================
// INICIALIZACIÓN
// =====================================================================
window.addEventListener('load', ()=>{ 
    obtenerUbicacion(); 
    cargarModelos();
});
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
