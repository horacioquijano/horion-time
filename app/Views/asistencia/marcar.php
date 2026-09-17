<?php
include __DIR__ . '/../layouts/header.php';
$csrf_token = $csrf_token ?? (class_exists('App\Helpers\Security') ? \App\Helpers\Security::generateCsrfToken() : bin2hex(random_bytes(32)));
$marcaciones_hoy = $marcaciones_hoy ?? [];
$sedes = $sedes ?? [];
?>

<div class="flex-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
    <h2 style="font-weight:700;color:var(--text-dark);margin:0;font-size:clamp(1.2rem, 3vw, 1.6rem);">
        <i class="fas fa-fingerprint" style="color:var(--primary);"></i> Registro de Asistencia
    </h2>
    <span id="reloj" style="font-size:clamp(1rem, 3vw, 1.5rem);font-weight:700;color:var(--primary);font-family:monospace;background:var(--primary-light);padding:6px 16px;border-radius:12px;box-shadow:0 4px 12px var(--primary-glow);">00:00:00</span>
</div>

<?php if (isset($_GET['success'])): ?>
<div class="card-3d" style="background:#dcfce7;border-left:4px solid var(--success);margin-bottom:24px;color:#166534;">
    <i class="fas fa-check-circle"></i> ¡Marcación registrada exitosamente!
</div>
<?php endif; ?>
<?php if (isset($_GET['error'])): ?>
<div class="card-3d" style="background:#fef2f2;border-left:4px solid #dc2626;margin-bottom:24px;color:#991b1b;">
    <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($_GET['error']) ?>
</div>
<?php endif; ?>

