<?php 
session_start();
include(__DIR__ . '/../layouts/header.php'); 
include(__DIR__ . '/../layouts/sidebar.php'); 
$dispositivos = $dispositivos ?? [];
$sedes = $sedes ?? [];
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
        <?php if (isset($_GET['success'])): ?>
        <div class="card-3d" style="background:#dcfce7;border-left:4px solid var(--success);margin-bottom:20px;color:#166534;">
            <i class="fas fa-check-circle"></i> Dispositivo registrado. Copia su PIN y guarda el enlace del kiosco.
        </div>
        <?php endif; ?>
        <?php if (isset($_GET['deleted'])): ?>
        <div class="card-3d" style="background:#fef3c7;border-left:4px solid var(--warning);margin-bottom:20px;color:#92400e;">
            <i class="fas fa-trash"></i> Dispositivo eliminado.
        </div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
        <div class="card-3d" style="background:#fef2f2;border-left:4px solid #dc2626;margin-bottom:20px;color:#991b1b;">
            <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($_GET['error']) ?>
        </div>
        <?php endif; ?>

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
                        <?php if (empty($dispositivos)): ?>
                        <tr>
                            <td colspan="6" style="text-align:center;color:var(--text-muted);padding:32px;">
                                <i class="fas fa-tablet-alt" style="font-size:1.6rem;display:block;margin-bottom:8px;opacity:.5;"></i>
                                No hay dispositivos registrados. Crea el primero con el botón "Nuevo Dispositivo".
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php foreach ($dispositivos as $d): ?>
                        <tr>
                            <td>
                                <div style="font-weight: 600;"><?= htmlspecialchars($d['nombre']) ?></div>
                                <div style="font-size: 0.8rem; color: var(--text-muted);"><?= ucfirst($d['tipo']) ?></div>
                            </td>
                            <td><?= htmlspecialchars($d['sede_nombre'] ?? '—') ?></td>
                            <td>
                                <?php if (!empty($d['pin_acceso'])): ?>
                                <span class="badge" style="background: #e0e7ff; color: #3730a3; font-family: monospace; letter-spacing: 2px;">
                                    <?= htmlspecialchars($d['pin_acceso']) ?>
                                </span>
                                <?php else: ?><span style="color:var(--text-muted);">—</span><?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($d['modo_kiosco'])): ?>
                                    <span class="badge badge-success"><i class="fas fa-check"></i> Activo</span>
                                <?php else: ?>
                                    <span class="badge badge-danger"><i class="fas fa-times"></i> Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 0.85rem; color: var(--text-muted);">
                                <?= !empty($d['ultima_actividad']) ? date('d/m/Y H:i', strtotime($d['ultima_actividad'])) : 'Nunca' ?>
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <?php if (!empty($d['token_kiosco'])): ?>
                                <a href="/horion-time/public/kiosco/<?= htmlspecialchars($d['token_kiosco']) ?>" target="_blank" class="btn" style="background: var(--primary); color: white; padding: 6px 10px; font-size: 0.8rem; text-decoration:none;">
                                    <i class="fas fa-external-link-alt"></i> Abrir Kiosco
                                </a>
                                <?php else: ?>
                                <span class="badge badge-warning">Sin token</span>
                                <?php endif; ?>
                                <button class="btn btn-danger" style="padding: 6px 10px;" onclick="confirmDelete(<?= (int)$d['id'] ?>, 'dispositivo')">
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
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
            <input type="hidden" name="empresa_id" value="<?= (int)($_SESSION['empresa_id'] ?? 1) ?>">
            
            <div style="margin-bottom: 16px;">
                <label class="form-label">Nombre del Dispositivo *</label>
                <input type="text" name="nombre" required class="form-input" placeholder="Ej: Kiosco Recepción Principal">
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                <div>
                    <label class="form-label">Tipo</label>
                    <select name="tipo" class="form-input">
                        <option value="kiosco">Kiosco Dedicado</option>
                        <option value="tablet">Tablet</option>
                        <option value="pc">PC / Laptop</option>
                        <option value="movil">Móvil</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Sede *</label>
                    <select name="sede_id" required class="form-input">
                        <option value="">Seleccione sede…</option>
                        <?php
                        $grupos = [];
                        foreach ($sedes as $s) { $grupos[(int)$s['empresa_id']][] = $s; }
                        foreach ($grupos as $eid => $lista):
                            $cab = $lista[0]['empresa_nombre'] ?? 'Empresa';
                        ?>
                        <optgroup label="🏢 <?= htmlspecialchars($cab) ?>">
                            <?php foreach ($lista as $s): ?>
                            <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['nombre']) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                <div>
                    <label class="form-label">Tiempo de inactividad (segundos)</label>
                    <input type="number" name="inactividad_seg" value="30" min="5" max="3600" class="form-input">
                </div>
                <div style="display:flex;align-items:end;padding-bottom:10px;">
                    <label style="display:flex;gap:8px;align-items:center;cursor:pointer;font-size:.9rem;">
                        <input type="checkbox" name="modo_kiosco" value="1" checked>
                        <span><b>Modo kiosco</b> (pantalla completa en portería)</span>
                    </label>
                </div>
            </div>

            <p style="font-size:.8rem;color:var(--text-muted);background:var(--bg-body,#f8fafc);padding:10px 12px;border-radius:10px;margin-bottom:20px;">
                <i class="fas fa-key" style="color:var(--primary);"></i>
                Al crearlo, el sistema genera automáticamente su <b>PIN de acceso</b> y su <b>token de kiosco</b> (enlace único para el tablet de portería).
            </p>

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

<script>
// Fallback por si horion-app.js no define confirmDelete
if (typeof confirmDelete !== 'function') {
    window.confirmDelete = function (id, tipo) {
        if (confirm('¿Eliminar este ' + (tipo || 'registro') + '? Esta acción no se puede deshacer.')) {
            window.location.href = '/horion-time/public/dispositivos/destroy/' + id;
        }
    };
}
</script>

<?php include(__DIR__ . '/../layouts/footer.php'); ?>
