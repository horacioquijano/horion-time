<?php
namespace App\Models;
use PDO;

class AsistenciaModel {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Registrar una nueva marcación
     * MEJORA: insert dinámico seguro — solo usa las columnas que REALMENTE existen
     * en la tabla (evita "Unknown column" si alguna no fue creada).
     * Si todas existen, el comportamiento es exactamente el mismo que antes.
     */
    public function registrarMarcacion($data) {
        $cols = $this->db->query("SHOW COLUMNS FROM registros_asistencia")->fetchAll(PDO::FETCH_COLUMN);

        $datos = [
            'usuario_id'       => $data['usuario_id'],
            'empresa_id'       => $data['empresa_id'],
            'sede_id'          => $data['sede_id'] ?? null,
            'fecha'            => $data['fecha'],
            'tipo_marcacion'   => $data['tipo_marcacion'],
            'hora_registro'    => $data['hora_registro'],
            'metodo_marcacion' => $data['metodo_marcacion'],
            'foto_evidencia'   => $data['foto_evidencia'] ?? null,
            'lat'              => $data['lat'] ?? null,
            'lng'              => $data['lng'] ?? null,
            'dispositivo_info' => json_encode([
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'ip'         => $_SERVER['REMOTE_ADDR'] ?? null
            ]),
            'estado'           => $data['estado'] ?? 'pendiente',
            'observaciones'    => $data['observaciones'] ?? null,
            'creado_por'       => $data['creado_por'] ?? $data['usuario_id']
        ];

        $use    = array_intersect_key($datos, array_flip($cols));
        $campos = implode(', ', array_keys($use));
        $marks  = implode(', ', array_map(fn($k) => ':' . $k, array_keys($use)));

        $stmt = $this->db->prepare("INSERT INTO registros_asistencia ($campos) VALUES ($marks)");
        foreach ($use as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        return $stmt->execute();
    }

    /**
     * Obtener marcaciones del día de un usuario
     */
    public function getMarcacionesHoy($usuario_id, $fecha = null) {
        $fecha = $fecha ?? date('Y-m-d');
        $stmt = $this->db->prepare("
            SELECT ra.*, u.nombre_completo, s.nombre as sede_nombre
            FROM registros_asistencia ra
            LEFT JOIN usuarios u ON ra.usuario_id = u.id
            LEFT JOIN sedes s ON ra.sede_id = s.id
            WHERE ra.usuario_id = :usuario_id AND ra.fecha = :fecha
            ORDER BY ra.hora_registro ASC
        ");
        $stmt->execute([':usuario_id' => $usuario_id, ':fecha' => $fecha]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener todas las marcaciones (para admin)
     * MEJORA: ORDER BY seguro — usa hora_sistema solo si la columna existe
     */
    public function getAllMarcaciones($empresa_id = null, $fecha = null) {
        $sql = "SELECT ra.*, u.nombre_completo, u.identificacion, s.nombre as sede_nombre
                FROM registros_asistencia ra
                LEFT JOIN usuarios u ON ra.usuario_id = u.id
                LEFT JOIN sedes s ON ra.sede_id = s.id
                WHERE 1=1";
        $params = [];
        if ($empresa_id) {
            $sql .= " AND ra.empresa_id = :empresa_id";
            $params[':empresa_id'] = $empresa_id;
        }
        if ($fecha) {
            $sql .= " AND ra.fecha = :fecha";
            $params[':fecha'] = $fecha;
        }
        $cols = $this->db->query("SHOW COLUMNS FROM registros_asistencia")->fetchAll(PDO::FETCH_COLUMN);
        $sql .= in_array('hora_sistema', $cols)
            ? " ORDER BY ra.hora_sistema DESC LIMIT 100"
            : " ORDER BY ra.fecha DESC, ra.hora_registro DESC LIMIT 100";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Actualizar estado de marcación (solo admin)
     * MEJORA: parámetros opcionales — funciona con 2, 3 o 4 argumentos
     */
    public function actualizarEstado($id, $estado, $observaciones = null, $userRole = null) {
        $userRole = $userRole ?? ($_SESSION['rol_nombre'] ?? '');
        $allowedRoles = ['SuperAdmin', 'Admin_Empresa', 'RRHH'];
        if (!in_array($userRole, $allowedRoles)) {
            throw new \Exception("Acceso denegado: Solo administradores pueden corregir marcaciones.");
        }
        $stmt = $this->db->prepare("UPDATE registros_asistencia SET estado = :estado, observaciones = :observaciones WHERE id = :id");
        return $stmt->execute([
            ':estado' => $estado,
            ':observaciones' => $observaciones,
            ':id' => $id
        ]);
    }

    /**
     * Obtener resumen del día para dashboard
     */
    public function getResumenDia($empresa_id, $fecha = null) {
        $fecha = $fecha ?? date('Y-m-d');
        $stmt = $this->db->prepare("
            SELECT
                COUNT(DISTINCT ra.usuario_id) as total_marcaciones,
                SUM(CASE WHEN ra.tipo_marcacion = 'entrada' THEN 1 ELSE 0 END) as entradas,
                SUM(CASE WHEN ra.estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes
            FROM registros_asistencia ra
            WHERE ra.empresa_id = :empresa_id AND ra.fecha = :fecha
        ");
        $stmt->execute([':empresa_id' => $empresa_id, ':fecha' => $fecha]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener resumen de la jornada de HOY para el empleado
     */
    public function getJornadaHoy($usuario_id, $fecha = null) {
        $fecha = $fecha ?? date('Y-m-d');
        $stmt = $this->db->prepare("
            SELECT
                MAX(CASE WHEN tipo_marcacion = 'entrada' THEN hora_registro END) as entrada,
                MIN(CASE WHEN tipo_marcacion = 'salida_almuerzo' THEN hora_registro END) as salida_almuerzo,
                MAX(CASE WHEN tipo_marcacion = 'regreso_almuerzo' THEN hora_registro END) as regreso_almuerzo,
                MAX(CASE WHEN tipo_marcacion = 'salida' THEN hora_registro END) as salida
            FROM registros_asistencia
            WHERE usuario_id = :usuario_id AND fecha = :fecha
        ");
        $stmt->execute([':usuario_id' => $usuario_id, ':fecha' => $fecha]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Calcular horas trabajadas basado en marcaciones
     */
    public function calcularHorasTrabajadas($jornada) {
        $horas = 0;
        $entrada     = $jornada['entrada'] ? strtotime("1970-01-01 {$jornada['entrada']} UTC") : null;
        $salida_alm  = $jornada['salida_almuerzo'] ? strtotime("1970-01-01 {$jornada['salida_almuerzo']} UTC") : null;
        $regreso_alm = $jornada['regreso_almuerzo'] ? strtotime("1970-01-01 {$jornada['regreso_almuerzo']} UTC") : null;
        $salida      = $jornada['salida'] ? strtotime("1970-01-01 {$jornada['salida']} UTC") : null;
        if ($entrada && $salida_alm) $horas += ($salida_alm - $entrada);
        if ($regreso_alm && $salida) $horas += ($salida - $regreso_alm);
        if ($entrada && $salida && !$salida_alm && !$regreso_alm) $horas += ($salida - $entrada);
        return round($horas / 3600, 2);
    }

    /**
     * Obtener resumen mensual del empleado
     */
    public function getResumenMensual($usuario_id, $anio, $mes) {
        $stmt = $this->db->prepare("
            SELECT
                COUNT(DISTINCT fecha) as dias_trabajados,
                COUNT(CASE WHEN tipo_marcacion = 'entrada' THEN 1 END) as total_entradas
            FROM registros_asistencia
            WHERE usuario_id = :usuario_id AND YEAR(fecha) = :anio AND MONTH(fecha) = :mes
        ");
        $stmt->execute([':usuario_id' => $usuario_id, ':anio' => $anio, ':mes' => $mes]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /* =====================================================
       NUEVOS MÉTODOS DE APOYO (no modifican los existentes)
       ===================================================== */

    /** Marcaciones por fecha para el listado de admin */
    public function getMarcacionesPorFecha($fecha, $empresa_id = null) {
        return $this->getAllMarcaciones($empresa_id, $fecha);
    }

    /** Alias claro para la vista de marcación */
    public function getMarcacionesUsuarioHoy($usuario_id, $fecha = null) {
        return $this->getMarcacionesHoy($usuario_id, $fecha);
    }

        /**
     * Sedes para el selector de marcación:
     * - Empresa activa: solo SUS sucursales (la empresa es la cabeza/root de ellas)
     * - SuperAdmin en modo global: todas las empresas, cada una con sus sucursales
     */
    public function getSedes($empresa_id, $todas = false) {
        if ($todas) {
            $stmt = $this->db->query("
                SELECT s.id, s.nombre, s.empresa_id, e.nombre AS empresa_nombre
                FROM sedes s
                LEFT JOIN empresas e ON e.id = s.empresa_id
                ORDER BY e.nombre ASC, s.nombre ASC
            ");
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }
        $stmt = $this->db->prepare("
            SELECT s.id, s.nombre, s.empresa_id, e.nombre AS empresa_nombre
            FROM sedes s
            LEFT JOIN empresas e ON e.id = s.empresa_id
            WHERE s.empresa_id = ?
            ORDER BY s.nombre ASC
        ");
        $stmt->execute([(int)($empresa_id ?? 1)]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}