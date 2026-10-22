<?php
// legajo.php
require_once __DIR__ . '/config/database.php';

$idEmpleado = (int)($_GET['id'] ?? 0);

if ($idEmpleado <= 0) {
    header('Location: empleados.php');
    exit;
}

// Garantizar columnas en la tabla adjuntos_inasistencias
try {
    $pdo->exec("ALTER TABLE adjuntos_inasistencias ADD COLUMN descripcion TEXT");
} catch (PDOException $e) {}

try {
    $pdo->exec("ALTER TABLE adjuntos_inasistencias ADD COLUMN empleado_id INTEGER");
} catch (PDOException $e) {}

// Modificar la columna inasistencia_id para permitir nulos (adjuntos independientes del legajo)
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS adjuntos_inasistencias (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            inasistencia_id INTEGER NULL,
            empleado_id INTEGER NULL,
            nombre_original TEXT NOT NULL,
            nombre_archivo TEXT NOT NULL,
            descripcion TEXT NULL,
            tipo_documento TEXT DEFAULT 'certificado',
            fecha_subida DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
} catch (PDOException $e) {}

$mensaje = '';

// Cargar datos del Empleado
$stmtEmp = $pdo->prepare("SELECT * FROM empleados WHERE id = :id");
$stmtEmp->execute([':id' => $idEmpleado]);
$empleado = $stmtEmp->fetch();

if (!$empleado) {
    header('Location: empleados.php');
    exit;
}

// --- ACCIONES POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. SUBIR DOCUMENTOS AL LEGAJO
    if ($action === 'subir_pdf') {
        $descripcion = trim($_POST['descripcion'] ?? '');

        if (!empty($_FILES['archivos']['name'][0])) {
            $directorioUpload = __DIR__ . '/uploads/adjuntos/';
            if (!is_dir($directorioUpload)) {
                mkdir($directorioUpload, 0777, true);
            }

            $subidos = 0;
            foreach ($_FILES['archivos']['tmp_name'] as $k => $tmpName) {
                if ($_FILES['archivos']['error'][$k] === UPLOAD_ERR_OK) {
                    $nombreOriginal = $_FILES['archivos']['name'][$k];
                    $ext = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));

                    if ($ext === 'pdf') {
                        $nombreUnico = 'legajo_' . $idEmpleado . '_' . uniqid() . '.pdf';
                        $destino = $directorioUpload . $nombreUnico;

                        if (move_uploaded_file($tmpName, $destino)) {
                            $stmtAdj = $pdo->prepare("
                                INSERT INTO adjuntos_inasistencias 
                                (empleado_id, nombre_original, nombre_archivo, descripcion) 
                                VALUES (:emp, :nom_orig, :nom_arch, :desc)
                            ");
                            $stmtAdj->execute([
                                ':emp'      => $idEmpleado,
                                ':nom_orig' => $nombreOriginal,
                                ':nom_arch' => $nombreUnico,
                                ':desc'     => $descripcion
                            ]);
                            $subidos++;
                        }
                    }
                }
            }
            if ($subidos > 0) {
                $mensaje = '<div class="alert alert-success">✅ Se agregaron ' . $subidos . ' documento(s) al legajo.</div>';
            } else {
                $mensaje = '<div class="alert alert-warning">⚠️ Solo se permiten archivos en formato PDF.</div>';
            }
        }
    }

    // 2. EDITAR DESCRIPCIÓN DE UN PDF
    if ($action === 'editar_descripcion') {
        $adjunto_id  = (int)($_POST['adjunto_id'] ?? 0);
        $nuevaDesc   = trim($_POST['descripcion'] ?? '');

        if ($adjunto_id > 0) {
            $stmtUpd = $pdo->prepare("UPDATE adjuntos_inasistencias SET descripcion = :desc WHERE id = :id");
            $stmtUpd->execute([':desc' => $nuevaDesc, ':id' => $adjunto_id]);
            $mensaje = '<div class="alert alert-success">✅ Descripción actualizada correctamente.</div>';
        }
    }

    // 3. ELIMINAR ARCHIVO PDF
    if ($action === 'eliminar_pdf') {
        $adjunto_id = (int)($_POST['adjunto_id'] ?? 0);
        $stmtSel = $pdo->prepare("SELECT nombre_archivo FROM adjuntos_inasistencias WHERE id = :id");
        $stmtSel->execute([':id' => $adjunto_id]);
        $arch = $stmtSel->fetch();

        if ($arch) {
            $ruta = __DIR__ . '/uploads/adjuntos/' . $arch['nombre_archivo'];
            if (file_exists($ruta)) {
                @unlink($ruta);
            }
            $stmtDel = $pdo->prepare("DELETE FROM adjuntos_inasistencias WHERE id = :id");
            $stmtDel->execute([':id' => $adjunto_id]);
            $mensaje = '<div class="alert alert-success">🗑️ Documento eliminado del legajo.</div>';
        }
    }
}

