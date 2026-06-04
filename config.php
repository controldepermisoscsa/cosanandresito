<?php
// =================================================================
// ARCHIVO DE CONFIGURACIÓN — NO SUBIR A GIT
// =================================================================
// Agrega config.php a tu .gitignore para no exponer credenciales.
//
// OPCIÓN IDEAL (hosting con acceso a carpeta raíz):
//   Mueve este archivo UNA carpeta arriba del proyecto:
//   /home/tuusuario/config.php        ← aquí el archivo
//   /home/tuusuario/public_html/      ← aquí el proyecto
//   Y en conexion.php usa: require_once __DIR__ . '/../config.php';
//
// OPCIÓN ALTERNATIVA (hosting compartido sin acceso a raíz):
//   Déjalo dentro del proyecto. El .htaccess incluido lo protege.
// =================================================================

// --- Supabase / PostgreSQL (Session Pooler) ---
define('DB_HOST', 'aws-1-us-east-1.pooler.supabase.com');
define('DB_PORT', '6543');
define('DB_NAME', 'postgres');
define('DB_USER', 'postgres.poajlhnksbdrjqfslxqu');
define('DB_PASS', 'controldepermisoscsa@gmail.com');

// --- Correo SMTP ---
define('SMTP_HOST',     'smtp.gmail.com');
define('SMTP_PORT',     587);
define('SMTP_USER',     'tucorreo@dominio.com');    // ← tu correo real
define('SMTP_PASS',     'tu_app_password_aqui');    // ← App Password de Gmail
define('SMTP_FROM',     'tucorreo@dominio.com');    // ← igual al anterior
define('SMTP_NAME',     'Sistema Coosanandresito');

// --- URL base (sin barra al final) ---
define('APP_URL', 'https://tudominio.com');         // ← tu dominio real

// --- Entorno ---
define('APP_ENV', 'produccion'); // 'produccion' o 'desarrollo'
