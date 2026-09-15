<?php 
session_start();
include(__DIR__ . '/../layouts/header.php'); 
include(__DIR__ . '/../layouts/sidebar.php'); 
?>

<main class="main-content">
    <div class="top-header">
        <h2 style="font-weight: 700; color: var(--text-dark);">
            <i class="fas fa-tablet-alt" style="color: var(--primary);"></i>
            Dispositivos y Kioscos
        </h2>
        <button class="btn btn-primary" onclick="openModal('modalDispositivo')">
            <i class="fas fa-plus"></i> Nuevo Dispositivo
        </button>
    </div>

    <div style="padding: 32px;">
        <div class="card-3d">
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nombre / Tipo</th>
                            <th>Sede</th>
                            <th>PIN Acceso</th>
                            <th>Modo Kiosco</th>
                            <th>Última Actividad</th>
                            <th style="text-align: right;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dispositivos as $d): ?>
                        <tr>
                            <td>
                                <div style="font-weight: 600;"><?= htmlspecialchars($d['nombre']) ?></div>
                                <div style="font-size: 0.8rem; color: var(--text-muted);"><?= ucfirst($d['tipo']) ?></div>
                            </td>
                            <td><?= htmlspecialchars($d['sede_nombre']) ?></td>
                            <td>
                                <span class="badge" style="background: #e0e7ff; color: #3730a3; font-family: monospace; letter-spacing: 2px;">
                                    <?= htmlspecialchars($d['pin_acceso']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($d['modo_kiosco']): ?>
                                    <span class="badge badge-success"><i class="fas fa-check"></i> Activo</span>
                                <?php else: ?>
                                    <span class="badge badge-danger"><i class="fas fa-times"></i> Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 0.85rem; color: var(--text-muted);">
                                <?= $d['ultima_actividad'] ? date('d/m/Y H:i', strtotime($d['ultima_actividad'])) : 'Nunca' ?>
                            </td>
                            <td style="text-align: right;">
                                <a href="/horion-time/public/kiosco/<?= $d['token_kiosco'] ?>" target="_blank" class="btn" style="background: var(--primary); color: white; padding: 6px 10px; font-size: 0.8rem;">
                                    <i class="fas fa-external-link-alt"></i> Abrir Kiosco
                                </a>
                                <button class="btn btn-danger" style="padding: 6px 10px;" onclick="confirmDelete(<?= $d['id'] ?>, 'dispositivo')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- Modal Crear Dispositivo -->
<div class="modal-overlay" id="modalDispositivo">
    <div class="modal-content">
        <h3 style="margin-bottom: 24px; font-weight: 700;">Registrar Nuevo Dispositivo</h3>
        <form action="/horion-time/public/dispositivos/store" method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <input type="hidden" name="empresa_id" value="<?= $_SESSION['empresa_id'] ?? 1 ?>">
            
            <div style="margin-bottom: 16px;">
                <label class="form-label">Nombre del Dispositivo</label>
                <input type="text" name="nombre" required class="form-input" placeholder="Ej: Kiosco Recepción Principal">
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                <div>
                    <label class="form-label">Tipo</label>
                    <select name="tipo" class="form-input">
                        <option value="kiosco_dedicado">Kiosco Dedicado</option>
                        <option value="tablet">Tablet</option>
                        <option value="pc">PC / Laptop</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Sede</label>
                    <select name="sede_id" required class="form-input">
                        <option value="1">Sede Principal</option>
                    </select>
                </div>
            </div>

            <div style="margin-bottom: 24px;">
                <label class="form-label">Tiempo de inactividad (segundos)</label>
                <input type="number" name="tiempo_inactividad" value="30" class="form-input">
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 12px;">
                <button type="button" class="btn" style="background: #f1f5f9;" onclick="closeModal('modalDispositivo')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Crear Dispositivo</button>
            </div>
        </form>
    </div>
</div>

<style>
.form-label { display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 6px; color: var(--text-muted); }
.form-input { width: 100%; padding: 10px 14px; border: 1px solid var(--border); border-radius: 10px; outline: none; }
</style>
<?php include(__DIR__ . '/../layouts/footer.php'); ?>