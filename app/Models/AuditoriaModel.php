<?php
namespace App\Models;
use PDO;

class AuditoriaModel {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Obtener logs con filtros y paginación
     */
    public function getLogs($filters, $page = 1, $limit = 50) {
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT al.*, u.nombre_completo as usuario_nombre, u.identificacion as usuario_id_doc
                FROM audit_log al
                LEFT JOIN usuarios u ON al.usuario_id = u.id
                WHERE 1=1";
        
        $params = [];
        
        if (!empty($filters['usuario_id'])) {
            $sql .= " AND al.usuario_id = :usuario_id";
            $params[':usuario_id'] = $filters['usuario_id'];
        }
        if (!empty($filters['accion'])) {
            $sql .= " AND al.accion = :accion";
            $params[':accion'] = $filters['accion'];
        }
        if (!empty($filters['tabla'])) {
            $sql .= " AND al.tabla_afectada = :tabla";
            $params[':tabla'] = $filters['tabla'];
        }
        if (!empty($filters['fecha_inicio'])) {
            $sql .= " AND al.fecha_hora >= :fecha_inicio";
            $params[':fecha_inicio'] = $filters['fecha_inicio'];
        }
        if (!empty($filters['fecha_fin'])) {
            $sql .= " AND al.fecha_hora <= :fecha_fin";
            $params[':fecha_fin'] = $filters['fecha_fin'];
        }
        if (!empty($filters['busqueda'])) {
            $sql .= " AND (u.nombre_completo LIKE :busq OR al.tabla_afectada LIKE :busq OR al.ip_address LIKE :busq)";
            $params[':busq'] = '%' . $filters['busqueda'] . '%';
        }
        
        $sql .= " ORDER BY al.fecha_hora DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $val) $stmt->bindValue($key, $val);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Contar total de logs para paginación
     */
    public function getTotalLogs($filters) {
        $sql = "SELECT COUNT(*) FROM audit_log al LEFT JOIN usuarios u ON al.usuario_id = u.id WHERE 1=1";
        $params = [];
        
        // (Mismos filtros que arriba, simplificado para el ejemplo)
        if (!empty($filters['accion'])) { $sql .= " AND al.accion = :accion"; $params[':accion'] = $filters['accion']; }
        if (!empty($filters['tabla'])) { $sql .= " AND al.tabla_afectada = :tabla"; $params[':tabla'] = $filters['tabla']; }
        
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $val) $stmt->bindValue($key, $val);
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    /**
     * Obtener resumen de acciones
     */
    public function getResumen() {
        $stmt = $this->db->query("
            SELECT accion, COUNT(*) as total 
            FROM audit_log 
            WHERE fecha_hora >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY accion
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}