<?php
// empleados.php
require_once __DIR__ . '/config/database.php';

$mensaje = '';
$empleadoEditar = null;

// --- ESTRUCTURA: AGREGAR COLUMNA ACTIVO SI NO EXISTE ---
try {
    $pdo->exec("ALTER TABLE empleados ADD COLUMN activo INTEGER DEFAULT 1");
} catch (PDOException $e) {}

// --- 1. ACCIÓN: ELIMINAR REGISTRO DEFINITIVAMENTE ---
if (isset($_GET['eliminar'])) {
    $idEliminar = (int)$_GET['eliminar'];
    try {
        $stmtInasistencias = $pdo->prepare("DELETE FROM inasistencias WHERE empleado_id = :id");
        $stmtInasistencias->execute([':id' => $idEliminar]);

        $stmt = $pdo->prepare("DELETE FROM empleados WHERE id = :id");
        $stmt->execute([':id' => $idEliminar]);

        $mensaje = '<div class="alert alert-success">Empleado y sus registros asociados fueron eliminados.</div>';
    } catch (PDOException $e) {
        $mensaje = '<div class="alert alert-danger">Error al eliminar el empleado: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// --- 2. ACCIÓN: DAR DE BAJA / REACTIVAR ---
if (isset($_GET['baja'])) {
    $idBaja = (int)$_GET['baja'];
    $stmt = $pdo->prepare("UPDATE empleados SET activo = 0 WHERE id = :id");
    $stmt->execute([':id' => $idBaja]);
    $mensaje = '<div class="alert alert-warning">Empleado dado de baja (inactivo).</div>';
}

if (isset($_GET['alta'])) {
    $idAlta = (int)$_GET['alta'];
    $stmt = $pdo->prepare("UPDATE empleados SET activo = 1 WHERE id = :id");
    $stmt->execute([':id' => $idAlta]);
    $mensaje = '<div class="alert alert-success">Empleado reactivado correctamente.</div>';
}

// --- 3. CARGAR DATOS PARA EDICIÓN ---
if (isset($_GET['editar'])) {
    $idEditar = (int)$_GET['editar'];
    $stmt = $pdo->prepare("SELECT * FROM empleados WHERE id = :id");
    $stmt->execute([':id' => $idEditar]);
    $empleadoEditar = $stmt->fetch();
}

// --- 4. PROCESAR ALTA O EDICIÓN MANUAL ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'guardar') {
    $id       = $_POST['id'] ?? null;
    $dni      = trim($_POST['dni']);
    $apellido = trim($_POST['apellido']);
    $nombre   = trim($_POST['nombre']);
    $cargo    = trim($_POST['cargo']);

    if (!empty($dni) && !empty($apellido) && !empty($nombre)) {
        try {
            if ($id) {
                $stmt = $pdo->prepare("
                    UPDATE empleados
                    SET dni = :dni, legajo = :legajo, apellido = :apellido, nombre = :nombre, cargo = :cargo
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':dni'      => $dni,
                    ':legajo'   => $dni,
                    ':apellido' => $apellido,
                    ':nombre'   => $nombre,
                    ':cargo'    => $cargo,
                    ':id'       => $id
                ]);
                $mensaje = '<div class="alert alert-success">Datos del empleado actualizados correctamente.</div>';
                $empleadoEditar = null;
            } else {
                $checkStmt = $pdo->prepare("SELECT id FROM empleados WHERE dni = :dni OR legajo = :legajo");
                $checkStmt->execute([':dni' => $dni, ':legajo' => $dni]);

                if ($checkStmt->fetch()) {
                    $mensaje = '<div class="alert alert-warning">El DNI ya se encuentra registrado.</div>';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO empleados (dni, legajo, apellido, nombre, cargo, activo) VALUES (:dni, :legajo, :apellido, :nombre, :cargo, 1)");
                    $stmt->execute([':dni' => $dni, ':legajo' => $dni, ':apellido' => $apellido, ':nombre' => $nombre, ':cargo' => $cargo]);
                    $mensaje = '<div class="alert alert-success">Empleado cargado correctamente.</div>';
                }
            }
        } catch (PDOException $e) {
            $mensaje = '<div class="alert alert-danger">Error al guardar los cambios.</div>';
        }
    } else {
        $mensaje = '<div class="alert alert-warning">Por favor, completá los campos obligatorios.</div>';
    }
}

