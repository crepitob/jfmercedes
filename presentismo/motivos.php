<?php
// motivos.php
require_once __DIR__ . '/config/database.php';

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'guardar') {
    $nombre       = trim($_POST['nombre'] ?? '');
    $limite_anual = (int)($_POST['limite_anual'] ?? 0);

    if (!empty($nombre)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO motivos (nombre, limite_anual) VALUES (:nombre, :limite_anual)");
            $stmt->execute([
                ':nombre'       => $nombre,
                ':limite_anual' => $limite_anual
            ]);
            $mensaje = '<div class="alert alert-success">Motivo agregado correctamente.</div>';
        } catch (PDOException $e) {
            $mensaje = '<div class="alert alert-warning">El motivo ya existe o no se pudo guardar.</div>';
        }
    } else {
        $mensaje = '<div class="alert alert-warning">Por favor, ingresá un nombre para el motivo.</div>';
    }
}

if (isset($_GET['eliminar'])) {
    $idEliminar = (int)$_GET['eliminar'];
    try {
        $stmt = $pdo->prepare("DELETE FROM motivos WHERE id = :id");
        $stmt->execute([':id' => $idEliminar]);
        $mensaje = '<div class="alert alert-success">Motivo eliminado.</div>';
    } catch (PDOException $e) {
        $mensaje = '<div class="alert alert-danger">Error al eliminar el motivo.</div>';
    }
}

$motivos = $pdo->query("SELECT * FROM motivos ORDER BY nombre ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Motivos - Control de Presentismo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<?php
$pagina_activa = 'motivos';
include __DIR__ . '/includes/navbar.php';
?>

<div class="container">
    <?= $mensaje ?>

    <div class="row g-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-bold">➕ Nuevo Motivo / Licencia</div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="guardar">
                        <div class="mb-3">
                            <label class="form-label">Nombre del Motivo / Artículo *</label>
                            <input type="text" name="nombre" class="form-control" placeholder="Ej: Art. 10 - Razones Particulares" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tope / Límite de días por año</label>
                            <input type="number" name="limite_anual" class="form-control" placeholder="0 = Sin límite" min="0" value="0">
                            <small class="text-muted">Si no tiene límite, dejá en 0.</small>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Guardar Motivo</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
                    <span>Motivos Registrados</span>
                    <span class="badge bg-primary fs-6"><?= count($motivos) ?> motivos</span>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Descripción / Nombre</th>
                                <th>Tope Anual</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($motivos)): ?>
                                <tr><td colspan="4" class="text-center text-muted p-3">No hay motivos cargados aún.</td></tr>
                            <?php else: ?>
                                <?php foreach ($motivos as $m): ?>
                                    <tr>
                                        <td><?= $m['id'] ?></td>
                                        <td><strong><?= htmlspecialchars($m['nombre']) ?></strong></td>
                                        <td>
                                            <?= ($m['limite_anual'] > 0) ? '<span class="badge bg-info text-dark">' . $m['limite_anual'] . ' días/año</span>' : '<span class="badge bg-light text-muted border">Sin tope</span>' ?>
                                        </td>
                                        <td class="text-end">
                                            <a href="motivos.php?eliminar=<?= $m['id'] ?>" class="btn btn-sm btn-outline-danger" title="Eliminar" onclick="return confirm('¿Eliminar este motivo?');">
                                                🗑️ Eliminar
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>