<?php
// app/Views/horarios/panel_turnos.php
include __DIR__ . '/../layouts/header.php';

$b       = '/horion-time/public';
$mes     = (int)($mes ?? date('n'));
$anio    = (int)($anio ?? date('Y'));
$tab     = $tab ?? 'panel';
$diasMes = (int)date('t', mktime(0, 0, $mes, 1, $anio));

$mesesEs = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',
            7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];

$paramMap = [];
foreach (($parametros ?? []) as $p) $paramMap[strtoupper($p['codigo'])] = $p;

// KPIs del panel
$kpiEmpleados = count($panel ?? []);
$kpiDias = 0; $kpiHoras = 0.0;
foreach (($panel ?? []) as $row) { $kpiDias += count($row['dias']); $kpiHoras += $row['horas']; }
?>

<div style="padding: 4px 0 28px;">

    <!-- ===== Encabezado ===== -->
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:12px;">
        <div>
            <h2 style="font-weight:800;color:var(--text-dark);margin:0;font-size:1.35rem;">
                <i class="fas fa-calendar-week" style="color:var(--primary);"></i>
                Panel de Turnos y Carga Masiva
            </h2>
            <p style="color:var(--text-muted);font-size:.85rem;margin:4px 0 0 0;">
                Programación mensual del personal · <?= $mesesEs[$mes] ?> <?= $anio ?>
            </p>
        </div>
        <form method="GET" action="<?= $b ?>/horarios/panelTurnos" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
            <select name="mes" class="form-input" style="width:auto;">
                <?php foreach ($mesesEs as $nm => $et): ?>
                    <option value="<?= $nm ?>" <?= $nm === $mes ? 'selected' : '' ?>><?= $et ?></option>
                <?php endforeach; ?>
            </select>
            <input type="number" name="anio" value="<?= $anio ?>" min="2020" max="2040" class="form-input" style="width:96px;">
            <button class="btn btn-primary" type="submit"><i class="fas fa-sync"></i> Ver</button>
        </form>
    </div>

    <!-- ===== Tabs ===== -->
    <div class="pt-tabs">
        <?php foreach (['panel'=>'🗓️ Panel Mensual','carga'=>'📥 Carga Masiva','historial'=>'🕘 Historial','leyenda'=>'🏷️ Leyenda'] as $k => $et): ?>
            <a class="pt-tab <?= $tab === $k ? 'active' : '' ?>"
               href="<?= $b ?>/horarios/panelTurnos?tab=<?= $k ?>&mes=<?= $mes ?>&anio=<?= $anio ?>"><?= $et ?></a>
        <?php endforeach; ?>
    </div>

    <?php if (isset($_GET['msg'])): ?>
        <div class="card-3d" style="background:#dcfce7;border-left:4px solid var(--success);margin-bottom:16px;color:#166534;">
            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($_GET['msg']) ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="card-3d" style="background:#fef2f2;border-left:4px solid #dc2626;margin-bottom:16px;color:#991b1b;">
            <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($_GET['error']) ?>
        </div>
    <?php endif; ?>

    <?php if ($tab === 'panel'): ?>
    <!-- ================= PANEL MENSUAL ================= -->
    <?php if ($kpiEmpleados > 0): ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin-bottom:18px;">
        <div class="card-3d" style="padding:14px 18px;">
            <div style="font-size:.78rem;color:var(--text-muted);font-weight:600;">EMPLEADOS PROGRAMADOS</div>
            <div style="font-size:1.5rem;font-weight:800;color:var(--primary);"><?= $kpiEmpleados ?></div>
        </div>
        <div class="card-3d" style="padding:14px 18px;">
            <div style="font-size:.78rem;color:var(--text-muted);font-weight:600;">DÍAS PROGRAMADOS</div>
            <div style="font-size:1.5rem;font-weight:800;color:var(--text-dark);"><?= number_format($kpiDias) ?></div>
        </div>
        <div class="card-3d" style="padding:14px 18px;">
            <div style="font-size:.78rem;color:var(--text-muted);font-weight:600;">PROMEDIO HORAS / EMPLEADO</div>
            <div style="font-size:1.5rem;font-weight:800;color:var(--text-dark);"><?= $kpiEmpleados ? number_format($kpiHoras / $kpiEmpleados, 1) : '0.0' ?></div>
        </div>
    </div>
    <?php endif; ?>

    <div class="card-3d" style="padding:18px;overflow-x:auto;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:10px;">
            <h3 style="margin:0;font-weight:700;">Turnos programados — <?= $mesesEs[$mes] ?> <?= $anio ?></h3>
            <div class="pt-legend">
                <?php foreach ($parametros as $p): ?>
                    <span class="pt-chip"><i style="background:<?= htmlspecialchars($p['color']) ?>;"></i><?= htmlspecialchars($p['codigo']) ?> = <?= htmlspecialchars($p['nombre']) ?></span>
                <?php endforeach; ?>
            </div>
        </div>

        <table class="data-table pt-table">
            <thead>
                <tr>
                    <th style="min-width:190px;text-align:left;">Empleado</th>
                    <th style="min-width:130px;text-align:left;">Servicio</th>
                    <?php for ($d = 1; $d <= $diasMes; $d++): ?>
                        <th style="min-width:46px;text-align:center;"><?= $d ?></th>
                    <?php endfor; ?>
                    <th style="text-align:right;">Horas</th>
                    <th style="text-align:right;">Dif. 176</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($panel)): ?>
                <tr><td colspan="<?= $diasMes + 4 ?>" style="text-align:center;padding:36px;color:var(--text-muted);">
                    <i class="fas fa-inbox" style="font-size:1.6rem;display:block;margin-bottom:8px;opacity:.5;"></i>
                    Sin turnos cargados para este mes.<br>Usa la pestaña <b>📥 Carga Masiva</b> para subir el Excel.
                </td></tr>
            <?php endif; ?>

            <?php foreach (($panel ?? []) as $uid => $row): ?>
                <tr>
                    <td style="text-align:left;">
                        <strong><?= htmlspecialchars($row['usuario']['nombre_completo']) ?></strong><br>
                        <small style="color:var(--text-muted);"><?= htmlspecialchars($row['usuario']['identificacion']) ?></small>
                    </td>
                    <td style="text-align:left;">
                        <?php $serv = ''; foreach ($row['dias'] as $c) { if (!empty($c['servicio'])) { $serv = $c['servicio']; break; } } ?>
                        <span class="pt-chip"><?= htmlspecialchars($serv ?: '—') ?></span>
                    </td>
                    <?php for ($d = 1; $d <= $diasMes; $d++):
                        $cel = $row['dias'][$d] ?? null;
                        $cod = $cel ? strtoupper($cel['codigo']) : '';
                        $color = ($cod && isset($paramMap[$cod])) ? $paramMap[$cod]['color'] : '#f1f5f9';
                    ?>
                        <td style="padding:3px;">
                            <select class="celda-turno" data-uid="<?= $uid ?>" data-dia="<?= $d ?>"
                                title="<?= $cod ? htmlspecialchars($paramMap[$cod]['nombre'] ?? $cod) : 'Sin turno' ?>"
                                style="background:<?= $color ?>;color:<?= $cod ? '#fff' : '#94a3b8' ?>;">
                                <option value="" <?= $cod === '' ? 'selected' : '' ?>>·</option>
                                <?php foreach ($parametros as $p): ?>
                                    <option value="<?= htmlspecialchars($p['codigo']) ?>" <?= $cod === strtoupper($p['codigo']) ? 'selected' : '' ?>><?= htmlspecialchars($p['codigo']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    <?php endfor; ?>
                    <td style="text-align:right;font-weight:700;"><?= number_format($row['horas'], 1) ?></td>
                    <?php $dif = $row['horas'] - 176; ?>
                    <td style="text-align:right;">
                        <span class="pt-chip" style="color:<?= abs($dif) < 0.01 ? '#166534' : '#991b1b' ?>;font-weight:700;">
                            <?= ($dif > 0 ? '+' : '') . number_format($dif, 1) ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php elseif ($tab === 'carga'): ?>
    <!-- ================= CARGA MASIVA ================= -->
    <div class="card-3d" style="padding:22px;">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:6px;">
            <h3 style="margin:0;font-weight:700;"><i class="fas fa-file-excel" style="color:var(--primary);"></i> Subir horario mensual</h3>
            <a class="btn" style="background:rgba(3,169,80,.12);color:var(--primary);text-decoration:none;font-weight:700;"
               href="<?= $b ?>/horarios/descargarPlantilla?mes=<?= $mes ?>&anio=<?= $anio ?>">
                <i class="fas fa-download"></i> Descargar plantilla (.xlsx)
            </a>
        </div>
        <p style="color:var(--text-muted);font-size:.88rem;margin:0 0 18px 0;">
            Sube tu <code>.xlsx</code> tal como lo llenas hoy (o usa la plantilla descargable). El sistema detecta el encabezado
            (<b>IDENTIFICACION</b>, días 1-31), fusiona empleados repetidos (URGENCIAS + MOVIL), convierte <b>VACACIONES</b> en <b>V</b>
            y marca textos libres como observaciones.
        </p>
        <form method="POST" action="<?= $b ?>/horarios/procesarCarga" enctype="multipart/form-data"
              style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;align-items:end;">
            <input type="hidden" name="stage" value="preview">
            <input type="hidden" name="mes" value="<?= $mes ?>">
            <input type="hidden" name="anio" value="<?= $anio ?>">
            <div>
                <label class="form-label">Periodo a cargar</label>
                <input type="text" class="form-input" value="<?= $mesesEs[$mes] ?> <?= $anio ?>" disabled>
            </div>
            <div>
                <label class="form-label">Archivo .xlsx *</label>
                <input type="file" name="archivo" accept=".xlsx" class="form-input" required>
            </div>
            <div>
                <button class="btn btn-primary" type="submit" style="width:100%;"><i class="fas fa-search"></i> Analizar y previsualizar</button>
            </div>
        </form>
    </div>

    <?php if (!empty($preview)): ?>
    <div class="card-3d" style="margin-top:18px;padding:22px;overflow-x:auto;">
        <h3 style="margin:0 0 12px 0;font-weight:700;">Vista previa — <?= count($preview) ?> filas leídas</h3>
        <table class="data-table pt-table">
            <thead>
                <tr><th>Cédula</th><th>Nombre</th><th>Servicio</th><th>Acción</th><th>Días</th><th>Horas</th><th>Observaciones</th></tr>
            </thead>
            <tbody>
            <?php foreach ($preview as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['identificacion'] ?: '—') ?></td>
                    <td style="text-align:left;"><?= htmlspecialchars($row['nombre']) ?></td>
                    <td style="text-align:left;"><?= htmlspecialchars($row['servicio']) ?></td>
                    <td>
                        <?php if ($row['accion'] === 'actualizar'): ?>
                            <span class="badge badge-success">Actualizar</span>
                        <?php elseif ($row['accion'] === 'crear'): ?>
                            <span class="badge badge-warning">Crear empleado</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Omitir</span>
                        <?php endif; ?>
                    </td>
                    <td><?= count($row['dias']) ?></td>
                    <td><?= number_format($row['horas'] ?? 0, 1) ?></td>
                    <td style="color:#92400e;font-size:.78rem;text-align:left;"><?= htmlspecialchars(implode(' · ', array_slice($row['avisos'], 0, 3))) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <form method="POST" action="<?= $b ?>/horarios/procesarCarga"
              style="margin-top:16px;padding:16px;background:var(--bg-body,#f8fafc);border-radius:12px;display:flex;gap:22px;flex-wrap:wrap;align-items:center;">
            <input type="hidden" name="stage" value="confirm">
            <input type="hidden" name="mes" value="<?= $mes ?>">
            <input type="hidden" name="anio" value="<?= $anio ?>">
            <label style="display:flex;gap:8px;align-items:center;cursor:pointer;font-size:.88rem;">
                <input type="checkbox" name="crear_usuarios" value="1"> Crear empleados faltantes (clave <code>Horion2026.</code>)
            </label>
            <label style="display:flex;gap:8px;align-items:center;cursor:pointer;font-size:.88rem;">
                <input type="checkbox" name="limpiar_mes" value="1" checked> Reemplazar turnos existentes del mes
            </label>
            <button class="btn btn-primary" type="submit"
                    onclick="return confirm('¿Confirmar y aplicar la carga? Quedará registrada en el historial.')">
                <i class="fas fa-check-circle"></i> Confirmar y aplicar carga
            </button>
        </form>
    </div>
    <?php endif; ?>

    <?php elseif ($tab === 'historial'): ?>
    <!-- ================= HISTORIAL ================= -->
    <div class="card-3d" style="padding:22px;overflow-x:auto;">
        <h3 style="margin:0 0 14px 0;font-weight:700;">Últimas cargas masivas</h3>
        <table class="data-table pt-table">
            <thead>
                <tr><th>#</th><th>Mes/Año</th><th>Archivo</th><th>OK</th><th>Obs.</th><th>Cargado por</th><th>Fecha</th><th>Estado</th><th></th></tr>
            </thead>
            <tbody>
            <?php if (empty($historial)): ?>
                <tr><td colspan="9" style="text-align:center;color:var(--text-muted);padding:28px;">Sin cargas registradas todavía</td></tr>
            <?php endif; ?>
            <?php foreach (($historial ?? []) as $h): ?>
                <tr>
                    <td><?= (int)$h['id'] ?></td>
                    <td><?= str_pad($h['mes'], 2, '0', STR_PAD_LEFT) ?>/<?= $h['anio'] ?></td>
                    <td style="text-align:left;"><?= htmlspecialchars($h['archivo_nombre']) ?></td>
                    <td><span class="badge badge-success"><?= (int)$h['filas_ok'] ?></span></td>
                    <td><span class="badge badge-warning"><?= (int)$h['filas_error'] ?></span></td>
                    <td><?= htmlspecialchars($h['cargado_por'] ?? '') ?></td>
                    <td><?= htmlspecialchars($h['creado_en']) ?></td>
                    <td><span class="badge badge-<?= $h['estado'] === 'procesado' ? 'success' : 'danger' ?>"><?= htmlspecialchars($h['estado']) ?></span></td>
                    <td>
                        <?php if ($h['estado'] === 'procesado'): ?>
                            <a class="btn" style="padding:5px 12px;background:#fef2f2;color:#991b1b;text-decoration:none;"
                               href="<?= $b ?>/horarios/revertirCarga/<?= (int)$h['id'] ?>"
                               onclick="return confirm('¿Revertir este lote? Se borran todos los turnos que creó.')">
                                <i class="fas fa-undo"></i> Revertir
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php elseif ($tab === 'leyenda'): ?>
    <!-- ================= LEYENDA ================= -->
    <div class="card-3d" style="padding:22px;overflow-x:auto;">
        <h3 style="margin:0 0 6px 0;font-weight:700;">Leyenda de turnos</h3>
        <p style="color:var(--text-muted);font-size:.86rem;margin:0 0 16px 0;">
            Define qué significa cada letra del Excel: horario, horas, color en el panel y si es descanso / vacación / nocturno.
        </p>
        <form method="POST" action="<?= $b ?>/horarios/parametrosTurnos">
            <table class="data-table pt-table">
                <thead>
                    <tr><th>Código</th><th>Nombre</th><th>Entrada</th><th>Salida</th><th>Horas</th><th>Color</th><th>Descanso</th><th>Vacación</th><th>Nocturno</th></tr>
                </thead>
                <tbody>
                <?php foreach (($parametros ?? []) as $i => $p): ?>
                    <tr>
                        <input type="hidden" name="id[<?= $i ?>]" value="<?= (int)$p['id'] ?>">
                        <td><input name="codigo[<?= $i ?>]" value="<?= htmlspecialchars($p['codigo']) ?>" maxlength="10" style="width:70px;text-align:center;font-weight:800;" class="form-input" required></td>
                        <td><input name="nombre[<?= $i ?>]" value="<?= htmlspecialchars($p['nombre']) ?>" class="form-input" required></td>
                        <td><input type="time" name="hora_entrada[<?= $i ?>]" value="<?= htmlspecialchars(substr($p['hora_entrada'] ?? '', 0, 5)) ?>" class="form-input"></td>
                        <td><input type="time" name="hora_salida[<?= $i ?>]" value="<?= htmlspecialchars(substr($p['hora_salida'] ?? '', 0, 5)) ?>" class="form-input"></td>
                        <td><input type="number" step="0.5" name="horas_trabajadas[<?= $i ?>]" value="<?= htmlspecialchars($p['horas_trabajadas']) ?>" style="width:80px;" class="form-input"></td>
                        <td><input type="color" name="color[<?= $i ?>]" value="<?= htmlspecialchars($p['color']) ?>" style="width:52px;height:36px;border:none;cursor:pointer;background:transparent;"></td>
                        <td style="text-align:center;">
                            <input type="hidden" name="es_descanso[<?= $i ?>]" value="0">
                            <input type="checkbox" name="es_descanso[<?= $i ?>]" value="1" <?= $p['es_descanso'] ? 'checked' : '' ?>>
                        </td>
                        <td style="text-align:center;">
                            <input type="hidden" name="es_vacacion[<?= $i ?>]" value="0">
                            <input type="checkbox" name="es_vacacion[<?= $i ?>]" value="1" <?= $p['es_vacacion'] ? 'checked' : '' ?>>
                        </td>
                        <td style="text-align:center;">
                            <input type="hidden" name="recargo_nocturno[<?= $i ?>]" value="0">
                            <input type="checkbox" name="recargo_nocturno[<?= $i ?>]" value="1" <?= $p['recargo_nocturno'] ? 'checked' : '' ?>>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <tr style="background:var(--bg-body,#f8fafc);">
                    <td><input name="new_codigo" placeholder="Nueva" maxlength="10" style="width:70px;text-align:center;" class="form-input"></td>
                    <td><input name="new_nombre" placeholder="Nombre de la letra" class="form-input"></td>
                    <td><input type="time" name="new_entrada" class="form-input"></td>
                    <td><input type="time" name="new_salida" class="form-input"></td>
                    <td><input type="number" step="0.5" name="new_horas" value="0" style="width:80px;" class="form-input"></td>
                    <td><input type="color" name="new_color" value="#64748b" style="width:52px;height:36px;border:none;cursor:pointer;background:transparent;"></td>
                    <td colspan="3" style="color:var(--text-muted);font-size:.85rem;"><em>Agregar nueva letra (opcional)</em></td>
                </tr>
                </tbody>
            </table>
            <div style="margin-top:16px;">
                <button class="btn btn-primary" type="submit"><i class="fas fa-save"></i> Guardar leyenda</button>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>

<style>
.pt-tabs{display:flex;gap:6px;background:var(--bg-body,#f1f5f9);padding:6px;border-radius:14px;width:max-content;max-width:100%;overflow-x:auto;margin-bottom:20px;}
.pt-tab{padding:10px 18px;border-radius:10px;border:none;background:transparent;color:var(--text-muted);font-weight:600;cursor:pointer;text-decoration:none;font-size:.9rem;white-space:nowrap;transition:all .2s;}
.pt-tab:hover{color:var(--primary);}
.pt-tab.active{background:#fff;color:var(--primary);box-shadow:0 2px 10px rgba(0,0,0,.10);font-weight:800;}
.pt-legend{display:flex;gap:8px;flex-wrap:wrap;}
.pt-chip{display:inline-flex;align-items:center;gap:6px;background:#fff;border:1px solid var(--border,#e2e8f0);border-radius:999px;padding:4px 12px;font-size:.76rem;font-weight:600;color:var(--text-muted);white-space:nowrap;}
.pt-chip i{width:10px;height:10px;border-radius:50%;display:inline-block;flex:none;}
.pt-table{min-width:1100px;font-size:.8rem;border-collapse:separate;border-spacing:0;}
.pt-table thead th{position:sticky;top:0;background:#fff;z-index:2;box-shadow:0 1px 0 var(--border,#e2e8f0);}
.pt-table tbody tr:nth-child(even){background:var(--bg-body,#f8fafc);}
.pt-table tbody tr:hover{background:rgba(3,169,80,.06);}
.celda-turno{width:100%;padding:5px 2px;border:1px solid rgba(0,0,0,.06);border-radius:8px;text-align:center;font-weight:800;font-size:.78rem;cursor:pointer;transition:transform .1s;}
.celda-turno:hover{transform:scale(1.12);}
@media (max-width:768px){
    .pt-table{font-size:.72rem;}
    .celda-turno{font-size:.68rem;padding:3px 1px;}
    .pt-tabs{width:100%;}
}
</style>

<script>
document.querySelectorAll('.celda-turno').forEach(function (sel) {
    sel.addEventListener('change', function () {
        var fd = new FormData();
        fd.append('usuario_id', this.dataset.uid);
        fd.append('dia',        this.dataset.dia);
        fd.append('mes',        <?= $mes ?>);
        fd.append('anio',       <?= $anio ?>);
        fd.append('codigo',     this.value);
        fetch('<?= $b ?>/horarios/guardarCelda', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (j) { if (j.ok) { location.reload(); } else { alert('Error: ' + (j.error || 'desconocido')); } })
            .catch(function (e) { alert('Error de red: ' + e); });
    });
});
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
