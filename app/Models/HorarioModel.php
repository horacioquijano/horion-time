<?php
namespace App\Models;
use PDO;

class HorarioModel {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    // ========== Utilidades de estructura ==========
    private function columnas(string $tabla): array {
        try {
            return $this->db->query("SHOW COLUMNS FROM `$tabla`")->fetchAll(PDO::FETCH_COLUMN);
        } catch (\Throwable $e) { return []; }
    }

    private function columnasInfo(string $tabla): array {
        try {
            return $this->db->query("SHOW COLUMNS FROM `$tabla`")->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) { return []; }
    }

    private function tablaExiste(string $tabla): bool {
        try {
            $st = $this->db->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
            $st->execute([$tabla]);
            return (int)$st->fetchColumn() > 0;
        } catch (\Throwable $e) { return false; }
    }

    private function pick(array $cols, array $candidatas): ?string {
        foreach ($candidatas as $c) if (in_array($c, $cols, true)) return $c;
        return null;
    }

    private function valorSeguro(string $type) {
        $type = strtolower($type);
        if (strpos($type, 'enum') === 0 && preg_match("/^enum\((.*)\)$/s", $type, $m)) {
            $opts = str_getcsv($m[1], "'", "\\");
            return (string)($opts[0] ?? '');
        }
        if (strpos($type, 'time') !== false) return '00:00:00';
        if (strpos($type, 'date') !== false) return date('Y-m-d');
        if (preg_match('/(int|decimal|float|double|bit)/', $type)) return 0;
        return '';
    }

    /** Fila completa: alias conocidos + relleno de NOT NULL sin default */
    private function filaCompleta(array $colsInfo, array $base): array {
        $fila = [];
        foreach ($colsInfo as $ci) {
            $field = $ci['Field'];
            $type  = strtolower((string)$ci['Type']);
            $null  = strtoupper((string)$ci['Null']) === 'YES';
            $extra = strtolower((string)$ci['Extra']);
            if (strpos($extra, 'auto_increment') !== false) continue;
            if (array_key_exists($field, $base)) {
                $val = $base[$field];
                if (strpos($type, 'enum') === 0 && preg_match("/^enum\((.*)\)$/s", $type, $m)) {
                    $opts = str_getcsv($m[1], "'", "\\");
                    if (!in_array((string)$val, $opts, true)) $val = $opts[0] ?? '';
                }
                $fila[$field] = $val;
                continue;
            }
            if (!$null && $ci['Default'] === null) {
                $fila[$field] = $this->valorSeguro($type);
            }
        }
        return $fila;
    }

