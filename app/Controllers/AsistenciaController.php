<?php
namespace App\Controllers;
require_once __DIR__ . '/../Models/AsistenciaModel.php';
require_once __DIR__ . '/../Helpers/Security.php';
use App\Models\AsistenciaModel;
use App\Helpers\Security;

class AsistenciaController {
    private $model;
    private $db;
    const UMBRAL_ROSTRO = 0.55;
    
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
    
    /** Procesa el formulario de marcación con los 3 candados */
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
            
            $_POST['fecha'] = $_POST['fecha'] ?? date('Y-m-d');
            $_POST['hora_registro'] = $_POST['hora_registro'] ?? date('H:i:s');
            
            foreach (['lat', 'lng', 'accuracy', 'precision'] as $__gps) {
                if (array_key_exists($__gps, $_POST)) {
                    $__val = trim((string)$_POST[$__gps]);
                    $_POST[$__gps] = ($__val === '') ? null : $__val;
                }
            }
            
            $uid = (int)($_SESSION['usuario_id'] ?? 1);
            $observaciones = $_POST['observaciones'] ?? '';
            $sede_id = !empty($_POST['sede_id']) ? (int)$_POST['sede_id'] : null;
            
            // ===== CANDADO GPS =====
            $ok_gps = null; // true=dentro/sin georef, false=fuera, null=sin GPS
            if ($sede_id && !empty($_POST['lat']) && !empty($_POST['lng'])) {
                $distancia = $this->calcularDistancia($sede_id, (float)$_POST['lat'], (float)$_POST['lng']);
                if ($distancia !== null) {
                    $sede = $this->obtenerSede($sede_id);
                    $radio = (int)($sede['radio_m'] ?? 150);
                    if ($distancia <= $radio) {
                        $ok_gps = true;
                        $observaciones = trim($observaciones . " [Dentro del radio: " . round($distancia) . " m]");
                    } else {
                        $ok_gps = false;
                        $observaciones = trim($observaciones . " [FUERA DE RANGO: " . round($distancia) . " m de " . $sede['nombre'] . " (radio: {$radio} m)]");
                    }
                }
            } elseif (empty($_POST['lat']) || empty($_POST['lng'])) {
                $observaciones = trim($observaciones . " [SIN GPS]");
            }
            
            // ===== CANDADO ROSTRO (verificación server-side, FASE C) =====
            $modo_face = $_POST['modo_face'] ?? 'ia';
            $live = json_decode((string)($_POST['face_descriptor'] ?? ''), true);
            $live_ok = is_array($live) && count($live) === 128;
            
            $st = $this->db->prepare("SELECT face_descriptor FROM usuarios WHERE id = ? LIMIT 1");
            $st->execute([$uid]);
            $guardado = $st->fetchColumn();
            
            $ok_face = null; // true ok, false no coincide, 'manual' requiere revisión
            if ($live_ok) {
                if (empty($guardado)) {
                    // Enrolamiento único (NO se sobrescribe en cada marcación)
                    $this->guardarDescriptor($uid, json_encode($live));
                    $ok_face = true;
                    $observaciones = trim("[ROSTRO ENROLADO] " . $observaciones);
                } else {
                    $arr = json_decode((string)$guardado, true);
                    if (is_array($arr) && count($arr) === 128) {
                        $dist = $this->distanciaRostro($live, $arr);
                        if ($dist <= self::UMBRAL_ROSTRO) {
                            $ok_face = true;
                            $observaciones = trim("[ROSTRO OK d=" . number_format($dist, 2) . "] " . $observaciones);
                        } else {
                            $ok_face = false;
                            $observaciones = trim("[ROSTRO NO COINCIDE d=" . number_format($dist, 2) . "] " . $observaciones);
                        }
                    } else {
                        $ok_face = 'manual';
                        $observaciones = trim("[VECTOR GUARDADO DAÑADO - VERIFICACIÓN MANUAL] " . $observaciones);
                    }
                }
            } else {
                // Modo compatibilidad (iOS/Safari sin IA)
                if (empty($guardado)) {
                    $ok_face = 'manual';
                    $observaciones = trim("[ENROLADO CON FOTO - SIN VECTOR: completar en Chrome/Edge] " . $observaciones);
                } else {
                    $ok_face = 'manual';
                    $observaciones = trim("[VERIFICACIÓN MANUAL - MODO COMPATIBILIDAD] " . $observaciones);
                }
            }
            
            // ===== DECISIÓN DE ESTADO (matriz de 3 candados) =====
            $estado = 'pendiente';
            if ($ok_face === true && $ok_gps !== false && $ok_gps !== null) {
                $estado = 'validado';
            } elseif ($ok_face === true && $ok_gps === null) {
                $estado = 'pendiente'; // rostro ok pero sin GPS
            }
            
            // ===== GUARDAR MARCACIÓN =====
            $this->model->registrarMarcacion([
                'empresa_id'       => $_SESSION['empresa_id'] ?? 1,
                'usuario_id'       => $uid,
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
                'creado_por'       => $uid,
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
    
    // =====================================================
    // FASE C: RECONOCIMIENTO FACIAL (endpoints y utilidades)
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
    
    /** Distancia euclidiana entre dos vectores de 128 dimensiones */
    private function distanciaRostro(array $a, array $b): float {
        $sum = 0.0;
        for ($i = 0; $i < 128; $i++) {
            $d = ((float)($a[$i] ?? 0)) - ((float)($b[$i] ?? 0));
            $sum += $d * $d;
        }
        return sqrt($sum);
    }
    
    // =====================================================
    // GPS: cálculo de distancia
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
