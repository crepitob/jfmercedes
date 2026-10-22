<?php
// guardar_ausencia.php
require_once __DIR__ . '/config/database.php';

header('Content-Type: application/json');

$id_empleado      = (int)($_POST['id_empleado'] ?? 0);
$id_motivo        = (int)($_POST['id_motivo'] ?? 0);
$dias_solicitados = (int)($_POST['dias'] ?? 1);
$anio_actual      = date('Y');

$query = "SELECT 
            m.limite_anual,
            m.nombre,
            COALESCE(SUM(
                CASE 
                    WHEN i.fecha_fin IS NOT NULL AND i.fecha_fin != '' 
                    THEN (julianday(i.fecha_fin) - julianday(i.fecha) + 1)
                    ELSE 1 
                END
            ), 0) AS dias_usados
          FROM motivos m
          LEFT JOIN inasistencias i ON i.motivo = m.nombre 
              AND i.empleado_id = :id_empleado 
              AND strftime('%Y', i.fecha) = :anio
          WHERE m.id = :id_motivo
          GROUP BY m.id, m.limite_anual, m.nombre";

$stmt = $pdo->prepare($query);
$stmt->execute([
    ':id_empleado' => $id_empleado,
    ':anio'        => (string)$anio_actual,
    ':id_motivo'   => $id_motivo
]);

$resultado = $stmt->fetch(PDO::FETCH_ASSOC);

if ($resultado && (int)$resultado['limite_anual'] > 0) {
    $limite_anual = (int)$resultado['limite_anual'];
    $dias_usados  = (int)$resultado['dias_usados'];
    $disponibles  = $limite_anual - $dias_usados;

    if ($disponibles <= 0) {
        echo json_encode([
            'status'  => 'error',
            'message' => 'El empleado no posee más días disponibles para este artículo durante el presente año.'
        ]);
        exit;
    }

    if ($dias_solicitados > $disponibles) {
        echo json_encode([
            'status'  => 'error',
            'message' => "El artículo tiene un tope de $limite_anual días al año. Al empleado solo le quedan $disponibles días disponibles."
        ]);
        exit;
    }
}

echo json_encode(['status' => 'success', 'message' => 'Validación correcta.']);