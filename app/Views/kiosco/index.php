<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kiosco HORION TIME - <?= htmlspecialchars($dispositivo['nombre']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #03a950;
            --bg-dark: #0f172a;
            --glass: rgba(255, 255, 255, 0.05);
            --glass-border: rgba(255, 255, 255, 0.1);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; user-select: none; }
        body {
            font-family: 'Inter', sans-serif;
            background: radial-gradient(circle at top right, #1e293b, var(--bg-dark));
            color: white;
            height: 100vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        /* Header Kiosco */
        .k-header {
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(0,0,0,0.2);
            backdrop-filter: blur(10px);
        }
        .k-logo { font-size: 1.5rem; font-weight: 900; color: var(--primary); letter-spacing: -1px; }
        .k-clock { font-size: 2rem; font-weight: 800; font-family: monospace; }
        .k-date { font-size: 1rem; opacity: 0.7; text-align: right; }

        /* Contenedor Principal */
        .k-main {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 60px;
            padding: 40px;
        }

        /* Cámara / Escáner Facial */
        .k-camera-box {
            width: 400px;
            height: 400px;
            background: var(--glass);
            border: 2px solid var(--glass-border);
            border-radius: 30px;
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
            box-shadow: 0 20px 50px rgba(0,0,0,0.5);
        }
        .k-camera-box video { width: 100%; height: 100%; object-fit: cover; opacity: 0.8; }
        .scan-line {
            position: absolute; width: 100%; height: 4px; background: var(--primary);
            box-shadow: 0 0 20px var(--primary);
            animation: scan 3s infinite linear;
        }
        @keyframes scan { 0% { top: 0; } 50% { top: 100%; } 100% { top: 0; } }
        
        .face-icon { position: absolute; font-size: 8rem; color: rgba(255,255,255,0.1); }

        /* Teclado PIN */
        .k-pin-box {
            width: 350px;
            background: var(--glass);
            border: 1px solid var(--glass-border);
            border-radius: 30px;
            padding: 30px;
            backdrop-filter: blur(20px);
        }
        .pin-display {
            background: rgba(0,0,0,0.3);
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            font-size: 2.5rem;
            font-weight: 800;
            letter-spacing: 10px;
            margin-bottom: 20px;
            min-height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid transparent;
            transition: border 0.3s;
        }
        .pin-display.active { border-color: var(--primary); box-shadow: 0 0 20px rgba(3, 169, 80, 0.3); }
        
        .keypad { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; }
        .key {
            background: rgba(255,255,255,0.08);
            border: none;
            border-radius: 15px;
            padding: 20px;
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
            cursor: pointer;
            transition: all 0.1s;
        }
        .key:active { background: var(--primary); transform: scale(0.95); }
        .key.action { background: rgba(229, 57, 53, 0.2); color: #e53935; }
        .key.enter { background: var(--primary); }

        /* Mensaje de éxito */
        .success-overlay {
            position: fixed; inset: 0; background: rgba(3, 169, 80, 0.95);
            display: none; justify-content: center; align-items: center; flex-direction: column;
            z-index: 100; animation: fadeIn 0.3s;
        }
        .success-overlay.active { display: flex; }
        .success-overlay i { font-size: 8rem; margin-bottom: 20px; animation: popIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        .success-overlay h1 { font-size: 3rem; font-weight: 900; }
        .success-overlay p { font-size: 1.5rem; opacity: 0.9; }
        
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes popIn { from { transform: scale(0); } to { transform: scale(1); } }
    </style>
</head>
<body>

    <div class="k-header">
        <div class="k-logo"><i class="fas fa-clock"></i> HORION TIME</div>
        <div>
            <div class="k-clock" id="clock">00:00:00</div>
            <div class="k-date" id="date">Cargando fecha...</div>
        </div>
    </div>

    <div class="k-main">
        <!-- Escáner Facial Simulado -->
        <div class="k-camera-box">
            <i class="fas fa-user-circle face-icon"></i>
            <div class="scan-line"></div>
            <video id="video" autoplay playsinline muted></video>
        </div>

        <!-- Teclado PIN -->
        <div class="k-pin-box">
            <h3 style="text-align: center; margin-bottom: 20px; font-weight: 600; opacity: 0.8;">Ingrese su Identificación</h3>
            <div class="pin-display" id="pinDisplay"></div>
            <div class="keypad">
                <button class="key" onclick="addKey('1')">1</button>
                <button class="key" onclick="addKey('2')">2</button>
                <button class="key" onclick="addKey('3')">3</button>
                <button class="key" onclick="addKey('4')">4</button>
                <button class="key" onclick="addKey('5')">5</button>
                <button class="key" onclick="addKey('6')">6</button>
                <button class="key" onclick="addKey('7')">7</button>
                <button class="key" onclick="addKey('8')">8</button>
                <button class="key" onclick="addKey('9')">9</button>
                <button class="key action" onclick="clearKey()"><i class="fas fa-backspace"></i></button>
                <button class="key" onclick="addKey('0')">0</button>
                <button class="key enter" onclick="submitId()"><i class="fas fa-check"></i></button>
            </div>
        </div>
    </div>

    <!-- Overlay de Éxito -->
    <div class="success-overlay" id="successOverlay">
        <i class="fas fa-check-circle"></i>
        <h1 id="successTitle">¡Marcación Exitosa!</h1>
        <p id="successMsg">Bienvenido</p>
    </div>

    <script>
        const TOKEN = '<?= $dispositivo['token_kiosco'] ?>';
        const EMPRESA_ID = <?= $dispositivo['empresa_id'] ?>;
        const SEDE_ID = <?= $dispositivo['sede_id'] ?>;
        const INACTIVITY_TIME = <?= $dispositivo['tiempo_inactividad_seg'] ?> * 1000;

        let currentInput = '';
        let inactivityTimer;

        // Reloj
        function updateClock() {
            const now = new Date();
            document.getElementById('clock').textContent = now.toLocaleTimeString('es-CO', {hour12: false});
            document.getElementById('date').textContent = now.toLocaleDateString('es-CO', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        }
        setInterval(updateClock, 1000);
        updateClock();

        // Cámara
        navigator.mediaDevices.getUserMedia({ video: true })
            .then(stream => document.getElementById('video').srcObject = stream)
            .catch(err => console.log('Cámara no disponible:', err));

        // Teclado
        function addKey(num) {
            if (currentInput.length < 10) {
                currentInput += num;
                updateDisplay();
                resetInactivity();
            }
        }
        function clearKey() {
            currentInput = currentInput.slice(0, -1);
            updateDisplay();
            resetInactivity();
        }
        function updateDisplay() {
            const display = document.getElementById('pinDisplay');
            display.textContent = '*'.repeat(currentInput.length);
            display.classList.toggle('active', currentInput.length > 0);
        }

        // Enviar Identificación
        async function submitId() {
            if (currentInput.length < 4) return;
            
            try {
                const res = await fetch('/horion-time/public/kiosco/identificar', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ identificacion: currentInput, empresa_id: EMPRESA_ID, sede_id: SEDE_ID, token: TOKEN })
                });
                const data = await res.json();
                
                if (data.success) {
                    showSuccess(data.message);
                } else {
                    alert(data.message);
                }
            } catch (e) {
                alert('Error de conexión');
            }
            clearKey();
        }

        function showSuccess(msg) {
            document.getElementById('successMsg').textContent = msg;
            document.getElementById('successOverlay').classList.add('active');
            setTimeout(() => {
                document.getElementById('successOverlay').classList.remove('active');
            }, 3000);
        }

        // Auto-logout por inactividad
        function resetInactivity() {
            clearTimeout(inactivityTimer);
            inactivityTimer = setTimeout(() => {
                currentInput = '';
                updateDisplay();
                // Aquí se podría redirigir a una pantalla de "Toque para iniciar"
            }, INACTIVITY_TIME);
        }
        document.addEventListener('click', resetInactivity);
        resetInactivity();
    </script>
</body>
</html>