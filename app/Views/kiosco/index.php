<?php
// app/Views/kiosco/index.php — KIOSCO v3.0 (registro directo con cédula)
$dispositivo = $dispositivo ?? null;
$token_inicial = $dispositivo ? ($dispositivo['token_kiosco'] ?? '') : '';
$nombre_sede = $dispositivo ? ($dispositivo['sede_nombre'] ?? '') : '';
$nombre_disp = $dispositivo ? ($dispositivo['nombre'] ?? '') : '';
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
  --primary:#03a950; --primary-dark:#02843c; --primary-light:#e6f9f0;
  --bg:#f1f5f9; --card:#ffffff; --text:#0f172a; --muted:#64748b;
  --border:#e2e8f0; --danger:#dc2626; --danger-light:#fef2f2;
  --radius:20px; --shadow:0 10px 30px rgba(2,132,60,.08), 0 2px 8px rgba(15,23,42,.06);
}
*{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent;}
html,body{height:100%;}
body{background:var(--bg);background-image:radial-gradient(circle at 15% 20%, rgba(3,169,80,.06), transparent 45%),radial-gradient(circle at 85% 80%, rgba(3,169,80,.05), transparent 45%);color:var(--text);font-family:'Inter',system-ui,sans-serif;overflow:hidden;}
.top-bar{position:fixed;top:0;left:0;right:0;z-index:10;display:flex;justify-content:space-between;align-items:center;gap:12px;padding:14px 24px;background:rgba(255,255,255,.92);backdrop-filter:blur(10px);border-bottom:1px solid var(--border);}
.brand{display:flex;align-items:center;gap:12px;}
.brand-icon{width:42px;height:42px;border-radius:12px;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.2rem;box-shadow:0 4px 12px rgba(3,169,80,.35);}
.brand-text{font-weight:900;font-size:1.05rem;}
.brand-text span{color:var(--primary);}
.brand-sub{font-size:.72rem;color:var(--muted);font-weight:600;letter-spacing:1px;text-transform:uppercase;}
.clock-box{text-align:right;}
.clock{font-size:1.6rem;font-weight:800;font-variant-numeric:tabular-nums;}
.clock-date{font-size:.75rem;color:var(--muted);font-weight:600;text-transform:capitalize;}
.screen{position:absolute;inset:0;z-index:2;display:none;flex-direction:column;align-items:center;justify-content:center;padding:90px 16px 60px;overflow-y:auto;animation:fadeIn .35s ease;}
.screen.active{display:flex;}
@keyframes fadeIn{from{opacity:0;transform:translateY(8px);}to{opacity:1;transform:translateY(0);}}
.screen-title{font-size:clamp(1.4rem,4vw,2rem);font-weight:900;text-align:center;}
.screen-title i{color:var(--primary);margin-right:8px;}
.screen-sub{color:var(--muted);font-size:.95rem;margin:6px 0 18px;text-align:center;font-weight:500;}
.card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);padding:26px;width:100%;max-width:460px;}
.card.wide{max-width:900px;}
.k-label{display:block;font-size:.85rem;font-weight:800;color:var(--primary-dark);margin-bottom:8px;text-transform:uppercase;letter-spacing:.8px;}
.k-input{width:100%;padding:18px;border:2px solid var(--border);border-radius:14px;font-family:'Inter',sans-serif;font-size:1.6rem;font-weight:800;text-align:center;letter-spacing:4px;color:var(--text);background:#fff;outline:none;transition:border .2s, box-shadow .2s;}
.k-input:focus{border-color:var(--primary);box-shadow:0 0 0 5px rgba(3,169,80,.15);}
.k-input.cedula{letter-spacing:2px;text-align:left;font-size:1.7rem;}
.btn-k{width:100%;margin-top:14px;padding:18px;border:none;border-radius:14px;cursor:pointer;background:var(--primary);color:#fff;font-family:'Inter',sans-serif;font-size:1.05rem;font-weight:800;box-shadow:0 6px 16px rgba(3,169,80,.30);transition:all .2s;display:flex;align-items:center;justify-content:center;gap:10px;}
.btn-k:active{background:var(--primary-dark);transform:translateY(-1px);}
.btn-k:disabled{opacity:.6;cursor:wait;}
.btn-ghost{width:100%;margin-top:10px;padding:13px;border-radius:14px;cursor:pointer;background:#fff;border:1.5px solid var(--border);color:var(--muted);font-family:'Inter',sans-serif;font-size:.88rem;font-weight:700;display:flex;align-items:center;justify-content:center;gap:8px;}
.btn-ghost:hover{border-color:var(--primary);color:var(--primary);}
.keypad{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:14px;}
.keypad.mini{gap:8px;margin-top:12px;}
.key{padding:16px 0;background:#fff;border:1.5px solid var(--border);border-radius:14px;font-family:'Inter',sans-serif;font-size:1.4rem;font-weight:800;color:var(--text);cursor:pointer;transition:all .12s;min-height:58px;display:flex;align-items:center;justify-content:center;}
.keypad.mini .key{min-height:50px;font-size:1.2rem;padding:10px 0;}
.key:active{background:var(--primary-light);border-color:var(--primary);color:var(--primary-dark);transform:scale(.96);}
.key.fn{background:var(--bg);color:var(--muted);font-size:1rem;}
.key.fn.ok{background:var(--primary-light);border-color:var(--primary);color:var(--primary-dark);}
.cam-wrap{position:relative;width:100%;max-width:340px;aspect-ratio:1/1;margin:0 auto;border-radius:24px;overflow:hidden;background:#0f172a;border:3px solid var(--primary);box-shadow:0 8px 24px rgba(3,169,80,.25);}
.cam-wrap video{width:100%;height:100%;object-fit:cover;transform:scaleX(-1);}
.scan-line{position:absolute;left:0;right:0;height:3px;background:linear-gradient(90deg,transparent,var(--primary),transparent);box-shadow:0 0 14px var(--primary);animation:scan 2.2s linear infinite;}
@keyframes scan{0%{top:0;}100%{top:100%;}}
.cam-tag{position:absolute;bottom:10px;left:50%;transform:translateX(-50%);background:rgba(15,23,42,.72);color:#fff;font-size:.72rem;font-weight:700;letter-spacing:1.5px;padding:6px 14px;border-radius:999px;white-space:nowrap;}
.cam-tag i{color:#4ade80;margin-right:6px;font-size:.55rem;vertical-align:middle;}
#flash{position:fixed;inset:0;background:#fff;opacity:0;pointer-events:none;z-index:50;transition:opacity .15s;}
#flash.on{opacity:1;}
.ident-grid{display:grid;grid-template-columns:1fr 1fr;gap:26px;align-items:start;}
.sede-chip{display:inline-flex;align-items:center;gap:8px;background:var(--primary-light);color:var(--primary-dark);border-radius:999px;padding:7px 14px;font-size:.8rem;font-weight:700;margin-bottom:14px;}
.hint{font-size:.8rem;color:var(--muted);margin-top:10px;text-align:center;font-weight:600;}
.hint b{color:var(--primary-dark);}
.lock-btn{position:fixed;bottom:52px;right:16px;z-index:20;width:46px;height:46px;border-radius:50%;border:1.5px solid var(--border);background:#fff;color:var(--muted);font-size:1rem;cursor:pointer;box-shadow:var(--shadow);}
.lock-btn:hover{color:var(--primary);border-color:var(--primary);}
.success-ring{width:130px;height:130px;border-radius:50%;background:var(--primary-light);border:6px solid var(--primary);display:flex;align-items:center;justify-content:center;font-size:3.4rem;color:var(--primary);margin-bottom:18px;box-shadow:0 0 0 12px rgba(3,169,80,.12);animation:pulse 1.6s infinite;}
@keyframes pulse{0%,100%{transform:scale(1);}50%{transform:scale(1.04);}}
.success-type{display:inline-block;background:var(--primary);color:#fff;border-radius:999px;padding:8px 22px;font-weight:800;letter-spacing:1px;margin-top:8px;}
#fotoOk{width:120px;height:120px;object-fit:cover;border-radius:16px;border:3px solid var(--primary);margin-top:14px;box-shadow:var(--shadow);}
.err{display:none;margin-top:12px;padding:12px 14px;border-radius:12px;background:var(--danger-light);border:1px solid #fecaca;color:var(--danger);font-size:.9rem;font-weight:700;text-align:center;}
.err.show{display:block;animation:shake .35s;}
@keyframes shake{0%,100%{transform:translateX(0);}25%{transform:translateX(-8px);}75%{transform:translateX(8px);}}
.status-bar{position:fixed;bottom:0;left:0;right:0;z-index:10;text-align:center;padding:10px;font-size:.72rem;color:var(--muted);font-weight:600;letter-spacing:1px;background:rgba(255,255,255,.9);border-top:1px solid var(--border);}
.status-bar i{color:var(--primary);margin-right:6px;}
.status-bar b{color:var(--primary-dark);}
@media (max-width:860px){
  .ident-grid{grid-template-columns:1fr;gap:16px;}
  .cam-wrap{max-width:240px;}
  .card{padding:20px;} .card.wide{max-width:540px;}
  .top-bar{padding:10px 14px;} .clock{font-size:1.25rem;} .brand-sub{display:none;}
  .screen{padding:80px 12px 56px;}
}
@media (max-width:420px){
  .key{min-height:50px;font-size:1.2rem;}
  .keypad.mini .key{min-height:42px;font-size:1.05rem;}
  .k-input{font-size:1.2rem;letter-spacing:4px;}
  .k-input.cedula{font-size:1.35rem;letter-spacing:1px;}
}
</style>
</head>
<body>

<div id="flash"></div>

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

<!-- ===== PANTALLA A: TOKEN (solo si la URL no trae token) ===== -->
<div class="screen" id="screen-token">
  <h1 class="screen-title"><i class="fas fa-plug"></i> Conectar Terminal</h1>
  <p class="screen-sub">Ingrese el código del dispositivo asignado a este equipo</p>
  <div class="card">
    <label class="k-label">Código del dispositivo</label>
    <input type="text" id="inpToken" class="k-input" style="letter-spacing:2px;font-size:1rem;" placeholder="PEGUE O ESCRIBA EL TOKEN" value="<?= htmlspecialchars($token_inicial) ?>" autocomplete="off">
    <button class="btn-k" onclick="cargarDispositivo()"><i class="fas fa-arrow-right"></i> Continuar</button>
    <div class="err" id="errToken"></div>
  </div>
</div>

<!-- ===== PANTALLA B: EMPLEADO (cédula + cámara + registro automático) ===== -->
<div class="screen" id="screen-identify">
  <h1 class="screen-title"><i class="fas fa-id-card"></i> Registro de Asistencia</h1>
  <p class="screen-sub">Digite su <b style="color:var(--primary-dark);">CÉDULA COMPLETA</b> y mire a la cámara<br><small>Al terminar de digitar, el registro y la foto se toman automáticamente</small></p>
  <div class="card wide">
    <?php if ($nombre_sede !== ''): ?>
    <div style="text-align:center;"><span class="sede-chip"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($nombre_sede) ?></span></div>
    <?php endif; ?>
    <div class="ident-grid">
      <div>
        <label class="k-label">Número de cédula (10 a 15 dígitos)</label>
        <input type="text" id="inpCedula" class="k-input cedula" placeholder="Ej: 1001914117" inputmode="numeric" autocomplete="off" maxlength="15">
        <div class="keypad mini">
          <div class="key" onclick="teclaCed('1')">1</div><div class="key" onclick="teclaCed('2')">2</div><div class="key" onclick="teclaCed('3')">3</div>
          <div class="key" onclick="teclaCed('4')">4</div><div class="key" onclick="teclaCed('5')">5</div><div class="key" onclick="teclaCed('6')">6</div>
          <div class="key" onclick="teclaCed('7')">7</div><div class="key" onclick="teclaCed('8')">8</div><div class="key" onclick="teclaCed('9')">9</div>
          <div class="key fn" onclick="teclaCed('C')"><i class="fas fa-backspace"></i></div>
          <div class="key" onclick="teclaCed('0')">0</div>
          <div class="key fn ok" onclick="teclaCed('OK')"><i class="fas fa-fingerprint"></i></div>
        </div>
        <button class="btn-k" id="btnMarcar" onclick="iniciarMarcacion()"><i class="fas fa-camera"></i> Registrar y tomar foto</button>
        <div class="err" id="errIdent"></div>
        <p class="hint">Sin teclado físico: use el teclado de pantalla. <b>Registro automático</b> 1 s después de completar la cédula.</p>
      </div>
      <div>
        <div class="cam-wrap">
          <video id="video" autoplay playsinline muted></video>
          <div class="scan-line"></div>
          <div class="cam-tag"><i class="fas fa-circle"></i> CÁMARA ACTIVA — MIRE AL FRENTE</div>
        </div>
        <canvas id="canvas" style="display:none;"></canvas>
      </div>
    </div>
  </div>
</div>

<!-- ===== PANTALLA C: BLOQUEO (PIN solo coordinador) ===== -->
<div class="screen" id="screen-pin">
  <h1 class="screen-title"><i class="fas fa-lock"></i> Kiosco Bloqueado</h1>
  <p class="screen-sub">PIN de 4 dígitos del dispositivo (solo personal autorizado)<br><small>Lo encuentra en el sistema: menú Dispositivos → columna "PIN Acceso"</small></p>
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
  </div>
</div>

<!-- ===== PANTALLA D: ÉXITO ===== -->
<div class="screen" id="screen-success">
  <div class="success-ring"><i class="fas fa-check"></i></div>
  <h1 class="screen-title" id="nombreUsuario">¡Bienvenido!</h1>
  <div class="success-type" id="tipoMarcacion">ENTRADA</div>
  <img id="fotoOk" style="display:none;" alt="Foto capturada">
  <p class="screen-sub" style="margin-top:16px;">Listo para el siguiente empleado en <b id="countdown">5</b> s…</p>
</div>

<button class="lock-btn" title="Bloquear kiosco (solo coordinador)" onclick="bloquear()"><i class="fas fa-lock"></i></button>

<div class="status-bar"><i class="fas fa-circle"></i> SISTEMA EN LÍNEA · <b>KIOSCO v3.0</b> · <?= htmlspecialchars($nombre_disp ?: 'TERMINAL LIBRE') ?> · <?= date('Y-m-d') ?></div>

<script>
const B = '/horion-time/public';
let TOKEN_ACTUAL = <?= json_encode($token_inicial) ?>;
let SEDE_ID = <?= $dispositivo ? (int)($dispositivo['sede_id'] ?? 0) : 'null' ?>;
let stream = null, fotoActual = null, enviando = false, autoTimer = null;

const diasEs = ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'];
const mesesEs = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
function tick(){
  const n = new Date();
  document.getElementById('reloj').textContent = n.toLocaleTimeString('es-CO',{hour12:false});
  document.getElementById('fecha').textContent = diasEs[n.getDay()] + ', ' + n.getDate() + ' de ' + mesesEs[n.getMonth()] + ' ' + n.getFullYear();
}
setInterval(tick,1000); tick();

function show(id){
  document.querySelectorAll('.screen').forEach(s => s.classList.remove('active'));
  document.getElementById(id).classList.add('active');
}

// ----- Arranque: si hay token, directo a la pantalla del EMPLEADO -----
window.addEventListener('load', () => {
  if (TOKEN_ACTUAL) { abrirEmpleado(); } else { show('screen-token'); }
});
function abrirEmpleado(){
  document.getElementById('inpCedula').value = '';
  show('screen-identify');
  iniciarCamara();
  setTimeout(() => document.getElementById('inpCedula').focus(), 300);
}

// ----- Token (solo sin token en URL) -----
function cargarDispositivo(){
  const t = document.getElementById('inpToken').value.trim();
  if (!t) return err('errToken','Ingrese el código del dispositivo');
  TOKEN_ACTUAL = t;
  abrirEmpleado();
}

// ----- Bloqueo / desbloqueo (coordinador) -----
function bloquear(){
  detenerCamara();
  document.getElementById('inpPin').value = '';
  show('screen-pin');
}
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
      abrirEmpleado();
    } else {
      err('errPin', (d.message || 'PIN incorrecto') + ' — verifique en Dispositivos → PIN Acceso');
      document.getElementById('inpPin').value = '';
    }
  }catch(e){ err('errPin','Error de conexión'); }
}

// ----- Cédula: teclado en pantalla + registro automático -----
function teclaCed(v){
  const inp = document.getElementById('inpCedula');
  if (v === 'C') { inp.value = inp.value.slice(0,-1); programarAuto(); }
  else if (v === 'OK') iniciarMarcacion();
  else if (inp.value.length < 15) { inp.value += v; programarAuto(); }
}
document.addEventListener('DOMContentLoaded', () => {
  const inp = document.getElementById('inpCedula');
  inp.addEventListener('input', programarAuto);
  inp.addEventListener('keypress', e => { if (e.key === 'Enter') iniciarMarcacion(); });
  document.getElementById('inpToken').addEventListener('keypress', e => { if (e.key === 'Enter') cargarDispositivo(); });
});
function programarAuto(){
  clearTimeout(autoTimer);
  const len = document.getElementById('inpCedula').value.trim().length;
  if (len >= 6) autoTimer = setTimeout(iniciarMarcacion, 1200);
}

// ----- Cámara -----
async function iniciarCamara(){
  try{
    stream = await navigator.mediaDevices.getUserMedia({ video:{ facingMode:'user', width:{ideal:720} }, audio:false });
    document.getElementById('video').srcObject = stream;
  }catch(e){ console.warn('Sin cámara:', e); }
}
function detenerCamara(){
  if (stream) stream.getTracks().forEach(t => t.stop());
  stream = null;
}
function capturarFoto(){
  const v = document.getElementById('video'), c = document.getElementById('canvas');
  if (!v.videoWidth) return null;
  c.width = v.videoWidth; c.height = v.videoHeight;
  c.getContext('2d').drawImage(v,0,0);
  return c.toDataURL('image/jpeg',0.75);
}
function flash(){
  const f = document.getElementById('flash');
  f.classList.add('on');
  setTimeout(() => f.classList.remove('on'), 180);
}

// ----- Registro -----
async function iniciarMarcacion(){
  if (enviando) return;
  clearTimeout(autoTimer);
  const ced = document.getElementById('inpCedula').value.trim();
  if (ced.length < 5) return err('errIdent','Digite la cédula COMPLETA (mínimo 5 dígitos)');
  enviando = true;
  const btn = document.getElementById('btnMarcar');
  btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Registrando…';
  fotoActual = capturarFoto();
  if (fotoActual) flash();
  try{
    const r = await fetch(B + '/kiosco/identificar', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ identificacion: ced, sede_id: SEDE_ID, token: TOKEN_ACTUAL, foto_data: fotoActual })
    });
    const d = await r.json();
    if (d.success){
      document.getElementById('nombreUsuario').textContent = '¡' + (d.nombre || '').split(' ')[0] + '!';
      document.getElementById('tipoMarcacion').textContent = (d.tipo || 'Entrada').toUpperCase() + ' · ' + new Date().toLocaleTimeString('es-CO',{hour12:false});
      const img = document.getElementById('fotoOk');
      if (fotoActual){ img.src = fotoActual; img.style.display = 'block'; } else img.style.display = 'none';
      show('screen-success');
      let c = 5;
      document.getElementById('countdown').textContent = c;
      const ci = setInterval(() => {
        c--; document.getElementById('countdown').textContent = c;
        if (c <= 0){ clearInterval(ci); abrirEmpleado(); }
      }, 1000);
    } else err('errIdent', d.message || 'No fue posible registrar');
  }catch(e){ err('errIdent','Error de red'); }
  finally{ enviando = false; btn.disabled = false; btn.innerHTML = '<i class="fas fa-camera"></i> Registrar y tomar foto'; }
}

function err(id,msg){
  const el = document.getElementById(id);
  el.textContent = msg; el.classList.add('show');
  setTimeout(() => el.classList.remove('show'), 4500);
}
</script>
</body>
</html>
