<?php
namespace App\Controllers;
use App\Models\UsuarioModel;
use PDO;

class UsuarioController {
    private $model;
    private $db;

    public function __construct($db) {
        $this->db = $db;
        $this->model = new UsuarioModel($db);
    }

    private function esSuper()       { return (($_SESSION['rol_nombre'] ?? '') === 'SuperAdmin'); }
    private function modoGlobal()    { return (bool)($_SESSION['modo_global'] ?? false); }
    private function empresaActual() { return (int)($_SESSION['empresa_id'] ?? 0); }

    public function index() {
        $es_super    = $this->esSuper();
        $modo_global = $this->modoGlobal();
        $mi_empresa  = $this->empresaActual();

        $usuarios = ($es_super && $modo_global)
            ? $this->model->getAll()
            : $this->model->getByEmpresa($mi_empresa ?: 1);

        $roles = $this->db->query("SELECT id, nombre FROM roles GROUP BY id, nombre ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

        // Sucursales y jefes AGRUPADOS POR EMPRESA (el select siempre tiene datos)
        $sedesPorEmpresa = []; $jefesPorEmpresa = [];
        if ($es_super) {
            $st = $this->db->query("SELECT id, nombre, empresa_id FROM sedes ORDER BY empresa_id, nombre");
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $s) $sedesPorEmpresa[(int)$s['empresa_id']][] = $s;
            $st = $this->db->query("SELECT id, nombre_completo, cargo, empresa_id FROM usuarios WHERE es_jefe = 1 AND estado = 'activo' ORDER BY nombre_completo");
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $j) $jefesPorEmpresa[(int)$j['empresa_id']][] = $j;
        } else {
            $st = $this->db->prepare("SELECT id, nombre, empresa_id FROM sedes WHERE empresa_id = ? ORDER BY nombre");
            $st->execute([$mi_empresa]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $s) $sedesPorEmpresa[(int)$s['empresa_id']][] = $s;
            $st = $this->db->prepare("SELECT id, nombre_completo, cargo, empresa_id FROM usuarios WHERE empresa_id = ? AND es_jefe = 1 AND estado = 'activo' ORDER BY nombre_completo");
            $st->execute([$mi_empresa]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $j) $jefesPorEmpresa[(int)$j['empresa_id']][] = $j;
        }

        $sedesJs = [];
        foreach ($sedesPorEmpresa as $eid => $arr)
            $sedesJs[$eid] = array_map(fn($s) => ['id' => (int)$s['id'], 'nombre' => $s['nombre']], $arr);
        $jefesJs = [];
        foreach ($jefesPorEmpresa as $eid => $arr)
            $jefesJs[$eid] = array_map(fn($j) => ['id' => (int)$j['id'], 'nombre' => $j['nombre_completo'] . ($j['cargo'] ? ' (' . $j['cargo'] . ')' : '')], $arr);

        $empresa_destino = $mi_empresa;
        $empresas_lista  = $_SESSION['empresas_lista'] ?? [];

        require_once __DIR__ . '/../Views/usuarios/index.php';
    }

    public function store() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /horion-time/public/usuarios'); exit; }
        try {
            // Empresa destino: en modo global se elige en el form; si no, la activa del selector
            $empresa_destino = 0;
            if ($this->esSuper() && $this->modoGlobal()) $empresa_destino = (int)($_POST['empresa_id'] ?? 0);
            if (!$empresa_destino) $empresa_destino = $this->empresaActual();
            if (!$empresa_destino) throw new \Exception('Selecciona o activa una empresa antes de crear el usuario');
            $_POST['empresa_id'] = $empresa_destino;

            // La sucursal DEBE pertenecer a esa empresa (por eso se guarda correcta)
            $sede_id = (int)($_POST['sede_id'] ?? 0);
            if ($sede_id) {
                $q = $this->db->prepare("SELECT empresa_id FROM sedes WHERE id = ?");
                $q->execute([$sede_id]);
                if ((int)$q->fetchColumn() !== $empresa_destino) throw new \Exception('La sucursal elegida no pertenece a la empresa destino');
            }
            $_POST['sede_id'] = $sede_id ?: null;

            // El jefe también debe ser de esa empresa
            $jefe_id = (int)($_POST['jefe_inmediato_id'] ?? 0);
            if ($jefe_id) {
                $q = $this->db->prepare("SELECT empresa_id FROM usuarios WHERE id = ?");
                $q->execute([$jefe_id]);
                if ((int)$q->fetchColumn() !== $empresa_destino) $jefe_id = 0;
            }
            $_POST['jefe_inmediato_id'] = $jefe_id ?: null;

            $_POST['estado'] = $_POST['estado'] ?? 'activo';
            $_POST['es_jefe'] = isset($_POST['es_jefe']) ? 1 : 0;
            $_POST['password_hash'] = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : null;

            $this->model->create($_POST);
            header('Location: /horion-time/public/usuarios?success=1'); exit;
        } catch (\Exception $e) {
            header('Location: /horion-time/public/usuarios?error=' . urlencode($e->getMessage())); exit;
        }
    }

    public function update($id = null) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /horion-time/public/usuarios'); exit; }
        $id = (int)($id ?? ($_POST['id'] ?? 0));
        try {
            $u = $this->model->getById($id);
            if (!$u) throw new \Exception('Usuario no encontrado');
            if (!$this->esSuper() && (int)$u['empresa_id'] !== $this->empresaActual()) {
                throw new \Exception('Sin permiso para editar este usuario');
            }
            // Sucursal y jefe validados contra la empresa del usuario
            $empresa_destino = (int)$u['empresa_id'];
            $sede_id = (int)($_POST['sede_id'] ?? 0);
            if ($sede_id) {
                $q = $this->db->prepare("SELECT empresa_id FROM sedes WHERE id = ?");
                $q->execute([$sede_id]);
                if ((int)$q->fetchColumn() !== $empresa_destino) $sede_id = 0;
            }
            $_POST['sede_id'] = $sede_id ?: null;

            $jefe_id = (int)($_POST['jefe_inmediato_id'] ?? 0);
            if ($jefe_id) {
                $q = $this->db->prepare("SELECT empresa_id FROM usuarios WHERE id = ?");
                $q->execute([$jefe_id]);
                if ((int)$q->fetchColumn() !== $empresa_destino) $jefe_id = 0;
            }
            $_POST['jefe_inmediato_id'] = $jefe_id ?: null;

            $_POST['es_jefe'] = isset($_POST['es_jefe']) ? 1 : 0;
            if (!empty($_POST['password'])) {
                $_POST['password_hash'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
            } else {
                unset($_POST['password_hash']);
            }

            $this->model->update($id, $_POST);
            header('Location: /horion-time/public/usuarios?success=1'); exit;
        } catch (\Exception $e) {
            header('Location: /horion-time/public/usuarios?error=' . urlencode($e->getMessage())); exit;
        }
    }

    public function destroy($id) {
        $id = (int)($id ?? 0);
        try {
            $u = $this->model->getById($id);
            if (!$u) throw new \Exception('Usuario no encontrado');
            if (!$this->esSuper() && (int)$u['empresa_id'] !== $this->empresaActual()) throw new \Exception('Sin permiso');
            $this->model->delete($id);
            header('Location: /horion-time/public/usuarios?deleted=1'); exit;
        } catch (\Exception $e) {
            header('Location: /horion-time/public/usuarios?error=' . urlencode($e->getMessage())); exit;
        }
    }
}