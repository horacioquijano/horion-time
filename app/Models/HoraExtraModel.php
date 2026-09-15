<?php
namespace App\Models;
use PDO;

class HoraExtraModel {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Crear solicitud de hora extra
     */
    public function create($data) {
        // Calcular horas solicitadas automáticamente si no vienen
        if (empty($data['horas_solicitadas'])) {
            $data['horas_solicitadas'] = $this->calcularHoras(
                $data['hora_inicio_programada'],
                $data['hora_fin_programada']
            );
        }

        // Determinar tipo automáticamente según fecha y hora
        if (empty($data['tipo'])) {
            $data['tipo'] = $this->determinarTipo(
                $data['fecha'],
                $data['hora_inicio_programada'],
                $data['empresa_id']
            );
        }

        // Obtener porcentaje de recargo
        $recargo = $this->getPorcentajeRecargo($data['tipo'], $data['empresa_id']);
        
        // Calcular valor hora ordinaria (salario mensual / 240 horas según ley colombiana)
        $valorHoraOrdinaria = $this->calcularValorHora($data['usuario_id']);
        
        // Calcular valor total
        $valorTotal = $data['horas_solicitadas'] * $valorHoraOrdinaria * (1 + ($recargo / 100));

        $sql = "INSERT INTO horas_extras 
                (usuario_id, empresa_id, fecha, tipo, 
                 hora_inicio_programada, hora_fin_programada,
                 horas_solicitadas, valor_hora_ordinaria, porcentaje_aplicado, valor_total,
                 estado, solicitante_id, justificacion, soporte_documento) 
                VALUES 
                (:usuario_id, :empresa_id, :fecha, :tipo,
                 :hora_inicio, :hora_fin,
                 :horas, :valor_hora, :recargo, :valor_total,
                 'pendiente', :solicitante_id, :justificacion, :soporte)";
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':usuario_id' => $data['usuario_id'],
            ':empresa_id' => $data['empresa_id'],
            ':fecha' => $data['fecha'],
            ':tipo' => $data['tipo'],
            ':hora_inicio' => $data['hora_inicio_programada'],
            ':hora_fin' => $data['hora_fin_programada'],
            ':horas' => $data['horas_solicitadas'],
            ':valor_hora' => $valorHoraOrdinaria,
            ':recargo' => $recargo,
            ':valor_total' => $valorTotal,
            ':solicitante_id' => $data['solicitante_id'],
            ':justificacion' => $data['justificacion'],
            ':soporte' => $data['soporte_documento'] ?? null
        ]);
        
        return $result ? $this->db->lastInsertId() : false;
    }

    /**
     * Calcular horas entre dos tiempos
     */
    private function calcularHoras($inicio, $fin) {
        $inicio_dt = new \DateTime($inicio);
        $fin_dt = new \DateTime($fin);
        
        // Si la hora fin es menor que inicio, asumir que cruza medianoche
        if ($fin_dt < $inicio_dt) {
            $fin_dt->modify('+1 day');
        }
        
        $diff = $inicio_dt->diff($fin_dt);
        return $diff->h + ($diff->i / 60);
    }

    /**
     * Determinar tipo de hora extra según fecha y hora
     */
    private function determinarTipo($fecha, $hora_inicio, $empresa_id) {
        $fecha_dt = new \DateTime($fecha);
        $dia_semana = $fecha_dt->format('w'); // 0 = domingo
        $es_festivo = $this->esDiaFestivo($fecha, $empresa_id);
        
        $hora_dt = new \DateTime($hora_inicio);
        $hora_num = (int)$hora_dt->format('H');
        
        // Dominical + Festivo
        if ($dia_semana == 0 && $es_festivo) {
            return 'dominical_festivo';
        }
        // Festivo
        if ($es_festivo) {
            return 'festivo';
        }
        // Dominical
        if ($dia_semana == 0) {
            return 'dominical';
        }
        // Nocturna (22:00 - 06:00)
        if ($hora_num >= 22 || $hora_num < 6) {
            return 'nocturna';
        }
        // Diurna por defecto
        return 'diurna';
    }

    /**
     * Verificar si es día festivo
     */
    private function esDiaFestivo($fecha, $empresa_id) {
        // Obtener festivos de configuración de empresa
        $stmt = $this->db->prepare("
            SELECT configuracion_personalizada FROM empresas WHERE id = :empresa_id
        ");
        $stmt->execute([':empresa_id' => $empresa_id]);
        $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$empresa || empty($empresa['configuracion_personalizada'])) {
            return false;
        }
        
        $config = json_decode($empresa['configuracion_personalizada'], true);
        $festivos = $config['festivos'] ?? [];
        
        return in_array($fecha, $festivos);
    }

    /**
     * Obtener porcentaje de recargo según tipo
     */
    private function getPorcentajeRecargo($tipo, $empresa_id) {
        $stmt = $this->db->prepare("
            SELECT porcentaje_recargo FROM configuracion_recargos 
            WHERE empresa_id = :empresa_id 
            AND tipo_recargo = :tipo 
            AND activo = 1
        ");
        $stmt->execute([':empresa_id' => $empresa_id, ':tipo' => "hora_extra_{$tipo}"]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? (float)$result['porcentaje_recargo'] : 0;
    }

    /**
     * Calcular valor hora ordinaria del empleado
     */
    private function calcularValorHora($usuario_id) {
        // Obtener salario del cargo del usuario
        $stmt = $this->db->prepare("
            SELECT c.salario_base 
            FROM usuarios u
            LEFT JOIN cargos c ON u.cargo_id = c.id
            WHERE u.id = :usuario_id
        ");
        $stmt->execute([':usuario_id' => $usuario_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $salario = $result['salario_base'] ?? 0;
        
        // Si no hay salario, usar salario mínimo (2026 Colombia: ~$1.400.000)
        if ($salario <= 0) {
            $salario = 1400000;
        }
        
        // Dividir entre 240 horas (jornada mensual estándar)
        return round($salario / 240, 2);
    }

    /**
     * Obtener horas extras con filtros
     */
    public function getAll($filters = []) {
        $sql = "SELECT he.*, 
                       u.nombre_completo as empleado_nombre, 
                       u.identificacion as empleado_id,
                       s.nombre_completo as solicitante_nombre,
                       a.nombre_completo as aprobador_nombre
                FROM horas_extras he
                LEFT JOIN usuarios u ON he.usuario_id = u.id
                LEFT JOIN usuarios s ON he.solicitante_id = s.id
                LEFT JOIN usuarios a ON he.aprobador_id = a.id
                WHERE 1=1";
        
        $params = [];
        
        if (!empty($filters['empresa_id'])) {
            $sql .= " AND he.empresa_id = :empresa_id";
            $params[':empresa_id'] = $filters['empresa_id'];
        }
        if (!empty($filters['usuario_id'])) {
            $sql .= " AND he.usuario_id = :usuario_id";
            $params[':usuario_id'] = $filters['usuario_id'];
        }
        if (!empty($filters['estado'])) {
            $sql .= " AND he.estado = :estado";
            $params[':estado'] = $filters['estado'];
        }
        if (!empty($filters['tipo'])) {
            $sql .= " AND he.tipo = :tipo";
            $params[':tipo'] = $filters['tipo'];
        }
        if (!empty($filters['fecha_inicio'])) {
            $sql .= " AND he.fecha >= :fecha_inicio";
            $params[':fecha_inicio'] = $filters['fecha_inicio'];
        }
        if (!empty($filters['fecha_fin'])) {
            $sql .= " AND he.fecha <= :fecha_fin";
            $params[':fecha_fin'] = $filters['fecha_fin'];
        }
        
        $sql .= " ORDER BY he.fecha DESC, he.hora_inicio_programada DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener resumen de horas extras
     */
    public function getResumen($empresa_id, $fecha_inicio = null, $fecha_fin = null) {
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                    SUM(CASE WHEN estado = 'aprobada' THEN 1 ELSE 0 END) as aprobadas,
                    SUM(CASE WHEN estado = 'pagada' THEN 1 ELSE 0 END) as pagadas,
                    SUM(CASE WHEN estado = 'rechazada' THEN 1 ELSE 0 END) as rechazadas,
                    SUM(CASE WHEN estado IN ('aprobada', 'pagada') THEN horas_aprobadas ELSE 0 END) as horas_totales_aprobadas,
                    SUM(CASE WHEN estado IN ('aprobada', 'pagada') THEN valor_total ELSE 0 END) as valor_total_aprobado
                FROM horas_extras
                WHERE empresa_id = :empresa_id";
        
        $params = [':empresa_id' => $empresa_id];
        
        if ($fecha_inicio) {
            $sql .= " AND fecha >= :fecha_inicio";
            $params[':fecha_inicio'] = $fecha_inicio;
        }
        if ($fecha_fin) {
            $sql .= " AND fecha <= :fecha_fin";
            $params[':fecha_fin'] = $fecha_fin;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Aprobar/Rechazar hora extra (solo Admin/Supervisor/RRHH)
     */
    public function actualizarEstado($id, $estado, $aprobador_id, $horas_aprobadas, $observaciones, $userRole) {
        // Validación de permisos
        $allowedRoles = ['SuperAdmin', 'Admin_Empresa', 'Supervisor', 'RRHH'];
        if (!in_array($userRole, $allowedRoles)) {
            throw new \Exception("Acceso denegado: Solo personal autorizado puede aprobar horas extras.");
        }

        // Obtener hora extra
        $stmt = $this->db->prepare("SELECT * FROM horas_extras WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $he = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$he) {
            throw new \Exception("Hora extra no encontrada.");
        }
        if (!in_array($he['estado'], ['pendiente', 'autorizada'])) {
            throw new \Exception("La hora extra ya fue procesada (estado: {$he['estado']}).");
        }
        if ($he['usuario_id'] == $aprobador_id) {
            throw new \Exception("No puedes aprobar tu propia hora extra.");
        }

        // Calcular valor total con horas aprobadas
        $valorTotal = $horas_aprobadas * $he['valor_hora_ordinaria'] * (1 + ($he['porcentaje_aplicado'] / 100));

        $sql = "UPDATE horas_extras 
                SET estado = :estado, 
                    aprobador_id = :aprobador_id, 
                    horas_aprobadas = :horas_aprobadas,
                    valor_total = :valor_total,
                    fecha_aprobacion = NOW(),
                    observaciones = :observaciones
                WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':estado' => $estado,
            ':aprobador_id' => $aprobador_id,
            ':horas_aprobadas' => $horas_aprobadas,
            ':valor_total' => round($valorTotal, 2),
            ':observaciones' => $observaciones,
            ':id' => $id
        ]);
    }

    /**
     * Marcar como pagada (solo Admin)
     */
    public function marcarPagada($id, $numero_nomina, $userRole) {
        if (!in_array($userRole, ['SuperAdmin', 'Admin_Empresa'])) {
            throw new \Exception("Acceso denegado: Solo administradores pueden marcar como pagada.");
        }

        $stmt = $this->db->prepare("
            UPDATE horas_extras 
            SET estado = 'pagada', 
                fecha_pago = CURDATE(),
                numero_nomina = :numero_nomina
            WHERE id = :id AND estado = 'aprobada'
        ");
        return $stmt->execute([
            ':numero_nomina' => $numero_nomina,
            ':id' => $id
        ]);
    }

    /**
     * Obtener configuración de recargos
     */
    public function getConfiguracionRecargos($empresa_id) {
        $stmt = $this->db->prepare("SELECT * FROM configuracion_recargos WHERE empresa_id = :empresa_id AND activo = 1 ORDER BY tipo_recargo");
        $stmt->execute([':empresa_id' => $empresa_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}