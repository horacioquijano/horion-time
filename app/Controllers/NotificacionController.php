<?php
namespace App\Controllers;
use App\Services\NotificationEngine;
use App\Helpers\Security;

class NotificacionController {
    private $engine;

    public function __construct($db) {
        $this->engine = new NotificationEngine($db);
    }

    /**
     * Centro de notificaciones
     */
    public function index() {
        $usuario_id = $_SESSION['usuario_id'] ?? 1;
        $empresa_id = $_SESSION['empresa_id'] ?? 1;
        
        // Obtener notificaciones
        $stmt = $this->engine->db->prepare("
            SELECT * FROM alertas 
            WHERE empresa_id = :eid AND (usuario_id = :uid OR usuario_id IS NULL)
            ORDER BY prioridad DESC, fecha_creacion DESC LIMIT 100
        ");
        $stmt->execute([':eid' => $empresa_id, ':uid' => $usuario_id]);
        $notificaciones = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Obtener preferencias
        $stmt2 = $this->engine->db->prepare("SELECT * FROM notificaciones_usuario WHERE usuario_id = :uid");
        $stmt2->execute([':uid' => $usuario_id]);
        $preferencias = $stmt2->fetch(\PDO::FETCH_ASSOC) ?: [
            'recibir_email_alertas' => true, 'recibir_email_info' => false,
            'recibir_push_alertas' => true, 'recibir_push_info' => false
        ];

        $csrf_token = Security::generateCsrfToken();
        require_once __DIR__ . '/../Views/notificaciones/index.php';
    }

    /**
     * API: Marcar como leída
     */
    public function markAsRead() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Security::validateCsrfToken($_POST['csrf_token'] ?? '');
            $id = $_POST['id'] ?? 0;
            $usuario_id = $_SESSION['usuario_id'] ?? 1;
            
            $stmt = $this->engine->db->prepare("UPDATE alertas SET leido = 1 WHERE id = :id AND (usuario_id = :uid OR usuario_id IS NULL)");
            $stmt->execute([':id' => $id, ':uid' => $usuario_id]);
            
            echo json_encode(['success' => true]);
            exit;
        }
    }

    /**
     * API: Marcar todas como leídas
     */
    public function markAllAsRead() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Security::validateCsrfToken($_POST['csrf_token'] ?? '');
            $usuario_id = $_SESSION['usuario_id'] ?? 1;
            $empresa_id = $_SESSION['empresa_id'] ?? 1;
            
            $stmt = $this->engine->db->prepare("UPDATE alertas SET leido = 1 WHERE empresa_id = :eid AND (usuario_id = :uid OR usuario_id IS NULL) AND leido = 0");
            $stmt->execute([':eid' => $empresa_id, ':uid' => $usuario_id]);
            
            echo json_encode(['success' => true, 'affected' => $stmt->rowCount()]);
            exit;
        }
    }

    /**
     * Actualizar preferencias
     */
    public function updatePreferences() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Security::validateCsrfToken($_POST['csrf_token'] ?? '');
            $usuario_id = $_SESSION['usuario_id'] ?? 1;
            
            $stmt = $this->engine->db->prepare("
                INSERT INTO notificaciones_usuario 
                (usuario_id, recibir_email_alertas, recibir_email_info, recibir_push_alertas, recibir_push_info, resumen_diario_email) 
                VALUES (:uid, :e_alert, :e_info, :p_alert, :p_info, :resumen)
                ON DUPLICATE KEY UPDATE 
                recibir_email_alertas = :e_alert, recibir_email_info = :e_info,
                recibir_push_alertas = :p_alert, recibir_push_info = :p_info, resumen_diario_email = :resumen
            ");
            
            $stmt->execute([
                ':uid' => $usuario_id,
                ':e_alert' => isset($_POST['email_alertas']) ? 1 : 0,
                ':e_info' => isset($_POST['email_info']) ? 1 : 0,
                ':p_alert' => isset($_POST['push_alertas']) ? 1 : 0,
                ':p_info' => isset($_POST['push_info']) ? 1 : 0,
                ':resumen' => isset($_POST['resumen_diario']) ? 1 : 0
            ]);
            
            header('Location: /horion-time/public/notificaciones?success=1');
            exit;
        }
    }
}