<?php
// Archivo: app/Views/horarios/panel_turnos.php
// Pestañas: Panel Mensual | Carga Masiva | Historial | Leyenda
$b = '/horion-time/public';
$diasMes = (int)date('t', mktime(0, 0, (int)$mes, 1, (int)$anio));

// Mapa codigo => fila de parametro (para colores/horas)
$paramMap = [];
foreach ($parametros as $p) $paramMap[strtoupper($p['codigo'])] = $p;
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
    <h2 style="font-weight:700;color:var(--text-dark);margin:0;">
        <i class="fas fa-calendar-week" style="color:var(--primary);"></i>
        Panel de Turnos y Carga Masiva
    </h2>
    <form method="GET" action="<?= $b ?>/horarios/panelTurnos" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
        <select name="mes" class="form-input" style="width:auto;">
            <?php foreach (['1'=>'Enero','2'=>'Febrero','3'=>'Marzo','4'=>'Abril','5'=>'Mayo','6'=>'Junio','7'=>'Julio','8'=>'Agosto','9'=>'Septiembre','10'=>'Octubre','11'=>'Noviembre','12'=>'Diciembre'] as $nm => $et): ?>
                <option value="<?= $nm ?>" <?= (int)$nm === (int)$mes ? 'selected' : '' ?>><?= $et ?></option>
            <?php endforeach; ?>
        </select>
        <input type="number" name="anio" value="<?= (int)$anio ?>" min="2020" max="2040" class="form-input" style="width:100px;">
        <button class="btn btn-primary" type="submit"><i class="fas fa-sync"></i> Ver</button>
    </form>
</div>

