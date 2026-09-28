<?php
include __DIR__ . '/../layouts/header.php';
$b = '/horion-time/public';
$marcaciones = $marcaciones ?? [];
$sedes = $sedes ?? [];
$empresas = $empresas ?? [];
$modoGlobal = (bool)($_SESSION['modo_global'] ?? false);
$esGestion = in_array($_SESSION['rol_nombre'] ?? '', ['SuperAdmin', 'Admin_Empresa', 'RRHH'], true);

$desde    = $_GET['desde'] ?? $_GET['fecha'] ?? date('Y-m-d');
$hasta    = $_GET['hasta'] ?? $_GET['fecha'] ?? date('Y-m-d');
$q        = $_GET['q'] ?? '';
$estadoF  = $_GET['estado'] ?? '';
$sedeF    = $_GET['sede'] ?? '';
$empresaF = $_GET['empresa'] ?? '';

// KPIs
$total      = count($marcaciones);
$validadas  = count(array_filter($marcaciones, fn($m) => ($m['estado'] ?? '') === 'validado'));
$pendientes = count(array_filter($marcaciones, fn($m) => ($m['estado'] ?? '') === 'pendiente'));
$rechazadas = count(array_filter($marcaciones, fn($m) => ($m['estado'] ?? '') === 'rechazado'));
$fueraRango = count(array_filter($marcaciones, fn($m) => stripos((string)($m['observaciones'] ?? ''), 'FUERA DE RANGO') !== false));
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
    <h2 style="font-weight:700;color:var(--text-dark);margin:0;">
        <i class="fas fa-clipboard-list" style="color:var(--primary);"></i> Registro de Asistencia
    </h2>
    <a class="btn btn-primary" style="text-decoration:none;" href="<?= $b ?>/asistencia/exportar?<?= htmlspecialchars(http_build_query($_GET)) ?>">
        <i class="fas fa-file-excel"></i> Exportar CSV
    </a>
</div>

<?php if (isset($_GET['error'])): ?>
<div class="card-3d" style="background:#fef2f2;border-left:4px solid #dc2626;margin-bottom:20px;color:#991b1b;">
    <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($_GET['error']) ?>
</div>
<?php endif; ?>

