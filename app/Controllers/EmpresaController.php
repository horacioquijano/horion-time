<?php
namespace App\Controllers;
use App\Models\EmpresaModel;
use PDO;

class EmpresaController {
    private $model;
    private $db;

    public function __construct($db) {
        $this->db = $db;
        $this->model = new EmpresaModel($db);
    }

    private function rol() {
        return $_SESSION['rol_nombre'] ?? $_SESSION['rol'] ?? '';
    }

    /** Empresa CASA del usuario logueado (no la del selector temporal) */
    private function empresaOrigen(): int {
        try {
            $st = $this->db->prepare("SELECT empresa_id FROM usuarios WHERE id = ?");
            $st->execute([(int)($_SESSION['usuario_id'] ?? 0)]);
            $v = (int)$st->fetchColumn();
            if ($v > 0) return $v;
        } catch (\Throwable $e) {}
        return (int)($_SESSION['empresa_id'] ?? 0) ?: 1;
    }

    /** ÚNICO dueño de la vista global: Horion Time S.A.S. (id 1) */
    private function esPlataforma(): bool {
        return $this->empresaOrigen() === 1;
    }

    private function empresaActiva(): int {
        return (int)($_SESSION['empresa_id'] ?? 0) ?: $this->empresaOrigen();
    }

    /** Listado: Horion ve todo; demás empresas solo la suya y sus sucursales */
    public function index() {
        $es_plataforma = $this->esPlataforma();
        $mi_empresa    = $this->empresaActiva();

        $empresas = $this->model->getAll();
        if (!$es_plataforma && is_array($empresas)) {
            $empresas = array_values(array_filter(
                $empresas,
                fn($e) => (int)($e['id'] ?? 0) === $mi_empresa
            ));
        }

        // Sucursales SOLO de las empresas visibles
        $sedesPorEmpresa = [];
        $ids = array_map(fn($e) => (int)$e['id'], $empresas);
        if ($ids) {
            $marks = implode(',', array_fill(0, count($ids), '?'));
            $st = $this->db->prepare("SELECT * FROM sedes WHERE empresa_id IN ($marks) ORDER BY empresa_id, nombre");
            $st->execute($ids);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $s) {
                $sedesPorEmpresa[(int)$s['empresa_id']][] = $s;
            }
        }

        require_once __DIR__ . '/../Views/empresas/index.php';
    }

    /** Crear empresa: exclusivo de Horion Time S.A.S. */
    public function store() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /horion-time/public/empresas'); exit; }
        if (!$this->esPlataforma()) {
            header('Location: /horion-time/public/empresas?error=' . urlencode('Solo Horion Time S.A.S. puede crear empresas')); exit;
        }
        try {
            $_POST['estado'] = $_POST['estado'] ?? 'activo';
            $this->model->create($_POST);
            header('Location: /horion-time/public/empresas?success=1'); exit;
        } catch (\Exception $e) {
            header('Location: /horion-time/public/empresas?error=' . urlencode($e->getMessage())); exit;
        }
    }

    /** Editar: Horion cualquiera; demás empresas solo la suya */
    public function update($id = null) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /horion-time/public/empresas'); exit; }
        $id = (int)($id ?? ($_POST['id'] ?? 0));
        $origen = $this->empresaOrigen();
        $mi     = $this->empresaActiva();
        if ($origen !== 1 && $id !== $mi && $id !== $origen) {
            header('Location: /horion-time/public/empresas?error=' . urlencode('Sin permiso para editar esta empresa')); exit;
        }
        try {
            $cols = $this->db->query("SHOW COLUMNS FROM empresas")->fetchAll(PDO::FETCH_COLUMN);
            $data = array_intersect_key($_POST, array_flip($cols));
            unset($data['id']);
            if (!$data) throw new \Exception('Nada que actualizar');
            $sets = implode(', ', array_map(fn($k) => "`$k` = :$k", array_keys($data)));
            $st = $this->db->prepare("UPDATE empresas SET $sets WHERE id = :id");
            foreach ($data as $k => $v) $st->bindValue(":$k", $v);
            $st->bindValue(':id', $id);
            $st->execute();
            header('Location: /horion-time/public/empresas?success=1'); exit;
        } catch (\Exception $e) {
            header('Location: /horion-time/public/empresas?error=' . urlencode($e->getMessage())); exit;
        }
    }

    /** Eliminar empresa: exclusivo de Horion Time S.A.S. */
    public function destroy($id) {
        if (!$this->esPlataforma()) {
            header('Location: /horion-time/public/empresas?error=' . urlencode('Solo Horion Time S.A.S. puede eliminar empresas')); exit;
        }
        try {
            $this->model->delete($id, true);
            header('Location: /horion-time/public/empresas?deleted=1'); exit;
        } catch (\Exception $e) {
            header('Location: /horion-time/public/empresas?error=' . urlencode($e->getMessage())); exit;
        }
    }

    /** Crear sucursal: Horion en cualquier empresa; demás solo en la suya */
    public function storeSede() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /horion-time/public/empresas'); exit; }
        $empresa_id = (int)($_POST['empresa_id'] ?? 0);
        $origen = $this->empresaOrigen();
        $mi     = $this->empresaActiva();
        if ($origen !== 1 && $empresa_id !== $mi && $empresa_id !== $origen) {
            header('Location: /horion-time/public/empresas?error=' . urlencode('Sin permiso para crear sucursales en esta empresa')); exit;
        }
        try {
            if (trim($_POST['nombre'] ?? '') === '') throw new \Exception('El nombre de la sucursal es obligatorio');
            $cols = $this->db->query("SHOW COLUMNS FROM sedes")->fetchAll(PDO::FETCH_COLUMN);
            $fila = [
                'empresa_id' => $empresa_id,
                'nombre'     => trim((string)$_POST['nombre']),
                'ciudad'     => trim((string)($_POST['ciudad'] ?? '')),
                'direccion'  => trim((string)($_POST['direccion'] ?? '')),
                'telefono'   => trim((string)($_POST['telefono'] ?? '')),
                'estado'     => 'activo',
            ];
            $fila = array_intersect_key($fila, array_flip($cols));
            $campos = implode(', ', array_map(fn($k) => "`$k`", array_keys($fila)));
            $marks  = implode(', ', array_map(fn($k) => ":$k", array_keys($fila)));
            $st = $this->db->prepare("INSERT INTO sedes ($campos) VALUES ($marks)");
            foreach ($fila as $k => $v) $st->bindValue(":$k", $v);
            $st->execute();
            header('Location: /horion-time/public/empresas?success=1'); exit;
        } catch (\Exception $e) {
            header('Location: /horion-time/public/empresas?error=' . urlencode($e->getMessage())); exit;
        }
    }

    /** Eliminar sucursal: Horion cualquiera; demás solo las suyas */
    public function destroySede($id = null) {
        $id = (int)($id ?? 0);
        $origen = $this->empresaOrigen();
        $mi     = $this->empresaActiva();
        try {
            $q = $this->db->prepare("SELECT empresa_id FROM sedes WHERE id = ?");
            $q->execute([$id]);
            $emp = (int)$q->fetchColumn();
            if ($origen !== 1 && $emp !== $mi && $emp !== $origen) {
                throw new \Exception('Sin permiso sobre esta sucursal');
            }
            $this->db->prepare("DELETE FROM sedes WHERE id = ?")->execute([$id]);
            header('Location: /horion-time/public/empresas?deleted=1'); exit;
        } catch (\Exception $e) {
            header('Location: /horion-time/public/empresas?error=' . urlencode($e->getMessage())); exit;
        }
    }
}