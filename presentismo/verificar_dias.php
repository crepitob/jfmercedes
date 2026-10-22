<?php
// verificar_dias.php
require_once __DIR__ . '/config/database.php';

header('Content-Type: application/json');

$id_empleado = (int)($_GET['id_empleado'] ?? 0);
$id_motivo   = (int)($_GET['id_motivo'] ?? 0);
$anio_actual = date('Y');

// Corregido: Uso de strftime para compatibilidad con SQLite
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

$limite = $resultado ? (int)$resultado['limite_anual'] : 0;
$usados = $resultado ? (int)$resultado['dias_usados'] : 0;
$disponibles = $limite > 0 ? max(0, $limite - $usados) : 999;

echo json_encode([
    'limite_anual' => $limite,
    'dias_usados'  => $usados,
    'disponibles'  => $disponibles
]);