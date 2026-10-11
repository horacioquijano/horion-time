<?php
namespace App\Controllers;
require_once __DIR__ . '/../Models/AsistenciaModel.php';
require_once __DIR__ . '/../Helpers/Security.php';
use App\Models\AsistenciaModel;
use App\Helpers\Security;

class AsistenciaController {
    private $model;
    private $db;
    const UMBRAL_ROSTRO = 0.55;
    
    public function __construct($db) {
        $this->db = $db;
        $this->model = new AsistenciaModel($db);
    }
    
    private function filtros() {
        $modoGlobal = (bool)($_SESSION['modo_global'] ?? false);
        $empresa = $_GET['empresa'] ?? null;
        return [
            'fecha_desde' => $_GET['desde'] ?? $_GET['fecha'] ?? date('Y-m-d'),
            'fecha_hasta' => $_GET['hasta'] ?? $_GET['fecha'] ?? date('Y-m-d'),
            'empresa_id'  => $modoGlobal ? ($empresa !== '' && $empresa !== null ? (int)$empresa : null) : (int)($_SESSION['empresa_id'] ?? 1),
            'sede_id'     => ($_GET['sede'] ?? '') !== '' ? (int)$_GET['sede'] : null,
            'estado'      => ($_GET['estado'] ?? '') !== '' ? $_GET['estado'] : null,
            'q'           => trim((string)($_GET['q'] ?? '')),
        ];
    }
    
    public function index() {
        $filtros = $this->filtros();
        $marcaciones = $this->model->getMarcacionesFiltradas($filtros);
        $modoGlobal = (bool)($_SESSION['modo_global'] ?? false);
        $sedes = $this->model->getSedes($_SESSION['empresa_id'] ?? 1, $modoGlobal);
        $empresas = $_SESSION['empresas_lista'] ?? [];
        $GLOBALS['pageTitle'] = 'Registro de Asistencia';
        include __DIR__ . '/../Views/asistencia/index.php';
    }
    
