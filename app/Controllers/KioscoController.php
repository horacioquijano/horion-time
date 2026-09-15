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

    public function index($token) {
        $dispositivo = $this->dispositivoModel->getByToken($token);
        if (!$dispositivo) {
            http_response_code(404);
            die("Dispositivo no encontrado o inactivo.");
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
        
        echo json_encode(['success' => true, 'message' => 'Acceso concedido. Ingrese su identificación.']);
        exit;
    }

    // API: Identificar usuario y marcar
    public function identificar() {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);
        
        $stmt = $this->db->prepare("SELECT id, nombre_completo, foto_perfil FROM usuarios WHERE identificacion = :ident AND estado = 'activo'");
        $stmt->execute([':ident' => $data['identificacion']]);
        $usuario = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$usuario) {
            echo json_encode(['success' => false, 'message' => 'Empleado no encontrado.']);
            exit;
        }

        // Registrar marcación automática (Entrada o Salida según lógica)
        // Aquí simplificamos registrando una entrada
        $this->asistenciaModel->registrarMarcacion([
            'usuario_id' => $usuario['id'],
            'empresa_id' => $data['empresa_id'],
            'sede_id' => $data['sede_id'],
            'fecha' => date('Y-m-d'),
            'tipo_marcacion' => 'entrada', // Lógica real debería determinar el tipo
            'hora_registro' => date('H:i:s'),
            'metodo_marcacion' => 'pin',
            'estado' => 'validado'
        ]);

        echo json_encode([
            'success' => true, 
            'message' => "¡Bienvenido, {$usuario['nombre_completo']}!",
            'foto' => $usuario['foto_perfil']
        ]);
        exit;
    }
}