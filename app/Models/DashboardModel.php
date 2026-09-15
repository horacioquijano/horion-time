<?php
namespace App\Models;
use PDO;

class DashboardModel {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /** Condición segura de tardanza (compatible MariaDB, tolera horarios NULL) */
    private function condTardanza() {
        return "TIME(ra.hora_registro) > ADDTIME(TIME(h.jornada_diurna_inicio), SEC_TO_TIME(COALESCE(h.tolerancia_entrada, 0) * 60))";
    }

    /** SUPER ADMIN: Resumen global multiempresa */
    public function getResumenGlobal() {
        try {
            $stmt = $this->db->query("
                SELECT
                    COUNT(DISTINCT e.id) as total_empresas,
                    COUNT(DISTINCT CASE WHEN e.estado = 'activo' THEN e.id END) as empresas_activas,
                    COUNT(DISTINCT u.id) as total_usuarios,
                    COUNT(DISTINCT CASE WHEN u.estado = 'activo' THEN u.id END) as usuarios_activos
                FROM empresas e
                LEFT JOIN usuarios u ON e.id = u.empresa_id
            ");
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total_empresas'=>0,'empresas_activas'=>0,'total_usuarios'=>0,'usuarios_activos'=>0];
        } catch (\Throwable $e) {
            return ['total_empresas'=>0,'empresas_activas'=>0,'total_usuarios'=>0,'usuarios_activos'=>0];
        }
    }

    /** SUPER ADMIN: Asistencia global del día */
    public function getAsistenciaGlobalHoy() {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    COUNT(DISTINCT ra.usuario_id) as total_marcaciones,
                    COUNT(DISTINCT CASE WHEN ra.tipo_marcacion = 'entrada' THEN ra.usuario_id END) as entradas,
                    COUNT(DISTINCT CASE WHEN ra.estado = 'pendiente' THEN ra.usuario_id END) as pendientes
                FROM registros_asistencia ra
                WHERE ra.fecha = :fecha
            ");
            $stmt->execute([':fecha' => date('Y-m-d')]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['entradas'=>0,'pendientes'=>0];
        } catch (\Throwable $e) {
            return ['entradas'=>0,'pendientes'=>0];
        }
    }

    /** SUPER ADMIN: Asistencia por empresa (gráfico barras) */
    public function getAsistenciaPorEmpresa($fecha = null) {
        try {
            $fecha = $fecha ?? date('Y-m-d');
            $stmt = $this->db->prepare("
                SELECT
                    e.nombre as empresa,
                    COUNT(DISTINCT ra.usuario_id) as presentes,
                    (SELECT COUNT(*) FROM usuarios u WHERE u.empresa_id = e.id AND u.estado = 'activo') as total_empleados
                FROM empresas e
                LEFT JOIN registros_asistencia ra ON e.id = ra.empresa_id AND ra.fecha = :fecha AND ra.tipo_marcacion = 'entrada'
                WHERE e.estado = 'activo'
                GROUP BY e.id, e.nombre
                ORDER BY presentes DESC
                LIMIT 10
            ");
            $stmt->execute([':fecha' => $fecha]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** SUPER ADMIN: Tendencias semanales */
    public function getTendenciasSemanales() {
        try {
            $stmt = $this->db->query("
                SELECT
                    ra.fecha,
                    COUNT(DISTINCT ra.usuario_id) as total_marcaciones,
                    COUNT(DISTINCT CASE WHEN ra.tipo_marcacion = 'entrada' THEN ra.usuario_id END) as entradas
                FROM registros_asistencia ra
                WHERE ra.fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                GROUP BY ra.fecha
                ORDER BY ra.fecha ASC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** ADMIN EMPRESA: Resumen del día */
    public function getResumenDia($empresa_id, $fecha = null) {
        $fecha = $fecha ?? date('Y-m-d');
        $out = [
            'total_empleados' => 0, 'presentes' => 0, 'porcentaje_presentes' => 0,
            'tardanzas' => 0, 'porcentaje_tardanzas' => 0, 'ausentes' => 0, 'porcentaje_ausentes' => 0,
            'ausencias_justificadas' => 0, 'sin_marcar' => 0, 'con_entrada' => 0, 'con_salida' => 0
        ];
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM usuarios WHERE empresa_id = :empresa_id AND estado = 'activo'");
            $stmt->execute([':empresa_id' => $empresa_id]);
            $totalEmpleados = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

            $stmt = $this->db->prepare("
                SELECT
                    COUNT(DISTINCT usuario_id) as presentes,
                    COUNT(DISTINCT CASE WHEN tipo_marcacion = 'entrada' THEN usuario_id END) as con_entrada,
                    COUNT(DISTINCT CASE WHEN tipo_marcacion = 'salida' THEN usuario_id END) as con_salida
                FROM registros_asistencia
                WHERE empresa_id = :empresa_id AND fecha = :fecha
            ");
            $stmt->execute([':empresa_id' => $empresa_id, ':fecha' => $fecha]);
            $marc = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            // Tardanzas: sintaxis MariaDB segura
            $stmt = $this->db->prepare("
                SELECT COUNT(DISTINCT ra.usuario_id) as tardanzas
                FROM registros_asistencia ra
                INNER JOIN usuarios u ON ra.usuario_id = u.id
                INNER JOIN horarios h ON u.horario_asignado_id = h.id AND h.jornada_diurna_inicio IS NOT NULL
                WHERE ra.empresa_id = :empresa_id
                  AND ra.fecha = :fecha
                  AND ra.tipo_marcacion = 'entrada'
                  AND " . $this->condTardanza() . "
            ");
            $stmt->execute([':empresa_id' => $empresa_id, ':fecha' => $fecha]);
            $tardanzas = (int)($stmt->fetch(PDO::FETCH_ASSOC)['tardanzas'] ?? 0);

            // Ausencias justificadas (novedades aprobadas que cubren la fecha)
            $stmt = $this->db->prepare("
                SELECT COUNT(DISTINCT usuario_id) as ausencias_justificadas
                FROM novedades
                WHERE empresa_id = :empresa_id
                  AND estado = 'aprobada'
                  AND fecha_inicio IS NOT NULL AND fecha_fin IS NOT NULL
                  AND :fecha BETWEEN fecha_inicio AND fecha_fin
                  AND tipo IN ('incapacidad','vacaciones','licencia','permiso','calamidad')
            ");
            $stmt->execute([':empresa_id' => $empresa_id, ':fecha' => $fecha]);
            $ausJust = (int)($stmt->fetch(PDO::FETCH_ASSOC)['ausencias_justificadas'] ?? 0);

            $presentes = (int)($marc['presentes'] ?? 0);
            $ausentes  = max(0, $totalEmpleados - $presentes - $ausJust);

            return [
                'total_empleados' => $totalEmpleados,
                'presentes' => $presentes,
                'porcentaje_presentes' => $totalEmpleados > 0 ? round(($presentes / $totalEmpleados) * 100, 1) : 0,
                'tardanzas' => $tardanzas,
                'porcentaje_tardanzas' => $totalEmpleados > 0 ? round(($tardanzas / $totalEmpleados) * 100, 1) : 0,
                'ausentes' => $ausentes,
                'porcentaje_ausentes' => $totalEmpleados > 0 ? round(($ausentes / $totalEmpleados) * 100, 1) : 0,
                'ausencias_justificadas' => $ausJust,
                'sin_marcar' => max(0, $totalEmpleados - $presentes - $ausJust),
                'con_entrada' => (int)($marc['con_entrada'] ?? 0),
                'con_salida' => (int)($marc['con_salida'] ?? 0),
            ];
        } catch (\Throwable $e) {
            return $out;
        }
    }

    /** ADMIN EMPRESA: Asistencia por sede (gráfico pie) */
    public function getAsistenciaPorSede($empresa_id, $fecha = null) {
        try {
            $fecha = $fecha ?? date('Y-m-d');
            $stmt = $this->db->prepare("
                SELECT
                    s.nombre as sede,
                    COUNT(DISTINCT ra.usuario_id) as presentes
                FROM sedes s
                LEFT JOIN registros_asistencia ra ON s.id = ra.sede_id AND ra.fecha = :fecha AND ra.tipo_marcacion = 'entrada'
                WHERE s.empresa_id = :empresa_id
                GROUP BY s.id, s.nombre
                HAVING presentes > 0
                ORDER BY presentes DESC
            ");
            $stmt->execute([':empresa_id' => $empresa_id, ':fecha' => $fecha]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** ADMIN EMPRESA: Top 10 llegadas tarde */
    public function getTopTardanzas($empresa_id, $fecha = null) {
        try {
            $fecha = $fecha ?? date('Y-m-d');
            $stmt = $this->db->prepare("
                SELECT
                    u.nombre_completo as empleado,
                    TIME(ra.hora_registro) as hora_real,
                    TIME(h.jornada_diurna_inicio) as hora_programada,
                    (TIME_TO_SEC(TIMEDIFF(TIME(ra.hora_registro), TIME(h.jornada_diurna_inicio))) DIV 60) as minutos_tarde
                FROM registros_asistencia ra
                INNER JOIN usuarios u ON ra.usuario_id = u.id
                INNER JOIN horarios h ON u.horario_asignado_id = h.id AND h.jornada_diurna_inicio IS NOT NULL
                WHERE ra.empresa_id = :empresa_id
                  AND ra.fecha = :fecha
                  AND ra.tipo_marcacion = 'entrada'
                  AND " . $this->condTardanza() . "
                ORDER BY minutos_tarde DESC
                LIMIT 10
            ");
            $stmt->execute([':empresa_id' => $empresa_id, ':fecha' => $fecha]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** ADMIN EMPRESA: Horas trabajadas vs programadas (últimos 7 días) */
    public function getHorasTrabajadasVsProgramadas($empresa_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    ra.fecha,
                    COUNT(DISTINCT ra.usuario_id) * 8 as horas_programadas,
                    COUNT(DISTINCT ra.usuario_id) * 7.5 as horas_trabajadas_estimadas
                FROM registros_asistencia ra
                WHERE ra.empresa_id = :empresa_id
                  AND ra.fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                  AND ra.tipo_marcacion IN ('entrada', 'salida')
                GROUP BY ra.fecha
                ORDER BY ra.fecha ASC
            ");
            $stmt->execute([':empresa_id' => $empresa_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** ADMIN EMPRESA: Incidencias por tipo (doughnut) */
    public function getIncidenciasPorTipo($empresa_id, $fecha_inicio = null, $fecha_fin = null) {
        try {
            $fecha_inicio = $fecha_inicio ?? date('Y-m-01');
            $fecha_fin = $fecha_fin ?? date('Y-m-t');
            $stmt = $this->db->prepare("
                SELECT tipo, COUNT(*) as total
                FROM novedades
                WHERE empresa_id = :empresa_id
                  AND fecha_inicio IS NOT NULL
                  AND fecha_inicio BETWEEN :fecha_inicio AND :fecha_fin
                GROUP BY tipo
                ORDER BY total DESC
            ");
            $stmt->execute([
                ':empresa_id' => $empresa_id,
                ':fecha_inicio' => $fecha_inicio,
                ':fecha_fin' => $fecha_fin
            ]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Alertas no leídas */
    public function getAlertas($empresa_id, $usuario_id = null, $limite = 10) {
        try {
            $sql = "SELECT * FROM alertas WHERE empresa_id = :empresa_id AND leido = 0";
            if ($usuario_id) {
                $sql .= " AND (usuario_id = :usuario_id OR usuario_id IS NULL)";
            }
            $sql .= " ORDER BY prioridad DESC, fecha_creacion DESC LIMIT " . (int)$limite;
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':empresa_id', $empresa_id);
            if ($usuario_id) $stmt->bindValue(':usuario_id', $usuario_id);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Marcar alerta como leída */
    public function marcarAlertaLeida($id, $usuario_id) {
        try {
            $stmt = $this->db->prepare("UPDATE alertas SET leido = 1 WHERE id = :id AND (usuario_id = :usuario_id OR usuario_id IS NULL)");
            return $stmt->execute([':id' => $id, ':usuario_id' => $usuario_id]);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Generar alertas automáticas (sin marcación después de hora programada) */
    public function generarAlertasAutomaticas($empresa_id) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO alertas (empresa_id, usuario_id, tipo, titulo, mensaje, prioridad)
                SELECT
                    :empresa_id,
                    u.id,
                    'warning',
                    'Sin marcación de entrada',
                    CONCAT(u.nombre_completo, ' no ha marcado entrada y ya pasó la hora programada'),
                    2
                FROM usuarios u
                INNER JOIN horarios h ON u.horario_asignado_id = h.id AND h.jornada_diurna_inicio IS NOT NULL
                LEFT JOIN registros_asistencia ra ON u.id = ra.usuario_id AND ra.fecha = :fecha AND ra.tipo_marcacion = 'entrada'
                WHERE u.empresa_id = :empresa_id
                  AND u.estado = 'activo'
                  AND ra.id IS NULL
                  AND TIME(:ahora) > ADDTIME(TIME(h.jornada_diurna_inicio), SEC_TO_TIME(COALESCE(h.tolerancia_entrada,0) * 60))
                  AND NOT EXISTS (
                      SELECT 1 FROM alertas a
                      WHERE a.usuario_id = u.id
                        AND DATE(a.fecha_creacion) = CURDATE()
                        AND a.titulo = 'Sin marcación de entrada'
                  )
            ");
            $stmt->execute([':empresa_id' => $empresa_id, ':fecha' => date('Y-m-d'), ':ahora' => date('H:i:s')]);
            return $stmt->rowCount();
        } catch (\Throwable $e) {
            return 0;
        }
    }
}