    public function exportar() {
        $filtros = $this->filtros();
        $marcaciones = $this->model->getMarcacionesFiltradas($filtros);
        $nombre = 'asistencia_' . $filtros['fecha_desde'] . '_a_' . $filtros['fecha_hasta'] . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $nombre . '"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Fecha','Hora','Empleado','Cédula','Tipo','Método','Sede','Turno del día','Horario turno','Estado','Observaciones','Lat','Lng']);
        foreach ($marcaciones as $m) {
            $turno = $m['turno_codigo'] ?? '';
            $horario = '';
            if ($turno !== '' && !empty($m['turno_entrada'])) {
                $horario = substr($m['turno_entrada'], 0, 5) . '-' . substr($m['turno_salida'], 0, 5);
            }
            fputcsv($out, [
                $m['fecha'], $m['hora_registro'], $m['nombre_completo'] ?? '', $m['identificacion'] ?? '',
                $m['tipo_marcacion'], $m['metodo_marcacion'], $m['sede_nombre'] ?? '',
                $turno !== '' ? $turno : 'SIN TURNO', $horario, $m['estado'],
                $m['observaciones'] ?? '', $m['lat'] ?? '', $m['lng'] ?? '',
            ]);
        }
        fclose($out);
        exit;
    }
    
    public function marcar() {
        $csrf_token = Security::generateCsrfToken();
        $marcaciones_hoy = $this->model->getMarcacionesUsuarioHoy($_SESSION['usuario_id'] ?? 1);
        $sedes = $this->model->getSedes(
            $_SESSION['empresa_id'] ?? 1,
            (($_SESSION['rol_nombre'] ?? '') === 'SuperAdmin' && (bool)($_SESSION['modo_global'] ?? false))
        );
        $GLOBALS['pageTitle'] = 'Mi Marcación';
        include __DIR__ . '/../Views/asistencia/marcar.php';
    }
    
    public function procesar() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /horion-time/public/asistencia/marcar'); exit;
        }
        try {
            $foto = null;
            if (!empty($_POST['foto_data'])) {
                $data = $_POST['foto_data'];
                if (strpos($data, ',') !== false) {
                    $bin = base64_decode(substr($data, strpos($data, ',') + 1));
                    if ($bin !== false) {
                        $dir = __DIR__ . '/../../public/uploads/marcaciones/';
                        if (!is_dir($dir)) mkdir($dir, 0777, true);
                        $nombre = 'marcacion_' . ($_SESSION['usuario_id'] ?? 0) . '_' . date('Ymd_His') . '.jpg';
                        file_put_contents($dir . $nombre, $bin);
                        $foto = '/horion-time/public/uploads/marcaciones/' . $nombre;
                    }
                }
            }
            $_POST['fecha'] = $_POST['fecha'] ?? date('Y-m-d');
            $_POST['hora_registro'] = $_POST['hora_registro'] ?? date('H:i:s');
            foreach (['lat', 'lng', 'accuracy', 'precision', 'distancia_m'] as $__gps) {
                if (array_key_exists($__gps, $_POST)) {
                    $__val = trim((string)$_POST[$__gps]);
                    $_POST[$__gps] = ($__val === '') ? null : $__val;
                }
            }
            $uid = (int)($_SESSION['usuario_id'] ?? 1);
            $empresa_id = (int)($_SESSION['empresa_id'] ?? 1);
            $observaciones = trim((string)($_POST['observaciones'] ?? ''));
            $sede_id = !empty($_POST['sede_id']) ? (int)$_POST['sede_id'] : null;
            
            // ----- CANDADO GPS -----
            $ok_gps = null; // true=dentro/sin georef, false=fuera, null=sin GPS
            if (!empty($_POST['lat']) && !empty($_POST['lng'])) {
                if ($sede_id) {
                    $distancia = $this->calcularDistancia($sede_id, (float)$_POST['lat'], (float)$_POST['lng']);
                    if ($distancia !== null) {
                        $sede = $this->obtenerSede($sede_id);
                        $radio = (int)($sede['radio_m'] ?? 150);
                        if ($distancia <= $radio) {
                            $ok_gps = true;
                            $observaciones = trim($observaciones . " [Dentro del radio: " . round($distancia) . " m]");
                        } else {
                            $ok_gps = false;
                            $observaciones = trim($observaciones . " [FUERA DE RANGO: " . round($distancia) . " m de " . $sede['nombre'] . " (radio: {$radio} m)]");
                        }
                    } else {
                        $ok_gps = true;
                        $observaciones = trim($observaciones . " [Sede sin georeferencia]");
                    }
                } else { $ok_gps = true; }
            } else {
                $observaciones = trim($observaciones . " [SIN GPS]");
            }
            
            // ----- CANDADO ROSTRO -----
            $live = json_decode((string)($_POST['face_descriptor'] ?? ''), true);
            $live_ok = is_array($live) && count($live) === 128;
            $st = $this->db->prepare("SELECT face_descriptor FROM usuarios WHERE id = ? LIMIT 1");
            $st->execute([$uid]);
            $guardado = $st->fetchColumn();
            $ok_face = null; // true, false, o 'manual'
            if ($live_ok) {
                if (empty($guardado)) {
                    $this->guardarDescriptor($uid, json_encode($live));
                    $ok_face = true;
                    $observaciones = trim("[ROSTRO ENROLADO] " . $observaciones);
                } else {
                    $arr = json_decode((string)$guardado, true);
                    if (is_array($arr) && count($arr) === 128) {
                        $dist = $this->distanciaRostro($live, $arr);
                        if ($dist <= self::UMBRAL_ROSTRO) {
                            $ok_face = true;
                            $observaciones = trim("[ROSTRO OK d=" . number_format($dist, 2) . "] " . $observaciones);
                        } else {
                            $ok_face = false;
                            $observaciones = trim("[ROSTRO NO COINCIDE d=" . number_format($dist, 2) . "] " . $observaciones);
                        }
                    } else {
                        $ok_face = 'manual';
                        $observaciones = trim("[VECTOR GUARDADO DAÑADO - VERIFICACIÓN MANUAL] " . $observaciones);
                    }
                }
            } else {
                if (empty($guardado)) {
                    $ok_face = 'manual';
                    $observaciones = trim("[ENROLADO CON FOTO - SIN VECTOR] " . $observaciones);
                } else {
                    $ok_face = 'manual';
                    $observaciones = trim("[VERIFICACIÓN MANUAL - MODO COMPATIBILIDAD] " . $observaciones);
                }
            }
            
            // ----- TURNO DE HOY (Panel) -----
            $turno = $this->turnoDeHoy($uid);
            $es_vacacion = $turno && (int)$turno['es_vacacion'] === 1;
            $es_libre    = $turno && (int)$turno['es_descanso'] === 1;
            if ($es_vacacion) $observaciones = trim("[VACACIONES según panel] " . $observaciones);
            elseif ($es_libre) $observaciones = trim("[DÍA LIBRE según panel: candidato a horas extra] " . $observaciones);
            elseif (!$turno) $observaciones = trim("[SIN TURNO PROGRAMADO] " . $observaciones);
            
            // ===== AUTO-VALIDACIÓN (sin aprobación manual en flujo normal) =====
            // Dentro del radio de la sede = presencia confirmada → el sistema valida SOLO.
            // RRHH solo revisa excepciones: vacaciones, rostro no coincide, fuera de rango, sin GPS.
            $estado = 'pendiente';
            if ($es_vacacion) {
                $estado = 'pendiente';
            } elseif ($ok_face === false) {
                $estado = 'pendiente';
            } elseif ($ok_gps === false) {
                $estado = 'pendiente';
            } elseif ($ok_gps === null) {
                $estado = 'pendiente';
            } else {
                $estado = 'validado';
                $observaciones = trim($observaciones . ($ok_face === 'manual'
                    ? " [AUTO-VALIDADO por GPS: rostro en modo compatibilidad]"
                    : " [AUTO-VALIDADO: GPS dentro de rango + rostro OK]"));
            }
            
            $this->model->registrarMarcacion([
                'empresa_id' => $empresa_id, 'usuario_id' => $uid, 'sede_id' => $sede_id,
                'fecha' => $_POST['fecha'], 'hora_registro' => $_POST['hora_registro'],
                'tipo_marcacion' => $_POST['tipo_marcacion'] ?? 'entrada',
                'metodo_marcacion' => $_POST['metodo_marcacion'] ?? 'facial',
                'foto_evidencia' => $foto, 'lat' => $_POST['lat'] ?? null, 'lng' => $_POST['lng'] ?? null,
                'estado' => $estado, 'observaciones' => $observaciones, 'creado_por' => $uid,
            ]);
            $refId = (int)$this->db->lastInsertId();
            
            // ----- ALERTAS AUTOMÁTICAS (solo excepciones) -----
            $nombreEmp = $_SESSION['nombre_completo'] ?? ('Usuario ' . $uid);
            $hora = date('d/m H:i');
            if ($ok_gps === false) $this->alertarRRHH($empresa_id, 'geocerca', '🚨 Marcación fuera de rango', $nombreEmp . ' marcó fuera del radio (' . $hora . ').', 2);
            if ($ok_face === false) $this->alertarRRHH($empresa_id, 'rostro', '⚠️ Rostro no coincide', 'Posible suplantación de ' . $nombreEmp . ' (' . $hora . ').', 3);
            if ($es_vacacion) $this->alertarRRHH($empresa_id, 'vacacion', '🌴 Marcación en VACACIONES', $nombreEmp . ' marcó estando en vacaciones (' . $hora . ').', 2);
            elseif ($es_libre) $this->alertarRRHH($empresa_id, 'dia_libre', '😴 Marcación en día LIBRE', $nombreEmp . ' marcó en día de descanso (' . $hora . '). Candidato a horas extra.', 1);
            
            header('Location: /horion-time/public/asistencia/marcar?success=1'); exit;
        } catch (\Exception $e) {
            header('Location: /horion-time/public/asistencia/marcar?error=' . urlencode($e->getMessage())); exit;
        }
    }
    
    public function corregir($id = null) {
        $id = (int)($id ?? 0);
        $estado = $_GET['estado'] ?? '';
        $motivo = trim((string)($_GET['motivo'] ?? ''));
        if (in_array($estado, ['validado', 'pendiente', 'rechazado'], true)) {
            $actual = $this->model->getById($id);
            $obs = (string)($actual['observaciones'] ?? '');
            if ($motivo !== '') {
                $obs = trim($obs . ' [CORRECCIÓN ' . date('d/m H:i') . ' por ' . ($_SESSION['nombre_completo'] ?? 'admin') . ': ' . $motivo . ']');
            }
            $this->model->actualizarEstado($id, $estado, $obs !== '' ? $obs : null);
        }
        $volver = $_SERVER['HTTP_REFERER'] ?? '/horion-time/public/asistencia';
        header('Location: ' . $volver); exit;
    }
    
    // =====================================================
    // CONTROL DEL DÍA (detalle coordinadores/jefes de área)
    // =====================================================
    
    private function rolesControlDia(): bool {
        return in_array($_SESSION['rol_nombre'] ?? '', ['SuperAdmin', 'Admin_Empresa', 'RRHH', 'Supervisor'], true);
    }
    
    private function paramsControlDia(): array {
        $fecha = $_GET['fecha'] ?? date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) $fecha = date('Y-m-d');
        return [$fecha, $_GET['estado'] ?? '', $_GET['turno'] ?? ''];
    }
    
    private function detalleConServicio($empresa_id, $fecha, $fEstado, $fTurno) {
        require_once __DIR__ . '/../Models/DashboardModel.php';
        $dash = new \App\Models\DashboardModel($this->db);
        $detalle = $dash->getEstadoDiaDetallado($empresa_id, $fecha, $fEstado, $fTurno);
        try {
            $st = $this->db->prepare("SELECT usuario_id, MAX(servicio) AS servicio FROM turnos_calendario WHERE fecha = ? AND empresa_id = ? GROUP BY usuario_id");
            $st->execute([$fecha, (int)$empresa_id]);
            $serv = [];
            foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $r) $serv[(int)$r['usuario_id']] = $r['servicio'];
            foreach ($detalle as &$row) $row['servicio'] = $serv[(int)$row['usuario_id']] ?? '';
            unset($row);
        } catch (\Throwable $e) {}
        return [$dash, $detalle];
    }
    
    public function controlDia() {
        if (!$this->rolesControlDia()) { header('Location: /horion-time/public/dashboard'); exit; }
        [$fecha, $fEstado, $fTurno] = $this->paramsControlDia();
        $empresa_id = (int)($_SESSION['empresa_id'] ?? 1);
        [$dash, $detalle] = $this->detalleConServicio($empresa_id, $fecha, $fEstado, $fTurno);
        $resumen = $dash->getResumenDiaPanel($empresa_id, $fecha);
        $conteos = $dash->getConteosEstadoDia($empresa_id, $fecha);
        $GLOBALS['pageTitle'] = 'Control del Día';
        include __DIR__ . '/../Views/asistencia/control_dia.php';
    }
    
    public function exportarControlDia() {
        if (!$this->rolesControlDia()) { header('Location: /horion-time/public/dashboard'); exit; }
        [$fecha, $fEstado, $fTurno] = $this->paramsControlDia();
        $empresa_id = (int)($_SESSION['empresa_id'] ?? 1);
        [, $detalle] = $this->detalleConServicio($empresa_id, $fecha, $fEstado, $fTurno);
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="control_dia_' . $fecha . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Cédula','Empleado','Servicio','Sede','Turno','Horario','Hora entrada','Hora salida','Estado','Diferencia (min)']);
        foreach ($detalle as $f) {
            $h = '';
            if (!empty($f['hora_entrada'])) $h = substr($f['hora_entrada'],0,5) . '-' . substr($f['hora_salida'] ?? '',0,5);
            fputcsv($out, [
                $f['identificacion'] ?? '', $f['nombre_completo'] ?? '', $f['servicio'] ?? '', $f['sede_nombre'] ?? '',
                $f['turno_codigo'] ?? '', $h, $f['entrada_real'] ?? '', $f['salida_real'] ?? '',
                $f['estado']['label'] ?? '', $f['estado']['diff'] !== null ? $f['estado']['diff'] : '',
            ]);
        }
        fclose($out);
        exit;
    }
    
    // =====================================================
    // ALERTAS AUTOMÁTICAS
    // =====================================================
    
    private function turnoDeHoy($uid) {
        try {
            $st = $this->db->prepare("SELECT tc.codigo, pt.es_descanso, pt.es_vacacion, pt.nombre AS turno_nombre
                                      FROM turnos_calendario tc LEFT JOIN parametros_turnos pt ON pt.codigo = tc.codigo
                                      WHERE tc.usuario_id = ? AND tc.fecha = ? LIMIT 1");
            $st->execute([(int)$uid, date('Y-m-d')]);
            return $st->fetch(\PDO::FETCH_ASSOC) ?: null;
        } catch (\Throwable $e) { return null; }
    }
    
    private function alertarRRHH($empresa_id, $tipo, $titulo, $mensaje, $prioridad = 2) {
        try {
            $cols = $this->db->query("SHOW COLUMNS FROM alertas")->fetchAll(\PDO::FETCH_COLUMN);
            $row = [
                'empresa_id' => (int)$empresa_id, 'usuario_id' => null,
                'tipo' => $tipo, 'titulo' => $titulo, 'mensaje' => $mensaje,
                'prioridad' => (int)$prioridad, 'leido' => 0,
            ];
            $map = [
                'tipo' => ['tipo','categoria','category'],
                'titulo' => ['titulo','title','asunto'],
                'mensaje' => ['mensaje','message','descripcion'],
                'prioridad' => ['prioridad','priority','nivel'],
                'leido' => ['leido','leida','read'],
                'fecha_creacion' => ['fecha_creacion','created_at','fecha','creado_en'],
            ];
            $final = [];
            foreach ($row as $k => $v) {
                if (in_array($k, $cols, true)) { $final[$k] = $v; continue; }
                foreach (($map[$k] ?? []) as $alt) { if (in_array($alt, $cols, true)) { $final[$alt] = $v; break; } }
            }
            if (!isset($final['fecha_creacion']) && in_array('fecha_creacion', $cols, true)) $final['fecha_creacion'] = date('Y-m-d H:i:s');
            if (!isset($final['created_at']) && in_array('created_at', $cols, true)) $final['created_at'] = date('Y-m-d H:i:s');
            $campos = implode(', ', array_map(fn($k) => "`$k`", array_keys($final)));
            $marks  = implode(', ', array_map(fn($k) => ":$k", array_keys($final)));
            $st = $this->db->prepare("INSERT INTO alertas ($campos) VALUES ($marks)");
            foreach ($final as $k => $v) $st->bindValue(":$k", $v);
            $st->execute();
        } catch (\Throwable $e) { /* silencioso */ }
    }
    
    // =====================================================
    // CUMPLIMIENTO (FASE B)
    // =====================================================
    
    private function rolesReporte(): bool {
        return in_array($_SESSION['rol_nombre'] ?? '', ['SuperAdmin', 'Admin_Empresa', 'RRHH', 'Auditor', 'Contador'], true);
    }
    private function paramsCumplimiento(): array {
        $mes  = (int)($_GET['mes'] ?? date('n'));
        $anio = (int)($_GET['anio'] ?? date('Y'));
        $modoGlobal = (bool)($_SESSION['modo_global'] ?? false);
        $empresa = $modoGlobal ? (($_GET['empresa'] ?? '') !== '' ? (int)$_GET['empresa'] : null) : (int)($_SESSION['empresa_id'] ?? 1);
        return [$mes, $anio, $empresa];
    }
    public function cumplimiento() {
        if (!$this->rolesReporte()) { header('Location: /horion-time/public/dashboard'); exit; }
        require_once __DIR__ . '/../Models/CumplimientoModel.php';
        $m = new \App\Models\CumplimientoModel($this->db);
        [$mes, $anio, $empresa] = $this->paramsCumplimiento();
        $res = $m->getResumen($empresa, $mes, $anio);
        $empresas = $_SESSION['empresas_lista'] ?? [];
        $GLOBALS['pageTitle'] = 'Cumplimiento de Turnos';
        $GLOBALS['currentPage'] = 'cumplimiento';
        include __DIR__ . '/../Views/asistencia/cumplimiento.php';
    }
    public function exportarCumplimiento() {
        if (!$this->rolesReporte()) { header('Location: /horion-time/public/dashboard'); exit; }
        require_once __DIR__ . '/../Models/CumplimientoModel.php';
        $m = new \App\Models\CumplimientoModel($this->db);
        [$mes, $anio, $empresa] = $this->paramsCumplimiento();
        $res = $m->getResumen($empresa, $mes, $anio);
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="cumplimiento_' . $anio . '-' . str_pad((string)$mes, 2, '0', STR_PAD_LEFT) . '.csv"');
        $out = fopen('php://output', 'w'); fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Empleado','Cédula','Servicio','Días programados','Días asistidos','Tardanzas','Salidas tempranas','Ausencias','Días extra (L)','Horas extra','Alertas vacaciones','Horas programadas','Horas trabajadas','% Cumplimiento','Marcas sin turno']);
        foreach ($res['empleados'] as $r) {
            fputcsv($out, [
                $r['nombre'], $r['identificacion'], $r['servicio'],
                $r['dias_prog'], $r['dias_asistidos'], $r['tardanzas'], $r['salidas_tempranas'],
                $r['ausencias'], $r['dias_extra'], $r['horas_extra'], $r['alertas_vacacion'],
                $r['horas_prog'], $r['horas_trab'], $r['pct'] ?? 'N/A', $r['marcas_sin_turno'],
            ]);
        }
        fclose($out); exit;
    }
    
    // =====================================================
    // ROSTRO
    // =====================================================
    public function obtenerDescriptor() {
        header('Content-Type: application/json');
        try {
            $usuario_id = (int)($_SESSION['usuario_id'] ?? 0);
            if (!$usuario_id) { echo json_encode(['descriptor' => null]); exit; }
            $st = $this->db->prepare("SELECT face_descriptor FROM usuarios WHERE id = ? LIMIT 1");
            $st->execute([$usuario_id]);
            echo json_encode(['descriptor' => $st->fetchColumn() ?: null]);
        } catch (\Throwable $e) { echo json_encode(['descriptor' => null, 'error' => $e->getMessage()]); }
        exit;
    }
    private function guardarDescriptor($usuario_id, $descriptor_json) {
        try { $st = $this->db->prepare("UPDATE usuarios SET face_descriptor = ? WHERE id = ?"); $st->execute([$descriptor_json, (int)$usuario_id]); return true; }
        catch (\Throwable $e) { return false; }
    }
    private function distanciaRostro(array $a, array $b): float {
        $sum = 0.0;
        for ($i = 0; $i < 128; $i++) { $d = ((float)($a[$i] ?? 0)) - ((float)($b[$i] ?? 0)); $sum += $d * $d; }
        return sqrt($sum);
    }
    
    // =====================================================
    // GPS
    // =====================================================
    private function calcularDistancia($sede_id, $lat_empleado, $lng_empleado) {
        $sede = $this->obtenerSede($sede_id);
        if (!$sede || !$sede['lat_ref'] || !$sede['lng_ref']) return null;
        $lat_ref = (float)$sede['lat_ref']; $lng_ref = (float)$sede['lng_ref'];
        $R = 6371000; $dLat = deg2rad($lat_ref - $lat_empleado); $dLng = deg2rad($lng_ref - $lng_empleado);
        $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat_empleado)) * cos(deg2rad($lat_ref)) * sin($dLng/2) * sin($dLng/2);
        return $R * 2 * atan2(sqrt($a), sqrt(1-$a));
    }
    private function obtenerSede($sede_id) {
        try {
            $st = $this->db->prepare("SELECT id, nombre, lat_ref, lng_ref, radio_m FROM sedes WHERE id = ? LIMIT 1");
            $st->execute([(int)$sede_id]); return $st->fetch(\PDO::FETCH_ASSOC) ?: null;
        } catch (\Throwable $e) { return null; }
    }
}
