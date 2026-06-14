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

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        UPDATE permisos
        SET estado = 'cancelado', asignado_a = NULL, id_asignado = NULL
        WHERE id_permiso = ?
          AND estado NOT IN ('cancelado','finalizado')
    ");
    $stmt->execute([$idPermiso]);

    if ($stmt->rowCount() === 0) {
        $pdo->rollBack();
        header('Location: gerente_inicio.php?msg=El permiso ya fue cancelado o finalizado');
        exit();
    }

    $pdo->commit();

    $manager = new EstadoCorreoManager($pdo);
    $manager->gerenteCancela($idPermiso);
    header('Location: gerente_inicio.php?msg=Permiso cancelado correctamente');
    exit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("ERROR cancelar_permiso.php: " . $e->getMessage());
    header('Location: gerente_inicio.php?msg=Error al cancelar el permiso');
    exit();
}