<?php
/**
 * Copiar como config_mail.php (gitignoreado) y completar.
 *
 * Casilla de WEST IT desde la que salen por defecto los mails de las webs de clientes
 * (invitaciones, recuperar clave, avisos de pedido). Cada mail sale con el nombre de la
 * empresa y "responder a" el mail de contacto de la empresa.
 *
 * clave_cifrado: texto aleatorio largo (ej. bin2hex(random_bytes(32))). Cifra las claves
 * SMTP propias que carguen las empresas. NO cambiarla después: las claves ya guardadas
 * dejarían de poder leerse.
 */
return [
    'smtp_host' => 'mail.westit.com.ar',
    'smtp_puerto' => 587,
    'smtp_seguridad' => 'tls',   // tls | ssl | none
    'smtp_usuario' => 'no-responder@westit.com.ar',
    'smtp_clave' => '',
    'remitente' => 'no-responder@westit.com.ar',
    'clave_cifrado' => '',
];
