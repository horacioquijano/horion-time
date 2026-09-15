<?php if (session_status() === PHP_SESSION_NONE) session_start();
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$_SESSION['rol'] = $_SESSION['rol'] ?? ($_SESSION['rol_nombre'] ?? 'Admin');
$esSuper = (($_SESSION['rol_nombre'] ?? '') === 'SuperAdmin');
$empresas_lista = $_SESSION['empresas_lista'] ?? [];
$empresa_actual = $_SESSION['empresa_id'] ?? null;
$modo_global = (bool)($_SESSION['modo_global'] ?? true);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HORION TIME | <?= htmlspecialchars($pageTitle ?? 'Control de Asistencia') ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="<?= $basePath ?>/assets/css/horion-style.css">
</head>
<body class="theme-light">
<div class="app-container">
<?php include __DIR__ . '/sidebar.php'; ?>
<main class="main-content">
<header class="top-header">
<div class="header-left">
<button class="btn-icon" id="toggleSidebar" title="Colapsar menú"><i class="fas fa-bars"></i></button>
<div class="breadcrumb">
<i class="fas fa-home" style="color: var(--primary);"></i>
<span class="separator">/</span>
<span class="current-page"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></span>
</div>
</div>
<div class="header-right">
<?php if ($esSuper && !empty($empresas_lista)): ?>
<select onchange="location='<?= $basePath ?>/cambiarEmpresa?id='+this.value"
        title="Filtro de empresa (SuperAdmin)"
        style="padding:8px 12px;border:1.5px solid var(--primary);border-radius:10px;background:var(--primary-light);color:var(--primary);font-size:.85rem;font-weight:700;cursor:pointer;max-width:220px;">
    <option value="0" <?= ($modo_global || $empresa_actual === null) ? 'selected' : '' ?>>🌐 Todas las empresas</option>
    <?php foreach ($empresas_lista as $e): ?>
    <option value="<?= (int)$e['id'] ?>" <?= (!$modo_global && (int)$empresa_actual === (int)$e['id']) ? 'selected' : '' ?>>🏢 <?= htmlspecialchars($e['nombre']) ?></option>
    <?php endforeach; ?>
</select>
<?php endif; ?>
<div class="search-box"><i class="fas fa-search"></i><input type="text" placeholder="Buscar empleado, empresa..."><kbd>Ctrl K</kbd></div>
<button class="btn-icon" id="toggleTheme" title="Cambiar tema"><i class="fas fa-moon"></i></button>
<div class="notification-wrapper"><button class="btn-icon relative" id="notifBtn"><i class="fas fa-bell"></i><span class="badge-dot">3</span></button></div>
<div class="user-profile">
<img src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['nombre_completo'] ?? 'Admin') ?>&background=03a950&color=fff" alt="Avatar">
<div class="user-info">
<span class="user-name"><?= htmlspecialchars(explode(' ', $_SESSION['nombre_completo'] ?? 'Administrador')[0]) ?></span>
<span class="user-role"><?= htmlspecialchars($_SESSION['rol_nombre'] ?? 'Admin') ?></span>
</div>
<i class="fas fa-chevron-down" style="font-size:0.8rem;color:var(--text-muted);"></i>
</div>
</div>
</header>
<div class="content-wrapper">