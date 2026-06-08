<?php
$host = 'aws-1-us-east-1.pooler.supabase.com';
$db = 'postgres';
$user = 'postgres.poajlhnksbdrjqfslxqu';
$pass = 'controldepermisoscsa@gmail.com';
$port = 6543;
$charset = 'UTF8';

$dsn = "pgsql:host=$host;port=$port;dbname=$db;";

try {
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    $error_code = $e->getCode();
    if ($error_code === '08006') {
        die("No se pudo conectar a la base de datos PostgreSQL. Verifica la conexión a internet y las credenciales. Contacta al administrador.");
    } elseif ($error_code === '28P01') {
        die("No se pudo conectar a la base de datos. Las credenciales de acceso son incorrectas. Contacta al administrador.");
    } else {
        die("No se pudo conectar a la base de datos. Contacta al administrador.");
    }
}
?>