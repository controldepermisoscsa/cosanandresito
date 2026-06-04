<?php
session_start();
require 'conexion.php';
require_once 'estado_correo_manager.php';

$idPermiso = $_GET['id'] ?? null;
$motivo = trim($_GET['motivo'] ?? '');

if (!$idPermiso || empty($motivo)) {
    header('Location: gerente_inicio.php?msg=ID de permiso o motivo no válido');
    exit();
}

// Solo Gerencia
if (!isset($_SESSION['usuario_id']) || !in_array(strtolower($_SESSION['cargo'] ?? ''), ['gerente', 'gerencia'])) {
    header('Location: login.php');
    exit();
}

try {
    // Obtener información del permiso y solicitante
    $stmt = $pdo->prepare("
        SELECT p.id_usuario, u.nombre, c.nombre_cargo
        FROM permisos p
        INNER JOIN usuarios u ON p.id_usuario = u.id_usuario
        INNER JOIN cargo c ON u.id_cargo = c.id_cargo
        WHERE p.id_permiso = ?
    ");
    $stmt->execute([$idPermiso]);
    $permiso = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$permiso) {
        header('Location: gerente_inicio.php?msg=Permiso no encontrado');
        exit();
    }

    $cargo_solicitante = strtolower(trim($permiso['nombre_cargo']));
    $id_solicitante = $permiso['id_usuario'];

    if ($cargo_solicitante === 'auxiliar') {
        // reasignar a coordinador
        $stmt_coord = $pdo->prepare("
            SELECT u.id_usuario FROM usuarios u
            INNER JOIN cargo c ON u.id_cargo = c.id_cargo
            WHERE LOWER(c.nombre_cargo) = 'coordinador' LIMIT 1
        ");
        $stmt_coord->execute();
        $coord = $stmt_coord->fetch(PDO::FETCH_ASSOC);
        $id_asignado_final = $coord ? $coord['id_usuario'] : null;
        $asignado_a = 'coordinador';
    } else {
        $asignado_a = $cargo_solicitante;
        $id_asignado_final = $id_solicitante;
    }

    $stmt = $pdo->prepare("
        UPDATE permisos 
        SET estado = 'rechazado', 
            asignado_a = ?, 
            id_asignado = ?, 
            motivo_rechazo = ?
        WHERE id_permiso = ?
    ");
    $stmt->execute([$asignado_a, $id_asignado_final, $motivo, $idPermiso]);

    if ($stmt->rowCount() === 0) {
        header('Location: gerente_inicio.php?msg=No se pudo actualizar el permiso');
        exit();
    }

    // Notificaciones
    $manager = new EstadoCorreoManager($pdo);
    if ($cargo_solicitante === 'auxiliar' && $id_asignado_final) {
        $manager->gerenteRechazaAuxiliar($idPermiso, $id_asignado_final, $motivo);
    } else {
        $manager->gerenteRechazaDirecto($idPermiso, $motivo);
    }

    header('Location: gerente_inicio.php?msg=Solicitud rechazada correctamente');
    exit();

} catch (Exception $e) {
    error_log("ERROR rechazar_permiso.php: " . $e->getMessage());
    header('Location: gerente_inicio.php?msg=Error al rechazar la solicitud');
    exit();
}