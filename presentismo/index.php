<?php
// index.php
require_once __DIR__ . '/config/database.php';

// Verificación/Creación automática de la tabla de adjuntos
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS adjuntos_inasistencias (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            inasistencia_id INTEGER NOT NULL,
            nombre_original TEXT NOT NULL,
            nombre_archivo TEXT NOT NULL,
            tipo_documento TEXT DEFAULT 'certificado',
            fecha_subida DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (inasistencia_id) REFERENCES inasistencias(id) ON DELETE CASCADE
        );
    ");
} catch (PDOException $e) {
    // Continuar si la tabla ya existe
}

$mensaje = '';

// Mantener filtros en la URL al editar o eliminar
$queryParams = $_GET;
unset($queryParams['mensaje_status']);
$queryStringActual = http_build_query($queryParams);
$redireccionURL = 'index.php' . ($queryStringActual ? '?' . $queryStringActual : '');

// --- 1. PROCESAR ELIMINACIÓN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'eliminar_inasistencia') {
    $id_eliminar = (int)($_POST['id'] ?? 0);

    if ($id_eliminar > 0) {
        try {
            $stmtDel = $pdo->prepare("DELETE FROM inasistencias WHERE id = :id");
            $stmtDel->execute([':id' => $id_eliminar]);

            if ($stmtDel->rowCount() > 0) {
                header("Location: " . $redireccionURL);
                exit;
            }
        } catch (PDOException $e) {
            $mensaje = '<div class="alert alert-danger alert-dismissible fade show">🚫 Error al eliminar: ' . htmlspecialchars($e->getMessage()) . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        }
    }
}

// --- 2. PROCESAR EDICIÓN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'editar_inasistencia') {
    $id_editar    = (int)($_POST['id'] ?? 0);
    $fecha_desde  = $_POST['fecha_desde'] ?? '';
    $fecha_hasta  = $_POST['fecha_hasta'] ?? '';
    $motivo       = trim($_POST['motivo'] ?? '');

    if ($id_editar > 0 && !empty($fecha_desde) && !empty($motivo)) {
        try {
            $stmtUpd = $pdo->prepare("
                UPDATE inasistencias 
                SET fecha = :desde, 
                    fecha_fin = :hasta, 
                    motivo = :motivo 
                WHERE id = :id
            ");
            $stmtUpd->execute([
                ':desde'  => $fecha_desde,
                ':hasta'  => !empty($fecha_hasta) ? $fecha_hasta : $fecha_desde,
                ':motivo' => $motivo,
                ':id'     => $id_editar
            ]);

            header("Location: " . $redireccionURL);
            exit;
        } catch (PDOException $e) {
            $mensaje = '<div class="alert alert-danger alert-dismissible fade show">🚫 Error al actualizar: ' . htmlspecialchars($e->getMessage()) . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        }
    }
}

// --- 3. FILTROS ---
$filtro_empleado = (int)($_GET['empleado_id'] ?? 0);
$filtro_motivo   = trim($_GET['motivo'] ?? '');
$filtro_desde    = trim($_GET['fecha_desde'] ?? '');
$filtro_hasta    = trim($_GET['fecha_hasta'] ?? '');

$whereClause = ["1=1"];
$params = [];

if ($filtro_empleado > 0) {
    $whereClause[] = "i.empleado_id = :empleado_id";
    $params[':empleado_id'] = $filtro_empleado;
}

if (!empty($filtro_motivo)) {
    $whereClause[] = "i.motivo = :motivo";
    $params[':motivo'] = $filtro_motivo;
}

if (!empty($filtro_desde)) {
    $whereClause[] = "i.fecha >= :fecha_desde";
    $params[':fecha_desde'] = $filtro_desde;
}

if (!empty($filtro_hasta)) {
    $whereClause[] = "COALESCE(NULLIF(i.fecha_fin, ''), i.fecha) <= :fecha_hasta";
    $params[':fecha_hasta'] = $filtro_hasta;
}

$sqlWhere = implode(" AND ", $whereClause);

$queryInasistencias = "
    SELECT 
        i.id,
        i.empleado_id,
        i.fecha,
        i.fecha_fin,
        i.motivo,
        i.feria_origen,
        e.apellido,
        e.nombre,
        e.legajo
    FROM inasistencias i
    JOIN empleados e ON i.empleado_id = e.id
    WHERE $sqlWhere
    ORDER BY i.id DESC
";

