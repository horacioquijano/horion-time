<?php
namespace App\Controllers\Api;
use App\Middleware\ApiAuth;

class ReportesApiController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * GET /api/reportes/asistencia
     */
    public function getAsistencia($auth) {
        $fechaInicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
        $fechaFin = $_GET['fecha_fin'] ?? date('Y-m-d');
        $usuarioId = $_GET['usuario_id'] ?? null;

        $sql = "SELECT u.nombre_completo as empleado, ra.fecha, 
                       GROUP_CONCAT(CONCAT(ra.tipo_marcacion, ':', ra.hora_registro) ORDER BY ra.hora_registro) as marcaciones
                FROM registros_asistencia ra
                JOIN usuarios u ON ra.usuario_id = u.id
                WHERE ra.empresa_id = :eid AND ra.fecha BETWEEN :fi AND :ff";
        
        $params = [':eid' => $auth['empresa_id'], ':fi' => $fechaInicio, ':ff' => $fechaFin];
        
        if ($usuarioId) {
            $sql .= " AND ra.usuario_id = :uid";
            $params[':uid'] = $usuarioId;
        }
        
        $sql .= " GROUP BY ra.usuario_id, ra.fecha ORDER BY ra.fecha DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => [
                'empresa_id' => $auth['empresa_id'],
                'periodo' => ['inicio' => $fechaInicio, 'fin' => $fechaFin],
                'total' => count($data),
                'registros' => $data
            ],
            'meta' => [
                'page' => 1,
                'per_page' => 100,
                'total_pages' => 1
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}