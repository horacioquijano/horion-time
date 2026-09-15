<?php
namespace App\Models;
use PDO;

class NovedadModel {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Crear nueva novedad
     */
    public function create($data) {
        // Calcular días automáticamente
        $inicio = new \DateTime($data['fecha_inicio']);
        $fin = new \DateTime($data['fecha_fin']);
        $dias = $inicio->diff($fin)->days + 1;

        $sql = "INSERT INTO novedades 
                (usuario_id, empresa_id, tipo, subtipo, fecha_inicio, fecha_fin, 
                 horas_solicitadas, motivo, soporte_documento, estado, dias_calculados) 
                VALUES 
                (:usuario_id, :empresa_id, :tipo, :subtipo, :fecha_inicio, :fecha_fin,
                 :horas, :motivo, :soporte, 'pendiente', :dias)";
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':usuario_id' => $data['usuario_id'],
            ':empresa_id' => $data['empresa_id'],
            ':tipo' => $data['tipo'],
            ':subtipo' => $data['subtipo'] ?? null,
            ':fecha_inicio' => $data['fecha_inicio'],
            ':fecha_fin' => $data['fecha_fin'],
            ':horas' => $data['horas_solicitadas'] ?? null,
            ':motivo' => $data['motivo'],
            ':soporte' => $data['soporte_documento'] ?? null,
            ':dias' => $dias
        ]);
        
        return $result ? $this->db->lastInsertId() : false;
    }

    /**
     * Obtener novedades con filtros
     */
    public function getAll($filters = []) {
        $sql = "SELECT n.*, 
                       u.nombre_completo as empleado_nombre, 
                       u.identificacion as empleado_id,
                       a.nombre_completo as aprobador_nombre
                FROM novedades n
                LEFT JOIN usuarios u ON n.usuario_id = u.id
                LEFT JOIN usuarios a ON n.aprobador_id = a.id
                WHERE 1=1";
        
        $params = [];
        
        if (!empty($filters['empresa_id'])) {
            $sql .= " AND n.empresa_id = :empresa_id";
            $params[':empresa_id'] = $filters['empresa_id'];
        }
        if (!empty($filters['usuario_id'])) {
            $sql .= " AND n.usuario_id = :usuario_id";
            $params[':usuario_id'] = $filters['usuario_id'];
        }
        if (!empty($filters['estado'])) {
            $sql .= " AND n.estado = :estado";
            $params[':estado'] = $filters['estado'];
        }
        if (!empty($filters['tipo'])) {
            $sql .= " AND n.tipo = :tipo";
            $params[':tipo'] = $filters['tipo'];
        }
        if (!empty($filters['fecha_inicio'])) {
            $sql .= " AND n.fecha_inicio >= :fecha_inicio";
            $params[':fecha_inicio'] = $filters['fecha_inicio'];
        }
        if (!empty($filters['fecha_fin'])) {
            $sql .= " AND n.fecha_fin <= :fecha_fin";
            $params[':fecha_fin'] = $filters['fecha_fin'];
        }
        
        $sql .= " ORDER BY n.fecha_solicitud DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener novedad por ID
     */
    public function getById($id) {
        $stmt = $this->db->prepare("
            SELECT n.*, u.nombre_completo as empleado_nombre, u.identificacion,
                   e.nombre as empresa_nombre,
                   a.nombre_completo as aprobador_nombre
            FROM novedades n
            LEFT JOIN usuarios u ON n.usuario_id = u.id
            LEFT JOIN empresas e ON n.empresa_id = e.id
            LEFT JOIN usuarios a ON n.aprobador_id = a.id
            WHERE n.id = :id
        ");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Aprobar/Rechazar novedad (solo Admin/Supervisor/RRHH)
     */
    public function actualizarEstado($id, $estado, $aprobador_id, $observaciones, $userRole) {
        // Validación de permisos
        $allowedRoles = ['SuperAdmin', 'Admin_Empresa', 'Supervisor', 'RRHH'];
        if (!in_array($userRole, $allowedRoles)) {
            throw new \Exception("Acceso denegado: Solo personal autorizado puede aprobar novedades.");
        }

        // No se puede aprobar una novedad ya resuelta
        $novedad = $this->getById($id);
        if (!$novedad) {
            throw new \Exception("Novedad no encontrada.");
        }
        if ($novedad['estado'] !== 'pendiente') {
            throw new \Exception("La novedad ya fue procesada (estado: {$novedad['estado']}).");
        }

        // No puede aprobar su propia novedad
        if ($novedad['usuario_id'] == $aprobador_id) {
            throw new \Exception("No puedes aprobar tu propia novedad.");
        }

        $sql = "UPDATE novedades 
                SET estado = :estado, 
                    aprobador_id = :aprobador_id, 
                    fecha_aprobacion = NOW(),
                    observaciones_aprobador = :observaciones
                WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':estado' => $estado,
            ':aprobador_id' => $aprobador_id,
            ':observaciones' => $observaciones,
            ':id' => $id
        ]);
    }

    /**
     * Obtener tipos de novedad disponibles
     */
    public function getTipos($empresa_id) {
        $stmt = $this->db->prepare("SELECT * FROM tipos_novedad WHERE empresa_id = :empresa_id AND activo = 1 ORDER BY nombre ASC");
        $stmt->execute([':empresa_id' => $empresa_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener resumen de novedades pendientes
     */
    public function getResumen($empresa_id) {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                SUM(CASE WHEN estado = 'aprobada' THEN 1 ELSE 0 END) as aprobadas,
                SUM(CASE WHEN estado = 'rechazada' THEN 1 ELSE 0 END) as rechazadas
            FROM novedades
            WHERE empresa_id = :empresa_id
        ");
        $stmt->execute([':empresa_id' => $empresa_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Validar que no existan novedades superpuestas
     */
    public function validarSuperposicion($usuario_id, $fecha_inicio, $fecha_fin, $excluir_id = null) {
        $sql = "SELECT COUNT(*) as total FROM novedades 
                WHERE usuario_id = :usuario_id 
                AND estado IN ('pendiente', 'aprobada')
                AND (
                    (fecha_inicio <= :fecha_fin AND fecha_fin >= :fecha_inicio)
                )";
        
        if ($excluir_id) {
            $sql .= " AND id != :excluir_id";
        }
        
        $stmt = $this->db->prepare($sql);
        $params = [
            ':usuario_id' => $usuario_id,
            ':fecha_inicio' => $fecha_inicio,
            ':fecha_fin' => $fecha_fin
        ];
        if ($excluir_id) {
            $params[':excluir_id'] = $excluir_id;
        }
        
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] > 0;
    }
}