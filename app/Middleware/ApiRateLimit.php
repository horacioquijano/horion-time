<?php
namespace App\Middleware;

class ApiRateLimit {
    /**
     * Límites por endpoint (peticiones por minuto)
     */
    private static $limits = [
        'POST /api/auth/login' => 10,
        'POST /api/auth/registro-marcacion' => 30,
        'GET /api/usuarios' => 60,
        'GET /api/reportes' => 20,
        'default' => 100
    ];

    public static function check($db, $identificador, $endpoint, $metodo) {
        $key = "$metodo $endpoint";
        $limite = self::$limits[$key] ?? self::$limits['default'];
        
        $ventanaInicio = date('Y-m-d H:i:00', time()); // Ventana de 1 minuto
        
        $stmt = $db->prepare("
            SELECT peticiones FROM api_rate_limit 
            WHERE identificador = :id AND endpoint = :ep AND ventana_inicio = :ventana
        ");
        $stmt->execute([':id' => $identificador, ':ep' => $endpoint, ':ventana' => $ventanaInicio]);
        $registro = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($registro && $registro['peticiones'] >= $limite) {
            ApiAuth::sendError(429, 'Demasiadas peticiones. Intenta en 1 minuto.', [
                'limite' => $limite,
                'reintentar_en' => 60
            ]);
        }

        // Insertar o actualizar
        if ($registro) {
            $db->prepare("UPDATE api_rate_limit SET peticiones = peticiones + 1 WHERE id = :id")
               ->execute([':id' => $registro['id']]); // Necesitaría el ID, simplificado abajo
        }
        
        $db->prepare("
            INSERT INTO api_rate_limit (identificador, endpoint, peticiones, ventana_inicio) 
            VALUES (:id, :ep, 1, :ventana)
            ON DUPLICATE KEY UPDATE peticiones = peticiones + 1
        ")->execute([':id' => $identificador, ':ep' => $endpoint, ':ventana' => $ventanaInicio]);
    }
}