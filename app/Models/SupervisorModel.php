<?php
namespace App\Models;
use PDO;

class SupervisorModel {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Obtener IDs de usuarios bajo supervisión directa (y por delegación)
     */
    public function getEquipoIds($supervisor_id) {
        // Subordinados directos
        $stmt = $this->db->prepare("SELECT id FROM usuarios WHERE jefe_inmediato_id = :sid AND estado = 'activo'");
        $stmt->execute([':sid' => $supervisor_id]);
        $directos = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id');

        // Delegaciones activas donde este supervisor fue designado
        $stmt2 = $this->db->prepare("
            SELECT u.id FROM usuarios u
            INNER JOIN delegaciones_supervision d ON u.jefe_inmediato_id = d.supervisor_original_id
            WHERE d.supervisor_delegado_id = :sid AND d.estado = 'activa' 
            AND CURDATE() BETWEEN d.fecha_inicio AND d.fecha_fin
            AND u.estado = 'activo'
        ");
        $stmt2->execute([':sid' => $supervisor_id]);
        $delegados = array_column($stmt2->fetchAll(PDO::FETCH_ASSOC), 'id');

        return array_unique(array_merge($directos, $delegados));
    }

    /**
     * Obtener detalle del equipo con estado del día
     */
    public function getEquipoDetalle($supervisor_id, $fecha = null) {
        $fecha = $fecha ?? date('Y-m-d');
        $equipoIds = $this->getEquipoIds($supervisor_id);
        
        if (empty($equipoIds)) return [];

        $placeholders = implode(',', array_fill(0, count($equipoIds), '?'));
        
        $sql = "SELECT 
                    u.id, u.nombre_completo, u.identificacion, u.email, u.foto_perfil,
                    d.nombre as departamento, c.nombre as cargo,
                    h.nombre as horario_nombre, h.jornada_diurna_inicio, h.tolerancia_entrada,
                    MAX(CASE WHEN ra.tipo_marcacion = 'entrada' THEN ra.hora_registro END) as entrada,
                    MAX(CASE WHEN ra.tipo_marcacion = 'salida' THEN ra.hora_registro END) as salida
                FROM usuarios u
                LEFT JOIN departamentos d ON u.departamento_id = d.id
                LEFT JOIN cargos c ON u.cargo_id = c.id
                LEFT JOIN horarios h ON u.horario_asignado_id = h.id
                LEFT JOIN registros_asistencia ra ON u.id = ra.usuario_id AND ra.fecha = :fecha
                WHERE u.id IN ($placeholders) AND u.estado = 'activo'
                GROUP BY u.id
                ORDER BY u.nombre_completo ASC";

        $params = array_merge([$fecha], $equipoIds);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Resumen del equipo para el día
     */
    public function getResumenEquipo($supervisor_id, $fecha = null) {
        $fecha = $fecha ?? date('Y-m-d');
        $equipo = $this->getEquipoDetalle($supervisor_id, $fecha);
        
        $total = count($equipo);
        $presentes = 0;
        $tardanzas = 0;
        $ausentes = 0;
        $sinMarcar = 0;

        foreach ($equipo as $emp) {
            if ($emp['entrada']) {
                $presentes++;
                // Detectar tardanza
                if ($emp['jornada_diurna_inicio'] && $emp['tolerancia_entrada']) {
                    $horaLimite = strtotime($emp['jornada_diurna_inicio']) + ($emp['tolerancia_entrada'] * 60);
                    if (strtotime($emp['entrada']) > $horaLimite) {
                        $tardanzas++;
                    }
                }
            } else {
                // Verificar si tiene novedad aprobada (ausencia justificada)
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) FROM novedades 
                    WHERE usuario_id = :uid AND estado = 'aprobada' 
                    AND :fecha BETWEEN fecha_inicio AND fecha_fin
                ");
                $stmt->execute([':uid' => $emp['id'], ':fecha' => $fecha]);
                if ($stmt->fetchColumn() > 0) {
                    // Ausente pero justificado, no cuenta como "sin marcar"
                } else {
                    $sinMarcar++;
                }
            }
        }

        $ausentes = $total - $presentes;

        return [
            'total' => $total,
            'presentes' => $presentes,
            'tardanzas' => $tardanzas,
            'ausentes' => $ausentes,
            'sin_marcar' => $sinMarcar,
            'porcentaje_asistencia' => $total > 0 ? round(($presentes / $total) * 100, 1) : 0
        ];
    }

    /**
     * Novedades pendientes del equipo
     */
    public function getNovedadesPendientesEquipo($supervisor_id) {
        $equipoIds = $this->getEquipoIds($supervisor_id);
        if (empty($equipoIds)) return [];

        $placeholders = implode(',', array_fill(0, count($equipoIds), '?'));
        $sql = "SELECT n.*, u.nombre_completo as empleado_nombre 
                FROM novedades n
                INNER JOIN usuarios u ON n.usuario_id = u.id
                WHERE n.usuario_id IN ($placeholders) AND n.estado = 'pendiente'
                ORDER BY n.fecha_solicitud DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($equipoIds);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Horas extras pendientes del equipo
     */
    public function getHorasExtrasPendientesEquipo($supervisor_id) {
        $equipoIds = $this->getEquipoIds($supervisor_id);
        if (empty($equipoIds)) return [];

        $placeholders = implode(',', array_fill(0, count($equipoIds), '?'));
        $sql = "SELECT he.*, u.nombre_completo as empleado_nombre 
                FROM horas_extras he
                INNER JOIN usuarios u ON he.usuario_id = u.id
                WHERE he.usuario_id IN ($placeholders) AND he.estado = 'pendiente'
                ORDER BY he.fecha DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($equipoIds);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Inconsistencias de marcación del equipo (últimos 7 días)
     */
    public function getInconsistencias($supervisor_id) {
        $equipoIds = $this->getEquipoIds($supervisor_id);
        if (empty($equipoIds)) return [];

        $placeholders = implode(',', array_fill(0, count($equipoIds), '?'));
        $sql = "SELECT 
                    ra.usuario_id, u.nombre_completo as empleado, ra.fecha,
                    GROUP_CONCAT(DISTINCT ra.tipo_marcacion ORDER BY ra.hora_registro) as marcaciones,
                    COUNT(ra.id) as total_marcaciones
                FROM registros_asistencia ra
                INNER JOIN usuarios u ON ra.usuario_id = u.id
                WHERE ra.usuario_id IN ($placeholders) 
                AND ra.fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                GROUP BY ra.usuario_id, ra.fecha
                HAVING total_marcaciones NOT IN (2, 4) -- Debería tener 2 (entrada+salida) o 4 (con almuerzos)
                ORDER BY ra.fecha DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($equipoIds);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}