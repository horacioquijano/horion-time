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

        // SuperAdmin + "Todas" => vista global; SuperAdmin con empresa elegida => vista de esa empresa
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
        return [
            'resumen_dia' => $this->model->getResumenDia($empresa_id),
            'asistencia_por_sede' => $this->model->getAsistenciaPorSede($empresa_id),
            'top_tardanzas' => $this->model->getTopTardanzas($empresa_id),
            'horas_trabajadas' => $this->model->getHorasTrabajadasVsProgramadas($empresa_id),
            'incidencias_tipo' => $this->model->getIncidenciasPorTipo($empresa_id),
            'empresa_nombre' => $_SESSION['empresa_nombre'] ?? 'Mi Empresa'
        ];
    }   
    /**
     * Método para marcar alerta como leída (llamado vía AJAX desde el frontend)
     */
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
    
}