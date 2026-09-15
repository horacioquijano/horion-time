<?php
namespace App\Controllers\Api;
use App\Middleware\ApiAuth;
use App\Models\AsistenciaModel;

class AsistenciaApiController {
    private $db;
    private $model;

    public function __construct($db) {
        $this->db = $db;
        $this->model = new AsistenciaModel($db);
    }

    /**
     * POST /api/auth/registro-marcacion
     * Permite marcación desde app móvil o kiosco externo
     */
    public function registrarMarcacion($auth) {
        $data = json_decode(file_get_contents('php://input'), true);

        // Validaciones
        if (empty($data['tipo_marcacion']) || !in_array($data['tipo_marcacion'], ['entrada', 'salida_almuerzo', 'regreso_almuerzo', 'salida'])) {
            ApiAuth::sendError(400, 'tipo_marcacion inválido');
        }

        // Validar geolocalización si la empresa lo requiere
        $stmt = $this->db->prepare("SELECT requerir_geolocalizacion, radio_gps_metros FROM configuracion_empresa WHERE empresa_id = :eid");
        $stmt->execute([':eid' => $auth['empresa_id']]);
        $config = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($config && $config['requerir_geolocalizacion'] && empty($data['lat'])) {
            ApiAuth::sendError(400, 'Geolocalización requerida por política de empresa');
        }

        // Registrar marcación
        $id = $this->model->registrarMarcacion([
            'usuario_id' => $auth['usuario_id'],
            'empresa_id' => $auth['empresa_id'],
            'sede_id' => $data['sede_id'] ?? null,
            'fecha' => date('Y-m-d'),
            'tipo_marcacion' => $data['tipo_marcacion'],
            'hora_registro' => date('H:i:s'),
            'metodo_marcacion' => $data['metodo'] ?? 'api',
            'foto_evidencia' => $data['foto'] ?? null,
            'lat' => $data['lat'] ?? null,
            'lng' => $data['lng'] ?? null,
            'estado' => 'validado',
            'observaciones' => $data['observaciones'] ?? 'Registro vía API'
        ]);

        echo json_encode([
            'success' => true,
            'data' => [
                'id' => $id,
                'mensaje' => 'Marcación registrada exitosamente',
                'hora' => date('H:i:s'),
                'fecha' => date('Y-m-d')
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * GET /api/usuarios/{id}/horion-time/public/asistencia
     */
    public function getAsistenciaUsuario($auth, $usuarioId) {
        // Validar permisos: solo puede ver su propia asistencia a menos que sea admin
        if ($auth['usuario_id'] != $usuarioId && !in_array($auth['rol'], ['SuperAdmin', 'Admin_Empresa', 'RRHH'])) {
            ApiAuth::sendError(403, 'No tienes permisos para ver esta asistencia');
        }

        $fechaInicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
        $fechaFin = $_GET['fecha_fin'] ?? date('Y-m-d');

        $stmt = $this->db->prepare("
            SELECT fecha, tipo_marcacion, hora_registro, metodo_marcacion, estado,
                   lat, lng, foto_evidencia
            FROM registros_asistencia
            WHERE usuario_id = :uid AND fecha BETWEEN :fi AND :ff
            ORDER BY fecha DESC, hora_registro DESC
        ");
        $stmt->execute([':uid' => $usuarioId, ':fi' => $fechaInicio, ':ff' => $fechaFin]);
        $registros = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => [
                'usuario_id' => $usuarioId,
                'periodo' => ['inicio' => $fechaInicio, 'fin' => $fechaFin],
                'total_registros' => count($registros),
                'registros' => $registros
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}