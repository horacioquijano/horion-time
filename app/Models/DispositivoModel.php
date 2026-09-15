<?php
namespace App\Models;
use PDO;

class DispositivoModel {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function getAll($empresa_id) {
        $stmt = $this->db->prepare("
            SELECT d.*, s.nombre as sede_nombre 
            FROM dispositivos d 
            LEFT JOIN sedes s ON d.sede_id = s.id 
            WHERE d.empresa_id = :eid 
            ORDER BY d.nombre ASC
        ");
        $stmt->execute([':eid' => $empresa_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getByToken($token) {
        $stmt = $this->db->prepare("SELECT * FROM dispositivos WHERE token_kiosco = :token AND estado = 'activo'");
        $stmt->execute([':token' => $token]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create($data) {
        $token = bin2hex(random_bytes(32));
        $pin = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT); // PIN por defecto de 4 dígitos
        
        $sql = "INSERT INTO dispositivos 
                (empresa_id, sede_id, nombre, tipo, mac_address, modo_kiosco, pin_acceso, token_kiosco, qr_code_url, tiempo_inactividad_seg) 
                VALUES (:eid, :sid, :nombre, :tipo, :mac, :kiosco, :pin, :token, :qr, :inactividad)";
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':eid' => $data['empresa_id'],
            ':sid' => $data['sede_id'],
            ':nombre' => $data['nombre'],
            ':tipo' => $data['tipo'],
            ':mac' => $data['mac_address'] ?? null,
            ':kiosco' => isset($data['modo_kiosco']) ? 1 : 0,
            ':pin' => $pin,
            ':token' => $token,
            ':qr' => "https://app.horiontime.co/kiosco/{$token}",
            ':inactividad' => $data['tiempo_inactividad'] ?? 30
        ]);
        
        return $result ? $this->db->lastInsertId() : false;
    }

    public function delete($id, $userRole) {
        $allowedRoles = ['SuperAdmin', 'Admin_Empresa'];
        if (!in_array($userRole, $allowedRoles)) {
            throw new \Exception("Acceso denegado: Solo administradores pueden eliminar dispositivos.");
        }
        $stmt = $this->db->prepare("DELETE FROM dispositivos WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    public function updateLastActivity($id) {
        $stmt = $this->db->prepare("UPDATE dispositivos SET ultima_actividad = NOW() WHERE id = :id");
        $stmt->execute([':id' => $id]);
    }

    public function logAcceso($dispositivo_id, $usuario_id, $metodo, $identificacion = null) {
        $stmt = $this->db->prepare("
            INSERT INTO log_accesos_kiosco (dispositivo_id, usuario_id, identificacion_manual, metodo, ip_address) 
            VALUES (:did, :uid, :ident, :metodo, :ip)
        ");
        return $stmt->execute([
            ':did' => $dispositivo_id,
            ':uid' => $usuario_id,
            ':ident' => $identificacion,
            ':metodo' => $metodo,
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        ]);
    }
}