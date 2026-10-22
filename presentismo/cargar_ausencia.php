<?php
// cargar_ausencia.php
require_once __DIR__ . '/config/database.php';

// Asegurar columnas y tablas
try {
    $pdo->exec("ALTER TABLE inasistencias ADD COLUMN feria_origen TEXT");
} catch (PDOException $e) {}

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS feriados (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            fecha DATE NOT NULL UNIQUE,
            descripcion TEXT NOT NULL
        )
    ");
} catch (PDOException $e) {}

// Tabla para almacenar múltiples adjuntos por inasistencia
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
        )
    ");
} catch (PDOException $e) {}

$mensaje = '';

// OBTENER LISTA DE FERIADOS REGISTRADOS (FECHAS)
$feriadosArray =$pdo->query("SELECT fecha FROM feriados")->fetchAll(PDO::FETCH_COLUMN);

// --- FUNCIÓN PHP PARA CALCULAR DÍAS A DESCONTAR ---
function calcularDiasConsumidos($fecha_desde,$fecha_hasta, $feria_origen,$listaFeriados = []) {
    $fDesde = new DateTime($fecha_desde);
    $fHasta = new DateTime(!empty($fecha_hasta) ? $fecha_hasta :$fecha_desde);

    if ($fDesde >$fHasta) {
        return 0;
    }

    if (mb_strpos(mb_strtolower($feria_origen), 'julio') !== false) {$diasHabiles = 0;
        $intervalo = new DateInterval('P1D');
        $periodo = new DatePeriod($fDesde, $intervalo,$fHasta->modify('+1 day'));

        foreach ($periodo as$fecha) {
            $stringFecha =$fecha->format('Y-m-d');
            $diaSemana = (int)$fecha->format('N'); // 1 = Lunes, 7 = Domingo

            if ($diaSemana >= 1 && $diaSemana <= 5 && !in_array($stringFecha, $listaFeriados)) {$diasHabiles++;
            }
        }
        return $diasHabiles;
    }

    $diff = $fDesde->diff($fHasta);
    return $diff->days + 1;
}

