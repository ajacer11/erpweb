<?php
/**
 * certificado_descargar.php - UI PARA GENERAR SOLICITUD DE CERTIFICADO (CSR)
 */
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-4 fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="text-white mb-0"><i class="fas fa-key text-accent me-2"></i>SOLICITUD DE CERTIFICADO ARCA</h4>
            <small class="text-white-50">Generación de archivo .csr para el portal de ARCA/AFIP</small>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card card-filtros shadow-lg border-0">
                <div class="card-header bg-dark border-bottom border-secondary p-3">
                    <h5 class="text-white mb-0 fs-6"><i class="fas fa-file-signature me-2"></i>Datos de la Solicitud</h5>
                </div>
                <div class="card-body p-4">
                    <form id="formCSR">
                        <div class="mb-4">
                            <label class="form-label-custom mb-1">CUIT EMISOR <span class="text-danger">*</span></label>
                            <input type="text" id="cuit" class="form-control bg-dark text-white" required placeholder="Ej: 20123456789" pattern="[0-9]{11}" title="Ingrese los 11 números sin guiones">
                            <small class="text-muted d-block mt-1">Ingrese el CUIT sin guiones.</small>
                        </div>

                        <div class="mb-4">
                            <label class="form-label-custom mb-1">NOMBRE DEL ALIAS <span class="text-danger">*</span></label>
                            <input type="text" id="alias" class="form-control bg-dark text-white" required placeholder="Ej: facturacion_2026">
                            <small class="text-warning d-block mt-1"><i class="fas fa-exclamation-triangle me-1"></i>Debe coincidir EXACTAMENTE con el alias que creará en ARCA.</small>
                        </div>

                        <div class="mb-4">
                            <label class="form-label-custom mb-1">NOMBRE DEL ARCHIVO A DESCARGAR</label>
                            <input type="text" id="salida" class="form-control bg-dark text-white" value="solicitud" required>
                            <small class="text-muted d-block mt-1">El sistema le agregará la extensión .csr automáticamente.</small>
                        </div>

                        <hr class="border-secondary mb-4">

                        <button type="submit" class="btn btn-info w-100 fw-bold py-2 fs-6 shadow" id="btnGenerar">
                            <i class="fas fa-download me-2"></i>GENERAR Y DESCARGAR .CSR
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const form = document.getElementById('formCSR');
    const btn = document.getElementById('btnGenerar');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const payload = {
            cuit: document.getElementById('cuit').value,
            alias: document.getElementById('alias').value,
            salida: document.getElementById('salida').value
        };

        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>GENERANDO...';

        try {
            const res = await fetch('api_certificados.php?action=generate_csr', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(payload)
            }).then(r => r.json());

            if (res.success) {
                // Descarga dinámica del Blob (Sin redirecciones)
                const blob = new Blob([res.csr_content], { type: 'application/pkcs10' });
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = res.filename;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);

                Swal.fire({
                    icon: 'success',
                    title: '¡Generado!',
                    text: 'El archivo se ha descargado a tu computadora. Súbelo al portal de ARCA.',
                    background: '#001020',
                    color: '#fff'
                });
            } else {
                Swal.fire('Error', res.error, 'error');
            }
        } catch (error) {
            Swal.fire('Error', 'Falla de comunicación con el servidor.', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-download me-2"></i>GENERAR Y DESCARGAR .CSR';
        }
    });
})();
</script>