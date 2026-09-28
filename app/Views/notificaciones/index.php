<?php 
session_start();
include(__DIR__ . '/../layouts/header.php'); 
include(__DIR__ . '/../layouts/sidebar.php'); 

$notificaciones = $notificaciones ?? [];
$conteos = $conteos ?? [];
$preferencias = $preferencias ?? ['recibir_email_alertas' => true, 'recibir_email_info' => false, 'recibir_push_alertas' => true, 'recibir_push_info' => false, 'resumen_diario_email' => false];
$filtro_tipo = $_GET['tipo'] ?? '';
$filtro_estado = $_GET['estado'] ?? 'pendientes';

// Mapeo de iconos y colores por tipo de alerta
$mapeo = [
    'geocerca' => ['icon' => 'map-marker-alt', 'color' => '#dc2626', 'label' => 'Fuera de rango'],
    'rostro' => ['icon' => 'user-shield', 'color' => '#f59e0b', 'label' => 'Rostro no coincide'],
    'rostro_manual' => ['icon' => 'camera', 'color' => '#3b82f6', 'label' => 'Verificación manual'],
    'vacacion' => ['icon' => 'umbrella-beach', 'color' => '#8b5cf6', 'label' => 'Vacaciones'],
    'dia_libre' => ['icon' => 'bed', 'color' => '#06b6d4', 'label' => 'Día libre'],
    'tardanza' => ['icon' => 'clock', 'color' => '#f97316', 'label' => 'Tardanza'],
    'ausencia' => ['icon' => 'user-slash', 'color' => '#ef4444', 'label' => 'Ausencia'],
    'warning' => ['icon' => 'exclamation-triangle', 'color' => '#f59e0b', 'label' => 'Advertencia'],
    'error' => ['icon' => 'times-circle', 'color' => '#dc2626', 'label' => 'Error'],
    'info' => ['icon' => 'info-circle', 'color' => '#3b82f6', 'label' => 'Información'],
    'success' => ['icon' => 'check-circle', 'color' => '#03a950', 'label' => 'Éxito'],
];
?>

