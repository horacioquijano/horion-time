<?php
namespace App\Controllers;
use App\Helpers\Security;

class NotificacionController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /** Centro de notificaciones con filtros */
    public function index() {
        $usuario_id = $_SESSION['usuario_id'] ?? 1;
        $empresa_id = $_SESSION['empresa_id'] ?? 1;
        $modo_global = (bool)($_SESSION['modo_global'] ?? false);
        
        // Detectar columnas reales de la tabla alertas
        $cols = $this->db->query("SHOW COLUMNS FROM alertas")->fetchAll(\PDO::FETCH_COLUMN);
        $colLeido = in_array('leido', $cols) ? 'leido' : (in_array('leida', $cols) ? 'leida' : 'read');
        $colFecha = in_array('fecha_creacion', $cols) ? 'fecha_creacion' : (in_array('created_at', $cols) ? 'created_at' : 'fecha');
        $colTipo = in_array('tipo', $cols) ? 'tipo' : (in_array('categoria', $cols) ? 'categoria' : 'type');
        $colTitulo = in_array('titulo', $cols) ? 'titulo' : (in_array('title', $cols) ? 'title' : 'asunto');
        $colMensaje = in_array('mensaje', $cols) ? 'mensaje' : (in_array('message', $cols) ? 'message' : 'descripcion');
        $colUsuario = in_array('usuario_id', $cols) ? 'usuario_id' : null;
        $colEmpresa = in_array('empresa_id', $cols) ? 'empresa_id' : null;
        
        // Filtros
        $filtro_tipo = $_GET['tipo'] ?? '';
        $filtro_estado = $_GET['estado'] ?? 'pendientes'; // pendientes, leidas, todas
        
        $sql = "SELECT * FROM alertas WHERE 1=1";
        $params = [];
        
        if ($colEmpresa && !$modo_global && $empresa_id) {
            $sql .= " AND `$colEmpresa` = ?";
            $params[] = (int)$empresa_id;
        }
        if ($colUsuario) {
            $sql .= " AND (`$colUsuario` = ? OR `$colUsuario` IS NULL)";
            $params[] = (int)$usuario_id;
        }
        if ($filtro_tipo !== '') {
            $sql .= " AND `$colTipo` = ?";
            $params[] = $filtro_tipo;
        }
        if ($filtro_estado === 'pendientes' && $colLeido) {
            $sql .= " AND `$colLeido` = 0";
        } elseif ($filtro_estado === 'leidas' && $colLeido) {
            $sql .= " AND `$colLeido` = 1";
        }
        
        $sql .= " ORDER BY `$colFecha` DESC LIMIT 200";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $notificaciones = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Contar por tipo para los filtros
        $sql_count = "SELECT `$colTipo`, COUNT(*) as total FROM alertas WHERE 1=1";
        $params_count = [];
        if ($colEmpresa && !$modo_global && $empresa_id) {
            $sql_count .= " AND `$colEmpresa` = ?";
            $params_count[] = (int)$empresa_id;
        }
        if ($colUsuario) {
            $sql_count .= " AND (`$colUsuario` = ? OR `$colUsuario` IS NULL)";
            $params_count[] = (int)$usuario_id;
        }
        $sql_count .= " GROUP BY `$colTipo`";
        $stmt_count = $this->db->prepare($sql_count);
        $stmt_count->execute($params_count);
        $conteos = [];
        foreach ($stmt_count->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $conteos[$row[$colTipo]] = (int)$row['total'];
        }
        
        // Obtener preferencias
        $preferencias = ['recibir_email_alertas' => true, 'recibir_email_info' => false, 'recibir_push_alertas' => true, 'recibir_push_info' => false, 'resumen_diario_email' => false];
        try {
            $stmt2 = $this->db->prepare("SELECT * FROM notificaciones_usuario WHERE usuario_id = ? LIMIT 1");
            $stmt2->execute([(int)$usuario_id]);
            $pref_row = $stmt2->fetch(\PDO::FETCH_ASSOC);
            if ($pref_row) $preferencias = $pref_row;
        } catch (\Throwable $e) {}
        
        $csrf_token = Security::generateCsrfToken();
        require_once __DIR__ . '/../Views/notificaciones/index.php';
    }

    /** API: Marcar como leída */
    public function markAsRead() {
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success' => false]); exit; }
        
        try {
            Security::validateCsrfToken($_POST['csrf_token'] ?? '');
            $id = (int)($_POST['id'] ?? 0);
            $usuario_id = $_SESSION['usuario_id'] ?? 1;
            
            $cols = $this->db->query("SHOW COLUMNS FROM alertas")->fetchAll(\PDO::FETCH_COLUMN);
            $colLeido = in_array('leido', $cols) ? 'leido' : (in_array('leida', $cols) ? 'leida' : 'read');
            $colUsuario = in_array('usuario_id', $cols) ? 'usuario_id' : null;
            
            if (!$colLeido) { echo json_encode(['success' => false, 'message' => 'Columna de estado no encontrada']); exit; }
            
            $sql = "UPDATE alertas SET `$colLeido` = 1 WHERE id = ?";
            $params = [$id];
            if ($colUsuario) {
                $sql .= " AND (`$colUsuario` = ? OR `$colUsuario` IS NULL)";
                $params[] = (int)$usuario_id;
            }
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            echo json_encode(['success' => true]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    /** API: Marcar todas como leídas */
    public function markAllAsRead() {
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success' => false]); exit; }
        
        try {
            Security::validateCsrfToken($_POST['csrf_token'] ?? '');
            $usuario_id = $_SESSION['usuario_id'] ?? 1;
            $empresa_id = $_SESSION['empresa_id'] ?? 1;
            $modo_global = (bool)($_SESSION['modo_global'] ?? false);
            
            $cols = $this->db->query("SHOW COLUMNS FROM alertas")->fetchAll(\PDO::FETCH_COLUMN);
            $colLeido = in_array('leido', $cols) ? 'leido' : (in_array('leida', $cols) ? 'leida' : 'read');
            $colUsuario = in_array('usuario_id', $cols) ? 'usuario_id' : null;
            $colEmpresa = in_array('empresa_id', $cols) ? 'empresa_id' : null;
            
            if (!$colLeido) { echo json_encode(['success' => false, 'message' => 'Columna de estado no encontrada']); exit; }
            
            $sql = "UPDATE alertas SET `$colLeido` = 1 WHERE `$colLeido` = 0";
            $params = [];
            if ($colEmpresa && !$modo_global && $empresa_id) {
                $sql .= " AND `$colEmpresa` = ?";
                $params[] = (int)$empresa_id;
            }
            if ($colUsuario) {
                $sql .= " AND (`$colUsuario` = ? OR `$colUsuario` IS NULL)";
                $params[] = (int)$usuario_id;
            }
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            echo json_encode(['success' => true, 'affected' => $stmt->rowCount()]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    /** Actualizar preferencias */
    public function updatePreferences() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /horion-time/public/notificaciones');
            exit;
        }
        try {
            Security::validateCsrfToken($_POST['csrf_token'] ?? '');
            $usuario_id = (int)($_SESSION['usuario_id'] ?? 1);
            
            $stmt = $this->db->prepare("
                INSERT INTO notificaciones_usuario 
                (usuario_id, recibir_email_alertas, recibir_email_info, recibir_push_alertas, recibir_push_info, resumen_diario_email) 
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                recibir_email_alertas = VALUES(recibir_email_alertas), 
                recibir_email_info = VALUES(recibir_email_info),
                recibir_push_alertas = VALUES(recibir_push_alertas), 
                recibir_push_info = VALUES(recibir_push_info), 
                resumen_diario_email = VALUES(resumen_diario_email)
            ");
            $stmt->execute([
                $usuario_id,
                isset($_POST['email_alertas']) ? 1 : 0,
                isset($_POST['email_info']) ? 1 : 0,
                isset($_POST['push_alertas']) ? 1 : 0,
                isset($_POST['push_info']) ? 1 : 0,
                isset($_POST['resumen_diario']) ? 1 : 0
            ]);
            
            header('Location: /horion-time/public/notificaciones?success=1');
            exit;
        } catch (\Throwable $e) {
            header('Location: /horion-time/public/notificaciones?error=' . urlencode($e->getMessage()));
            exit;
        }
    }
}
