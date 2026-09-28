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
            header('Location: /horion-time/public/asistencia/marcar');
            exit;
        }
        
        try {
            // ===== PROCESAR FOTO =====
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
            
            // ===== GARANTIZAR FECHA Y HORA =====
            $_POST['fecha'] = $_POST['fecha'] ?? date('Y-m-d');
            $_POST['hora_registro'] = $_POST['hora_registro'] ?? date('H:i:s');
            
            // ===== FIX: GPS vacío => NULL =====
            foreach (['lat', 'lng', 'accuracy', 'precision', 'distancia_m'] as $__gps) {
                if (array_key_exists($__gps, $_POST)) {
                    $__val = trim((string)$_POST[$__gps]);
                    $_POST[$__gps] = ($__val === '') ? null : $__val;
                }
            }
            
            // ===== VALIDACIÓN DE DISTANCIA (Haversine) =====
            $estado = 'pendiente';
            $observaciones = $_POST['observaciones'] ?? '';
            $sede_id = !empty($_POST['sede_id']) ? (int)$_POST['sede_id'] : null;
            
            if ($sede_id && !empty($_POST['lat']) && !empty($_POST['lng'])) {
                $distancia = $this->calcularDistancia($sede_id, (float)$_POST['lat'], (float)$_POST['lng']);
                
                if ($distancia !== null) {
                    $sede = $this->obtenerSede($sede_id);
                    $radio = (int)($sede['radio_m'] ?? 150);
                    
                    if ($distancia <= $radio) {
                        $estado = 'validado';
                        $observaciones = trim($observaciones . " [Dentro del radio: " . round($distancia) . " m]");
                    } else {
                        $estado = 'pendiente';
                        $observaciones = trim($observaciones . " [FUERA DE RANGO: " . round($distancia) . " m de " . $sede['nombre'] . " (radio: {$radio} m)]");
                    }
                }
            } elseif (empty($_POST['lat']) || empty($_POST['lng'])) {
                $observaciones = trim($observaciones . " [SIN GPS]");
            }
            
            // ===== GUARDAR DESCRIPTOR FACIAL SI VIENE =====
            if (!empty($_POST['face_descriptor'])) {
                $this->guardarDescriptor($_SESSION['usuario_id'] ?? 1, $_POST['face_descriptor']);
            }
            
            // ===== REGISTRAR MARCACIÓN =====
            $this->model->registrarMarcacion([
                'empresa_id'       => $_SESSION['empresa_id'] ?? 1,
                'usuario_id'       => $_SESSION['usuario_id'] ?? 1,
                'sede_id'          => $sede_id,
                'fecha'            => $_POST['fecha'],
                'hora_registro'    => $_POST['hora_registro'],
                'tipo_marcacion'   => $_POST['tipo_marcacion'] ?? 'entrada',
                'metodo_marcacion' => $_POST['metodo_marcacion'] ?? 'facial',
                'foto_evidencia'   => $foto,
                'lat'              => $_POST['lat'] ?? null,
                'lng'              => $_POST['lng'] ?? null,
                'estado'           => $estado,
                'observaciones'    => $observaciones,
                'creado_por'       => (int)($_SESSION['usuario_id'] ?? 1),
            ]);
            
            header('Location: /horion-time/public/asistencia/marcar?success=1');
            exit;
            
        } catch (\Exception $e) {
            header('Location: /horion-time/public/asistencia/marcar?error=' . urlencode($e->getMessage()));
            exit;
        }
    }
    
    /** Corregir estado desde el listado */
    public function corregir($id = null) {
        $id = (int)($id ?? 0);
        $estado = $_GET['estado'] ?? '';
        if (in_array($estado, ['validado', 'pendiente', 'rechazado'], true)) {
            $this->model->actualizarEstado($id, $estado);
        }
        header('Location: /horion-time/public/asistencia');
        exit;
    }
    
    // =====================================================
    // MÉTODOS PARA RECONOCIMIENTO FACIAL (NUEVOS)
    // =====================================================
    
    /** Endpoint AJAX: obtener descriptor facial del usuario logueado */
    public function obtenerDescriptor() {
        header('Content-Type: application/json');
        try {
            $usuario_id = (int)($_SESSION['usuario_id'] ?? 0);
            if (!$usuario_id) {
                echo json_encode(['descriptor' => null]);
                exit;
            }
            
            $st = $this->db->prepare("SELECT face_descriptor FROM usuarios WHERE id = ? LIMIT 1");
            $st->execute([$usuario_id]);
            $descriptor = $st->fetchColumn();
            
            echo json_encode(['descriptor' => $descriptor ?: null]);
        } catch (\Throwable $e) {
            echo json_encode(['descriptor' => null, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    /** Guardar descriptor facial (inscripción) */
    private function guardarDescriptor($usuario_id, $descriptor_json) {
        try {
            $st = $this->db->prepare("UPDATE usuarios SET face_descriptor = ? WHERE id = ?");
            $st->execute([$descriptor_json, (int)$usuario_id]);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
    
    // =====================================================
    // MÉTODOS PARA VALIDACIÓN GPS (NUEVOS)
    // =====================================================
    
    /** Calcular distancia Haversine entre GPS del empleado y sede */
    private function calcularDistancia($sede_id, $lat_empleado, $lng_empleado) {
        $sede = $this->obtenerSede($sede_id);
        if (!$sede || !$sede['lat_ref'] || !$sede['lng_ref']) {
            return null; // Sede sin georeferencia
        }
        
        $lat_ref = (float)$sede['lat_ref'];
        $lng_ref = (float)$sede['lng_ref'];
        
        // Fórmula de Haversine
        $R = 6371000; // Radio de la Tierra en metros
        $dLat = deg2rad($lat_ref - $lat_empleado);
        $dLng = deg2rad($lng_ref - $lng_empleado);
        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($lat_empleado)) * cos(deg2rad($lat_ref)) *
             sin($dLng/2) * sin($dLng/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        $distancia = $R * $c;
        
        return $distancia;
    }
    
    /** Obtener datos de una sede */
    private function obtenerSede($sede_id) {
        try {
            $st = $this->db->prepare("SELECT id, nombre, lat_ref, lng_ref, radio_m FROM sedes WHERE id = ? LIMIT 1");
            $st->execute([(int)$sede_id]);
            return $st->fetch(\PDO::FETCH_ASSOC) ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
