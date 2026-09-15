<?php include __DIR__ . '/../layouts/header.php';
$empresas = $empresas ?? [];
$sedesPorEmpresa = $sedesPorEmpresa ?? [];
$es_plataforma = $es_plataforma ?? (($_SESSION['rol_nombre'] ?? '') === 'SuperAdmin');
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
    <h2 style="font-weight:700;color:var(--text-dark);margin:0;">
        <i class="fas fa-building" style="color:var(--primary);"></i> Empresas y Sucursales
    </h2>
    <?php if ($es_plataforma): ?>
    <button class="btn btn-primary" onclick="openModal('modalCrearEmpresa')"><i class="fas fa-plus"></i> Crear Empresa</button>
    <?php endif; ?>
</div>

<?php if (isset($_GET['success'])): ?><div class="card-3d" style="background:#dcfce7;border-left:4px solid var(--success);margin-bottom:24px;color:#166534;"><i class="fas fa-check-circle"></i> Operación realizada con éxito.</div><?php endif; ?>
<?php if (isset($_GET['deleted'])): ?><div class="card-3d" style="background:#fef3c7;border-left:4px solid var(--warning);margin-bottom:24px;color:#92400e;"><i class="fas fa-trash"></i> Registro eliminado.</div><?php endif; ?>
<?php if (isset($_GET['error'])): ?><div class="card-3d" style="background:#fef2f2;border-left:4px solid #dc2626;margin-bottom:24px;color:#991b1b;"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($_GET['error']) ?></div><?php endif; ?>

