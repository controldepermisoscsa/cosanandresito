<?php
session_start();
require 'conexion.php';

// Verificar si el usuario tiene el cargo de Administrador
if (!isset($_SESSION['usuario_id']) || strtolower($_SESSION['cargo'] ?? '') !== 'administrador') {
    header('Location: login.php');
    exit();
}

// Obtener el ID del usuario a eliminar
$id_usuario = $_GET['id'] ?? null;

if ($id_usuario) {
    // Eliminar el usuario de la base de datos
    $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id_usuario = :id_usuario");
    $stmt->execute(['id_usuario' => $id_usuario]);

    // Redirigir al panel de administrador
    header('Location: admin_inicio.php?mensaje=Usuario eliminado correctamente');
    exit();
} else {
    header('Location: admin_inicio.php?mensaje=Error al eliminar usuario');
    exit();
}
?>