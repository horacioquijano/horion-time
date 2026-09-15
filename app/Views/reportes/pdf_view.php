<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte <?= ucfirst($tipo) ?></title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; color: #333; margin: 20px; }
        h1 { color: #03a950; border-bottom: 2px solid #03a950; padding-bottom: 10px; }
        .info { margin-bottom: 20px; color: #666; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #03a950; color: white; font-weight: bold; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; background: #03a950; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 14px;">
            🖨️ Imprimir / Guardar como PDF
        </button>
        <a href="/horion-time/public/reportes" style="margin-left: 10px; color: #03a950;">← Volver</a>
    </div>

    <h1>Reporte de <?= ucfirst(str_replace('_', ' ', $tipo)) ?></h1>
    <div class="info">
        <p><strong>Empresa:</strong> <?= $_SESSION['empresa_nombre'] ?? 'Horion Time' ?></p>
        <p><strong>Período:</strong> <?= $filters['fecha_inicio'] ?> al <?= $filters['fecha_fin'] ?></p>
        <p><strong>Generado:</strong> <?= date('d/m/Y H:i:s') ?> por <?= $_SESSION['nombre_completo'] ?? 'Sistema' ?></p>
        <p><strong>Total registros:</strong> <?= count($data) ?></p>
    </div>

    <table>
        <thead>
            <tr>
                <?php if (!empty($data)): ?>
                <?php foreach (array_keys($data[0]) as $col): ?>
                    <th><?= ucfirst(str_replace('_', ' ', $col)) ?></th>
                <?php endforeach; ?>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($data as $row): ?>
            <tr>
                <?php foreach ($row as $val): ?>
                    <td><?= htmlspecialchars($val) ?></td>
                <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>