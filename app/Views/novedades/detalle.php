<?php include __DIR__ . '/../layouts/header.php';
$n = $novedad ?? [];
$tipos = $tipos ?? [];
$puedeProcesar = in_array($_SESSION['rol_nombre'] ?? '', ['SuperAdmin', 'Admin_Empresa', 'RRHH'], true);
$esImagen = false;
if (!empty($n['documento'])) {
    $ext = strtolower(pathinfo($n['documento'], PATHINFO_EXTENSION));
    $esImagen = in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp']);
}
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
    <h2 style="font-weight:700;color:var(--text-dark);margin:0;">
        <i class="fas fa-file-medical" style="color:var(--primary);"></i> Detalle de Novedad #<?= (int)($n['id'] ?? 0) ?>
    </h2>
    <a href="/horion-time/public/novedades" class="btn" style="background:var(--bg-body);">
        <i class="fas fa-arrow-left"></i> Volver al Listado
    </a>
</div>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:24px;">
    <!-- Información principal -->
    <div class="card-3d">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
            <h3 style="font-weight:800;"><?= htmlspecialchars($tipos[$n['tipo']] ?? ucfirst(str_replace('_', ' ', $n['tipo'] ?? ''))) ?></h3>
            <span class="badge badge-<?= ($n['estado'] ?? '') === 'aprobada' ? 'success' : (($n['estado'] ?? '') === 'rechazada' ? 'danger' : 'warning') ?>">
                <?= htmlspecialchars(ucfirst($n['estado'] ?? 'pendiente')) ?>
            </span>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px;">
            <div style="background:var(--bg-body);padding:12px 14px;border-radius:10px;">
                <div style="font-size:.75rem;color:var(--text-muted);">Empleado</div>
                <div style="font-weight:700;"><?= htmlspecialchars($n['empleado_nombre'] ?? '—') ?></div>
                <div style="font-size:.8rem;color:var(--text-muted);"><?= htmlspecialchars($n['empleado_documento'] ?? '') ?></div>
            </div>
            <div style="background:var(--bg-body);padding:12px 14px;border-radius:10px;">
                <div style="font-size:.75rem;color:var(--text-muted);">Empresa</div>
                <div style="font-weight:700;"><?= htmlspecialchars($n['empresa_nombre'] ?? '—') ?></div>
            </div>
            <div style="background:var(--bg-body);padding:12px 14px;border-radius:10px;">
                <div style="font-size:.75rem;color:var(--text-muted);">Período</div>
                <div style="font-weight:700;">
                    <?= $n['fecha_inicio'] ? date('d/m/Y', strtotime($n['fecha_inicio'])) : '—' ?>
                    → <?= $n['fecha_fin'] ? date('d/m/Y', strtotime($n['fecha_fin'])) : '—' ?>
                </div>
            </div>
            <div style="background:var(--bg-body);padding:12px 14px;border-radius:10px;">
                <div style="font-size:.75rem;color:var(--text-muted);">Días / Horas</div>
                <div style="font-weight:700;"><?= (int)($n['dias'] ?? 0) ?> día(s) <?= !empty($n['horas']) ? '· ' . $n['horas'] . ' h' : '' ?></div>
            </div>
            <?php if (!empty($n['subtipo'])): ?>
            <div style="background:var(--bg-body);padding:12px 14px;border-radius:10px;">
                <div style="font-size:.75rem;color:var(--text-muted);">Subtipo</div>
                <div style="font-weight:700;text-transform:capitalize;"><?= htmlspecialchars(str_replace('_', ' ', $n['subtipo'])) ?></div>
            </div>
            <?php endif; ?>
            <div style="background:var(--bg-body);padding:12px 14px;border-radius:10px;">
                <div style="font-size:.75rem;color:var(--text-muted);">Solicitado</div>
                <div style="font-weight:700;"><?= $n['solicitado'] ? date('d/m/Y H:i', strtotime($n['solicitado'])) : '—' ?></div>
            </div>
        </div>
        <label class="form-label">Justificación</label>
        <div style="background:var(--bg-body);padding:14px;border-radius:10px;white-space:pre-wrap;min-height:80px;">
            <?= htmlspecialchars($n['justificacion'] ?? 'Sin justificación') ?>
        </div>
    </div>

    <!-- Panel lateral: documento y acciones -->
    <div style="display:flex;flex-direction:column;gap:24px;">
        <div class="card-3d">
            <h3 style="font-weight:800;margin-bottom:14px;"><i class="fas fa-paperclip" style="color:var(--primary);"></i> Documento Soporte</h3>
            <?php if (!empty($n['documento'])): ?>
                <?php if ($esImagen): ?>
                <img src="<?= htmlspecialchars($n['documento']) ?>" style="width:100%;border-radius:10px;border:1px solid var(--border);margin-bottom:12px;">
                <?php else: ?>
                <div style="background:var(--bg-body);padding:16px;border-radius:10px;text-align:center;margin-bottom:12px;">
                    <i class="fas fa-file-pdf" style="font-size:2.2rem;color:var(--danger);"></i>
                    <div style="font-size:.85rem;color:var(--text-muted);margin-top:6px;">Documento PDF</div>
                </div>
                <?php endif; ?>
                <a href="<?= htmlspecialchars($n['documento']) ?>" target="_blank" class="btn btn-primary" style="width:100%;justify-content:center;">
                    <i class="fas fa-external-link-alt"></i> Abrir Documento
                </a>
            <?php else: ?>
                <p style="color:var(--text-muted);text-align:center;padding:20px 0;">Sin documento adjunto</p>
            <?php endif; ?>
        </div>

        <?php if ($puedeProcesar && ($n['estado'] ?? '') === 'pendiente'): ?>
        <div class="card-3d">
            <h3 style="font-weight:800;margin-bottom:14px;"><i class="fas fa-gavel" style="color:var(--warning);"></i> Procesar Solicitud</h3>
            <div style="display:flex;flex-direction:column;gap:10px;">
                <a href="/horion-time/public/novedades/aprobar/<?= (int)$n['id'] ?>" class="btn btn-primary" style="justify-content:center;background:linear-gradient(135deg,var(--success),#166534);"
                   onclick="return confirm('¿Aprobar esta novedad?');">
                    <i class="fas fa-check"></i> Aprobar
                </a>
                <a href="/horion-time/public/novedades/rechazar/<?= (int)$n['id'] ?>" class="btn btn-danger" style="justify-content:center;"
                   onclick="return confirm('¿Rechazar esta novedad?');">
                    <i class="fas fa-times"></i> Rechazar
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../layouts/footer.php'; ?>