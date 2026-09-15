<?php if (!defined('SIDEBAR_INCLUDED')) { define('SIDEBAR_INCLUDED', true);
$cp = $currentPage ?? '';
$b = '/horion-time/public';
$rol = $_SESSION['rol_nombre'] ?? 'Empleado';

$permisos = [
    'SuperAdmin'    => '*',
    'Admin_Empresa' => '*',
    'RRHH'          => ['dashboard','usuarios','asistencia','horarios','novedades','horas_extras','reportes','notificaciones','portal','empresas'],
    'Supervisor'    => ['dashboard','supervisor','asistencia','horarios','novedades','horas_extras','portal','notificaciones'],
    'Auditor'       => ['dashboard','reportes','auditoria','portal'],
    'Contador'      => ['dashboard','reportes','horas_extras','portal'],
    'Empleado'      => ['dashboard','asistencia','portal','horarios','novedades','horas_extras'],
];
$lista = $permisos[$rol] ?? $permisos['Empleado'];
$puede = function ($clave) use ($lista) { return $lista === '*' || in_array($clave, $lista, true); };
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="logo-icon"><i class="fas fa-clock"></i></div>
        <span class="logo-text">HORION TIME</span>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section">
            <span class="nav-title">Principal</span>
            <?php if ($puede('dashboard')): ?><a href="<?= $b ?>/dashboard" class="nav-item <?= $cp==='dashboard'?'active':'' ?>"><i class="fas fa-chart-pie"></i><span>Dashboard</span></a><?php endif; ?>
            <?php if ($puede('asistencia')): ?><a href="<?= $b ?>/asistencia/marcar" class="nav-item <?= $cp==='marcar'?'active':'' ?>"><i class="fas fa-fingerprint"></i><span>Mi Marcación</span></a><?php endif; ?>
            <?php if ($puede('portal')): ?><a href="<?= $b ?>/portal" class="nav-item <?= $cp==='portal'?'active':'' ?>"><i class="fas fa-user-circle"></i><span>Portal Empleado</span></a><?php endif; ?>
        </div>
        <?php if ($puede('empresas') || $puede('usuarios') || $puede('horarios')): ?>
        <div class="nav-section">
            <span class="nav-title">Gestión</span>
            <?php if ($puede('empresas')): ?><a href="<?= $b ?>/empresas" class="nav-item <?= $cp==='empresas'?'active':'' ?>"><i class="fas fa-building"></i><span>Empresas y Sucursales</span></a><?php endif; ?>
            <?php if ($puede('usuarios')): ?><a href="<?= $b ?>/usuarios" class="nav-item <?= $cp==='usuarios'?'active':'' ?>"><i class="fas fa-users-cog"></i><span>Usuarios y Roles</span></a><?php endif; ?>
            <?php if ($puede('horarios')): ?><a href="<?= $b ?>/horarios" class="nav-item <?= $cp==='horarios'?'active':'' ?>"><i class="fas fa-calendar-alt"></i><span>Horarios y Turnos</span></a><?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if ($puede('novedades') || $puede('horas_extras') || $puede('asistencia') || $puede('supervisor')): ?>
        <div class="nav-section">
            <span class="nav-title">Operaciones</span>
            <?php if ($puede('novedades')): ?><a href="<?= $b ?>/novedades" class="nav-item <?= $cp==='novedades'?'active':'' ?>"><i class="fas fa-exclamation-triangle"></i><span>Novedades</span></a><?php endif; ?>
            <?php if ($puede('horas_extras')): ?><a href="<?= $b ?>/horas_extras" class="nav-item <?= $cp==='horas_extras'?'active':'' ?>"><i class="fas fa-business-time"></i><span>Horas Extras</span></a><?php endif; ?>
            <?php if ($puede('asistencia')): ?><a href="<?= $b ?>/asistencia" class="nav-item <?= $cp==='asistencia'?'active':'' ?>"><i class="fas fa-clipboard-list"></i><span>Registro Asistencia</span></a><?php endif; ?>
            <?php if ($puede('supervisor')): ?><a href="<?= $b ?>/supervisor" class="nav-item <?= $cp==='supervisor'?'active':'' ?>"><i class="fas fa-user-tie"></i><span>Portal Supervisor</span></a><?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if ($puede('reportes') || $puede('auditoria') || $puede('notificaciones') || $lista === '*'): ?>
        <div class="nav-section">
            <span class="nav-title">Sistema</span>
            <?php if ($puede('reportes')): ?><a href="<?= $b ?>/reportes" class="nav-item <?= $cp==='reportes'?'active':'' ?>"><i class="fas fa-file-export"></i><span>Reportes</span></a><?php endif; ?>
            <?php if ($puede('auditoria')): ?><a href="<?= $b ?>/auditoria" class="nav-item <?= $cp==='auditoria'?'active':'' ?>"><i class="fas fa-shield-alt"></i><span>Auditoría</span></a><?php endif; ?>
            <?php if ($puede('notificaciones')): ?><a href="<?= $b ?>/notificaciones" class="nav-item <?= $cp==='notificaciones'?'active':'' ?>"><i class="fas fa-bell"></i><span>Notificaciones</span></a><?php endif; ?>
            <?php if ($lista === '*'): ?><a href="<?= $b ?>/configuracion" class="nav-item <?= $cp==='configuracion'?'active':'' ?>"><i class="fas fa-cogs"></i><span>Configuración</span></a><?php endif; ?>
            <?php if ($lista === '*'): ?><a href="<?= $b ?>/dispositivos" class="nav-item <?= $cp==='dispositivos'?'active':'' ?>"><i class="fas fa-tablet-alt"></i><span>Dispositivos</span></a><?php endif; ?>
        </div>
        <?php endif; ?>
    </nav>
    <div class="sidebar-footer">
        <a href="<?= $b ?>/portal/perfil" class="nav-item"><i class="fas fa-user-circle"></i><span>Mi Perfil</span></a>
        <a href="<?= $b ?>/logout" class="nav-item text-danger"><i class="fas fa-sign-out-alt"></i><span>Cerrar Sesión</span></a>
    </div>
</aside>
<?php } ?>