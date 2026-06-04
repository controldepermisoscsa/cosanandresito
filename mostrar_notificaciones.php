<?php
session_start();
require 'conexion.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if (!isset($_SESSION['usuario_id'])) {
        throw new Exception('Sesión no válida');
    }

    $usuario_id = $_SESSION['usuario_id'];

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Obtener notificaciones del usuario
        $stmt = $pdo->prepare("
            SELECT id_notificacion, mensaje, fecha_envio, leido 
            FROM notificaciones 
            WHERE id_usuario = ? 
            ORDER BY fecha_envio DESC 
            LIMIT 20
        ");
        $stmt->execute([$usuario_id]);
        $notificaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Contar notificaciones no leídas
        $stmt_count = $pdo->prepare("
            SELECT COUNT(*) as no_leidas 
            FROM notificaciones 
            WHERE id_usuario = ? AND leido = 0
        ");
        $stmt_count->execute([$usuario_id]);
        $contador = $stmt_count->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'notificaciones' => $notificaciones,
            'no_leidas' => $contador['no_leidas']
        ]);

    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $accion = $_POST['accion'] ?? '';

        if ($accion === 'marcar_leida') {
            $id_notificacion = intval($_POST['id_notificacion'] ?? 0);
            
            $stmt = $pdo->prepare("
                UPDATE notificaciones 
                SET leido = 1 
                WHERE id_notificacion = ? AND id_usuario = ?
            ");
            $stmt->execute([$id_notificacion, $usuario_id]);

            echo json_encode(['success' => true, 'message' => 'Notificación marcada como leída']);

        } elseif ($accion === 'marcar_todas_leidas') {
            $stmt = $pdo->prepare("
                UPDATE notificaciones 
                SET leido = 1 
                WHERE id_usuario = ?
            ");
            $stmt->execute([$usuario_id]);

            echo json_encode(['success' => true, 'message' => 'Todas las notificaciones marcadas como leídas']);

        } else {
            throw new Exception('Acción no válida');
        }
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
