<?php
// Incluimos la conexión existente a la base de datos
require_once __DIR__ . '/config/database.php';

try {
    // Iniciamos una transacción: si algo falla, no se modifica nada
    $pdo->beginTransaction();

    // 1. Normalizar legajos antes de la migración para no romper la restricción UNIQUE
    // Si hay legajos vacíos o repetidos (por ejemplo, legajo == dni), se asigna 'LEG-' + ID[cite: 4]
    $empleados = $pdo->query("SELECT id, dni, legajo FROM empleados")->fetchAll(PDO::FETCH_ASSOC);
    $legajosVistos = [];

    foreach ($empleados as $emp) {
        $legajoActual = trim($emp['legajo'] ?? '');
        
        // Si el legajo está vacío o ya fue visto en otro registro, generamos uno único provisorio
        if (empty($legajoActual) || in_array($legajoActual, $legajosVistos)) {
            $nuevoLegajo = 'LEG-' . $emp['id'];
            $stmtUpdate = $pdo->prepare("UPDATE empleados SET legajo = :legajo WHERE id = :id");
            $stmtUpdate->execute([':legajo' => $nuevoLegajo, ':id' => $emp['id']]);
            $legajosVistos[] = $nuevoLegajo;
        } else {
            $legajosVistos[] = $legajoActual;
        }
    }

    // 2. Crear una nueva tabla con la estructura correcta y restricciones UNIQUE definitivas
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS empleados_nueva (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            legajo TEXT UNIQUE NOT NULL,
            dni TEXT UNIQUE NOT NULL,
            apellido TEXT NOT NULL,
            nombre TEXT NOT NULL,
            cargo TEXT,
            activo INTEGER DEFAULT 1
        );
    ");

    // 3. Copiar todos los datos de la tabla vieja a la nueva tabla
    $pdo->exec("
        INSERT INTO empleados_nueva (id, legajo, dni, apellido, nombre, cargo, activo)
        SELECT id, legajo, dni, apellido, nombre, cargo, activo FROM empleados;
    ");

    // 4. Eliminar la tabla antigua y renombrar la nueva
    $pdo->exec("DROP TABLE empleados;");
    $pdo->exec("ALTER TABLE empleados_nueva RENAME TO empleados;");

    // Confirmar cambios en la base de datos
    $pdo->commit();

    echo "<div style='font-family: sans-serif; padding: 20px; background-color: #d4edda; color: #155724; border-radius: 5px;'>";
    echo "<h2>✅ Base de datos actualizada con éxito</h2>";
    echo "<p>Todos tus empleados mantuvieron sus datos. Los legajos vacíos o duplicados se actualizaron provisoriamente como <code>LEG-[ID]</code> para cumplir con la regla de unicidad.</p>";
    echo "<p><strong>Por seguridad, ahora podés borrar este archivo (<code>migrar_bd.php</code>).</strong></p>";
    echo "</div>";

} catch (Exception $e) {
    // Si ocurre un error, revertimos todos los cambios
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "<div style='font-family: sans-serif; padding: 20px; background-color: #f8d7da; color: #721c24; border-radius: 5px;'>";
    echo "<h2>❌ Error durante la migración</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}