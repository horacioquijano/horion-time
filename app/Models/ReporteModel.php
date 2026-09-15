<?php
namespace App\Models;
use PDO;

class ReporteModel {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Reporte 1: Asistencia General
     */
    public function getAsistenciaGeneral($filters) {
        $sql = "SELECT 
                    u.identificacion,
                    u.nombre_completo as empleado,
                    d.nombre as departamento,
                    s.nombre as sede,
                    ra.fecha,
                    MIN(CASE WHEN ra.tipo_marcacion = 'entrada' THEN ra.hora_registro END) as entrada,
                    MIN(CASE WHEN ra.tipo_marcacion = 'salida_almuerzo' THEN ra.hora_registro END) as salida_almuerzo,
                    MAX(CASE WHEN ra.tipo_marcacion = 'regreso_almuerzo' THEN ra.hora_registro END) as regreso_almuerzo,
                    MAX(CASE WHEN ra.tipo_marcacion = 'salida' THEN ra.hora_registro END) as salida,
                    ra.estado
                FROM registros_asistencia ra
                INNER JOIN usuarios u ON ra.usuario_id = u.id
                LEFT JOIN departamentos d ON u.departamento_id = d.id
                LEFT JOIN sedes s ON ra.sede_id = s.id
                WHERE ra.empresa_id = :empresa_id";
        
        $params = [':empresa_id' => $filters['empresa_id']];
        
        if (!empty($filters['fecha_inicio'])) {
            $sql .= " AND ra.fecha >= :fecha_inicio";
            $params[':fecha_inicio'] = $filters['fecha_inicio'];
        }
        if (!empty($filters['fecha_fin'])) {
            $sql .= " AND ra.fecha <= :fecha_fin";
            $params[':fecha_fin'] = $filters['fecha_fin'];
        }
        if (!empty($filters['usuario_id'])) {
            $sql .= " AND ra.usuario_id = :usuario_id";
            $params[':usuario_id'] = $filters['usuario_id'];
        }
        
        $sql .= " GROUP BY ra.usuario_id, ra.fecha ORDER BY ra.fecha DESC, u.nombre_completo ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Reporte 2: Tardanzas
     */
    public function getTardanzas($filters) {
        $sql = "SELECT 
                    u.identificacion,
                    u.nombre_completo as empleado,
                    ra.fecha,
                    h.jornada_diurna_inicio as hora_programada,
                    ra.hora_registro as hora_real,
                    TIMESTAMPDIFF(MINUTE, h.jornada_diurna_inicio, ra.hora_registro) as minutos_tarde
                FROM registros_asistencia ra
                INNER JOIN usuarios u ON ra.usuario_id = u.id
                LEFT JOIN horarios h ON u.horario_asignado_id = h.id
                WHERE ra.empresa_id = :empresa_id 
                AND ra.tipo_marcacion = 'entrada'
                AND ra.hora_registro > TIME_ADD(h.jornada_diurna_inicio, INTERVAL h.tolerancia_entrada MINUTE)";
        
        $params = [':empresa_id' => $filters['empresa_id']];
        
        if (!empty($filters['fecha_inicio'])) {
            $sql .= " AND ra.fecha >= :fecha_inicio";
            $params[':fecha_inicio'] = $filters['fecha_inicio'];
        }
        if (!empty($filters['fecha_fin'])) {
            $sql .= " AND ra.fecha <= :fecha_fin";
            $params[':fecha_fin'] = $filters['fecha_fin'];
        }
        
        $sql .= " ORDER BY ra.fecha DESC, minutos_tarde DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Reporte 3: Horas Extras
     */
    public function getHorasExtras($filters) {
        $sql = "SELECT 
                    u.identificacion,
                    u.nombre_completo as empleado,
                    he.fecha,
                    he.tipo,
                    he.hora_inicio_programada,
                    he.hora_fin_programada,
                    he.horas_solicitadas,
                    he.horas_aprobadas,
                    he.estado,
                    he.valor_total
                FROM horas_extras he
                INNER JOIN usuarios u ON he.usuario_id = u.id
                WHERE he.empresa_id = :empresa_id";
        
        $params = [':empresa_id' => $filters['empresa_id']];
        
        if (!empty($filters['fecha_inicio'])) {
            $sql .= " AND he.fecha >= :fecha_inicio";
            $params[':fecha_inicio'] = $filters['fecha_inicio'];
        }
        if (!empty($filters['fecha_fin'])) {
            $sql .= " AND he.fecha <= :fecha_fin";
            $params[':fecha_fin'] = $filters['fecha_fin'];
        }
        
        $sql .= " ORDER BY he.fecha DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Reporte 4: Ausencias e Incidencias
     */
    public function getIncidencias($filters) {
        $sql = "SELECT 
                    u.identificacion,
                    u.nombre_completo as empleado,
                    n.tipo,
                    n.fecha_inicio,
                    n.fecha_fin,
                    n.dias_calculados as dias,
                    n.motivo,
                    n.estado,
                    a.nombre_completo as aprobador
                FROM novedades n
                INNER JOIN usuarios u ON n.usuario_id = u.id
                LEFT JOIN usuarios a ON n.aprobador_id = a.id
                WHERE n.empresa_id = :empresa_id";
        
        $params = [':empresa_id' => $filters['empresa_id']];
        
        if (!empty($filters['fecha_inicio'])) {
            $sql .= " AND n.fecha_inicio >= :fecha_inicio";
            $params[':fecha_inicio'] = $filters['fecha_inicio'];
        }
        if (!empty($filters['fecha_fin'])) {
            $sql .= " AND n.fecha_fin <= :fecha_fin";
            $params[':fecha_fin'] = $filters['fecha_fin'];
        }
        
        $sql .= " ORDER BY n.fecha_inicio DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Reporte 5: Consolidado Mensual
     */
    public function getConsolidadoMensual($filters) {
        $sql = "SELECT 
                    u.identificacion,
                    u.nombre_completo as empleado,
                    COUNT(DISTINCT CASE WHEN ra.tipo_marcacion = 'entrada' THEN ra.fecha END) as dias_trabajados,
                    COUNT(ra.id) as total_marcaciones,
                    SUM(CASE WHEN he.estado = 'aprobada' THEN he.horas_aprobadas ELSE 0 END) as horas_extras,
                    SUM(CASE WHEN n.estado = 'aprobada' THEN n.dias_calculados ELSE 0 END) as dias_novedades
                FROM usuarios u
                LEFT JOIN registros_asistencia ra ON u.id = ra.usuario_id AND ra.fecha BETWEEN :fecha_inicio AND :fecha_fin
                LEFT JOIN horas_extras he ON u.id = he.usuario_id AND he.fecha BETWEEN :fecha_inicio2 AND :fecha_fin2 AND he.estado = 'aprobada'
                LEFT JOIN novedades n ON u.id = n.usuario_id AND n.fecha_inicio <= :fecha_fin3 AND n.fecha_fin >= :fecha_inicio3 AND n.estado = 'aprobada'
                WHERE u.empresa_id = :empresa_id AND u.estado = 'activo'
                GROUP BY u.id
                ORDER BY u.nombre_completo ASC";
        
        $params = [
            ':empresa_id' => $filters['empresa_id'],
            ':fecha_inicio' => $filters['fecha_inicio'], ':fecha_fin' => $filters['fecha_fin'],
            ':fecha_inicio2' => $filters['fecha_inicio'], ':fecha_fin2' => $filters['fecha_fin'],
            ':fecha_inicio3' => $filters['fecha_inicio'], ':fecha_fin3' => $filters['fecha_fin']
        ];
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener lista de empleados para filtros
     */
    public function getEmpleados($empresa_id) {
        $stmt = $this->db->prepare("SELECT id, nombre_completo FROM usuarios WHERE empresa_id = :empresa_id AND estado = 'activo' ORDER BY nombre_completo");
        $stmt->execute([':empresa_id' => $empresa_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Registrar log de reporte
     */
    public function logReporte($usuario_id, $empresa_id, $tipo, $filtros, $formato, $registros) {
        $stmt = $this->db->prepare("INSERT INTO reportes_log (usuario_id, empresa_id, tipo_reporte, filtros_aplicados, formato_exportacion, registros_generados) VALUES (:uid, :eid, :tipo, :filtros, :formato, :reg)");
        return $stmt->execute([
            ':uid' => $usuario_id, ':eid' => $empresa_id, ':tipo' => $tipo,
            ':filtros' => json_encode($filtros), ':formato' => $formato, ':reg' => $registros
        ]);
    }
}