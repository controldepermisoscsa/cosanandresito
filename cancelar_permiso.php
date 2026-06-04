<?php
session_start();
require 'conexion.php';
require_once 'estado_correo_manager.php';

// Verificar si el usuario ha iniciado sesión y tiene el cargo de Gerencia (tolerante a mayúsc/minúsc)
if (!isset($_SESSION['usuario_id']) || !in_array(strtolower($_SESSION['cargo'] ?? ''), ['gerente', 'gerencia'])) {
    header('Location: login.php');
    exit();
}

$idPermiso = $_GET['id'] ?? null;
if (!$idPermiso) {
    header('Location: gerente_inicio.php?msg=ID de permiso no válido');
    exit();
}

$stmt = $pdo->prepare("UPDATE permisos SET estado = 'cancelado', asignado_a = NULL, id_asignado = NULL WHERE id_permiso = ?");
$stmt->execute([$idPermiso]);

if ($stmt->rowCount() > 0) {
    // Notificar
    $manager = new EstadoCorreoManager($pdo);
    $manager->gerenteCancela($idPermiso);
    header('Location: gerente_inicio.php?msg=Permiso cancelado correctamente');
} else {
    header('Location: gerente_inicio.php?msg=No se pudo cancelar el permiso');
}
exit;