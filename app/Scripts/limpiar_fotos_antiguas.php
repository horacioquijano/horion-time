<?php
/**
 * Script de limpieza de fotos antiguas (> 60 días)
 * Ejecutar vía CRON diariamente
 */

require_once __DIR__ . '/../../vendor/autoload.php';

// Conexión a la BD
try {
    $db = \App\Config\Database::getInstance()->getConnection();
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Directorio de fotos
$directorio = __DIR__ . '/../../public/uploads/marcaciones/';

if (!is_dir($directorio)) {
    echo "El directorio no existe: $directorio\n";
    exit(1);
}

// Fecha límite (60 días atrás)
$fecha_limite = strtotime('-60 days');
$fecha_limite_str = date('Y-m-d', $fecha_limite);

echo "=== Limpieza de fotos antiguas ===\n";
echo "Fecha límite: $fecha_limite_str\n\n";

// 1. Obtener fotos que aún están en uso (marcaciones recientes)
$stmt = $db->prepare("SELECT foto_evidencia FROM registros_asistencia WHERE foto_evidencia IS NOT NULL AND foto_evidencia != '' AND fecha >= ?");
$stmt->execute([$fecha_limite_str]);
$fotos_en_uso = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $fotos_en_uso[] = basename($row['foto_evidencia']);
}

echo "Fotos en uso (últimos 60 días): " . count($fotos_en_uso) . "\n";

// 2. Recorrer archivos físicos
$eliminados = 0;
$espacio_liberado = 0;

foreach (glob($directorio . '*.jpg') as $archivo) {
    $nombre_archivo = basename($archivo);
    $fecha_modificacion = filemtime($archivo);
    
    // Si el archivo tiene más de 60 días Y no está en uso
    if ($fecha_modificacion < $fecha_limite && !in_array($nombre_archivo, $fotos_en_uso)) {
        $tamano = filesize($archivo);
        
        if (unlink($archivo)) {
            $eliminados++;
            $espacio_liberado += $tamano;
            echo "✅ Eliminado: $nombre_archivo (" . number_format($tamano / 1024, 2) . " KB)\n";
        } else {
            echo "❌ Error al eliminar: $nombre_archivo\n";
        }
    }
}

echo "\n=== Resumen ===\n";
echo "Archivos eliminados: $eliminados\n";
echo "Espacio liberado: " . number_format($espacio_liberado / 1024 / 1024, 2) . " MB\n";

// 3. Limpiar referencias en la BD (fotos que ya no existen)
$stmt = $db->prepare("SELECT id, foto_evidencia FROM registros_asistencia WHERE foto_evidencia IS NOT NULL AND foto_evidencia != ''");
$stmt->execute();
$limpiados = 0;

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $ruta_completa = __DIR__ . '/../../public' . $row['foto_evidencia'];
    if (!file_exists($ruta_completa)) {
        $db->prepare("UPDATE registros_asistencia SET foto_evidencia = NULL WHERE id = ?")->execute([$row['id']]);
        $limpiados++;
    }
}

echo "Referencias BD limpiadas: $limpiados\n";
