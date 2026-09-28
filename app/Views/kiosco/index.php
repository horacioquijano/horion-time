<?php
// app/Views/kiosco/index.php — Kiosco HORION TIME (estilo oficial del sistema)
$dispositivo = $dispositivo ?? null;
$token_inicial = $dispositivo ? ($dispositivo['token_kiosco'] ?? '') : '';
$inactividad = $dispositivo ? (int)($dispositivo['inactividad_seg'] ?? 30) : 30;
$nombre_sede = $dispositivo ? ($dispositivo['sede_nombre'] ?? '') : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>HORION TIME | Kiosco de Marcación</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root{
  --primary:#03a950;
  --primary-dark:#02843c;
  --primary-light:#e6f9f0;
  --bg:#f1f5f9;
  --card:#ffffff;
  --text:#0f172a;
  --muted:#64748b;
  --border:#e2e8f0;
  --danger:#dc2626;
  --danger-light:#fef2f2;
  --warning:#f59e0b;
  --radius:20px;
  --shadow:0 10px 30px rgba(2,132,60,.08), 0 2px 8px rgba(15,23,42,.06);
}
*{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent;}
html,body{height:100%;}
body{
  background:var(--bg);
  background-image:radial-gradient(circle at 15% 20%, rgba(3,169,80,.06), transparent 45%),
                   radial-gradient(circle at 85% 80%, rgba(3,169,80,.05), transparent 45%);
  color:var(--text);
  font-family:'Inter',system-ui,-apple-system,sans-serif;
  overflow:hidden;
}

/* ===== Barra superior ===== */
.top-bar{
  position:fixed;top:0;left:0;right:0;z-index:10;
  display:flex;justify-content:space-between;align-items:center;gap:12px;
  padding:14px 24px;
  background:rgba(255,255,255,.92);backdrop-filter:blur(10px);
  border-bottom:1px solid var(--border);
}
.brand{display:flex;align-items:center;gap:12px;}
.brand-icon{width:42px;height:42px;border-radius:12px;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.2rem;box-shadow:0 4px 12px rgba(3,169,80,.35);}
.brand-text{font-weight:900;font-size:1.05rem;letter-spacing:.5px;color:var(--text);}
.brand-text span{color:var(--primary);}
.brand-sub{font-size:.72rem;color:var(--muted);font-weight:600;letter-spacing:1px;text-transform:uppercase;}
.clock-box{text-align:right;}
.clock{font-size:1.6rem;font-weight:800;color:var(--text);font-variant-numeric:tabular-nums;letter-spacing:1px;}
.clock-date{font-size:.75rem;color:var(--muted);font-weight:600;text-transform:capitalize;}

/* ===== Pantallas ===== */
.screen{
  position:absolute;inset:0;z-index:2;
  display:none;flex-direction:column;align-items:center;justify-content:center;
  padding:90px 16px 60px;overflow-y:auto;
  animation:fadeIn .35s ease;
}
.screen.active{display:flex;}
@keyframes fadeIn{from{opacity:0;transform:translateY(8px);}to{opacity:1;transform:translateY(0);}}

.screen-title{font-size:clamp(1.4rem,4vw,2rem);font-weight:900;color:var(--text);text-align:center;}
.screen-title i{color:var(--primary);margin-right:8px;}
.screen-sub{color:var(--muted);font-size:.92rem;margin:6px 0 22px;text-align:center;font-weight:500;}

/* ===== Tarjetas ===== */
.card{
  background:var(--card);border:1px solid var(--border);border-radius:var(--radius);
  box-shadow:var(--shadow);padding:28px;width:100%;max-width:460px;
}
.card.wide{max-width:880px;}