// --- 1. ENDPOINT AJAX: DEVUELVE FERIAS Y DÍAS DISPONIBLES ---
if (isset($_GET['action']) &&$_GET['action'] === 'obtener_ferias_credito') {
    header('Content-Type: application/json');
    $empleado_id = (int)($_GET['empleado_id'] ?? 0);

    if ($empleado_id <= 0) {
        echo json_encode([]);
        exit;
    }

    try {
        $stmtG =$pdo->prepare("
            SELECT 
                periodo, 
                SUM(CASE WHEN UPPER(tipo) = 'SUMAR' THEN dias ELSE -dias END) as total_acreditado 
            FROM movimientos_feria 
            WHERE empleado_id = :emp 
            GROUP BY periodo
        ");
        $stmtG->execute([':emp' =>$empleado_id]);
        $movimientos =$stmtG->fetchAll(PDO::FETCH_ASSOC);

        $listadoFerias = [];

        foreach ($movimientos as$m) {
            $periodo = trim($m['periodo'] ?? '');
            if ($periodo === '') {$periodo = "Feria General";
            }
            
            $acreditados = (int)$m['total_acreditado'];

            $stmtTom =$pdo->prepare("
                SELECT fecha, fecha_fin 
                FROM inasistencias 
                WHERE empleado_id = :emp 
                  AND LOWER(TRIM(COALESCE(feria_origen, ''))) = LOWER(TRIM(:periodo))
            ");
            $stmtTom->execute([':emp' => $empleado_id, ':periodo' =>$periodo]);

            $tomados = 0;
            foreach ($stmtTom->fetchAll(PDO::FETCH_ASSOC) as $reg) {$tomados += calcularDiasConsumidos($reg['fecha'],$reg['fecha_fin'], $periodo,$feriadosArray);
            }

            $disponibles = max(0, $acreditados -$tomados);

            $listadoFerias[] = [
                'periodo'     => $periodo,
                'acreditados' => $acreditados,
                'tomados'     => $tomados,
                'disponibles' => $disponibles
            ];
        }

        echo json_encode($listadoFerias);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// --- 2. PROCESAMIENTO Y GUARDADO (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $empleado_id  = (int)($_POST['empleado_id'] ?? 0);
    $motivo       = trim($_POST['motivo'] ?? '');
    $feria_origen = trim($_POST['feria_origen'] ?? '');
    $fecha        =$_POST['fecha'] ?? '';
    $fecha_fin    =$_POST['fecha_fin'] ?? '';

    if ($empleado_id > 0 && !empty($motivo) && !empty($fecha)) {$diasSolicitados = calcularDiasConsumidos($fecha,$fecha_fin, $feria_origen,$feriadosArray);

        if ($diasSolicitados <= 0) {$mensaje = '<div class="alert alert-warning mb-4">No hay días hábiles computables en el rango seleccionado (los días coinciden con feriados o fin de semana).</div>';
        } else {
            // Validar saldo si se eligió Compensación de Feria
            if (mb_strpos(mb_strtolower($motivo), 'compensac') !== false || mb_strpos(mb_strtolower($motivo), 'feria') !== false) {
                if (empty($feria_origen)) {$mensaje = '<div class="alert alert-danger mb-4">Debés seleccionar la feria acreditada a compensar.</div>';
                } else {
                    $stmtAcr =$pdo->prepare("
                        SELECT SUM(CASE WHEN UPPER(tipo) = 'SUMAR' THEN dias ELSE -dias END) 
                        FROM movimientos_feria 
                        WHERE empleado_id = :emp AND LOWER(TRIM(periodo)) = LOWER(TRIM(:per))
                    ");
                    $stmtAcr->execute([':emp' => $empleado_id, ':per' =>$feria_origen]);
                    $acreditados = (int)$stmtAcr->fetchColumn();

                    $stmtTom =$pdo->prepare("
                        SELECT fecha, fecha_fin 
                        FROM inasistencias 
                        WHERE empleado_id = :emp AND LOWER(TRIM(COALESCE(feria_origen, ''))) = LOWER(TRIM(:per))
                    ");
                    $stmtTom->execute([':emp' =>$empleado_id, ':per' => $feria_origen]);$tomados = 0;
                    foreach ($stmtTom->fetchAll(PDO::FETCH_ASSOC) as $reg) {$tomados += calcularDiasConsumidos($reg['fecha'],$reg['fecha_fin'], $feria_origen,$feriadosArray);
                    }

                    $disponibles = max(0, $acreditados -$tomados);

                    if ($diasSolicitados >$disponibles) {
                        $tipoDiasText = (mb_strpos(mb_strtolower($feria_origen), 'julio') !== false) ? 'día(s) hábiles' : 'día(s) corridos';
                        $mensaje = '<div class="alert alert-danger mb-4">🚫 No se puede registrar. Para <strong>' . htmlspecialchars($feria_origen) . '</strong> dispone de <strong>' . $disponibles . ' ' .$tipoDiasText . '</strong> y está intentando descontar <strong>' . $diasSolicitados . '</strong>.</div>';
                    }
                }
            } else {
                $feria_origen = '';
            }

            if (empty($mensaje)) {
                $stmtIns =$pdo->prepare("
                    INSERT INTO inasistencias (empleado_id, fecha, fecha_fin, motivo, feria_origen) 
                    VALUES (:emp, :fecha, :fecha_fin, :motivo, :feria)
                ");
                $stmtIns->execute([
                    ':emp'       => $empleado_id,
                    ':fecha'     => $fecha,
                    ':fecha_fin' => $fecha_fin,
                    ':motivo'    => $motivo,
                    ':feria'     => $feria_origen
                ]);

                $inasistencia_id =$pdo->lastInsertId();

                // --- PROCESAR ARCHIVOS PDF ADJUNTOS ---
                $cantidadArchivosSubidos = 0;
                if (!empty($_FILES['adjuntos']['name'][0])) {$directorioUpload = __DIR__ . '/uploads/adjuntos/';
                    if (!is_dir($directorioUpload)) {
                        mkdir($directorioUpload, 0777, true);
                    }

                    foreach ($_FILES['adjuntos']['tmp_name'] as $key =>$tmpName) {
                        if ($_FILES['adjuntos']['error'][$key] === UPLOAD_ERR_OK) {$nombreOriginal = $_FILES['adjuntos']['name'][$key];
                            $ext = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));

                            if ($ext === 'pdf') {
                                $nombreUnico = 'adjunto_' .$inasistencia_id . '_' . uniqid() . '.pdf';
                                $destino = $directorioUpload .$nombreUnico;

                                if (move_uploaded_file($tmpName,$destino)) {
                                    $stmtAdj =$pdo->prepare("
                                        INSERT INTO adjuntos_inasistencias (inasistencia_id, nombre_original, nombre_archivo) 
                                        VALUES (:id, :nom_orig, :nom_arch)
                                    ");
                                    $stmtAdj->execute([
                                        ':id'       => $inasistencia_id,
                                        ':nom_orig' => $nombreOriginal,
                                        ':nom_arch' => $nombreUnico
                                    ]);
                                    $cantidadArchivosSubidos++;
                                }
                            }
                        }
                    }
                }

                $tipoText = (mb_strpos(mb_strtolower($feria_origen), 'julio') !== false) ? 'día(s) hábiles' : 'día(s) corridos';
                $txtAdjuntos = ($cantidadArchivosSubidos > 0) ? " Se adjuntaron $cantidadArchivosSubidos archivo(s) PDF." : "";
                $mensaje = '<div class="alert alert-success mb-4">✅ Ausencia por <strong>' . htmlspecialchars($motivo) . '</strong> registrada con éxito. Se descontaron <strong>' . $diasSolicitados . ' ' . $tipoText . '</strong>.' .$txtAdjuntos . '</div>';
            }
        }
    } else {
        $mensaje = '<div class="alert alert-warning mb-4">Por favor completá todos los campos obligatorios.</div>';
    }
}

// Cargar Empleados
$empleados =$pdo->query("SELECT id, apellido, nombre, legajo FROM empleados WHERE COALESCE(activo, 1) = 1 ORDER BY apellido, nombre")->fetchAll(PDO::FETCH_ASSOC);

// Cargar Motivos Base
$motivosDB =$pdo->query("SELECT nombre FROM motivos ORDER BY nombre ASC")->fetchAll(PDO::FETCH_COLUMN);
$motivosTodos = array_unique(array_merge(['Compensación de Feria'], $motivosDB));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cargar Ausencia - Control de Presentismo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<?php
$pagina_activa = 'cargar_ausencia';
include __DIR__ . '/includes/navbar.php';
?>

<div class="container" style="max-width: 650px;">
    <?= $mensaje ?>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-bold">📝 Registrar Licencia / Ausencia</div>
        <div class="card-body">
            <form method="POST" id="formAusencia" enctype="multipart/form-data">

                <!-- 1. AGENTE -->
                <div class="mb-3">
                    <label class="form-label fw-semibold">Agente *</label>
                    <select name="empleado_id" id="empleado_id" class="form-select" required onchange="alCambiarEmpleado()">
                        <option value="">-- Seleccionar Agente --</option>
                        <?php foreach ($empleados as$emp): ?>
                            <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['apellido'] . ', ' .$emp['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 2. MOTIVO PRINCIPAL -->
                <div class="mb-3">
                    <label class="form-label fw-semibold">Motivo / Licencia *</label>
                    <select name="motivo" id="motivo" class="form-select" required onchange="evaluarMostrarFeria()">
                        <option value="">-- Seleccionar Motivo --</option>
                        <?php foreach ($motivosTodos as$m): ?>
                            <option value="<?= htmlspecialchars($m) ?>"><?= htmlspecialchars($m) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 3. FERIA ACREDITADA -->
                <div class="mb-3 d-none" id="contenedorFeriaOrigen">
                    <label class="form-label fw-semibold text-primary">Feria Acreditada a Compensar *</label>
                    <select name="feria_origen" id="feria_origen" class="form-select" onchange="validarFechasYSaldo()">
                        <option value="">-- Seleccionar Feria --</option>
                    </select>
                    <div id="infoSaldo" class="form-text mt-2 fw-bold"></div>
                </div>

                <!-- 4. FECHAS -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Fecha Desde *</label>
                        <input type="date" name="fecha" id="fecha" class="form-control" required onchange="validarFechasYSaldo()">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Fecha Hasta</label>
                        <input type="date" name="fecha_fin" id="fecha_fin" class="form-control" onchange="validarFechasYSaldo()">
                    </div>
                </div>

                <!-- 5. ARCHIVOS ADJUNTOS CON ACUMULADOR -->
                <div class="mb-4">
                    <label class="form-label fw-semibold">Adjuntar Certificado / Nota de Pedido (PDF)</label>
                    
                    <div class="input-group mb-2">
                        <input type="file" id="selector_pdf" class="form-control" accept=".pdf" multiple>
                        <!-- <button class="btn btn-outline-secondary" type="button" onclick="agregarArchivos()">➕ Agregar PDF</button> -->
                    </div>
                    
                    <!-- Campo oculto donde se sincronizarán todos los archivos agregados antes del submit -->
                    <input type="file" name="adjuntos[]" id="adjuntos_finales" multiple class="d-none">

                    <ul class="list-group small" id="lista_adjuntos"></ul>
                    <!-- <div class="form-text mt-1">Podés seleccionar un archivo de una carpeta y hacer clic en <strong>Elegir archivo</strong>. Luego cambiar de carpeta y agregar otro.</div> -->
                </div>

                <button type="submit" id="btnGuardar" class="btn btn-primary w-100">Guardar Registro</button>
            </form>
        </div>
    </div>
</div>

<script>
let feriasDelEmpleado = [];
const listaFeriadosJS = <?= json_encode($feriadosArray) ?>;

// --- GESTOR DE ARCHIVOS ADJUNTOS (ACUMULADOR) ---
const acumuladorArchivos = new DataTransfer();

function agregarArchivos() {
    const selector = document.getElementById('selector_pdf');
    const archivos = selector.files;

    for (let i = 0; i < archivos.length; i++) {
        if (archivos[i].type === 'application/pdf' || archivos[i].name.toLowerCase().endsWith('.pdf')) {
            acumuladorArchivos.items.add(archivos[i]);
        }
    }

    selector.value = ''; // Limpiar el selector para permitir elegir de otra carpeta
    sincronizarYRenderizarArchivos();
}

function eliminarArchivo(index) {
    acumuladorArchivos.items.remove(index);
    sincronizarYRenderizarArchivos();
}

function sincronizarYRenderizarArchivos() {
    const inputFinal = document.getElementById('adjuntos_finales');
    inputFinal.files = acumuladorArchivos.files;

    const listaUL = document.getElementById('lista_adjuntos');
    listaUL.innerHTML = '';

    for (let i = 0; i < acumuladorArchivos.files.length; i++) {
        const file = acumuladorArchivos.files[i];
        const li = document.createElement('li');
        li.className = 'list-group-item d-flex justify-content-between align-items-center py-1 px-2';
        li.innerHTML = `
            <span>📄 ${file.name} <span class="text-muted">(${(file.size / 1024).toFixed(1)} KB)</span></span>
            <button type="button" class="btn btn-link text-danger p-0 ms-2" title="Quitar" onclick="eliminarArchivo(${i})">✖</button>
        `;
        listaUL.appendChild(li);
    }
}

// Agregar automáticamente si el usuario selecciona archivos sin presionar el botón "Agregar PDF"
document.getElementById('selector_pdf').addEventListener('change', function() {
    if (this.files.length > 0) {
        agregarArchivos();
    }
});

function alCambiarEmpleado() {
    const empId = document.getElementById('empleado_id').value;
    feriasDelEmpleado = [];
    
    document.getElementById('contenedorFeriaOrigen').classList.add('d-none');
    document.getElementById('feria_origen').innerHTML = '<option value="">-- Seleccionar Feria --</option>';
    document.getElementById('infoSaldo').innerHTML = '';
    
    if (!empId) return;

    fetch(`cargar_ausencia.php?action=obtener_ferias_credito&empleado_id=${empId}`)
        .then(r => r.json())
        .then(data => {
            if (Array.isArray(data)) {
                feriasDelEmpleado = data;
                evaluarMostrarFeria();
            }
        })
        .catch(err => console.error("Error obteniendo créditos:", err));
}

function evaluarMostrarFeria() {
    const motivo = document.getElementById('motivo').value.toLowerCase();
    const contenedor = document.getElementById('contenedorFeriaOrigen');
    const selectFeria = document.getElementById('feria_origen');
    const infoSaldo = document.getElementById('infoSaldo');
    const btnGuardar = document.getElementById('btnGuardar');

    if (motivo.includes('compensac') || motivo.includes('feria')) {
        contenedor.classList.remove('d-none');
        selectFeria.innerHTML = '<option value="">-- Seleccionar Feria con Días Disponibles --</option>';

        if (feriasDelEmpleado.length === 0) {
            infoSaldo.innerHTML = '<span class="text-danger">⚠️ Este agente no posee días de guardia cargados en "Cargar Feria".</span>';
            btnGuardar.disabled = true;
        } else {
            infoSaldo.innerHTML = '';
            btnGuardar.disabled = false;

            feriasDelEmpleado.forEach(f => {
                const opt = document.createElement('option');
                opt.value = f.periodo;
                const tipoText = f.periodo.toLowerCase().includes('julio') ? 'días hábiles' : 'días corridos';
                opt.textContent = `${f.periodo} (Disponibles: ${f.disponibles} ${tipoText} / Acreditados: ${f.acreditados})`;
                opt.dataset.disponibles = f.disponibles;
                
                if (f.disponibles <= 0) {
                    opt.disabled = true;
                }
                selectFeria.appendChild(opt);
            });
        }
    } else {
        contenedor.classList.add('d-none');
        selectFeria.value = '';
        infoSaldo.innerHTML = '';
        btnGuardar.disabled = false;
    }

    validarFechasYSaldo();
}

function validarFechasYSaldo() {
    const motivo = document.getElementById('motivo').value.toLowerCase();
    const selectFeria = document.getElementById('feria_origen');
    const selectedOption = selectFeria.options[selectFeria.selectedIndex];
    const fechaDesde = document.getElementById('fecha').value;
    const fechaHasta = document.getElementById('fecha_fin').value || fechaDesde;
    const btnGuardar = document.getElementById('btnGuardar');
    const infoSaldo = document.getElementById('infoSaldo');

    if (!motivo.includes('compensac') && !motivo.includes('feria')) {
        btnGuardar.disabled = false;
        return;
    }

    if (!selectedOption || !selectedOption.value) {
        if (feriasDelEmpleado.length > 0) {
            infoSaldo.innerHTML = '<span class="text-warning">⚠️ Seleccioná la feria a compensar.</span>';
        }
        btnGuardar.disabled = true;
        return;
    }

    const disponibles = parseInt(selectedOption.dataset.disponibles);
    const esJulio = selectedOption.value.toLowerCase().includes('julio');

    if (!fechaDesde) {
        const tipoText = esJulio ? 'días hábiles' : 'días corridos';
        infoSaldo.innerHTML = `<span class="text-info">ℹ️ Saldo disponible para ${selectedOption.value}: <strong>${disponibles} ${tipoText}</strong>.</span>`;
        btnGuardar.disabled = (disponibles <= 0);
        return;
    }

    const d1 = new Date(fechaDesde + 'T00:00:00');
    const d2 = new Date(fechaHasta + 'T00:00:00');
    let diasSolicitados = 0;

    if (esJulio) {
        let tempDate = new Date(d1);
        while (tempDate <= d2) {
            const dayOfWeek = tempDate.getDay();
            const yyyy = tempDate.getFullYear();
            const mm = String(tempDate.getMonth() + 1).padStart(2, '0');
            const dd = String(tempDate.getDate()).padStart(2, '0');
            const formattedDate = `${yyyy}-${mm}-${dd}`;

            if (dayOfWeek !== 0 && dayOfWeek !== 6 && !listaFeriadosJS.includes(formattedDate)) {
                diasSolicitados++;
            }
            tempDate.setDate(tempDate.getDate() + 1);
        }
    } else {
        const diffTime = Math.abs(d2 - d1);
        diasSolicitados = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
    }

    const tipoText = esJulio ? 'día(s) hábiles' : 'día(s) corridos';

    if (diasSolicitados <= 0) {
        infoSaldo.innerHTML = `<span class="text-warning">⚠️ El rango de fechas coincide únicamente con feriados o fines de semana (0 días hábiles).</span>`;
        btnGuardar.disabled = true;
    } else if (diasSolicitados > disponibles) {
        infoSaldo.innerHTML = `<span class="text-danger">🚫 Intentás solicitar <strong>${diasSolicitados} ${tipoText}</strong> pero el saldo disponible en ${selectedOption.value} es de <strong>${disponibles}</strong>.</span>`;
        btnGuardar.disabled = true;
    } else {
        infoSaldo.innerHTML = `<span class="text-success">✅ Se descontarán <strong>${diasSolicitados} ${tipoText}</strong> de ${selectedOption.value}.</span>`;
        btnGuardar.disabled = false;
    }
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>