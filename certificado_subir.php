<?php
/**
 * certificado_subir.php - UI PARA SUBIR CERTIFICADO AUTORIZADO (.CRT)
 */
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-4 fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="text-white mb-0"><i class="fas fa-upload text-accent me-2"></i>SUBIR CERTIFICADO ARCA</h4>
            <small class="text-white-50">Cargar el archivo .crt devuelto por ARCA para activar la facturación</small>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card card-filtros shadow-lg border-0">
                <div class="card-body p-5 text-center">
                    
                    <div class="mb-4 text-info opacity-75">
                        <i class="fas fa-certificate fa-4x mb-3"></i>
                        <p class="mb-0">Seleccione el archivo con extensión <b>.crt</b> que descargó del portal de ARCA/AFIP.</p>
                    </div>

                    <form id="formUpload" enctype="multipart/form-data">
                        <div class="mb-4 text-start">
                            <input class="form-control form-control-lg bg-dark text-white border-secondary" type="file" id="fileCertificado" accept=".crt,.pem" required>
                        </div>

                        <hr class="border-secondary mb-4">

                        <button type="submit" class="btn btn-success w-100 fw-bold py-2 fs-6 shadow" id="btnSubir">
                            <i class="fas fa-cloud-upload-alt me-2"></i>GUARDAR CERTIFICADO
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const form = document.getElementById('formUpload');
    const btn = document.getElementById('btnSubir');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const fileInput = document.getElementById('fileCertificado');
        if (fileInput.files.length === 0) {
            Swal.fire('Atención', 'Seleccione un archivo primero.', 'warning');
            return;
        }

        const formData = new FormData();
        formData.append('certificado', fileInput.files[0]);

        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>SUBIENDO...';

        try {
            const res = await fetch('api_certificados.php?action=upload_crt', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            }).then(r => r.json());

            if (res.success) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Completado!',
                    text: res.message,
                    background: '#001020',
                    color: '#fff'
                });
                form.reset();
            } else {
                Swal.fire('Error', res.error, 'error');
            }
        } catch (error) {
            Swal.fire('Error', 'Falla de comunicación con el servidor.', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-cloud-upload-alt me-2"></i>GUARDAR CERTIFICADO';
        }
    });
})();
</script>