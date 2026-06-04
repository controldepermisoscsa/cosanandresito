<?php
session_start();
require 'conexion.php';
require_once 'estado_correo_manager.php';

$idPermiso = $_GET['id'] ?? null;

if (!$idPermiso) {
    header('Location: gerente_inicio.php?msg=ID de permiso no válido');
    exit();
}

// Solo Gerencia
if (!isset($_SESSION['usuario_id']) || !in_array(strtolower($_SESSION['cargo'] ?? ''), ['gerente', 'gerencia'])) {
    header('Location: login.php');
    exit();
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("UPDATE permisos SET estado = 'aprobado', asignado_a = NULL, id_asignado = NULL, motivo_rechazo = NULL WHERE id_permiso = ?");
    $stmt->execute([$idPermiso]);

    if ($stmt->rowCount() === 0) {
        $pdo->rollBack();
        header('Location: gerente_inicio.php?msg=No se pudo aprobar la solicitud');
        exit();
    }

    // Notificar
    $manager = new EstadoCorreoManager($pdo);
    $manager->gerenteAprueba($idPermiso);

    $pdo->commit();
    header('Location: gerente_inicio.php?msg=Solicitud aprobada correctamente');
    exit();
} catch (Exception $e) {
    if (isset($pdo)) $pdo->rollBack();
    error_log("ERROR aprobar_permiso.php: " . $e->getMessage());
    header('Location: gerente_inicio.php?msg=Error al aprobar la solicitud');
    exit();
}