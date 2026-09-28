<?php
namespace App\Controllers;
use App\Models\DispositivoModel;
use App\Helpers\Security;

class DispositivoController {
    private $model;
    private $db;

    public function __construct($db) {
        $this->db = $db;
        $this->model = new DispositivoModel($db);
    }

    /** Listado + sedes reales para el modal */
    public function index() {
        $dispositivos = $this->model->getAll($_SESSION['empresa_id'] ?? 1);

        // Sedes: SuperAdmin en modo global ve todas; demás solo su empresa
        $esSuper = (($_SESSION['rol_nombre'] ?? '') === 'SuperAdmin');
        $modoGlobal = (bool)($_SESSION['modo_global'] ?? false);
        if ($esSuper && $modoGlobal) {
            $st = $this->db->query("SELECT s.id, s.nombre, s.empresa_id, e.nombre AS empresa_nombre
                                    FROM sedes s LEFT JOIN empresas e ON e.id = s.empresa_id
                                    ORDER BY e.nombre ASC, s.nombre ASC");
            $sedes = $st->fetchAll(\PDO::FETCH_ASSOC);
        } else {
            $st = $this->db->prepare("SELECT s.id, s.nombre, s.empresa_id, e.nombre AS empresa_nombre
                                      FROM sedes s LEFT JOIN empresas e ON e.id = s.empresa_id
                                      WHERE s.empresa_id = ? ORDER BY s.nombre ASC");
            $st->execute([(int)($_SESSION['empresa_id'] ?? 1)]);
            $sedes = $st->fetchAll(\PDO::FETCH_ASSOC);
        }

        $csrf_token = Security::generateCsrfToken();
        require_once __DIR__ . '/../Views/dispositivos/index.php';
    }

    /** Crear dispositivo con token + PIN autogenerados y tipos válidos del ENUM */
    public function store() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /horion-time/public/dispositivos');
            exit;
        }
        try {
            Security::validateCsrfToken($_POST['csrf_token'] ?? '');

            $nombre = trim((string)($_POST['nombre'] ?? ''));
            if ($nombre === '') throw new \Exception('El nombre del dispositivo es obligatorio');

            $sede_id = (int)($_POST['sede_id'] ?? 0);
            if (!$sede_id) throw new \Exception('Selecciona una sede');

            // Mapeo seguro al ENUM('tablet','pc','movil','kiosco')
            $tipoMap = [
                'kiosco_dedicado' => 'kiosco',
                'kiosco'          => 'kiosco',
                'tablet'          => 'tablet',
                'pc'              => 'pc',
                'movil'           => 'movil',
            ];
            $tipo = $tipoMap[$_POST['tipo'] ?? 'kiosco'] ?? 'kiosco';

            $fila = [
                'empresa_id'      => (int)($_POST['empresa_id'] ?? $_SESSION['empresa_id'] ?? 1),
                'sede_id'         => $sede_id,
                'nombre'          => $nombre,
                'tipo'            => $tipo,
                'estado'          => 'activo',
                'modo_kiosco'     => isset($_POST['modo_kiosco']) ? 1 : 0,
                'inactividad_seg' => max(5, (int)($_POST['inactividad_seg'] ?? 30)),
                'pin_acceso'      => str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT),
                'token_kiosco'    => bin2hex(random_bytes(16)),
                'mac_address'     => trim((string)($_POST['mac_address'] ?? '')) ?: null,
                'ip_address'      => trim((string)($_POST['ip_address'] ?? '')) ?: null,
            ];

            // Insert dinámico seguro: solo columnas que existen
            $cols = $this->db->query("SHOW COLUMNS FROM dispositivos")->fetchAll(\PDO::FETCH_COLUMN);
            $use = array_intersect_key($fila, array_flip($cols));
            $campos = implode(', ', array_map(fn($k) => "`$k`", array_keys($use)));
            $marks  = implode(', ', array_map(fn($k) => ":$k", array_keys($use)));
            $st = $this->db->prepare("INSERT INTO dispositivos ($campos) VALUES ($marks)");
            foreach ($use as $k => $v) $st->bindValue(":$k", $v);
            $st->execute();

            header('Location: /horion-time/public/dispositivos?success=1');
            exit;
        } catch (\Exception $e) {
            header('Location: /horion-time/public/dispositivos?error=' . urlencode($e->getMessage()));
            exit;
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
