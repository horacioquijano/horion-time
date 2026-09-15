<?php
namespace App\Controllers;
use PDO;
use DateTime;
use DateTimeZone;
class HoraExtraController {
private $db;
private const TZ = 'America/Bogota';
public function __construct($db) {
$this->db = $db;
}
private function esSuper()       { return (($_SESSION['rol_nombre'] ?? '') === 'SuperAdmin'); }
private function modoGlobal()    { return (bool)($_SESSION['modo_global'] ?? false); }
private function empresaActual() { return (int)($_SESSION['empresa_id'] ?? 0); }
/** ===== Festivos de Colombia por año (Computus + Ley Emiliani) ===== */
public static function festivosColombia(int $anio): array {
$a = $anio % 19; $b = intdiv($anio, 100); $c = $anio % 100;
$d = intdiv($b, 4); $e = $b % 4; $f = intdiv($b + 8, 25); $g = intdiv($b - $f + 1, 3);
$h = (19 * $a + $b - $d - $g + 15) % 30; $i = intdiv($c, 4); $k = $c % 4;
$l = (32 + 2 * $e + 2 * $i - $h - $k) % 7; $m = intdiv($a + 11 * $h + 22 * $l, 451);
$mes = intdiv($h + $l - 7 * $m + 114, 31);
$dia = (($h + $l - 7 * $m + 114) % 31) + 1;
$pascua = mktime(0, 0, 0, $mes, $dia, $anio);
$sumar = fn($dias) => $pascua + $dias * 86400;
$lunesSig = function ($ts) {
$t = strtotime('monday this week', $ts);
return ($t < $ts) ? $t + 7 * 86400 : $t;
};
$fest = [
"$anio-01-01", "$anio-05-01", "$anio-07-20", "$anio-08-07", "$anio-12-08", "$anio-12-25",
date('Y-m-d', $sumar(-3)),
date('Y-m-d', $sumar(-2)),
];
$emiliani = [
"$anio-01-06", "$anio-03-19",
date('Y-m-d', $sumar(39)),
date('Y-m-d', $sumar(60)),
date('Y-m-d', $sumar(68)),
"$anio-10-12", "$anio-11-01", "$anio-11-11",
];
foreach ($emiliani as $f) $fest[] = date('Y-m-d', $lunesSig(strtotime($f . ' 12:00:00')));
$fest = array_values(array_unique($fest));
sort($fest);
return $fest;
}
private static function aMinutos(string $hhmm): int {
$p = array_map('intval', explode(':', $hhmm));
return ($p[0] ?? 0) * 60 + ($p[1] ?? 0);
}
/** ===== Clasificador según ley vigente (Ley 2101 de 2021) ===== */
public static function clasificarRango(string $fecha, string $hi, string $hf): array {
$tz  = new DateTimeZone(self::TZ);
$ts  = new DateTime($fecha . ' 00:00:00', $tz);
$dow = (int)$ts->format('N');
$festivos   = self::festivosColombia((int)$ts->format('Y'));
$esFestivo  = in_array($fecha, $festivos, true);
$esDominical = ($dow === 7) || $esFestivo;
$a = self::aMinutos($hi);
$b = self::aMinutos($hf);
if ($b <= $a) $b += 1440;
$diurnos = 0; $nocturnos = 0;
for ($m = $a; $m < $b; $m++) {
$mm = $m % 1440;
if ($mm >= 360 && $mm < 1140) $diurnos++; else $nocturnos++;
}
$horas = round(($b - $a) / 60, 2);
$mayorDiurna = $diurnos >= $nocturnos;
// ===== CAMBIO 1: códigos compatibles con el ENUM de la tabla =====
if ($dow === 7 && $esFestivo)     { $codigo = 'dominical_festivo'; $recargo = $mayorDiurna ? 75 : 110; $nombre = 'Domingo Festivo'; }
elseif ($dow === 7)               { $codigo = 'dominical';         $recargo = $mayorDiurna ? 75 : 110; $nombre = 'Dominical'; }
elseif ($esFestivo)               { $codigo = 'festivo';           $recargo = $mayorDiurna ? 75 : 110; $nombre = 'Festivo'; }
else                              { $codigo = $mayorDiurna ? 'diurna' : 'nocturna'; $recargo = $mayorDiurna ? 25 : 75; $nombre = $mayorDiurna ? 'Hora Extra Diurna' : 'Hora Extra Nocturna'; }
// ===== FIN CAMBIO 1 =====
$dias = [1 => 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
return [
'fecha'             => $fecha,
'dia_semana'        => $dias[$dow],
'es_festivo'        => $esFestivo,
'horas'             => $horas,
'minutos_diurnos'   => $diurnos,
'minutos_nocturnos' => $nocturnos,
'tipo'              => $codigo,
'recargo'           => $recargo,
'label'             => $nombre . ' (+' . $recargo . '%)',
];
}
/** Endpoint JSON para el cálculo en vivo del formulario */
public function clasificar() {
header('Content-Type: application/json');
$fecha = $_GET['fecha'] ?? date('Y-m-d');
$hi = $_GET['hora_inicio'] ?? '';
$hf = $_GET['hora_fin'] ?? '';
if (!$hi || !$hf) { echo json_encode(['ok' => false]); exit; }
echo json_encode(['ok' => true] + self::clasificarRango($fecha, $hi, $hf));
exit;
}
/** Listado */
public function index() {
$es_super    = $this->esSuper();
$modo_global = $this->modoGlobal();
$mi_empresa  = $this->empresaActual();
// ===== CAMBIO 2: filtros del formulario =====
$f_estado = $_GET['estado'] ?? '';
$f_tipo   = $_GET['tipo'] ?? '';
$f_fi     = $_GET['fecha_inicio'] ?? '';
$f_ff     = $_GET['fecha_fin'] ?? '';
// ===== FIN CAMBIO 2 =====
try {
$sql = "SELECT h.*, u.nombre_completo, u.identificacion, e.nombre AS empresa_nombre
FROM horas_extras h
LEFT JOIN usuarios u ON h.usuario_id = u.id
LEFT JOIN empresas e ON h.empresa_id = e.id
WHERE 1=1";
$params = [];
if (!($es_super && $modo_global)) { $sql .= " AND h.empresa_id = :eid"; $params[':eid'] = $mi_empresa ?: 1; }
// ===== CAMBIO 2 (cont): condiciones de filtro =====
if ($f_estado !== '') { $sql .= " AND h.estado = :est"; $params[':est'] = $f_estado; }
if ($f_tipo !== '')   { $sql .= " AND h.tipo = :tip";   $params[':tip'] = $f_tipo; }
if ($f_fi !== '')     { $sql .= " AND h.fecha >= :fi";  $params[':fi'] = $f_fi; }
if ($f_ff !== '')     { $sql .= " AND h.fecha <= :ff";  $params[':ff'] = $f_ff; }
// ===== FIN CAMBIO 2 =====
$sql .= " ORDER BY h.fecha DESC, h.id DESC LIMIT 200";
$st = $this->db->prepare($sql);
$st->execute($params);
$horas_extras = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {
$horas_extras = [];
}
$labels = [
'diurna' => 'Hora Extra Diurna', 'nocturna' => 'Hora Extra Nocturna',
'dominical' => 'Dominical', 'festivo' => 'Festivo', 'dominical_festivo' => 'Domingo Festivo',
];
foreach ($horas_extras as &$h) {
$h['empleado_nombre']    = $h['empleado_nombre'] ?? $h['nombre_completo'] ?? 'Sin nombre';
$h['empleado_id']        = $h['empleado_id'] ?? $h['usuario_id'] ?? $h['solicitante_id'] ?? null;
$h['estado']             = $h['estado'] ?? 'pendiente';
$h['horas']              = $h['horas'] ?? $h['horas_solicitadas'] ?? $h['cantidad_horas'] ?? 0;
$h['horas_solicitadas']  = $h['horas_solicitadas'] ?? $h['horas'];
$h['horas_aprobadas']    = $h['horas_aprobadas'] ?? null;
$h['valor_total']        = $h['valor_total'] ?? null;
$h['hora_inicio']        = $h['hora_inicio'] ?? $h['hora_inicio_programada'] ?? '—';
$h['hora_fin']           = $h['hora_fin'] ?? $h['hora_fin_programada'] ?? '—';
$h['porcentaje_recargo'] = $h['porcentaje_recargo'] ?? $h['porcentaje_aplicado'] ?? $h['recargo'] ?? 0;
$h['recargo']            = $h['recargo'] ?? $h['porcentaje_recargo'];
$h['tipo_recargo']       = $h['tipo_recargo'] ?? ($labels[$h['tipo'] ?? ''] ?? ucfirst(str_replace('_', ' ', $h['tipo'] ?? '')));
$h['justificacion']      = $h['justificacion'] ?? $h['motivo'] ?? '';
}
unset($h);
$counts = ['pendiente' => 0, 'aprobada' => 0, 'rechazada' => 0, 'pagada' => 0, 'autorizada' => 0];
$horasTotAprobadas = 0.0; $valorTotAprobado = 0.0;
foreach ($horas_extras as $h) {
$e = $h['estado'] ?? 'pendiente';
if (isset($counts[$e])) $counts[$e]++;
if ($e === 'aprobada' || $e === 'pagada') {
$horasTotAprobadas += (float)($h['horas_aprobadas'] ?? 0);
$valorTotAprobado  += (float)($h['valor_total'] ?? 0);
}
}
$resumen = [
'total' => count($horas_extras),
'pendientes' => $counts['pendiente'], 'aprobadas' => $counts['aprobada'], 'rechazadas' => $counts['rechazada'],
'pendiente' => $counts['pendiente'], 'aprobada' => $counts['aprobada'], 'rechazada' => $counts['rechazada'],
// ===== CAMBIO 3: claves que usan las cards de la vista =====
'horas_totales_aprobadas' => $horasTotAprobadas,
'valor_total_aprobado'    => $valorTotAprobado,
// ===== FIN CAMBIO 3 =====
];
$configRecargos = [
['tipo_recargo' => 'Hora Extra Diurna',            'hora_inicio' => '06:00', 'hora_fin' => '19:00', 'porcentaje_recargo' => 25,  'descripcion' => 'Lun–sáb 06:00–19:00'],
['tipo_recargo' => 'Hora Extra Nocturna',          'hora_inicio' => '19:00', 'hora_fin' => '06:00', 'porcentaje_recargo' => 75,  'descripcion' => 'Lun–sáb 19:00–06:00'],
['tipo_recargo' => 'Dominical / Festiva Diurna',   'hora_inicio' => '06:00', 'hora_fin' => '19:00', 'porcentaje_recargo' => 75,  'descripcion' => 'Domingos y festivos 06:00–19:00'],
['tipo_recargo' => 'Dominical / Festiva Nocturna', 'hora_inicio' => '19:00', 'hora_fin' => '06:00', 'porcentaje_recargo' => 110, 'descripcion' => 'Domingos y festivos 19:00–06:00'],
];
$solicitudes = $horas_extras;
// ===== CAMBIO 4: alias que recorre la tabla de la vista =====
$horasExtras = $horas_extras;
// ===== FIN CAMBIO 4 =====
$csrf_token = $csrf_token ?? bin2hex(random_bytes(32));
require_once __DIR__ . '/../Views/horas_extras/index.php';
}
/** GET formulario | POST guarda */
public function solicitar() {
if ($_SERVER['REQUEST_METHOD'] === 'POST') { $this->guardar(); return; }
$hoy = (new DateTime('now', new DateTimeZone(self::TZ)))->format('Y-m-d');
$csrf_token = bin2hex(random_bytes(32));
require_once __DIR__ . '/../Views/horas_extras/solicitar.php';
}
public function store() {
if ($_SERVER['REQUEST_METHOD'] === 'POST') { $this->guardar(); return; }
header('Location: /horion-time/public/horas_extras/solicitar'); exit;
}
private function guardar() {
try {
$fecha = $_POST['fecha'] ?? '';
$hi = $_POST['hora_inicio_programada'] ?? $_POST['hora_inicio'] ?? '';
$hf = $_POST['hora_fin_programada'] ?? $_POST['hora_fin'] ?? '';
$just = trim($_POST['justificacion'] ?? $_POST['motivo'] ?? '');
if (!$fecha || !$hi || !$hf || $just === '') throw new \Exception('Completa fecha, horas y justificación');
$c = self::clasificarRango($fecha, $hi, $hf);
$cols = $this->db->query("SHOW COLUMNS FROM horas_extras")->fetchAll(PDO::FETCH_COLUMN);
$pick = function (array $cands) use ($cols) {
foreach ($cands as $x) if (in_array($x, $cols)) return $x;
return null;
};
$data = [
'empresa_id' => (int)($_POST['empresa_id'] ?? $_SESSION['empresa_id'] ?? 1),
'usuario_id' => (int)($_POST['usuario_id'] ?? $_SESSION['usuario_id'] ?? 1),
'fecha'      => $fecha,
];
foreach (['solicitante_id', 'usuario_solicitante_id', 'empleado_id', 'solicitado_por'] as $fk) {
if (in_array($fk, $cols) && !isset($data[$fk])) {
$data[$fk] = (int)($_POST['usuario_id'] ?? $_SESSION['usuario_id'] ?? 1);
}
}
// ===== CAMBIO 5: nombres reales de columnas de la tabla =====
if ($x = $pick(['hora_inicio_programada','hora_inicio']))            $data[$x] = $hi;
if ($x = $pick(['hora_fin_programada','hora_fin']))                  $data[$x] = $hf;
if ($x = $pick(['horas_solicitadas','horas','cantidad_horas']))      $data[$x] = $c['horas'];
if ($x = $pick(['tipo','tipo_hora_extra','clase']))                  $data[$x] = $c['tipo'];
if ($x = $pick(['porcentaje_aplicado','recargo','recargo_pct','porcentaje_recargo'])) $data[$x] = $c['recargo'];
if ($x = $pick(['justificacion','motivo','observaciones']))          $data[$x] = $just;
if ($x = $pick(['estado']))                                          $data[$x] = 'pendiente';
// ===== FIN CAMBIO 5 =====
$data = array_intersect_key($data, array_flip($cols));
$campos = implode(', ', array_map(fn($k) => "`$k`", array_keys($data)));
$marks  = implode(', ', array_map(fn($k) => ":$k", array_keys($data)));
$st = $this->db->prepare("INSERT INTO horas_extras ($campos) VALUES ($marks)");
foreach ($data as $k => $v) $st->bindValue(":$k", $v);
$st->execute();
header('Location: /horion-time/public/horas_extras?success=1'); exit;
} catch (\Exception $e) {
header('Location: /horion-time/public/horas_extras/solicitar?error=' . urlencode($e->getMessage())); exit;
}
}
// ===== CAMBIO 6: métodos que usan los modales de la vista =====
public function procesar($id = null) {
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /horion-time/public/horas_extras'); exit; }
$id = (int)($id ?? 0);
$estado = ($_POST['estado'] ?? '') === 'aprobada' ? 'aprobada' : 'rechazada';
if (!in_array($_SESSION['rol_nombre'] ?? '', ['SuperAdmin','Admin_Empresa','Supervisor','RRHH'], true)) {
header('Location: /horion-time/public/horas_extras?error=' . urlencode('Sin permiso')); exit;
}
try {
$cols = $this->db->query("SHOW COLUMNS FROM horas_extras")->fetchAll(PDO::FETCH_COLUMN);
$set = ["estado = :estado"]; $params = [':estado' => $estado, ':id' => $id];
if (in_array('horas_aprobadas', $cols))  { $set[] = "horas_aprobadas = :ha"; $params[':ha'] = $estado === 'aprobada' ? (float)($_POST['horas_aprobadas'] ?? 0) : 0; }
if (in_array('observaciones', $cols))    { $set[] = "observaciones = :obs";  $params[':obs'] = $_POST['observaciones'] ?? ''; }
if (in_array('aprobador_id', $cols))     { $set[] = "aprobador_id = :ap";    $params[':ap'] = (int)($_SESSION['usuario_id'] ?? 1); }
if (in_array('fecha_aprobacion', $cols)) { $set[] = "fecha_aprobacion = NOW()"; }
$st = $this->db->prepare("UPDATE horas_extras SET " . implode(', ', $set) . " WHERE id = :id");
$st->execute($params);
header('Location: /horion-time/public/horas_extras?processed=1'); exit;
} catch (\Exception $e) {
header('Location: /horion-time/public/horas_extras?error=' . urlencode($e->getMessage())); exit;
}
}
public function pagar($id = null) {
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /horion-time/public/horas_extras'); exit; }
$id = (int)($id ?? 0);
if (!in_array($_SESSION['rol_nombre'] ?? '', ['SuperAdmin','Admin_Empresa'], true)) {
header('Location: /horion-time/public/horas_extras?error=' . urlencode('Sin permiso')); exit;
}
try {
$cols = $this->db->query("SHOW COLUMNS FROM horas_extras")->fetchAll(PDO::FETCH_COLUMN);
$set = ["estado = 'pagada'"]; $params = [':id' => $id];
if (in_array('fecha_pago', $cols))    { $set[] = "fecha_pago = CURDATE()"; }
if (in_array('numero_nomina', $cols)) { $set[] = "numero_nomina = :nom"; $params[':nom'] = $_POST['numero_nomina'] ?? ''; }
$st = $this->db->prepare("UPDATE horas_extras SET " . implode(', ', $set) . " WHERE id = :id");
$st->execute($params);
header('Location: /horion-time/public/horas_extras?paid=1'); exit;
} catch (\Exception $e) {
header('Location: /horion-time/public/horas_extras?error=' . urlencode($e->getMessage())); exit;
}
}
// ===== FIN CAMBIO 6 =====
public function aprobar($id = null)  { $this->cambiarEstado((int)($id ?? 0), 'aprobada'); }
public function rechazar($id = null) { $this->cambiarEstado((int)($id ?? 0), 'rechazada'); }
private function cambiarEstado($id, $estado) {
if (!in_array($_SESSION['rol_nombre'] ?? '', ['SuperAdmin', 'Admin_Empresa', 'RRHH'], true)) {
header('Location: /horion-time/public/horas_extras?error=' . urlencode('Sin permiso')); exit;
}
try {
$st = $this->db->prepare("UPDATE horas_extras SET estado = :e WHERE id = :id");
$st->execute([':e' => $estado, ':id' => $id]);
header('Location: /horion-time/public/horas_extras?success=1'); exit;
} catch (\Exception $e) {
header('Location: /horion-time/public/horas_extras?error=' . urlencode($e->getMessage())); exit;
}
}
}