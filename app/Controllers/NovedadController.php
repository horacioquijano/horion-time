<?php
namespace App\Controllers;
use PDO;

class NovedadController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    private function esSuper()       { return (($_SESSION['rol_nombre'] ?? '') === 'SuperAdmin'); }
    private function modoGlobal()    { return (bool)($_SESSION['modo_global'] ?? false); }
    private function empresaActual() { return (int)($_SESSION['empresa_id'] ?? 0); }

    /** Normaliza claves para que las vistas nunca den warnings */
    private function normalizarFilas(array $rows): array {
        foreach ($rows as &$n) {
            $n['empleado_nombre']   = $n['empleado_nombre']   ?? $n['nombre_completo'] ?? $n['usuario_nombre'] ?? 'Sin nombre';
            $n['usuario_nombre']    = $n['usuario_nombre']    ?? $n['nombre_completo'] ?? 'Sin nombre';
            $n['empleado_id']       = $n['empleado_id']       ?? $n['usuario_id'] ?? null;
            $n['empleado_documento']= $n['empleado_documento']?? $n['identificacion'] ?? '';
            $n['estado']            = $n['estado'] ?? 'pendiente';
            $n['tipo']              = $n['tipo'] ?? '';
            $n['subtipo']           = $n['subtipo'] ?? null;
            $n['fecha_inicio']      = $n['fecha_inicio'] ?? null;
            $n['fecha_fin']         = $n['fecha_fin'] ?? null;
            $n['dias']              = $n['dias'] ?? (($n['fecha_inicio'] && $n['fecha_fin'])
                                    ? max(1, (int)round((strtotime($n['fecha_fin']) - strtotime($n['fecha_inicio'])) / 86400) + 1) : 1);
            $n['horas']             = $n['horas'] ?? $n['horas_solicitadas'] ?? null;
            $n['solicitado']        = $n['solicitado'] ?? $n['fecha_creacion'] ?? $n['created_at'] ?? null;
            $n['justificacion']     = $n['justificacion'] ?? $n['motivo'] ?? $n['descripcion'] ?? '';
            $n['motivo']            = $n['motivo'] ?? $n['justificacion'] ?? $n['descripcion'] ?? '';
            $n['descripcion']       = $n['descripcion'] ?? $n['justificacion'] ?? '';
            $n['documento']         = $n['documento'] ?? $n['documento_ruta'] ?? $n['archivo'] ?? $n['archivo_ruta'] ?? $n['soporte'] ?? null;
            $n['archivo']           = $n['archivo'] ?? $n['documento'] ?? null;
            $n['empresa_nombre']    = $n['empresa_nombre'] ?? '';
        }
        unset($n);
        return $rows;
    }

    /** Listado + resumen */
    public function index() {
        $es_super    = $this->esSuper();
        $modo_global = $this->modoGlobal();
        $mi_empresa  = $this->empresaActual();
        try {
            if ($es_super && $modo_global) {
                $st = $this->db->query("
                    SELECT n.*, u.nombre_completo, u.identificacion, e.nombre AS empresa_nombre
                    FROM novedades n
                    LEFT JOIN usuarios u ON n.usuario_id = u.id
                    LEFT JOIN empresas e ON n.empresa_id = e.id
                    ORDER BY n.fecha_inicio DESC LIMIT 200");
            } else {
                $st = $this->db->prepare("
                    SELECT n.*, u.nombre_completo, u.identificacion, e.nombre AS empresa_nombre
                    FROM novedades n
                    LEFT JOIN usuarios u ON n.usuario_id = u.id
                    LEFT JOIN empresas e ON n.empresa_id = e.id
                    WHERE n.empresa_id = :eid
                    ORDER BY n.fecha_inicio DESC LIMIT 200");
                $st->execute([':eid' => $mi_empresa ?: 1]);
            }
            $novedades = $this->normalizarFilas($st->fetchAll(PDO::FETCH_ASSOC));
        } catch (\Throwable $e) {
            $novedades = [];
        }

        $counts = ['pendiente' => 0, 'aprobada' => 0, 'rechazada' => 0];
        foreach ($novedades as $n) {
            $e = $n['estado'] ?? 'pendiente';
            if (isset($counts[$e])) $counts[$e]++;
        }
        $resumen = [
            'total' => count($novedades),
            'pendientes' => $counts['pendiente'], 'aprobadas' => $counts['aprobada'], 'rechazadas' => $counts['rechazada'],
            'pendiente' => $counts['pendiente'], 'aprobada' => $counts['aprobada'], 'rechazada' => $counts['rechazada'],
            'total_pendientes' => $counts['pendiente'], 'total_aprobadas' => $counts['aprobada'], 'total_rechazadas' => $counts['rechazada'],
        ];

        $tipos = self::tiposNovedad();
        require_once __DIR__ . '/../Views/novedades/index.php';
    }

    public static function tiposNovedad() {
        return [
            'incapacidad'    => '🏥 Incapacidad Médica',
            'vacaciones'     => '🌴 Vacaciones',
            'licencia'       => '📄 Licencia Remunerada',
            'permiso'        => '⏱️ Permiso Personal',
            'calamidad'      => '🆘 Calamidad Doméstica',
            'trabajo_remoto' => '💻 Trabajo Remoto',
            'otra'           => '📌 Otra',
        ];
    }

    /** ===== DETALLE: página HTML o JSON según cómo lo pida la vista ===== */
    public function detalle($id = null) {
        $id = (int)($id ?? ($_GET['id'] ?? 0));
        $novedad = null;
        try {
            $st = $this->db->prepare("
                SELECT n.*, u.nombre_completo, u.identificacion, u.email AS empleado_email,
                       u.cargo AS empleado_cargo, e.nombre AS empresa_nombre
                FROM novedades n
                LEFT JOIN usuarios u ON n.usuario_id = u.id
                LEFT JOIN empresas e ON n.empresa_id = e.id
                WHERE n.id = :id");
            $st->execute([':id' => $id]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if ($row) $novedad = $this->normalizarFilas([$row])[0];
        } catch (\Throwable $e) {}

        $quiereJson = stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false
            || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'
            || (($_GET['format'] ?? '') === 'json');

        if ($quiereJson) {
            header('Content-Type: application/json');
            echo json_encode($novedad ?: ['error' => 'Novedad no encontrada']);
            exit;
        }

        if (!$novedad) {
            header('Location: /horion-time/public/novedades?error=' . urlencode('Novedad no encontrada'));
            exit;
        }
        $tipos = self::tiposNovedad();
        require_once __DIR__ . '/../Views/novedades/detalle.php';
    }

    /** Alias por si la vista/enlace usa /ver/ o /show/ */
    public function ver($id = null)   { $this->detalle($id); }
    public function show($id = null)  { $this->detalle($id); }

    /** Recibe el POST del formulario */
    public function store() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { $this->guardar(); return; }
        header('Location: /horion-time/public/novedades/solicitar'); exit;
    }

    public function solicitar() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { $this->guardar(); return; }
        $tipos = self::tiposNovedad();
        require_once __DIR__ . '/../Views/novedades/solicitar.php';
    }

    private function guardar() {
        try {
            $tipo = trim($_POST['tipo'] ?? '');
            $fi   = $_POST['fecha_inicio'] ?? '';
            $ff   = $_POST['fecha_fin'] ?? '';
            $just = trim($_POST['justificacion'] ?? $_POST['motivo'] ?? '');

            if ($tipo === '' || $fi === '' || $ff === '' || $just === '') {
                throw new \Exception('Completa tipo, fechas y justificación');
            }
            if (strtotime($ff) < strtotime($fi)) {
                throw new \Exception('La fecha fin no puede ser anterior a la fecha inicio');
            }

            $doc_ruta = null;
            $f = null;
            if (!empty($_FILES['soporte']['name']))        $f = $_FILES['soporte'];
            elseif (!empty($_FILES['documento']['name'])) $f = $_FILES['documento'];
            if ($f) {
                $okTypes = ['application/pdf', 'image/png', 'image/jpeg'];
                if (!in_array($f['type'], $okTypes)) throw new \Exception('El documento debe ser PDF o imagen');
                if ($f['size'] > 5 * 1024 * 1024) throw new \Exception('El documento supera 5MB');
                $dir = __DIR__ . '/../../public/uploads/novedades/';
                if (!is_dir($dir)) mkdir($dir, 0777, true);
                $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
                $nombre = 'novedad_' . ($_SESSION['usuario_id'] ?? 0) . '_' . date('Ymd_His') . '.' . $ext;
                if (!move_uploaded_file($f['tmp_name'], $dir . $nombre)) throw new \Exception('No se pudo guardar el documento');
                $doc_ruta = '/horion-time/public/uploads/novedades/' . $nombre;
            }

            $cols = $this->db->query("SHOW COLUMNS FROM novedades")->fetchAll(PDO::FETCH_COLUMN);
            $pick = function (array $cands) use ($cols) {
                foreach ($cands as $c) if (in_array($c, $cols)) return $c;
                return null;
            };

            $data = [
                'empresa_id'   => (int)($_POST['empresa_id'] ?? $_SESSION['empresa_id'] ?? 1),
                'usuario_id'   => (int)($_POST['usuario_id'] ?? $_SESSION['usuario_id'] ?? 1),
                'tipo'         => $tipo,
                'fecha_inicio' => $fi,
                'fecha_fin'    => $ff,
            ];
            if ($c = $pick(['subtipo']))                              $data[$c] = trim($_POST['subtipo'] ?? '') ?: null;
            if ($c = $pick(['justificacion','motivo','descripcion'])) $data[$c] = $just;
            if ($c = $pick(['horas_solicitadas','horas']))            $data[$c] = !empty($_POST['horas_solicitadas']) ? (float)$_POST['horas_solicitadas'] : null;
            if ($c = $pick(['documento','documento_ruta','archivo','archivo_ruta','soporte'])) $data[$c] = $doc_ruta;
            if ($c = $pick(['estado']))                               $data[$c] = 'pendiente';
            if ($c = $pick(['solicitado_por','creado_por']))          $data[$c] = (int)($_SESSION['usuario_id'] ?? 1);
            $data = array_intersect_key($data, array_flip($cols));

            $campos = implode(', ', array_map(fn($k) => "`$k`", array_keys($data)));
            $marks  = implode(', ', array_map(fn($k) => ":$k", array_keys($data)));
            $st = $this->db->prepare("INSERT INTO novedades ($campos) VALUES ($marks)");
            foreach ($data as $k => $v) $st->bindValue(":$k", $v);
            $st->execute();

            header('Location: /horion-time/public/novedades?success=1'); exit;
        } catch (\Exception $e) {
            header('Location: /horion-time/public/novedades/solicitar?error=' . urlencode($e->getMessage())); exit;
        }
    }

    public function aprobar($id = null)   { $this->cambiarEstado((int)($id ?? 0), 'aprobada'); }
    public function rechazar($id = null)  { $this->cambiarEstado((int)($id ?? 0), 'rechazada'); }

    private function cambiarEstado($id, $estado) {
        $permitidos = ['SuperAdmin', 'Admin_Empresa', 'RRHH'];
        if (!in_array($_SESSION['rol_nombre'] ?? '', $permitidos, true)) {
            header('Location: /horion-time/public/novedades?error=' . urlencode('Sin permiso para aprobar novedades')); exit;
        }
        try {
            $st = $this->db->prepare("UPDATE novedades SET estado = :e WHERE id = :id");
            $st->execute([':e' => $estado, ':id' => $id]);
            header('Location: /horion-time/public/novedades?success=1'); exit;
        } catch (\Exception $e) {
            header('Location: /horion-time/public/novedades?error=' . urlencode($e->getMessage())); exit;
        }
    }
}