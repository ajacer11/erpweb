<?php
/**
 * Columnas siniva..siniva8 (netos de venta de las 8 listas) recalculadas desde el precio final, que es el que
 * manda (igual que normalizarListasParaVenta en facturación): neto = (final - imp. interno) / (1 + alícuota).
 * Así un neto guardado desfasado (ej. alícuota cambiada sin recalcular el neto) no cambia el precio en pedidos
 * y presupuestos. A 2 decimales, la escala de preciounit en las líneas.
 * $a: alias de alb_productos en la consulta ('' si no tiene).
 */
function sqlNetosListas(string $a = ''): string
{
    $p = $a !== '' ? "$a." : '';
    $cols = [];
    for ($i = 1; $i <= 8; $i++) {
        $neto = $i === 1 ? 'siniva' : "siniva$i";
        $final = $i === 1 ? 'precio' : "precio$i";
        $cols[] = "ROUND(({$p}$final - IFNULL({$p}ImpInt, 0)) / (1 + IFNULL({$p}poriva, 0) / 100), 2) AS $neto";
    }
    return implode(', ', $cols);
}
