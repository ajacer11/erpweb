<?php
/**
 * imprimir_ticket.php - VISTA HÍBRIDA PROFESIONAL (A4 / TICKET 80mm)
 * Lógica de Paginación Exacta: 19 ítems (hoja normal) / 16 ítems (hoja con totalizador)
 * Ajuste V2.4: Integración de IIBB e Inicio de Actividades en Cabecera (Solicitud Usuario)
 * v2.4 TOMAR COMO VERSIÖN BASE 
 */
session_start();
require_once 'db.php';
require_once 'stock_deposito_helpers.php';
require_once 'retper_helpers.php';
require_once __DIR__ . '/vendor/autoload.php';

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0)
    die("ID de comprobante no válido.");

try {
    // 1. Datos de Empresa, Logo y Formato de Impresión
    $stmtLogo = $pdo->query("SELECT logo, formatoimpresion, mostrarvendedor, leyenda2 FROM configuracion ORDER BY id DESC LIMIT 1");
    $logoData = $stmtLogo->fetch(PDO::FETCH_ASSOC);
    $logoBase64 = ($logoData && !empty($logoData['logo'])) ? base64_encode($logoData['logo']) : null;
    $logoMime = 'image/png';
    if ($logoBase64) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $logoMime = $finfo->buffer($logoData['logo']) ?: 'image/png';
    }
    $mostrarVendedor = !isset($logoData['mostrarvendedor']) || (int)$logoData['mostrarvendedor'] === 1;
    $leyendaPie = trim((string)($logoData['leyenda2'] ?? ''));

    // Si no viene ?format= explícito (ej. al reimprimir desde comprobantes_emitidos.php /
    // reporte_ventas_comprobantes.php), se usa el formato configurado para el negocio —
    // mismo criterio que newfactura.js aplica al imprimir recién emitido (config.formatoimpresion,
    // '80'/'58' = ticket térmico, cualquier otra cosa = A4). Antes el default hardcodeado era
    // 'A4' siempre, por eso reimprimir un ticket térmico salía en A4.
    if (isset($_GET['format'])) {
        $format = $_GET['format'];
    } else {
        $fmtConfig = $logoData['formatoimpresion'] ?? 'A4';
        $format = in_array($fmtConfig, ['80', '58']) ? 'ticket' : 'A4';
    }
    $isTicket = (strtolower($format) === 'ticket');

    $stmtEmp = $pdo->query("SELECT * FROM datosempresa ORDER BY id DESC LIMIT 1");
    $emp = $stmtEmp->fetch(PDO::FETCH_ASSOC);
    if (!$emp) {
        $emp = ['razon' => 'EMPRESA NO CONFIGURADA', 'domicilio' => '', 'localidad' => '', 'provincia' => '', 'cuit' => '00-00000000-0', 'condicioniva' => 'Resp. Insc.', 'numiibb' => '', 'fechainicioact' => ''];
    }

    // 2. Cabecera del Comprobante
    $sqlCab = "SELECT t.*, 
                      t.ptovta as ptovta_alias, t.numcomp as numcomp_alias, 
                      t.tipocomp as tipocomp_alias, t.lvdesde as lvdesde_alias, 
                      t.lvhasta as lvhasta_alias,
                      c.razon as cliente_nombre, c.domicilio as cli_dom, c.localidad as cli_loc, 
                      c.numdoc as cli_doc, c.condicionfiscal as cli_cond, c.tipodoc as cli_tdd,
                      v.nombre as vendedor_nombre
               FROM tickets t
               LEFT JOIN alb_clientes c ON t.cliente = c.id
               LEFT JOIN vendedores v ON t.vendedor = v.id
               WHERE t.id = ?";
    $stmtCab = $pdo->prepare($sqlCab);
    $stmtCab->execute([$id]);
    $cab = $stmtCab->fetch(PDO::FETCH_ASSOC);
    $isX = false;
    if (!$cab)
        die("El comprobante no existe.");

    // En una reimpresión se usa la sucursal que realmente emitió la venta. El CUIT
    // queda intacto en $emp porque pertenece al tenant y no al depósito.
    if (multidepositoHabilitado($pdo) && !empty($cab['id_deposito'])) {
        $stmtDep = $pdo->prepare("SELECT domicilio, localidad, provincia, telefono, email, codigopostal, logo FROM depositos WHERE id = ?");
        $stmtDep->execute([(int) $cab['id_deposito']]);
        $depEmisor = $stmtDep->fetch(PDO::FETCH_ASSOC);
        if ($depEmisor) {
            foreach (['domicilio', 'localidad', 'provincia', 'telefono', 'email'] as $campo) {
                if (trim((string) ($depEmisor[$campo] ?? '')) !== '') {
                    $emp[$campo] = (string) $depEmisor[$campo];
                }
            }
            if (trim((string) ($depEmisor['codigopostal'] ?? '')) !== '') {
                $emp['cp'] = (string) $depEmisor['codigopostal'];
            }
            if (!empty($depEmisor['logo'])) {
                $logoBase64 = base64_encode($depEmisor['logo']);
                $logoMime = (new finfo(FILEINFO_MIME_TYPE))->buffer($depEmisor['logo']) ?: 'image/png';
            }
        }
    }

    $tipoID = intval($cab['tipocomp_alias']);
    $letra = 'X'; // default seguro
    $isTipoA = false;
    if (in_array($tipoID, [1, 2, 3])) {
        $letra = 'A';
        $isTipoA = true;
    } elseif (in_array($tipoID, [6, 7, 8])) {
        $letra = 'B';
        $isTipoA = false;
    } elseif (in_array($tipoID, [11, 12, 13])) {
        $letra = 'C';
        $isTipoA = false;
    } elseif (in_array($tipoID, [100, 101])) {
        $letra = 'X';
        $isTipoA = false;
        $isX = true;
    }

    $showQR = (!empty($cab['cae']) && !$isX);

    // --- LÓGICA DE GENERACIÓN DE QR AFIP ---
    $qrEmbedded = null;
    if ($showQR) {
        $cuitLimpio = preg_replace('/[^0-9]/', '', $emp['cuit']);
        $docRecLimpio = preg_replace('/[^0-9]/', '', $cab['cli_doc']);

        // Tipo de documento receptor: 80 (CUIT), 96 (DNI), 99 (Consumidor Final / Sin documento)
        $tipoDocRec = (strlen($docRecLimpio) >= 10) ? 80 : ((strlen($docRecLimpio) > 0) ? 96 : 99);

        $qrPayload = [
            "ver" => 1,
            "fecha" => date('Y-m-d', strtotime($cab['fecha'])),
            "cuit" => (int) $cuitLimpio,
            "ptoVta" => (int) $cab['ptovta_alias'],
            "tipoCmp" => (int) $cab['tipocomp_alias'],
            "nroCmp" => (int) $cab['numcomp_alias'],
            "importe" => (float) $cab['total'],
            "moneda" => 'PES',
            "ctz" => 1,
            "tipoDocRec" => $tipoDocRec,
            "nroDocRec" => (int) $docRecLimpio,
            "tipoCodAut" => "E",
            "codAut" => (int) $cab['cae']
        ];

        $qrBase64 = base64_encode(json_encode($qrPayload));
        $qrUrl = "https://www.afip.gob.ar/fe/qr/?p=" . $qrBase64;

        // Generación local del QR (sin depender de un servicio externo de terceros)
        try {
            $qrResult = (new Builder())->build(
                writer: new SvgWriter(),
                data: $qrUrl,
                size: 150,
                margin: 0
            );
            $qrEmbedded = $qrResult->getDataUri();
        } catch (\Throwable $e) {
            $qrEmbedded = null;
        }
    }

    // 3. Obtener Líneas de Venta
    $sqlDet = "SELECT lv.*, ld.pordesc 
               FROM Lineadeventa lv
               LEFT JOIN lineadescuentos ld ON lv.tipocomp = ld.tipocomp 
                                            AND lv.pdv = ld.pdv 
                                            AND lv.numcomp = ld.numcomp 
                                            AND lv.prodcod = ld.codpro
               WHERE lv.numcomp = ? AND lv.pdv = ? AND lv.tipocomp = ? 
                 AND lv.id >= ? AND lv.id <= ?
               ORDER BY lv.id ASC";
    $stmtDet = $pdo->prepare($sqlDet);
    $stmtDet->execute([$cab['numcomp_alias'], $cab['ptovta_alias'], $cab['tipocomp_alias'], $cab['lvdesde_alias'], $cab['lvhasta_alias']]);
    $lineasRaw = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

    $lineasProductos = array_filter($lineasRaw, function ($l) {
        return !in_array(strtoupper($l['prodcod'] ?? $l['ProdCod'] ?? ''), ['DESC', 'RECA']);
    });

    // --- LÓGICA DE PAGINACIÓN EXACTA 19/16 ---
    $paginasItems = [];
    $tempItems = array_values($lineasProductos);

    if ($isTicket) {
        $paginasItems[] = $tempItems; // Tira continua
    } else {
        while (count($tempItems) > 0) {
            $totalRestante = count($tempItems);
            if ($totalRestante <= 16) {
                $paginasItems[] = array_splice($tempItems, 0, $totalRestante);
            } elseif ($totalRestante <= 19) {
                $paginasItems[] = array_splice($tempItems, 0, floor($totalRestante / 2));
            } else {
                $paginasItems[] = array_splice($tempItems, 0, 19);
            }
        }
    }
    $totalPaginas = count($paginasItems);

    $tiposNombres = [
        1 => 'FACTURA A',
        2 => 'NOTA DE DEBITO A',
        3 => 'NOTA DE CREDITO A',
        6 => 'FACTURA B',
        7 => 'NOTA DE DEBITO B',
        8 => 'NOTA DE CREDITO B',
        11 => 'FACTURA C',
        12 => 'NOTA DE DEBITO C',
        13 => 'NOTA DE CREDITO C',
        100 => 'TICKET',
        101 => 'TICKET DE CREDITO'
    ];
    $tipoStr = $tiposNombres[$cab['tipocomp_alias']] ?? 'COMPROBANTE';

} catch (Exception $e) {
    die("Error crítico: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title><?php echo $tipoStr; ?> #<?php echo $cab['numcomp_alias']; ?></title>
    <link rel="icon" type="image/png" href="isotipo.png">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap');

        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        @page {
            size:
                <?php echo $isTicket ? '80mm auto' : 'A4'; ?>
            ;
            margin: 0;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            color: black;
            margin: 0;
            padding: 0;
            background-color:
                <?php echo $isTicket ? '#fff' : '#f5f5f5'; ?>
            ;
            font-size: 10px;
        }

        .sheet {
            width:
                <?php echo $isTicket ? '80mm' : '210mm'; ?>
            ;
            min-height:
                <?php echo $isTicket ? 'auto' : '297mm'; ?>
            ;
            margin:
                <?php echo $isTicket ? '0' : '5mm auto'; ?>
            ;
            background: white;
            padding:
                <?php echo $isTicket ? '5mm 3mm' : '10mm 12mm'; ?>
            ;
            box-shadow:
                <?php echo $isTicket ? 'none' : '0 0 10px rgba(0,0,0,0.1)'; ?>
            ;
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
        }

        .header {
            display: flex;
            flex-direction:
                <?php echo $isTicket ? 'column' : 'row'; ?>
            ;
            justify-content: space-between;
            border-bottom: 1.5pt solid #002d5a;
            padding-bottom: 3mm;
            margin-bottom: 3mm;
            text-align:
                <?php echo $isTicket ? 'center' : 'left'; ?>
            ;
        }

        .logo-box img {
            max-width: 160px;
            max-height: 50px;
            object-fit: contain;
        }

        .company-info h2 {
            margin: 0;
            color: #002d5a;
            font-size: 13px;
            font-weight: 800;
        }

        .company-info p {
            margin: 1px 0;
            color: black;
            font-size: 9px;
            line-height: 1.1;
        }

        .doc-info {
            text-align:
                <?php echo $isTicket ? 'center' : 'right'; ?>
            ;
        }

        .doc-type-box {
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border: 1.5pt solid #002d5a;
            padding: 3px 8px;
            font-weight: 800;
            margin: 1mm 0;
            line-height: 1;
        }

        .doc-letter {
            font-size: 18px;
        }

        .doc-code {
            font-size: 8px;
            font-weight: 800;
            margin-top: 2px;
        }


        .info-grid {
            display: grid;
            grid-template-columns:
                <?php echo $isTicket ? '1fr' : '1.1fr 0.9fr'; ?>
            ;
            gap: 2mm;
            margin-bottom: 3mm;
        }

        .box {
            border: 0.5pt solid #ccc;
            padding: 2mm;
            border-radius: 2px;
            background: #fcfcfc;
        }

        .box h3 {
            margin: 0 0 1mm 0;
            font-size: 8px;
            text-transform: uppercase;
            color: black;
            border-bottom: 0.5pt solid #ccc;
            padding-bottom: 0.5mm;
        }

        .item-row-a4 {
            padding: 1.5mm 0;
            border-bottom: 0.5pt solid #eee;
            page-break-inside: avoid;
        }

        .item-row-a4 .l1 {
            font-size: 10.5px;
            font-weight: 400;
            margin-bottom: 0.5mm;
        }

        .item-row-a4 .l1 strong {
            color: #002d5a;
            font-size: 11px;
        }

        .item-row-a4 .l2 {
            font-size: 9px;
            color: #444;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .item-ticket-group {
            padding: 1.5mm 0;
            border-bottom: 0.5pt solid #eee;
        }

        .t-line-1 {
            font-size: 10px;
            font-weight: 700;
            margin-bottom: 0.3mm;
        }

        .t-line-2,
        .t-line-3 {
            font-size: 10px;
            color: black;
        }

        .t-line-4,
        .t-line-5 {
            font-size: 10px;
            font-weight: 700;
            text-align: right;
            margin-top: 0.2mm;
            color: black;
        }

        .totals-container {
            display: flex;
            justify-content: flex-end;
            padding-top: 3mm;
            margin-top: auto;
        }

        .totals-box {
            width:
                <?php echo $isTicket ? '100%' : '90mm'; ?>
            ;
            border: 1.5pt solid #002d5a;
            padding: 2.5mm;
            background: #fff;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 1mm 0;
            border-bottom: 0.5pt solid #eee;
            font-size: 10.5px;
        }

        .total-row.grand-total {
            border-top: 1.5pt solid #002d5a;
            border-bottom: none;
            margin-top: 1mm;
            padding-top: 2mm;
            font-size: 15px;
            font-weight: 800;
            color: #002d5a;
        }

        .qr-wrapper {
            width: 35mm;
            height: 35mm;
            border: 0.5pt solid #eee;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fdfdfd;
        }

        .qr-wrapper img {
            width: 32mm;
            height: 32mm;
        }

        .page-footer {
            border-top: 0.5pt solid #ddd;
            font-size: 9px;
            display: flex;
            justify-content: space-between;
            color: black;
            padding-top: 2mm;
            margin-top: 2mm;
        }

        .sheet:not(.last-sheet) .page-footer {
            margin-top: auto;
        }

        @media print {
            .btn-print {
                display: none;
            }

            body {
                background: white;
            }

            .sheet {
                box-shadow: none;
                margin: 0;
                width:
                    <?php echo $isTicket ? '80mm' : '100%'; ?>
                ;
                page-break-after:
                    <?php echo $isTicket ? 'avoid' : 'always'; ?>
                ;
            }
        }

        .text-right {
            text-align: right !important;
        }

        .fw-bold {
            font-weight: 700;
        }

        .text-muted {
            color: #666;
            font-size: 8px;
        }
    </style>
</head>

<body>

    <button style="position:fixed;bottom:20px;right:20px;padding:10px 20px;z-index:999" class="btn-print"
        onclick="window.print()">IMPRIMIR / PDF</button>

    <?php
    $totalUnidadesCount = 0;
    foreach ($paginasItems as $nPag => $itemsEnPagina):
        $esUltimaPagina = ($nPag + 1 == $totalPaginas);
        ?>

        <div class="sheet <?php echo $esUltimaPagina ? 'last-sheet' : ''; ?>">
            <!-- ENCABEZADO -->
            <div class="header">
                <div class="logo-side">
                    <div class="logo-box">
                        <?php if ($logoBase64): ?>
                            <img src="data:<?php echo htmlspecialchars($logoMime); ?>;base64,<?php echo $logoBase64; ?>" alt="Logo">
                        <?php endif; ?>
                    </div>
                    <div class="company-info">
                        <h2><?php echo htmlspecialchars($emp['razon']); ?></h2>
                        <p><?php echo htmlspecialchars($emp['domicilio'] . ' - ' . $emp['localidad']); ?></p>
                        <p>CUIT: <?php echo htmlspecialchars($emp['cuit']); ?> | IVA:
                            <?php echo htmlspecialchars($emp['condicioniva']); ?>
                        </p>
                        <p>Ing. Brutos: <?= $emp['numiibb'] ?: '-' ?> | Inicio Act:
                            <?= $emp['fechainicioact'] ? date('d/m/Y', strtotime($emp['fechainicioact'])) : '-' ?>
                        </p>
                    </div>
                </div>
                <div class="doc-info">
                    <div class="doc-type-box">
                        <div class="doc-letter"><?php echo $letra; ?></div>
                        <div class="doc-code">Cod. <?php echo $tipoID; ?></div>
                    </div>


                    <h1 style="margin:0; font-size:16px"><?php echo $tipoStr; ?></h1>
                    <p class="fw-bold">N°
                        <?php echo str_pad($cab['ptovta_alias'], 4, '0', STR_PAD_LEFT); ?>-<?php echo str_pad($cab['numcomp_alias'], 8, '0', STR_PAD_LEFT); ?>
                    </p>
                    <p>Fecha: <?php echo date('d/m/Y', strtotime($cab['fecha'])); ?></p>
                </div>
            </div>

            <!-- DATOS CLIENTE -->
            <div class="info-grid">
                <div class="box">
                    <h3>RECEPTOR / CLIENTE</h3>
                    <p><strong><?php echo htmlspecialchars($cab['cliente_nombre']); ?></strong></p>
                    <p><?php echo htmlspecialchars($cab['cli_doc']); ?> | <?php echo $cab['cli_cond']; ?></p>
                    <?php if (!$isTicket): ?>
                        <p><?php echo htmlspecialchars($cab['cli_dom'] . ' (' . $cab['cli_loc'] . ')'); ?></p><?php endif; ?>
                </div>
                <div class="box">
                    <h3>INFO COMERCIAL</h3>
                    <?php if ($mostrarVendedor): ?>
                        <p>Vendedor: <strong><?php echo htmlspecialchars($cab['vendedor_nombre'] ?: 'S/V'); ?></strong></p>
                    <?php endif; ?>

                    <?php
                    $pagoStr = htmlspecialchars($cab['mpago1'] ?: 'Cta. Cte.');
                    if (!empty($cab['mpago2']) && floatval($cab['mpago2imp']) > 0) {
                        $pagoStr .= ' / ' . htmlspecialchars($cab['mpago2']);
                    }
                    ?>
                    <p>Pago: <strong><?php echo $pagoStr; ?></strong></p>

                    <p>Página: <strong><?php echo ($nPag + 1); ?> / <?php echo $totalPaginas; ?></strong></p>
                </div>
            </div>

            <!-- LISTADO DE PRODUCTOS -->
            <div class="items-container">
                <?php foreach ($itemsEnPagina as $row):
                    $cant = floatval($row['cant'] ?? $row['Cant'] ?? 0);
                    $totalL = floatval($row['total'] ?? $row['Total'] ?? 0);
                    $impIntL = floatval($row['ii'] ?? $row['II'] ?? 0);
                    $ivaL = floatval($row['iva'] ?? $row['Iva'] ?? 0);
                    $totalUnidadesCount += $cant;

                    $netoTotalL = $totalL - $impIntL - $ivaL;
                    $netoU = $netoTotalL / ($cant ?: 1);
                    $impIntU = $impIntL / ($cant ?: 1);
                    $finalU = $totalL / ($cant ?: 1);
                    $alicuota = $row['alicuota'] ?? '21';
                    ?>
                    <?php if (!$isTicket): ?>
                        <div class="item-row-a4">
                            <div class="l1"><strong><?php echo number_format($cant, 0); ?></strong> -
                                <?php echo htmlspecialchars($row['prodcod'] ?? $row['ProdCod']); ?>
                                <?php echo htmlspecialchars($row['proddes'] ?? $row['ProdDes']); ?>
                            </div>
                            <div class="l2">
                                <?php if ($isTipoA): ?>
                                    <div class="units"><span class="text-muted">UNITARIOS--></span> Neto:
                                        <?php echo number_format($netoU, 2, ',', '.'); ?>, IVA: <?php echo $alicuota; ?>%, Imp Int:
                                        <?php echo number_format($impIntU, 2, ',', '.'); ?>
                                    </div>
                                    <div class="totals"><span class="text-muted">|| TOTALES--></span> Imp Int:
                                        <?php echo number_format($impIntL, 2, ',', '.'); ?> <strong>Neto:
                                            <?php echo number_format($netoTotalL, 2, ',', '.'); ?></strong>
                                    </div>
                                <?php else: ?>
                                    <div class="units"><span class="text-muted">UNITARIO FINAL--></span>
                                        <?php echo number_format($finalU, 2, ',', '.'); ?></div>
                                    <div class="totals"><span class="text-muted">|| TOTAL LÍNEA--></span>
                                        <strong><?php echo number_format($totalL, 2, ',', '.'); ?></strong>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="item-ticket-group">
                            <div class="t-line-1"><strong><?php echo number_format($cant, 0); ?></strong> x
                                <?php echo htmlspecialchars($row['proddes'] ?? $row['ProdDes']); ?>
                            </div>
                            <?php if ($isTipoA): ?>
                                <div class="t-line-2">Cod: <?php echo htmlspecialchars($row['prodcod']); ?> | Neto U:
                                    <?php echo number_format($netoU, 2, ',', '.'); ?> | II U:
                                    <?php echo number_format($impIntU, 2, ',', '.'); ?>
                                </div>
                                <div class="t-line-3">IVA: <?php echo $alicuota; ?>% | Neto T:
                                    <?php echo number_format($netoTotalL, 2, ',', '.'); ?> | II T:
                                    <?php echo number_format($impIntL, 2, ',', '.'); ?>
                                </div>
                                <div class="t-line-4 fw-bold">Subtotal Imp Int: <?php echo number_format($impIntL, 2, ',', '.'); ?>
                                </div>
                                <div class="t-line-5 fw-bold">Subtotal Neto: <?php echo number_format($netoTotalL, 2, ',', '.'); ?>
                                </div>
                            <?php else: ?>
                                <div class="t-line-2">Cod: <?php echo htmlspecialchars($row['prodcod']); ?> | Unitario:
                                    <?php echo number_format($finalU, 2, ',', '.'); ?>
                                </div>
                                <div class="t-line-5 fw-bold">Total Línea: <?php echo number_format($totalL, 2, ',', '.'); ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <!-- TOTALES -->
            <?php if ($esUltimaPagina): ?>
                <div class="totals-container">
                    <?php if ($showQR): ?>
                        <div class="qr-wrapper" style="margin-right:auto">
                            <?php if ($qrEmbedded): ?>
                                <img src="<?php echo $qrEmbedded; ?>" alt="QR ARCA">
                            <?php else: ?>
                                <span style="font-size:7px; color:#999">QR ARCA</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="totals-box">
                        <?php
                        $subtN = floatval($cab['subtotal'] ?? 0) + floatval($cab['subtotal10'] ?? 0) + floatval($cab['subtotal0'] ?? 0);
                        $iva21 = floatval($cab['iva'] ?? 0);
                        $iva10 = floatval($cab['iva10'] ?? 0);
                        $iiH = floatval($cab['ii'] ?? 0);
                        $totalH = floatval($cab['total'] ?? 0);
                        $percepcionesH = percepcionesDeTicket($pdo, (int) ($cab['id'] ?? $id));
                        ?>

                        <?php if ($isTipoA): ?>
                            <div class="total-row"><span>Neto Gravado:</span><span
                                    class="fw-bold"><?php echo number_format($subtN, 2, ',', '.'); ?></span></div>
                            <?php if ($iva21 > 0): ?>
                                <div class="total-row"><span>I.V.A. 21%:</span><span
                                        class="fw-bold"><?php echo number_format($iva21, 2, ',', '.'); ?></span></div><?php endif; ?>
                            <?php if ($iva10 > 0): ?>
                                <div class="total-row"><span>I.V.A. 10.5%:</span><span
                                        class="fw-bold"><?php echo number_format($iva10, 2, ',', '.'); ?></span></div><?php endif; ?>
                            <?php if ($iiH > 0): ?>
                                <div class="total-row"><span>Impuestos Internos:</span><span
                                        class="fw-bold"><?php echo number_format($iiH, 2, ',', '.'); ?></span></div><?php endif; ?>
                        <?php else: ?>
                            <p style="font-size: 9px; margin-bottom: 5px; color: #555; line-height: 1.2;">Impuestos nacionales del
                                comprobante:<br>IVA: $<?php echo number_format($iva21 + $iva10, 2, ',', '.'); ?> // Imp Int:
                                $<?php echo number_format($iiH, 2, ',', '.'); ?></p>
                        <?php endif; ?>

                        <?php foreach ($percepcionesH as $perc): ?>
                            <div class="total-row"><span><?php echo htmlspecialchars($perc['nombre']); ?> (<?php echo rtrim(rtrim(number_format((float) $perc['alicuota'], 2, ',', '.'), '0'), ','); ?>%):</span><span
                                    class="fw-bold"><?php echo number_format((float) $perc['importe'], 2, ',', '.'); ?></span></div>
                        <?php endforeach; ?>

                        <div class="total-row grand-total"><span>TOTAL:</span><span>$
                                <?php echo number_format($totalH, 2, ',', '.'); ?></span></div>
                    </div>
                </div>

                <div style="margin-top: 3mm;">
                    <?php if (!empty($cab['cae'])): ?>
                        <div
                            style="display:flex; flex-direction: <?php echo $isTicket ? 'column' : 'row'; ?>; align-items:center; gap: 3mm; border: 0.5pt solid #999; padding: 1.5mm; background: #f9f9f9;">
                            <div style="font-size: 8px; line-height: 1.3; text-align: center;">
                                CAE N°: <strong><?php echo $cab['cae']; ?></strong><br>
                                Vto. CAE: <strong><?php echo date('d/m/Y', strtotime($cab['fechacae'])); ?></strong>
                            </div>
                            <div style="flex-grow:1; text-align:center; font-style:italic; font-size: 7.5px; color: #555;">
                                <?php echo $isTicket ? 'Comprobante ARCA' : 'Comprobante autorizado por ARCA.'; ?>
                            </div>
                            <div style="text-align:right">
                                <img src="img/logoarca.jpg" style="height:6mm; opacity:0.8">
                            </div>
                        </div>
                    <?php else: ?>
                        <div
                            style="text-align: center; border: 1.5pt solid #d32f2f; color: #d32f2f; padding: 2mm; font-weight: 800; font-size: 12px;">
                            NO VÁLIDO COMO FACTURA</div>
                    <?php endif; ?>
                </div>

                <?php if ($leyendaPie !== ''): ?>
                    <div style="text-align: center; margin-top: 3mm; font-size: 9px; color: #333;">
                        <?php echo htmlspecialchars($leyendaPie); ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <div class="page-footer">
                <div>Sistema West IT ERP (https://westit.com.ar)</div>
                <div>Cantidad de Artículos.: <?php echo number_format($totalUnidadesCount, 0); ?></div>
                <?php if (!$isTicket): ?>
                    <div>Hoja <?php echo ($nPag + 1); ?> / <?php echo $totalPaginas; ?></div><?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>

</body>

</html>
