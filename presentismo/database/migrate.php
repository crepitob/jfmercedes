<?php
// database/migrate.php
require_once __DIR__ . '/../config/database.php';

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS migrations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            migration TEXT UNIQUE NOT NULL,
            executed_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
    ");

    $ejecutadas = $pdo->query("SELECT migration FROM migrations")->fetchAll(PDO::FETCH_COLUMN);

    $dir = __DIR__ . '/migrations';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $archivos = glob($dir . '/*.php');
    sort($archivos);

    $nuevasMigraciones = 0;

    foreach ($archivos as $archivo) {
        $nombreMigracion = basename($archivo);

        if (!in_array($nombreMigracion, $ejecutadas)) {
            echo "Aplicando migración: <strong>{$nombreMigracion}</strong>... ";

            $pdo->beginTransaction();

            $sqlQueries = require $archivo;
            
            if (is_callable($sqlQueries)) {
                $sqlQueries($pdo);
            } elseif (is_string($sqlQueries)) {
                $pdo->exec($sqlQueries);
            }

            $stmt = $pdo->prepare("INSERT INTO migrations (migration) VALUES (:migration)");
            $stmt->execute([':migration' => $nombreMigracion]);

            $pdo->commit();

            echo "<span style='color:green;'>[OK]</span><br>";
            $nuevasMigraciones++;
        }
    }

    if ($nuevasMigraciones === 0) {
        echo "<p style='color:blue;'>La base de datos ya está actualizada. No hay migraciones pendientes.</p>";
    } else {
        echo "<p style='color:green;'><strong>¡Se aplicaron {$nuevasMigraciones} migraciones con éxito!</strong></p>";
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "<br><span style='color:red;'><strong>Error en la migración:</strong> " . htmlspecialchars($e->getMessage()) . "</span>";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
    <a class="btn btn-primary" href="../index.php">Volver al inicio</a>
</body>
</html>