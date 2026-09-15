<?php include __DIR__ . '/../layouts/header.php'; 
$usuario = $usuario ?? [];
$csrf_token = $csrf_token ?? '';
$ocultas = ['password', 'password_hash', 'clave', 'token', 'remember_token'];
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
    <h2 style="font-weight:700;color:var(--text-dark);margin:0;">
        <i class="fas fa-id-card" style="color:var(--primary);"></i> Mi Perfil
    </h2>
</div>

<?php if (isset($_GET['ok'])): ?>
<div class="card-3d" style="background:#dcfce7;border-left:4px solid var(--success);margin-bottom:24px;color:#166534;">
    <i class="fas fa-check-circle"></i> Contraseña actualizada correctamente.
</div>
<?php endif; ?>
<?php if (isset($_GET['error'])): ?>
<div class="card-3d" style="background:#fef2f2;border-left:4px solid #dc2626;margin-bottom:24px;color:#991b1b;">
    <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($_GET['error']) ?>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
    <div class="card-3d">
        <div style="display:flex;align-items:center;gap:16px;margin-bottom:24px;">
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($usuario['nombre_completo'] ?? $_SESSION['nombre_completo'] ?? 'Usuario') ?>&background=03a950&color=fff&size=96&bold=true"
                 style="width:80px;height:80px;border-radius:50%;border:3px solid var(--primary);box-shadow:0 4px 15px var(--primary-glow);">
            <div>
                <div style="font-size:1.3rem;font-weight:800;"><?= htmlspecialchars($usuario['nombre_completo'] ?? $_SESSION['nombre_completo'] ?? 'Usuario') ?></div>
                <span class="badge badge-success"><i class="fas fa-shield-alt"></i> <?= htmlspecialchars($_SESSION['rol_nombre'] ?? 'Empleado') ?></span>
            </div>
        </div>
        <div style="display:flex;flex-direction:column;gap:10px;">
            <?php foreach ($usuario as $campo => $valor): ?>
                <?php if (in_array($campo, $ocultas) || $campo === 'id') continue; ?>
                <div style="display:flex;justify-content:space-between;padding:10px 14px;background:var(--bg-body);border-radius:10px;font-size:.9rem;">
                    <span style="color:var(--text-muted);text-transform:capitalize;"><?= htmlspecialchars(str_replace('_', ' ', $campo)) ?></span>
                    <strong><?= htmlspecialchars(is_scalar($valor) ? (string)$valor : '—') ?></strong>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card-3d">
        <h3 style="font-weight:700;margin-bottom:16px;"><i class="fas fa-key" style="color:var(--primary);"></i> Cambiar Contraseña</h3>
        <form action="/horion-time/public/portal/cambiarPassword" method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <div style="margin-bottom:14px;">
                <label class="form-label">Contraseña actual</label>
                <input type="password" name="password_actual" class="form-input" required>
            </div>
            <div style="margin-bottom:14px;">
                <label class="form-label">Nueva contraseña (mín. 6 caracteres)</label>
                <input type="password" name="password_nueva" class="form-input" minlength="6" required>
            </div>
            <div style="margin-bottom:20px;">
                <label class="form-label">Confirmar nueva contraseña</label>
                <input type="password" name="password_confirmar" class="form-input" minlength="6" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;">
                <i class="fas fa-save"></i> Actualizar Contraseña
            </button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../layouts/footer.php'; ?>