<div class="grid-2col" style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:32px;">
    <!-- Cámara / Captura Biométrica -->
    <div class="card-3d">
        <h3 style="font-weight:700;margin-bottom:16px;display:flex;align-items:center;gap:10px;font-size:clamp(1rem, 2.5vw, 1.2rem);">
            <i class="fas fa-camera" style="color:var(--primary);"></i> Captura Biométrica
        </h3>
        <div style="position:relative;background:#000;border-radius:12px;overflow:hidden;aspect-ratio:4/3;">
            <video id="video" autoplay playsinline muted style="width:100%;height:100%;object-fit:cover;"></video>
            <canvas id="canvas" style="display:none;"></canvas>
            <canvas id="faceOverlay" style="position:absolute;inset:0;width:100%;height:100%;pointer-events:none;"></canvas>
            <div id="faceStatus" style="position:absolute;bottom:10px;left:50%;transform:translateX(-50%);background:rgba(0,0,0,.7);color:#fff;padding:6px 16px;border-radius:20px;font-size:.85rem;font-weight:600;backdrop-filter:blur(8px);white-space:nowrap;">
                Iniciando cámara...
            </div>
        </div>
        <div style="display:flex;gap:12px;margin-top:16px;flex-wrap:wrap;">
            <button type="button" class="btn btn-primary" onclick="iniciarCamara()" id="btnIniciar" style="flex:1;min-width:120px;">
                <i class="fas fa-video"></i> Reiniciar Cámara
            </button>
            <button type="button" class="btn btn-primary" onclick="capturarFoto()" id="btnCapturar" disabled style="flex:1;min-width:120px;">
                <i class="fas fa-camera-retro"></i> Capturar Manual
            </button>
        </div>
        <div id="fotoPreview" style="margin-top:16px;display:none;">
            <img id="previewImg" style="width:100%;border-radius:12px;border:2px solid var(--primary);" />
        </div>
    </div>

    <!-- Ubicación y Método -->
    <div class="card-3d">
        <h3 style="font-weight:700;margin-bottom:16px;display:flex;align-items:center;gap:10px;font-size:clamp(1rem, 2.5vw, 1.2rem);">
            <i class="fas fa-map-marker-alt" style="color:var(--primary);"></i> Ubicación y Método
        </h3>
        <div style="background:var(--bg-body);padding:16px;border-radius:12px;margin-bottom:16px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;flex-wrap:wrap;gap:6px;">
                <span style="font-weight:600;color:var(--text-muted);">Estado GPS</span>
                <span id="gpsStatus" class="badge" style="background:#fef3c7;color:#92400e;">Buscando...</span>
            </div>
            <div style="font-size:.85rem;color:var(--text-muted);">
                <div>Lat: <span id="latDisplay">--</span></div>
                <div>Lng: <span id="lngDisplay">--</span></div>
                <div>Precisión: <span id="accuracyDisplay">--</span> metros</div>
            </div>
            <button type="button" class="btn" style="width:100%;margin-top:12px;background:var(--bg-body);border:1px solid var(--border);" onclick="obtenerUbicacion()">
                <i class="fas fa-location-arrow"></i> Actualizar Ubicación
            </button>
        </div>
        <div style="margin-bottom:16px;">
            <label style="display:block;font-size:.85rem;font-weight:600;margin-bottom:8px;color:var(--text-muted);">Método de Marcación</label>
            <div style="display:grid;grid-template-columns:repeat(2, 1fr);gap:8px;">
                <button type="button" class="btn metodo-btn" data-metodo="foto" onclick="seleccionarMetodo(this)"><i class="fas fa-camera"></i> Foto</button>
                <button type="button" class="btn metodo-btn active" data-metodo="facial" onclick="seleccionarMetodo(this)"><i class="fas fa-user-check"></i> Facial</button>
                <button type="button" class="btn metodo-btn" data-metodo="qr" onclick="seleccionarMetodo(this)"><i class="fas fa-qrcode"></i> QR</button>
                <button type="button" class="btn metodo-btn" data-metodo="pin" onclick="seleccionarMetodo(this)"><i class="fas fa-key"></i> PIN</button>
            </div>
        </div>
        <div style="margin-bottom:16px;">
            <label style="display:block;font-size:.85rem;font-weight:600;margin-bottom:6px;color:var(--text-muted);">Sucursal</label>
            <select name="sede_id" form="formMarcacion" class="form-input" required style="width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 10px;">
                <option value="">Seleccione sucursal...</option>
                <?php
                $grupos = [];
                foreach (($sedes ?? []) as $s) {
                    $grupos[(int)$s['empresa_id']][] = $s;
                }
                foreach ($grupos as $eid => $lista):
                    $cabecera = $lista[0]['empresa_nombre'] ?? 'Empresa';
                ?>
                <optgroup label="🏢 <?= htmlspecialchars($cabecera) ?>">
                    <?php foreach ($lista as $s): ?>
                    <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['nombre']) ?></option>
                    <?php endforeach; ?>
                </optgroup>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="display:block;font-size:.85rem;font-weight:600;margin-bottom:8px;color:var(--text-muted);">Tipo de Marcación</label>
            <select id="tipoMarcacion" class="form-input">
                <option value="entrada">🟢 Entrada</option>
                <option value="salida_almuerzo">🍽️ Salida Almuerzo</option>
                <option value="regreso_almuerzo">🍽️ Regreso Almuerzo</option>
                <option value="salida">🔴 Salida</option>
            </select>
        </div>
    </div>
</div>

<!-- Formulario -->
<div class="card-3d">
    <form action="/horion-time/public/asistencia/procesar" method="POST" id="formMarcacion">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" name="foto_data" id="fotoData">
        <input type="hidden" name="lat" id="latInput">
        <input type="hidden" name="lng" id="lngInput">
        <input type="hidden" name="metodo_marcacion" id="metodoInput" value="facial">
        <input type="hidden" name="tipo_marcacion" id="tipoMarcacionInput">
        <input type="hidden" name="face_detected" id="faceDetectedInput" value="0">
        <div class="grid-3col" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:16px;">
            <div>
                <label style="display:block;font-size:.85rem;font-weight:600;margin-bottom:6px;color:var(--text-muted);">PIN (si aplica)</label>
                <input type="password" name="pin" placeholder="****" class="form-input">
            </div>
            <div style="grid-column: span 2;">
                <label style="display:block;font-size:.85rem;font-weight:600;margin-bottom:6px;color:var(--text-muted);">Observaciones</label>
                <input type="text" name="observaciones" placeholder="Opcional" class="form-input">
            </div>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;padding:16px;font-size:1.1rem;" onclick="return prepararMarcacion()">
            <i class="fas fa-check-circle"></i> REGISTRAR MARCACIÓN
        </button>
    </form>
