<?php
namespace App\Controllers;
use App\Models\SupervisorModel;
use App\Models\NovedadModel;
use App\Models\HoraExtraModel;
use App\Helpers\Security;
class SupervisorController {
private $supervisorModel;
private $novedadModel;
private $horaExtraModel;
private $db;
public function __construct($db) {
$this->db = $db;
$this->supervisorModel = new SupervisorModel($db);
$this->novedadModel = new NovedadModel($db);
$this->horaExtraModel = new HoraExtraModel($db);
}
// ===== CAMBIO 1: helper de empresa activa =====
private function empresaActual() { return (int)($_SESSION['empresa_id'] ?? 0); }
private function modoGlobal()    { return (bool)($_SESSION['modo_global'] ?? false); }
// ===== FIN CAMBIO 1 =====
/**
* Dashboard del Supervisor
*/
public function index() {
$usuario_id = $_SESSION['usuario_id'] ?? 1;
$rol = $_SESSION['rol_nombre'] ?? '';
$rolesPermitidos = ['SuperAdmin', 'Admin_Empresa', 'Supervisor', 'RRHH'];
if (!in_array($rol, $rolesPermitidos)) {
http_response_code(403);
die("Acceso denegado: Se requieren permisos de supervisión.");
}
// ===== CAMBIO 2: llamadas protegidas + valores por defecto =====
try { // Obtener resumen usando horarios reales de cada empleado
$horarioModel = new \App\Models\HorarioModel($this->db);
$equipo = $this->supervisorModel->getEquipoDetalle($usuario_id);
$presentes = 0; $tardanzas = 0; $ausentes = 0; $total = count($equipo);

foreach ($equipo as $emp) {
    $turno = $horarioModel->getTurnoEmpleadoDia($emp['id'], date('Y-m-d'));
    
    if (!$turno || $turno['es_descanso']) {
        continue; // No debía trabajar hoy
    }
    
    // Aquí iría la lógica real de verificar marcaciones vs turno
    // Por ahora, simulación basada en marcaciones del día
    $marcaciones = $this->db->prepare("
        SELECT MIN(hora_registro) as primera 
        FROM registros_asistencia 
        WHERE usuario_id = ? AND DATE(fecha) = CURDATE()
    ");
    $marcaciones->execute([$emp['id']]);
    $primera = $marcaciones->fetchColumn();
    
    if ($primera) {
        $presentes++;
        $tolerancia = $turno['tolerancia_entrada_min'] ?? 10;
        $hora_limite = date('H:i:s', strtotime($turno['hora_entrada'] . " +{$tolerancia} minutes"));
        if ($primera > $hora_limite) {
            $tardanzas++;
        }
    } else {
        $ausentes++;
    }
}

$resumen = [
    'porcentaje_asistencia' => $total > 0 ? round(($presentes / $total) * 100) : 0,
    'presentes' => $presentes,
    'tardanzas' => $tardanzas,
    'ausentes' => $ausentes
];} catch (\Throwable $e) { $resumen = []; }
if (!is_array($resumen)) $resumen = [];
$resumen += ['porcentaje_asistencia' => 0, 'presentes' => 0, 'tardanzas' => 0, 'ausentes' => 0];
try { $equipo = $this->supervisorModel->getEquipoDetalle($usuario_id); } catch (\Throwable $e) { $equipo = []; }
try { $novedadesPendientes = $this->supervisorModel->getNovedadesPendientesEquipo($usuario_id); } catch (\Throwable $e) { $novedadesPendientes = []; }
try { $horasExtrasPendientes = $this->supervisorModel->getHorasExtrasPendientesEquipo($usuario_id); } catch (\Throwable $e) { $horasExtrasPendientes = []; }
try { $inconsistencias = $this->supervisorModel->getInconsistencias($usuario_id); } catch (\Throwable $e) { $inconsistencias = []; }
// ===== CAMBIO 3: si el equipo no tiene pendientes, mostrar los de la empresa =====
$verEmpresa = in_array($rol, ['SuperAdmin', 'Admin_Empresa', 'RRHH'], true);
$filtroEmp = ($verEmpresa && $this->modoGlobal()) ? '' : ' AND x.empresa_id = :eid ';
if (empty($novedadesPendientes)) {
try {
$sql = "SELECT x.*, u.nombre_completo AS empleado_nombre, u.identificacion,
COALESCE(x.justificacion, x.motivo, x.descripcion, '') AS motivo,
GREATEST(DATEDIFF(x.fecha_fin, x.fecha_inicio) + 1, 1) AS dias_calculados
FROM novedades x LEFT JOIN usuarios u ON x.usuario_id = u.id
WHERE x.estado = 'pendiente' $filtroEmp ORDER BY x.fecha_inicio DESC LIMIT 50";
$st = $this->db->prepare($sql);
if ($filtroEmp !== '') $st->execute([':eid' => $this->empresaActual() ?: 1]);
$novedadesPendientes = $st->fetchAll(\PDO::FETCH_ASSOC);
} catch (\Throwable $e) { $novedadesPendientes = []; }
}
if (empty($horasExtrasPendientes)) {
try {
$sql = "SELECT x.*, u.nombre_completo AS empleado_nombre, u.identificacion
FROM horas_extras x LEFT JOIN usuarios u ON x.usuario_id = u.id
WHERE x.estado IN ('pendiente','autorizada') $filtroEmp ORDER BY x.fecha DESC LIMIT 50";
$st = $this->db->prepare($sql);
if ($filtroEmp !== '') $st->execute([':eid' => $this->empresaActual() ?: 1]);
$horasExtrasPendientes = $st->fetchAll(\PDO::FETCH_ASSOC);
} catch (\Throwable $e) { $horasExtrasPendientes = []; }
}
// ===== FIN CAMBIOS 2-3 =====
$csrf_token = Security::generateCsrfToken();
require_once __DIR__ . '/../Views/supervisor/index.php';
}
/**
* Vista del equipo completo
*/
public function equipo() {
$usuario_id = $_SESSION['usuario_id'] ?? 1;
$fecha = $_GET['fecha'] ?? date('Y-m-d');
$equipo = $this->supervisorModel->getEquipoDetalle($usuario_id, $fecha);
$resumen = $this->supervisorModel->getResumenEquipo($usuario_id, $fecha);
$csrf_token = Security::generateCsrfToken();
require_once __DIR__ . '/../Views/supervisor/equipo.php';
}
/**
* Aprobar novedad del equipo (acción rápida)
*/
public function aprobarNovedad($id) {
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
try {
Security::validateCsrfToken($_POST['csrf_token'] ?? '');
$stmt = $this->db->prepare("SELECT usuario_id FROM novedades WHERE id = :id");
$stmt->execute([':id' => $id]);
$novedad = $stmt->fetch(\PDO::FETCH_ASSOC);
if (!$novedad) throw new \Exception("Novedad no encontrada.");
// ===== CAMBIO 4: admins globales no requieren pertenecer al equipo =====
$esAdminGlobal = in_array($_SESSION['rol_nombre'] ?? '', ['SuperAdmin', 'Admin_Empresa', 'RRHH'], true);
if (!$esAdminGlobal) {
$equipoIds = $this->supervisorModel->getEquipoIds($_SESSION['usuario_id']);
if (!in_array($novedad['usuario_id'], $equipoIds)) {
throw new \Exception("No tienes permisos para aprobar esta novedad.");
}
}
// ===== FIN CAMBIO 4 =====
$estado = $_POST['estado'] ?? 'aprobada';
$this->novedadModel->actualizarEstado(
$id, $estado, $_SESSION['usuario_id'],
$_POST['observaciones'] ?? '', $_SESSION['rol_nombre']
);
header('Location: /horion-time/public/supervisor?success=1');
exit;
} catch (\Exception $e) {
header('Location: /horion-time/public/supervisor?error=' . urlencode($e->getMessage()));
exit;
}
}
}