$stmtInasistencias = $pdo->prepare($queryInasistencias);
$stmtInasistencias->execute($params);
$inasistencias = $stmtInasistencias->fetchAll(PDO::FETCH_ASSOC);

// Cargar adjuntos agrupados por inasistencia_id
$adjuntosMap = [];
if (!empty($inasistencias)) {
    $ids = array_column($inasistencias, 'id');
    $inClause = implode(',', array_map('intval', $ids));
    $stmtAdjuntos = $pdo->query("SELECT * FROM adjuntos_inasistencias WHERE inasistencia_id IN ($inClause)");
    while ($adj = $stmtAdjuntos->fetch(PDO::FETCH_ASSOC)) {
        $adjuntosMap[$adj['inasistencia_id']][] = $adj;
    }
}

$totalEmpleados     = $pdo->query("SELECT COUNT(*) FROM empleados WHERE COALESCE(activo, 1) = 1")->fetchColumn();
$totalInasistencias = $pdo->query("SELECT COUNT(*) FROM inasistencias")->fetchColumn();
$ausentesHoy        = $pdo->query("SELECT COUNT(*) FROM inasistencias WHERE date('now') BETWEEN fecha AND COALESCE(NULLIF(fecha_fin, ''), fecha)")->fetchColumn();

$empleadosList = $pdo->query("SELECT id, apellido, nombre FROM empleados WHERE COALESCE(activo, 1) = 1 ORDER BY apellido, nombre")->fetchAll(PDO::FETCH_ASSOC);
$motivosList   = $pdo->query("SELECT DISTINCT motivo FROM inasistencias WHERE motivo IS NOT NULL AND motivo != '' ORDER BY motivo ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Inicio - Control de Presentismo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<?php
$pagina_activa = 'index';
if (file_exists(__DIR__ . '/includes/navbar.php')) {
    include __DIR__ . '/includes/navbar.php';
}
?>

<div class="container my-4">
    <?= $mensaje ?>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm bg-white p-3 text-center">
                <span class="text-muted small fw-bold">TOTAL EMPLEADOS</span>
                <h3 class="fw-bold mb-0 text-primary"><?= $totalEmpleados ?></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm bg-white p-3 text-center">
                <span class="text-muted small fw-bold">AUSENTES HOY</span>
                <h3 class="fw-bold mb-0 text-warning"><?= $ausentesHoy ?></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm bg-white p-3 text-center">
                <span class="text-muted small fw-bold">TOTAL REGISTROS</span>
                <h3 class="fw-bold mb-0 text-secondary"><?= $totalInasistencias ?></h3>
            </div>
        </div>
    </div>

    <!-- BUSCADOR CON FILTROS -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="index.php" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Agente / Empleado</label>
                    <select name="empleado_id" class="form-select form-select-sm">
                        <option value="">-- Todos los agentes --</option>
                        <?php foreach ($empleadosList as $emp): ?>
                            <option value="<?= $emp['id'] ?>" <?= $filtro_empleado == $emp['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($emp['apellido'] . ', ' . $emp['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label small fw-bold">Motivo / Licencia</label>
                    <select name="motivo" class="form-select form-select-sm">
                        <option value="">-- Todos los motivos --</option>
                        <?php foreach ($motivosList as $mot): ?>
                            <option value="<?= htmlspecialchars($mot['motivo']) ?>" <?= $filtro_motivo === $mot['motivo'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($mot['motivo']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label small fw-bold">Desde</label>
                    <input type="date" name="fecha_desde" class="form-control form-control-sm" value="<?= htmlspecialchars($filtro_desde) ?>">
                </div>

                <div class="col-md-2">
                    <label class="form-label small fw-bold">Hasta</label>
                    <input type="date" name="fecha_hasta" class="form-control form-control-sm" value="<?= htmlspecialchars($filtro_hasta) ?>">
                </div>

                <div class="col-md-2 d-flex gap-1">
                    <button type="submit" class="btn btn-primary btn-sm w-100">🔍 Buscar</button>
                    <?php if ($filtro_empleado || $filtro_motivo || $filtro_desde || $filtro_hasta): ?>
                        <a href="index.php" class="btn btn-outline-danger btn-sm" title="Limpiar filtros">✖</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h4 mb-0 fw-bold">📋 Registro Reciente de Ausencias</h2>
        <a href="cargar_ausencia.php" class="btn btn-primary">+ Nueva Ausencia</a>
    </div>

    <!-- TABLA DE RESULTADOS CON EDICIÓN DESPLEGABLE Y ADJUNTOS -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Agente</th>
                            <th>DNI / Legajo</th>
                            <th>Fecha Desde</th>
                            <th>Fecha Hasta</th>
                            <th>Motivo / Licencia</th>
                            <th>Adjuntos</th>
                            <th class="text-end pe-3" style="min-width: 160px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($inasistencias)): ?>
                            <?php foreach ($inasistencias as $row): 
                                $fechaHasta = !empty($row['fecha_fin']) ? $row['fecha_fin'] : $row['fecha'];
                                $nombreCompleto = htmlspecialchars($row['apellido'] . ', ' . $row['nombre']);
                                $idRow = $row['id'];
                                $archivosAdjuntos = $adjuntosMap[$idRow] ?? [];
                            ?>
                                <tr>
                                    <td class="fw-semibold"><?= $nombreCompleto ?></td>
                                    <td><?= htmlspecialchars($row['legajo'] ?? '') ?></td>
                                    <td><?= date('d/m/Y', strtotime($row['fecha'])) ?></td>
                                    <td><?= date('d/m/Y', strtotime($fechaHasta)) ?></td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?= htmlspecialchars($row['motivo']) ?>
                                        </span>
                                        <?php if (!empty($row['feria_origen'])): ?>
                                            <span class="badge bg-info text-dark"><?= htmlspecialchars($row['feria_origen']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($archivosAdjuntos)): ?>
                                            <div class="d-flex flex-column gap-1">
                                                <?php foreach ($archivosAdjuntos as $adj): ?>
                                                    <a href="uploads/adjuntos/<?= htmlspecialchars($adj['nombre_archivo']) ?>" 
                                                       target="_blank" 
                                                       class="btn btn-outline-danger btn-sm py-0 px-2 text-truncate" 
                                                       style="max-width: 180px;"
                                                       title="<?= htmlspecialchars($adj['nombre_original']) ?>">
                                                        📄 <?= htmlspecialchars($adj['nombre_original']) ?>
                                                    </a>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted small">Sin adjuntos</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-3">
                                        <!-- BOTÓN DESPLEGAR EDICIÓN -->
                                        <button class="btn btn-outline-primary btn-sm me-1" 
                                                type="button" 
                                                data-bs-toggle="collapse" 
                                                data-bs-target="#editRow_<?= $idRow ?>">
                                            ✏️ Editar
                                        </button>

                                        <!-- BOTÓN ELIMINAR -->
                                        <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar registro de <?= htmlspecialchars($row['apellido']) ?>?');">
                                            <input type="hidden" name="action" value="eliminar_inasistencia">
                                            <input type="hidden" name="id" value="<?= $idRow ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm" title="Eliminar">
                                                🗑️
                                            </button>
                                        </form>
                                    </td>
                                </tr>

                                <!-- FILA OCULTA CON FORMULARIO DE EDICIÓN EN LÍNEA -->
                                <tr class="collapse bg-white" id="editRow_<?= $idRow ?>">
                                    <td colspan="7" class="p-3 border-start border-3 border-primary">
                                        <form method="POST" class="row g-2 align-items-end">
                                            <input type="hidden" name="action" value="editar_inasistencia">
                                            <input type="hidden" name="id" value="<?= $idRow ?>">

                                            <div class="col-md-3">
                                                <label class="form-label small fw-bold">Fecha Desde</label>
                                                <input type="date" name="fecha_desde" class="form-control form-control-sm" value="<?= $row['fecha'] ?>" required>
                                            </div>

                                            <div class="col-md-3">
                                                <label class="form-label small fw-bold">Fecha Hasta</label>
                                                <input type="date" name="fecha_hasta" class="form-control form-control-sm" value="<?= $fechaHasta ?>">
                                            </div>

                                            <div class="col-md-4">
                                                <label class="form-label small fw-bold">Motivo</label>
                                                <input type="text" name="motivo" class="form-control form-control-sm" value="<?= htmlspecialchars($row['motivo']) ?>" required>
                                            </div>

                                            <div class="col-md-2 d-flex gap-1">
                                                <button type="submit" class="btn btn-success btn-sm w-100">💾 Guardar</button>
                                                <button type="button" class="btn btn-light btn-sm" data-bs-toggle="collapse" data-bs-target="#editRow_<?= $idRow ?>">✖</button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    No se encontraron ausencias con los criterios de búsqueda seleccionados.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>