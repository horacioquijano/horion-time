<?php
date_default_timezone_set('America/Bogota');
ini_set('display_errors', 1);
error_reporting(E_ALL);

$pass  = '@s3st2m1s@';          // ← cámbiala si deseas
$email = 'superadmin@horiontime.co';
$report = [];

try {
    $db = new PDO('HORION_DSN, HORION_DB_USER, HORION_DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (PDOException $e) {
    die('❌ Error de conexión: ' . $e->getMessage());
}

// 1) Rol SuperAdmin (se crea si no existe)
$st = $db->prepare("SELECT id FROM roles WHERE nombre = 'SuperAdmin' LIMIT 1");
$st->execute();
$rolId = $st->fetchColumn();
if (!$rolId) {
    try {
        $db->exec("INSERT INTO roles (nombre) VALUES ('SuperAdmin')");
        $rolId = (int)$db->lastInsertId();
        $report[] = "Rol SuperAdmin CREADO (id $rolId)";
    } catch (Throwable $e) {
        $report[] = '❌ No se pudo crear el rol: ' . $e->getMessage();
    }
} else {
    $report[] = "Rol SuperAdmin ya existía (id $rolId)";
}

// 2) Usuario SuperAdmin (se crea o se actualiza su contraseña)
$hash = password_hash($pass, PASSWORD_DEFAULT);
$st = $db->prepare("SELECT id FROM usuarios WHERE email = ? LIMIT 1");
$st->execute([$email]);
$uid = $st->fetchColumn();

$cols = $db->query("SHOW COLUMNS FROM usuarios")->fetchAll(PDO::FETCH_COLUMN);
$data = [
    'empresa_id'         => 1,
    'rol_id'             => (int)$rolId,
    'nombre_completo'    => 'Super Administrador',
    'email'              => $email,
    'password_hash'      => $hash,
    'estado'             => 'activo',
    'identificacion'     => '000000000',
    'cargo'              => 'Super Administrador de Plataforma',
    'fecha_ingreso'      => date('Y-m-d'),
    'fecha_contratacion' => date('Y-m-d'),
];
$data = array_intersect_key($data, array_flip($cols));

try {
    if ($uid) {
        $sets = implode(', ', array_map(fn($k) => "`$k` = :$k", array_keys($data)));
        $st = $db->prepare("UPDATE usuarios SET $sets WHERE id = :id");
        foreach ($data as $k => $v) $st->bindValue(":$k", $v);
        $st->bindValue(':id', (int)$uid);
        $st->execute();
        $report[] = "Usuario SuperAdmin ACTUALIZADO (id $uid) — contraseña restablecida";
    } else {
        $campos = implode(', ', array_map(fn($k) => "`$k`", array_keys($data)));
        $marks  = implode(', ', array_map(fn($k) => ":$k", array_keys($data)));
        $st = $db->prepare("INSERT INTO usuarios ($campos) VALUES ($marks)");
        foreach ($data as $k => $v) $st->bindValue(":$k", $v);
        $st->execute();
        $report[] = 'Usuario SuperAdmin CREADO (id ' . $db->lastInsertId() . ')';
    }
} catch (Throwable $e) {
    $report[] = '❌ Error al guardar el usuario: ' . $e->getMessage();
}
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Setup SuperAdmin</title>
<style>
body{font-family:Inter,Segoe UI,sans-serif;padding:24px;background:#f4f6f9;color:#2d3748;max-width:800px;margin:0 auto}
h1{color:#03a950}.box{background:#fff;padding:20px;border-radius:12px;margin:16px 0;box-shadow:0 4px 12px rgba(0,0,0,.05)}
.cred{font-size:1.15rem;background:#0f172a;color:#a5f3fc;padding:18px;border-radius:12px;font-family:monospace;line-height:2}
a{display:inline-block;margin-top:12px;color:#fff;background:#03a950;padding:12px 22px;border-radius:10px;text-decoration:none;font-weight:700}
ul{line-height:1.9}
</style></head><body>
<h1>🛡️ Setup Super Administrador</h1>
<div class="box"><ul><?php foreach ($report as $r): ?><li>✔ <?= htmlspecialchars($r) ?></li><?php endforeach; ?></ul></div>
<div class="box">
<h3>🔑 Credenciales de acceso</h3>
<div class="cred">
Email: <?= htmlspecialchars($email) ?><br>
Contraseña: <?= htmlspecialchars($pass) ?>
</div>
<a href="/horion-time/public/login">→ Ir al Login</a>
</div>
</body></html>