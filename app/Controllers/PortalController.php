<?php
namespace App\Controllers;
require_once __DIR__ . '/../Models/AsistenciaModel.php';
require_once __DIR__ . '/../Helpers/Security.php';
use App\Models\AsistenciaModel;
use App\Helpers\Security;
use PDO;

class PortalController {
    private $db;
    private $asistencia;

    public function __construct($db) {
        $this->db = $db;
        $this->asistencia = new AsistenciaModel($db);
    }

    public function index() {
        $usuario_id = $_SESSION['usuario_id'] ?? 1;
        $csrf_token = Security::generateCsrfToken();

        $jornada = ['entrada' => null, 'salida_almuerzo' => null, 'regreso_almuerzo' => null, 'salida' => null];
        $horas_trabajadas = 0;
        $marcaciones_hoy = [];
        $marcaciones_semana = [];
        $mis_novedades = [];
        $resumen_mensual = ['dias_trabajados' => 0, 'total_entradas' => 0];

        try {
            $j = $this->asistencia->getJornadaHoy($usuario_id);
            if ($j) {
                foreach ($jornada as $k => $v) if (!empty($j[$k])) $jornada[$k] = $j[$k];
            }
            $horas_trabajadas = $this->asistencia->calcularHorasTrabajadas($jornada);
        } catch (\Throwable $e) {}

        try { $marcaciones_hoy = $this->asistencia->getMarcacionesHoy($usuario_id); } catch (\Throwable $e) {}

        try {
            $st = $this->db->prepare("SELECT * FROM registros_asistencia
                WHERE usuario_id = :uid AND fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                ORDER BY fecha DESC, hora_registro DESC LIMIT 50");
            $st->execute([':uid' => $usuario_id]);
            $marcaciones_semana = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {}

        try {
            $st = $this->db->prepare("SELECT * FROM novedades WHERE usuario_id = :uid ORDER BY fecha_inicio DESC LIMIT 10");
            $st->execute([':uid' => $usuario_id]);
            $mis_novedades = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {}

        try {
            $rm = $this->asistencia->getResumenMensual($usuario_id, (int)date('Y'), (int)date('n'));
            if ($rm) $resumen_mensual = $rm;
        } catch (\Throwable $e) {}

        $GLOBALS['pageTitle'] = 'Portal Empleado';
        include __DIR__ . '/../Views/portal/index.php';
    }

    public function perfil() {
        $usuario_id = $_SESSION['usuario_id'] ?? 1;
        $csrf_token = Security::generateCsrfToken();
        $usuario = [];
        try {
            $st = $this->db->prepare("SELECT * FROM usuarios WHERE id = :uid");
            $st->execute([':uid' => $usuario_id]);
            $usuario = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {}
        $GLOBALS['pageTitle'] = 'Mi Perfil';
        include __DIR__ . '/../Views/portal/perfil.php';
    }

    public function cambiarPassword() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /horion-time/public/portal/perfil'); exit; }
        $usuario_id = $_SESSION['usuario_id'] ?? 1;
        $actual  = $_POST['password_actual'] ?? '';
        $nueva   = $_POST['password_nueva'] ?? '';
        $confirm = $_POST['password_confirmar'] ?? '';
        try {
            if ($nueva === '' || $nueva !== $confirm) throw new \Exception('La confirmación no coincide.');
            if (strlen($nueva) < 6) throw new \Exception('Mínimo 6 caracteres.');
            
            $cols = $this->db->query("SHOW COLUMNS FROM usuarios")->fetchAll(PDO::FETCH_COLUMN);
            $colPass = null;
            foreach (['password_hash', 'password', 'clave'] as $c) if (in_array($c, $cols)) { $colPass = $c; break; }
            if (!$colPass) throw new \Exception('No hay columna de contraseña.');
            
            $st = $this->db->prepare("SELECT `$colPass` AS pass FROM usuarios WHERE id = :uid");
            $st->execute([':uid' => $usuario_id]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            $ok = $row && (password_verify($actual, (string)$row['pass']) || hash('sha256', $actual) === $row['pass'] || $actual === $row['pass']);
            if (!$ok) throw new \Exception('Contraseña actual incorrecta.');
            
            $up = $this->db->prepare("UPDATE usuarios SET `$colPass` = :p WHERE id = :uid");
            $up->execute([':p' => password_hash($nueva, PASSWORD_DEFAULT), ':uid' => $usuario_id]);
            header('Location: /horion-time/public/portal/perfil?ok=1'); exit;
        } catch (\Throwable $e) {
            header('Location: /horion-time/public/portal/perfil?error=' . urlencode($e->getMessage())); exit;
        }
    }
}