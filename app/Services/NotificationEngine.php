<?php
namespace App\Services;
use PDO;

class NotificationEngine {
    public $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Enviar notificación In-App (Instantánea)
     */
    public function sendInApp($empresa_id, $usuario_id, $tipo, $titulo, $mensaje, $prioridad = 1, $metadata = null) {
        $stmt = $this->db->prepare("
            INSERT INTO alertas (empresa_id, usuario_id, tipo, canal, titulo, mensaje, prioridad, metadata) 
            VALUES (:eid, :uid, :tipo, 'in_app', :titulo, :mensaje, :prioridad, :meta)
        ");
        return $stmt->execute([
            ':eid' => $empresa_id, ':uid' => $usuario_id, ':tipo' => $tipo,
            ':titulo' => $titulo, ':mensaje' => $mensaje, ':prioridad' => $prioridad,
            ':meta' => $metadata ? json_encode($metadata) : null
        ]);
    }

    /**
     * Encolar notificación por Email (Asíncrona)
     */
    public function queueEmail($email, $asunto, $cuerpo) {
        $stmt = $this->db->prepare("
            INSERT INTO cola_notificaciones (tipo_envio, destinatario_email, asunto, cuerpo) 
            VALUES ('email', :email, :asunto, :cuerpo)
        ");
        return $stmt->execute([':email' => $email, ':asunto' => $asunto, ':cuerpo' => $cuerpo]);
    }

    /**
     * Encolar notificación Push (Asíncrona)
     */
    public function queuePush($push_token, $titulo, $cuerpo) {
        $stmt = $this->db->prepare("
            INSERT INTO cola_notificaciones (tipo_envio, destinatario_push_token, asunto, cuerpo) 
            VALUES ('push', :token, :titulo, :cuerpo)
        ");
        return $stmt->execute([':token' => $push_token, ':titulo' => $titulo, ':cuerpo' => $cuerpo]);
    }

    /**
     * Procesar cola de notificaciones (Ejecutar vía Cron Job)
     */
    public function procesarCola($limite = 50) {
        $stmt = $this->db->prepare("
            SELECT * FROM cola_notificaciones 
            WHERE estado = 'pendiente' AND intentos < max_intentos 
            LIMIT :limite
        ");
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        $pendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($pendientes as $notificacion) {
            $exito = false;
            try {
                if ($notificacion['tipo_envio'] === 'email') {
                    $exito = $this->enviarEmailReal($notificacion);
                } elseif ($notificacion['tipo_envio'] === 'push') {
                    $exito = $this->enviarPushReal($notificacion);
                }
            } catch (\Exception $e) {
                $exito = false;
                $error = $e->getMessage();
            }

            $nuevo_estado = $exito ? 'enviado' : 'pendiente';
            $nuevos_intentos = $notificacion['intentos'] + 1;
            $error_msg = $error ?? ($notificacion['ultimo_error'] ?? null);

            $update = $this->db->prepare("
                UPDATE cola_notificaciones 
                SET intentos = :intentos, estado = :estado, ultimo_error = :error, procesado_en = NOW() 
                WHERE id = :id
            ");
            $update->execute([
                ':intentos' => $nuevos_intentos, ':estado' => $nuevo_estado, 
                ':error' => $error_msg, ':id' => $notificacion['id']
            ]);
        }
        return count($pendientes);
    }

    private function enviarEmailReal($notificacion) {
        // TODO: Implementar PHPMailer aquí
        // $mail = new PHPMailer(true); ...
        // return $mail->send();
        error_log("Email enviado a {$notificacion['destinatario_email']}: {$notificacion['asunto']}");
        return true; // Simulación exitosa
    }

    private function enviarPushReal($notificacion) {
        // TODO: Implementar Web Push API (Minishlink/web-push) aquí
        error_log("Push enviado a token: {$notificacion['destinatario_push_token']}");
        return true; // Simulación exitosa
    }
}