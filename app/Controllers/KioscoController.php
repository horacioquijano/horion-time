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

    /** FIX: $token es opcional. Si no viene, muestra pantalla de bienvenida */
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

    // API: Validar PIN
    public function validarPin() {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);
        
        $dispositivo = $this->dispositivoModel->getByToken($data['token'] ?? '');
        if (!$dispositivo || $dispositivo['pin_acceso'] !== $data['pin']) {
            echo json_encode(['success' => false, 'message' => 'PIN incorrecto o dispositivo inválido.']);
            exit;
        }

        $this->dispositivoModel->updateLastActivity($dispositivo['id']);
        $this->dispositivoModel->logAcceso($dispositivo['id'], null, 'pin');
        
        echo json_encode(['success' => true, 'message' => 'Acceso concedido.', 'sede_id' => $dispositivo['sede_id']]);
        exit;
    }

    // API: Identificar usuario y marcar (MEJORADO: acepta foto y tipo de marcación)
    public function identificar() {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);
        
        $stmt = $this->db->prepare("SELECT id, nombre_completo, foto_perfil, empresa_id FROM usuarios WHERE identificacion = :ident AND estado = 'activo'");
        $stmt->execute([':ident' => $data['identificacion']]);
        $usuario = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$usuario) {
            echo json_encode(['success' => false, 'message' => 'Empleado no encontrado o inactivo.']);
            exit;
        }

        // Guardar foto si viene
        $foto = null;
        if (!empty($data['foto_data'])) {
            $bin = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $data['foto_data']));
            if ($bin) {
                $dir = __DIR__ . '/../../public/uploads/marcaciones/';
                if (!is_dir($dir)) mkdir($dir, 0777, true);
                $nombre = 'kiosco_' . $usuario['id'] . '_' . date('Ymd_His') . '.jpg';
                file_put_contents($dir . $nombre, $bin);
                $foto = '/horion-time/public/uploads/marcaciones/' . $nombre;
            }
        }

        // Lógica de tipo de marcación (Entrada/Salida) basada en la última marcación del día
        $tipo = 'entrada';
        $hoy = date('Y-m-d');
        $st2 = $this->db->prepare("SELECT tipo_marcacion FROM registros_asistencia WHERE usuario_id = ? AND fecha = ? ORDER BY hora_registro DESC LIMIT 1");
        $st2->execute([$usuario['id'], $hoy]);
        $ultima = $st2->fetchColumn();
        if ($ultima === 'entrada') $tipo = 'salida_almuerzo';
        elseif ($ultima === 'salida_almuerzo') $tipo = 'regreso_almuerzo';
        elseif ($ultima === 'regreso_almuerzo') $tipo = 'salida';

        $this->asistenciaModel->registrarMarcacion([
            'usuario_id'       => $usuario['id'],
            'empresa_id'       => $usuario['empresa_id'] ?? $data['empresa_id'] ?? 1,
            'sede_id'          => $data['sede_id'] ?? null,
            'fecha'            => $hoy,
            'tipo_marcacion'   => $tipo,
            'hora_registro'    => date('H:i:s'),
            'metodo_marcacion' => 'kiosco',
            'foto_evidencia'   => $foto,
            'estado'           => 'validado',
            'observaciones'    => '[MARCATIÓN DESDE KIOSCO FÍSICO]',
        ]);

        echo json_encode([
            'success' => true, 
            'message' => "¡Marcación registrada!",
            'nombre' => $usuario['nombre_completo'],
            'tipo' => ucfirst(str_replace('_', ' ', $tipo)),
            'foto' => $usuario['foto_perfil']
        ]);
        exit;
    }
}
