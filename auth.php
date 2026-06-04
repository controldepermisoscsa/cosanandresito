<?php
// auth.php - procesa login
require 'includes/conexion.php';
session_start();

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

if(!$email || !$password){
    header('Location: login.php');
    exit;
}

// Conexión
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if($conn->connect_error){
    die('Error de conexión: ' . $conn->connect_error);
}

$emailEsc = $conn->real_escape_string($email);
$sql = "SELECT id_usuario, nombre, correo, password, rol FROM usuarios WHERE correo = '$emailEsc' LIMIT 1";
$res = $conn->query($sql);

if($res && $res->num_rows === 1){
    $row = $res->fetch_assoc();
    // Aquí usamos SHA-256 en la base de datos para este ejemplo
    $hash = hash('sha256', $password);
    if($hash === $row['password']){
        // Login correcto
        $_SESSION['user_id'] = $row['id_usuario'];
        $_SESSION['user_name'] = $row['nombre'];
        $_SESSION['user_role'] = $row['rol'];
        header('Location: dashboard.php');
        exit;
    }
}

// Si llega aquí: login fallido
header('Location: login.php?error=1');
exit;
?>
