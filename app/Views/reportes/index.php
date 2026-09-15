<?php 
session_start();
include(__DIR__ . '/../layouts/header.php'); 
include(__DIR__ . '/../layouts/sidebar.php'); 
?>

<main class="main-content">
    <div class="top-header">
        <h2 style="font-weight: 700; color: var(--text-dark);">
            <i class="fas fa-file-export" style="color: var(--primary);"></i>
            Centro de Reportes
        </h2>
    </div>

    <div style="padding: 32px;">
        <?php if (isset($_GET['error'])): ?>
            <div class="card-3d" style="background: #fef2f2; border-left: 4px solid #dc2626; margin-bottom: 24px; color: #991b1b;">
                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($_GET['error']) ?>
            </div>
        <?php endif; ?>

        <div class="card-3d">
            <!-- Tabs de Reportes -->
            <div style="display: flex; gap: 8px; border-bottom: 2px solid var(--border); margin-bottom: 24px; overflow-x: auto;">
                <button class="tab-btn active" onclick="cambiarTab('asistencia', this)">📋 Asistencia General</button>
                <button class="tab-btn" onclick="cambiarTab('tardanzas', this)">⏰ Tardanzas</button>
                <button class="tab-btn" onclick="cambiarTab('horas_extras', this)">⏱️ Horas Extras</button>
                <button class="tab-btn" onclick="cambiarTab('incidencias', this)">⚠️ Incidencias</button>
                <button class="tab-btn" onclick="cambiarTab('consolidado', this)">📊 Consolidado Mensual</button>
            </div>

            <!-- Filtros -->
            <form action="/horion-time/public/reportes/generar" method="POST" id="formReporte" style="background: rgba(3, 169, 80, 0.03); padding: 20px; border-radius: 12px; margin-bottom: 24px;">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="tipo_reporte" id="inputTipo" value="asistencia">
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; align-items: end;">
                    <div>
                        <label class="form-label">Fecha Inicio</label>
                        <input type="date" name="fecha_inicio" required class="form-input" value="<?= date('Y-m-01') ?>">
                    </div>
                    <div>
                        <label class="form-label">Fecha Fin</label>
                        <input type="date" name="fecha_fin" required class="form-input" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div>
                        <label class="form-label">Empleado (Opcional)</label>
                        <select name="usuario_id" class="form-input">
                            <option value="">Todos los empleados</option>
                            <?php foreach ($empleados as $emp): ?>
                            <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['nombre_completo']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Formato de Exportación</label>
                        <select name="formato" class="form-input">
                            <option value="excel">📊 Excel (.xls)</option>
                            <option value="csv">📄 CSV (.csv)</option>
                            <option value="pdf">📕 PDF (Imprimir)</option>
                        </select>
                    </div>
                    <div>
                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            <i class="fas fa-download"></i> Generar y Exportar
                        </button>
                    </div>
                </div>
            </form>

            <!-- Preview de Tabla (Simulación de los primeros 10 registros) -->
            <h3 style="font-weight: 700; margin-bottom: 16px; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-eye" style="color: var(--primary);"></i> Vista Previa (Últimos registros)
            </h3>
            <div class="table-container" style="max-height: 400px; overflow-y: auto;">
                <table class="data-table" id="tablaPreview">
                    <thead>
                        <tr>
                            <th>Empleado</th>
                            <th>Fecha</th>
                            <th>Detalle 1</th>
                            <th>Detalle 2</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 32px; color: var(--text-muted);">
                                Seleccione los filtros y haga clic en "Generar y Exportar" para ver los datos.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<style>
.tab-btn {
    padding: 12px 20px; background: none; border: none; border-bottom: 3px solid transparent;
    font-weight: 600; color: var(--text-muted); cursor: pointer; transition: all 0.2s; white-space: nowrap;
}
.tab-btn:hover { color: var(--primary); background: rgba(3, 169, 80, 0.05); }
.tab-btn.active { color: var(--primary); border-bottom-color: var(--primary); }
.form-label { display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 6px; color: var(--text-muted); }
.form-input { width: 100%; padding: 10px 14px; border: 1px solid var(--border); border-radius: 10px; outline: none; transition: all 0.2s; }
.form-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(3, 169, 80, 0.1); }
</style>

<script>
function cambiarTab(tipo, btn) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('inputTipo').value = tipo;
    
    // Aquí se podría cargar una vista previa vía AJAX
    console.log('Tab cambiada a:', tipo);
}
</script>

<?php include(__DIR__ . '/../layouts/footer.php'); ?>