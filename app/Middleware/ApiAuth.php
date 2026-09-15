<?php
namespace App\Middleware;
use App\Helpers\JWT;

class ApiAuth {
    /**
     * Validar token JWT o API Token
     */
    public static function authenticate($db) {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

        if (empty($authHeader)) {
            self::sendError(401, 'Token de autenticación requerido');
        }

        // Soportar "Bearer TOKEN" o "Token TOKEN"
        $token = preg_replace('/^(Bearer|Token)\s+/i', '', $authHeader);

        try {
            // Intentar decodificar como JWT
            $payload = JWT::decode($token);
            
            // Verificar que el usuario existe y está activo
            $stmt = $db->prepare("SELECT u.*, e.nombre as empresa_nombre FROM usuarios u 
                                  LEFT JOIN empresas e ON u.empresa_id = e.id 
                                  WHERE u.id = :id AND u.estado = 'activo'");
            $stmt->execute([':id' => $payload['sub']]);
            $usuario = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$usuario) {
                self::sendError(401, 'Usuario no encontrado o inactivo');
            }

            return [
                'usuario_id' => $usuario['id'],
                'empresa_id' => $usuario['empresa_id'],
                'rol' => $payload['rol'] ?? 'Empleado',
                'email' => $usuario['email']
            ];
        } catch (\Exception $e) {
            // Si no es JWT, intentar como API Token
            return self::validateApiToken($db, $token);
        }
    }

    /**
     * Validar API Token de aplicación externa
     */
    private static function validateApiToken($db, $token) {
        $tokenHash = hash('sha256', $token);
        
        $stmt = $db->prepare("
            SELECT t.*, u.id as usuario_id, u.estado as usuario_estado 
            FROM api_tokens t 
            LEFT JOIN usuarios u ON t.usuario_id = u.id 
            WHERE t.token_hash = :hash AND t.estado = 'activo'
        ");
        $stmt->execute([':hash' => $tokenHash]);
        $apiToken = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$apiToken) {
            self::sendError(401, 'Token API inválido o revocado');
        }

        // Verificar expiración
        if ($apiToken['expira_en'] && strtotime($apiToken['expira_en']) < time()) {
            self::sendError(401, 'Token API expirado');
        }

        // Verificar IP restringida
        if ($apiToken['ip_restringida'] && $apiToken['ip_restringida'] !== ($_SERVER['REMOTE_ADDR'] ?? '')) {
            self::sendError(403, 'IP no autorizada para este token');
        }

        // Actualizar último uso
        $db->prepare("UPDATE api_tokens SET ultimo_uso = NOW() WHERE id = :id")
           ->execute([':id' => $apiToken['id']]);

        return [
            'usuario_id' => $apiToken['usuario_id'],
            'empresa_id' => $apiToken['empresa_id'],
            'rol' => 'API',
            'permisos' => json_decode($apiToken['permisos'] ?? '[]', true),
            'token_id' => $apiToken['id']
        ];
    }

    /**
     * Verificar permisos del token
     */
    public static function checkPermission($authData, $permisoRequerido) {
        if (!isset($authData['permisos'])) return true; // Tokens JWT tienen todos los permisos
        return in_array($permisoRequerido, $authData['permisos']);
    }

    public static function sendError($code, $message, $details = null) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details
            ],
            'timestamp' => date('c')
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}