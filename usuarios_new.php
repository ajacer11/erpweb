<?php
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="text-white mb-0">GESTIÓN DE USUARIOS WEB</h4>
            <small class="text-accent opacity-75">Registro de nuevos accesos</small>
        </div>
        <button class="btn btn-outline-secondary rounded-pill" onclick="loadModule('usuarios_admin.php')">
            <i class="fas fa-list me-2"></i>VER LISTADO
        </button>
    </div>

    <div class="row justify-content-center">
        <div class="col-xl-6 col-lg-8">
            <div class="card card-filtros shadow-lg">
                <div class="card-header bg-navbar border-secondary py-3">
                    <h5 class="card-title text-white mb-0"><i class="fas fa-user-plus me-2 text-info"></i>NUEVO PERFIL DE ACCESO</h5>
                </div>
                <div class="card-body p-4">
                    <form id="formNuevoUsuario">
                        <div class="row g-4">
                            <div class="col-md-12">
                                <label class="form-label-custom">NOMBRE DE USUARIO / LOGIN</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-dark border-secondary text-info"><i class="fas fa-at"></i></span>
                                    <input type="text" id="txtUsuario" name="usuario" class="form-control ignore-uppercase" placeholder="Ej: jsmith" required autofocus>
                                </div>
                            </div>

                            <div class="col-md-12">
                                <label class="form-label-custom">CONTRASEÑA WEB</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-dark border-secondary text-info"><i class="fas fa-key"></i></span>
                                    <input type="password" id="txtPassWeb" name="passweb" class="form-control ignore-uppercase" placeholder="••••••••" required>
                                    <button class="btn btn-outline-secondary border-secondary" type="button" id="btnTogglePass">
                                        <i class="fas fa-eye" id="eyeIcon"></i>
                                    </button>
                                </div>
                                <div class="mt-2" style="font-size: 0.75rem; color: #aaa;">
                                    <i class="fas fa-info-circle me-1"></i> Requisitos: 8+ caracteres, Mayúscula, Minúscula, Número y Especial.
                                </div>
                            </div>

                            <hr class="border-secondary opacity-25">

                            <div class="col-md-12 d-flex justify-content-center">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="chkAdmin" name="esadmin">
                                    <label class="form-check-label text-white" for="chkAdmin">PERFIL ADMINISTRADOR</label>
                                </div>
                            </div>

                            <div class="col-md-12 mt-4 pt-3 border-top border-secondary">
                                <button type="submit" class="btn btn-info fw-bold w-100 py-2 rounded-pill shadow" id="btnGuardarUser">
                                    <i class="fas fa-save me-2"></i>CREAR USUARIO Y ACTIVAR ACCESO
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    // LÓGICA PARA PERMITIR MINÚSCULAS
    // Al entrar a los inputs sensibles, desactivamos el forzado global de index.php
    $('.ignore-uppercase').on('focus', function() {
        window.FORCE_UPPERCASE_DISABLED = true;
    }).on('blur', function() {
        window.FORCE_UPPERCASE_DISABLED = false;
    });

    $('#btnTogglePass').on('click', function() {
        const input = $('#txtPassWeb');
        const icon = $('#eyeIcon');
        if (input.attr('type') === 'password') {
            input.attr('type', 'text');
            icon.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
            input.attr('type', 'password');
            icon.removeClass('fa-eye-slash').addClass('fa-eye');
        }
    });

    $('#formNuevoUsuario').on('submit', async function(e) {
        e.preventDefault();
        
        const pass = $('#txtPassWeb').val();
        const passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&._#\-\(\)\[\]\{\}])[A-Za-z\d@$!%*?&._#\-\(\)\[\]\{\}]{8,}$/;

        if (!passwordRegex.test(pass)) {
            return Swal.fire({
                icon: 'warning',
                title: 'Contraseña Débil',
                html: '<div class="text-start small">' +
                      'La contraseña debe cumplir con lo siguiente:<br>' +
                      '• Al menos 8 caracteres.<br>' +
                      '• Al menos una letra <b>minúscula</b>.<br>' +
                      '• Al menos una letra <b>mayúscula</b>.<br>' +
                      '• Al menos un número.<br>' +
                      '• Un carácter especial (@$!%*?&. etc.).</div>',
                background: '#001020',
                color: '#fff',
                confirmButtonColor: 'var(--accent)'
            });
        }

        const payload = {
            usuario: $('#txtUsuario').val().trim(), // Quitamos el toLowerCase automático para dar flexibilidad
            passweb: pass,
            esadmin: $('#chkAdmin').is(':checked') ? 1 : 0
        };

        try {
            $('#btnGuardarUser').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>PROCESANDO...');
            const resp = await fetch('api_usuarios.php?action=create', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(payload)
            });
            const result = await resp.json();
            if (result.success) {
                Swal.fire({ icon: 'success', title: '¡Usuario Creado!', background: '#001020', color: '#fff' })
                .then(() => loadModule('usuarios_admin.php'));
            } else throw new Error(result.error);
        } catch (e) {
            Swal.fire('Error', e.message, 'error');
        } finally {
            $('#btnGuardarUser').prop('disabled', false).html('<i class="fas fa-save me-2"></i>CREAR USUARIO Y ACTIVAR ACCESO');
        }
    });
})();
</script>