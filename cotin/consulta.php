<?php
/**
 * reimpresion_comprobantes.php - MÓDULO AUTÓNOMO DE REIMPRESIÓN PREMIUM
 * Edición Exclusiva: COTINHAO SAS (Black & Gold Luxury)
 * Nota: Este archivo es independiente del ERP y no requiere check_access.php.
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>COTINHAO SAS - Reimpresión</title>
    
    <!-- Google Fonts: Cinzel (Elegante/Gold) y Montserrat (Moderno) -->
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=Montserrat:wght@300;400;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 & FontAwesome -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        :root {
            --gold-primary: #D4AF37;
            --gold-secondary: #FFD700;
            --gold-soft: #f1d592;
            --bg-black: #050505;
            --card-black: #0f0f0f;
            --accent-border: rgba(212, 175, 55, 0.3);
        }

        body {
            background-color: var(--bg-black);
            color: #fff;
            font-family: 'Montserrat', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            overflow-x: hidden;
        }

        /* Fondo Decorativo */
        body::before {
            content: "";
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: radial-gradient(circle at 50% 50%, rgba(212, 175, 55, 0.05) 0%, transparent 70%);
            z-index: -1;
        }

        .luxury-card {
            background: var(--card-black);
            border: 1px solid var(--accent-border);
            border-radius: 24px;
            width: 100%;
            max-width: 480px;
            padding: 50px 40px;
            box-shadow: 0 30px 60px rgba(0,0,0,0.8), 
                        0 0 40px rgba(212, 175, 55, 0.05);
            position: relative;
            animation: fadeInScale 1s cubic-bezier(0.19, 1, 0.22, 1);
        }

        @keyframes fadeInScale {
            from { opacity: 0; transform: scale(0.95) translateY(20px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }

        .logo-box {
            text-align: center;
            margin-bottom: 35px;
        }

        .main-logo {
            max-width: 220px;
            filter: drop-shadow(0 0 8px rgba(212, 175, 55, 0.4));
        }

        .company-name {
            font-family: 'Cinzel', serif;
            color: var(--gold-primary);
            font-size: 14px;
            letter-spacing: 6px;
            text-transform: uppercase;
            margin-top: 15px;
            font-weight: 700;
        }

        .page-title {
            font-family: 'Cinzel', serif;
            color: #fff;
            font-size: 26px;
            text-align: center;
            margin-bottom: 40px;
            background: linear-gradient(to right, #D4AF37, #FFF, #D4AF37);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: 400;
        }

        /* Estilos de Formulario */
        .luxury-label {
            font-family: 'Cinzel', serif;
            color: var(--gold-soft);
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 10px;
            display: block;
        }

        .luxury-input {
            background: rgba(255,255,255,0.02) !important;
            border: 1px solid #2a2a2a !important;
            color: #fff !important;
            border-radius: 12px !important;
            padding: 14px 20px !important;
            font-size: 15px !important;
            transition: all 0.4s ease !important;
        }

        .luxury-input:focus {
            border-color: var(--gold-primary) !important;
            box-shadow: 0 0 15px rgba(212, 175, 55, 0.1) !important;
            background: #000 !important;
        }

        /* Modern Toggle Switch */
        .toggle-wrapper {
            background: #000;
            border: 1px solid #2a2a2a;
            border-radius: 14px;
            display: flex;
            padding: 5px;
            position: relative;
            margin-top: 10px;
        }

        .toggle-option {
            flex: 1;
            text-align: center;
            padding: 12px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #666;
            cursor: pointer;
            z-index: 1;
            transition: color 0.3s ease;
        }

        .toggle-option.active {
            color: #000;
        }

        .toggle-selection {
            position: absolute;
            width: calc(50% - 5px);
            height: calc(100% - 10px);
            background: linear-gradient(135deg, var(--gold-primary), var(--gold-secondary));
            border-radius: 10px;
            top: 5px;
            left: 5px;
            transition: transform 0.4s cubic-bezier(0.65, 0, 0.35, 1);
            box-shadow: 0 5px 15px rgba(212, 175, 55, 0.3);
        }

        input[value="ticket"]:checked ~ .toggle-selection {
            transform: translateX(100%);
        }

        /* Botón de Acción */
        .btn-luxury {
            background: linear-gradient(135deg, #b8860b, var(--gold-primary));
            border: none;
            color: #000 !important;
            font-family: 'Cinzel', serif;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 3px;
            padding: 18px;
            border-radius: 15px;
            width: 100%;
            margin-top: 40px;
            transition: all 0.4s ease;
            box-shadow: 0 10px 20px rgba(0,0,0,0.4);
        }

        .btn-luxury:hover {
            transform: translateY(-4px);
            box-shadow: 0 15px 30px rgba(212, 175, 55, 0.2);
            filter: brightness(1.1);
        }

        .btn-luxury:active {
            transform: translateY(-1px);
        }

        select option {
            background: var(--card-black);
            color: #fff;
        }

    </style>
</head>
<body>

<div class="luxury-card">
    <div class="logo-box">
        <!-- Logo prioritario personalizado -->
        <img src="logo.png" alt="COTINHAO" class="main-logo" onerror="this.src='../img/logo.png'; this.onerror=null;">
        <br>
        <div class="company-name" style="margin-top: 20px;">UN DESARROLLO EXCLUSIVO PARA</div>
        <div class="company-name">COTINAHO SAS</div>
    </div>

    <h2 class="page-title">Reimpresión de Comprobantes</h2>

    <form action="imprimir.php" method="GET" target="_blank" id="reprintForm">
        
        <div class="mb-4">
            <label class="luxury-label">Tipo de Comprobante</label>
            <select name="tipocomp" class="form-select luxury-input">
                <option value="1">FACTURA A</option>
                <option value="3">NOTA DE CRÉDITO A</option>
                <option value="6" selected>FACTURA B</option>
                <option value="8">NOTA DE CRÉDITO B</option>
            </select>
        </div>

        <div class="mb-4">
            <label class="luxury-label">Número de Comprobante</label>
            <input type="number" name="numero" class="form-control luxury-input" 
                   placeholder="Ingrese el número..." required autofocus>
        </div>

        <div class="mb-3">
            <label class="luxury-label">Formato de Impresión</label>
            <div class="toggle-wrapper">
                <input type="radio" name="format" value="A4" id="fmtA4" checked style="display:none">
                <input type="radio" name="format" value="ticket" id="fmtTicket" style="display:none">
                
                <div class="toggle-selection"></div>
                
                <label for="fmtA4" class="toggle-option" id="lblA4">Hoja A4</label>
                <label for="fmtTicket" class="toggle-option" id="lblTicket">Ticket 80mm</label>
            </div>
        </div>

        <button type="submit" class="btn btn-luxury">
            <i class="fa-solid fa-print me-2"></i> VER COMPROBANTE
        </button>

    </form>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    (function() {
        const form = document.getElementById('reprintForm');
        const optA4 = document.getElementById('fmtA4');
        const optTicket = document.getElementById('fmtTicket');
        const lblA4 = document.getElementById('lblA4');
        const lblTicket = document.getElementById('lblTicket');

        function updateToggleState() {
            if (optA4.checked) {
                lblA4.classList.add('active');
                lblTicket.classList.remove('active');
            } else {
                lblTicket.classList.add('active');
                lblA4.classList.remove('active');
            }
        }

        optA4.addEventListener('change', updateToggleState);
        optTicket.addEventListener('change', updateToggleState);
        updateToggleState();

        form.addEventListener('submit', function() {
            const inputNumero = form.querySelector('input[name="numero"]');
            
            // Efecto de carga elegante
            Swal.fire({
                title: 'Procesando...',
                text: 'Preparando documento para COTINHAO SAS',
                icon: 'info',
                showConfirmButton: false,
                timer: 2000,
                timerProgressBar: true,
                background: '#0f0f0f',
                color: '#D4AF37',
                toast: true,
                position: 'top-end'
            });

            setTimeout(() => {
                inputNumero.value = "";
                inputNumero.focus();
            }, 600);
        });
    })();
</script>

</body>
</html>