<?php
session_start();
require 'conexion.php';
require_once 'estado_correo_manager.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

try {
    $id_usuario  = $_SESSION['usuario_id'];
    $rol_usuario = strtolower(trim($_SESSION['cargo'] ?? ''));
    $id_permiso  = intval($_POST['id_permiso'] ?? 0);

    if ($id_permiso <= 0) throw new Exception('ID de permiso inválido');

    // Verificar que el permiso pertenece al usuario y está rechazado
    $stmt = $pdo->prepare("SELECT * FROM permisos WHERE id_permiso = ? AND id_usuario = ? AND estado = 'rechazado'");
    $stmt->execute([$id_permiso, $id_usuario]);
    $permiso = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$permiso) throw new Exception('Permiso no encontrado o no válido para reenviar');

    $pdo->beginTransaction();

    // Determinar destino según el rol
    if ($rol_usuario === 'auxiliar') {
        $nuevo_asignado_a = 'coordinador';
        $stmt_dest = $pdo->prepare("
            SELECT u.id_usuario FROM usuarios u
            INNER JOIN cargo c ON u.id_cargo = c.id_cargo
            WHERE LOWER(c.nombre_cargo) = 'coordinador'
            LIMIT 1
        ");
        $stmt_dest->execute();
    } else {
        $nuevo_asignado_a = 'gerente';
        $stmt_dest = $pdo->prepare("
            SELECT u.id_usuario FROM usuarios u
            INNER JOIN cargo c ON u.id_cargo = c.id_cargo
            WHERE LOWER(c.nombre_cargo) IN ('gerencia', 'gerente')
            ORDER BY u.id_usuario ASC
            LIMIT 1
        ");
        $stmt_dest->execute();
    }

    $dest = $stmt_dest->fetch(PDO::FETCH_ASSOC);
    if (!$dest) throw new Exception('No se encontró usuario disponible para asignar el permiso');
    $nuevo_id_asignado = $dest['id_usuario'];

    $stmt = $pdo->prepare("
        UPDATE permisos
        SET estado = 'reenviado', asignado_a = ?, id_asignado = ?, motivo_rechazo = NULL
        WHERE id_permiso = ? AND id_usuario = ?
    ");
    $stmt->execute([$nuevo_asignado_a, $nuevo_id_asignado, $id_permiso, $id_usuario]);

    if ($stmt->rowCount() === 0) throw new Exception('No se pudo reenviar el permiso');

    // Correos
    try {
        $correoManager = new EstadoCorreoManager($pdo);
        if ($rol_usuario === 'auxiliar') {
            $correoManager->auxiliarReenviaCorregido($id_permiso, $id_usuario, $nuevo_id_asignado);
        } else {
            $correoManager->solicitanteDirectoReenviaDirecto($id_permiso, $id_usuario, $nuevo_id_asignado);
        }
    } catch (Exception $e) {
        error_log("Error enviando correos de reenvío: " . $e->getMessage());
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Permiso reenviado correctamente']);

} catch (Exception $e) {
    if (isset($pdo)) $pdo->rollBack();
    error_log("Error en reenviar_permiso.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
