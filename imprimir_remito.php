<?php
/**
 * imprimir_remito.php - REMITO DE MERCADERÍA (A4, sin precios, por duplicado)
 *
 * Clon liviano de imprimir_ticket.php: mismos datos de empresa/cliente/depósito
 * emisor que la factura, pero sin CAE, sin QR, sin totales ni precios, y con
 * numeración propia (talonario "remitos_mercaderia", arranca en 6200 — ver
 * sql/2026-09-07_remitos_mercaderia.sql), independiente de la numeración
 * fiscal de "tickets". Se abre por navegación directa (window.open), no por
 * AJAX, igual que imprimir_ticket.php.
 *
 * Un comprobante tiene como máximo un remito (UNIQUE KEY id_ticket en la
 * tabla remitos_mercaderia): si ya existe se reutiliza tal cual, si no se
 * genera acá mismo, la primera vez que se pide.
 *
 * OJO: el nombre es "remitos_mercaderia" y no "remitos" a propósito — algunos
 * tenants migrados desde sistemas viejos ya tienen una tabla huérfana
 * "remitos" con otro esquema (ver sql/2026-09-07_remitos_mercaderia.sql).
 */
session_start();
require_once 'db.php';
require_once 'stock_deposito_helpers.php';

const ITEMS_POR_PAGINA = 35;
const NUMERO_INICIAL_REMITOS = 6200;

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) die("ID de comprobante no válido.");

