<?php
namespace App\Controllers;
use App\Models\DispositivoModel;
use App\Models\UsuarioModel;
use App\Models\AsistenciaModel;

class KioscoController {
    private $dispositivoModel;
    private $usuarioModel;
    private $asistenciaModel;
    private $db;

    public function __construct($db) {
        $this->db = $db;
        $this->dispositivoModel = new DispositivoModel($db);
        $this->usuarioModel = new UsuarioModel($db);
        $this->asistenciaModel = new AsistenciaModel($db);
    }

    /** $token opcional: sin token muestra pantalla de conexión */
    public function index($token = null) {
        $dispositivo = null;
        if ($token) {
            $dispositivo = $this->dispositivoModel->getByToken($token);
            if ($dispositivo) {
                $this->dispositivoModel->updateLastActivity($dispositivo['id']);
            }
        }
        require_once __DIR__ . '/../Views/kiosco/index.php';
    }

    /** API: Validar PIN del dispositivo */
    public function validarPin() {
        header('Content-Type: application/json');
        try {
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            $dispositivo = $this->dispositivoModel->getByToken((string)($data['token'] ?? ''));
            if (!$dispositivo || (string)($dispositivo['pin_acceso'] ?? '') !== (string)($data['pin'] ?? '')) {
                echo json_encode(['success' => false, 'message' => 'PIN incorrecto o dispositivo inválido.']);
                exit;
            }
            $this->dispositivoModel->updateLastActivity($dispositivo['id']);
            if (method_exists($this->dispositivoModel, 'logAcceso')) {
                $this->dispositivoModel->logAcceso($dispositivo['id'], null, 'pin');
            }
            echo json_encode([
                'success' => true,
                'message' => 'Acceso concedido.',
                'sede_id' => (int)($dispositivo['sede_id'] ?? 0),
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Error interno: ' . $e->getMessage()]);
        }
        exit;
    }

    /** API: Identificar empleado y registrar marcación (SIN 500: todo controlado) */
    public function identificar() {
        header('Content-Type: application/json');
        try {
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            $ident = trim((string)($data['identificacion'] ?? ''));
            if ($ident === '') {
                echo json_encode(['success' => false, 'message' => 'Falta la identificación.']);
                exit;
            }

            // SELECT seguro: trae todo y lee claves con ?? (no truena si falta una columna)
            $stmt = $this->db->prepare("SELECT * FROM usuarios WHERE identificacion = ? AND estado = 'activo' LIMIT 1");
            $stmt->execute([$ident]);
            $usuario = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$usuario) {
                echo json_encode(['success' => false, 'message' => 'Empleado no encontrado o inactivo.']);
                exit;
            }

            // Dispositivo: sede de respaldo + actividad
            $dispositivo = $this->dispositivoModel->getByToken((string)($data['token'] ?? ''));
            $sede_id = (int)($data['sede_id'] ?? 0) ?: (int)($dispositivo['sede_id'] ?? 0) ?: null;

            // Guardar foto de evidencia
            $foto = null;
            if (!empty($data['foto_data'])) {
                $bin = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $data['foto_data']));
                if ($bin) {
                    $dir = __DIR__ . '/../../public/uploads/marcaciones/';
                    if (!is_dir($dir)) mkdir($dir, 0777, true);
                    $nombre = 'kiosco_' . (int)$usuario['id'] . '_' . date('Ymd_His') . '.jpg';
                    if (@file_put_contents($dir . $nombre, $bin)) {
                        $foto = '/horion-time/public/uploads/marcaciones/' . $nombre;
                    }
                }
            }

            // Tipo de marcación automático según la última del día
            $tipo = 'entrada';
            $hoy = date('Y-m-d');
            $st2 = $this->db->prepare("SELECT tipo_marcacion FROM registros_asistencia WHERE usuario_id = ? AND fecha = ? ORDER BY hora_registro DESC LIMIT 1");
            $st2->execute([(int)$usuario['id'], $hoy]);
            $ultima = $st2->fetchColumn();
            if ($ultima === 'entrada') $tipo = 'salida_almuerzo';
            elseif ($ultima === 'salida_almuerzo') $tipo = 'regreso_almuerzo';
            elseif ($ultima === 'regreso_almuerzo') $tipo = 'salida';

            // MÉTODO SEGURO: solo usa 'kiosco' si el ENUM lo permite; si no, 'foto'
            $metodo = 'kiosco';
            $col = $this->db->query("SHOW COLUMNS FROM registros_asistencia LIKE 'metodo_marcacion'")->fetch(\PDO::FETCH_ASSOC);
            if ($col && stripos((string)$col['Type'], 'enum') === 0 && strpos((string)$col['Type'], "'kiosco'") === false) {
                $metodo = 'foto';
            }

            $this->asistenciaModel->registrarMarcacion([
                'usuario_id'       => (int)$usuario['id'],
                'empresa_id'       => (int)($usuario['empresa_id'] ?? 1),
                'sede_id'          => $sede_id,
                'fecha'            => $hoy,
                'tipo_marcacion'   => $tipo,
                'hora_registro'    => date('H:i:s'),
                'metodo_marcacion' => $metodo,
                'foto_evidencia'   => $foto,
                'estado'           => 'validado',
                'observaciones'    => '[MARCACIÓN DESDE KIOSCO FÍSICO]',
            ]);

            if ($dispositivo) {
                $this->dispositivoModel->updateLastActivity($dispositivo['id']);
            }

            echo json_encode([
                'success' => true,
                'message' => '¡Marcación registrada!',
                'nombre'  => $usuario['nombre_completo'] ?? '',
                'tipo'    => ucfirst(str_replace('_', ' ', $tipo)),
                'foto'    => $usuario['foto_perfil'] ?? null,
            ]);
        } catch (\Throwable $e) {
            // NUNCA más 500 ciego: el kiosco muestra el motivo exacto
            echo json_encode(['success' => false, 'message' => 'Error interno: ' . $e->getMessage()]);
        }
        exit;
    }
}