<!-- ===== FILTROS ===== -->
<div class="card-3d" style="padding:16px 18px;margin-bottom:20px;">
    <form method="GET" action="<?= $b ?>/asistencia" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;align-items:end;">
        <div>
            <label class="form-label" style="font-size:.78rem;">Desde</label>
            <input type="date" name="desde" value="<?= htmlspecialchars($desde) ?>" class="form-input">
        </div>
        <div>
            <label class="form-label" style="font-size:.78rem;">Hasta</label>
            <input type="date" name="hasta" value="<?= htmlspecialchars($hasta) ?>" class="form-input">
        </div>
        <?php if ($modoGlobal && !empty($empresas)): ?>
        <div>
            <label class="form-label" style="font-size:.78rem;">Empresa</label>
            <select name="empresa" class="form-input">
                <option value="">Todas</option>
                <?php foreach ($empresas as $e): ?>
                <option value="<?= (int)$e['id'] ?>" <?= (string)$empresaF === (string)$e['id'] ? 'selected' : '' ?>><?= htmlspecialchars($e['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div>
            <label class="form-label" style="font-size:.78rem;">Sucursal</label>
            <select name="sede" class="form-input">
                <option value="">Todas</option>
                <?php foreach ($sedes as $s): ?>
                <option value="<?= (int)$s['id'] ?>" <?= (string)$sedeF === (string)$s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label" style="font-size:.78rem;">Estado</label>
            <select name="estado" class="form-input">
                <option value="">Todos</option>
                <option value="validado"   <?= $estadoF === 'validado' ? 'selected' : '' ?>>Validado</option>
                <option value="pendiente"  <?= $estadoF === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                <option value="rechazado"  <?= $estadoF === 'rechazado' ? 'selected' : '' ?>>Rechazado</option>
            </select>
        </div>
        <div style="grid-column:span 2;">
            <label class="form-label" style="font-size:.78rem;">Empleado (nombre o cédula)</label>
            <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" class="form-input" placeholder="Buscar…">
        </div>
        <div style="display:flex;gap:8px;">
            <button class="btn btn-primary" type="submit" style="flex:1;"><i class="fas fa-filter"></i> Filtrar</button>
            <a class="btn" style="background:var(--bg-body);text-decoration:none;" href="<?= $b ?>/asistencia">Limpiar</a>
        </div>
    </form>
</div>

<!-- ===== KPIs ===== -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;margin-bottom:20px;">
    <div class="card-3d" style="padding:14px 18px;">
        <div style="color:var(--text-muted);font-size:.8rem;font-weight:600;">TOTAL</div>
        <div style="font-size:1.7rem;font-weight:800;color:var(--primary);"><?= $total ?></div>
    </div>
    <div class="card-3d" style="padding:14px 18px;">
        <div style="color:var(--text-muted);font-size:.8rem;font-weight:600;">VALIDADAS</div>
        <div style="font-size:1.7rem;font-weight:800;color:var(--success);"><?= $validadas ?></div>
    </div>
    <div class="card-3d" style="padding:14px 18px;">
        <div style="color:var(--text-muted);font-size:.8rem;font-weight:600;">PENDIENTES</div>
        <div style="font-size:1.7rem;font-weight:800;color:var(--warning);"><?= $pendientes ?></div>
    </div>
    <div class="card-3d" style="padding:14px 18px;">
        <div style="color:var(--text-muted);font-size:.8rem;font-weight:600;">RECHAZADAS</div>
        <div style="font-size:1.7rem;font-weight:800;color:#dc2626;"><?= $rechazadas ?></div>
    </div>
    <div class="card-3d" style="padding:14px 18px;">
        <div style="color:var(--text-muted);font-size:.8rem;font-weight:600;">FUERA DE RANGO</div>
        <div style="font-size:1.7rem;font-weight:800;color:#92400e;"><?= $fueraRango ?></div>
    </div>
</div>

<!-- ===== TABLA ===== -->
<div class="card-3d" style="overflow-x:auto;">
    <table class="data-table" style="min-width:1150px;font-size:.83rem;">
        <thead>
            <tr>
                <th style="text-align:left;">Empleado</th>
                <th>Fecha / Hora</th>
                <th>Tipo</th>
                <th>Turno del día</th>
                <th>Método</th>
                <th style="text-align:left;">Sede / GPS</th>
                <th>Foto</th>
                <th>Estado</th>
                <th style="text-align:left;">Observaciones</th>
                <?php if ($esGestion): ?><th>Acciones</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($marcaciones)): ?>
            <tr><td colspan="10" style="text-align:center;color:var(--text-muted);padding:32px;">No hay marcaciones para los filtros seleccionados</td></tr>
        <?php else: foreach ($marcaciones as $m): 
            $obs = (string)($m['observaciones'] ?? '');
            $fuera = stripos($obs, 'FUERA DE RANGO') !== false;
            $sinGps = stripos($obs, '[SIN GPS]') !== false;
            
            // Tardanza vs turno programado (tolerancia 10 min; noche cruza medianoche)
            $tardanza = false;
            if (!empty($m['turno_entrada']) && ($m['tipo_marcacion'] ?? '') === 'entrada') {
                $tEnt = strtotime($m['fecha'] . ' ' . $m['turno_entrada']);
                $tMar = strtotime($m['fecha'] . ' ' . $m['hora_registro']);
                if ((int)substr($m['turno_entrada'], 0, 2) >= 12 && (int)substr($m['hora_registro'], 0, 2) < 12) $tMar += 86400;
                if ($tMar > $tEnt + 600) $tardanza = true;
            }
        ?>
            <tr>
                <td style="text-align:left;">
                    <div style="font-weight:600;"><?= htmlspecialchars($m['nombre_completo'] ?? '') ?></div>
                    <div style="font-size:.75rem;color:var(--text-muted);"><?= htmlspecialchars($m['identificacion'] ?? '') ?></div>
                </td>
                <td>
                    <?= date('d/m/Y', strtotime($m['fecha'])) ?><br>
                    <span style="font-family:monospace;font-weight:700;"><?= substr($m['hora_registro'], 0, 5) ?></span>
                    <?php if ($tardanza): ?><span class="badge" style="background:#fef3c7;color:#92400e;font-size:.68rem;">TARDANZA</span><?php endif; ?>
                </td>
                <td><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $m['tipo_marcacion']))) ?></td>
                <td>
                    <?php if (!empty($m['turno_codigo'])): ?>
                        <span class="badge" style="background:<?= htmlspecialchars($m['turno_color'] ?? '#64748b') ?>;color:#fff;font-weight:800;">
                            <?= htmlspecialchars($m['turno_codigo']) ?>
                        </span>
                        <div style="font-size:.72rem;color:var(--text-muted);">
                            <?= substr($m['turno_entrada'], 0, 5) ?>–<?= substr($m['turno_salida'], 0, 5) ?>
                            <?= $tardanza ? '⚠️' : '✅' ?>
                        </div>
                    <?php else: ?>
                        <span class="badge" style="background:#f1f5f9;color:#94a3b8;">Sin turno</span>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="badge" style="background:#e0e7ff;color:#3730a3;">
                        <i class="fas fa-<?= $m['metodo_marcacion'] === 'foto' ? 'camera' : ($m['metodo_marcacion'] === 'qr' ? 'qrcode' : 'fingerprint') ?>"></i>
                        <?= htmlspecialchars(ucfirst($m['metodo_marcacion'])) ?>
                    </span>
                </td>
                <td style="text-align:left;">
                    <div style="font-size:.85rem;"><?= htmlspecialchars($m['sede_nombre'] ?? 'N/A') ?></div>
                    <?php if (!empty($m['lat']) && !empty($m['lng'])): ?>
                        <a href="https://maps.google.com/?q=<?= $m['lat'] ?>,<?= $m['lng'] ?>" target="_blank" rel="noopener"
                           style="font-size:.72rem;font-weight:700;color:<?= $fuera ? '#dc2626' : 'var(--primary)' ?>;text-decoration:none;">
                            <i class="fas fa-map-marker-alt"></i> <?= $fuera ? 'Fuera de rango' : 'Ver en mapa' ?>
                        </a>
                    <?php elseif ($sinGps): ?>
                        <span style="font-size:.72rem;color:#92400e;">Sin GPS</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!empty($m['foto_evidencia'])): ?>
                    <img src="<?= htmlspecialchars($m['foto_evidencia']) ?>" style="width:40px;height:40px;border-radius:8px;object-fit:cover;cursor:pointer;border:2px solid var(--primary);" onclick="verFoto('<?= htmlspecialchars($m['foto_evidencia']) ?>')">
                    <?php else: ?><span style="color:var(--text-muted);">—</span><?php endif; ?>
                </td>
                <td>
                    <span class="badge badge-<?= $m['estado'] === 'validado' ? 'success' : ($m['estado'] === 'pendiente' ? 'warning' : 'danger') ?>">
                        <?= htmlspecialchars(ucfirst($m['estado'])) ?>
                    </span>
                </td>
                <td style="text-align:left;max-width:220px;">
                    <div style="font-size:.72rem;color:var(--text-muted);white-space:normal;" title="<?= htmlspecialchars($obs) ?>">
                        <?= htmlspecialchars(mb_strimwidth($obs, 0, 90, '…')) ?>
                    </div>
                </td>
                <?php if ($esGestion): ?>
                <td style="white-space:nowrap;">
                    <button class="btn" style="background:#dcfce7;color:#166534;padding:5px 9px;" title="Validar" onclick="accion(<?= (int)$m['id'] ?>,'validado')"><i class="fas fa-check"></i></button>
                    <button class="btn" style="background:#fef2f2;color:#991b1b;padding:5px 9px;" title="Rechazar" onclick="accion(<?= (int)$m['id'] ?>,'rechazado')"><i class="fas fa-times"></i></button>
                    <button class="btn" style="background:var(--bg-body);padding:5px 9px;" title="Otro estado" onclick="accion(<?= (int)$m['id'] ?>,'pendiente')"><i class="fas fa-edit"></i></button>
                </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<script>
function verFoto(url){ window.open(url,'_blank'); }

function accion(id, estado) {
    const verbo = estado === 'validado' ? 'VALIDAR' : (estado === 'rechazado' ? 'RECHAZAR' : 'marcar como PENDIENTE');
    const motivo = prompt('Motivo para ' + verbo + ' (obligatorio):');
    if (motivo === null) return;
    if (motivo.trim() === '') { alert('⚠️ El motivo es obligatorio.'); return; }
    window.location.href = '<?= $b ?>/asistencia/corregir/' + id + '?estado=' + estado + '&motivo=' + encodeURIComponent(motivo);
}
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
