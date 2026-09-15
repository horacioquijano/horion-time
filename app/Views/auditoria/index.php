<?php 
session_start();
include(__DIR__ . '/../layouts/header.php'); 
include(__DIR__ . '/../layouts/sidebar.php'); 
?>

<main class="main-content">
    <div class="top-header">
        <h2 style="font-weight: 700; color: var(--text-dark);">
            <i class="fas fa-shield-alt" style="color: var(--primary);"></i>
            Auditoría y Logs del Sistema
        </h2>
        <span class="badge" style="background: rgba(229, 57, 53, 0.1); color: #e53935;">
            <i class="fas fa-lock"></i> Solo Lectura (Admin)
        </span>
    </div>

    <div style="padding: 32px;">
        <!-- Cards de Resumen -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 24px; margin-bottom: 32px;">
            <?php 
            $acciones_config = [
                'create' => ['icon' => 'fa-plus-circle', 'color' => 'var(--success)', 'label' => 'Creaciones'],
                'update' => ['icon' => 'fa-edit', 'color' => 'var(--info)', 'label' => 'Modificaciones'],
                'delete' => ['icon' => 'fa-trash-alt', 'color' => 'var(--danger)', 'label' => 'Eliminaciones'],
                'export' => ['icon' => 'fa-file-export', 'color' => 'var(--warning)', 'label' => 'Exportaciones']
            ];
            foreach ($acciones_config as $key => $cfg): 
                $total_accion = 0;
                foreach ($resumen as $r) { if ($r['accion'] === $key) $total_accion = $r['total']; }
            ?>
            <div class="card-3d" style="border-left: 4px solid <?= $cfg['color'] ?>;">
                <div style="color: var(--text-muted); font-size: 0.9rem;">
                    <i class="fas <?= $cfg['icon'] ?>"></i> <?= $cfg['label'] ?> (30 días)
                </div>
                <div style="font-size: 2rem; font-weight: 800; color: <?= $cfg['color'] ?>; margin-top: 8px;">
                    <?= number_format($total_accion) ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Filtros Avanzados -->
        <div class="card-3d" style="margin-bottom: 24px;">
            <form method="GET" action="/horion-time/public/auditoria" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; align-items: end;">
                <div>
                    <label class="form-label">Acción</label>
                    <select name="accion" class="form-input">
                        <option value="">Todas</option>
                        <option value="create" <?= ($_GET['accion'] ?? '') === 'create' ? 'selected' : '' ?>>Creación</option>
                        <option value="update" <?= ($_GET['accion'] ?? '') === 'update' ? 'selected' : '' ?>>Modificación</option>
                        <option value="delete" <?= ($_GET['accion'] ?? '') === 'delete' ? 'selected' : '' ?>>Eliminación</option>
                        <option value="export" <?= ($_GET['accion'] ?? '') === 'export' ? 'selected' : '' ?>>Exportación</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Tabla Afectada</label>
                    <select name="tabla" class="form-input">
                        <option value="">Todas</option>
                        <option value="usuarios" <?= ($_GET['tabla'] ?? '') === 'usuarios' ? 'selected' : '' ?>>Usuarios</option>
                        <option value="empresas" <?= ($_GET['tabla'] ?? '') === 'empresas' ? 'selected' : '' ?>>Empresas</option>
                        <option value="registros_asistencia" <?= ($_GET['tabla'] ?? '') === 'registros_asistencia' ? 'selected' : '' ?>>Asistencia</option>
                        <option value="novedades" <?= ($_GET['tabla'] ?? '') === 'novedades' ? 'selected' : '' ?>>Novedades</option>
                        <option value="horas_extras" <?= ($_GET['tabla'] ?? '') === 'horas_extras' ? 'selected' : '' ?>>Horas Extras</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Desde</label>
                    <input type="date" name="fecha_inicio" value="<?= $_GET['fecha_inicio'] ?? '' ?>" class="form-input">
                </div>
                <div>
                    <label class="form-label">Hasta</label>
                    <input type="date" name="fecha_fin" value="<?= $_GET['fecha_fin'] ?? '' ?>" class="form-input">
                </div>
                <div>
                    <label class="form-label">Búsqueda (Usuario/IP)</label>
                    <input type="text" name="busqueda" value="<?= $_GET['busqueda'] ?? '' ?>" placeholder="Ej: Juan, 192.168..." class="form-input">
                </div>
                <div>
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-filter"></i> Filtrar
                    </button>
                </div>
            </form>
        </div>

        <!-- Tabla de Logs -->
        <div class="card-3d">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <h3 style="font-weight: 700;">Registro de Actividades (<?= number_format($total) ?> total)</h3>
                <small style="color: var(--text-muted);">Mostrando página <?= $page ?> de <?= $totalPages ?: 1 ?></small>
            </div>

            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 40px;"></th>
                            <th>Fecha y Hora</th>
                            <th>Usuario</th>
                            <th>Acción</th>
                            <th>Tabla</th>
                            <th>ID Registro</th>
                            <th>IP / Dispositivo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 48px; color: var(--text-muted);">
                                <i class="fas fa-clipboard-check" style="font-size: 2rem; display: block; margin-bottom: 12px;"></i>
                                No se encontraron registros con los filtros aplicados
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($logs as $log): 
                            $accionColor = $log['accion'] === 'create' ? 'var(--success)' : 
                                           ($log['accion'] === 'delete' ? 'var(--danger)' : 
                                           ($log['accion'] === 'export' ? 'var(--warning)' : 'var(--info)'));
                        ?>
                        <tr style="cursor: pointer;" onclick="toggleLog(<?= $log['id'] ?>)">
                            <td><i class="fas fa-chevron-right" id="icon-<?= $log['id'] ?>" style="color: var(--text-muted); transition: transform 0.2s;"></i></td>
                            <td style="font-family: monospace; font-size: 0.9rem;"><?= date('d/m/Y H:i:s', strtotime($log['fecha_hora'])) ?></td>
                            <td>
                                <div style="font-weight: 600;"><?= htmlspecialchars($log['usuario_nombre'] ?? 'Sistema / API') ?></div>
                                <?php if (!empty($log['usuario_id_doc'])): ?>
                                <div style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($log['usuario_id_doc']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge" style="background: <?= $accionColor ?>20; color: <?= $accionColor ?>; text-transform: uppercase; font-weight: 700;">
                                    <?= htmlspecialchars($log['accion']) ?>
                                </span>
                            </td>
                            <td style="font-family: monospace; color: var(--text-muted);"><?= htmlspecialchars($log['tabla_afectada']) ?></td>
                            <td style="font-weight: 600;"><?= $log['registro_id'] ?? '-' ?></td>
                            <td>
                                <div style="font-size: 0.85rem;"><?= htmlspecialchars($log['ip_address']) ?></div>
                                <div style="font-size: 0.7rem; color: var(--text-muted); max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= htmlspecialchars($log['user_agent']) ?>">
                                    <?= htmlspecialchars(substr($log['user_agent'], 0, 50)) ?>...
                                </div>
                            </td>
                        </tr>
                        <!-- Fila Expandible para JSON -->
                        <tr id="log-detail-<?= $log['id'] ?>" style="display: none; background: #f8fafc;">
                            <td colspan="7" style="padding: 20px;">
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                                    <div>
                                        <h4 style="font-size: 0.85rem; font-weight: 700; color: var(--danger); margin-bottom: 8px; text-transform: uppercase;">
                                            <i class="fas fa-arrow-left"></i> Valores Anteriores
                                        </h4>
                                        <pre style="background: #1e1e24; color: #a5b3ce; padding: 12px; border-radius: 8px; font-size: 0.8rem; max-height: 200px; overflow-y: auto; margin: 0;"><code><?= $log['valores_anteriores'] ? htmlspecialchars(json_encode(json_decode($log['valores_anteriores']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) : 'null' ?></code></pre>
                                    </div>
                                    <div>
                                        <h4 style="font-size: 0.85rem; font-weight: 700; color: var(--success); margin-bottom: 8px; text-transform: uppercase;">
                                            Valores Nuevos <i class="fas fa-arrow-right"></i>
                                        </h4>
                                        <pre style="background: #1e1e24; color: #a5b3ce; padding: 12px; border-radius: 8px; font-size: 0.8rem; max-height: 200px; overflow-y: auto; margin: 0;"><code><?= $log['valores_nuevos'] ? htmlspecialchars(json_encode(json_decode($log['valores_nuevos']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) : 'null' ?></code></pre>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <?php if ($totalPages > 1): ?>
            <div style="display: flex; justify-content: center; gap: 8px; margin-top: 24px;">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?= $i ?>&<?= http_build_query(array_filter($_GET, fn($k) => $k !== 'page')) ?>" 
                   class="btn" style="padding: 8px 14px; background: <?= $i === $page ? 'var(--primary)' : '#f1f5f9' ?>; color: <?= $i === $page ? 'white' : 'var(--text-dark)' ?>;">
                    <?= $i ?>
                </a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<style>
.form-label { display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 6px; color: var(--text-muted); }
.form-input { width: 100%; padding: 10px 14px; border: 1px solid var(--border); border-radius: 10px; outline: none; transition: all 0.2s; font-size: 0.95rem; }
.form-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(3, 169, 80, 0.1); }
pre code { font-family: 'Courier New', monospace; }
</style>

<script>
function toggleLog(id) {
    const detailRow = document.getElementById('log-detail-' + id);
    const icon = document.getElementById('icon-' + id);
    
    if (detailRow.style.display === 'none') {
        detailRow.style.display = 'table-row';
        icon.style.transform = 'rotate(90deg)';
        icon.style.color = 'var(--primary)';
    } else {
        detailRow.style.display = 'none';
        icon.style.transform = 'rotate(0deg)';
        icon.style.color = 'var(--text-muted)';
    }
}
</script>

<?php include(__DIR__ . '/../layouts/footer.php'); ?>