</div>

<!-- Marcaciones de hoy -->
<div class="card-3d" style="margin-top:24px;">
    <h3 style="font-weight:700;margin-bottom:16px;">
        <i class="fas fa-history" style="color:var(--primary);"></i> Mis Marcaciones de Hoy
    </h3>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr><th>Hora</th><th>Tipo</th><th>Método</th><th>Sucursal</th><th>Estado</th></tr>
            </thead>
            <tbody>
                <?php if (empty($marcaciones_hoy)): ?>
                <tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:32px;">No hay marcaciones registradas hoy</td></tr>
                <?php else: ?>
                <?php foreach ($marcaciones_hoy as $m): ?>
                <tr>
                    <td style="font-family:monospace;font-weight:600;"><?= htmlspecialchars($m['hora_registro']) ?></td>
                    <td><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $m['tipo_marcacion']))) ?></td>
                    <td><span class="badge" style="background:#e0e7ff;color:#3730a3;"><i class="fas fa-<?= $m['metodo_marcacion'] === 'foto' ? 'camera' : ($m['metodo_marcacion'] === 'qr' ? 'qrcode' : 'fingerprint') ?>"></i> <?= htmlspecialchars(ucfirst($m['metodo_marcacion'])) ?></span></td>
                    <td><?= htmlspecialchars($m['sede_nombre'] ?? 'N/A') ?></td>
                    <td><span class="badge badge-<?= $m['estado'] === 'validado' ? 'success' : ($m['estado'] === 'pendiente' ? 'warning' : 'danger') ?>"><?= htmlspecialchars(ucfirst($m['estado'])) ?></span></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
@keyframes pulse{0%,100%{transform:translate(-50%,-50%) scale(1);opacity:1}50%{transform:translate(-50%,-50%) scale(1.1);opacity:.5}}
.metodo-btn{background:var(--bg-body);color:var(--text-dark);padding:12px;border:2px solid transparent;transition:all .2s;font-size:.9rem}
.metodo-btn.active{background:rgba(3,169,80,.1);border-color:var(--primary);color:var(--primary)}
.metodo-btn:hover{transform:translateY(-2px)}

/* Tablet */
@media (max-width: 1024px) {
    .grid-3col { grid-template-columns: 1fr 1fr !important; }
}

/* Celular */
@media (max-width: 768px) {
    .grid-2col { grid-template-columns: 1fr !important; }
    .grid-3col { grid-template-columns: 1fr !important; }
    .grid-3col > div[style*="grid-column: span 2"] { grid-column: span 1 !important; }
    .flex-header { flex-direction: column; align-items: stretch !important; text-align: center; }
    .card-3d { padding: 16px !important; }
    .data-table { font-size: .85rem; }
    .data-table th, .data-table td { padding: 8px 6px !important; }
    .btn { padding: 10px 14px !important; font-size: .9rem !important; }
    .metodo-btn { padding: 10px 8px; font-size: .8rem; }
}

/* Celulares pequeños */
@media (max-width: 480px) {
    .data-table { display: block; overflow-x: auto; }
    .card-3d h3 { font-size: 1rem; }
    .flex-header h2 { font-size: 1.2rem; }
}
</style>

<script>
let stream = null, fotoCapturada = false, metodoSeleccionado = 'facial';
let faceDetector = null, faceDetectionInterval = null;
let caraEstableContador = 0;
const CARA_ESTABLE_UMBRAL = 4; // 4 detecciones × 500ms = ~2 seg

function actualizarReloj(){ 
    document.getElementById('reloj').textContent = new Date().toLocaleTimeString('es-CO',{hour12:false,timeZone:'America/Bogota'}); 
}
setInterval(actualizarReloj, 1000); 
actualizarReloj();

// Inicializar FaceDetector (API nativa del navegador)
function initFaceDetector() {
    if ('FaceDetector' in window) {
        try {
            faceDetector = new FaceDetector({ fastMode: true, maxDetectedFaces: 1 });
            return true;
        } catch(e) { console.warn('FaceDetector falló:', e); }
    }
    return false;
}

