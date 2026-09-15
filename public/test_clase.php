<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🧪 Test de Carga de Clase</h2>";
echo "<pre>";

// Test 1: Verificar que el archivo existe
$file = __DIR__ . '/../app/Controllers/DashboardController.php';
echo "1. Archivo existe: " . (file_exists($file) ? '✅ SÍ' : ' NO') . "\n";
echo "   Ruta: $file\n\n";

// Test 2: Leer el contenido del archivo
echo "2. Primeras 10 líneas del archivo:\n";
$lines = file($file);
for ($i = 0; $i < min(10, count($lines)); $i++) {
    echo "   Línea " . ($i+1) . ": " . htmlspecialchars($lines[$i]) . "\n";
}
echo "\n";

// Test 3: Intentar cargar manualmente
echo "3. Intentando require_once...\n";
try {
    require_once $file;
    echo "   ✅ Archivo cargado exitosamente\n\n";
} catch (Exception $e) {
    echo "   ❌ Error al cargar: " . $e->getMessage() . "\n\n";
}

// Test 4: Verificar que la clase existe
echo "4. Verificando si la clase existe...\n";
if (class_exists('App\\Controllers\\DashboardController')) {
    echo "   ✅ La clase App\\Controllers\\DashboardController existe\n";
} else {
    echo "   ❌ La clase NO existe\n";
    echo "   Clases cargadas: " . implode(', ', get_declared_classes()) . "\n";
}

echo "</pre>";