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
    
    /** Ver detalle de horario */
    public function detalle($id) {
        $turnos = $this->model->getTurnosHorario($id);
        $dias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        
        echo json_encode([
            'turnos' => $turnos,
            'dias' => $dias
        ]);
        exit;
    }
}