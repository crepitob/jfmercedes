<?php
// generar_oficio.php
require_once __DIR__ . '/config/database.php';

// Configurar zona horaria de Argentina
date_default_timezone_set('America/Argentina/Buenos_Aires');

// Función para formatear fecha actual en español
function obtenerFechaActualTexto() {
    $dias = [1=>'primero', 2=>'2', 3=>'3', 4=>'4', 5=>'5', 6=>'6', 7=>'7', 8=>'8', 9=>'9', 10=>'10',
             11=>'11', 12=>'12', 13=>'13', 14=>'14', 15=>'15', 16=>'16', 17=>'17', 18=>'18', 19=>'19',
             20=>'20', 21=>'21', 22=>'22', 23=>'23', 24=>'24', 25=>'25', 26=>'26', 27=>'27', 28=>'28',
             29=>'29', 30=>'30', 31=>'31'];
             
    $meses = [1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril', 5 => 'mayo', 6 => 'junio',
              7 => 'julio', 8 => 'agosto', 9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'];

    $dia = (int)date('j');
    $mes = (int)date('n');
    $anio = date('Y');

    $diaTexto = ($dia === 1) ? '1°' : $dia;

    return "Mercedes, " . $diaTexto . " de " . $meses[$mes] . " de " . $anio;
}

$fechaEncabezado = obtenerFechaActualTexto();

// --- FILTROS DE FECHA PARA EL OFICIO ---
$fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-01');
$fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-t');

// OBTENER AUSENCIAS EN EL RANGO SELECCIONADO
$query = "
    SELECT 
        i.fecha,
        i.fecha_fin,
        i.motivo,
        e.apellido,
        e.nombre,
        e.legajo
    FROM inasistencias i
    JOIN empleados e ON i.empleado_id = e.id
    WHERE i.fecha BETWEEN :desde AND :hasta
    ORDER BY e.apellido, e.nombre, i.fecha ASC
";

$stmt = $pdo->prepare($query);
$stmt->execute([':desde' => $fecha_desde, ':hasta' => $fecha_hasta]);
$inasistencias = $stmt->fetchAll();

// --- FUNCIÓN AUXILIAR PARA FORMATO DE FECHAS SEGÚN DURACIÓN ---
function formatearRangoFechas($fInicio, $fFin) {
    if (empty($fFin) || $fFin === $fInicio) {
        return date('d/m/Y', strtotime($fInicio));
    }

    $dt1 = new DateTime($fInicio);
    $dt2 = new DateTime($fFin);
    $diff = $dt1->diff($dt2);
    $cantDias = $diff->days + 1;

    if ($cantDias === 2) {
        return $dt1->format('d') . '-' . $dt2->format('d/m/Y');
    } elseif ($cantDias > 2) {
        return "del " . $dt1->format('d/m/Y') . " al " . $dt2->format('d/m/Y');
    } else {
        return date('d/m/Y', strtotime($fInicio));
    }
}

// --- MANEJO DE DESCARGA EN FORMATO WORD (.DOC) ---
if (isset($_GET['export']) && $_GET['export'] === 'word') {
    $nombreArchivo = "Oficio_Licencias_" . date('Ymd_His') . ".doc";
    
    header("Content-Type: application/vnd.ms-word; charset=utf-8");
    header("Content-Disposition: attachment;Filename=" . $nombreArchivo);
    header("Pragma: no-cache");
    header("Expires: 0");
    ?>
    <html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:w='urn:schemas-microsoft-com:office:word' xmlns='http://www.w3.org/TR/REC-html40'>
    <head>
        <meta charset="utf-8">
        <title>Oficio Judicial</title>
        <style>
            body { font-family: "Courier New", Courier, monospace; font-size: 14pt; line-height: 1.5; color: #000; }
        </style>
    </head>
    <body>
        <div style="text-align: right; margin-bottom: 30px;">
            <?= $fechaEncabezado ?>.-
        </div>

        <div style="margin-bottom: 25px;">
            <strong>A la Cámara Federal de Apelaciones<br>Secretaría de Superintendencia</strong><br>
            S.&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; / &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;D.
        </div>

        <p style="text-align: justify; text-indent: 40px; margin-bottom: 25px;">
            Tengo el agrado de dirigirme a V.E. en mi carácter de Juez Titular a cargo del Juzgado Federal de Primera Instancia de Mercedes (B), a fin de comunicarle las licencias concedidas (Ac. 34/77 CSJN) en este Juzgado, durante el transcurso del mes de <strong>AGOSTO</strong>
        </p>

        <?php if (!empty($inasistencias)): ?>
            <div style="margin-top: 15px; margin-bottom: 15px;">
                <?php foreach ($inasistencias as $row): ?>
                    <div style="margin-bottom: 6px;">
                        <strong><?= htmlspecialchars(mb_strtoupper($row['apellido']) . ', ' . mb_strtoupper($row['nombre'])) ?></strong>:
                        <?= htmlspecialchars($row['motivo']) ?>
                        <?= formatearRangoFechas($row['fecha'], $row['fecha_fin']) ?>.-
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p style="text-align: center; margin: 30px 0; font-style: italic;">
                Sin novedades de inasistencias ni licencias registradas en el período seleccionado.
            </p>
        <?php endif; ?>

        <p style="text-align: justify; text-indent: 40px; margin-top: 25px;">
            Saludo a V.E. atentamente.-
        </p>

        <table style="width: 100%; margin-top: 80px;">
            <tr>
                <td style="width: 50%;"></td>
                <td style="width: 50%; text-align: center; border-top: 1px solid #000; padding-top: 8px;">
                    <strong>Firma y Sello del Responsable</strong>
                </td>
            </tr>
        </table>
    </body>
    </html>
    <?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Generar Oficio / Nota - Control de Presentismo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Regla para suprimir encabezados y pies de página automáticos del navegador */
        @page {
            size: A4;
            margin: 15mm;
        }

        @media print {
            /* Forzar la ocultación total de barras y controles al imprimir */
            nav, .navbar, .no-print { 
                display: none !important; 
                visibility: hidden !important;
            }
            body { 
                background-color: #fff !important; 
                margin: 0 !important;
                padding: 0 !important;
            }
            .card { 
                border: none !important; 
                box-shadow: none !important; 
            }
            .container { 
                max-width: 100% !important; 
                width: 100% !important; 
                margin: 0 !important; 
                padding: 0 !important; 
            }
        }
        .hoja-oficio {
            background: #fff;
            padding: 40px 50px;
            min-height: 800px;
            font-family: "Courier New", Courier, monospace;
            font-size: 14pt;
            line-height: 1.5;
        }
    </style>
</head>
<body class="bg-light">

<?php
$pagina_activa = 'generar_oficio';
include __DIR__ . '/includes/navbar.php';
?>

<div class="container">
    <!-- PANEL DE CONTROL NO IMPRIMIBLE -->
    <div class="card border-0 shadow-sm mb-4 no-print">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Fecha Desde</label>
                    <input type="date" name="fecha_desde" class="form-control" value="<?= htmlspecialchars($fecha_desde) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Fecha Hasta</label>
                    <input type="date" name="fecha_hasta" class="form-control" value="<?= htmlspecialchars($fecha_hasta) ?>">
                </div>
                <div class="col-md-6 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100">🔍 Filtrar Periodo</button>
                    <button type="button" onclick="window.print();" class="btn btn-success w-100">🖨️ Imprimir / PDF</button>
                    <a href="generar_oficio.php?export=word&fecha_desde=<?= urlencode($fecha_desde) ?>&fecha_hasta=<?= urlencode($fecha_hasta) ?>" class="btn btn-outline-primary w-100">📄 Descargar Word</a>
                </div>
            </form>
        </div>
    </div>

    <!-- DOCUMENTO OFICIAL -->
    <div class="card border-0 shadow-sm mb-5">
        <div class="card-body hoja-oficio">
            
            <!-- ENCABEZADO: LUGAR Y FECHA A LA DERECHA -->
            <div class="text-end mb-5">
                <?= $fechaEncabezado ?>.-
            </div>

            <!-- DESTINATARIO -->
            <div class="mb-4">
                <strong>A la Cámara Federal de Apelaciones<br>Secretaría de Superintendencia</strong><br>
                S.&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; / &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;D.
            </div>

            <!-- CUERPO DE LA NOTA -->
            <p class="text-justify mb-4" style="text-indent: 40px;">
                Tengo el agrado de dirigirme a V.E. en mi carácter de Juez Titular a cargo del Juzgado Federal de Primera Instancia de Mercedes (B), a fin de comunicarle las licencias concedidas (Ac. 34/77 CSJN) en este Juzgado, durante el transcurso del mes de <strong>AGOSTO</strong>
            </p>

            <!-- DETALLE DE LICENCIAS -->
            <?php if (!empty($inasistencias)): ?>
                <div class="my-4">
                    <?php foreach ($inasistencias as $row): ?>
                        <div class="mb-2">
                            <strong><?= htmlspecialchars(mb_strtoupper($row['apellido']) . ', ' . mb_strtoupper($row['nombre'])) ?></strong>: 
                            <?= htmlspecialchars($row['motivo']) ?> 
                            <?= formatearRangoFechas($row['fecha'], $row['fecha_fin']) ?>.-
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-light text-center border my-4">
                    Sin novedades de inasistencias ni licencias registradas en el período seleccionado.
                </div>
            <?php endif; ?>

            <p class="text-justify mt-4" style="text-indent: 40px;">
                Saludo a V.E. atentamente.-
            </p>

            <!-- ESPACIO PARA FIRMA -->
            <div class="row mt-5 pt-5 text-center">
                <div class="col-6 offset-6">
                    <div style="border-top: 1px solid #000; width: 80%; margin: 0 auto; padding-top: 5px;">
                        <strong>Firma y Sello del Responsable</strong>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>