async function iniciarCamara(){
    const statusEl = document.getElementById('faceStatus');
    statusEl.textContent = 'Solicitando cámara...';
    statusEl.style.background = 'rgba(254, 243, 199, 0.9)';
    statusEl.style.color = '#92400e';
    
    if (stream) stream.getTracks().forEach(t => t.stop());
    
    try{
        stream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 720 } },
            audio: false
        });
        const video = document.getElementById('video');
        video.srcObject = stream;
        document.getElementById('btnCapturar').disabled = false;
        
        await new Promise(r => video.readyState >= 2 ? r() : (video.onloadeddata = r));
        
        statusEl.textContent = '✓ Cámara activa';
        statusEl.style.background = 'rgba(220, 252, 231, 0.9)';
        statusEl.style.color = '#166534';
        
        if (metodoSeleccionado === 'facial') iniciarDeteccionFacial();
    } catch(err) { 
        statusEl.textContent = '❌ ' + err.message;
        statusEl.style.background = 'rgba(254, 226, 226, 0.9)';
        statusEl.style.color = '#991b1b';
    }
}

function iniciarDeteccionFacial() {
    if (!initFaceDetector()) {
        document.getElementById('faceStatus').textContent = '📷 Captura manual (sin detección facial)';
        return;
    }
    caraEstableContador = 0;
    if (faceDetectionInterval) clearInterval(faceDetectionInterval);
    faceDetectionInterval = setInterval(detectarCara, 500);
    document.getElementById('faceStatus').textContent = '🔍 Detectando rostro...';
}

async function detectarCara() {
    if (!faceDetector || !stream) return;
    const video = document.getElementById('video');
    const overlay = document.getElementById('faceOverlay');
    const ctx = overlay.getContext('2d');
    const statusEl = document.getElementById('faceStatus');
    
    overlay.width = video.videoWidth;
    overlay.height = video.videoHeight;
    ctx.clearRect(0, 0, overlay.width, overlay.height);
    
    try {
        const faces = await faceDetector.detect(video);
        if (faces.length > 0) {
            const box = faces[0].boundingBox;
            ctx.strokeStyle = '#03a950';
            ctx.lineWidth = 4;
            ctx.strokeRect(box.x, box.y, box.width, box.height);
            
            // Esquinas decorativas
            const c = 20;
            ctx.beginPath();
            ctx.moveTo(box.x, box.y + c); ctx.lineTo(box.x, box.y); ctx.lineTo(box.x + c, box.y);
            ctx.moveTo(box.x + box.width - c, box.y); ctx.lineTo(box.x + box.width, box.y); ctx.lineTo(box.x + box.width, box.y + c);
            ctx.moveTo(box.x, box.y + box.height - c); ctx.lineTo(box.x, box.y + box.height); ctx.lineTo(box.x + c, box.y + box.height);
            ctx.moveTo(box.x + box.width - c, box.y + box.height); ctx.lineTo(box.x + box.width, box.y + box.height); ctx.lineTo(box.x + box.width, box.y + box.height - c);
            ctx.stroke();
            
            caraEstableContador++;
            const restantes = Math.max(0, CARA_ESTABLE_UMBRAL - caraEstableContador);
            if (caraEstableContador >= CARA_ESTABLE_UMBRAL && !fotoCapturada) {
                statusEl.textContent = '✓ Rostro sincronizado - Capturando...';
                statusEl.style.background = 'rgba(220, 252, 231, 0.95)';
                statusEl.style.color = '#166534';
                clearInterval(faceDetectionInterval);
                setTimeout(() => capturarFoto(true), 300);
            } else if (!fotoCapturada) {
                statusEl.textContent = `🎯 Rostro detectado - Sincronizando (${restantes})...`;
                statusEl.style.background = 'rgba(254, 243, 199, 0.9)';
                statusEl.style.color = '#92400e';
            }
        } else {
            caraEstableContador = 0;
            if (!fotoCapturada) {
                statusEl.textContent = '🔍 Posicione su rostro frente a la cámara';
                statusEl.style.background = 'rgba(0, 0, 0, 0.7)';
                statusEl.style.color = '#fff';
            }
        }
    } catch(e) { /* seguir intentando */ }
}

