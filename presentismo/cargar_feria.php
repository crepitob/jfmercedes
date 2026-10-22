<?php
// cargar_feria.php
require_once __DIR__ . '/config/database.php';

$mensaje = '';

// --- CREAR TABLA SI NO EXISTE ---
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS movimientos_feria (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            empleado_id INTEGER NOT NULL,
            tipo TEXT NOT NULL,
            dias INTEGER NOT NULL,
            periodo TEXT,
            concepto TEXT,
            fecha_carga DATE DEFAULT (date('now')),
            FOREIGN KEY (empleado_id) REFERENCES empleados(id)
        )
    ");
} catch (PDOException $e) {
    $mensaje = '<div class="alert alert-danger">Error en base de datos.</div>';
}

// --- PROCESAR REGISTRO DE DÍAS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'guardar_movimiento') {
    $empleado_id  = (int)($_POST['empleado_id'] ?? 0);
    $tipo         = $_POST['tipo'] ?? 'SUMAR';
    $periodo_tipo = $_POST['periodo_tipo'] ?? 'Enero';
    $periodo_anio = $_POST['periodo_anio'] ?? date('Y');
    $dias         = (int)($_POST['dias'] ?? 0);
    $concepto     = trim($_POST['concepto'] ?? '');

    $periodo = "Feria " . $periodo_tipo . " " . $periodo_anio;
    $tipoTexto = ($periodo_tipo === 'Enero') ? 'días corridos' : 'días hábiles';

    if ($empleado_id > 0 && $dias > 0) {
        $stmt = $pdo->prepare("
            INSERT INTO movimientos_feria (empleado_id, tipo, dias, periodo, concepto) 
            VALUES (:emp, :tipo, :dias, :per, :con)
        ");
        $stmt->execute([
            ':emp'   => $empleado_id,
            ':tipo'  => $tipo,
            ':dias'  => $dias,
            ':per'   => $periodo,
            ':con'   => $concepto ?: ("Guardia de feria (" . $dias . " " . $tipoTexto . ")")
        ]);

        $mensaje = '<div class="alert alert-success">✅ Movimiento de feria registrado con éxito: <strong>+' . $dias . ' ' . $tipoTexto . '</strong> acreditados para ' . htmlspecialchars($periodo) . '.</div>';
    } else {
        $mensaje = '<div class="alert alert-warning">Completá todos los campos obligatorios y asegurate de que la cantidad de días sea mayor a 0.</div>';
    }
}

// ELIMINAR MOVIMIENTO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'eliminar_movimiento') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $pdo->prepare("DELETE FROM movimientos_feria WHERE id = :id")->execute([':id' => $id]);
        $mensaje = '<div class="alert alert-info">Movimiento eliminado.</div>';
    }
}

// OBTENER AGENTES
$empleados = $pdo->query("SELECT id, apellido, nombre, legajo FROM empleados WHERE COALESCE(activo, 1) = 1 ORDER BY apellido, nombre")->fetchAll(PDO::FETCH_ASSOC);

// HISTORIAL DE MOVIMIENTOS
$movimientos = $pdo->query("
    SELECT m.*, e.apellido, e.nombre 
    FROM movimientos_feria m 
    JOIN empleados e ON m.empleado_id = e.id 
    ORDER BY m.id DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cargar Feria - Control de Presentismo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<?php
$pagina_activa = 'cargar_feria';
include __DIR__ . '/includes/navbar.php';
?>

<div class="container">
    <?= $mensaje ?>

    <div class="row g-4">
        <!-- FORMULARIO DE CARGA DE DÍAS -->
        <div class="col-md-5">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-bold">⚖️ Cargar Días de Guardia por Cantidad</div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="guardar_movimiento">

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Agente *</label>
                            <select name="empleado_id" class="form-select" required>
                                <option value="">-- Seleccionar Agente --</option>
                                <?php foreach ($empleados as $emp): ?>
                                    <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['apellido'] . ', ' . $emp['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Feria *</label>
                                <select name="periodo_tipo" id="periodo_tipo" class="form-select" required onchange="actualizarEtiquetaDias()">
                                    <option value="Enero">Enero (Corridos)</option>
                                    <option value="Julio">Julio (Hábiles)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Año *</label>
                                <input type="number" name="periodo_anio" class="form-control" value="<?= date('Y') ?>" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold" id="label_dias">Cantidad de Días Corridos *</label>
                            <input type="number" name="dias" id="dias" class="form-control" min="1" placeholder="Ej: 5" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Observaciones / Concepto</label>
                            <input type="text" name="concepto" class="form-control" placeholder="Ej: Cobertura de guardia extraordinaria">
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Guardar Guardia de Feria</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- HISTORIAL DE MOVIMIENTOS -->
        <div class="col-md-7">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-bold">📋 Días de Feria Registrados</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Agente</th>
                                    <th>Período</th>
                                    <th>Días Acreditados</th>
                                    <th>Concepto</th>
                                    <th class="text-end pe-3">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($movimientos)): ?>
                                    <?php foreach ($movimientos as $m): ?>
                                        <tr>
                                            <td class="fw-semibold"><?= htmlspecialchars($m['apellido'] . ', ' . $m['nombre']) ?></td>
                                            <td><span class="badge bg-primary"><?= htmlspecialchars($m['periodo'] ?: '-') ?></span></td>
                                            <td>
                                                <?php if ($m['tipo'] === 'SUMAR'): ?>
                                                    <span class="fw-bold text-success">+<?= $m['dias'] ?> días</span>
                                                <?php else: ?>
                                                    <span class="fw-bold text-danger">-<?= $m['dias'] ?> días</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="small text-muted"><?= htmlspecialchars($m['concepto'] ?: '-') ?></td>
                                            <td class="text-end pe-3">
                                                <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este registro?');">
                                                    <input type="hidden" name="action" value="eliminar_movimiento">
                                                    <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                                    <button type="submit" class="btn btn-outline-danger btn-sm">🗑️</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-3 text-muted">No hay movimientos registrados.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function actualizarEtiquetaDias() {
    const tipoFeria = document.getElementById('periodo_tipo').value;
    const labelDias = document.getElementById('label_dias');

    if (tipoFeria === 'Enero') {
        labelDias.innerHTML = 'Cantidad de Días Corridos *';
    } else {
        labelDias.innerHTML = 'Cantidad de Días Hábiles *';
    }
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>