/* ===== Inputs y botones ===== */
.k-label{display:block;font-size:.8rem;font-weight:700;color:var(--muted);margin-bottom:8px;text-transform:uppercase;letter-spacing:.8px;}
.k-input{
  width:100%;padding:16px 18px;border:1.5px solid var(--border);border-radius:14px;
  font-family:'Inter',sans-serif;font-size:1.3rem;font-weight:800;text-align:center;letter-spacing:6px;
  color:var(--text);background:#fff;outline:none;transition:border .2s, box-shadow .2s;
}
.k-input:focus{border-color:var(--primary);box-shadow:0 0 0 4px rgba(3,169,80,.15);}
.k-input::placeholder{color:#cbd5e1;letter-spacing:4px;}
.btn-k{
  width:100%;margin-top:16px;padding:16px;border:none;border-radius:14px;cursor:pointer;
  background:var(--primary);color:#fff;font-family:'Inter',sans-serif;font-size:1rem;font-weight:800;letter-spacing:.5px;
  box-shadow:0 6px 16px rgba(3,169,80,.30);transition:all .2s;
  display:flex;align-items:center;justify-content:center;gap:10px;
}
.btn-k:hover,.btn-k:active{background:var(--primary-dark);transform:translateY(-1px);}
.btn-k:disabled{opacity:.6;transform:none;cursor:wait;}
.btn-ghost{
  width:100%;margin-top:12px;padding:13px;border-radius:14px;cursor:pointer;
  background:#fff;border:1.5px solid var(--border);color:var(--muted);
  font-family:'Inter',sans-serif;font-size:.88rem;font-weight:700;transition:all .2s;
  display:flex;align-items:center;justify-content:center;gap:8px;
}
.btn-ghost:hover{border-color:var(--primary);color:var(--primary);}

/* ===== Teclado numérico ===== */
.keypad{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:18px;}
.key{
  padding:18px 0;background:#fff;border:1.5px solid var(--border);border-radius:14px;
  font-family:'Inter',sans-serif;font-size:1.4rem;font-weight:800;color:var(--text);
  cursor:pointer;transition:all .12s;min-height:60px;
  display:flex;align-items:center;justify-content:center;
}
.key:active{background:var(--primary-light);border-color:var(--primary);color:var(--primary-dark);transform:scale(.96);}
.key.fn{background:var(--bg);color:var(--muted);font-size:1.1rem;}
.key.fn.ok{background:var(--primary-light);border-color:var(--primary);color:var(--primary-dark);}

/* ===== Cámara ===== */
.cam-wrap{
  position:relative;width:100%;max-width:340px;aspect-ratio:1/1;margin:0 auto;
  border-radius:24px;overflow:hidden;background:#0f172a;
  border:3px solid var(--primary);box-shadow:0 8px 24px rgba(3,169,80,.25);
}
.cam-wrap video{width:100%;height:100%;object-fit:cover;transform:scaleX(-1);}
.scan-line{
  position:absolute;left:0;right:0;height:3px;
  background:linear-gradient(90deg,transparent,var(--primary),transparent);
  box-shadow:0 0 14px var(--primary);animation:scan 2.2s linear infinite;
}
@keyframes scan{0%{top:0;}100%{top:100%;}}
.cam-tag{
  position:absolute;bottom:10px;left:50%;transform:translateX(-50%);
  background:rgba(15,23,42,.72);color:#fff;font-size:.72rem;font-weight:700;letter-spacing:1.5px;
  padding:6px 14px;border-radius:999px;backdrop-filter:blur(6px);white-space:nowrap;
}
.cam-tag i{color:#4ade80;margin-right:6px;font-size:.55rem;vertical-align:middle;}

/* ===== Identificación: grid ===== */
.ident-grid{display:grid;grid-template-columns:1fr 1fr;gap:28px;align-items:center;}
.sede-chip{
  display:inline-flex;align-items:center;gap:8px;background:var(--primary-light);
  color:var(--primary-dark);border-radius:999px;padding:7px 14px;
  font-size:.8rem;font-weight:700;margin-bottom:16px;
}

/* ===== Éxito ===== */
.success-ring{
  width:150px;height:150px;border-radius:50%;
  background:var(--primary-light);border:6px solid var(--primary);
  display:flex;align-items:center;justify-content:center;
  font-size:4rem;color:var(--primary);margin-bottom:24px;
  box-shadow:0 0 0 12px rgba(3,169,80,.12);animation:pulse 1.6s infinite;
}
@keyframes pulse{0%,100%{transform:scale(1);}50%{transform:scale(1.04);}}
.success-type{
  display:inline-block;background:var(--primary);color:#fff;border-radius:999px;
  padding:8px 22px;font-weight:800;letter-spacing:1px;margin-top:10px;
}

/* ===== Errores ===== */
.err{
  display:none;margin-top:14px;padding:12px 14px;border-radius:12px;
  background:var(--danger-light);border:1px solid #fecaca;color:var(--danger);
  font-size:.88rem;font-weight:700;text-align:center;
}
.err.show{display:block;animation:shake .35s;}
@keyframes shake{0%,100%{transform:translateX(0);}25%{transform:translateX(-8px);}75%{transform:translateX(8px);}}

/* ===== Pie ===== */
.status-bar{
  position:fixed;bottom:0;left:0;right:0;z-index:10;text-align:center;
  padding:10px;font-size:.72rem;color:var(--muted);font-weight:600;letter-spacing:1px;
  background:rgba(255,255,255,.9);border-top:1px solid var(--border);backdrop-filter:blur(8px);
}
.status-bar i{color:var(--primary);margin-right:6px;}

/* ===== RESPONSIVE ===== */
@media (max-width:860px){
  .ident-grid{grid-template-columns:1fr;gap:18px;}
  .cam-wrap{max-width:260px;}
  .card{padding:22px;}
  .card.wide{max-width:520px;}
  .top-bar{padding:10px 14px;}
  .clock{font-size:1.25rem;}
  .brand-sub{display:none;}
  .screen{padding:80px 12px 56px;}
}
@media (max-width:420px){
  .key{min-height:52px;font-size:1.2rem;padding:14px 0;}
  .k-input{font-size:1.1rem;letter-spacing:4px;}
  .cam-wrap{max-width:220px;}
  .success-ring{width:120px;height:120px;font-size:3rem;}
}
@media (orientation:landscape) and (max-height:520px){
  .screen{justify-content:flex-start;}
  .cam-wrap{max-width:180px;}
  .success-ring{width:90px;height:90px;font-size:2.2rem;margin-bottom:12px;}
}
</style>
</head>
<body>

<!-- ===== Barra superior ===== -->
<div class="top-bar">
  <div class="brand">
    <div class="brand-icon"><i class="fas fa-clock"></i></div>
    <div>
      <div class="brand-text">HORION <span>TIME</span></div>
      <div class="brand-sub">Kiosco de marcación</div>
    </div>
  </div>
  <div class="clock-box">
    <div class="clock" id="reloj">00:00:00</div>
    <div class="clock-date" id="fecha"></div>
  </div>
</div>

<!-- ===== PANTALLA 1: TOKEN ===== -->
<div class="screen active" id="screen-token">
  <h1 class="screen-title"><i class="fas fa-plug"></i> Conectar Terminal</h1>
  <p class="screen-sub">Ingrese el código del dispositivo asignado a este equipo</p>
  <div class="card">
    <label class="k-label">Código del dispositivo</label>
    <input type="text" id="inpToken" class="k-input" style="letter-spacing:2px;font-size:1rem;" placeholder="PEGUE O ESCRIBA EL TOKEN" value="<?= htmlspecialchars($token_inicial) ?>" autocomplete="off">
    <button class="btn-k" onclick="cargarDispositivo()"><i class="fas fa-arrow-right"></i> Continuar</button>
    <div class="err" id="errToken"></div>
  </div>
</div>

<!-- ===== PANTALLA 2: PIN DEL DISPOSITIVO ===== -->
<div class="screen" id="screen-pin">
  <h1 class="screen-title"><i class="fas fa-lock"></i> Desbloquear Kiosco</h1>
  <p class="screen-sub">PIN de 4 dígitos del dispositivo<br><small style="color:var(--muted);">(lo encuentra en Dispositivos → PIN Acceso)</small></p>
  <div class="card">
    <input type="password" id="inpPin" class="k-input" placeholder="• • • •" maxlength="4" readonly inputmode="numeric">
    <div class="keypad">
      <div class="key" onclick="tecla('1')">1</div><div class="key" onclick="tecla('2')">2</div><div class="key" onclick="tecla('3')">3</div>
      <div class="key" onclick="tecla('4')">4</div><div class="key" onclick="tecla('5')">5</div><div class="key" onclick="tecla('6')">6</div>
      <div class="key" onclick="tecla('7')">7</div><div class="key" onclick="tecla('8')">8</div><div class="key" onclick="tecla('9')">9</div>
      <div class="key fn" onclick="tecla('C')"><i class="fas fa-backspace"></i></div>
      <div class="key" onclick="tecla('0')">0</div>
      <div class="key fn ok" onclick="tecla('OK')"><i class="fas fa-check"></i></div>
    </div>
    <div class="err" id="errPin"></div>
    <button class="btn-ghost" onclick="volverToken()"><i class="fas fa-arrow-left"></i> Cambiar de terminal</button>
  </div>
</div>

<!-- ===== PANTALLA 3: IDENTIFICACIÓN ===== -->
<div class="screen" id="screen-identify">
  <h1 class="screen-title"><i class="fas fa-fingerprint"></i> Registro de Asistencia</h1>
  <p class="screen-sub">Digite su cédula y mire a la cámara</p>
  <div class="card wide">
    <?php if ($nombre_sede !== ''): ?>
    <div style="text-align:center;"><span class="sede-chip"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($nombre_sede) ?></span></div>
    <?php endif; ?>
    <div class="ident-grid">
      <div>
        <label class="k-label">Número de cédula</label>
        <input type="text" id="inpCedula" class="k-input" style="font-size:1.15rem;letter-spacing:3px;" placeholder="EJ: 1001914117" inputmode="numeric" autocomplete="off">
        <button class="btn-k" id="btnMarcar" onclick="iniciarMarcacion()"><i class="fas fa-fingerprint"></i> Registrar marcación</button>
        <div class="err" id="errIdent"></div>
        <button class="btn-ghost" onclick="volverPin()"><i class="fas fa-lock"></i> Bloquear kiosco</button>
      </div>
      <div>
        <div class="cam-wrap">
          <video id="video" autoplay playsinline muted></video>
          <div class="scan-line"></div>
          <div class="cam-tag"><i class="fas fa-circle"></i> CÁMARA ACTIVA</div>
        </div>
        <canvas id="canvas" style="display:none;"></canvas>
      </div>
    </div>
  </div>
</div>

<!-- ===== PANTALLA 4: ÉXITO ===== -->
<div class="screen" id="screen-success">
  <div class="success-ring"><i class="fas fa-check"></i></div>
  <h1 class="screen-title" id="nombreUsuario">¡Bienvenido!</h1>
  <div class="success-type" id="tipoMarcacion">ENTRADA</div>
  <p class="screen-sub" style="margin-top:18px;">Regresando al bloqueo en <b id="countdown">5</b> s…</p>
</div>

<div class="status-bar"><i class="fas fa-circle"></i> SISTEMA EN LÍNEA · CONEXIÓN SEGURA · <?= date('Y-m-d') ?></div>

<script>
const B = '/horion-time/public';
let TOKEN_ACTUAL = <?= json_encode($token_inicial) ?>;
let SEDE_ID = <?= $dispositivo ? (int)($dispositivo['sede_id'] ?? 0) : 'null' ?>;
let stream = null, inactividadTimer = null;
const INACTIVIDAD_SEG = <?= $inactividad ?>;

// ===== Reloj y fecha =====
const diasEs = ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'];
const mesesEs = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
function tick(){
  const n = new Date();
  document.getElementById('reloj').textContent = n.toLocaleTimeString('es-CO',{hour12:false});
  document.getElementById('fecha').textContent = diasEs[n.getDay()] + ', ' + n.getDate() + ' de ' + mesesEs[n.getMonth()] + ' ' + n.getFullYear();
}
setInterval(tick,1000); tick();

// ===== Navegación entre pantallas =====
function show(id){
  document.querySelectorAll('.screen').forEach(s => s.classList.remove('active'));
  document.getElementById(id).classList.add('active');
  resetInactividad();
}
function resetInactividad(){
  clearTimeout(inactividadTimer);
  if (document.getElementById('screen-identify').classList.contains('active')) {
    inactividadTimer = setTimeout(volverPin, INACTIVIDAD_SEG * 1000);
  }
}
['touchstart','click','keydown'].forEach(e => document.addEventListener(e, resetInactividad, {passive:true}));

// ===== Pantalla 1: token =====
function cargarDispositivo(){
  const t = document.getElementById('inpToken').value.trim();
  if (!t) return err('errToken','Ingrese el código del dispositivo');
  TOKEN_ACTUAL = t;
  document.getElementById('inpPin').value = '';
  show('screen-pin');
}
function volverToken(){ show('screen-token'); }

// ===== Pantalla 2: PIN =====
function tecla(v){
  const inp = document.getElementById('inpPin');
  if (v === 'C') inp.value = inp.value.slice(0,-1);
  else if (v === 'OK') validarPin();
  else if (inp.value.length < 4) inp.value += v;
}
async function validarPin(){
  const pin = document.getElementById('inpPin').value;
  if (pin.length !== 4) return err('errPin','El PIN debe tener 4 dígitos');
  try{
    const r = await fetch(B + '/kiosco/validarPin', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ token: TOKEN_ACTUAL, pin: pin })
    });
    const d = await r.json();
    if (d.success){
      SEDE_ID = d.sede_id ?? SEDE_ID;
      iniciarCamara();
      show('screen-identify');
      setTimeout(() => document.getElementById('inpCedula').focus(), 300);
    } else err('errPin', d.message || 'PIN incorrecto');
  }catch(e){ err('errPin','Error de conexión con el servidor'); }
}
function volverPin(){
  if (stream) stream.getTracks().forEach(t => t.stop());
  stream = null;
  document.getElementById('inpPin').value = '';
  document.getElementById('inpCedula').value = '';
  show('screen-pin');
}

