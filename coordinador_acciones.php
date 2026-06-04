<?php
session_start();
require 'conexion.php';
require_once 'estado_correo_manager.php';

header('Content-Type: application/json; charset=utf-8');

// Verificar que el usuario esté logueado y sea coordinador
if (!isset($_SESSION['usuario_id']) || strtolower($_SESSION['cargo']) !== 'coordinador') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? null;
    $id_coordinador = $_SESSION['usuario_id'];
    $id_permiso = intval($_POST['id_permiso'] ?? 0);

    if ($id_permiso <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID de permiso inválido']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Obtener información del permiso
        $stmt = $pdo->prepare("
            SELECT p.*, u.nombre, c.nombre_cargo 
            FROM permisos p
            INNER JOIN usuarios u ON p.id_usuario = u.id_usuario
            INNER JOIN cargo c ON u.id_cargo = c.id_cargo
            WHERE p.id_permiso = ?
        ");
        $stmt->execute([$id_permiso]);
        $permiso = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$permiso) {
            throw new Exception("Permiso no encontrado");
        }

        // ENVIAR A GERENTE (ACTUALIZADO para incluir persona encargada)
        if ($accion === 'enviar_gerente') {
            // Obtener persona encargada del formulario
            $encargado_ausencia = trim($_POST['encargado_ausencia'] ?? '');
            
            // Buscar ID del gerente (tolerante en el nombre del cargo: 'gerencia' o 'gerente')
            $stmt_gerente = $pdo->prepare("
                SELECT u.id_usuario, u.nombre, u.correo FROM usuarios u 
                INNER JOIN cargo c ON u.id_cargo = c.id_cargo 
                WHERE LOWER(c.nombre_cargo) IN ('gerencia','gerente')
                ORDER BY u.id_usuario ASC
                LIMIT 1
            ");
            $stmt_gerente->execute();
            $gerente = $stmt_gerente->fetch(PDO::FETCH_ASSOC);

            if (!$gerente) {
                throw new Exception("No se encontró usuario con cargo de Gerencia");
            }

            // Actualizar asignación a gerente Y persona encargada
            $stmt = $pdo->prepare("
                UPDATE permisos 
                SET asignado_a = 'gerente', 
                    id_asignado = ?,
                    encargado_ausencia = ?
                WHERE id_permiso = ? 
                AND id_asignado = ? 
                AND asignado_a = 'coordinador'
            ");
            $stmt->execute([
                $gerente['id_usuario'], 
                !empty($encargado_ausencia) ? $encargado_ausencia : null,
                $id_permiso, 
                $id_coordinador
            ]);

            if ($stmt->rowCount() === 0) {
                throw new Exception("No se pudo enviar el permiso al gerente");
            }

            $pdo->commit();

            // Enviar notificación/correo vía EstadoCorreoManager (ya maneja PDF -> Gerencia)
            try {
                $correo_manager = new EstadoCorreoManager($pdo);
                $correo_manager->coordinadorEnviaAGerente($id_permiso, $gerente['id_usuario']);
            } catch (Exception $e) {
                // registrar el error pero no revertir (ya se hizo commit)
                error_log("Error enviando notificación a gerente tras reenvío: " . $e->getMessage());
            }

            echo json_encode(['success' => true, 'message' => 'Permiso enviado a Gerencia exitosamente']);
            exit;
        }

        // REENVIAR AL AUXILIAR
        if ($accion === 'reenviar_auxiliar') {
            // Solo para permisos rechazados de auxiliares
            if ($permiso['estado'] !== 'rechazado' || strtolower($permiso['nombre_cargo']) !== 'auxiliar') {
                throw new Exception("Solo se pueden reenviar permisos rechazados de auxiliares");
            }

            // Asignar de vuelta al auxiliar
            $stmt = $pdo->prepare("
                UPDATE permisos 
                SET asignado_a = 'auxiliar', 
                    id_asignado = ?
                WHERE id_permiso = ? 
                AND id_asignado = ? 
                AND asignado_a = 'coordinador'
                AND estado = 'rechazado'
            ");
            $stmt->execute([$permiso['id_usuario'], $id_permiso, $id_coordinador]);

            if ($stmt->rowCount() === 0) {
                throw new Exception("No se pudo reenviar el permiso al auxiliar");
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Rechazo enviado de vuelta al auxiliar']);
            exit;
        }

        throw new Exception("Acción '$accion' no válida para coordinador");

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("ERROR en coordinador_acciones.php: " . $e->getMessage());
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'error' => $e->getMessage()
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
}
?>
