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

    /** Candado: solo roles de gestión pueden escribir turnos/leyenda/cargas */
    private function requiereGestion() {
        $rol = $_SESSION['rol_nombre'] ?? '';
        return in_array($rol, ['SuperAdmin', 'Admin_Empresa', 'RRHH'], true);
    }
    
    /** Listado de horarios y asignaciones */
    public function index() {
        $empresa_id = $this->empresaActual();
        $horarios = $this->model->getHorarios($empresa_id);
        $empleados = $this->model->getEmpleadosConHorario($empresa_id);
        
        $GLOBALS['pageTitle'] = 'Gestión de Horarios';
        include __DIR__ . '/../Views/horarios/index.php';
    }
    
    /** Crear nuevo horario */
    public function crear() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /horion-time/public/horarios');
            exit;
        }
        
        try {
            $horario_id = $this->model->crearHorario([
                'empresa_id' => $this->empresaActual(),
                'nombre' => $_POST['nombre'],
                'descripcion' => $_POST['descripcion'] ?? '',
                'horas_semanales' => $_POST['horas_semanales'] ?? 48
            ]);
            
            // Crear turnos para cada día
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
            
            header('Location: /horion-time/public/horarios?success=1');
            exit;
        } catch (\Exception $e) {
            header('Location: /horion-time/public/horarios?error=' . urlencode($e->getMessage()));
            exit;
        }
    }
    
    /** Asignar horario a empleado */
    public function asignar() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /horion-time/public/horarios');
            exit;
        }
        
        try {
            $this->model->asignarHorario(
                (int)$_POST['usuario_id'],
                (int)$_POST['horario_id'],
                $_POST['fecha_inicio'],
                $_POST['fecha_fin'] ?: null
            );
            
            header('Location: /horion-time/public/horarios?success=1');
            exit;
        } catch (\Exception $e) {
            header('Location: /horion-time/public/horarios?error=' . urlencode($e->getMessage()));
            exit;
        }
    }
    
    /** Ver detalle de horario (JSON) */
    public function detalle($id) {
        $turnos = $this->model->getTurnosHorario($id);
        $dias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        
        echo json_encode([
            'turnos' => $turnos,
            'dias' => $dias
        ]);
        exit;
    }

    // ==========================================================
    //  PANEL DE TURNOS Y CARGA MASIVA
    // ==========================================================

    /** Pantalla principal: Panel Mensual / Carga / Historial / Leyenda */
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
        if ($preview !== null && $tab !== 'carga') {
            unset($_SESSION['carga_preview']);
            $preview = null;
        }

        include __DIR__ . '/../Views/horarios/panel_turnos.php';
    }

    /** Subida del Excel: stage=preview analiza; stage=confirm aplica */
    public function procesarCarga() {
        require_once __DIR__ . '/../Models/CargaMasivaModel.php';
        require_once __DIR__ . '/../Libs/MiniXLSX.php';

        if (!$this->requiereGestion()) {
            header('Location: /horion-time/public/horarios?error=' . urlencode('Sin permisos para cargar turnos'));
            exit;
        }

        $m = new \App\Models\CargaMasivaModel($this->db);
        $empresa_id = $this->empresaActual();
        $mes  = (int)($_POST['mes']  ?? date('n'));
        $anio = (int)($_POST['anio'] ?? date('Y'));
        $stage = $_POST['stage'] ?? 'preview';

        try {
            if ($stage === 'preview') {
                if (empty($_FILES['archivo']['tmp_name'])) {
                    throw new \Exception('Selecciona un archivo .xlsx');
                }
                $dir = __DIR__ . '/../../public/uploads/cargas/';
                if (!is_dir($dir)) mkdir($dir, 0777, true);
                $tmp = $dir . 'tmp_' . uniqid() . '.xlsx';
                if (!move_uploaded_file($_FILES['archivo']['tmp_name'], $tmp)) {
                    throw new \Exception('No se pudo guardar el archivo subido');
                }
                $rows = \App\Libs\MiniXLSX::parse($tmp);
                $_SESSION['carga_preview'] = $m->analizarFilas($rows, $mes, $anio, $empresa_id);
                $_SESSION['carga_tmp']  = basename($tmp);
                $_SESSION['carga_meta'] = ['mes' => $mes, 'anio' => $anio, 'archivo' => $_FILES['archivo']['name']];
                header('Location: /horion-time/public/horarios/panelTurnos?tab=carga&mes=' . $mes . '&anio=' . $anio);
                exit;
            }

            // ---- confirm ----
            $preview = $_SESSION['carga_preview'] ?? null;
            if (!$preview) {
                throw new \Exception('No hay vista previa vigente: sube el archivo de nuevo.');
            }
            $crear   = !empty($_POST['crear_usuarios']);
            $limpiar = !empty($_POST['limpiar_mes']);
            $meta = $_SESSION['carga_meta'] ?? ['mes' => $mes, 'anio' => $anio, 'archivo' => 'carga.xlsx'];

            $res = $m->confirmarCarga(
                $preview,
                $empresa_id,
                (int)$meta['mes'],
                (int)$meta['anio'],
                (int)($_SESSION['usuario_id'] ?? 1),
                $crear,
                $limpiar,
                $meta['archivo']
            );

            $tmpFile = __DIR__ . '/../../public/uploads/cargas/' . ($_SESSION['carga_tmp'] ?? '');
            if ($tmpFile !== '' && is_file($tmpFile)) @unlink($tmpFile);
            unset($_SESSION['carga_preview'], $_SESSION['carga_tmp'], $_SESSION['carga_meta']);

            header('Location: /horion-time/public/horarios/panelTurnos?tab=historial&msg=' .
                urlencode('Carga aplicada: ' . $res['ok'] . ' empleados OK, ' . $res['err'] . ' con observaciones'));
            exit;
        } catch (\Exception $e) {
            unset($_SESSION['carga_preview'], $_SESSION['carga_tmp'], $_SESSION['carga_meta']);
            header('Location: /horion-time/public/horarios/panelTurnos?tab=carga&mes=' . $mes . '&anio=' . $anio .
                '&error=' . urlencode($e->getMessage()));
            exit;
        }
    }

    /** Guardar una celda del panel (AJAX) */
    public function guardarCelda() {
        require_once __DIR__ . '/../Models/CargaMasivaModel.php';
        header('Content-Type: application/json');

        if (!$this->requiereGestion()) {
            echo json_encode(['ok' => false, 'error' => 'Sin permisos para editar turnos']);
            exit;
        }

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

            if ($cod === '') {
                $m->borrarDia($empresa_id, $uid, $fecha);
                echo json_encode(['ok' => true]);
                exit;
            }

            $horas = 0.0;
            foreach ($m->getParametros($empresa_id) as $par) {
                if (strtoupper($par['codigo']) === $cod) { $horas = (float)$par['horas_trabajadas']; break; }
            }

            $m->upsertDia($empresa_id, $uid, $fecha, $cod, null, $horas, 'manual', null, (int)($_SESSION['usuario_id'] ?? 1));
            echo json_encode(['ok' => true]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** Guardar la leyenda parametrizable */
    public function parametrosTurnos() {
        require_once __DIR__ . '/../Models/CargaMasivaModel.php';

        if (!$this->requiereGestion()) {
            header('Location: /horion-time/public/horarios?error=' . urlencode('Sin permisos para editar la leyenda'));
            exit;
        }

        $m = new \App\Models\CargaMasivaModel($this->db);
        $rows = [];

        foreach (($_POST['id'] ?? []) as $i => $id) {
            $rows[] = [
                'id'               => $id,
                'codigo'           => $_POST['codigo'][$i] ?? '',
                'nombre'           => $_POST['nombre'][$i] ?? '',
                'hora_entrada'     => $_POST['hora_entrada'][$i] ?? '',
                'hora_salida'      => $_POST['hora_salida'][$i] ?? '',
                'horas_trabajadas' => $_POST['horas_trabajadas'][$i] ?? 0,
                'color'            => $_POST['color'][$i] ?? '#e2e8f0',
                'es_descanso'      => $_POST['es_descanso'][$i] ?? 0,
                'es_vacacion'      => $_POST['es_vacacion'][$i] ?? 0,
                'recargo_nocturno' => $_POST['recargo_nocturno'][$i] ?? 0,
            ];
        }

        if (!empty($_POST['new_codigo'])) {
            $rows[] = [
                'id'               => 0,
                'codigo'           => $_POST['new_codigo'],
                'nombre'           => $_POST['new_nombre'] ?? $_POST['new_codigo'],
                'hora_entrada'     => $_POST['new_entrada'] ?? '',
                'hora_salida'      => $_POST['new_salida'] ?? '',
                'horas_trabajadas' => $_POST['new_horas'] ?? 0,
                'color'            => $_POST['new_color'] ?? '#e2e8f0',
                'es_descanso'      => 0,
                'es_vacacion'      => 0,
                'recargo_nocturno' => 0,
            ];
        }

        $m->guardarParametros($rows, $this->empresaActual());
        header('Location: /horion-time/public/horarios/panelTurnos?tab=leyenda&msg=' . urlencode('Leyenda guardada'));
        exit;
    }

    /** Revertir un lote completo */
    public function revertirCarga($id = null) {
        require_once __DIR__ . '/../Models/CargaMasivaModel.php';

        if (!$this->requiereGestion()) {
            header('Location: /horion-time/public/horarios?error=' . urlencode('Sin permisos para revertir cargas'));
            exit;
        }

        $m = new \App\Models\CargaMasivaModel($this->db);
        $m->revertirLote((int)($id ?? 0));
        header('Location: /horion-time/public/horarios/panelTurnos?tab=historial&msg=' . urlencode('Lote revertido'));
        exit;
    }

    /** GENERA Y DESCARGA LA PLANTILLA .xlsx DEL MES (NUEVO) */
    public function descargarPlantilla() {
        require_once __DIR__ . '/../Libs/MiniXLSXWriter.php';
        require_once __DIR__ . '/../Models/CargaMasivaModel.php';

        $m  = new \App\Models\CargaMasivaModel($this->db);
        $mes  = (int)($_GET['mes']  ?? date('n'));
        $anio = (int)($_GET['anio'] ?? date('Y'));
        $diasMes = (int)date('t', mktime(0, 0, $mes, 1, $anio));
        $mesesEs = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',
                    7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];

        $parametros = $m->getParametros($this->empresaActual());

        $hoja = [];
        $hoja[] = ['HORARIO DE TURNOS — ' . strtoupper($mesesEs[$mes]) . ' ' . $anio];
        $hoja[] = [];

        $head = ['IDENTIFICACION', 'NOMBRES Y APELLIDOS', 'CARGO', 'SERVICIO A LABORAR'];
        for ($d = 1; $d <= $diasMes; $d++) $head[] = $d;
        $hoja[] = $head;

        // Filas de ejemplo
        $ej1 = ['123456789', 'PEREZ EJEMPLO JUAN', 'AUXILIAR CLINICO', 'URGENCIAS'];
        $ej2 = ['987654321', 'RODRIGUEZ EJEMPLO ANA', 'AUXILIAR CLINICO', 'LAVANDERIA'];
        for ($d = 1; $d <= $diasMes; $d++) {
            $ej1[] = ($d % 7 === 0) ? 'L' : ($d % 7 === 1 ? 'N' : 'C');
            $ej2[] = ($d % 7 === 0) ? 'L' : 'C';
        }
        $hoja[] = $ej1;
        $hoja[] = $ej2;
        $hoja[] = [];

        $hoja[] = ['LEYENDA (se edita en el sistema: Panel de Turnos → pestaña Leyenda)'];
        foreach ($parametros as $p) {
            $hoja[] = [
                $p['codigo'],
                $p['nombre'],
                trim(($p['hora_entrada'] ?? '') . ' a ' . ($p['hora_salida'] ?? ''), ' a'),
                $p['horas_trabajadas'] . ' horas',
            ];
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
        readfile($tmp);
        @unlink($tmp);
        exit;
    }
}
