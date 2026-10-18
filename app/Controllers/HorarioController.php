<?php
namespace App\Controllers;
use App\Models\HorarioModel;

class HorarioController {
    private $model;
    private $db;
    private $tablasCache = null;
    
    public function __construct($db) {
        $this->db = $db;
        $this->model = new HorarioModel($db);
    }
    
    private function empresaActual() {
        return (int)($_SESSION['empresa_id'] ?? 1);
    }

    private function requiereGestion() {
        $rol = $_SESSION['rol_nombre'] ?? '';
        return in_array($rol, ['SuperAdmin', 'Admin_Empresa', 'RRHH'], true);
    }

    private function tablas(): array {
        if ($this->tablasCache === null) {
            try { $this->tablasCache = $this->db->query("SHOW TABLES")->fetchAll(\PDO::FETCH_COLUMN); }
            catch (\Throwable $e) { $this->tablasCache = []; }
        }
        return $this->tablasCache;
    }
    
    public function index() {
        $empresa_id = $this->empresaActual();
        $horarios = $this->model->getHorarios($empresa_id);
        $empleados = $this->model->getEmpleadosConHorario($empresa_id);
        
        $panelPorUsuario = [];
        try {
            $st = $this->db->prepare("SELECT usuario_id, COUNT(*) AS dias
                                      FROM turnos_calendario
                                      WHERE empresa_id = ? AND YEAR(fecha) = ? AND MONTH(fecha) = ?
                                      GROUP BY usuario_id");
            $st->execute([$empresa_id, (int)date('Y'), (int)date('n')]);
            foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $panelPorUsuario[(int)$r['usuario_id']] = (int)$r['dias'];
            }
        } catch (\Throwable $e) { $panelPorUsuario = []; }
        
        $GLOBALS['pageTitle'] = 'Gestión de Horarios';
        include __DIR__ . '/../Views/horarios/index.php';
    }
    
    public function crear() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /horion-time/public/horarios'); exit; }
        try {
            $horario_id = $this->model->crearHorario([
                'empresa_id' => $this->empresaActual(),
                'nombre' => $_POST['nombre'],
                'descripcion' => $_POST['descripcion'] ?? '',
                'horas_semanales' => $_POST['horas_semanales'] ?? 48
            ]);
            $dias = ['domingo', 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado'];
            $turnos = [];
            foreach ($dias as $i => $dia) {
                if (!empty($_POST[$dia . '_entrada']) && !empty($_POST[$dia . '_salida'])) {
                    $turnos[$i] = [
                        'hora_entrada' => $_POST[$dia . '_entrada'],
                        'hora_salida' => $_POST[$dia . '_salida'],
                        'es_descanso' => isset($_POST[$dia . '_descanso']) ? 1 : 0,
                        'tolerancia_entrada_min' => $_POST[$dia . '_tolerancia_entrada'] ?? 10,
                        'tolerancia_salida_min' => $_POST[$dia . '_tolerancia_salida'] ?? 10
                    ];
                }
            }
            $this->model->crearTurnos($horario_id, $turnos);
            header('Location: /horion-time/public/horarios?success=1'); exit;
        } catch (\Exception $e) {
            header('Location: /horion-time/public/horarios?error=' . urlencode($e->getMessage())); exit;
        }
    }
    