// --- FILTROS DE BÚSQUEDA ---
$buscar_texto = trim($_GET['buscar_texto'] ?? '');
$buscar_fecha = trim($_GET['buscar_fecha'] ?? '');

$sql = "
    SELECT 
        a.id,
        a.nombre_original,
        a.nombre_archivo,
        a.descripcion,
        a.fecha_subida,
        a.inasistencia_id,
        i.motivo,
        i.fecha as fecha_inasistencia
    FROM adjuntos_inasistencias a
    LEFT JOIN inasistencias i ON a.inasistencia_id = i.id
    WHERE (a.empleado_id = :emp OR i.empleado_id = :emp)
";

$params = [':emp' => $idEmpleado];

if ($buscar_texto !== '') {
    $sql .= " AND (LOWER(a.descripcion) LIKE :txt OR LOWER(a.nombre_original) LIKE :txt OR LOWER(COALESCE(i.motivo, '')) LIKE :txt)";
    $params[':txt'] = '%' . mb_strtolower($buscar_texto) . '%';
}

if ($buscar_fecha !== '') {
    $sql .= " AND (DATE(a.fecha_subida) = :fec OR i.fecha = :fec)";
    $params[':fec'] = $buscar_fecha;
}

$sql .= " ORDER BY a.fecha_subida DESC";

