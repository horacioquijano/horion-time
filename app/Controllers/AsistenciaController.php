<?php
namespace App\Controllers;
require_once __DIR__ . '/../Models/AsistenciaModel.php';
require_once __DIR__ . '/../Helpers/Security.php';
use App\Models\AsistenciaModel;
use App\Helpers\Security;
class AsistenciaController {
private $model;
private $db;
public function __construct($db) {
$this->db = $db;
$this->model = new AsistenciaModel($db);
}
/** Listado general con filtro por fecha */
public function index() {
$fecha = $_GET['fecha'] ?? date('Y-m-d');
$empresa_filtro = (bool)($_SESSION['modo_global'] ?? false) ? null : (int)($_SESSION['empresa_id'] ?? 1);
$marcaciones = $this->model->getMarcacionesPorFecha($fecha, $empresa_filtro);
$GLOBALS['pageTitle'] = 'Registro de Asistencia';
include __DIR__ . '/../Views/asistencia/index.php';
}
/** Pantalla de marcación con cámara y GPS */
public function marcar() {
$csrf_token = Security::generateCsrfToken();
$marcaciones_hoy = $this->model->getMarcacionesUsuarioHoy($_SESSION['usuario_id'] ?? 1);
$sedes = $this->model->getSedes(
    $_SESSION['empresa_id'] ?? 1,
    (($_SESSION['rol_nombre'] ?? '') === 'SuperAdmin' && (bool)($_SESSION['modo_global'] ?? false))
);
$GLOBALS['pageTitle'] = 'Mi Marcación';
include __DIR__ . '/../Views/asistencia/marcar.php';
}
/** Procesa el formulario de marcación */
public function procesar() {
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
header('Location: /horion-time/public/asistencia/marcar'); exit;
}
try {
$foto = null;
if (!empty($_POST['foto_data'])) {
$data = $_POST['foto_data'];
if (strpos($data, ',') !== false) {
$bin = base64_decode(substr($data, strpos($data, ',') + 1));
if ($bin !== false) {
$dir = __DIR__ . '/../../public/uploads/marcaciones/';
if (!is_dir($dir)) mkdir($dir, 0777, true);
$nombre = 'marcacion_' . ($_SESSION['usuario_id'] ?? 0) . '_' . date('Ymd_His') . '.jpg';
file_put_contents($dir . $nombre, $bin);
$foto = '/horion-time/public/uploads/marcaciones/' . $nombre;
}
}
}
// ===== FIX: Garantizar fecha y hora_registro (evita el error NOT NULL) =====
$_POST['fecha'] = $_POST['fecha'] ?? date('Y-m-d');
$_POST['hora_registro'] = $_POST['hora_registro'] ?? date('H:i:s');
// ===== FIN FIX =====

// ===== FIX ERROR 1366: GPS vacío => NULL en columnas DECIMAL =====
foreach (['lat', 'lng', 'accuracy', 'precision'] as $__gps) {
    if (array_key_exists($__gps, $_POST)) {
        $__val = trim((string)$_POST[$__gps]);
        $_POST[$__gps] = ($__val === '') ? null : $__val;
    }
}
// ==================================================================

$this->model->registrarMarcacion([
'empresa_id'       => $_SESSION['empresa_id'] ?? 1,
'usuario_id'       => $_SESSION['usuario_id'] ?? 1,
'sede_id'          => !empty($_POST['sede_id']) ? (int)$_POST['sede_id'] : null,
'fecha'            => $_POST['fecha'],
'hora_registro'    => $_POST['hora_registro'],
'tipo_marcacion'   => $_POST['tipo_marcacion'] ?? 'entrada',
'metodo_marcacion' => $_POST['metodo_marcacion'] ?? 'foto',
'foto_evidencia'   => $foto,
'lat'              => $_POST['lat'] ?? null,
'lng'              => $_POST['lng'] ?? null,
'observaciones'    => $_POST['observaciones'] ?? null,
'creado_por'       => (int)($_SESSION['usuario_id'] ?? 1),
]);
header('Location: /horion-time/public/asistencia/marcar?success=1'); exit;
} catch (\Exception $e) {
header('Location: /horion-time/public/asistencia/marcar?error=' . urlencode($e->getMessage())); exit;
}
}
/** Corregir estado desde el listado */
public function corregir($id = null) {
$id = (int)($id ?? 0);
$estado = $_GET['estado'] ?? '';
if (in_array($estado, ['validado', 'pendiente', 'rechazado'], true)) {
$this->model->actualizarEstado($id, $estado);
}
header('Location: /horion-time/public/asistencia'); exit;
}
}
