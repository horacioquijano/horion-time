<?php
namespace App\Controllers;
use App\Models\HorarioModel;

class HorarioController {
    private $model;
    private $db;
    
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
    //  SINCRONIZACIÓN AUTOMÁTICA PANEL → HORARIOS SEMANALES
    // ==========================================================

    /** Ruta del botón 🔄 */
    public function sincronizarHorarios() {
        if (!$this->requiereGestion()) { header('Location: /horion-time/public/horarios'); exit; }
        $mes  = (int)($_GET['mes']  ?? date('n'));
        $anio = (int)($_GET['anio'] ?? date('Y'));
        try {
            $res = $this->doSincronizar($this->empresaActual(), $mes, $anio);
            $msg = 'Sincronización completa: ' . $res['asignados'] . ' asignados (' .
                   $res['fijos_creados'] . ' horarios fijos creados, ' . $res['fijos_reusados'] .
                   ' reutilizados, ' . $res['rotativos'] . ' rotativos).';
            header('Location: /horion-time/public/horarios?success=1&msg=' . urlencode($msg)); exit;
        } catch (\Throwable $e) {
            header('Location: /horion-time/public/horarios?error=' . urlencode('Error sincronizando: ' . $e->getMessage())); exit;
        }
    }

    /** Motor: detecta fijos vs rotativos y crea/reutiliza/asigna horarios */
    private function doSincronizar($empresa_id, $mes, $anio) {
        $res = ['fijos_creados'=>0,'fijos_reusados'=>0,'rotativos'=>0,'asignados'=>0,'omitidos'=>0];

        // 1) Calendario del mes con horas de la leyenda
        $st = $this->db->prepare("SELECT tc.usuario_id, tc.fecha, tc.codigo,
                                         pt.hora_entrada, pt.hora_salida, pt.horas_trabajadas,
                                         pt.es_descanso, pt.es_vacacion
                                  FROM turnos_calendario tc
                                  LEFT JOIN parametros_turnos pt ON pt.codigo = tc.codigo
                                  WHERE tc.empresa_id = ? AND YEAR(tc.fecha) = ? AND MONTH(tc.fecha) = ?");
        $st->execute([(int)$empresa_id, (int)$anio, (int)$mes]);
        $porUsuario = [];
        foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $r) {
            $porUsuario[(int)$r['usuario_id']][] = $r;
        }
        if (!$porUsuario) return $res;

        // 2) Horarios actuales por empleado (para no re-asignar el mismo)
        $horarioActual = [];
        foreach ($this->model->getEmpleadosConHorario($empresa_id) as $e) {
            $horarioActual[(int)$e['id']] = $e['horario_nombre'] ?? null;
        }

        $primerDia = sprintf('%04d-%02d-01', $anio, $mes);
        $ultimoDia = date('Y-m-t', strtotime($primerDia));
        $rotativoId = null;

        foreach ($porUsuario as $uid => $filas) {
            // Ignora empleados sin días laborables (solo V/L)
            $laborales = array_filter($filas, fn($f) => (int)($f['es_vacacion'] ?? 0) === 0 && (int)($f['es_descanso'] ?? 0) === 0);
            if (count($laborales) === 0) { $res['omitidos']++; continue; }

            // 3) Agrupa por día de semana (0=dom … 6=sáb)
            $porDia = [];
            foreach ($filas as $f) {
                if ((int)($f['es_vacacion'] ?? 0) === 1) continue; // vacaciones = excepción
                $w = (int)date('w', strtotime($f['fecha']));
                $porDia[$w][] = $f;
            }

            // 4) ¿FIJO? regla de mayoría ≥75% por día de semana
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
                $ratio = $conteo[$modo] / count($lista);
                if ($ratio < 0.75) { $esFijo = false; }
                $modoPorDia[$w] = $modo;
                $metaPorDia[$w] = $lista[0];
            }

            if ($esFijo) {
                // 5) Firma del patrón → reutilizar horario existente
                $sig = implode(',', array_map(fn($w) => $modoPorDia[$w] ?? '-', range(0, 6)));
                $firma = '[FIRMA:' . $sig . ']';
                $q = $this->db->prepare("SELECT id, nombre FROM horarios WHERE empresa_id = ? AND descripcion = ? LIMIT 1");
                $q->execute([(int)$empresa_id, $firma]);
                $hor = $q->fetch(\PDO::FETCH_ASSOC);

                if (!$hor) {
                    $horasSem = 0.0;
                    $turnos = [];
                    for ($w = 0; $w <= 6; $w++) {
                        $cod = $modoPorDia[$w];
                        if ($cod === null) continue;
                        $meta = $metaPorDia[$w] ?? null;
                        $esDesc = $meta ? (int)($meta['es_descanso'] ?? 0) : 0;
                        if ($esDesc === 1) {
                            $turnos[$w] = ['hora_entrada'=>null,'hora_salida'=>null,'es_descanso'=>1,'tolerancia_entrada_min'=>10,'tolerancia_salida_min'=>10];
                        } else {
                            $horasSem += (float)($meta['horas_trabajadas'] ?? 0);
                            $turnos[$w] = [
                                'hora_entrada' => $meta['hora_entrada'] ?? null,
                                'hora_salida'  => $meta['hora_salida'] ?? null,
                                'es_descanso'  => 0,
                                'tolerancia_entrada_min' => 10,
                                'tolerancia_salida_min' => 10,
                            ];
                        }
                    }
                    $letras = implode('-', array_values(array_unique(array_filter($modoPorDia, fn($c) => $c !== null))));
                    $hid = $this->model->crearHorario([
                        'empresa_id' => (int)$empresa_id,
                        'nombre' => 'Auto Fijo ' . $letras,
                        'descripcion' => $firma,
                        'horas_semanales' => $horasSem,
                    ]);
                    $this->model->crearTurnos($hid, $turnos);
                    $hor = ['id' => $hid, 'nombre' => 'Auto Fijo ' . $letras];
                    $res['fijos_creados']++;
                } else {
                    $res['fijos_reusados']++;
                }
                $objetivo = $hor;
            } else {
                // 6) ROTATIVO → horario marcador compartido
                if ($rotativoId === null) {
                    $q = $this->db->prepare("SELECT id, nombre FROM horarios WHERE empresa_id = ? AND descripcion = '[FIRMA:ROTATIVO]' LIMIT 1");
                    $q->execute([(int)$empresa_id]);
                    $hor = $q->fetch(\PDO::FETCH_ASSOC);
                    if (!$hor) {
                        $hid = $this->model->crearHorario([
                            'empresa_id' => (int)$empresa_id,
                            'nombre' => 'Rotativo — ver Panel de Turnos',
                            'descripcion' => '[FIRMA:ROTATIVO]',
                            'horas_semanales' => 44,
                        ]);
                        $turnos = [];
                        for ($w = 0; $w <= 6; $w++) {
                            $turnos[$w] = ['hora_entrada'=>null,'hora_salida'=>null,'es_descanso'=>0,'tolerancia_entrada_min'=>10,'tolerancia_salida_min'=>10];
                        }
                        $this->model->crearTurnos($hid, $turnos);
                        $hor = ['id' => $hid, 'nombre' => 'Rotativo — ver Panel de Turnos'];
                    }
                    $rotativoId = (int)$hor['id'];
                    $this->rotativoNombre = $hor['nombre'];
                }
                $objetivo = ['id' => $rotativoId, 'nombre' => $this->rotativoNombre ?? 'Rotativo — ver Panel de Turnos'];
                $res['rotativos']++;
            }

            // 7) Asignar solo si cambió
            if (($horarioActual[$uid] ?? null) !== $objetivo['nombre']) {
                $this->model->asignarHorario($uid, (int)$objetivo['id'], $primerDia, $ultimoDia);
                $res['asignados']++;
            }
        }
        return $res;
    }
    private $rotativoNombre = null;

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

            // ===== NUEVO: sincroniza horarios semanales automáticamente =====
            $syncMsg = '';
            try {
                $sync = $this->doSincronizar($empresa_id, (int)$meta['mes'], (int)$meta['anio']);
                $syncMsg = ' · Horarios sincronizados: ' . $sync['asignados'] . ' asignados (' .
                           $sync['fijos_creados'] . ' fijos nuevos, ' . $sync['fijos_reusados'] . ' reutilizados, ' .
                           $sync['rotativos'] . ' rotativos).';
            } catch (\Throwable $e) { $syncMsg = ' · (sincronización de horarios omitida: ' . $e->getMessage() . ')'; }

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
