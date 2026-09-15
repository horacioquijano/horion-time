<?php 
session_start();
include(__DIR__ . '/../layouts/header.php'); 
include(__DIR__ . '/../layouts/sidebar.php'); 
?>

<main class="main-content">
    <div class="top-header">
        <h2 style="font-weight: 700; color: var(--text-dark);">
            <i class="fas fa-users" style="color: var(--primary);"></i>
            Equipo a Mi Cargo
        </h2>
        <div style="display: flex; gap: 12px; align-items: center;">
            <input type="date" value="<?= $_GET['fecha'] ?? date('Y-m-d') ?>" 
                   onchange="window.location.href='/horion-time/public/supervisor/equipo?fecha='+this.value"
                   style="padding: 8px 12px; border: 1px solid var(--border); border-radius: 8px;">
            <a href="/horion-time/public/supervisor" class="btn" style="background: #f1f5f9;">
                <i class="fas fa-arrow-left"></i> Volver al Panel
            </a>
        </div>
    </div>

    <div style="padding: 32px;">
        <!-- Resumen rápido -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 24px;">
            <div class="card-3d" style="padding: 16px; border-left: 4px solid var(--primary);">
                <div style="font-size: 0.85rem; color: var(--text-muted);">Total Equipo</div>
                <div style="font-size: 1.8rem; font-weight: 800;"><?= $resumen['total'] ?></div>
            </div>
            <div class="card-3d" style="padding: 16px; border-left: 4px solid var(--success);">
                <div style="font-size: 0.85rem; color: var(--text-muted);">Presentes</div>
                <div style="font-size: 1.8rem; font-weight: 800; color: var(--success);"><?= $resumen['presentes'] ?></div>
            </div>
            <div class="card-3d" style="padding: 16px; border-left: 4px solid var(--warning);">
                <div style="font-size: 0.85rem; color: var(--text-muted);">Tardanzas</div>
                <div style="font-size: 1.8rem; font-weight: 800; color: var(--warning);"><?= $resumen['tardanzas'] ?></div>
            </div>
            <div class="card-3d" style="padding: 16px; border-left: 4px solid var(--danger);">
                <div style="font-size: 0.85rem; color: var(--text-muted);">Ausentes</div>
                <div style="font-size: 1.8rem; font-weight: 800; color: var(--danger);"><?= $resumen['ausentes'] ?></div>
            </div>
        </div>

        <!-- Tabla del equipo -->
        <div class="card-3d">
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Empleado</th>
                            <th>Cargo / Dpto</th>
                            <th>Horario</th>
                            <th>Entrada</th>
                            <th>Salida</th>
                            <th>Estado</th>
                            <th style="text-align: right;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($equipo)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 48px; color: var(--text-muted);">
                                No tienes personal asignado directamente.
                            </td>
                        </tr>
                        <?php else: foreach ($equipo as $emp): 
                            $estado = 'sin_marcar';
                            $estadoLabel = 'Sin marcar';
                            $estadoColor = '#9e9e9e';
                            
                            if ($emp['entrada']) {
                                $estado = 'presente';
                                $estadoLabel = 'Presente';
                                $estadoColor = 'var(--success)';
                                
                                if ($emp['jornada_diurna_inicio'] && $emp['tolerancia_entrada']) {
                                    $horaLimite = strtotime($emp['jornada_diurna_inicio']) + ($emp['tolerancia_entrada'] * 60);
                                    if (strtotime($emp['entrada']) > $horaLimite) {
                                        $estado = 'tarde';
                                        $estadoLabel = 'Tarde';
                                        $estadoColor = 'var(--warning)';
                                    }
                                }
                            }
                        ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <img src="<?= !empty($emp['foto_perfil']) ? $emp['foto_perfil'] : 'https://ui-avatars.com/api/?name='.urlencode($emp['nombre_completo']).'&background=03a950&color=fff&size=40' ?>" 
                                         style="width: 40px; height: 40px; border-radius: 50%;">
                                    <div>
                                        <div style="font-weight: 600;"><?= htmlspecialchars($emp['nombre_completo']) ?></div>
                                        <div style="font-size: 0.8rem; color: var(--text-muted);"><?= htmlspecialchars($emp['identificacion']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div style="font-size: 0.9rem;"><?= htmlspecialchars($emp['cargo'] ?? 'N/A') ?></div>
                                <div style="font-size: 0.8rem; color: var(--text-muted);"><?= htmlspecialchars($emp['departamento'] ?? 'N/A') ?></div>
                            </td>
                            <td style="font-size: 0.85rem;"><?= htmlspecialchars($emp['horario_nombre'] ?? 'Sin asignar') ?></td>
                            <td style="font-family: monospace; font-weight: 600;">
                                <?= $emp['entrada'] ? substr($emp['entrada'], 0, 5) : '--:--' ?>
                            </td>
                            <td style="font-family: monospace; font-weight: 600;">
                                <?= $emp['salida'] ? substr($emp['salida'], 0, 5) : '--:--' ?>
                            </td>
                            <td>
                                <span class="badge" style="background: <?= $estadoColor ?>20; color: <?= $estadoColor ?>;">
                                    <?= $estadoLabel ?>
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <a href="/horion-time/public/asistencia?usuario_id=<?= $emp['id'] ?>" class="btn" style="background: #f1f5f9; padding: 6px 10px; font-size: 0.8rem;">
                                    <i class="fas fa-eye"></i> Detalle
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<?php include(__DIR__ . '/../layouts/footer.php'); ?>