function capturarFoto(auto = false){
    const video = document.getElementById('video');
    const canvas = document.getElementById('canvas');
    const ctx = canvas.getContext('2d');
    canvas.width = video.videoWidth; 
    canvas.height = video.videoHeight; 
    ctx.drawImage(video, 0, 0);
    const dataURL = canvas.toDataURL('image/jpeg', 0.8); 
    fotoCapturada = true;
    document.getElementById('previewImg').src = dataURL;
    document.getElementById('fotoPreview').style.display = 'block';
    document.getElementById('fotoData').value = dataURL;
    document.getElementById('faceDetectedInput').value = caraEstableContador >= CARA_ESTABLE_UMBRAL ? '1' : '0';
    
    if (faceDetectionInterval) { clearInterval(faceDetectionInterval); faceDetectionInterval = null; }
    
    const statusEl = document.getElementById('faceStatus');
    statusEl.textContent = auto ? '✓ Rostro sincronizado y capturado' : '✓ Foto capturada';
    statusEl.style.background = 'rgba(220, 252, 231, 0.95)';
    statusEl.style.color = '#166534';
    
    if (window.showToast) showToast(auto ? '✓ Rostro reconocido' : 'Foto capturada', 'success');
}

function obtenerUbicacion(){
    const st = document.getElementById('gpsStatus'); 
    st.textContent = 'Buscando...'; 
    st.style.background = '#fef3c7'; 
    st.style.color = '#92400e';
    if(!navigator.geolocation){ 
        st.textContent = 'No soportado'; 
        st.style.background = '#fee2e2'; 
        st.style.color = '#991b1b'; 
        return; 
    }
    navigator.geolocation.getCurrentPosition(p => {
        document.getElementById('latDisplay').textContent = p.coords.latitude.toFixed(6);
        document.getElementById('lngDisplay').textContent = p.coords.longitude.toFixed(6);
        document.getElementById('accuracyDisplay').textContent = p.coords.accuracy.toFixed(0);
        document.getElementById('latInput').value = p.coords.latitude;
        document.getElementById('lngInput').value = p.coords.longitude;
        st.textContent = '✓ Preciso'; 
        st.style.background = '#dcfce7'; 
        st.style.color = '#166534';
    }, e => { 
        st.textContent = 'Error: ' + e.message; 
        st.style.background = '#fee2e2'; 
        st.style.color = '#991b1b'; 
    }, {enableHighAccuracy:true, timeout:10000, maximumAge:0});
}

function seleccionarMetodo(btn){
    document.querySelectorAll('.metodo-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active'); 
    metodoSeleccionado = btn.dataset.metodo;
    document.getElementById('metodoInput').value = metodoSeleccionado;
    
    if (metodoSeleccionado === 'facial' && stream) {
        iniciarDeteccionFacial();
    } else if (faceDetectionInterval) {
        clearInterval(faceDetectionInterval);
        faceDetectionInterval = null;
        const ctx = document.getElementById('faceOverlay').getContext('2d');
        ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
    }
}

function prepararMarcacion(){
    document.getElementById('tipoMarcacionInput').value = document.getElementById('tipoMarcacion').value;
    if(!fotoCapturada && (metodoSeleccionado === 'foto' || metodoSeleccionado === 'facial')){ 
        alert('⚠️ Debe capturar una foto antes de registrar la marcación'); 
        return false; 
    }
    const sede = document.querySelector('select[name="sede_id"]').value;
    if(!sede){ 
        alert('⚠️ Debe seleccionar una sucursal'); 
        return false; 
    }
    if(!document.getElementById('latInput').value){ 
        if(!confirm('⚠️ No se ha obtenido la ubicación GPS. ¿Continuar sin geolocalización?')) 
            return false; 
    }
    return true;
}

// AUTO-INICIO: cámara + GPS al cargar la página
window.addEventListener('load', () => {
    obtenerUbicacion();
    setTimeout(iniciarCamara, 500);
});
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
