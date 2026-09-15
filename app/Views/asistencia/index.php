<?php
include __DIR__ . '/../layouts/header.php';
$marcaciones = $marcaciones ?? [];
$fechaFiltro = $_GET['fecha'] ?? date('Y-m-d');
$pendientes = count(array_filter($marcaciones, fn($m) => ($m['estado'] ?? '') === 'pendiente'));
$validadas  = count(array_filter($marcaciones, fn($m) => ($m['estado'] ?? '') === 'validado'));
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
    <h2 style="font-weight:700;color:var(--text-dark);margin:0;">
        <i class="fas fa-clipboard-list" style="color:var(--primary);"></i> Registro de Asistencia
    </h2>
    <form method="GET" action="/horion-time/public/asistencia" style="display:flex;gap:12px;">
        <input type="date" name="fecha" value="<?= htmlspecialchars($fechaFiltro) ?>" class="form-input" style="width:auto;">
        <button class="btn btn-primary" type="submit"><i class="fas fa-filter"></i> Filtrar</button>
    </form>
</div>

<?php if (isset($_GET['error'])): ?>
<div class="card-3d" style="background:#fef2f2;border-left:4px solid #dc2626;margin-bottom:24px;color:#991b1b;">
    <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($_GET['error']) ?>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:24px;margin-bottom:32px;">
    <div class="card-3d">
        <div style="color:var(--text-muted);font-size:.9rem;">Total Marcaciones</div>
        <div style="font-size:2rem;font-weight:800;color:var(--primary);"><?= count($marcaciones) ?></div>
    </div>
    <div class="card-3d">
        <div style="color:var(--text-muted);font-size:.9rem;">Pendientes de Revisión</div>
        <div style="font-size:2rem;font-weight:800;color:var(--warning);"><?= $pendientes ?></div>
    </div>
    <div class="card-3d">
        <div style="color:var(--text-muted);font-size:.9rem;">Validadas</div>
        <div style="font-size:2rem;font-weight:800;color:var(--success);"><?= $validadas ?></div>
    </div>
</div>

<div class="card-3d">
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr><th>Empleado</th><th>Fecha</th><th>Hora</th><th>Tipo</th><th>Método</th><th>Sede</th><th>Foto</th><th>Estado</th><th>Acciones</th></tr>
            </thead>
            <tbody>
                <?php if (empty($marcaciones)): ?>
                <tr><td colspan="9" style="text-align:center;color:var(--text-muted);padding:32px;">No hay marcaciones para la fecha seleccionada</td></tr>
                <?php else: ?>
                <?php foreach ($marcaciones as $m): ?>
                <tr>
                    <td>
                        <div style="font-weight:600;"><?= htmlspecialchars($m['nombre_completo']) ?></div>
                        <div style="font-size:.8rem;color:var(--text-muted);"><?= htmlspecialchars($m['identificacion']) ?></div>
                    </td>
                    <td><?= date('d/m/Y', strtotime($m['fecha'])) ?></td>
                    <td style="font-family:monospace;font-weight:600;"><?= htmlspecialchars($m['hora_registro']) ?></td>
                    <td><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $m['tipo_marcacion']))) ?></td>
                    <td><span class="badge" style="background:#e0e7ff;color:#3730a3;"><i class="fas fa-<?= $m['metodo_marcacion'] === 'foto' ? 'camera' : ($m['metodo_marcacion'] === 'qr' ? 'qrcode' : 'fingerprint') ?>"></i> <?= htmlspecialchars(ucfirst($m['metodo_marcacion'])) ?></span></td>
                    <td style="font-size:.9rem;"><?= htmlspecialchars($m['sede_nombre'] ?? 'N/A') ?></td>
                    <td>
                        <?php if (!empty($m['foto_evidencia'])): ?>
                        <img src="<?= htmlspecialchars($m['foto_evidencia']) ?>" style="width:40px;height:40px;border-radius:8px;object-fit:cover;cursor:pointer;border:2px solid var(--primary);" onclick="verFoto('<?= htmlspecialchars($m['foto_evidencia']) ?>')">
                        <?php else: ?><span style="color:var(--text-muted);">—</span><?php endif; ?>
                    </td>
                    <td><span class="badge badge-<?= $m['estado'] === 'validado' ? 'success' : ($m['estado'] === 'pendiente' ? 'warning' : 'danger') ?>"><?= htmlspecialchars(ucfirst($m['estado'])) ?></span></td>
                    <td>
                        <?php if (in_array($_SESSION['rol_nombre'] ?? '', ['SuperAdmin', 'Admin_Empresa', 'RRHH'])): ?>
                        <button class="btn" style="background:var(--bg-body);padding:6px 10px;" onclick="corregirMarcacion(<?= (int)$m['id'] ?>)"><i class="fas fa-edit"></i></button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function verFoto(url){ window.open(url,'_blank'); }
function corregirMarcacion(id){
    const nuevoEstado = prompt('Ingrese nuevo estado (validado/pendiente/rechazado):');
    if (nuevoEstado) window.location.href = '/horion-time/public/asistencia/corregir/' + id + '?estado=' + nuevoEstado;
}
</script>
<?php include __DIR__ . '/../layouts/footer.php'; ?>