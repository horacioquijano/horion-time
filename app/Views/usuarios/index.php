<?php include __DIR__ . '/../layouts/header.php';

// =====================================================================
// AUTO-ABASTECIMIENTO: garantiza sucursales y jefes aunque el
// controlador no los pase (consulta directa a la BD como respaldo)
// =====================================================================
try {
    if (empty($sedesPorEmpresa)) {
        $__pdo = new PDO('HORION_DSN, HORION_DB_USER, HORION_DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $sedesPorEmpresa = [];
        foreach ($__pdo->query("SELECT id, nombre, empresa_id FROM sedes ORDER BY empresa_id, nombre")->fetchAll(PDO::FETCH_ASSOC) as $__s) {
            $sedesPorEmpresa[(int)$__s['empresa_id']][] = $__s;
        }
    }
    if (empty($jefesPorEmpresa)) {
        $__pdo = $__pdo ?? new PDO('HORION_DSN, HORION_DB_USER, HORION_DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $jefesPorEmpresa = [];
        foreach ($__pdo->query("SELECT id, nombre_completo, cargo, empresa_id FROM usuarios WHERE es_jefe = 1 AND estado = 'activo' ORDER BY nombre_completo")->fetchAll(PDO::FETCH_ASSOC) as $__j) {
            $jefesPorEmpresa[(int)$__j['empresa_id']][] = $__j;
        }
    }
} catch (Throwable $e) { /* si falla, los selects quedan vacíos pero la página carga */ }

// Arrays para el JavaScript (siempre se construyen aquí)
$sedesJs = [];
foreach ($sedesPorEmpresa as $__eid => $__arr) {
    $sedesJs[$__eid] = array_map(fn($s) => ['id' => (int)$s['id'], 'nombre' => $s['nombre']], $__arr);
}
$jefesJs = [];
foreach ($jefesPorEmpresa as $__eid => $__arr) {
    $jefesJs[$__eid] = array_map(fn($j) => ['id' => (int)$j['id'], 'nombre' => $j['nombre_completo'] . (!empty($j['cargo']) ? ' (' . $j['cargo'] . ')' : '')], $__arr);
}

$usuarios = $usuarios ?? [];
$roles = $roles ?? [];
$es_super = $es_super ?? (($_SESSION['rol_nombre'] ?? '') === 'SuperAdmin');
$modo_global = $modo_global ?? false;
$empresa_destino = $empresa_destino ?? (int)($_SESSION['empresa_id'] ?? 0);
$empresas_lista = $empresas_lista ?? [];
$sedes_destino = $sedesPorEmpresa[$empresa_destino] ?? [];
$jefes_destino = $jefesPorEmpresa[$empresa_destino] ?? [];
// ===== PATCH: garantiza la clave 'nombre' en los jefes (elimina el warning de la línea 160) =====
foreach ($jefes_destino as &$__j) {
    $__j['nombre'] = $__j['nombre'] ?? $__j['nombre_completo'] ?? 'Sin nombre';
}
unset($__j);
foreach ($jefesPorEmpresa as &$__arr) {
    foreach ($__arr as &$__j) {
        $__j['nombre'] = $__j['nombre'] ?? $__j['nombre_completo'] ?? 'Sin nombre';
    }
    unset($__j);
}
unset($__arr);
// Reconstruye el JSON del JS con la misma etiqueta normalizada
$jefesJs = [];
foreach ($jefesPorEmpresa as $__eid => $__arr) {
    $jefesJs[$__eid] = array_map(
        fn($j) => ['id' => (int)$j['id'], 'nombre' => ($j['nombre'] ?? 'Sin nombre') . (!empty($j['cargo']) ? ' (' . $j['cargo'] . ')' : '')],
        $__arr
    );
}
// ===== FIN PATCH =====
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
    <h2 style="font-weight:700;color:var(--text-dark);margin:0;">
        <i class="fas fa-users-cog" style="color:var(--primary);"></i> Usuarios y Roles
    </h2>
    <button class="btn btn-primary" onclick="openModal('modalCrearUsuario')"><i class="fas fa-plus"></i> Crear Usuario</button>
</div>

<?php if (!$modo_global): ?>
<div class="card-3d" style="border-left:4px solid var(--primary);margin-bottom:20px;">
    <i class="fas fa-building" style="color:var(--primary);"></i>
    Trabajando sobre: <strong><?= htmlspecialchars($_SESSION['empresa_nombre'] ?? '—') ?></strong>.
    Los usuarios creados aquí verán solo esta empresa y sus sucursales.
</div>
<?php else: ?>
<div class="card-3d" style="border-left:4px solid var(--info);margin-bottom:20px;">
    <i class="fas fa-globe" style="color:var(--info);"></i>
    Modo global: elige la <strong>empresa destino</strong>; las sucursales y jefes se cargan automáticamente al seleccionarla.
</div>
<?php endif; ?>

<?php if (isset($_GET['success'])): ?><div class="card-3d" style="background:#dcfce7;border-left:4px solid var(--success);margin-bottom:20px;color:#166534;"><i class="fas fa-check-circle"></i> Operación realizada con éxito.</div><?php endif; ?>
<?php if (isset($_GET['deleted'])): ?><div class="card-3d" style="background:#fef3c7;border-left:4px solid var(--warning);margin-bottom:20px;color:#92400e;"><i class="fas fa-trash"></i> Usuario eliminado.</div><?php endif; ?>
<?php if (isset($_GET['error'])): ?><div class="card-3d" style="background:#fef2f2;border-left:4px solid #dc2626;margin-bottom:20px;color:#991b1b;"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($_GET['error']) ?></div><?php endif; ?>

<div class="card-3d">
    <div class="table-container">
        <table class="data-table">
            <thead><tr><th>Nombre</th><th>Email</th><th>Rol</th><th>Cargo</th><th>Jefe</th><th>Sucursal</th><?php if ($modo_global): ?><th>Empresa</th><?php endif; ?><th>Estado</th><th style="text-align:right;">Acciones</th></tr></thead>
            <tbody>
            <?php if (empty($usuarios)): ?>
                <tr><td colspan="10" style="text-align:center;color:var(--text-muted);padding:32px;">No hay usuarios en esta empresa</td></tr>
            <?php else: foreach ($usuarios as $u): ?>
                <tr>
                    <td><div style="font-weight:700;"><?= htmlspecialchars($u['nombre_completo'] ?? '') ?></div><div style="font-size:.8rem;color:var(--text-muted);"><?= htmlspecialchars($u['identificacion'] ?? '—') ?></div></td>
                    <td><?= htmlspecialchars($u['email'] ?? '—') ?></td>
                    <td><span class="badge badge-info"><?= htmlspecialchars($u['rol_nombre'] ?? 'Sin rol') ?></span></td>
                    <td><?= htmlspecialchars($u['cargo'] ?? '—') ?></td>
                    <td><?= ($u['es_jefe'] ?? 0) ? '<span class="badge badge-success"><i class="fas fa-user-tie"></i> Sí</span>' : '<span style="color:var(--text-muted);">No</span>' ?></td>
                    <td><?= htmlspecialchars($u['sede_nombre'] ?? '—') ?></td>
                    <?php if ($modo_global): ?><td><?= htmlspecialchars($u['empresa_nombre'] ?? '—') ?></td><?php endif; ?>
                    <td><span class="badge badge-<?= ($u['estado'] ?? 'activo') === 'activo' ? 'success' : 'danger' ?>"><?= htmlspecialchars(ucfirst($u['estado'] ?? 'activo')) ?></span></td>
                    <td style="text-align:right;white-space:nowrap;">
                        <button class="btn" style="background:#e0e7ff;padding:6px 10px;" title="Editar"
                            data-id="<?= (int)$u['id'] ?>"
                            data-empresa="<?= (int)($u['empresa_id'] ?? 0) ?>"
                            data-nombre="<?= htmlspecialchars($u['nombre_completo'] ?? '') ?>"
                            data-email="<?= htmlspecialchars($u['email'] ?? '') ?>"
                            data-identificacion="<?= htmlspecialchars($u['identificacion'] ?? '') ?>"
                            data-telefono="<?= htmlspecialchars($u['telefono'] ?? '') ?>"
                            data-cargo="<?= htmlspecialchars($u['cargo'] ?? '') ?>"
                            data-area="<?= htmlspecialchars($u['area'] ?? '') ?>"
                            data-rol="<?= (int)($u['rol_id'] ?? 0) ?>"
                            data-sede="<?= (int)($u['sede_id'] ?? 0) ?>"
                            data-jefe="<?= (int)($u['jefe_inmediato_id'] ?? 0) ?>"
                            data-esjefe="<?= (int)($u['es_jefe'] ?? 0) ?>"
                            data-estado="<?= htmlspecialchars($u['estado'] ?? 'activo') ?>"
                            onclick="abrirEdicion(this)"><i class="fas fa-edit"></i></button>
                        <?php if ($es_super || $empresa_destino === (int)($u['empresa_id'] ?? 0)): ?>
                        <button class="btn btn-danger" style="padding:6px 10px;" title="Eliminar"
                            onclick="if(confirm('¿Eliminar este usuario?')) location='/horion-time/public/usuarios/destroy/<?= (int)$u['id'] ?>'"><i class="fas fa-trash"></i></button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL CREAR -->
<div class="modal-overlay" id="modalCrearUsuario">
    <div class="modal-content" style="max-width:760px;">
        <h3 style="font-weight:800;margin-bottom:20px;"><i class="fas fa-user-plus" style="color:var(--primary);"></i> Crear Usuario</h3>
        <form action="/horion-time/public/usuarios/store" method="POST">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <?php if ($es_super && $modo_global): ?>
                <div style="grid-column:1/-1;">
                    <label class="form-label">Empresa destino *</label>
                    <select class="form-input" name="empresa_id" id="crear_empresa" required onchange="actualizarCrear()">
                        <option value="">Seleccione empresa...</option>
                        <?php foreach ($empresas_lista as $emp): ?>
                        <option value="<?= (int)$emp['id'] ?>"><?= htmlspecialchars($emp['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php else: ?>
                <div style="grid-column:1/-1;">
                    <label class="form-label">Empresa destino</label>
                    <input class="form-input" value="<?= htmlspecialchars($_SESSION['empresa_nombre'] ?? '—') ?>" disabled style="background:var(--primary-light);color:var(--primary);font-weight:700;">
                </div>
                <?php endif; ?>
                <div style="grid-column:1/-1;"><label class="form-label">Nombre Completo *</label><input class="form-input" name="nombre_completo" required></div>
                <div><label class="form-label">Email *</label><input type="email" class="form-input" name="email" required></div>
                <div><label class="form-label">Contraseña *</label><input type="password" class="form-input" name="password" required minlength="6"></div>
                <div><label class="form-label">Número de Cédula *</label><input class="form-input" name="identificacion" required></div>
                <div><label class="form-label">Teléfono</label><input class="form-input" name="telefono"></div>
                <div><label class="form-label">Cargo</label><input class="form-input" name="cargo"></div>
                <div><label class="form-label">Área</label><input class="form-input" name="area"></div>
                <div><label class="form-label">Rol *</label>
                    <select class="form-input" name="rol_id" required>
                        <option value="">Seleccione...</option>
                        <?php foreach ($roles as $r): ?><option value="<?= (int)$r['id'] ?>"><?= htmlspecialchars($r['nombre']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div><label class="form-label">Sucursal</label>
                    <select class="form-input" name="sede_id" id="crear_sede">
                        <option value="">Sin sede</option>
                        <?php foreach ($sedes_destino as $s): ?>
                        <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><label class="form-label">Jefe Inmediato</label>
                    <select class="form-input" name="jefe_inmediato_id" id="crear_jefe">
                        <option value="">Sin jefe</option>
                        <?php foreach ($jefes_destino as $j): ?>
                        <option value="<?= (int)$j['id'] ?>"><?= htmlspecialchars($j['nombre'] ?? $j['nombre_completo'] ?? 'Sin nombre') ?><?php if (!empty($j['cargo'])): ?> (<?= htmlspecialchars($j['cargo']) ?>)<?php endif; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><label class="form-label">Estado</label><select class="form-input" name="estado"><option value="activo">Activo</option><option value="inactivo">Inactivo</option></select></div>
                <div style="grid-column:1/-1;display:flex;align-items:center;gap:12px;padding:12px;background:var(--primary-light);border-radius:10px;">
                    <input type="checkbox" name="es_jefe" id="crear_es_jefe" style="width:20px;height:20px;cursor:pointer;">
                    <label for="crear_es_jefe" style="font-weight:600;color:var(--primary);cursor:pointer;margin:0;">Este usuario es Jefe Inmediato</label>
                </div>
            </div>
            <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:20px;">
                <button type="button" class="btn" style="background:var(--bg-body);" onclick="closeModal('modalCrearUsuario')">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDITAR -->
<div class="modal-overlay" id="modalEditarUsuario">
    <div class="modal-content" style="max-width:760px;">
        <h3 style="font-weight:800;margin-bottom:20px;"><i class="fas fa-user-edit" style="color:var(--info);"></i> Editar Usuario</h3>
        <form id="formEditarUsuario" action="/horion-time/public/usuarios/update/0" method="POST">
            <input type="hidden" name="id" id="edit_id">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div style="grid-column:1/-1;"><label class="form-label">Nombre Completo *</label><input class="form-input" name="nombre_completo" id="edit_nombre" required></div>
                <div><label class="form-label">Email *</label><input type="email" class="form-input" name="email" id="edit_email" required></div>
                <div><label class="form-label">Nueva Contraseña (vacío = no cambiar)</label><input type="password" class="form-input" name="password" minlength="6" placeholder="••••••"></div>
                <div><label class="form-label">Número de Cédula *</label><input class="form-input" name="identificacion" id="edit_identificacion" required></div>
                <div><label class="form-label">Teléfono</label><input class="form-input" name="telefono" id="edit_telefono"></div>
                <div><label class="form-label">Cargo</label><input class="form-input" name="cargo" id="edit_cargo"></div>
                <div><label class="form-label">Área</label><input class="form-input" name="area" id="edit_area"></div>
                <div><label class="form-label">Rol *</label>
                    <select class="form-input" name="rol_id" id="edit_rol" required>
                        <option value="">Seleccione...</option>
                        <?php foreach ($roles as $r): ?><option value="<?= (int)$r['id'] ?>"><?= htmlspecialchars($r['nombre']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div><label class="form-label">Sucursal</label><select class="form-input" name="sede_id" id="edit_sede"><option value="">Sin sede</option></select></div>
                <div><label class="form-label">Jefe Inmediato</label><select class="form-input" name="jefe_inmediato_id" id="edit_jefe"><option value="">Sin jefe</option></select></div>
                <div><label class="form-label">Estado</label><select class="form-input" name="estado" id="edit_estado"><option value="activo">Activo</option><option value="inactivo">Inactivo</option></select></div>
                <div style="grid-column:1/-1;display:flex;align-items:center;gap:12px;padding:12px;background:var(--primary-light);border-radius:10px;">
                    <input type="checkbox" name="es_jefe" id="edit_es_jefe" style="width:20px;height:20px;cursor:pointer;">
                    <label for="edit_es_jefe" style="font-weight:600;color:var(--primary);cursor:pointer;margin:0;">Este usuario es Jefe Inmediato</label>
                </div>
            </div>
            <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:20px;">
                <button type="button" class="btn" style="background:var(--bg-body);" onclick="closeModal('modalEditarUsuario')">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Actualizar</button>
            </div>
        </form>
    </div>
</div>

<script>
// Datos garantizados por la propia vista (respaldo directo a BD)
const SEDES = <?= json_encode($sedesJs) ?>;
const JEFES = <?= json_encode($jefesJs) ?>;

function llenarSelect(sel, items, placeholder, valor) {
    if (!sel) return;
    sel.innerHTML = '<option value="">' + placeholder + '</option>' +
        (items || []).map(i => '<option value="' + i.id + '">' + (i.nombre || '') + '</option>').join('');
    if (valor) sel.value = valor;
}

// Al elegir empresa destino: carga sus sucursales y jefes
function actualizarCrear() {
    const selEmp = document.getElementById('crear_empresa');
    if (!selEmp || !selEmp.value) return;
    const emp = selEmp.value;
    llenarSelect(document.getElementById('crear_sede'), SEDES[emp] || [], 'Sin sede');
    llenarSelect(document.getElementById('crear_jefe'), JEFES[emp] || [], 'Sin jefe');
    if ((SEDES[emp] || []).length === 0) {
        console.warn('HORION: la empresa ' + emp + ' no tiene sucursales creadas. Créalas en Empresas → Sucursales.');
    }
}

function abrirEdicion(btn) {
    document.getElementById('edit_id').value = btn.dataset.id;
    document.getElementById('edit_nombre').value = btn.dataset.nombre;
    document.getElementById('edit_email').value = btn.dataset.email;
    document.getElementById('edit_identificacion').value = btn.dataset.identificacion;
    document.getElementById('edit_telefono').value = btn.dataset.telefono;
    document.getElementById('edit_cargo').value = btn.dataset.cargo;
    document.getElementById('edit_area').value = btn.dataset.area;
    document.getElementById('edit_rol').value = btn.dataset.rol;
    document.getElementById('edit_es_jefe').checked = btn.dataset.esjefe === '1';
    document.getElementById('edit_estado').value = btn.dataset.estado;
    llenarSelect(document.getElementById('edit_sede'), SEDES[btn.dataset.empresa] || [], 'Sin sede', btn.dataset.sede);
    llenarSelect(document.getElementById('edit_jefe'), JEFES[btn.dataset.empresa] || [], 'Sin jefe', btn.dataset.jefe);
    document.getElementById('formEditarUsuario').action = '/horion-time/public/usuarios/update/' + btn.dataset.id;
    openModal('modalEditarUsuario');
}
</script>
<?php include __DIR__ . '/../layouts/footer.php'; ?>