<?php 
session_start();
include(__DIR__ . '/../layouts/header.php'); 
include(__DIR__ . '/../layouts/sidebar.php'); 
?>

<main class="main-content">
    <div class="top-header">
        <h2 style="font-weight: 700; color: var(--text-dark);">
            <i class="fas fa-bell" style="color: var(--primary);"></i>
            Centro de Notificaciones
        </h2>
        <button class="btn" style="background: #f1f5f9;" onclick="marcarTodasLeidas()">
            <i class="fas fa-check-double"></i> Marcar todas como leídas
        </button>
    </div>

    <div style="padding: 32px; max-width: 1200px; margin: 0 auto;">
        <?php if (isset($_GET['success'])): ?>
            <div class="card-3d" style="background: #dcfce7; border-left: 4px solid var(--success); margin-bottom: 24px; color: #166534;">
                <i class="fas fa-check-circle"></i> Preferencias actualizadas correctamente.
            </div>
        <?php endif; ?>

        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
            
            <!-- Lista de Notificaciones -->
            <div class="card-3d">
                <h3 style="font-weight: 700; margin-bottom: 16px;">Recibidas</h3>
                
                <?php if (empty($notificaciones)): ?>
                <div style="text-align: center; padding: 48px; color: var(--text-muted);">
                    <i class="fas fa-inbox" style="font-size: 3rem; color: var(--border); margin-bottom: 16px;"></i>
                    <p>No tienes notificaciones pendientes.</p>
                </div>
                <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <?php foreach ($notificaciones as $notif): 
                        $isRead = $notif['leido'];
                        $colors = [
                            'warning' => '#ff9800', 'error' => '#e53935', 
                            'info' => '#2196f3', 'success' => '#03a950'
                        ];
                        $color = $colors[$notif['tipo']] ?? '#6c757d';
                    ?>
                    <div class="card-3d" style="padding: 16px; display: flex; gap: 16px; align-items: start; 
                         background: <?= $isRead ? '#fafafa' : 'rgba(3, 169, 80, 0.03)' ?>; 
                         border-left: 4px solid <?= $color ?>; transition: all 0.2s;">
                        
                        <div style="width: 40px; height: 40px; background: <?= $color ?>20; color: <?= $color ?>; 
                             border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <i class="fas fa-<?= $notif['tipo'] === 'warning' ? 'exclamation-triangle' : ($notif['tipo'] === 'error' ? 'times-circle' : 'info-circle') ?>"></i>
                        </div>
                        
                        <div style="flex: 1;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                <h4 style="font-weight: 700; font-size: 1rem; margin: 0; color: var(--text-dark);">
                                    <?= htmlspecialchars($notif['titulo']) ?>
                                    <?php if (!$isRead): ?>
                                    <span style="display: inline-block; width: 8px; height: 8px; background: var(--primary); border-radius: 50%; margin-left: 8px;"></span>
                                    <?php endif; ?>
                                </h4>
                                <small style="color: var(--text-muted); font-size: 0.8rem;">
                                    <?= date('d/m/Y H:i', strtotime($notif['fecha_creacion'])) ?>
                                </small>
                            </div>
                            <p style="margin: 0; color: var(--text-muted); font-size: 0.9rem; line-height: 1.4;">
                                <?= htmlspecialchars($notif['mensaje']) ?>
                            </p>
                        </div>

                        <?php if (!$isRead): ?>
                        <button class="btn" style="background: transparent; color: var(--primary); padding: 8px;" 
                                onclick="marcarLeida(<?= $notif['id'] ?>)" title="Marcar como leída">
                            <i class="fas fa-check"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Panel de Configuración -->
            <div class="card-3d" style="height: fit-content;">
                <h3 style="font-weight: 700; margin-bottom: 16px;">
                    <i class="fas fa-cog" style="color: var(--primary);"></i> Preferencias
                </h3>
                <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 20px;">
                    Controla cómo y cuándo recibes notificaciones.
                </p>

                <form action="/horion-time/public/notificaciones/updatePreferences" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    
                    <div style="margin-bottom: 24px;">
                        <h4 style="font-size: 0.9rem; font-weight: 700; margin-bottom: 12px; color: var(--text-dark);">
                            <i class="fas fa-envelope" style="color: var(--info);"></i> Notificaciones por Email
                        </h4>
                        <label style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px; cursor: pointer;">
                            <input type="checkbox" name="email_alertas" <?= $preferencias['recibir_email_alertas'] ? 'checked' : '' ?> style="accent-color: var(--primary); width: 18px; height: 18px;">
                            <span style="font-size: 0.9rem;">Alertas críticas (Tardanzas, Ausencias)</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox" name="email_info" <?= $preferencias['recibir_email_info'] ? 'checked' : '' ?> style="accent-color: var(--primary); width: 18px; height: 18px;">
                            <span style="font-size: 0.9rem;">Información general y actualizaciones</span>
                        </label>
                    </div>

                    <div style="margin-bottom: 24px;">
                        <h4 style="font-size: 0.9rem; font-weight: 700; margin-bottom: 12px; color: var(--text-dark);">
                            <i class="fas fa-mobile-alt" style="color: var(--success);"></i> Notificaciones Push
                        </h4>
                        <label style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px; cursor: pointer;">
                            <input type="checkbox" name="push_alertas" <?= $preferencias['recibir_push_alertas'] ? 'checked' : '' ?> style="accent-color: var(--primary); width: 18px; height: 18px;">
                            <span style="font-size: 0.9rem;">Alertas en el navegador/dispositivo</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox" name="push_info" <?= $preferencias['recibir_push_info'] ? 'checked' : '' ?> style="accent-color: var(--primary); width: 18px; height: 18px;">
                            <span style="font-size: 0.9rem;">Información general</span>
                        </label>
                    </div>

                    <div style="padding-top: 16px; border-top: 1px solid var(--border);">
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox" name="resumen_diario" <?= $preferencias['resumen_diario_email'] ? 'checked' : '' ?> style="accent-color: var(--primary); width: 18px; height: 18px;">
                            <span style="font-size: 0.9rem; font-weight: 600;">Recibir resumen diario por email</span>
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
        });
    }
}
</script>

<?php include(__DIR__ . '/../layouts/footer.php'); ?>