try {
    // 1. Cabecera del comprobante (mismos datos que la factura)
    $sqlCab = "SELECT t.*,
                      t.ptovta as ptovta_alias, t.numcomp as numcomp_alias,
                      t.tipocomp as tipocomp_alias, t.lvdesde as lvdesde_alias,
                      t.lvhasta as lvhasta_alias,
                      c.razon as cliente_nombre, c.domicilio as cli_dom, c.localidad as cli_loc,
                      c.numdoc as cli_doc, c.condicionfiscal as cli_cond,
                      v.nombre as vendedor_nombre
               FROM tickets t
               LEFT JOIN alb_clientes c ON t.cliente = c.id
               LEFT JOIN vendedores v ON t.vendedor = v.id
               WHERE t.id = ?";
    $stmtCab = $pdo->prepare($sqlCab);
    $stmtCab->execute([$id]);
    $cab = $stmtCab->fetch(PDO::FETCH_ASSOC);
    if (!$cab) die("El comprobante no existe.");

    // 2. Datos de empresa (+ depósito emisor, mismo criterio que imprimir_ticket.php)
    $stmtLogo = $pdo->query("SELECT logo, mostrarvendedor FROM configuracion ORDER BY id DESC LIMIT 1");
    $logoData = $stmtLogo->fetch(PDO::FETCH_ASSOC);
    $logoBase64 = ($logoData && !empty($logoData['logo'])) ? base64_encode($logoData['logo']) : null;
    $logoMime = 'image/png';
    if ($logoBase64) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $logoMime = $finfo->buffer($logoData['logo']) ?: 'image/png';
    }
    $mostrarVendedor = !isset($logoData['mostrarvendedor']) || (int)$logoData['mostrarvendedor'] === 1;

    $stmtEmp = $pdo->query("SELECT * FROM datosempresa ORDER BY id DESC LIMIT 1");
    $emp = $stmtEmp->fetch(PDO::FETCH_ASSOC);
    if (!$emp) {
        $emp = ['razon' => 'EMPRESA NO CONFIGURADA', 'domicilio' => '', 'localidad' => '', 'provincia' => '', 'cuit' => '00-00000000-0'];
    }

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
            if (!empty($depEmisor['logo'])) {
                $logoBase64 = base64_encode($depEmisor['logo']);
                $logoMime = (new finfo(FILEINFO_MIME_TYPE))->buffer($depEmisor['logo']) ?: 'image/png';
            }
        }
    }

    // 3. Comprobante asociado (para trazabilidad, no es lo que se numera acá)
    $tiposNombres = [
        1 => 'FACTURA A', 2 => 'NOTA DE DEBITO A', 3 => 'NOTA DE CREDITO A',
        6 => 'FACTURA B', 7 => 'NOTA DE DEBITO B', 8 => 'NOTA DE CREDITO B',
        11 => 'FACTURA C', 12 => 'NOTA DE DEBITO C', 13 => 'NOTA DE CREDITO C',
        100 => 'TICKET', 101 => 'TICKET DE CREDITO'
    ];
    $tipoOrigenStr = $tiposNombres[(int)$cab['tipocomp_alias']] ?? 'COMPROBANTE';
    $numeroOrigenStr = str_pad($cab['ptovta_alias'], 4, '0', STR_PAD_LEFT) . '-' . str_pad($cab['numcomp_alias'], 8, '0', STR_PAD_LEFT);

    // 4. Buscar o crear el remito de este comprobante (talonario propio, 6200+)
    $usuario = $_SESSION['usuario_nombre'] ?? 'SISTEMA';
    $pdo->beginTransaction();
    try {
        $stmtEx = $pdo->prepare("SELECT numero, fecha FROM remitos_mercaderia WHERE id_ticket = ?");
        $stmtEx->execute([$id]);
        $remito = $stmtEx->fetch(PDO::FETCH_ASSOC);

        if ($remito) {
            $numeroRemito = (int)$remito['numero'];
            $fechaRemito = $remito['fecha'];
        } else {
            $numeroRemito = null;
            $intentos = 0;
            while ($numeroRemito === null) {
                $intentos++;
                $candidato = (int)$pdo->query("SELECT COALESCE(MAX(numero), " . (NUMERO_INICIAL_REMITOS - 1) . ") + 1 FROM remitos_mercaderia")->fetchColumn();
                try {
                    $ins = $pdo->prepare("INSERT INTO remitos_mercaderia (id_ticket, numero, fecha, usuario) VALUES (?, ?, CURDATE(), ?)");
                    $ins->execute([$id, $candidato, $usuario]);
                    $numeroRemito = $candidato;
                } catch (PDOException $e) {
                    if ($e->getCode() !== '23000' || $intentos >= 5) throw $e;
                    // Colisión de numero (otra request concurrente) o de id_ticket
                    // (otra pestaña ya generó el remito de este mismo comprobante):
                    // si fue esto último, ya está resuelto, lo reutilizamos.
                    $stmtEx2 = $pdo->prepare("SELECT numero FROM remitos_mercaderia WHERE id_ticket = ?");
                    $stmtEx2->execute([$id]);
                    $yaExiste = $stmtEx2->fetchColumn();
                    if ($yaExiste !== false) $numeroRemito = (int)$yaExiste;
                }
            }
            $fechaRemito = date('Y-m-d');
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

    // 5. Ítems (mismas líneas que la factura, sin precios)
    $sqlDet = "SELECT prodcod, proddes, cant
               FROM Lineadeventa
               WHERE numcomp = ? AND pdv = ? AND tipocomp = ?
                 AND id >= ? AND id <= ?
               ORDER BY id ASC";
    $stmtDet = $pdo->prepare($sqlDet);
    $stmtDet->execute([$cab['numcomp_alias'], $cab['ptovta_alias'], $cab['tipocomp_alias'], $cab['lvdesde_alias'], $cab['lvhasta_alias']]);
    $lineasRaw = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

    $items = array_values(array_filter($lineasRaw, function ($l) {
        return !in_array(strtoupper($l['prodcod'] ?? ''), ['DESC', 'RECA']);
    }));

    $paginasItems = [];
    $tempItems = $items;
    while (count($tempItems) > 0) {
        $paginasItems[] = array_splice($tempItems, 0, ITEMS_POR_PAGINA);
    }
    if (empty($paginasItems)) $paginasItems[] = [];
    $totalPaginas = count($paginasItems);

    $totalUnidades = array_sum(array_column($items, 'cant'));

} catch (Exception $e) {
    die("Error crítico: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>REMITO #<?= $numeroRemito ?></title>
    <link rel="icon" type="image/png" href="isotipo.png">
    <style>
        * { box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        @page { size: A4; margin: 0; }
        body { font-family: Arial, Helvetica, sans-serif; color: black; margin: 0; padding: 0; background: #f5f5f5; font-size: 10px; }

        .sheet {
            width: 210mm; min-height: 297mm; margin: 5mm auto; background: white;
            padding: 10mm 12mm; box-shadow: 0 0 10px rgba(0,0,0,0.1);
            display: flex; flex-direction: column; position: relative; overflow: hidden;
        }

        .copia-label {
            display: inline-block; font-size: 10px; font-weight: 800;
            border: 1pt solid #002d5a; color: #002d5a; padding: 1mm 3mm;
            letter-spacing: 1px; margin-bottom: 2mm;
        }

        .header { display: flex; flex-direction: row; justify-content: space-between; border-bottom: 1.5pt solid #002d5a; padding-bottom: 3mm; margin-bottom: 3mm; text-align: left; }
        .logo-box img { max-width: 160px; max-height: 50px; object-fit: contain; }
        .company-info h2 { margin: 0; color: #002d5a; font-size: 13px; font-weight: 800; }
        .company-info p { margin: 1px 0; color: black; font-size: 9px; line-height: 1.1; }
        .doc-info { text-align: right; }
        .doc-type-box { display: inline-flex; flex-direction: column; align-items: center; justify-content: center; border: 1.5pt solid #002d5a; padding: 3px 10px; font-weight: 800; margin: 1mm 0; line-height: 1; }
        .doc-letter { font-size: 18px; }
        .doc-code { font-size: 8px; font-weight: 800; margin-top: 2px; }

        .info-grid { display: grid; grid-template-columns: 1.1fr 0.9fr; gap: 2mm; margin-bottom: 3mm; }
        .box { border: 0.5pt solid #ccc; padding: 2mm; border-radius: 2px; background: #fcfcfc; }
        .box h3 { margin: 0 0 1mm 0; font-size: 8px; text-transform: uppercase; color: black; border-bottom: 0.5pt solid #ccc; padding-bottom: 0.5mm; }

        table.items { width: 100%; border-collapse: collapse; margin-top: 2mm; }
        table.items thead th { text-align: left; font-size: 8px; text-transform: uppercase; color: #002d5a; border-bottom: 1.5pt solid #002d5a; padding: 1.5mm 1mm; }
        table.items thead th.col-cant { width: 20mm; text-align: right; }
        table.items tbody td { font-size: 10px; padding: 1.5mm 1mm; border-bottom: 0.5pt solid #eee; page-break-inside: avoid; }
        table.items tbody td.col-cant { text-align: right; font-weight: 700; }

        /* Firmas + pie de página van SIEMPRE pegados al fondo físico de la hoja
           (no solo debajo de los ítems): un único wrapper con margin-top:auto
           empuja todo el bloque hacia abajo dentro del .sheet flex-column. */
        .pie-hoja { margin-top: auto; }
        .page-footer { border-top: 0.5pt solid #ddd; font-size: 9px; display: flex; justify-content: space-between; color: black; padding-top: 2mm; }
        .firma-box { display: flex; justify-content: space-between; margin-bottom: 8mm; gap: 10mm; }
        .firma-box div { flex: 1; border-top: 0.5pt solid #666; padding-top: 1mm; text-align: center; font-size: 8px; color: #555; }

        @media print {
            .btn-print { display: none; }
            body { background: white; }
            .sheet { box-shadow: none; margin: 0; width: 100%; page-break-after: always; }
            .sheet:last-child { page-break-after: auto; }
        }
    </style>
</head>

<body>
    <button style="position:fixed;bottom:20px;right:20px;padding:10px 20px;z-index:999" class="btn-print" onclick="window.print()">IMPRIMIR / PDF</button>

    <?php foreach (['ORIGINAL', 'DUPLICADO'] as $copia): ?>
        <?php foreach ($paginasItems as $nPag => $itemsEnPagina):
            $esUltimaPagina = ($nPag + 1 == $totalPaginas);
        ?>
            <div class="sheet">
                <div class="header">
                    <div class="logo-side">
                        <div class="logo-box">
                            <?php if ($logoBase64): ?>
                                <img src="data:<?= htmlspecialchars($logoMime) ?>;base64,<?= $logoBase64 ?>" alt="Logo">
                            <?php endif; ?>
                        </div>
                        <div class="company-info">
                            <h2><?= htmlspecialchars($emp['razon']) ?></h2>
                            <p><?= htmlspecialchars($emp['domicilio'] . ' - ' . $emp['localidad']) ?></p>
                            <p>CUIT: <?= htmlspecialchars($emp['cuit']) ?></p>
                        </div>
                    </div>
                    <div class="doc-info">
                        <div class="copia-label"><?= $copia ?></div>
                        <div class="doc-type-box">
                            <div class="doc-letter">R</div>
                            <div class="doc-code">REMITO</div>
                        </div>
                        <p class="fw-bold" style="font-weight:700">N° <?= str_pad($numeroRemito, 8, '0', STR_PAD_LEFT) ?></p>
                        <p>Fecha: <?= date('d/m/Y', strtotime($fechaRemito)) ?></p>
                        <p style="font-size:8px; color:#555">Comprob. asociado: <?= htmlspecialchars($tipoOrigenStr) ?> <?= $numeroOrigenStr ?></p>
                    </div>
                </div>

                <div class="info-grid">
                    <div class="box">
                        <h3>DESTINATARIO</h3>
                        <p><strong><?= htmlspecialchars($cab['cliente_nombre']) ?></strong></p>
                        <p><?= htmlspecialchars($cab['cli_doc']) ?> | <?= htmlspecialchars($cab['cli_cond']) ?></p>
                        <p><?= htmlspecialchars($cab['cli_dom'] . ' (' . $cab['cli_loc'] . ')') ?></p>
                    </div>
                    <div class="box">
                        <h3>INFO ENTREGA</h3>
                        <?php if ($mostrarVendedor): ?>
                            <p>Vendedor: <strong><?= htmlspecialchars($cab['vendedor_nombre'] ?: 'S/V') ?></strong></p>
                        <?php endif; ?>
                        <p>Página: <strong><?= ($nPag + 1) ?> / <?= $totalPaginas ?></strong></p>
                    </div>
                </div>

                <table class="items">
                    <thead>
                        <tr><th class="col-cant">Cant.</th><th>Código</th><th>Descripción</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($itemsEnPagina as $row): ?>
                            <tr>
                                <td class="col-cant"><?= number_format((float)$row['cant'], 0) ?></td>
                                <td><?= htmlspecialchars($row['prodcod']) ?></td>
                                <td><?= htmlspecialchars($row['proddes']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="pie-hoja">
                    <?php if ($esUltimaPagina): ?>
                        <div class="firma-box">
                            <div>Firma y aclaración de quien entrega</div>
                            <div>Firma, aclaración y DNI de quien recibe</div>
                        </div>
                    <?php endif; ?>

                    <div class="page-footer">
                        <div>Sistema West IT ERP (https://westit.com.ar)</div>
                        <div>Cant. Artículos: <?= number_format($totalUnidades, 0) ?></div>
                        <div>Hoja <?= ($nPag + 1) ?> / <?= $totalPaginas ?></div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endforeach; ?>
</body>

</html>
