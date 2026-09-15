<?php
namespace App\Controllers;
use PDO;

class ConfiguracionController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function index() {
        $empresa_id = (int)($_SESSION['empresa_id'] ?? 1);
        $empresa = [];
        try {
            $st = $this->db->prepare("SELECT * FROM empresas WHERE id = ?");
            $st->execute([$empresa_id]);
            $empresa = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {}
        require_once __DIR__ . '/../Views/configuracion/index.php';
    }

    public function guardar() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /horion-time/public/configuracion'); exit; }
        try {
            $empresa_id = (int)($_POST['empresa_id'] ?? ($_SESSION['empresa_id'] ?? 1));
            $cols = $this->db->query("SHOW COLUMNS FROM empresas")->fetchAll(PDO::FETCH_COLUMN);
            // Checkbox: si no viene, es 0
            $_POST['resumen_diario_email'] = isset($_POST['resumen_diario_email']) ? 1 : 0;
            $_POST['alertas_tardanza']     = isset($_POST['alertas_tardanza'])     ? 1 : 0;
            $_POST['alertas_ausencia']     = isset($_POST['alertas_ausencia'])     ? 1 : 0;
            // Logo (opcional)
            if (!empty($_FILES['logo']['name'])) {
                $f = $_FILES['logo'];
                if (!in_array($f['type'], ['image/png','image/jpeg'])) throw new \Exception('El logo debe ser PNG o JPG');
                if ($f['size'] > 2 * 1024 * 1024) throw new \Exception('El logo no puede superar 2MB');
                $dir = __DIR__ . '/../../public/uploads/empresas/';
                if (!is_dir($dir)) mkdir($dir, 0777, true);
                $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
                $nombre = 'logo_' . $empresa_id . '_' . time() . '.' . $ext;
                if (move_uploaded_file($f['tmp_name'], $dir . $nombre)) {
                    $_POST['logo_url'] = '/horion-time/public/uploads/empresas/' . $nombre;
                }
            }
            // Solo columnas existentes
            $data = array_intersect_key($_POST, array_flip($cols));
            unset($data['id']);
            if (!$data) throw new \Exception('Nada que actualizar');
            $sets = implode(', ', array_map(fn($k) => "`$k` = :$k", array_keys($data)));
            $st = $this->db->prepare("UPDATE empresas SET $sets WHERE id = :id");
            foreach ($data as $k => $v) $st->bindValue(":$k", $v);
            $st->bindValue(':id', $empresa_id);
            $st->execute();
            header('Location: /horion-time/public/configuracion?success=1'); exit;
        } catch (\Exception $e) {
            header('Location: /horion-time/public/configuracion?error=' . urlencode($e->getMessage())); exit;
        }
    }
}