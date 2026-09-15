<?php
include __DIR__ . '/../layouts/header.php';
$csrf_token = $csrf_token ?? (class_exists('App\Helpers\Security') ? \App\Helpers\Security::generateCsrfToken() : bin2hex(random_bytes(32)));
$marcaciones_hoy = $marcaciones_hoy ?? [];
$sedes = $sedes ?? [];
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
    <h2 style="font-weight:700;color:var(--text-dark);margin:0;">
        <i class="fas fa-fingerprint" style="color:var(--primary);"></i> Registro de Asistencia
    </h2>
    <span id="reloj" style="font-size:1.5rem;font-weight:700;color:var(--primary);font-family:monospace;background:var(--primary-light);padding:6px 16px;border-radius:12px;box-shadow:0 4px 12px var(--primary-glow);">00:00:00</span>
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

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:32px;">
    <!-- Cámara / Captura Biométrica -->
    <div class="card-3d">
        <h3 style="font-weight:700;margin-bottom:16px;display:flex;align-items:center;gap:10px;">
            <i class="fas fa-camera" style="color:var(--primary);"></i> Captura Biométrica
        </h3>
        <div style="position:relative;background:#000;border-radius:12px;overflow:hidden;aspect-ratio:4/3;">
            <video id="video" autoplay playsinline style="width:100%;height:100%;object-fit:cover;"></video>
            <canvas id="canvas" style="display:none;"></canvas>
            <div id="scanOverlay" style="position:absolute;inset:0;border:3px solid var(--primary);border-radius:12px;opacity:0;transition:opacity .3s;">
                <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:60%;height:60%;border:2px solid var(--primary);border-radius:50%;animation:pulse 2s infinite;"></div>
            </div>
        </div>
        <div style="display:flex;gap:12px;margin-top:16px;">
            <button type="button" class="btn btn-primary" onclick="iniciarCamara()" style="flex:1;">
                <i class="fas fa-video"></i> Iniciar Cámara
            </button>
            <button type="button" class="btn btn-primary" onclick="capturarFoto()" id="btnCapturar" disabled style="flex:1;">
                <i class="fas fa-camera-retro"></i> Capturar
            </button>
        </div>
        <div id="fotoPreview" style="margin-top:16px;display:none;">
            <img id="previewImg" style="width:100%;border-radius:12px;border:2px solid var(--primary);" />
        </div>
    </div>

    <!-- Ubicación y Método -->
    <div class="card-3d">
        <h3 style="font-weight:700;margin-bottom:16px;display:flex;align-items:center;gap:10px;">
            <i class="fas fa-map-marker-alt" style="color:var(--primary);"></i> Ubicación y Método
        </h3>
        <div style="background:var(--bg-body);padding:16px;border-radius:12px;margin-bottom:16px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
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
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                <button type="button" class="btn metodo-btn active" data-metodo="foto" onclick="seleccionarMetodo(this)"><i class="fas fa-camera"></i> Foto</button>
                <button type="button" class="btn metodo-btn" data-metodo="facial" onclick="seleccionarMetodo(this)"><i class="fas fa-user-check"></i> Facial</button>
                <button type="button" class="btn metodo-btn" data-metodo="qr" onclick="seleccionarMetodo(this)"><i class="fas fa-qrcode"></i> QR</button>
                <button type="button" class="btn metodo-btn" data-metodo="pin" onclick="seleccionarMetodo(this)"><i class="fas fa-key"></i> PIN</button>
            </div>
        </div>
        <div>
    <label style="display:block;font-size:0.85rem;font-weight:600;margin-bottom:6px;color:var(--text-muted);">Sucursal</label>
    <select name="sede_id" style="width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 10px;">
    <option value="">Seleccione sucursal...</option>
    <?php
    // Agrupar: cada empresa es la CABEZA (raíz) de sus propias sucursales
    $grupos = [];
    foreach (($sedes ?? []) as $s) {
        $grupos[(int)$s['empresa_id']][] = $s;
    }
    foreach ($grupos as $eid => $lista):
        $cabecera = $lista[0]['empresa_nombre'] ?? 'Empresa';
    ?>
    <optgroup label="🏢 <?= htmlspecialchars($cabecera) ?> (Raíz)">
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
        <input type="hidden" name="metodo_marcacion" id="metodoInput" value="foto">
        <input type="hidden" name="tipo_marcacion" id="tipoMarcacionInput">
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:16px;">
            <div>
                <label style="display:block;font-size:.85rem;font-weight:600;margin-bottom:6px;color:var(--text-muted);">Sede</label>
                <select name="sede_id" class="form-input">
                    <option value="">Seleccione sede...</option>
                    <?php foreach ($sedes as $s): ?>
                    <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="display:block;font-size:.85rem;font-weight:600;margin-bottom:6px;color:var(--text-muted);">PIN (si aplica)</label>
                <input type="password" name="pin" placeholder="****" class="form-input">
            </div>
            <div>
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
                <tr><th>Hora</th><th>Tipo</th><th>Método</th><th>Sede</th><th>Estado</th></tr>
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
.metodo-btn{background:var(--bg-body);color:var(--text-dark);padding:12px;border:2px solid transparent;transition:all .2s}
.metodo-btn.active{background:rgba(3,169,80,.1);border-color:var(--primary);color:var(--primary)}
.metodo-btn:hover{transform:translateY(-2px)}
</style>
<script>
let stream = null, fotoCapturada = null, metodoSeleccionado = 'foto';
function actualizarReloj(){ document.getElementById('reloj').textContent = new Date().toLocaleTimeString('es-CO',{hour12:false,timeZone:'America/Bogota'}); }
setInterval(actualizarReloj,1000); actualizarReloj();
async function iniciarCamara(){
    try{
        stream = await navigator.mediaDevices.getUserMedia({video:{facingMode:'user',width:640,height:480}});
        document.getElementById('video').srcObject = stream;
        document.getElementById('btnCapturar').disabled = false;
    }catch(err){ alert('Error al acceder a la cámara: '+err.message); }
}
function capturarFoto(){
    const video=document.getElementById('video'),canvas=document.getElementById('canvas'),ctx=canvas.getContext('2d');
    canvas.width=video.videoWidth; canvas.height=video.videoHeight; ctx.drawImage(video,0,0);
    const dataURL=canvas.toDataURL('image/jpeg',0.8); fotoCapturada=dataURL;
    document.getElementById('previewImg').src=dataURL;
    document.getElementById('fotoPreview').style.display='block';
    document.getElementById('fotoData').value=dataURL;
    const ov=document.getElementById('scanOverlay'); ov.style.opacity='1'; setTimeout(()=>ov.style.opacity='0',1500);
    if (window.showToast) showToast('Foto capturada correctamente','success');
}
function obtenerUbicacion(){
    const st=document.getElementById('gpsStatus'); st.textContent='Buscando...'; st.style.background='#fef3c7'; st.style.color='#92400e';
    if(!navigator.geolocation){ st.textContent='No soportado'; st.style.background='#fee2e2'; st.style.color='#991b1b'; return; }
    navigator.geolocation.getCurrentPosition(p=>{
        document.getElementById('latDisplay').textContent=p.coords.latitude.toFixed(6);
        document.getElementById('lngDisplay').textContent=p.coords.longitude.toFixed(6);
        document.getElementById('accuracyDisplay').textContent=p.coords.accuracy.toFixed(0);
        document.getElementById('latInput').value=p.coords.latitude;
        document.getElementById('lngInput').value=p.coords.longitude;
        st.textContent='✓ Preciso'; st.style.background='#dcfce7'; st.style.color='#166534';
    },e=>{ st.textContent='Error: '+e.message; st.style.background='#fee2e2'; st.style.color='#991b1b'; },{enableHighAccuracy:true,timeout:10000,maximumAge:0});
}
function seleccionarMetodo(btn){
    document.querySelectorAll('.metodo-btn').forEach(b=>b.classList.remove('active'));
    btn.classList.add('active'); metodoSeleccionado=btn.dataset.metodo;
    document.getElementById('metodoInput').value=metodoSeleccionado;
}
function prepararMarcacion(){
    document.getElementById('tipoMarcacionInput').value=document.getElementById('tipoMarcacion').value;
    if(!fotoCapturada && (metodoSeleccionado==='foto'||metodoSeleccionado==='facial')){ alert('⚠️ Debe capturar una foto antes de registrar la marcación'); return false; }
    if(!document.getElementById('latInput').value){ if(!confirm('⚠️ No se ha obtenido la ubicación GPS. ¿Continuar sin geolocalización?')) return false; }
    return true;
}
window.addEventListener('load', obtenerUbicacion);
</script>
<?php include __DIR__ . '/../layouts/footer.php'; ?>