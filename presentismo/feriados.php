<?php
// feriados.php
require_once __DIR__ . '/config/database.php';

$mensaje = '';

// --- CREAR TABLA DE FERIADOS SI NO EXISTE ---
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS feriados (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            fecha DATE NOT NULL UNIQUE,
            descripcion TEXT NOT NULL
        )
    ");
} catch (PDOException $e) {
    $mensaje = '<div class="alert alert-danger">Error de conexión con la base de datos.</div>';
}

// --- GUARDAR NUEVO FERIADO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'guardar_feriado') {
    $fecha       = $_POST['fecha'] ?? '';
    $descripcion = trim($_POST['descripcion'] ?? '');

    if (!empty($fecha) && !empty($descripcion)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO feriados (fecha, descripcion) VALUES (:fecha, :desc)");
            $stmt->execute([':fecha' => $fecha, ':desc' => $descripcion]);
            $mensaje = '<div class="alert alert-success">✅ Feriado/Asueto del <strong>' . date('d/m/Y', strtotime($fecha)) . '</strong> registrado correctamente.</div>';
        } catch (PDOException $e) {
            $mensaje = '<div class="alert alert-warning">Esa fecha ya está registrada como feriado o asueto.</div>';
        }
    } else {
        $mensaje = '<div class="alert alert-warning">Completá la fecha y la descripción.</div>';
    }
}

// --- ELIMINAR FERIADO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'eliminar_feriado') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $pdo->prepare("DELETE FROM feriados WHERE id = :id")->execute([':id' => $id]);
        $mensaje = '<div class="alert alert-info">Feriado eliminado.</div>';
    }
}

// OBTENER TODOS LOS FERIADOS
$feriados = $pdo->query("SELECT * FROM feriados ORDER BY fecha DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Feriados y Asuetos - Presentismo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<?php
$pagina_activa = 'feriados';
include __DIR__ . '/includes/navbar.php';
?>

<div class="container" style="max-width: 800px;">
    <?= $mensaje ?>

    <div class="row g-4">
        <!-- FORMULARIO DE ALTA -->
        <div class="col-md-5">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-bold">📅 Cargar Feriado / Asueto Decreto</div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="guardar_feriado">

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Fecha *</label>
                            <input type="date" name="fecha" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Motivo / Decreto *</label>
                            <input type="text" name="descripcion" class="form-control" placeholder="Ej: Asueto Local / Día del Judicial" required>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Guardar Feriado</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- LISTADO DE FERIADOS -->
        <div class="col-md-7">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-bold">📋 Feriados y Asuetos Registrados</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Fecha</th>
                                    <th>Motivo / Descripción</th>
                                    <th class="text-end pe-3">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($feriados)): ?>
                                    <?php foreach ($feriados as $f): ?>
                                        <tr>
                                            <td class="fw-bold"><?= date('d/m/Y', strtotime($f['fecha'])) ?></td>
                                            <td><?= htmlspecialchars($f['descripcion']) ?></td>
                                            <td class="text-end pe-3">
                                                <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este feriado?');">
                                                    <input type="hidden" name="action" value="eliminar_feriado">
                                                    <input type="hidden" name="id" value="<?= $f['id'] ?>">
                                                    <button type="submit" class="btn btn-outline-danger btn-sm">🗑️</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="text-center py-3 text-muted">No hay feriados o asuetos registrados.</td>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>