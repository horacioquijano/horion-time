<?php
namespace App\Helpers;
use PDO;

class Audit {
    /**
     * Registrar una acción en el log de auditoría
     */
    public static function log($db, $accion, $tabla, $registro_id = null, $valores_anteriores = null, $valores_nuevos = null, $usuario_id = null) {
        try {
            $usuario_id = $usuario_id ?? ($_SESSION['usuario_id'] ?? null);
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido';

            $sql = "INSERT INTO audit_log 
                    (usuario_id, accion, tabla_afectada, registro_id, valores_anteriores, valores_nuevos, ip_address, user_agent) 
                    VALUES (:uid, :accion, :tabla, :rid, :old, :new, :ip, :ua)";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':uid' => $usuario_id,
                ':accion' => $accion,
                ':tabla' => $tabla,
                ':rid' => $registro_id,
                ':old' => $valores_anteriores ? json_encode($valores_anteriores, JSON_UNESCAPED_UNICODE) : null,
                ':new' => $valores_nuevos ? json_encode($valores_nuevos, JSON_UNESCAPED_UNICODE) : null,
                ':ip' => $ip,
                ':ua' => $ua
            ]);
        } catch (\Exception $e) {
            // En producción, loguear en archivo en lugar de fallar la operación principal
            error_log("Audit Log Error: " . $e->getMessage());
        }
    }
}