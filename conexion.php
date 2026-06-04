<?php
// Cargar configuración
// Si moviste config.php fuera del webroot usa: __DIR__ . '/../config.php'
// Si lo dejaste dentro del proyecto usa:       __DIR__ . '/config.php'
require_once __DIR__ . '/config.php';

try {
    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s;sslmode=require',
        DB_HOST,
        DB_PORT,
        DB_NAME
    );

    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

} catch (PDOException $e) {
    // En producción nunca mostrar el error real al usuario
    error_log('Error de conexión BD: ' . $e->getMessage());

    if (defined('APP_ENV') && APP_ENV === 'desarrollo') {
        die('Error de conexión: ' . $e->getMessage());
    }

    die('No se pudo conectar a la base de datos. Contacta al administrador.');
}
?>