// Obtener listado de empleados ordenados
$empleados = $pdo->query("SELECT *, COALESCE(activo, 1) as activo_estado FROM empleados ORDER BY activo_estado DESC, apellido ASC, nombre ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Empleados</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<?php
$pagina_activa = 'empleados';
include __DIR__ . '/includes/navbar.php';
?>

<div class="container">
    <?= $mensaje ?>

    <div class="row g-4">
        <!-- Columna Formulario -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm mb-4 <?= $empleadoEditar ? 'border-start border-warning border-4' : '' ?>">
                <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
                    <span><?= $empleadoEditar ? '✏️ Editar Empleado' : '➕ Nuevo Empleado' ?></span>
                    <?php if ($empleadoEditar): ?>
                        <a href="empleados.php" class="btn btn-sm btn-outline-secondary">Cancelar</a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="guardar">
                        <?php if ($empleadoEditar): ?>
                            <input type="hidden" name="id" value="<?= $empleadoEditar['id'] ?>">
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label">DNI *</label>
                            <input type="text" name="dni" class="form-control" value="<?= htmlspecialchars($empleadoEditar['dni'] ?? '') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Apellido *</label>
                            <input type="text" name="apellido" class="form-control" value="<?= htmlspecialchars($empleadoEditar['apellido'] ?? '') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nombre *</label>
                            <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($empleadoEditar['nombre'] ?? '') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Cargo</label>
                            <input type="text" name="cargo" class="form-control" value="<?= htmlspecialchars($empleadoEditar['cargo'] ?? '') ?>">
                        </div>
                        <button type="submit" class="btn <?= $empleadoEditar ? 'btn-warning text-white' : 'btn-primary' ?> w-100">
                            <?= $empleadoEditar ? 'Actualizar Cambios' : 'Guardar Empleado' ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Columna Listado -->
        <div class="col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
                    <span>Personal Registrado</span>
                    <span class="badge bg-primary fs-6"><?= count($empleados) ?> registrados</span>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>DNI</th>
                                <th>Apellido y Nombre</th>
                                <th>Estado</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($empleados)): ?>
                                <tr><td colspan="4" class="text-center text-muted p-3">Aún no hay empleados registrados.</td></tr>
                            <?php else: ?>
                                <?php foreach ($empleados as $emp): 
                                    $esActivo = ($emp['activo_estado'] == 1);
                                ?>
                                    <tr class="<?= !$esActivo ? 'table-secondary text-muted' : (($empleadoEditar && $empleadoEditar['id'] == $emp['id']) ? 'table-warning' : '') ?>">
                                        <td><strong><?= htmlspecialchars($emp['dni'] ?: $emp['legajo']) ?></strong></td>
                                        <td>
                                            <?= htmlspecialchars($emp['apellido'] . ', ' . $emp['nombre']) ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($emp['cargo'] ?: '-') ?></small>
                                        </td>
                                        <td>
                                            <?php if ($esActivo): ?>
                                                <span class="badge bg-success">Activo</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inactivo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <div class="btn-group btn-group-sm" role="group">
                                                <!-- BOTÓN IR AL LEGAJO -->
                                                <a href="legajo.php?id=<?= $emp['id'] ?>" class="btn btn-outline-primary" title="Ver Legajo">
                                                    📂 Legajo
                                                </a>

                                                <!-- Editar -->
                                                <a href="empleados.php?editar=<?= $emp['id'] ?>" class="btn btn-outline-warning" title="Editar datos">
                                                    ✏️
                                                </a>

                                                <!-- Dar de baja / Alta -->
                                                <?php if ($esActivo): ?>
                                                    <a href="empleados.php?baja=<?= $emp['id'] ?>" class="btn btn-outline-secondary" title="Dar de baja" onclick="return confirm('¿Desactivar este empleado?');">
                                                        🚫
                                                    </a>
                                                <?php else: ?>
                                                    <a href="empleados.php?alta=<?= $emp['id'] ?>" class="btn btn-outline-success" title="Reactivar">
                                                        ✅
                                                    </a>
                                                <?php endif; ?>

                                                <!-- Eliminar -->
                                                <a href="empleados.php?eliminar=<?= $emp['id'] ?>" class="btn btn-outline-danger" title="Eliminar definitivamente" onclick="return confirm('¿Eliminar definitivamente a este empleado y su historial? Esta acción no se puede deshacer.');">
                                                    🗑️
                                                </a>
                                            </div>
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