    public function asignar() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /horion-time/public/horarios'); exit; }
        try {
            $this->model->asignarHorario(
                (int)$_POST['usuario_id'],
                (int)$_POST['horario_id'],
                $_POST['fecha_inicio'],
                $_POST['fecha_fin'] ?: null
            );
            header('Location: /horion-time/public/horarios?success=1'); exit;
        } catch (\Exception $e) {
            header('Location: /horion-time/public/horarios?error=' . urlencode($e->getMessage())); exit;
        }
    }
    
    public function detalle($id) {
        $turnos = $this->model->getTurnosHorario($id);
        $dias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        echo json_encode(['turnos' => $turnos, 'dias' => $dias]);
        exit;
    }

    // ==========================================================
    //  SINCRONIZACIÓN v5: relleno automático de TODO NOT NULL sin default
    // ==========================================================

    public function sincronizarHorarios() {
        if (!$this->requiereGestion()) { header('Location: /horion-time/public/horarios'); exit; }
        $mes  = (int)($_GET['mes']  ?? date('n'));
        $anio = (int)($_GET['anio'] ?? date('Y'));
        try {
            $res = $this->doSincronizar($this->empresaActual(), $mes, $anio);
            header('Location: /horion-time/public/horarios?success=1&msg=' . urlencode($this->mensajeSync($res)));
            exit;
        } catch (\Throwable $e) {
            header('Location: /horion-time/public/horarios?error=' . urlencode('Error sincronizando: ' . $e->getMessage())); exit;
        }
    }

    private function mensajeSync(array $res): string {
        $msg = 'Sincronización: ' . $res['procesados'] . ' procesados, ' . $res['asignados'] . ' asignados, ' .
               $res['fijos_creados'] . ' horarios fijos creados, ' . $res['rotativos'] . ' rotativos, ' .
               $res['turnos_insertados'] . ' turnos semanales insertados.';
        if ($res['verif']) $msg .= ' Verificación: ' . implode(' · ', $res['verif']);
        if ($res['errores']) $msg .= ' ⚠️ Errores (' . count($res['errores']) . '): ' . implode(' | ', array_slice($res['errores'], 0, 3));
        return $msg;
    }

    /** Valor seguro según tipo de columna (para NOT NULL sin default) */
    private function valorSeguro(string $type): string|int {
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

    /**
     * Construye la fila completa para `turnos`: alias conocidos + relleno
     * automático de cualquier columna NOT NULL sin default que exista en TU esquema.
     */
    private function filaTurnoCompleta(array $colsInfo, array $base): array {
        $fila = [];
        foreach ($colsInfo as $ci) {
            $field = $ci['Field'];
            $type  = strtolower((string)$ci['Type']);
            $null  = strtoupper((string)$ci['Null']) === 'YES';
            $extra = strtolower((string)$ci['Extra']);
            if (strpos($extra, 'auto_increment') !== false) continue;

            if (array_key_exists($field, $base)) {
                $val = $base[$field];
                // Si es enum y el valor base no está permitido, usa el primero
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

    private function refrescarTurnosAuto($horario_id, array $modoPorDia, array $metaPorDia, int $empresa_id, array &$errores): int {
        $insertados = 0;
        try {
            if (!in_array('turnos', $this->tablas(), true)) { $errores[] = 'Tabla turnos no existe'; return 0; }
            $colsInfo = $this->db->query("SHOW COLUMNS FROM turnos")->fetchAll(\PDO::FETCH_ASSOC);
            $this->db->prepare("DELETE FROM turnos WHERE horario_id = ?")->execute([(int)$horario_id]);
            for ($w = 0; $w <= 6; $w++) {
                $cod = $modoPorDia[$w] ?? null;
                if ($cod === null) continue;
                $meta = $metaPorDia[$w] ?? null;
                $esDesc = $meta ? (int)($meta['es_descanso'] ?? 0) : 0;
                $ent = $esDesc ? '00:00:00' : (string)($meta['hora_entrada'] ?? '00:00:00');
                $sal = $esDesc ? '00:00:00' : (string)($meta['hora_salida'] ?? '00:00:00');
                $nombreTurno = $esDesc ? 'Descanso' : trim((string)($meta['turno_nombre'] ?? $cod));

                $base = [
                    'horario_id'   => (int)$horario_id,
                    'dia_semana'   => $w, 'dia' => $w, 'dia_id' => $w,
                    'nombre'       => $nombreTurno,
                    'hora_entrada' => $ent, 'hora_salida' => $sal,
                    'hora_inicio'  => $ent, 'hora_fin'    => $sal,
                    'entrada'      => $ent, 'salida'      => $sal,
                    'es_descanso'  => $esDesc, 'descanso' => $esDesc,
                    'tolerancia_entrada_min' => 10, 'tolerancia_salida_min' => 10,
                    'tolerancia_entrada'     => 10, 'tolerancia_salida'     => 10,
                    'empresa_id'   => $empresa_id,
                    'estado'       => 'activo',
                ];

                $fila = $this->filaTurnoCompleta($colsInfo, $base);
                try {
                    $campos = implode(', ', array_map(fn($k) => "`$k`", array_keys($fila)));
                    $marks  = implode(', ', array_map(fn($k) => ":$k", array_keys($fila)));
                    $st = $this->db->prepare("INSERT INTO turnos ($campos) VALUES ($marks)");
                    foreach ($fila as $k => $v) $st->bindValue(":$k", $v);
                    $st->execute();
                    $insertados++;
                } catch (\Throwable $e) {
                    $errores[] = 'turno día ' . $w . ' hid ' . $horario_id . ': ' . $e->getMessage();
                }
            }
        } catch (\Throwable $e) {
            $errores[] = 'refrescarTurnos hid ' . $horario_id . ': ' . $e->getMessage();
        }
        return $insertados;
    }

    private function asignarSemana($uid, $hid, $fi, $ff, array &$errores): bool {
        $ok = false;
        if (in_array('empleado_horario', $this->tablas(), true)) {
            try {
                $cols = $this->db->query("SHOW COLUMNS FROM empleado_horario")->fetchAll(\PDO::FETCH_COLUMN);
                $this->db->prepare("UPDATE empleado_horario SET estado='inactivo' WHERE usuario_id=? AND estado='activo'")->execute([(int)$uid]);
                $fila = ['usuario_id' => (int)$uid, 'horario_id' => (int)$hid, 'fecha_inicio' => $fi];
                $extra = ['fecha_fin' => $ff, 'estado' => 'activo', 'empresa_id' => $this->empresaActual(), 'creado_por' => (int)($_SESSION['usuario_id'] ?? 1)];
                foreach ($extra as $k => $v) if (in_array($k, $cols, true)) $fila[$k] = $v;
                $campos = implode(', ', array_map(fn($k) => "`$k`", array_keys($fila)));
                $marks  = implode(', ', array_map(fn($k) => ":$k", array_keys($fila)));
                $st = $this->db->prepare("INSERT INTO empleado_horario ($campos) VALUES ($marks)");
                foreach ($fila as $k => $v) $st->bindValue(":$k", $v);
                $st->execute();
                $ok = true;
            } catch (\Throwable $e) {
                $errores[] = 'EH uid ' . $uid . ': ' . $e->getMessage();
            }
        }
        try {
            $this->db->prepare("UPDATE usuarios SET horario_asignado_id = ? WHERE id = ?")->execute([(int)$hid, (int)$uid]);
            $ok = true;
        } catch (\Throwable $e) {
            $errores[] = 'legacy uid ' . $uid . ': ' . $e->getMessage();
        }
        return $ok;
    }

    private function doSincronizar($empresa_id, $mes, $anio) {
        $res = ['fijos_creados'=>0,'fijos_reusados'=>0,'rotativos'=>0,'asignados'=>0,'omitidos'=>0,
                'procesados'=>0,'turnos_insertados'=>0,'errores'=>[],'verif'=>[]];

        $st = $this->db->prepare("SELECT tc.usuario_id, tc.fecha, tc.codigo,
                                         pt.hora_entrada, pt.hora_salida, pt.horas_trabajadas,
                                         pt.es_descanso, pt.es_vacacion, pt.nombre AS turno_nombre
                                  FROM turnos_calendario tc
                                  LEFT JOIN parametros_turnos pt ON pt.codigo = tc.codigo
                                  WHERE tc.empresa_id = ? AND YEAR(tc.fecha) = ? AND MONTH(tc.fecha) = ?");
        $st->execute([(int)$empresa_id, (int)$anio, (int)$mes]);
        $porUsuario = [];
        foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $r) {
            $porUsuario[(int)$r['usuario_id']][] = $r;
        }
        if (!$porUsuario) return $res;

        $horarioActual = [];
        foreach ($this->model->getEmpleadosConHorario($empresa_id) as $e) {
            $horarioActual[(int)$e['id']] = $e['horario_nombre'] ?? null;
        }

        $primerDia = sprintf('%04d-%02d-01', $anio, $mes);
        $ultimoDia = date('Y-m-t', strtotime($primerDia));
        $rotativoId = null;
        $rotativoNombre = 'Rotativo — ver Panel de Turnos';
        $autosIds = [];

        foreach ($porUsuario as $uid => $filas) {
            $res['procesados']++;
            try {
                $laborales = array_filter($filas, fn($f) => (int)($f['es_vacacion'] ?? 0) === 0 && (int)($f['es_descanso'] ?? 0) === 0);
                if (count($laborales) === 0) { $res['omitidos']++; continue; }

                $porDia = [];
                foreach ($filas as $f) {
                    if ((int)($f['es_vacacion'] ?? 0) === 1) continue;
                    $w = (int)date('w', strtotime($f['fecha']));
                    $porDia[$w][] = $f;
                }

                $esFijo = true;
                $modoPorDia = [];
                $metaPorDia = [];
                for ($w = 0; $w <= 6; $w++) {
                    $lista = $porDia[$w] ?? [];
                    if (!$lista) { $modoPorDia[$w] = null; continue; }
                    $conteo = [];
                    foreach ($lista as $f) $conteo[$f['codigo']] = ($conteo[$f['codigo']] ?? 0) + 1;
                    arsort($conteo);
                    $modo = array_key_first($conteo);
                    if ($conteo[$modo] / count($lista) < 0.75) $esFijo = false;
                    $modoPorDia[$w] = $modo;
                    $metaPorDia[$w] = $lista[0];
                }

                if ($esFijo) {
                    $sig = implode(',', array_map(fn($w) => $modoPorDia[$w] ?? '-', range(0, 6)));
                    $firma = '[FIRMA:' . $sig . ']';
                    $q = $this->db->prepare("SELECT id, nombre FROM horarios WHERE empresa_id = ? AND descripcion = ? LIMIT 1");
                    $q->execute([(int)$empresa_id, $firma]);
                    $hor = $q->fetch(\PDO::FETCH_ASSOC);

                    if (!$hor) {
                        $horasSem = 0.0;
                        for ($w = 0; $w <= 6; $w++) {
                            $meta = $metaPorDia[$w] ?? null;
                            if ($meta && (int)($meta['es_descanso'] ?? 0) === 0) $horasSem += (float)($meta['horas_trabajadas'] ?? 0);
                        }
                        $letras = implode('-', array_values(array_unique(array_filter($modoPorDia, fn($c) => $c !== null))));
                        $hid = $this->model->crearHorario([
                            'empresa_id' => (int)$empresa_id,
                            'nombre' => 'Auto Fijo ' . $letras,
                            'descripcion' => $firma,
                            'horas_semanales' => $horasSem,
                        ]);
                        $hor = ['id' => $hid, 'nombre' => 'Auto Fijo ' . $letras];
                        $res['fijos_creados']++;
                    } else {
                        $res['fijos_reusados']++;
                    }
                    $res['turnos_insertados'] += $this->refrescarTurnosAuto((int)$hor['id'], $modoPorDia, $metaPorDia, (int)$empresa_id, $res['errores']);
                    $autosIds[(int)$hor['id']] = $hor['nombre'];
                    $objetivo = $hor;
                } else {
                    if ($rotativoId === null) {
                        $q = $this->db->prepare("SELECT id, nombre FROM horarios WHERE empresa_id = ? AND descripcion = '[FIRMA:ROTATIVO]' LIMIT 1");
                        $q->execute([(int)$empresa_id]);
                        $hor = $q->fetch(\PDO::FETCH_ASSOC);
                        if (!$hor) {
                            $hid = $this->model->crearHorario([
                                'empresa_id' => (int)$empresa_id,
                                'nombre' => $rotativoNombre,
                                'descripcion' => '[FIRMA:ROTATIVO]',
                                'horas_semanales' => 44,
                            ]);
                            $hor = ['id' => $hid, 'nombre' => $rotativoNombre];
                        }
                        $rotativoId = (int)$hor['id'];
                    }
                    $objetivo = ['id' => $rotativoId, 'nombre' => $rotativoNombre];
                    $res['rotativos']++;
                }

                if (($horarioActual[$uid] ?? null) !== $objetivo['nombre']) {
                    if ($this->asignarSemana($uid, (int)$objetivo['id'], $primerDia, $ultimoDia, $res['errores'])) {
                        $res['asignados']++;
                    }
                }
            } catch (\Throwable $e) {
                $res['errores'][] = 'uid ' . $uid . ': ' . $e->getMessage();
            }
        }

        foreach ($autosIds as $hid => $nombre) {
            $q = $this->db->prepare("SELECT COUNT(*) FROM turnos WHERE horario_id = ?");
            $q->execute([(int)$hid]);
            $res['verif'][] = $nombre . '=' . (int)$q->fetchColumn() . ' turnos';
        }
        return $res;
    }

    // ==========================================================
    //  PANEL DE TURNOS Y CARGA MASIVA
    // ==========================================================

    public function panelTurnos() {
        require_once __DIR__ . '/../Models/CargaMasivaModel.php';
        $m = new \App\Models\CargaMasivaModel($this->db);
        $empresa_id = $this->empresaActual();
        $mes  = (int)($_GET['mes']  ?? date('n'));
        $anio = (int)($_GET['anio'] ?? date('Y'));
        $tab  = $_GET['tab'] ?? 'panel';
        $GLOBALS['pageTitle'] = 'Panel de Turnos';
        $panel      = ($tab === 'panel')      ? $m->getPanel($empresa_id, $mes, $anio) : [];
        $historial  = ($tab === 'historial')  ? $m->getHistorial($empresa_id)          : [];
        $parametros = $m->getParametros($empresa_id);
        $preview = $_SESSION['carga_preview'] ?? null;
        if ($preview !== null && $tab !== 'carga') { unset($_SESSION['carga_preview']); $preview = null; }
        include __DIR__ . '/../Views/horarios/panel_turnos.php';
    }

    public function procesarCarga() {
        require_once __DIR__ . '/../Models/CargaMasivaModel.php';
        require_once __DIR__ . '/../Libs/MiniXLSX.php';
        if (!$this->requiereGestion()) { header('Location: /horion-time/public/horarios?error=' . urlencode('Sin permisos para cargar turnos')); exit; }
        $m = new \App\Models\CargaMasivaModel($this->db);
        $empresa_id = $this->empresaActual();
        $mes  = (int)($_POST['mes']  ?? date('n'));
        $anio = (int)($_POST['anio'] ?? date('Y'));
        $stage = $_POST['stage'] ?? 'preview';
        try {
            if ($stage === 'preview') {
                if (empty($_FILES['archivo']['tmp_name'])) throw new \Exception('Selecciona un archivo .xlsx');
                $dir = __DIR__ . '/../../public/uploads/cargas/';
                if (!is_dir($dir)) mkdir($dir, 0777, true);
                $tmp = $dir . 'tmp_' . uniqid() . '.xlsx';
                if (!move_uploaded_file($_FILES['archivo']['tmp_name'], $tmp)) throw new \Exception('No se pudo guardar el archivo subido');
                $rows = \App\Libs\MiniXLSX::parse($tmp);
                $_SESSION['carga_preview'] = $m->analizarFilas($rows, $mes, $anio, $empresa_id);
                $_SESSION['carga_tmp']  = basename($tmp);
                $_SESSION['carga_meta'] = ['mes' => $mes, 'anio' => $anio, 'archivo' => $_FILES['archivo']['name']];
                header('Location: /horion-time/public/horarios/panelTurnos?tab=carga&mes=' . $mes . '&anio=' . $anio); exit;
            }
            $preview = $_SESSION['carga_preview'] ?? null;
            if (!$preview) throw new \Exception('No hay vista previa vigente: sube el archivo de nuevo.');
            $crear   = !empty($_POST['crear_usuarios']);
            $limpiar = !empty($_POST['limpiar_mes']);
            $meta = $_SESSION['carga_meta'] ?? ['mes' => $mes, 'anio' => $anio, 'archivo' => 'carga.xlsx'];
            $res = $m->confirmarCarga($preview, $empresa_id, (int)$meta['mes'], (int)$meta['anio'], (int)($_SESSION['usuario_id'] ?? 1), $crear, $limpiar, $meta['archivo']);
            $tmpFile = __DIR__ . '/../../public/uploads/cargas/' . ($_SESSION['carga_tmp'] ?? '');
            if ($tmpFile !== '' && is_file($tmpFile)) @unlink($tmpFile);
            unset($_SESSION['carga_preview'], $_SESSION['carga_tmp'], $_SESSION['carga_meta']);

            $syncMsg = '';
            try {
                $sync = $this->doSincronizar($empresa_id, (int)$meta['mes'], (int)$meta['anio']);
                $syncMsg = ' · ' . $this->mensajeSync($sync);
            } catch (\Throwable $e) { $syncMsg = ' · (sincronización omitida: ' . $e->getMessage() . ')'; }

            header('Location: /horion-time/public/horarios/panelTurnos?tab=historial&msg=' .
                urlencode('Carga aplicada: ' . $res['ok'] . ' empleados OK, ' . $res['err'] . ' con observaciones' . $syncMsg)); exit;
        } catch (\Exception $e) {
            unset($_SESSION['carga_preview'], $_SESSION['carga_tmp'], $_SESSION['carga_meta']);
            header('Location: /horion-time/public/horarios/panelTurnos?tab=carga&mes=' . $mes . '&anio=' . $anio . '&error=' . urlencode($e->getMessage())); exit;
        }
    }

    public function guardarCelda() {
        require_once __DIR__ . '/../Models/CargaMasivaModel.php';
        header('Content-Type: application/json');
        if (!$this->requiereGestion()) { echo json_encode(['ok' => false, 'error' => 'Sin permisos para editar turnos']); exit; }
        try {
            $m = new \App\Models\CargaMasivaModel($this->db);
            $empresa_id = $this->empresaActual();
            $uid  = (int)($_POST['usuario_id'] ?? 0);
            $dia  = (int)($_POST['dia'] ?? 0);
            $mes  = (int)($_POST['mes'] ?? date('n'));
            $anio = (int)($_POST['anio'] ?? date('Y'));
            $cod  = strtoupper(trim((string)($_POST['codigo'] ?? '')));
            if (!$uid || !$dia) throw new \Exception('Datos incompletos');
            $fecha = sprintf('%04d-%02d-%02d', $anio, $mes, $dia);
            if ($cod === '') { $m->borrarDia($empresa_id, $uid, $fecha); echo json_encode(['ok' => true]); exit; }
            $horas = 0.0;
            foreach ($m->getParametros($empresa_id) as $par) {
                if (strtoupper($par['codigo']) === $cod) { $horas = (float)$par['horas_trabajadas']; break; }
            }
            $m->upsertDia($empresa_id, $uid, $fecha, $cod, null, $horas, 'manual', null, (int)($_SESSION['usuario_id'] ?? 1));
            echo json_encode(['ok' => true]);
        } catch (\Throwable $e) { echo json_encode(['ok' => false, 'error' => $e->getMessage()]); }
        exit;
    }

    public function parametrosTurnos() {
        require_once __DIR__ . '/../Models/CargaMasivaModel.php';
        if (!$this->requiereGestion()) { header('Location: /horion-time/public/horarios?error=' . urlencode('Sin permisos para editar la leyenda')); exit; }
        $m = new \App\Models\CargaMasivaModel($this->db);
        $rows = [];
        foreach (($_POST['id'] ?? []) as $i => $id) {
            $rows[] = [
                'id' => $id, 'codigo' => $_POST['codigo'][$i] ?? '', 'nombre' => $_POST['nombre'][$i] ?? '',
                'hora_entrada' => $_POST['hora_entrada'][$i] ?? '', 'hora_salida' => $_POST['hora_salida'][$i] ?? '',
                'horas_trabajadas' => $_POST['horas_trabajadas'][$i] ?? 0, 'color' => $_POST['color'][$i] ?? '#e2e8f0',
                'es_descanso' => $_POST['es_descanso'][$i] ?? 0, 'es_vacacion' => $_POST['es_vacacion'][$i] ?? 0,
                'recargo_nocturno' => $_POST['recargo_nocturno'][$i] ?? 0,
            ];
        }
        if (!empty($_POST['new_codigo'])) {
            $rows[] = [
                'id' => 0, 'codigo' => $_POST['new_codigo'], 'nombre' => $_POST['new_nombre'] ?? $_POST['new_codigo'],
                'hora_entrada' => $_POST['new_entrada'] ?? '', 'hora_salida' => $_POST['new_salida'] ?? '',
                'horas_trabajadas' => $_POST['new_horas'] ?? 0, 'color' => $_POST['new_color'] ?? '#e2e8f0',
                'es_descanso' => 0, 'es_vacacion' => 0, 'recargo_nocturno' => 0,
            ];
        }
        $m->guardarParametros($rows, $this->empresaActual());
        header('Location: /horion-time/public/horarios/panelTurnos?tab=leyenda&msg=' . urlencode('Leyenda guardada')); exit;
    }

    public function revertirCarga($id = null) {
        require_once __DIR__ . '/../Models/CargaMasivaModel.php';
        if (!$this->requiereGestion()) { header('Location: /horion-time/public/horarios?error=' . urlencode('Sin permisos para revertir cargas')); exit; }
        $m = new \App\Models\CargaMasivaModel($this->db);
        $m->revertirLote((int)($id ?? 0));
        header('Location: /horion-time/public/horarios/panelTurnos?tab=historial&msg=' . urlencode('Lote revertido')); exit;
    }

    public function descargarPlantilla() {
        require_once __DIR__ . '/../Libs/MiniXLSXWriter.php';
        require_once __DIR__ . '/../Models/CargaMasivaModel.php';
        $m  = new \App\Models\CargaMasivaModel($this->db);
        $mes  = (int)($_GET['mes']  ?? date('n'));
        $anio = (int)($_GET['anio'] ?? date('Y'));
        $diasMes = (int)date('t', mktime(0, 0, $mes, 1, $anio));
        $mesesEs = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
        $parametros = $m->getParametros($this->empresaActual());
        $hoja = [];
        $hoja[] = ['HORARIO DE TURNOS — ' . strtoupper($mesesEs[$mes]) . ' ' . $anio];
        $hoja[] = [];
        $head = ['IDENTIFICACION', 'NOMBRES Y APELLIDOS', 'CARGO', 'SERVICIO A LABORAR'];
        for ($d = 1; $d <= $diasMes; $d++) $head[] = $d;
        $hoja[] = $head;
        $ej1 = ['123456789', 'PEREZ EJEMPLO JUAN', 'AUXILIAR CLINICO', 'URGENCIAS'];
        $ej2 = ['987654321', 'RODRIGUEZ EJEMPLO ANA', 'AUXILIAR CLINICO', 'LAVANDERIA'];
        for ($d = 1; $d <= $diasMes; $d++) {
            $ej1[] = ($d % 7 === 0) ? 'L' : ($d % 7 === 1 ? 'N' : 'C');
            $ej2[] = ($d % 7 === 0) ? 'L' : 'C';
        }
        $hoja[] = $ej1; $hoja[] = $ej2; $hoja[] = [];
        $hoja[] = ['LEYENDA (se edita en el sistema: Panel de Turnos → pestaña Leyenda)'];
        foreach ($parametros as $p) {
            $hoja[] = [$p['codigo'], $p['nombre'], trim(($p['hora_entrada'] ?? '') . ' a ' . ($p['hora_salida'] ?? ''), ' a'), $p['horas_trabajadas'] . ' horas'];
        }
        $hoja[] = [];
        $hoja[] = ['INSTRUCCIONES DE DILIGENCIAMIENTO'];
        $hoja[] = ['1. No modifique la fila de encabezados (IDENTIFICACION ... y los números de día).'];
        $hoja[] = ['2. Una fila por empleado. Si un empleado tiene dos servicios, use dos filas con la MISMA cédula: el sistema las fusiona.'];
        $hoja[] = ['3. En las celdas de día escriba únicamente la letra de la leyenda (C, N, L, M, V) o la palabra VACACIONES.'];
        $hoja[] = ['4. Deje vacía la celda de los días sin turno. No escriba totales ni horas: el sistema los calcula solo.'];
        $hoja[] = ['5. Empleados nuevos: si la cédula no existe en el sistema, podrá crearlos automáticamente al confirmar la carga.'];
        $hoja[] = ['6. Borre las dos filas de ejemplo antes de subir el archivo.'];
        $tmp = tempnam(sys_get_temp_dir(), 'plantilla_') . '.xlsx';
        \App\Libs\MiniXLSXWriter::crear(['Horario' => $hoja], $tmp, ['Horario' => [0, 2]]);
        $nombre = 'Plantilla_Turnos_' . $mesesEs[$mes] . '_' . $anio . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $nombre . '"');
        header('Content-Length: ' . filesize($tmp));
        readfile($tmp); @unlink($tmp); exit;
    }
}
