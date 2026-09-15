<?php
namespace App\Controllers;
use App\Models\AuditoriaModel;
use App\Helpers\Security;

class AuditoriaController {
    private $model;

    public function __construct($db) {
        $this->model = new AuditoriaModel($db);
    }

    public function index() {
        // Validación de seguridad: Solo admins pueden ver logs
        $rol = $_SESSION['rol_nombre'] ?? '';
        if (!in_array($rol, ['SuperAdmin', 'Admin_Empresa'])) {
            http_response_code(403);
            die("Acceso denegado.");
        }

        $filters = [
            'usuario_id' => $_GET['usuario_id'] ?? null,
            'accion' => $_GET['accion'] ?? null,
            'tabla' => $_GET['tabla'] ?? null,
            'fecha_inicio' => $_GET['fecha_inicio'] ?? null,
            'fecha_fin' => $_GET['fecha_fin'] ?? null,
            'busqueda' => $_GET['busqueda'] ?? null
        ];

        $page = max(1, (int)($_GET['page'] ?? 1));
        $logs = $this->model->getLogs($filters, $page);
        $total = $this->model->getTotalLogs($filters);
        $resumen = $this->model->getResumen();
        $totalPages = ceil($total / 50);
        $csrf_token = Security::generateCsrfToken();

        require_once __DIR__ . '/../Views/auditoria/index.php';
    }
}