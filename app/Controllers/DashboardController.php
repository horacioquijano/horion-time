<?php
namespace App\Controllers;
require_once __DIR__ . '/../Models/DashboardModel.php';
require_once __DIR__ . '/../Helpers/Security.php';
use App\Models\DashboardModel;
use App\Helpers\Security;

class DashboardController {
    private $model;
    private $db;

    public function __construct($db) {
        $this->db = $db;
        $this->model = new DashboardModel($db);
    }

    public function index() {
        $rol = $_SESSION['rol_nombre'] ?? 'Empleado';
        $modo_global = (bool)($_SESSION['modo_global'] ?? ($rol === 'SuperAdmin'));
        $empresa_id = (int)($_SESSION['empresa_id'] ?? 1);
        $usuario_id = $_SESSION['usuario_id'] ?? 1;

        $alertas = $this->model->getAlertas($empresa_id, $usuario_id, 5);
        $vista_global = ($rol === 'SuperAdmin' && $modo_global);

        if ($vista_global) {
            $data = $this->getDatosSuperAdmin();
        } else {
            $data = $this->getDatosAdminEmpresa($empresa_id);
        }

        $data['alertas'] = $alertas;
        $data['rol'] = $rol;
        $data['vista_global'] = $vista_global;
        $csrf_token = Security::generateCsrfToken();
        extract($data);
        require_once __DIR__ . '/../Views/dashboard/index.php';
    }

    private function getDatosSuperAdmin() {
        return [
            'resumen_global' => $this->model->getResumenGlobal(),
            'asistencia_hoy' => $this->model->getAsistenciaGlobalHoy(),
            'asistencia_por_empresa' => $this->model->getAsistenciaPorEmpresa(),
            'tendencias_semanales' => $this->model->getTendenciasSemanales()
        ];
    }

    private function getDatosAdminEmpresa($empresa_id) {
        $filtros = [
            'estado' => $_GET['estado'] ?? '',
            'turno'  => $_GET['turno'] ?? '',
        ];
        return [
            'resumen_dia' => $this->model->getResumenDia($empresa_id),
            // ===== FASE G: datos del Panel de Turnos =====
            'resumen_dia_panel' => $this->model->getResumenDiaPanel($empresa_id),
            'estado_dia_detallado' => $this->model->getEstadoDiaDetallado($empresa_id, null, $filtros['estado'], $filtros['turno']),
            'conteos_estado' => $this->model->getConteosEstadoDia($empresa_id),
            'filtros' => $filtros,
            // ===== Fin FASE G =====
            'asistencia_por_sede' => $this->model->getAsistenciaPorSede($empresa_id),
            'top_tardanzas' => $this->model->getTopTardanzas($empresa_id),
            'horas_trabajadas' => $this->model->getHorasTrabajadasVsProgramadas($empresa_id),
            'incidencias_tipo' => $this->model->getIncidenciasPorTipo($empresa_id),
            'empresa_nombre' => $_SESSION['empresa_nombre'] ?? 'Mi Empresa'
        ];
    }

    public function marcarLeida() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $_POST['id'] ?? 0;
            $usuario_id = $_SESSION['usuario_id'] ?? 1;
            $this->model->marcarAlertaLeida($id, $usuario_id);
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        }
    }

    /** FASE G: Exportar reporte del día a CSV (estado de cada empleado según Panel) */
    public function exportarDiaCSV() {
        $rol = $_SESSION['rol_nombre'] ?? 'Empleado';
        if (!in_array($rol, ['SuperAdmin','Admin_Empresa','RRHH','Supervisor','Auditor'], true)) {
            header('Location: /horion-time/public/dashboard'); exit;
        }
        $empresa_id = (int)($_SESSION['empresa_id'] ?? 1);
        $fecha = $_GET['fecha'] ?? date('Y-m-d');
        $filas = $this->model->getEstadoDiaDetallado($empresa_id, $fecha);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="estado_dia_' . $fecha . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Cédula','Empleado','Servicio','Sede','Turno','Horario','Estado','Hora entrada','Min diferencia']);
        foreach ($filas as $f) {
            $h = '';
            if (!empty($f['hora_entrada'])) {
                $h = substr($f['hora_entrada'],0,5) . '-' . substr($f['hora_salida'] ?? '',0,5);
            }
            fputcsv($out, [
                $f['identificacion'] ?? '',
                $f['nombre_completo'] ?? '',
                $f['servicio'] ?? '',
                $f['sede_nombre'] ?? '',
                $f['turno_codigo'] ?? '',
                $h,
                $f['estado']['label'] ?? '',
                $f['entrada_real'] ?? '',
                $f['estado']['diff'] !== null ? $f['estado']['diff'] . ' min' : '',
            ]);
        }
        fclose($out);
        exit;
    }
}