// ===== Pantalla 3: cámara y marcación =====
async function iniciarCamara(){
  try{
    stream = await navigator.mediaDevices.getUserMedia({ video:{ facingMode:'user', width:{ideal:720} }, audio:false });
    document.getElementById('video').srcObject = stream;
  }catch(e){ console.warn('Sin cámara:', e); }
}
function capturarFoto(){
  const v = document.getElementById('video'), c = document.getElementById('canvas');
  if (!v.videoWidth) return null;
  c.width = v.videoWidth; c.height = v.videoHeight;
  c.getContext('2d').drawImage(v,0,0);
  return c.toDataURL('image/jpeg',0.7);
}
async function iniciarMarcacion(){
  const ced = document.getElementById('inpCedula').value.trim();
  if (ced.length < 4) return err('errIdent','Cédula inválida');
  const btn = document.getElementById('btnMarcar');
  btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando…';
  try{
    const r = await fetch(B + '/kiosco/identificar', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ identificacion: ced, sede_id: SEDE_ID, token: TOKEN_ACTUAL, foto_data: capturarFoto() })
    });
    const d = await r.json();
    if (d.success){
      document.getElementById('nombreUsuario').textContent = '¡' + (d.nombre || '').split(' ')[0] + '!';
      document.getElementById('tipoMarcacion').textContent = (d.tipo || 'Entrada').toUpperCase() + ' · ' + new Date().toLocaleTimeString('es-CO',{hour12:false});
      show('screen-success');
      let c = 5;
      const ci = setInterval(() => {
        c--; document.getElementById('countdown').textContent = c;
        if (c <= 0){ clearInterval(ci); volverPin(); }
      }, 1000);
    } else err('errIdent', d.message || 'No fue posible registrar');
  }catch(e){ err('errIdent','Error de red'); }
  finally{ btn.disabled = false; btn.innerHTML = '<i class="fas fa-fingerprint"></i> Registrar marcación'; }
}

function err(id,msg){
  const el = document.getElementById(id);
  el.textContent = msg; el.classList.add('show');
  setTimeout(() => el.classList.remove('show'), 3500);
}

document.getElementById('inpToken').addEventListener('keypress', e => { if (e.key === 'Enter') cargarDispositivo(); });
document.getElementById('inpCedula').addEventListener('keypress', e => { if (e.key === 'Enter') iniciarMarcacion(); });

<?php if ($dispositivo): ?>
show('screen-pin');
<?php endif; ?>
</script>
</body>
</html>
