<?php
// app/Views/kiosco/index.php
$dispositivo = $dispositivo ?? null;
$token_inicial = $dispositivo ? $dispositivo['token_kiosco'] : '';
$inactividad = $dispositivo ? (int)$dispositivo['inactividad_seg'] : 30;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
<title>HORION TIME // KIOSK</title>
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500;700;900&family=Rajdhani:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
  --bg: #020617;
  --cyan: #00f3ff;
  --magenta: #ff00ea;
  --green: #39ff14;
  --red: #ff2a6d;
  --glass: rgba(10, 25, 47, 0.65);
  --border: rgba(0, 243, 255, 0.3);
}
* { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }
html, body {
  height: 100%; width: 100%; overflow: hidden;
  background: var(--bg);
  color: #fff;
  font-family: 'Rajdhani', sans-serif;
  user-select: none;
}
/* Grid animado de fondo */
body::before {
  content: ""; position: fixed; inset: 0; z-index: 0;
  background-image: 
    linear-gradient(rgba(0, 243, 255, 0.08) 1px, transparent 1px),
    linear-gradient(90deg, rgba(0, 243, 255, 0.08) 1px, transparent 1px);
  background-size: 60px 60px;
  animation: gridMove 30s linear infinite;
  mask-image: radial-gradient(ellipse at center, black 40%, transparent 80%);
}
@keyframes gridMove { from { background-position: 0 0; } to { background-position: 60px 60px; } }

/* Glow ambiental */
body::after {
  content: ""; position: fixed; inset: 0; z-index: 0; pointer-events: none;
  background: 
    radial-gradient(circle at 20% 30%, rgba(255, 0, 234, 0.15), transparent 40%),
    radial-gradient(circle at 80% 70%, rgba(0, 243, 255, 0.15), transparent 40%);
}

.screen {
  position: absolute; inset: 0; z-index: 2;
  display: none; flex-direction: column;
  align-items: center; justify-content: center;
  padding: 20px;
  animation: fadeIn .5s ease;
}
.screen.active { display: flex; }
@keyframes fadeIn { from { opacity: 0; transform: scale(0.98); } to { opacity: 1; transform: scale(1); } }