    // ========== Lecturas ==========
    public function getHorarios($empresa_id) {
        $tieneEH = $this->tablaExiste('empleado_horario');
        $sub = $tieneEH
            ? "(SELECT COUNT(*) FROM empleado_horario eh WHERE eh.horario_id = h.id AND eh.estado = 'activo')"
            : "(SELECT COUNT(*) FROM usuarios u WHERE u.horario_asignado_id = h.id AND u.estado = 'activo')";
        $sql = "SELECT h.*, $sub AS empleados_asignados FROM horarios h WHERE h.empresa_id = ? ORDER BY h.nombre";
        $st = $this->db->prepare($sql);
        $st->execute([(int)$empresa_id]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTurnosHorario($horario_id) {
        if (!$this->tablaExiste('turnos')) return [];
        $cols = $this->columnas('turnos');
        $colH = $this->pick($cols, ['horario_id']);
        if (!$colH) return [];
        $colDia = $this->pick($cols, ['dia_semana', 'dia', 'dia_id']);
        $sql = "SELECT * FROM turnos WHERE `$colH` = ?" . ($colDia ? " ORDER BY `$colDia`" : "");
        $st = $this->db->prepare($sql);
        $st->execute([(int)$horario_id]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getHorarioEmpleado($usuario_id, $fecha = null) {
        $fecha = $fecha ?? date('Y-m-d');
        if ($this->tablaExiste('empleado_horario')) {
            $st = $this->db->prepare("
                SELECT h.*, eh.fecha_inicio, eh.fecha_fin
                FROM empleado_horario eh
                INNER JOIN horarios h ON eh.horario_id = h.id
                WHERE eh.usuario_id = ? AND eh.estado = 'activo'
                  AND eh.fecha_inicio <= ?
                  AND (eh.fecha_fin IS NULL OR eh.fecha_fin >= ?)
                ORDER BY eh.fecha_inicio DESC LIMIT 1");
            $st->execute([(int)$usuario_id, $fecha, $fecha]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if ($row) return $row;
        }
        try {
            $st = $this->db->prepare("
                SELECT h.* FROM usuarios u
                INNER JOIN horarios h ON u.horario_asignado_id = h.id
                WHERE u.id = ? LIMIT 1");
            $st->execute([(int)$usuario_id]);
            return $st->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (\Throwable $e) { return null; }
    }

    public function getTurnoEmpleadoDia($usuario_id, $fecha) {
        $h = $this->getHorarioEmpleado($usuario_id, $fecha);
        if (!$h) return null;
        $dow = (int)date('w', strtotime($fecha));

        if ($this->tablaExiste('turnos')) {
            $cols = $this->columnas('turnos');
            $colH = $this->pick($cols, ['horario_id']);
            $colDia = $this->pick($cols, ['dia_semana', 'dia', 'dia_id']);
            $colEnt = $this->pick($cols, ['hora_entrada', 'hora_inicio', 'entrada']);
            $colSal = $this->pick($cols, ['hora_salida', 'hora_fin', 'salida']);
            if ($colH && $colDia && $colEnt && $colSal) {
                $st = $this->db->prepare("SELECT * FROM turnos WHERE `$colH` = ? AND `$colDia` = ? LIMIT 1");
                $st->execute([(int)$h['id'], $dow]);
                $t = $st->fetch(PDO::FETCH_ASSOC);
                if ($t) {
                    return [
                        'hora_entrada' => $t[$colEnt],
                        'hora_salida'  => $t[$colSal],
                        'es_descanso'  => (int)($t[$this->pick($cols, ['es_descanso', 'descanso']) ?? 'es_descanso'] ?? 0),
                        'tolerancia_entrada_min' => (int)($t[$this->pick($cols, ['tolerancia_entrada_min', 'tolerancia_entrada']) ?? 'x'] ?? ($h['tolerancia_entrada'] ?? 10)),
                        'tolerancia_salida_min'  => (int)($t[$this->pick($cols, ['tolerancia_salida_min', 'tolerancia_salida']) ?? 'x'] ?? ($h['tolerancia_salida'] ?? 5)),
                        'origen' => 'turnos',
                    ];
                }
            }
        }
        return [
            'hora_entrada' => $h['jornada_diurna_inicio'] ?? '06:00:00',
            'hora_salida'  => $h['jornada_diurna_fin'] ?? '22:00:00',
            'es_descanso'  => 0,
            'tolerancia_entrada_min' => (int)($h['tolerancia_entrada'] ?? 10),
            'tolerancia_salida_min'  => (int)($h['tolerancia_salida'] ?? 5),
            'origen' => 'horario',
        ];
    }

    public function getEmpleadosConHorario($empresa_id) {
        $tieneEH = $this->tablaExiste('empleado_horario');
        if ($tieneEH) {
            $sql = "SELECT u.*, COALESCE(h.nombre, h2.nombre) AS horario_nombre, eh.fecha_inicio, eh.fecha_fin
                    FROM usuarios u
                    LEFT JOIN empleado_horario eh ON u.id = eh.usuario_id AND eh.estado = 'activo'
                    LEFT JOIN horarios h  ON eh.horario_id = h.id
                    LEFT JOIN horarios h2 ON u.horario_asignado_id = h2.id
                    WHERE u.empresa_id = ? AND u.estado = 'activo'
                    ORDER BY u.nombre_completo";
        } else {
            $sql = "SELECT u.*, h.nombre AS horario_nombre, NULL AS fecha_inicio, NULL AS fecha_fin
                    FROM usuarios u
                    LEFT JOIN horarios h ON u.horario_asignado_id = h.id
                    WHERE u.empresa_id = ? AND u.estado = 'activo'
                    ORDER BY u.nombre_completo";
        }
        $st = $this->db->prepare($sql);
        $st->execute([(int)$empresa_id]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ========== Escrituras ==========
    public function crearHorario($data) {
        $cols = $this->columnas('horarios');
        $nombre = trim($data['nombre'] ?? '');
        if ($nombre === '') $nombre = 'Horario ' . date('Y-m-d H:i');
        $fila = ['nombre' => $nombre];
        $map = [
            'empresa_id'              => (int)($data['empresa_id'] ?? $_SESSION['empresa_id'] ?? 1),
            'descripcion'             => trim($data['descripcion'] ?? ''),
            'horas_semanales'         => (float)($data['horas_semanales'] ?? 48),
            'tipo'                    => $data['tipo'] ?? 'fijo',
            'tolerancia_entrada'      => (int)($data['tolerancia_entrada'] ?? 10),
            'tolerancia_salida'       => (int)($data['tolerancia_salida'] ?? 5),
            'jornada_diurna_inicio'   => $data['jornada_diurna_inicio'] ?? '06:00:00',
            'jornada_diurna_fin'      => $data['jornada_diurna_fin'] ?? '22:00:00',
            'jornada_nocturna_inicio' => $data['jornada_nocturna_inicio'] ?? '22:00:00',
            'jornada_nocturna_fin'    => $data['jornada_nocturna_fin'] ?? '06:00:00',
            'descanso_almuerzo_inicio'=> $data['descanso_almuerzo_inicio'] ?? '12:00:00',
            'descanso_almuerzo_fin'   => $data['descanso_almuerzo_fin'] ?? '13:00:00',
            'estado'                  => 'activo',
        ];
        foreach ($map as $k => $v) {
            if (!in_array($k, $cols, true)) continue;
            if ($v === '' || $v === null) continue;
            $fila[$k] = $v;
        }
        $campos = implode(', ', array_map(fn($k) => "`$k`", array_keys($fila)));
        $marks  = implode(', ', array_map(fn($k) => ":$k", array_keys($fila)));
        $st = $this->db->prepare("INSERT INTO horarios ($campos) VALUES ($marks)");
        foreach ($fila as $k => $v) $st->bindValue(":$k", $v);
        $st->execute();
        return (int)$this->db->lastInsertId();
    }

    /** CREAR TURNOS v2: respeta cualquier esquema NOT NULL de `turnos` */
    public function crearTurnos($horario_id, $turnos) {
        if (!$this->tablaExiste('turnos')) return 0;
        $colsInfo = $this->columnasInfo('turnos');
        if (!$colsInfo) return 0;
        $insertados = 0;
        foreach ($turnos as $dia => $t) {
            $esDesc = (int)($t['es_descanso'] ?? 0);
            $ent = $esDesc ? '00:00:00' : (string)($t['hora_entrada'] ?? '');
            $sal = $esDesc ? '00:00:00' : (string)($t['hora_salida'] ?? '');
            if (!$esDesc && ($ent === '' || $sal === '')) continue;
            $base = [
                'horario_id'   => (int)$horario_id,
                'dia_semana'   => (int)$dia, 'dia' => (int)$dia, 'dia_id' => (int)$dia,
                'nombre'       => (string)($t['nombre'] ?? ($esDesc ? 'Descanso' : 'Turno')),
                'hora_entrada' => $ent, 'hora_salida' => $sal,
                'hora_inicio'  => $ent, 'hora_fin'    => $sal,
                'entrada'      => $ent, 'salida'      => $sal,
                'es_descanso'  => $esDesc, 'descanso' => $esDesc,
                'tolerancia_entrada_min' => (int)($t['tolerancia_entrada_min'] ?? 10),
                'tolerancia_salida_min'  => (int)($t['tolerancia_salida_min'] ?? 10),
                'tolerancia_entrada'     => (int)($t['tolerancia_entrada_min'] ?? 10),
                'tolerancia_salida'      => (int)($t['tolerancia_salida_min'] ?? 10),
                'empresa_id'   => (int)($t['empresa_id'] ?? $_SESSION['empresa_id'] ?? 1),
                'estado'       => 'activo',
            ];
            $fila = $this->filaCompleta($colsInfo, $base);
            try {
                $campos = implode(', ', array_map(fn($k) => "`$k`", array_keys($fila)));
                $marks  = implode(', ', array_map(fn($k) => ":$k", array_keys($fila)));
                $st = $this->db->prepare("INSERT INTO turnos ($campos) VALUES ($marks)");
                foreach ($fila as $k => $v) $st->bindValue(":$k", $v);
                $st->execute();
                $insertados++;
            } catch (\Throwable $e) { /* un día fallando no tumba el resto */ }
        }
        return $insertados;
    }

    public function asignarHorario($usuario_id, $horario_id, $fecha_inicio, $fecha_fin = null) {
        if ($this->tablaExiste('empleado_horario')) {
            $this->db->prepare("UPDATE empleado_horario SET estado = 'inactivo' WHERE usuario_id = ? AND estado = 'activo'")
                     ->execute([(int)$usuario_id]);
            $cols = $this->columnas('empleado_horario');
            $fila = ['usuario_id' => (int)$usuario_id, 'horario_id' => (int)$horario_id, 'fecha_inicio' => $fecha_inicio];
            if (in_array('fecha_fin', $cols, true))  $fila['fecha_fin'] = $fecha_fin;
            if (in_array('estado', $cols, true))     $fila['estado'] = 'activo';
            $campos = implode(', ', array_map(fn($k) => "`$k`", array_keys($fila)));
            $marks  = implode(', ', array_map(fn($k) => ":$k", array_keys($fila)));
            $st = $this->db->prepare("INSERT INTO empleado_horario ($campos) VALUES ($marks)");
            foreach ($fila as $k => $v) $st->bindValue(":$k", $v);
            $st->execute();
        }
        try {
            $this->db->prepare("UPDATE usuarios SET horario_asignado_id = ? WHERE id = ?")
                     ->execute([(int)$horario_id, (int)$usuario_id]);
        } catch (\Throwable $e) {}
        return true;
    }

    public function debeTrabajar($usuario_id, $fecha, $hora = null) {
        $turno = $this->getTurnoEmpleadoDia($usuario_id, $fecha);
        if (!$turno || !empty($turno['es_descanso'])) return false;
        if ($hora) return ($hora >= $turno['hora_entrada'] && $hora <= $turno['hora_salida']);
        return true;
    }
}
