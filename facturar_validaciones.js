/**
 * facturar_validaciones.js - LÓGICA FISCAL Y DE NEGOCIO (V18.6)
 * Centraliza las reglas de emisor vs receptor y controles de cobro.
 */

window.billValidator = (function() {
        
    /**
     * Validación de Tipo de Comprobante según Empresa (Emisor ID 1) y Cliente (Receptor)
     */
    function validateInvoiceType(tipo, cliente, empresa) {
        if (!cliente || !empresa) return { isValid: true };

        const tipoInt = parseInt(tipo);
        const condEmpresa = (empresa.condicioniva || '').toUpperCase();
        const condCliente = (cliente.condicionfiscal || '').toUpperCase();
        
        const empresaEsRI = condEmpresa.includes('INSCRIPTO');
        const empresaEsMono = condEmpresa.includes('MONOTRIBUTO');
        const clienteEsRIMono = condCliente.includes('INSCRIPTO') || condCliente.includes('MONOTRIBUTO');

        // 1. REGLAS PARA EMPRESA RESPONSABLE INSCRIPTO
        if (empresaEsRI) {
            // Un RI solo puede emitir: 1, 2, 3, 6, 7, 8, 100, 101
            const permitidosRI = [1, 2, 3, 6, 7, 8, 100, 101]; 
            if (!permitidosRI.includes(tipoInt)) {
                return { isValid: false, message: "Como Responsable Inscripto, solo puede emitir Facturas A, B o Tickets (Tipo 1,2,3,6,7,8,100,101)." };
            }

            // Cruce con Cliente: A clientes RI/Monotributo solo se les emite A (o Ticket)
            if (clienteEsRIMono && [6, 7, 8].includes(tipoInt)) {
                return { isValid: false, message: "El cliente es RI o Monotributista. Debe emitir Factura A." };
            }
            // Cruce con Cliente: A clientes CF/Exentos solo se les emite B (o Ticket)
            if (!clienteEsRIMono && [1, 2, 3].includes(tipoInt)) {
                return { isValid: false, message: "El cliente es Consumidor Final o Exento. Debe emitir Factura B." };
            }
        } 
        
        // 2. REGLAS PARA EMPRESA MONOTRIBUTO
        else if (empresaEsMono) {
            // Un Monotributista solo puede emitir: 11, 12, 13, 100, 101
            const permitidosMono = [11, 12, 13, 100, 101];
            if (!permitidosMono.includes(tipoInt)) {
                return { isValid: false, message: "Como Monotributista, solo puede emitir Facturas C o Tickets (Tipo 11,12,13,100,101)." };
            }
        }

        return { isValid: true };
    }

    /**
     * Validación Integral Pre-Cobro
     */
    function validateBeforePay(data) {
        const { cart, clienteSeleccionado, total, vendedor, tipoComp, m1, m2, imp1, imp2, maestros } = data;
        
        // Validaciones Básicas
        if (!clienteSeleccionado) return { isValid: false, message: "Seleccione un cliente." };
        if (!vendedor || vendedor === "") return { isValid: false, message: "Seleccione un vendedor." };
        if (!m1) return { isValid: false, message: "Debe elegir al menos un medio de pago." };
        if (m1 && m2 && m1 === m2) return { isValid: false, message: "Los medios de pago no pueden ser iguales." };

        // Validación Fiscal Cruzada (Emisor vs Receptor)
        const checkFiscal = validateInvoiceType(tipoComp, clienteSeleccionado, maestros.empresa);
        if (!checkFiscal.isValid) return checkFiscal;

        // Validación de Importes
        const totalDoc = parseFloat(total.replace(/\./g,'').replace(',','.'));
        const totalCobrado = parseFloat(imp1 || 0) + parseFloat(imp2 || 0);
        if (totalCobrado < (totalDoc - 0.01)) {
            return { isValid: false, message: `El importe cobrado ($${totalCobrado.toFixed(2)}) no cubre el total ($${totalDoc.toFixed(2)}).` };
        }

        // Validación de Cuenta Corriente
        const medios = [m1, m2].filter(x => x);
        for (let id of medios) {
            const mData = maestros.medios_pago.find(x => x.id == id);
            if (mData && mData.tipodecuenta === 'Cuenta Corriente') {
                if (parseInt(clienteSeleccionado.cuentacorriente) === 0) {
                    return { isValid: false, message: "El cliente no tiene habilitada la Cuenta Corriente." };
                }
            }
        }

        // Validación de Asociados para NC/ND
        if ([2, 3, 7, 8, 12, 13, 101].includes(parseInt(tipoComp)) && !data.associatedDoc) {
            return { isValid: false, message: "Debe asociar un comprobante para Notas de Crédito/Débito." };
        }

        return { isValid: true };
    }

    return {
        validateBeforePay,
        validateInvoiceType
    };

})();