/* Header / Reloj */
.top-bar {
  position: absolute; top: 0; left: 0; right: 0;
  display: flex; justify-content: space-between; align-items: center;
  padding: 20px 40px; z-index: 5;
  border-bottom: 1px solid var(--border);
  backdrop-filter: blur(10px);
  background: rgba(2, 6, 23, 0.5);
}
.logo { font-family: 'Orbitron'; font-weight: 900; font-size: 1.5rem; letter-spacing: 4px; color: var(--cyan); text-shadow: 0 0 10px var(--cyan); }
.logo span { color: var(--magenta); text-shadow: 0 0 10px var(--magenta); }
.clock { font-family: 'Orbitron'; font-size: 2rem; font-weight: 700; color: #fff; text-shadow: 0 0 15px var(--cyan); letter-spacing: 2px; }

/* Títulos */
h1 {
  font-family: 'Orbitron'; font-weight: 900; font-size: clamp(2rem, 5vw, 4rem);
  text-transform: uppercase; letter-spacing: 6px;
  background: linear-gradient(90deg, var(--cyan), var(--magenta));
  -webkit-background-clip: text; -webkit-text-fill-color: transparent;
  margin-bottom: 10px; text-align: center;
  filter: drop-shadow(0 0 20px rgba(0, 243, 255, 0.5));
}
.subtitle { font-size: 1.2rem; color: rgba(255,255,255,0.6); letter-spacing: 2px; margin-bottom: 40px; text-transform: uppercase; }

/* Tarjetas de vidrio (Glassmorphism) */
.glass-card {
  background: var(--glass);
  border: 1px solid var(--border);
  border-radius: 24px;
  padding: 40px;
  backdrop-filter: blur(20px);
  box-shadow: 0 8px 32px rgba(0, 243, 255, 0.15), inset 0 1px 0 rgba(255,255,255,0.1);
  width: 100%; max-width: 500px;
  position: relative;
}
.glass-card::before {
  content: ""; position: absolute; top: -1px; left: 20%; right: 20%; height: 2px;
  background: linear-gradient(90deg, transparent, var(--cyan), transparent);
}

/* Inputs */
.neon-input {
  width: 100%; padding: 18px 20px;
  background: rgba(0,0,0,0.4);
  border: 2px solid var(--border);
  border-radius: 12px;
  color: #fff; font-family: 'Orbitron'; font-size: 1.5rem;
  text-align: center; letter-spacing: 8px;
  outline: none; transition: all .3s;
}
.neon-input:focus { border-color: var(--cyan); box-shadow: 0 0 20px var(--cyan); }

/* Botones */
.btn-neon {
  padding: 18px 40px;
  background: linear-gradient(135deg, var(--cyan), #0088ff);
  color: #000; font-family: 'Orbitron'; font-weight: 900;
  font-size: 1.1rem; letter-spacing: 3px; text-transform: uppercase;
  border: none; border-radius: 12px; cursor: pointer;
  box-shadow: 0 0 20px var(--cyan), inset 0 1px 0 rgba(255,255,255,0.3);
  transition: all .2s; margin-top: 20px; width: 100%;
}
.btn-neon:hover, .btn-neon:active { transform: translateY(-2px); box-shadow: 0 0 30px var(--cyan); }
.btn-neon.magenta { background: linear-gradient(135deg, var(--magenta), #aa00ff); box-shadow: 0 0 20px var(--magenta); }
.btn-neon.ghost { background: transparent; color: var(--cyan); border: 2px solid var(--cyan); box-shadow: none; }

/* Teclado Numérico */
.keypad { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-top: 20px; }
.key {
  padding: 20px; background: rgba(0, 243, 255, 0.05);
  border: 1px solid var(--border); border-radius: 12px;
  font-family: 'Orbitron'; font-size: 1.8rem; font-weight: 700;
  color: #fff; cursor: pointer; transition: all .1s;
  display: flex; align-items: center; justify-content: center;
}
.key:active { background: var(--cyan); color: #000; transform: scale(0.95); }
.key.fn { background: rgba(255, 0, 234, 0.1); border-color: var(--magenta); color: var(--magenta); font-size: 1.2rem; }

/* Cámara y Escáner */
.cam-wrapper {
  position: relative; width: 320px; height: 320px;
  border-radius: 50%; overflow: hidden;
  border: 4px solid var(--cyan);
  box-shadow: 0 0 40px var(--cyan), inset 0 0 40px rgba(0,243,255,0.2);
  margin: 20px auto;
}
.cam-wrapper video { width: 100%; height: 100%; object-fit: cover; transform: scaleX(-1); }
.scan-line {
  position: absolute; left: 0; right: 0; height: 4px;
  background: linear-gradient(90deg, transparent, var(--green), transparent);
  box-shadow: 0 0 20px var(--green);
  animation: scan 2s linear infinite;
}
@keyframes scan { 0% { top: 0; } 100% { top: 100%; } }
.cam-corners { position: absolute; inset: 0; pointer-events: none; }
.cam-corners::before, .cam-corners::after {
  content: ""; position: absolute; width: 40px; height: 40px;
  border: 4px solid var(--magenta);
}
.cam-corners::before { top: 10px; left: 10px; border-right: none; border-bottom: none; }
.cam-corners::after { bottom: 10px; right: 10px; border-left: none; border-top: none; }

/* Pantalla de Éxito */
.success-icon {
  width: 180px; height: 180px; border-radius: 50%;
  background: radial-gradient(circle, var(--green), transparent 70%);
  display: flex; align-items: center; justify-content: center;
  font-size: 6rem; color: var(--green);
  box-shadow: 0 0 60px var(--green);
  animation: pulseSuccess 1.5s infinite;
  margin-bottom: 30px;
}
@keyframes pulseSuccess { 0%,100% { transform: scale(1); opacity: 1; } 50% { transform: scale(1.05); opacity: 0.8; } }

.error-msg {
  background: rgba(255, 42, 109, 0.15); border: 1px solid var(--red);
  color: var(--red); padding: 12px; border-radius: 8px;
  margin-top: 15px; text-align: center; font-weight: 700;
  display: none;
}
.error-msg.show { display: block; animation: shake .4s; }
@keyframes shake { 0%,100%{transform:translateX(0)} 25%{transform:translateX(-10px)} 75%{transform:translateX(10px)} }

/* Status bar inferior */
.status-bar {
  position: absolute; bottom: 20px; left: 0; right: 0;
  text-align: center; font-size: .85rem; color: rgba(255,255,255,0.4);
  letter-spacing: 2px; z-index: 5;
}
.status-bar i { color: var(--green); margin-right: 6px; }
</style>
</head>
<body>

<div class="top-bar">
  <div class="logo">HORION<span>.</span>TIME</div>
  <div class="clock" id="reloj">00:00:00</div>
</div>

<!-- PANTALLA 1: BIENVENIDA / TOKEN -->
<div class="screen active" id="screen-token">
  <h1>Sistema de Acceso</h1>
  <p class="subtitle">Horion Time Kiosk // v2.0</p>
  <div class="glass-card">
    <p style="text-align:center;margin-bottom:20px;color:rgba(255,255,255,0.7);">Ingrese el <b style="color:var(--cyan)">CÓDIGO DE DISPOSITIVO</b> asignado a esta terminal.</p>
    <input type="text" id="inpToken" class="neon-input" placeholder="TOKEN" value="<?= htmlspecialchars($token_inicial) ?>" autocomplete="off">
    <button class="btn-neon" onclick="cargarDispositivo()"><i class="fas fa-plug"></i> Conectar Terminal</button>
    <div class="error-msg" id="errToken"></div>
  </div>
</div>

<!-- PANTALLA 2: PIN -->
<div class="screen" id="screen-pin">
  <h1>Autenticación</h1>
  <p class="subtitle">Ingrese PIN de supervisor</p>
  <div class="glass-card">
    <input type="password" id="inpPin" class="neon-input" placeholder="* * * *" maxlength="4" readonly>
    <div class="keypad">
      <div class="key" onclick="tecla('1')">1</div><div class="key" onclick="tecla('2')">2</div><div class="key" onclick="tecla('3')">3</div>
      <div class="key" onclick="tecla('4')">4</div><div class="key" onclick="tecla('5')">5</div><div class="key" onclick="tecla('6')">6</div>
      <div class="key" onclick="tecla('7')">7</div><div class="key" onclick="tecla('8')">8</div><div class="key" onclick="tecla('9')">9</div>
      <div class="key fn" onclick="tecla('C')"><i class="fas fa-backspace"></i></div>
      <div class="key" onclick="tecla('0')">0</div>
      <div class="key fn" onclick="tecla('OK')"><i class="fas fa-check"></i></div>
    </div>
    <div class="error-msg" id="errPin"></div>
    <button class="btn-neon ghost" style="margin-top:20px;" onclick="volverToken()"><i class="fas fa-arrow-left"></i> Cambiar Terminal</button>
  </div>
</div>

<!-- PANTALLA 3: IDENTIFICACIÓN + CÁMARA -->
<div class="screen" id="screen-identify">
  <h1>Identificación</h1>
  <p class="subtitle">Escanee su cédula y mire a la cámara</p>
  <div class="glass-card" style="max-width:700px;display:grid;grid-template-columns:1fr 1fr;gap:30px;align-items:center;">
    <div>
      <label style="color:var(--cyan);font-family:'Orbitron';font-size:.9rem;letter-spacing:2px;">NÚMERO DE CÉDULA</label>
      <input type="text" id="inpCedula" class="neon-input" placeholder="0000000" style="font-size:1.3rem;letter-spacing:4px;margin-top:8px;" inputmode="numeric" autocomplete="off">
      <button class="btn-neon" onclick="iniciarMarcacion()" id="btnMarcar"><i class="fas fa-fingerprint"></i> Registrar</button>
      <div class="error-msg" id="errIdent"></div>
      <button class="btn-neon ghost" style="margin-top:15px;font-size:.85rem;padding:12px;" onclick="volverPin()"><i class="fas fa-lock"></i> Bloquear Kiosco</button>
    </div>
    <div style="display:flex;flex-direction:column;align-items:center;">
      <div class="cam-wrapper">
        <video id="video" autoplay playsinline muted></video>
        <div class="scan-line"></div>
        <div class="cam-corners"></div>
      </div>
      <p style="color:var(--green);font-family:'Orbitron';font-size:.8rem;letter-spacing:2px;margin-top:10px;">
        <i class="fas fa-circle" style="font-size:.5rem;animation:pulseSuccess 1s infinite;"></i> CÁMARA ACTIVA
      </p>
      <canvas id="canvas" style="display:none;"></canvas>
    </div>
  </div>
</div>

<!-- PANTALLA 4: ÉXITO -->
<div class="screen" id="screen-success">
  <div class="success-icon"><i class="fas fa-check"></i></div>
  <h1 id="nombreUsuario" style="font-size:2.5rem;">¡Bienvenido!</h1>
  <p class="subtitle" id="tipoMarcacion" style="color:var(--green);font-size:1.5rem;margin-top:0;">ENTRADA REGISTRADA</p>
  <p style="color:rgba(255,255,255,0.5);margin-top:20px;">Retornando en <span id="countdown">5</span>s...</p>
</div>

<div class="status-bar">
  <i class="fas fa-circle"></i> SYSTEM ONLINE // ENCRYPTED CONNECTION // <?= date('Y-m-d') ?>
</div>

<script>
const B = '/horion-time/public';
let TOKEN_ACTUAL = '<?= htmlspecialchars($token_inicial) ?>';
let SEDE_ID = null;
let stream = null;
let inactividadTimer = null;
const INACTIVIDAD_SEG = <?= $inactividad ?>;

// ========== RELOJ ==========
function tick() {
  document.getElementById('reloj').textContent = new Date().toLocaleTimeString('es-CO', {hour12: false});
}
setInterval(tick, 1000); tick();

// ========== NAVEGACIÓN ==========
function show(id) {
  document.querySelectorAll('.screen').forEach(s => s.classList.remove('active'));
  document.getElementById(id).classList.add('active');
  resetInactividad();
}
function resetInactividad() {
  clearTimeout(inactividadTimer);
  if (document.getElementById('screen-identify').classList.contains('active')) {
    inactividadTimer = setTimeout(() => { volverPin(); }, INACTIVIDAD_SEG * 1000);
  }
}
['touchstart','click','keypress'].forEach(e => document.addEventListener(e, resetInactividad));

// ========== PANTALLA 1: TOKEN ==========
async function cargarDispositivo() {
  const t = document.getElementById('inpToken').value.trim();
  if (!t) return err('errToken', 'Ingrese el código del dispositivo');
  TOKEN_ACTUAL = t;
  show('screen-pin');
  document.getElementById('inpPin').focus();
}
function volverToken() { show('screen-token'); document.getElementById('inpPin').value = ''; }

// ========== PANTALLA 2: PIN ==========
function tecla(v) {
  const inp = document.getElementById('inpPin');
  if (v === 'C') inp.value = inp.value.slice(0, -1);
  else if (v === 'OK') validarPin();
  else if (inp.value.length < 4) inp.value += v;
}
async function validarPin() {
  const pin = document.getElementById('inpPin').value;
  if (pin.length !== 4) return err('errPin', 'PIN debe tener 4 dígitos');
  try {
    const r = await fetch(B + '/kiosco/validarPin', {
      method: 'POST', headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ token: TOKEN_ACTUAL, pin: pin })
    });
    const d = await r.json();
    if (d.success) {
      SEDE_ID = d.sede_id;
      iniciarCamara();
      show('screen-identify');
      document.getElementById('inpCedula').focus();
    } else err('errPin', d.message);
  } catch(e) { err('errPin', 'Error de conexión'); }
}
function volverPin() {
  if (stream) stream.getTracks().forEach(t => t.stop());
  stream = null;
  document.getElementById('inpPin').value = '';
  document.getElementById('inpCedula').value = '';
  show('screen-pin');
}

// ========== PANTALLA 3: CÁMARA Y MARCATIÓN ==========
async function iniciarCamara() {
  try {
    stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: {ideal: 720} }, audio: false });
    document.getElementById('video').srcObject = stream;
  } catch(e) { console.warn('Sin cámara:', e); }
}
function capturarFoto() {
  const v = document.getElementById('video'), c = document.getElementById('canvas');
  if (!v.videoWidth) return null;
  c.width = v.videoWidth; c.height = v.videoHeight;
  c.getContext('2d').drawImage(v, 0, 0);
  return c.toDataURL('image/jpeg', 0.7);
}
async function iniciarMarcacion() {
  const ced = document.getElementById('inpCedula').value.trim();
  if (ced.length < 4) return err('errIdent', 'Cédula inválida');
  const btn = document.getElementById('btnMarcar');
  btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> PROCESANDO...';
  try {
    const r = await fetch(B + '/kiosco/identificar', {
      method: 'POST', headers: {'Content-Type':'application/json'},
      body: JSON.stringify({
        identificacion: ced,
        sede_id: SEDE_ID,
        token: TOKEN_ACTUAL,
        foto_data: capturarFoto()
      })
    });
    const d = await r.json();
    if (d.success) {
      document.getElementById('nombreUsuario').textContent = d.nombre.split(' ')[0].toUpperCase();
      document.getElementById('tipoMarcacion').textContent = d.tipo.toUpperCase() + ' // ' + new Date().toLocaleTimeString('es-CO',{hour12:false});
      show('screen-success');
      let c = 5;
      const ci = setInterval(() => {
        c--; document.getElementById('countdown').textContent = c;
        if (c <= 0) { clearInterval(ci); volverPin(); document.getElementById('inpCedula').value = ''; }
      }, 1000);
    } else err('errIdent', d.message);
  } catch(e) { err('errIdent', 'Error de red'); }
  finally { btn.disabled = false; btn.innerHTML = '<i class="fas fa-fingerprint"></i> Registrar'; }
}

function err(id, msg) {
  const el = document.getElementById(id);
  el.textContent = msg; el.classList.add('show');
  setTimeout(() => el.classList.remove('show'), 3500);
}

// Enter key support
document.getElementById('inpToken').addEventListener('keypress', e => { if (e.key === 'Enter') cargarDispositivo(); });
document.getElementById('inpCedula').addEventListener('keypress', e => { if (e.key === 'Enter') iniciarMarcacion(); });

// Si ya venimos con token, saltar directo a PIN
<?php if ($dispositivo): ?>
  show('screen-pin');
<?php endif; ?>
</script>
</body>
</html>
