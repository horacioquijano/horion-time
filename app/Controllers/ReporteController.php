<?php
namespace App\Controllers;
use App\Models\ReporteModel;
use App\Helpers\Security;

class ReporteController {
    private $model;

    public function __construct($db) {
        $this->model = new ReporteModel($db);
    }

    public function index() {
        $empleados = $this->model->getEmpleados($_SESSION['empresa_id'] ?? 1);
        $csrf_token = Security::generateCsrfToken();
        require_once __DIR__ . '/../Views/reportes/index.php';
    }

    public function generar() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                Security::validateCsrfToken($_POST['csrf_token'] ?? '');
                
                $tipo = $_POST['tipo_reporte'];
                $formato = $_POST['formato']; // excel, pdf, csv
                $filters = [
                    'empresa_id' => $_SESSION['empresa_id'] ?? 1,
                    'fecha_inicio' => $_POST['fecha_inicio'],
                    'fecha_fin' => $_POST['fecha_fin'],
                    'usuario_id' => $_POST['usuario_id'] ?? null
                ];

                $data = [];
                switch ($tipo) {
                    case 'asistencia': $data = $this->model->getAsistenciaGeneral($filters); break;
                    case 'tardanzas': $data = $this->model->getTardanzas($filters); break;
                    case 'horas_extras': $data = $this->model->getHorasExtras($filters); break;
                    case 'incidencias': $data = $this->model->getIncidencias($filters); break;
                    case 'consolidado': $data = $this->model->getConsolidadoMensual($filters); break;
                    default: throw new \Exception("Tipo de reporte no válido.");
                }

                // Log
                $this->model->logReporte($_SESSION['usuario_id'] ?? 1, $filters['empresa_id'], $tipo, $filters, $formato, count($data));

                // Exportar
                $this->exportar($tipo, $formato, $data, $filters);
            } catch (\Exception $e) {
                header('Location: /horion-time/public/reportes?error=' . urlencode($e->getMessage()));
                exit;
            }
        }
    }

    private function exportar($tipo, $formato, $data, $filters) {
        $filename = "reporte_{$tipo}_" . date('Ymd_His');
        
        if ($formato === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
            $output = fopen('php://output', 'w');
            // BOM para Excel
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
            
            if (!empty($data)) {
                fputcsv($output, array_keys($data[0]), ';');
                foreach ($data as $row) fputcsv($output, $row, ';');
            }
            fclose($output);
            exit;
        } 
        elseif ($formato === 'excel') {
            // Truco HTML-to-XLS nativo (sin librerías externas, 100% funcional)
            header('Content-Type: application/vnd.ms-excel; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
            echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40"><head><meta charset="UTF-8"></head><body><table border="1">';
            if (!empty($data)) {
                echo '<tr>';
                foreach (array_keys($data[0]) as $col) echo '<th><b>' . htmlspecialchars(ucfirst(str_replace('_', ' ', $col))) . '</b></th>';
                echo '</tr>';
                foreach ($data as $row) {
                    echo '<tr>';
                    foreach ($row as $val) echo '<td>' . htmlspecialchars($val) . '</td>';
                    echo '</tr>';
                }
            }
            echo '</table></body></html>';
            exit;
        } 
        elseif ($formato === 'pdf') {
            // Vista HTML optimizada para impresión (Ctrl+P -> Guardar como PDF)
            header('Content-Type: text/html; charset=utf-8');
            require_once __DIR__ . '/../Views/reportes/pdf_view.php';
            exit;
        }
    }
}