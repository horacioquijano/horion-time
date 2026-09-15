<?php
namespace App\Controllers\Api;
use App\Helpers\JWT;
use App\Middleware\ApiAuth;

class AuthApiController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * POST /api/auth/login
     */
    public function login() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['email']) || empty($data['password'])) {
            ApiAuth::sendError(400, 'Email y contraseña requeridos');
        }

        $stmt = $this->db->prepare("
            SELECT u.*, r.nombre as rol_nombre, e.nombre as empresa_nombre 
            FROM usuarios u 
            LEFT JOIN roles r ON u.rol_id = r.id 
            LEFT JOIN empresas e ON u.empresa_id = e.id 
            WHERE u.email = :email AND u.estado = 'activo'
        ");
        $stmt->execute([':email' => $data['email']]);
        $usuario = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$usuario || !password_verify($data['password'], $usuario['password_hash'])) {
            ApiAuth::sendError(401, 'Credenciales inválidas');
        }

        // Actualizar último acceso
        $this->db->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = :id")
                 ->execute([':id' => $usuario['id']]);

        // Generar JWT
        $token = JWT::encode([
            'sub' => $usuario['id'],
            'email' => $usuario['email'],
            'rol' => $usuario['rol_nombre'],
            'empresa_id' => $usuario['empresa_id']
        ], 86400 * 7); // 7 días

        $this->logRequest('POST', '/api/auth/login', 200);

        echo json_encode([
            'success' => true,
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => 86400 * 7,
                'usuario' => [
                    'id' => $usuario['id'],
                    'nombre' => $usuario['nombre_completo'],
                    'email' => $usuario['email'],
                    'rol' => $usuario['rol_nombre'],
                    'empresa' => $usuario['empresa_nombre']
                ]
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * POST /api/auth/refresh
     */
    public function refresh() {
        $auth = ApiAuth::authenticate($this->db);
        $token = JWT::encode([
            'sub' => $auth['usuario_id'],
            'email' => $auth['email'],
            'rol' => $auth['rol'],
            'empresa_id' => $auth['empresa_id']
        ], 86400 * 7);

        echo json_encode(['success' => true, 'data' => ['token' => $token]]);
        exit;
    }

    private function logRequest($metodo, $endpoint, $status) {
        $stmt = $this->db->prepare("
            INSERT INTO api_log (metodo, endpoint, ip_address, user_agent, response_status, tiempo_respuesta_ms) 
            VALUES (:m, :e, :ip, :ua, :s, :t)
        ");
        $stmt->execute([
            ':m' => $metodo, ':e' => $endpoint, 
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':ua' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ':s' => $status, ':t' => 0
        ]);
    }
}