<!-- TABLA DE EMPRESAS -->
<div class="card-3d">
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr><th>Empresa</th><th>NIT</th><th>Representante</th><th>Sucursales</th><th>Estado</th><th style="text-align:right;">Acciones</th></tr>
            </thead>
            <tbody>
            <?php if (empty($empresas)): ?>
                <tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:32px;">No hay empresas registradas</td></tr>
            <?php else: foreach ($empresas as $e): $eid = (int)$e['id']; $nSedes = count($sedesPorEmpresa[$eid] ?? []); ?>
                <tr>
                    <td><div style="font-weight:700;"><?= htmlspecialchars($e['nombre'] ?? '') ?></div><div style="font-size:.8rem;color:var(--text-muted);"><?= htmlspecialchars($e['ciudad'] ?? '') ?></div></td>
                    <td><?= htmlspecialchars($e['nit'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($e['representante_legal'] ?? '—') ?></td>
                    <td><span class="badge badge-info"><i class="fas fa-map-marker-alt"></i> <?= $nSedes ?></span></td>
                    <td><span class="badge badge-<?= ($e['estado'] ?? 'activo') === 'activo' ? 'success' : 'danger' ?>"><?= htmlspecialchars(ucfirst($e['estado'] ?? 'activo')) ?></span></td>
                    <td style="text-align:right;white-space:nowrap;">
                        <button class="btn" style="background:var(--primary-light);padding:6px 10px;" title="Ver/crear sucursales" onclick="openModal('modalSedes<?= $eid ?>')"><i class="fas fa-map-marker-alt"></i></button>
                        <button class="btn" style="background:#e0e7ff;padding:6px 10px;" title="Editar"
                            data-id="<?= $eid ?>"
                            data-nombre="<?= htmlspecialchars($e['nombre'] ?? '') ?>"
                            data-nit="<?= htmlspecialchars($e['nit'] ?? '') ?>"
                            data-tel="<?= htmlspecialchars($e['telefono'] ?? '') ?>"
                            data-rep="<?= htmlspecialchars($e['representante_legal'] ?? '') ?>"
                            data-dir="<?= htmlspecialchars($e['direccion'] ?? '') ?>"
                            data-ciudad="<?= htmlspecialchars($e['ciudad'] ?? '') ?>"
                            data-estado="<?= htmlspecialchars($e['estado'] ?? 'activo') ?>"
                            onclick="abrirEdicion(this)"><i class="fas fa-edit"></i></button>
                        <?php if ($es_plataforma): ?>
                        <button class="btn btn-danger" style="padding:6px 10px;" title="Eliminar"
                            onclick="if(confirm('¿Eliminar la empresa y todos sus datos?')) location='/horion-time/public/empresas/destroy/<?= $eid ?>'"><i class="fas fa-trash"></i></button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL CREAR EMPRESA + SUCURSALES -->
<div class="modal-overlay" id="modalCrearEmpresa">
    <div class="modal-content" style="max-width:760px;">
        <h3 style="font-weight:800;margin-bottom:20px;"><i class="fas fa-building" style="color:var(--primary);"></i> Crear Empresa</h3>
        <form action="/horion-time/public/empresas/store" method="POST">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div style="grid-column:1/-1;"><label class="form-label">Nombre *</label><input class="form-input" name="nombre" required></div>
                <div><label class="form-label">NIT</label><input class="form-input" name="nit"></div>
                <div><label class="form-label">Teléfono</label><input class="form-input" name="telefono"></div>
                <div style="grid-column:1/-1;"><label class="form-label">Representante Legal</label><input class="form-input" name="representante_legal"></div>
                <div style="grid-column:1/-1;"><label class="form-label">Dirección</label><input class="form-input" name="direccion"></div>
                <div><label class="form-label">Ciudad</label><input class="form-input" name="ciudad"></div>
                <div><label class="form-label">Estado</label><select class="form-input" name="estado"><option value="activo">Activo</option><option value="inactivo">Inactivo</option></select></div>
            </div>

            <!-- SECCIÓN SUCURSALES -->
            <div style="margin-top:22px;border:2px dashed var(--primary);border-radius:12px;padding:16px;background:var(--primary-light);">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                    <strong style="color:var(--primary);"><i class="fas fa-map-marker-alt"></i> Sucursales de la empresa</strong>
                    <button type="button" class="btn btn-primary" style="padding:6px 12px;" onclick="agregarSucursal()"><i class="fas fa-plus"></i> Agregar</button>
                </div>
                <p style="font-size:.8rem;color:var(--text-muted);margin-bottom:10px;">Opcional: agrega una o varias sedes. Quedarán ligadas automáticamente a esta empresa.</p>
                <div id="sucursalesContainer"></div>
            </div>

            <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:20px;">
                <button type="button" class="btn" style="background:var(--bg-body);" onclick="closeModal('modalCrearEmpresa')">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar Empresa y Sucursales</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDITAR EMPRESA -->
<div class="modal-overlay" id="modalEditarEmpresa">
    <div class="modal-content">
        <h3 style="font-weight:800;margin-bottom:20px;"><i class="fas fa-edit" style="color:var(--info);"></i> Editar Empresa</h3>
        <form id="formEditarEmpresa" action="/horion-time/public/empresas/update/0" method="POST">
            <input type="hidden" name="id" id="edit_id">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div style="grid-column:1/-1;"><label class="form-label">Nombre *</label><input class="form-input" name="nombre" id="edit_nombre" required></div>
                <div><label class="form-label">NIT</label><input class="form-input" name="nit" id="edit_nit"></div>
                <div><label class="form-label">Teléfono</label><input class="form-input" name="telefono" id="edit_tel"></div>
                <div style="grid-column:1/-1;"><label class="form-label">Representante Legal</label><input class="form-input" name="representante_legal" id="edit_rep"></div>
                <div style="grid-column:1/-1;"><label class="form-label">Dirección</label><input class="form-input" name="direccion" id="edit_dir"></div>
                <div><label class="form-label">Ciudad</label><input class="form-input" name="ciudad" id="edit_ciudad"></div>
                <div><label class="form-label">Estado</label><select class="form-input" name="estado" id="edit_estado"><option value="activo">Activo</option><option value="inactivo">Inactivo</option></select></div>
            </div>
            <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:20px;">
                <button type="button" class="btn" style="background:var(--bg-body);" onclick="closeModal('modalEditarEmpresa')">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Actualizar</button>
            </div>
        </form>
    </div>
</div>

<!-- MODALES DE SUCURSALES POR EMPRESA -->
<?php foreach ($empresas as $e): $eid = (int)$e['id']; ?>
<div class="modal-overlay" id="modalSedes<?= $eid ?>">
    <div class="modal-content">
        <h3 style="font-weight:800;margin-bottom:6px;"><i class="fas fa-map-marker-alt" style="color:var(--primary);"></i> Sucursales de <?= htmlspecialchars($e['nombre'] ?? '') ?></h3>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:16px;">Administra las sedes donde operan los empleados de esta empresa.</p>
        <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:20px;max-height:260px;overflow-y:auto;">
            <?php $sedes = $sedesPorEmpresa[$eid] ?? []; ?>
            <?php if (empty($sedes)): ?>
                <p style="text-align:center;color:var(--text-muted);padding:20px;">Esta empresa aún no tiene sucursales.</p>
            <?php else: foreach ($sedes as $s): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;background:var(--bg-body);padding:12px 14px;border-radius:10px;">
                    <div>
                        <div style="font-weight:700;"><?= htmlspecialchars($s['nombre']) ?></div>
                        <div style="font-size:.8rem;color:var(--text-muted);"><?= htmlspecialchars(trim(($s['ciudad'] ?? '') . ' ' . ($s['direccion'] ?? ''))) ?></div>
                    </div>
                    <button class="btn btn-danger" style="padding:6px 10px;"
                        onclick="if(confirm('¿Eliminar la sucursal?')) location='/horion-time/public/empresas/destroySede/<?= (int)$s['id'] ?>'"><i class="fas fa-trash"></i></button>
                </div>
            <?php endforeach; endif; ?>
        </div>
        <form action="/horion-time/public/empresas/storeSede" method="POST" style="border-top:1px solid var(--border);padding-top:16px;">
            <input type="hidden" name="empresa_id" value="<?= $eid ?>">
            <div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:10px;">
                <div><label class="form-label">Nombre sucursal *</label><input class="form-input" name="nombre" required placeholder="Ej: Sede Norte"></div>
                <div><label class="form-label">Ciudad</label><input class="form-input" name="ciudad"></div>
                <div><label class="form-label">Teléfono</label><input class="form-input" name="telefono"></div>
                <div style="grid-column:1/-1;"><label class="form-label">Dirección</label><input class="form-input" name="direccion"></div>
            </div>
            <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:14px;">
                <button type="button" class="btn" style="background:var(--bg-body);" onclick="closeModal('modalSedes<?= $eid ?>')">Cerrar</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Agregar Sucursal</button>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>

<script>
// Agrega filas dinámicas de sucursal al modal de creación
function agregarSucursal() {
    const cont = document.getElementById('sucursalesContainer');
    const row = document.createElement('div');
    row.style.cssText = 'display:grid;grid-template-columns:2fr 1fr 2fr 1fr auto;gap:8px;margin-bottom:8px;align-items:center;';
    row.innerHTML =
        '<input class="form-input" name="sede_nombre[]" placeholder="Nombre sucursal *">' +
        '<input class="form-input" name="sede_ciudad[]" placeholder="Ciudad">' +
        '<input class="form-input" name="sede_direccion[]" placeholder="Dirección">' +
        '<input class="form-input" name="sede_telefono[]" placeholder="Teléfono">' +
        '<button type="button" class="btn btn-danger" style="padding:8px 10px;" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>';
    cont.appendChild(row);
}

// Precarga el modal de edición con los datos de la fila
function abrirEdicion(btn) {
    document.getElementById('edit_id').value = btn.dataset.id;
    document.getElementById('edit_nombre').value = btn.dataset.nombre;
    document.getElementById('edit_nit').value = btn.dataset.nit;
    document.getElementById('edit_tel').value = btn.dataset.tel;
    document.getElementById('edit_rep').value = btn.dataset.rep;
    document.getElementById('edit_dir').value = btn.dataset.dir;
    document.getElementById('edit_ciudad').value = btn.dataset.ciudad;
    document.getElementById('edit_estado').value = btn.dataset.estado;
    document.getElementById('formEditarEmpresa').action = '/horion-time/public/empresas/update/' + btn.dataset.id;
    openModal('modalEditarEmpresa');
}
</script>
<?php include __DIR__ . '/../layouts/footer.php'; ?>