$stmtArchivos = $pdo->prepare($sql);
$stmtArchivos->execute($params);
$archivos = $stmtArchivos->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Legajo Digital - <?= htmlspecialchars($empleado['apellido'] . ' ' . $empleado['nombre']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<?php
$pagina_activa = 'empleados';
include __DIR__ . '/includes/navbar.php';
?>

<div class="container py-4">
    
    <!-- Encabezado / Datos del Agente -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <a href="empleados.php" class="btn btn-outline-secondary btn-sm mb-2">← Volver a Empleados</a>
            <h2 class="mb-0">📂 Legajo Digital: <strong><?= htmlspecialchars($empleado['apellido'] . ', ' . $empleado['nombre']) ?></strong></h2>
            <div class="text-muted">DNI / Legajo: <?= htmlspecialchars($empleado['dni'] ?: $empleado['legajo']) ?> | Cargo: <?= htmlspecialchars($empleado['cargo'] ?: 'Sin especificar') ?></div>
        </div>
        <div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalSubirDoc">➕ Subir Documento PDF</button>
        </div>
    </div>

    <?= $mensaje ?>

    <!-- Buscador -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <input type="hidden" name="id" value="<?= $idEmpleado ?>">
                
                <div class="col-md-5">
                    <label class="form-label fw-semibold">Buscar por Descripción / Motivo / Nombre de archivo</label>
                    <input type="text" name="buscar_texto" class="form-control" placeholder="Ej: Certificado médico, Solicitud..." value="<?= htmlspecialchars($buscar_texto) ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Buscar por Fecha</label>
                    <input type="date" name="buscar_fecha" class="form-control" value="<?= htmlspecialchars($buscar_fecha) ?>">
                </div>

                <div class="col-md-3 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-dark w-100">🔍 Buscar</button>
                    <?php if ($buscar_texto !== '' || $buscar_fecha !== ''): ?>
                        <a href="legajo.php?id=<?= $idEmpleado ?>" class="btn btn-outline-secondary">Limpiar</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabla de Documentos -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center py-3">
            <span>📄 Documentos y Pedidos de Licencia del Agente</span>
            <span class="badge bg-secondary"><?= count($archivos) ?> documento(s)</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Fecha Registro</th>
                            <th>Origen / Licencia</th>
                            <th>Descripción</th>
                            <th>Archivo Original</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($archivos)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted p-4">No se encontraron documentos registrados en este legajo.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($archivos as $doc): ?>
                                <tr>
                                    <td>
                                        <small class="fw-bold"><?= date('d/m/Y H:i', strtotime($doc['fecha_subida'])) ?></small>
                                    </td>
                                    <td>
                                        <?php if (!empty($doc['motivo'])): ?>
                                            <span class="badge bg-info text-dark">Licencia: <?= htmlspecialchars($doc['motivo']) ?></span>
                                            <?php if ($doc['fecha_inasistencia']): ?>
                                                <br><small class="text-muted">Fecha: <?= date('d/m/Y', strtotime($doc['fecha_inasistencia'])) ?></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Documento Legajo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($doc['descripcion'] ?: '-') ?>
                                    </td>
                                    <td>
                                        <small class="text-muted">📄 <?= htmlspecialchars($doc['nombre_original']) ?></small>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <!-- Ver PDF -->
                                            <a href="uploads/adjuntos/<?= urlencode($doc['nombre_archivo']) ?>" target="_blank" class="btn btn-outline-primary" title="Ver / Descargar PDF">
                                                👁️ Ver PDF
                                            </a>

                                            <!-- Editar Descripción -->
                                            <button type="button" class="btn btn-outline-warning" 
                                                    onclick="abrirModalEditar(<?= $doc['id'] ?>, '<?= htmlspecialchars(addslashes($doc['descripcion'] ?? '')) ?>')" 
                                                    title="Editar Descripción">
                                                ✏️
                                            </button>

                                            <!-- Eliminar -->
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('¿Confirma eliminar este documento del legajo?');">
                                                <input type="hidden" name="action" value="eliminar_pdf">
                                                <input type="hidden" name="adjunto_id" value="<?= $doc['id'] ?>">
                                                <button type="submit" class="btn btn-outline-danger" title="Eliminar Documento">🗑️</button>
                                            </form>
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

<!-- MODAL: SUBIR DOCUMENTO AL LEGAJO -->
<div class="modal fade" id="modalSubirDoc" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="subir_pdf">
                <div class="modal-header">
                    <h5 class="modal-title">Subir Documento PDF al Legajo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Seleccionar PDF(s) *</label>
                        <input type="file" name="archivos[]" class="form-control" accept=".pdf" multiple required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Descripción / Observación</label>
                        <textarea name="descripcion" class="form-control" rows="3" placeholder="Ej: Título universitario, Certificado de aptitud física, Solicitud firmada..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Subir Archivo</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: EDITAR DESCRIPCIÓN -->
<div class="modal fade" id="modalEditarDesc" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="editar_descripcion">
                <input type="hidden" name="adjunto_id" id="edit_adjunto_id">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Descripción</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Descripción / Observación</label>
                        <textarea name="descripcion" id="edit_descripcion" class="form-control" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning text-white">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function abrirModalEditar(id, descripcion) {
    document.getElementById('edit_adjunto_id').value = id;
    document.getElementById('edit_descripcion').value = descripcion;
    const modal = new bootstrap.Modal(document.getElementById('modalEditarDesc'));
    modal.show();
}
</script>
</body>
</html>