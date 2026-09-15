<?php
namespace App\Models;
use PDO;

class ConfiguracionModel {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function getByEmpresa($empresa_id) {
        $stmt = $this->db->prepare("SELECT * FROM configuracion_empresa WHERE empresa_id = :eid");
        $stmt->execute([':eid' => $empresa_id]);
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Decodificar campos JSON automáticamente
        if ($config) {
            $config['festivos_personalizados'] = json_decode($config['festivos_personalizados'] ?? '[]', true);
            $config['dias_descanso'] = json_decode($config['dias_descanso'] ?? '[0,6]', true);
            $config['motor_reglas'] = json_decode($config['motor_reglas'] ?? '{}', true);
        }
        return $config;
    }

    public function save($empresa_id, $data) {
        // Preparar campos JSON
        $festivos = json_encode($data['festivos'] ?? []);
        $descanso = json_encode($data['descanso'] ?? [0, 6]);
        $reglas = json_encode($data['reglas'] ?? []);

        $sql = "INSERT INTO configuracion_empresa 
                (empresa_id, jornada_semanal_horas, tolerancia_entrada_min, tolerancia_salida_min, 
                 permitir_marcacion_temprana, requerir_foto_marcacion, requerir_geolocalizacion, 
                 radio_gps_metros, festivos_personalizados, dias_descanso, 
                 notificaciones_email, auditoria_activa, motor_reglas) 
                VALUES 
                (:eid, :horas, :tol_ent, :tol_sal, :marc_temprana, :req_foto, :req_gps, 
                 :radio_gps, :festivos, :descanso, :notif_email, :auditoria, :reglas)
                ON DUPLICATE KEY UPDATE
                jornada_semanal_horas = VALUES(jornada_semanal_horas),
                tolerancia_entrada_min = VALUES(tolerancia_entrada_min),
                tolerancia_salida_min = VALUES(tolerancia_salida_min),
                permitir_marcacion_temprana = VALUES(permitir_marcacion_temprana),
                requerir_foto_marcacion = VALUES(requerir_foto_marcacion),
                requerir_geolocalizacion = VALUES(requerir_geolocalizacion),
                radio_gps_metros = VALUES(radio_gps_metros),
                festivos_personalizados = VALUES(festivos_personalizados),
                dias_descanso = VALUES(dias_descanso),
                notificaciones_email = VALUES(notificaciones_email),
                auditoria_activa = VALUES(auditoria_activa),
                motor_reglas = VALUES(motor_reglas)";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':eid' => $empresa_id,
            ':horas' => $data['horas_semanales'],
            ':tol_ent' => $data['tolerancia_entrada'],
            ':tol_sal' => $data['tolerancia_salida'],
            ':marc_temprana' => isset($data['marcacion_temprana']) ? 1 : 0,
            ':req_foto' => isset($data['requerir_foto']) ? 1 : 0,
            ':req_gps' => isset($data['requerir_gps']) ? 1 : 0,
            ':radio_gps' => $data['radio_gps'],
            ':festivos' => $festivos,
            ':descanso' => $descanso,
            ':notif_email' => isset($data['notificaciones_email']) ? 1 : 0,
            ':auditoria' => isset($data['auditoria_activa']) ? 1 : 0,
            ':reglas' => $reglas
        ]);
    }
}