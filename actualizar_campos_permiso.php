<?php
session_start();
require 'conexion.php';

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
    $id_usuario     = $_SESSION['usuario_id'];
    $id_permiso     = intval($_POST['id_permiso'] ?? 0);
    $motivo         = trim($_POST['motivo'] ?? '');
    $fecha_salida   = $_POST['fecha_salida'] ?? '';
    $hora_salida    = $_POST['hora_salida'] ?? '';
    $fecha_regreso  = $_POST['fecha_regreso_aprox'] ?? '';
    $hora_regreso   = $_POST['hora_regreso_aprox'] ?? '';

    if ($id_permiso <= 0) throw new Exception('ID de permiso inválido');
    if (!$motivo || !$fecha_salida || !$hora_salida || !$fecha_regreso || !$hora_regreso) {
        throw new Exception('Todos los campos son obligatorios');
    }
    if (strlen($motivo) < 10) throw new Exception('El motivo debe tener al menos 10 caracteres');

    $ts_salida  = strtotime($fecha_salida . ' ' . $hora_salida);
    $ts_regreso = strtotime($fecha_regreso . ' ' . $hora_regreso);
    if ($ts_salida === false || $ts_regreso === false) throw new Exception('Fechas u horas inválidas');
    if ($ts_regreso <= $ts_salida) throw new Exception('La fecha de regreso debe ser posterior a la de salida');

    $pdo->beginTransaction();

    // Verificar estado dentro de la transacción para evitar race condition
    $stmt = $pdo->prepare("SELECT id_permiso FROM permisos WHERE id_permiso = ? AND id_usuario = ? AND estado = 'rechazado' FOR UPDATE");
    $stmt->execute([$id_permiso, $id_usuario]);
    if (!$stmt->fetch()) {
        $pdo->rollBack();
        throw new Exception('Permiso no encontrado, no te pertenece, o ya no está en estado rechazado');
    }

    $stmt = $pdo->prepare("
        UPDATE permisos
        SET motivo = ?, fecha_salida = ?, hora_salida = ?, fecha_regreso_aprox = ?, hora_regreso_aprox = ?
        WHERE id_permiso = ? AND id_usuario = ? AND estado = 'rechazado'
    ");
    $stmt->execute([$motivo, $fecha_salida, $hora_salida, $fecha_regreso, $hora_regreso, $id_permiso, $id_usuario]);

    if ($stmt->rowCount() === 0) {
        $pdo->rollBack();
        throw new Exception('No se pudieron actualizar los campos');
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Campos actualizados correctamente']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Error en actualizar_campos_permiso.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
