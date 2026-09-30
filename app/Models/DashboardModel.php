<?php
namespace App\Models;
use PDO;

class DashboardModel {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /** Condición segura de tardanza (fallback si no hay Panel de Turnos) */
    private function condTardanza() {
        return "TIME(ra.hora_registro) > ADDTIME(TIME(h.jornada_diurna_inicio), SEC_TO_TIME(COALESCE(h.tolerancia_entrada, 0) * 60))";
    }

    // ============ MÉTODOS VIEJOS (se mantienen por compatibilidad) ============

    public function getResumenGlobal() {
        try {
            $stmt = $this->db->query("
                SELECT
                    COUNT(DISTINCT e.id) as total_empresas,
                    COUNT(DISTINCT CASE WHEN e.estado = 'activo' THEN e.id END) as empresas_activas,
                    COUNT(DISTINCT u.id) as total_usuarios,
                    COUNT(DISTINCT CASE WHEN u.estado = 'activo' THEN u.id END) as usuarios_activos
                FROM empresas e LEFT JOIN usuarios u ON e.id = u.empresa_id
            ");
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total_empresas'=>0,'empresas_activas'=>0,'total_usuarios'=>0,'usuarios_activos'=>0];
        } catch (\Throwable $e) {
            return ['total_empresas'=>0,'empresas_activas'=>0,'total_usuarios'=>0,'usuarios_activos'=>0];
        }
    }

    public function getAsistenciaGlobalHoy() {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(DISTINCT ra.usuario_id) as total_marcaciones,
                       COUNT(DISTINCT CASE WHEN ra.tipo_marcacion = 'entrada' THEN ra.usuario_id END) as entradas,
                       COUNT(DISTINCT CASE WHEN ra.estado = 'pendiente' THEN ra.usuario_id END) as pendientes
                FROM registros_asistencia ra WHERE ra.fecha = :fecha
            ");
            $stmt->execute([':fecha' => date('Y-m-d')]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['entradas'=>0,'pendientes'=>0];
        } catch (\Throwable $e) { return ['entradas'=>0,'pendientes'=>0]; }
    }

    public function getAsistenciaPorEmpresa($fecha = null) {
        try {
            $fecha = $fecha ?? date('Y-m-d');
            $stmt = $this->db->prepare("
                SELECT e.nombre as empresa,
                       COUNT(DISTINCT ra.usuario_id) as presentes,
                       (SELECT COUNT(*) FROM usuarios u WHERE u.empresa_id = e.id AND u.estado = 'activo') as total_empleados
                FROM empresas e
                LEFT JOIN registros_asistencia ra ON e.id = ra.empresa_id AND ra.fecha = :fecha AND ra.tipo_marcacion = 'entrada'
                WHERE e.estado = 'activo'
                GROUP BY e.id, e.nombre ORDER BY presentes DESC LIMIT 10
            ");
            $stmt->execute([':fecha' => $fecha]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) { return []; }
    }

    public function getTendenciasSemanales() {
        try {
            $stmt = $this->db->query("
                SELECT ra.fecha,
                       COUNT(DISTINCT ra.usuario_id) as total_marcaciones,
                       COUNT(DISTINCT CASE WHEN ra.tipo_marcacion = 'entrada' THEN ra.usuario_id END) as entradas
                FROM registros_asistencia ra
                WHERE ra.fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                GROUP BY ra.fecha ORDER BY ra.fecha ASC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) { return []; }
    }

    public function getResumenDia($empresa_id, $fecha = null) {
        $fecha = $fecha ?? date('Y-m-d');
        $out = ['total_empleados'=>0,'presentes'=>0,'porcentaje_presentes'=>0,'tardanzas'=>0,'porcentaje_tardanzas'=>0,'ausentes'=>0,'porcentaje_ausentes'=>0,'ausencias_justificadas'=>0,'sin_marcar'=>0,'con_entrada'=>0,'con_salida'=>0];
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM usuarios WHERE empresa_id = :empresa_id AND estado = 'activo'");
            $stmt->execute([':empresa_id' => $empresa_id]);
            $totalEmpleados = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
            $stmt = $this->db->prepare("
                SELECT COUNT(DISTINCT usuario_id) as presentes,
                       COUNT(DISTINCT CASE WHEN tipo_marcacion = 'entrada' THEN usuario_id END) as con_entrada,
                       COUNT(DISTINCT CASE WHEN tipo_marcacion = 'salida' THEN usuario_id END) as con_salida
                FROM registros_asistencia WHERE empresa_id = :empresa_id AND fecha = :fecha
            ");
            $stmt->execute([':empresa_id' => $empresa_id, ':fecha' => $fecha]);
            $marc = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $stmt = $this->db->prepare("
                SELECT COUNT(DISTINCT ra.usuario_id) as tardanzas
                FROM registros_asistencia ra
                INNER JOIN usuarios u ON ra.usuario_id = u.id
                INNER JOIN horarios h ON u.horario_asignado_id = h.id AND h.jornada_diurna_inicio IS NOT NULL
                WHERE ra.empresa_id = :empresa_id AND ra.fecha = :fecha AND ra.tipo_marcacion = 'entrada' AND " . $this->condTardanza() . "
            ");
            $stmt->execute([':empresa_id' => $empresa_id, ':fecha' => $fecha]);
            $tardanzas = (int)($stmt->fetch(PDO::FETCH_ASSOC)['tardanzas'] ?? 0);
            $stmt = $this->db->prepare("
                SELECT COUNT(DISTINCT usuario_id) as ausencias_justificadas
                FROM novedades
                WHERE empresa_id = :empresa_id AND estado = 'aprobada' AND fecha_inicio IS NOT NULL AND fecha_fin IS NOT NULL
                  AND :fecha BETWEEN fecha_inicio AND fecha_fin AND tipo IN ('incapacidad','vacaciones','licencia','permiso','calamidad')
            ");
            $stmt->execute([':empresa_id' => $empresa_id, ':fecha' => $fecha]);
            $ausJust = (int)($stmt->fetch(PDO::FETCH_ASSOC)['ausencias_justificadas'] ?? 0);
            $presentes = (int)($marc['presentes'] ?? 0);
            $ausentes  = max(0, $totalEmpleados - $presentes - $ausJust);
            return [
                'total_empleados' => $totalEmpleados, 'presentes' => $presentes,
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
        } catch (\Throwable $e) { return $out; }
    }

    public function getAsistenciaPorSede($empresa_id, $fecha = null) {
        try {
            $fecha = $fecha ?? date('Y-m-d');
            $stmt = $this->db->prepare("
                SELECT s.nombre as sede, COUNT(DISTINCT ra.usuario_id) as presentes
                FROM sedes s LEFT JOIN registros_asistencia ra ON s.id = ra.sede_id AND ra.fecha = :fecha AND ra.tipo_marcacion = 'entrada'
                WHERE s.empresa_id = :empresa_id
                GROUP BY s.id, s.nombre HAVING presentes > 0 ORDER BY presentes DESC
            ");
            $stmt->execute([':empresa_id' => $empresa_id, ':fecha' => $fecha]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) { return []; }
    }

    public function getTopTardanzas($empresa_id, $fecha = null) {
        try {
            $fecha = $fecha ?? date('Y-m-d');
            $stmt = $this->db->prepare("
                SELECT u.nombre_completo as empleado, TIME(ra.hora_registro) as hora_real,
                       TIME(h.jornada_diurna_inicio) as hora_programada,
                       (TIME_TO_SEC(TIMEDIFF(TIME(ra.hora_registro), TIME(h.jornada_diurna_inicio))) DIV 60) as minutos_tarde
                FROM registros_asistencia ra
                INNER JOIN usuarios u ON ra.usuario_id = u.id
                INNER JOIN horarios h ON u.horario_asignado_id = h.id AND h.jornada_diurna_inicio IS NOT NULL
                WHERE ra.empresa_id = :empresa_id AND ra.fecha = :fecha AND ra.tipo_marcacion = 'entrada' AND " . $this->condTardanza() . "
                ORDER BY minutos_tarde DESC LIMIT 10
            ");
            $stmt->execute([':empresa_id' => $empresa_id, ':fecha' => $fecha]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) { return []; }
    }

    public function getHorasTrabajadasVsProgramadas($empresa_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT ra.fecha, COUNT(DISTINCT ra.usuario_id) * 8 as horas_programadas,
                       COUNT(DISTINCT ra.usuario_id) * 7.5 as horas_trabajadas_estimadas
                FROM registros_asistencia ra
                WHERE ra.empresa_id = :empresa_id AND ra.fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                  AND ra.tipo_marcacion IN ('entrada', 'salida')
                GROUP BY ra.fecha ORDER BY ra.fecha ASC
            ");
            $stmt->execute([':empresa_id' => $empresa_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) { return []; }
    }

    public function getIncidenciasPorTipo($empresa_id, $fecha_inicio = null, $fecha_fin = null) {
        try {
            $fecha_inicio = $fecha_inicio ?? date('Y-m-01');
            $fecha_fin = $fecha_fin ?? date('Y-m-t');
            $stmt = $this->db->prepare("
                SELECT tipo, COUNT(*) as total FROM novedades
                WHERE empresa_id = :empresa_id AND fecha_inicio IS NOT NULL AND fecha_inicio BETWEEN :fecha_inicio AND :fecha_fin
                GROUP BY tipo ORDER BY total DESC
            ");
            $stmt->execute([':empresa_id' => $empresa_id, ':fecha_inicio' => $fecha_inicio, ':fecha_fin' => $fecha_fin]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) { return []; }
    }

    public function getAlertas($empresa_id, $usuario_id = null, $limite = 10) {
        try {
            $sql = "SELECT * FROM alertas WHERE empresa_id = :empresa_id AND leido = 0";
            if ($usuario_id) $sql .= " AND (usuario_id = :usuario_id OR usuario_id IS NULL)";
            $sql .= " ORDER BY prioridad DESC, fecha_creacion DESC LIMIT " . (int)$limite;
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':empresa_id', $empresa_id);
            if ($usuario_id) $stmt->bindValue(':usuario_id', $usuario_id);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) { return []; }
    }

    public function marcarAlertaLeida($id, $usuario_id) {
        try {
            $stmt = $this->db->prepare("UPDATE alertas SET leido = 1 WHERE id = :id AND (usuario_id = :usuario_id OR usuario_id IS NULL)");
            return $stmt->execute([':id' => $id, ':usuario_id' => $usuario_id]);
        } catch (\Throwable $e) { return false; }
    }

    // ============ FASE G: MÉTODOS NUEVOS (Panel de Turnos) ============

    /**
     * Resumen del día basado en el Panel de Turnos (turnos_calendario + parametros_turnos)
     * Retorna: programados, libres, vacaciones, a tiempo, tarde, ausentes, por marcar
     */
    public function getResumenDiaPanel($empresa_id, $fecha = null) {
        $fecha = $fecha ?? date('Y-m-d');
        $ahora = date('H:i:s');
        $out = [
            'total_programados' => 0, 'libres_hoy' => 0, 'vacaciones_hoy' => 0,
            'a_tiempo' => 0, 'tarde' => 0, 'ausente' => 0, 'por_marcar' => 0,
            'sin_turno' => 0
        ];
        try {
            // 1. Contar por tipo de letra en el Panel
            $st = $this->db->prepare("
                SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN pt.es_descanso = 1 THEN 1 ELSE 0 END) as libres,
                    SUM(CASE WHEN pt.es_vacacion = 1 THEN 1 ELSE 0 END) as vacaciones,
                    SUM(CASE WHEN COALESCE(pt.es_descanso,0) = 0 AND COALESCE(pt.es_vacacion,0) = 0 THEN 1 ELSE 0 END) as laborales
                FROM turnos_calendario tc
                LEFT JOIN parametros_turnos pt ON pt.codigo = tc.codigo
                INNER JOIN usuarios u ON u.id = tc.usuario_id
                WHERE tc.fecha = ? AND u.empresa_id = ? AND u.estado = 'activo'
            ");
            $st->execute([$fecha, (int)$empresa_id]);
            $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];
            $out['total_programados'] = (int)($r['total'] ?? 0);
            $out['libres_hoy'] = (int)($r['libres'] ?? 0);
            $out['vacaciones_hoy'] = (int)($r['vacaciones'] ?? 0);
            $laborales = (int)($r['laborales'] ?? 0);

            // 2. Contar estados de los laborales (a tiempo / tarde / ausente / por marcar)
            $st = $this->db->prepare("
                SELECT tc.usuario_id, pt.hora_entrada, pt.recargo_nocturno,
                       MIN(CASE WHEN ra.tipo_marcacion='entrada' THEN ra.hora_registro END) AS entrada_real
                FROM turnos_calendario tc
                LEFT JOIN parametros_turnos pt ON pt.codigo = tc.codigo
                LEFT JOIN registros_asistencia ra ON ra.usuario_id = tc.usuario_id AND ra.fecha = ?
                INNER JOIN usuarios u ON u.id = tc.usuario_id
                WHERE tc.fecha = ? AND u.empresa_id = ? AND u.estado = 'activo'
                  AND COALESCE(pt.es_descanso,0) = 0 AND COALESCE(pt.es_vacacion,0) = 0
                GROUP BY tc.usuario_id, pt.hora_entrada, pt.recargo_nocturno
            ");
            $st->execute([$fecha, $fecha, (int)$empresa_id]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                $hEntrada = $row['hora_entrada'] ?? null;
                $entradaReal = $row['entrada_real'] ?? null;
                $nocturno = (int)($row['recargo_nocturno'] ?? 0) === 1;

                if ($entradaReal === null) {
                    // No marcó: ¿ya pasó hora+10 min?
                    if ($hEntrada === null) { $out['por_marcar']++; continue; }
                    $limite = $this->addMinutos($hEntrada, 10, $nocturno);
                    if ($this->yaPaso($limite, $nocturno)) $out['ausente']++;
                    else $out['por_marcar']++;
                } else {
                    // Marcó: ¿a tiempo o tarde?
                    if ($hEntrada === null) { $out['a_tiempo']++; continue; }
                    $limite = $this->addMinutos($hEntrada, 10, $nocturno);
                    if ($this->esAntesOIgual($entradaReal, $limite, $nocturno)) $out['a_tiempo']++;
                    else $out['tarde']++;
                }
            }
            return $out;
        } catch (\Throwable $e) {
            return $out;
        }
    }

    /**
     * Lista detallada del día: cada empleado programado con su turno y estado real
     * Útil para la tabla "Estado del Día" y para exportar CSV
     */
    public function getEstadoDiaDetallado($empresa_id, $fecha = null, $filtro_estado = '', $filtro_turno = '') {
        $fecha = $fecha ?? date('Y-m-d');
        try {
            $st = $this->db->prepare("
                SELECT
                    u.id AS usuario_id,
                    u.nombre_completo,
                    u.identificacion,
                    s.nombre AS sede_nombre,
                    tc.codigo AS turno_codigo,
                    pt.nombre AS turno_nombre,
                    pt.hora_entrada,
                    pt.hora_salida,
                    pt.color AS turno_color,
                    pt.es_descanso,
                    pt.es_vacacion,
                    pt.recargo_nocturno,
                    MIN(CASE WHEN ra.tipo_marcacion='entrada' THEN ra.hora_registro END) AS entrada_real,
                    MAX(CASE WHEN ra.tipo_marcacion='salida' THEN ra.hora_registro END) AS salida_real
                FROM turnos_calendario tc
                LEFT JOIN parametros_turnos pt ON pt.codigo = tc.codigo
                LEFT JOIN usuarios u ON u.id = tc.usuario_id
                LEFT JOIN sedes s ON s.id = u.sede_id
                LEFT JOIN registros_asistencia ra ON ra.usuario_id = tc.usuario_id AND ra.fecha = ?
                WHERE tc.fecha = ? AND u.empresa_id = ? AND u.estado = 'activo'
                GROUP BY u.id, u.nombre_completo, u.identificacion, s.nombre,
                         tc.codigo, pt.nombre, pt.hora_entrada, pt.hora_salida, pt.color,
                         pt.es_descanso, pt.es_vacacion, pt.recargo_nocturno
                ORDER BY u.nombre_completo
            ");
            $st->execute([$fecha, $fecha, (int)$empresa_id]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);

            $resultado = [];
            foreach ($rows as $r) {
                $estado = $this->calcularEstadoEmpleado($r);
                if ($filtro_estado !== '' && $estado['clave'] !== $filtro_estado) continue;
                if ($filtro_turno !== '' && $r['turno_codigo'] !== $filtro_turno) continue;
                $r['estado'] = $estado;
                $resultado[] = $r;
            }
            return $resultado;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Calcula el estado de UN empleado (a tiempo/tarde/ausente/por_marcar/libre/vacaciones/sin_turno) */
    private function calcularEstadoEmpleado(array $r): array {
        $libre = (int)($r['es_descanso'] ?? 0) === 1;
        $vaca  = (int)($r['es_vacacion'] ?? 0) === 1;
        $noct  = (int)($r['recargo_nocturno'] ?? 0) === 1;

        if ($libre && !$vaca) return ['clave'=>'libre','label'=>'Libre','color'=>'#94a3b8','icon'=>'bed','diff'=>null];
        if ($vaca) return ['clave'=>'vacaciones','label'=>'Vacaciones','color'=>'#ec4899','icon'=>'umbrella-beach','diff'=>null];
        if (empty($r['turno_codigo'])) return ['clave'=>'sin_turno','label'=>'Sin turno','color'=>'#cbd5e1','icon'=>'question','diff'=>null];

        $hProg = $r['hora_entrada'] ?? null;
        $real  = $r['entrada_real'] ?? null;

        if ($real === null) {
            if ($hProg === null) return ['clave'=>'por_marcar','label'=>'Por marcar','color'=>'#94a3b8','icon'=>'hourglass-half','diff'=>null];
            $limite = $this->addMinutos($hProg, 10, $noct);
            if ($this->yaPaso($limite, $noct)) return ['clave'=>'ausente','label'=>'Ausente','color'=>'#dc2626','icon'=>'times-circle','diff'=>null];
            return ['clave'=>'por_marcar','label'=>'Por marcar','color'=>'#f59e0b','icon'=>'hourglass-half','diff'=>null];
        }

        if ($hProg === null) return ['clave'=>'a_tiempo','label'=>'A tiempo','color'=>'#03a950','icon'=>'check-circle','diff'=>0];

        $diffMin = $this->diffMinutos($hProg, $real, $noct);
        if ($diffMin <= 10) return ['clave'=>'a_tiempo','label'=>'A tiempo','color'=>'#03a950','icon'=>'check-circle','diff'=>$diffMin];
        return ['clave'=>'tarde','label'=>'Tarde','color'=>'#f97316','icon'=>'clock','diff'=>$diffMin];
    }

    /** Suma minutos a una hora HH:MM:SS (con cruce de medianoche si es nocturno) */
    private function addMinutos(string $h, int $min, bool $nocturno): string {
        $s = strtotime("1970-01-01 $h UTC") + ($min * 60);
        return gmdate('H:i:s', $s);
    }

    /** Diferencia en minutos: real - prog (positivo = tarde) */
    private function diffMinutos(string $prog, string $real, bool $nocturno): int {
        $sp = strtotime("1970-01-01 $prog UTC");
        $sr = strtotime("1970-01-01 $real UTC");
        if ($nocturno && $sr < $sp) $sr += 86400;
        return (int)round(($sr - $sp) / 60);
    }

    /** ¿Real <= limite? (con soporte nocturno) */
    private function esAntesOIgual(string $real, string $limite, bool $nocturno): bool {
        $sr = strtotime("1970-01-01 $real UTC");
        $sl = strtotime("1970-01-01 $limite UTC");
        if ($nocturno) {
            if ($sr < strtotime("1970-01-01 12:00:00 UTC")) $sr += 86400;
            if ($sl < strtotime("1970-01-01 12:00:00 UTC")) $sl += 86400;
        }
        return $sr <= $sl;
    }

    /** ¿El tiempo limite ya pasó respecto a ahora? */
    private function yaPaso(string $limite, bool $nocturno): bool {
        $ahora = strtotime("1970-01-01 " . date('H:i:s') . " UTC");
        $sl = strtotime("1970-01-01 $limite UTC");
        if ($nocturno) {
            if ($ahora < strtotime("1970-01-01 12:00:00 UTC")) $ahora += 86400;
            if ($sl < strtotime("1970-01-01 12:00:00 UTC")) $sl += 86400;
        }
        return $ahora > $sl;
    }

    /** Turno de hoy de UN usuario específico (para el Portal del Empleado) */
    public function getTurnoHoyUsuario($usuario_id, $fecha = null) {
        $fecha = $fecha ?? date('Y-m-d');
        try {
            $st = $this->db->prepare("
                SELECT tc.codigo, tc.servicio,
                       pt.nombre AS turno_nombre, pt.hora_entrada, pt.hora_salida,
                       pt.horas_trabajadas, pt.color,
                       pt.es_descanso, pt.es_vacacion, pt.recargo_nocturno
                FROM turnos_calendario tc
                LEFT JOIN parametros_turnos pt ON pt.codigo = tc.codigo
                WHERE tc.usuario_id = ? AND tc.fecha = ?
                LIMIT 1
            ");
            $st->execute([(int)$usuario_id, $fecha]);
            return $st->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (\Throwable $e) { return null; }
    }

    /** Conteos agrupados por estado para los filtros del dashboard */
    public function getConteosEstadoDia($empresa_id, $fecha = null) {
        $filas = $this->getEstadoDiaDetallado($empresa_id, $fecha);
        $conteos = ['total'=>count($filas),'a_tiempo'=>0,'tarde'=>0,'ausente'=>0,'por_marcar'=>0,'libre'=>0,'vacaciones'=>0,'sin_turno'=>0];
        foreach ($filas as $f) {
            $k = $f['estado']['clave'];
            if (isset($conteos[$k])) $conteos[$k]++;
        }
        return $conteos;
    }
}
