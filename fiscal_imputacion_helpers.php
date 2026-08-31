<?php

function fiscalUnicoDeFilas(array $filas, array $idsEsperados, string $entidad): int
{
    $idsEncontrados = [];
    $fiscales = [];

    foreach ($filas as $fila) {
        $idsEncontrados[(int)$fila['id']] = true;
        $fiscales[(int)$fila['fiscal']] = true;
    }

    foreach ($idsEsperados as $id) {
        if (!isset($idsEncontrados[$id])) {
            throw new Exception("No se encontró $entidad #$id o no pertenece a la cuenta seleccionada.");
        }
    }

    if (count($fiscales) !== 1) {
        throw new Exception("No se pueden mezclar registros fiscales y no fiscales en la misma operación.");
    }

    return (int)array_key_first($fiscales);
}

function idsSeleccionados(array $filas): array
{
    return array_values(array_unique(array_filter(
        array_map(static fn($fila) => (int)($fila['id'] ?? 0), $filas),
        static fn($id) => $id > 0
    )));
}

function placeholdersSql(array $ids): string
{
    return implode(',', array_fill(0, count($ids), '?'));
}

function validarMismaCondicionFiscal(int $fiscalOrigen, int $fiscalDestino): void
{
    if ($fiscalOrigen !== $fiscalDestino) {
        throw new Exception('La operación fue rechazada: un pago o cobro fiscal solo puede imputarse a comprobantes fiscales, y uno no fiscal solo a comprobantes no fiscales.');
    }
}
