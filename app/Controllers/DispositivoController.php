<?php
namespace App\Controllers;
use App\Models\DispositivoModel;
use App\Helpers\Security;

class DispositivoController {
    private $model;

    public function __construct($db) {
        $this->model = new DispositivoModel($db);
    }

    public function index() {
        $dispositivos = $this->model->getAll($_SESSION['empresa_id'] ?? 1);
        $csrf_token = Security::generateCsrfToken();
        require_once __DIR__ . '/../Views/dispositivos/index.php';
    }

    public function store() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                Security::validateCsrfToken($_POST['csrf_token'] ?? '');
                $this->model->create($_POST);
                header('Location: /horion-time/public/dispositivos?success=1');
                exit;
            } catch (\Exception $e) {
                header('Location: /horion-time/public/dispositivos?error=' . urlencode($e->getMessage()));
                exit;
            }
        }
    }

    public function destroy($id) {
        try {
            $this->model->delete($id, $_SESSION['rol_nombre'] ?? 'Empleado');
            header('Location: /horion-time/public/dispositivos?deleted=1');
            exit;
        } catch (\Exception $e) {
            header('Location: /horion-time/public/dispositivos?error=' . urlencode($e->getMessage()));
            exit;
        }
    }
}