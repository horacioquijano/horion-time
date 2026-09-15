<?php
namespace App\Services;
use PDO;

class AlertRulesEngine {
    private $db;
    private $notifier;

    public function __construct($db) {
        $this->db = $db;
        $this->notifier = new NotificationEngine($db);
    }

    /**
     * Ejecutar todas las reglas de alerta (Llamar vía Cron cada 5-10 minutos)
     */
    public function ejecutarReglas() {
        $this->reglaLlegadasTarde();
        $this->reglaSinMarcacion();
        $this->reglaNovedadesPendientes();
    }

    /**
     * Regla 1: Detectar llegadas tarde y notificar al empleado y supervisor
     */
    private function reglaLlegadasTarde() {
        $hoy = date('Y-m-d');
        $stmt = $this->db->prepare("
            SELECT ra.usuario_id, ra.empresa_id, u.email, u.nombre_completo, 
                   TIME(ra.hora_registro) as hora_real, h.jornada_diurna_inicio, h.tolerancia_entrada,
                   sup.email as email_jefe, sup.nombre_completo as nombre_jefe
            FROM registros_asistencia ra
            JOIN usuarios u ON ra.usuario_id = u.id
            JOIN horarios h ON u.horario_asignado_id = h.id
            LEFT JOIN usuarios sup ON u.jefe_inmediato_id = sup.id
            WHERE ra.fecha = :fecha AND ra.tipo_marcacion = 'entrada'
            AND TIME(ra.hora_registro) > TIME_ADD(h.jornada_diurna_inicio, INTERVAL h.tolerancia_entrada MINUTE)
            AND NOT EXISTS (
                SELECT 1 FROM alertas a WHERE a.usuario_id = ra.usuario_id AND a.fecha_creacion = CURDATE() AND a.titulo LIKE '%llegada tarde%'
            )
        ");
        $stmt->execute([':fecha' => $hoy]);
        $tardanzas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($tardanzas as $t) {
            $minutos_tarde = (strtotime($t['hora_real']) - strtotime($t['jornada_diurna_inicio'])) / 60;
            $msg = "Registraste entrada a las {$t['hora_real']} ({$minutos_tarde} min tarde).";
            
            // Notificar al empleado
            $this->notifier->sendInApp($t['empresa_id'], $t['usuario_id'], 'warning', 'Llegada tarde detectada', $msg, 2);
            
            // Notificar al jefe (si existe y tiene preferencias activas)
            if (!empty($t['email_jefe'])) {
                $this->notifier->sendInApp($t['empresa_id'], $t['jefe_inmediato_id'] ?? 0, 'info', 'Colaborador llegó tarde', "{$t['nombre_completo']} llegó {$minutos_tarde} min tarde.", 1);
                $this->notifier->queueEmail($t['email_jefe'], 'Alerta de Asistencia: Llegada Tarde', "El colaborador {$t['nombre_completo']} registró entrada tarde hoy.");
            }
        }
    }

    /**
     * Regla 2: Empleados sin marcación de entrada pasada la hora tolerada
     */
    private function reglaSinMarcacion() {
        // Lógica similar a la Fase 7, pero encolando emails a RRHH
        // ... (Simplificado para el ejemplo)
    }

    /**
     * Regla 3: Novedades pendientes de aprobación por más de 24h
     */
    private function reglaNovedadesPendientes() {
        $stmt = $this->db->query("
            SELECT n.id, n.usuario_id, n.empresa_id, u.nombre_completo, u.email, sup.email as email_jefe
            FROM novedades n
            JOIN usuarios u ON n.usuario_id = u.id
            LEFT JOIN usuarios sup ON u.jefe_inmediato_id = sup.id
            WHERE n.estado = 'pendiente' AND n.fecha_solicitud < DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $pendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($pendientes as $p) {
            if (!empty($p['email_jefe'])) {
                $this->notifier->queueEmail($p['email_jefe'], 'Acción Requerida: Novedad Pendiente', "Tienes novedades de {$p['nombre_completo']} pendientes de aprobación desde hace más de 24h.");
            }
        }
    }
}