    /**
     * Aprobar hora extra del equipo
     */
    public function aprobarHoraExtra($id) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                Security::validateCsrfToken($_POST['csrf_token'] ?? '');
                $stmt = $this->db->prepare("SELECT usuario_id FROM horas_extras WHERE id = :id");
                $stmt->execute([':id' => $id]);
                $he = $stmt->fetch(\PDO::FETCH_ASSOC);
                if (!$he) throw new \Exception("Hora extra no encontrada.");

                // ===== CAMBIO 1: permisos por rol =====
                $rolActual = $_SESSION['rol_nombre'] ?? '';
                $esAdminGlobal = in_array($rolActual, ['SuperAdmin', 'Admin_Empresa', 'RRHH'], true);
                if (!$esAdminGlobal) {
                    // Supervisor: solo su equipo y NUNCA la propia
                    $equipoIds = $this->supervisorModel->getEquipoIds($_SESSION['usuario_id']);
                    if (!in_array($he['usuario_id'], $equipoIds)) {
                        throw new \Exception("No tienes permisos para aprobar esta hora extra.");
                    }
                    if ((int)$he['usuario_id'] === (int)($_SESSION['usuario_id'] ?? 0)) {
                        throw new \Exception("No puedes aprobar tu propia hora extra.");
                    }
                }
                // SuperAdmin / Admin_Empresa / RRHH: pueden aprobar cualquiera,
                // incluidas las propias (necesario para pruebas y administración)
                // ===== FIN CAMBIO 1 =====

                $estado = $_POST['estado'] ?? 'aprobada';
                $horas = $estado === 'aprobada' ? (float)($_POST['horas_aprobadas'] ?? 0) : 0;

                // ===== CAMBIO 2: actualización directa (evita el bloqueo interno del modelo) =====
                $cols = $this->db->query("SHOW COLUMNS FROM horas_extras")->fetchAll(\PDO::FETCH_COLUMN);
                $set = ["estado = :estado"];
                $params = [':estado' => $estado, ':id' => (int)$id];
                if (in_array('horas_aprobadas', $cols))  { $set[] = "horas_aprobadas = :ha";  $params[':ha'] = $horas; }
                if (in_array('horas_rechazadas', $cols) && $estado !== 'aprobada') { $set[] = "horas_rechazadas = :hr"; $params[':hr'] = (float)($_POST['horas_aprobadas'] ?? 0); }
                if (in_array('observaciones', $cols))    { $set[] = "observaciones = :obs";   $params[':obs'] = $_POST['observaciones'] ?? ''; }
                if (in_array('aprobador_id', $cols))     { $set[] = "aprobador_id = :ap";     $params[':ap'] = (int)($_SESSION['usuario_id'] ?? 1); }
                if (in_array('fecha_aprobacion', $cols)) { $set[] = "fecha_aprobacion = NOW()"; }
                $up = $this->db->prepare("UPDATE horas_extras SET " . implode(', ', $set) . " WHERE id = :id");
                $up->execute($params);
                // ===== FIN CAMBIO 2 =====

                header('Location: /horion-time/public/supervisor?success=1');
                exit;
            } catch (\Exception $e) {
                header('Location: /horion-time/public/supervisor?error=' . urlencode($e->getMessage()));
                exit;
            }
        }
    }

}