<main class="main-content">
    <div class="top-header">
        <h2 style="font-weight: 700; color: var(--text-dark);">
            <i class="fas fa-bell" style="color: var(--primary);"></i>
            Centro de Notificaciones
        </h2>
        <button class="btn btn-primary" onclick="marcarTodasLeidas()">
            <i class="fas fa-check-double"></i> Marcar todas como leídas
        </button>
    </div>

    <div style="padding: 24px; max-width: 1400px; margin: 0 auto;">
        <?php if (isset($_GET['success'])): ?>
            <div class="card-3d" style="background: #dcfce7; border-left: 4px solid var(--success); margin-bottom: 20px; color: #166534; padding: 14px 18px;">
                <i class="fas fa-check-circle"></i> Preferencias actualizadas correctamente.
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <div class="card-3d" style="background: #fef2f2; border-left: 4px solid #dc2626; margin-bottom: 20px; color: #991b1b; padding: 14px 18px;">
                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($_GET['error']) ?>
            </div>
        <?php endif; ?>

        <!-- Filtros -->
        <div class="card-3d" style="padding: 16px 20px; margin-bottom: 20px;">
            <form method="GET" action="/horion-time/public/notificaciones" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: end;">
                <div>
                    <label class="form-label" style="font-size: .78rem;">Estado</label>
                    <select name="estado" class="form-input" style="width: auto; min-width: 140px;">
                        <option value="pendientes" <?= $filtro_estado === 'pendientes' ? 'selected' : '' ?>>🔔 Pendientes</option>
                        <option value="leidas" <?= $filtro_estado === 'leidas' ? 'selected' : '' ?>>✅ Leídas</option>
                        <option value="todas" <?= $filtro_estado === 'todas' ? 'selected' : '' ?>>📋 Todas</option>
                    </select>
                </div>
                <div>
                    <label class="form-label" style="font-size: .78rem;">Tipo de alerta</label>
                    <select name="tipo" class="form-input" style="width: auto; min-width: 180px;">
                        <option value="">Todos los tipos</option>
                        <?php foreach ($mapeo as $key => $info): ?>
                        <option value="<?= htmlspecialchars($key) ?>" <?= $filtro_tipo === $key ? 'selected' : '' ?>>
                            <?= htmlspecialchars($info['label']) ?> (<?= $conteos[$key] ?? 0 ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="btn btn-primary" type="submit"><i class="fas fa-filter"></i> Filtrar</button>
                <a href="/horion-time/public/notificaciones" class="btn" style="background: var(--bg-body); text-decoration: none;">Limpiar</a>
            </form>
        </div>

        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
            
            <!-- Lista de Notificaciones -->
            <div class="card-3d" style="padding: 20px;">
                <h3 style="font-weight: 700; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-inbox" style="color: var(--primary);"></i>
                    Notificaciones
                    <span class="badge badge-info"><?= count($notificaciones) ?></span>
                </h3>
                
                <?php if (empty($notificaciones)): ?>
                <div style="text-align: center; padding: 60px 20px; color: var(--text-muted);">
                    <i class="fas fa-inbox" style="font-size: 4rem; color: var(--border); margin-bottom: 16px; opacity: .5;"></i>
                    <p style="font-size: 1.1rem; font-weight: 600; margin-bottom: 8px;">No hay notificaciones</p>
                    <p style="font-size: .88rem;">Las alertas automáticas aparecerán aquí cuando se detecten eventos.</p>
                </div>
                <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <?php foreach ($notificaciones as $notif): 
                        $isRead = !empty($notif['leido'] ?? $notif['leida'] ?? $notif['read'] ?? 0);
                        $tipo = $notif['tipo'] ?? $notif['categoria'] ?? $notif['type'] ?? 'info';
                        $info = $mapeo[$tipo] ?? $mapeo['info'];
                        $color = $info['color'];
                        $icon = $info['icon'];
                        $titulo = $notif['titulo'] ?? $notif['title'] ?? $notif['asunto'] ?? 'Sin título';
                        $mensaje = $notif['mensaje'] ?? $notif['message'] ?? $notif['descripcion'] ?? '';
                        $fecha = $notif['fecha_creacion'] ?? $notif['created_at'] ?? $notif['fecha'] ?? date('Y-m-d H:i:s');
                    ?>
                    <div class="card-3d" style="padding: 16px; display: flex; gap: 16px; align-items: start; 
                         background: <?= $isRead ? '#fafafa' : 'rgba(3, 169, 80, 0.04)' ?>; 
                         border-left: 4px solid <?= $color ?>; transition: all 0.2s; cursor: pointer;"
                         onclick="<?= !$isRead ? "marcarLeida({$notif['id']})" : '' ?>">
                        
                        <div style="width: 44px; height: 44px; background: <?= $color ?>15; color: <?= $color ?>; 
                             border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 1.2rem;">
                            <i class="fas fa-<?= $icon ?>"></i>
                        </div>
                        
                        <div style="flex: 1; min-width: 0;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; gap: 8px;">
                                <h4 style="font-weight: 700; font-size: 1rem; margin: 0; color: var(--text-dark); display: flex; align-items: center; gap: 8px;">
                                    <?= htmlspecialchars($titulo) ?>
                                    <?php if (!$isRead): ?>
                                    <span style="display: inline-block; width: 8px; height: 8px; background: var(--primary); border-radius: 50; animation: pulse 1.5s infinite;"></span>
                                    <?php endif; ?>
                                </h4>
                                <small style="color: var(--text-muted); font-size: 0.78rem; white-space: nowrap;">
                                    <?= date('d/m H:i', strtotime($fecha)) ?>
                                </small>
                            </div>
                            <p style="margin: 0; color: var(--text-muted); font-size: 0.88rem; line-height: 1.5;">
                                <?= htmlspecialchars($mensaje) ?>
                            </p>
                        </div>

                        <?php if (!$isRead): ?>
                        <button class="btn" style="background: transparent; color: var(--primary); padding: 8px; border-radius: 8px;" 
                                onclick="event.stopPropagation(); marcarLeida(<?= $notif['id'] ?>)" title="Marcar como leída">
                            <i class="fas fa-check"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Panel de Configuración -->
            <div class="card-3d" style="height: fit-content; padding: 20px;">
                <h3 style="font-weight: 700; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-cog" style="color: var(--primary);"></i> Preferencias
                </h3>
                <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 20px;">
                    Controla cómo y cuándo recibes notificaciones.
                </p>

                <form action="/horion-time/public/notificaciones/updatePreferences" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    
                    <div style="margin-bottom: 24px;">
                        <h4 style="font-size: 0.9rem; font-weight: 700; margin-bottom: 12px; color: var(--text-dark); display: flex; align-items: center; gap: 6px;">
                            <i class="fas fa-envelope" style="color: #3b82f6;"></i> Email
                        </h4>
                        <label style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px; cursor: pointer;">
                            <input type="checkbox" name="email_alertas" <?= !empty($preferencias['recibir_email_alertas']) ? 'checked' : '' ?> style="accent-color: var(--primary); width: 18px; height: 18px;">
                            <span style="font-size: 0.88rem;">Alertas críticas</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox" name="email_info" <?= !empty($preferencias['recibir_email_info']) ? 'checked' : '' ?> style="accent-color: var(--primary); width: 18px; height: 18px;">
                            <span style="font-size: 0.88rem;">Información general</span>
                        </label>
                    </div>

                    <div style="margin-bottom: 24px;">
                        <h4 style="font-size: 0.9rem; font-weight: 700; margin-bottom: 12px; color: var(--text-dark); display: flex; align-items: center; gap: 6px;">
                            <i class="fas fa-mobile-alt" style="color: var(--success);"></i> Push
                        </h4>
                        <label style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px; cursor: pointer;">
                            <input type="checkbox" name="push_alertas" <?= !empty($preferencias['recibir_push_alertas']) ? 'checked' : '' ?> style="accent-color: var(--primary); width: 18px; height: 18px;">
                            <span style="font-size: 0.88rem;">Alertas en navegador</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox" name="push_info" <?= !empty($preferencias['recibir_push_info']) ? 'checked' : '' ?> style="accent-color: var(--primary); width: 18px; height: 18px;">
                            <span style="font-size: 0.88rem;">Información general</span>
                        </label>
                    </div>

                    <div style="padding-top: 16px; border-top: 1px solid var(--border);">
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox" name="resumen_diario" <?= !empty($preferencias['resumen_diario_email']) ? 'checked' : '' ?> style="accent-color: var(--primary); width: 18px; height: 18px;">
                            <span style="font-size: 0.88rem; font-weight: 600;">Resumen diario por email</span>
                        </label>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 20px;">
                        <i class="fas fa-save"></i> Guardar Preferencias
                    </button>
                </form>
            </div>
        </div>
    </div>
</main>

<style>
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.4; }
}
</style>

<script>
function marcarLeida(id) {
    fetch('/horion-time/public/notificaciones/markAsRead', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `csrf_token=<?= $csrf_token ?>&id=${id}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) location.reload();
        else alert('Error: ' + (data.message || 'No se pudo marcar'));
    });
}

function marcarTodasLeidas() {
    if(confirm('¿Marcar todas las notificaciones como leídas?')) {
        fetch('/horion-time/public/notificaciones/markAllAsRead', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `csrf_token=<?= $csrf_token ?>`
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) location.reload();
            else alert('Error: ' + (data.message || 'No se pudo marcar'));
        });
    }
}
</script>

<?php include(__DIR__ . '/../layouts/footer.php'); ?>
