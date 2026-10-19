<?php
namespace App\Controllers;

class MantenimientoController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function limpiarFotos() {
        // Solo permitir desde localhost o con token secreto
        $token = $_GET['token'] ?? '';
        if ($token !== 'TU_TOKEN_SECRETO_AQUI') {
            http_response_code(403);
            echo json_encode(['error' => 'No autorizado']);
            exit;
        }

        $directorio = __DIR__ . '/../../public/uploads/marcaciones/';
        $fecha_limite = strtotime('-60 days');
        $fecha_limite_str = date('Y-m-d', $fecha_limite);

        // Obtener fotos en uso
        $stmt = $this->db->prepare("SELECT foto_evidencia FROM registros_asistencia WHERE foto_evidencia IS NOT NULL AND foto_evidencia != '' AND fecha >= ?");
        $stmt->execute([$fecha_limite_str]);
        $fotos_en_uso = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $fotos_en_uso[] = basename($row['foto_evidencia']);
        }

        $eliminados = 0;
        foreach (glob($directorio . '*.jpg') as $archivo) {
            $nombre_archivo = basename($archivo);
            if (filemtime($archivo) < $fecha_limite && !in_array($nombre_archivo, $fotos_en_uso)) {
                if (unlink($archivo)) $eliminados++;
            }
        }

        // Limpiar referencias BD
        $stmt = $this->db->prepare("SELECT id, foto_evidencia FROM registros_asistencia WHERE foto_evidencia IS NOT NULL AND foto_evidencia != ''");
        $stmt->execute();
        $limpiados = 0;
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $ruta_completa = __DIR__ . '/../../public' . $row['foto_evidencia'];
            if (!file_exists($ruta_completa)) {
                $this->db->prepare("UPDATE registros_asistencia SET foto_evidencia = NULL WHERE id = ?")->execute([$row['id']]);
                $limpiados++;
            }
        }

        echo json_encode([
            'success' => true,
            'eliminados' => $eliminados,
            'referencias_limpiadas' => $limpiados
        ]);
    }
}