<!-- Pestañas -->
<div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;">
    <?php foreach (['panel'=>'🗓️ Panel Mensual','carga'=>'📥 Carga Masiva','historial'=>'🕘 Historial','leyenda'=>'🏷️ Leyenda'] as $k => $et): ?>
        <a href="<?= $b ?>/horarios/panelTurnos?tab=<?= $k ?>&mes=<?= (int)$mes ?>&anio=<?= (int)$anio ?>"
           class="btn <?= $tab === $k ? 'btn-primary' : '' ?>"
           style="padding:8px 16px;text-decoration:none;"><?= $et ?></a>
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
<!-- ============ PANEL MENSUAL ============ -->
<div class="card-3d" style="overflow-x:auto;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:8px;">
        <h3 style="margin:0;">Turnos programados — <?= htmlspecialchars(\DateTime::createFromFormat('!m', $mes)->format('F')) ?> <?= $anio ?></h3>
        <div style="display:flex;gap:10px;flex-wrap:wrap;font-size:.8rem;">
            <?php foreach ($parametros as $p): ?>
                <span style="display:inline-flex;align-items:center;gap:6px;">
                    <span style="width:14px;height:14px;border-radius:4px;background:<?= htmlspecialchars($p['color']) ?>;display:inline-block;"></span>
                    <?= htmlspecialchars($p['codigo']) ?> = <?= htmlspecialchars($p['nombre']) ?>
                </span>
            <?php endforeach; ?>
        </div>
    </div>

    <table class="data-table" style="min-width:1100px;font-size:.8rem;border-collapse:collapse;">
        <thead>
            <tr>
                <th style="min-width:180px;text-align:left;">Empleado</th>
                <th style="min-width:130px;text-align:left;">Servicio</th>
                <?php for ($d = 1; $d <= $diasMes; $d++): ?>
                    <th style="min-width:52px;text-align:center;"><?= $d ?></th>
                <?php endfor; ?>
                <th style="text-align:right;">Horas</th>
                <th style="text-align:right;">Dif. 176</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($panel)): ?>
            <tr><td colspan="<?= $diasMes + 4 ?>" style="text-align:center;padding:30px;color:var(--text-muted);">
                Sin turnos cargados para este mes. Usa la pestaña <b>📥 Carga Masiva</b> para subir el Excel.
            </td></tr>
        <?php endif; ?>

        <?php foreach ($panel as $uid => $row): ?>
            <tr>
                <td style="text-align:left;">
                    <strong><?= htmlspecialchars($row['usuario']['nombre_completo']) ?></strong><br>
                    <small style="color:var(--text-muted);"><?= htmlspecialchars($row['usuario']['identificacion']) ?></small>
                </td>
                <td style="text-align:left;">
                    <?php
                        $serv = '';
                        foreach ($row['dias'] as $c) { if (!empty($c['servicio'])) { $serv = $c['servicio']; break; } }
                        echo htmlspecialchars($serv);
                    ?>
                </td>
                <?php for ($d = 1; $d <= $diasMes; $d++):
                    $cel = $row['dias'][$d] ?? null;
                    $cod = $cel ? strtoupper($cel['codigo']) : '';
                    $color = $cod && isset($paramMap[$cod]) ? $paramMap[$cod]['color'] : '#f1f5f9';
                    $textoColor = $cod ? '#fff' : '#94a3b8';
                ?>
                    <td style="padding:2px;">
                        <select class="celda-turno"
                                data-uid="<?= $uid ?>" data-dia="<?= $d ?>"
                                style="width:100%;padding:4px 2px;border:none;border-radius:6px;text-align:center;font-weight:700;color:<?= $textoColor ?>;background:<?= $color ?>;cursor:pointer;">
                            <option value="" <?= $cod === '' ? 'selected' : '' ?>>·</option>
                            <?php foreach ($parametros as $p): ?>
                                <option value="<?= htmlspecialchars($p['codigo']) ?>" <?= $cod === strtoupper($p['codigo']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['codigo']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                <?php endfor; ?>
                <td style="text-align:right;"><strong><?= number_format($row['horas'], 1) ?></strong></td>
                <?php $dif = $row['horas'] - 176; ?>
                <td style="text-align:right;font-weight:700;color:<?= abs($dif) < 0.01 ? 'var(--success)' : '#dc2626' ?>;">
                    <?= number_format($dif, 1) ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php elseif ($tab === 'carga'): ?>
<!-- ============ CARGA MASIVA ============ -->
<div class="card-3d">
    <h3 style="margin-bottom:16px;"><i class="fas fa-file-excel" style="color:var(--primary);"></i> Subir horario mensual (formato Excel actual)</h3>
    <p style="color:var(--text-muted);font-size:.9rem;margin-bottom:16px;">
        Sube tu archivo <code>.xlsx</code> tal como lo llenas hoy. El sistema detectará automáticamente la fila de encabezado
        (<b>IDENTIFICACION</b>, <b>NOMBRES</b>, días 1-31), fusionará empleados repetidos (ej: URGENCIAS + MOVIL),
        convertirá <b>VACACIONES</b> en letra <b>V</b> y marcará los textos libres como observaciones.
    </p>
    <form method="POST" action="<?= $b ?>/horarios/procesarCarga" enctype="multipart/form-data" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;align-items:end;">
        <input type="hidden" name="stage" value="preview">
        <input type="hidden" name="mes" value="<?= (int)$mes ?>">
        <input type="hidden" name="anio" value="<?= (int)$anio ?>">
        <div>
            <label class="form-label">Mes</label>
            <input type="text" class="form-input" value="<?= htmlspecialchars(\DateTime::createFromFormat('!m', $mes)->format('F')) ?> <?= $anio ?>" disabled>
        </div>
        <div>
            <label class="form-label">Archivo .xlsx *</label>
            <input type="file" name="archivo" accept=".xlsx" class="form-input" required>
        </div>
        <div style="grid-column:1/-1;">
            <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i> Analizar y previsualizar</button>
        </div>
    </form>
</div>

<?php if (!empty($preview)): ?>
<div class="card-3d" style="margin-top:20px;overflow-x:auto;">
    <h3 style="margin-bottom:12px;">Vista previa (<?= count($preview) ?> filas leídas)</h3>
    <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:12px;">
        Revisa que las cédulas coincidan, los días detectados sean correctos y las observaciones sean coherentes antes de confirmar.
    </p>
    <table class="data-table" style="min-width:900px;font-size:.82rem;">
        <thead>
            <tr>
                <th>Cédula</th>
                <th>Nombre</th>
                <th>Servicio</th>
                <th>Acción</th>
                <th>Días</th>
                <th>Horas</th>
                <th>Observaciones</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($preview as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['identificacion'] ?: '—') ?></td>
                <td><?= htmlspecialchars($row['nombre']) ?></td>
                <td><?= htmlspecialchars($row['servicio']) ?></td>
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
                <td style="color:#92400e;font-size:.78rem;">
                    <?= htmlspecialchars(implode(' · ', array_slice($row['avisos'], 0, 3))) ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <form method="POST" action="<?= $b ?>/horarios/procesarCarga" style="margin-top:16px;padding:16px;background:#f8fafc;border-radius:10px;display:flex;gap:20px;flex-wrap:wrap;align-items:center;">
        <input type="hidden" name="stage" value="confirm">
        <input type="hidden" name="mes" value="<?= (int)$mes ?>">
        <input type="hidden" name="anio" value="<?= (int)$anio ?>">
        <label style="display:flex;gap:6px;align-items:center;cursor:pointer;">
            <input type="checkbox" name="crear_usuarios" value="1">
            <span>Crear empleados faltantes (clave inicial <code>Horion2026.</code>)</span>
        </label>
        <label style="display:flex;gap:6px;align-items:center;cursor:pointer;">
            <input type="checkbox" name="limpiar_mes" value="1" checked>
            <span>Reemplazar turnos existentes del mes</span>
        </label>
        <button class="btn btn-primary" type="submit" onclick="return confirm('¿Confirmar y aplicar la carga? Esta acción quedará registrada en el historial.')">
            <i class="fas fa-check-circle"></i> Confirmar y aplicar carga
        </button>
    </form>
</div>
<?php endif; ?>

<?php elseif ($tab === 'historial'): ?>
<!-- ============ HISTORIAL ============ -->
<div class="card-3d" style="overflow-x:auto;">
    <h3 style="margin-bottom:12px;">Últimas cargas masivas</h3>
    <table class="data-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Mes/Año</th>
                <th>Archivo</th>
                <th>OK</th>
                <th>Obs.</th>
                <th>Cargado por</th>
                <th>Fecha</th>
                <th>Estado</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($historial)): ?>
            <tr><td colspan="9" style="text-align:center;color:var(--text-muted);padding:24px;">Sin cargas registradas</td></tr>
        <?php endif; ?>
        <?php foreach ($historial as $h): ?>
            <tr>
                <td><?= (int)$h['id'] ?></td>
                <td><?= str_pad($h['mes'], 2, '0', STR_PAD_LEFT) ?>/<?= $h['anio'] ?></td>
                <td><?= htmlspecialchars($h['archivo_nombre']) ?></td>
                <td><?= (int)$h['filas_ok'] ?></td>
                <td><?= (int)$h['filas_error'] ?></td>
                <td><?= htmlspecialchars($h['cargado_por'] ?? '') ?></td>
                <td><?= htmlspecialchars($h['creado_en']) ?></td>
                <td>
                    <span class="badge badge-<?= $h['estado'] === 'procesado' ? 'success' : 'danger' ?>">
                        <?= htmlspecialchars($h['estado']) ?>
                    </span>
                </td>
                <td>
                    <?php if ($h['estado'] === 'procesado'): ?>
                        <a class="btn" style="padding:4px 10px;background:#fef2f2;color:#991b1b;text-decoration:none;"
                           href="<?= $b ?>/horarios/revertirCarga/<?= (int)$h['id'] ?>"
                           onclick="return confirm('¿Revertir este lote? Se borrarán todos los turnos que creó.')">
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
<!-- ============ LEYENDA PARAMETRIZABLE ============ -->
<div class="card-3d" style="overflow-x:auto;">
    <h3 style="margin-bottom:6px;">Leyenda de turnos</h3>
    <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:14px;">
        Define qué significa cada letra del Excel: horas, horario, color en el panel y si es descanso/vacación/nocturno.
    </p>
    <form method="POST" action="<?= $b ?>/horarios/parametrosTurnos">
        <table class="data-table" style="min-width:900px;">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Nombre</th>
                    <th>Entrada</th>
                    <th>Salida</th>
                    <th>Horas</th>
                    <th>Color</th>
                    <th>Descanso</th>
                    <th>Vacación</th>
                    <th>Nocturno</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($parametros as $i => $p): ?>
                <tr>
                    <input type="hidden" name="id[<?= $i ?>]" value="<?= (int)$p['id'] ?>">
                    <td><input name="codigo[<?= $i ?>]" value="<?= htmlspecialchars($p['codigo']) ?>" maxlength="10" style="width:70px;" class="form-input" required></td>
                    <td><input name="nombre[<?= $i ?>]" value="<?= htmlspecialchars($p['nombre']) ?>" class="form-input" required></td>
                    <td><input type="time" name="hora_entrada[<?= $i ?>]" value="<?= htmlspecialchars(substr($p['hora_entrada'] ?? '', 0, 5)) ?>" class="form-input"></td>
                    <td><input type="time" name="hora_salida[<?= $i ?>]" value="<?= htmlspecialchars(substr($p['hora_salida'] ?? '', 0, 5)) ?>" class="form-input"></td>
                    <td><input type="number" step="0.5" name="horas_trabajadas[<?= $i ?>]" value="<?= htmlspecialchars($p['horas_trabajadas']) ?>" style="width:80px;" class="form-input"></td>
                    <td><input type="color" name="color[<?= $i ?>]" value="<?= htmlspecialchars($p['color']) ?>" style="width:50px;height:34px;border:none;cursor:pointer;"></td>
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
            <tr style="background:#f8fafc;">
                <td><input name="new_codigo" placeholder="Nueva" maxlength="10" style="width:70px;" class="form-input"></td>
                <td><input name="new_nombre" placeholder="Nombre de la letra" class="form-input"></td>
                <td><input type="time" name="new_entrada" class="form-input"></td>
                <td><input type="time" name="new_salida" class="form-input"></td>
                <td><input type="number" step="0.5" name="new_horas" value="0" style="width:80px;" class="form-input"></td>
                <td><input type="color" name="new_color" value="#64748b" style="width:50px;height:34px;border:none;cursor:pointer;"></td>
                <td colspan="3" style="color:var(--text-muted);font-size:.85rem;"><em>Agregar nueva letra a la leyenda (opcional)</em></td>
            </tr>
            </tbody>
        </table>
        <div style="margin-top:16px;display:flex;gap:10px;">
            <button class="btn btn-primary" type="submit"><i class="fas fa-save"></i> Guardar leyenda</button>
        </div>
    </form>
</div>
<?php endif; ?>

<style>
@media (max-width: 768px) {
    .data-table { font-size: .75rem; }
    .celda-turno { font-size: .7rem !important; padding: 3px 1px !important; }
}
</style>

<script>
// Guardar celda al cambiar el select del panel
document.querySelectorAll('.celda-turno').forEach(function (sel) {
    sel.addEventListener('change', function () {
        var fd = new FormData();
        fd.append('usuario_id', this.dataset.uid);
        fd.append('dia',        this.dataset.dia);
        fd.append('mes',        <?= (int)$mes ?>);
        fd.append('anio',       <?= (int)$anio ?>);
        fd.append('codigo',     this.value);
        fetch('<?= $b ?>/horarios/guardarCelda', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (j.ok) { location.reload(); }
                else { alert('Error: ' + (j.error || 'desconocido')); }
            })
            .catch(function (e) { alert('Error de red: ' + e); });
    });
});
</script>
