<?php
session_start();
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/estado_correo_manager.php';

header('Content-Type: application/json; charset=utf-8');

// Verificar que el usuario esté logueado
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? null;
    $rol_usuario = strtolower(trim($_SESSION['cargo'] ?? ''));
    $id_usuario = $_SESSION['usuario_id'];
    $id_permiso = intval($_POST['id_permiso'] ?? 0);
    $motivo_rechazo = $_POST['motivo_rechazo'] ?? null;

    // Validación temprana
    if ($id_permiso <= 0 && $accion !== 'crear') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID de permiso inválido']);
        exit;
    }

    // Datos adicionales para crear solicitud - ACTUALIZADOS
    $tipo_permiso = $_POST['tipo_permiso'] ?? null;
    $motivo = $_POST['motivo'] ?? null;
    $fecha_salida = $_POST['fecha_salida'] ?? null;
    $hora_salida = $_POST['hora_salida'] ?? null;
    $fecha_regreso_aprox = $_POST['fecha_regreso_aprox'] ?? null;
    $hora_regreso_aprox = $_POST['hora_regreso_aprox'] ?? null;
    $encargado_ausencia = $_POST['encargado_ausencia'] ?? null;

    try {
        $pdo->beginTransaction();
        
        // Instanciar el manager de estados y correos
        $estadoCorreo = new EstadoCorreoManager($pdo);

        // CREAR SOLICITUD - ACTUALIZADA CON VALIDACIONES COMENTADAS
        if ($accion === 'crear') {
            // Validar datos requeridos
            if (!$tipo_permiso || !$motivo || !$fecha_salida || !$fecha_regreso_aprox || !$hora_salida || !$hora_regreso_aprox) {
                throw new Exception("Todos los campos de fecha, hora y motivo son obligatorios");
            }

            // ============================================================================
            // VALIDACIONES DE FECHA/HORA COMENTADAS PARA TESTING
            // ============================================================================
            /*
            $ts_salida = strtotime($fecha_salida . ' ' . $hora_salida);
            $ts_regreso = strtotime($fecha_regreso_aprox . ' ' . $hora_regreso_aprox);
            $hoy = strtotime(date('Y-m-d'));
            $manana = strtotime('+1 day', $hoy);

            if ($ts_salida === false || $ts_regreso === false) {
                throw new Exception("Fecha u hora inválida");
            }

            // Verificar que NO sea el día actual
            if ($ts_salida >= $hoy && $ts_salida < $manana) {
                throw new Exception("El permiso debe hacerse con un día de anticipación");
            }

            // Verificar que no sea en el pasado
            if ($ts_salida < $hoy) {
                throw new Exception("No se pueden seleccionar fechas pasadas");
            }

            if ($ts_regreso < $ts_salida) {
                throw new Exception("La fecha y hora aproximada de regreso debe ser posterior a la de salida");
            }
            */
            
            // VALIDACIÓN MÍNIMA PARA TESTING
            $ts_salida = strtotime($fecha_salida . ' ' . $hora_salida);
            $ts_regreso = strtotime($fecha_regreso_aprox . ' ' . $hora_regreso_aprox);
            
            if ($ts_salida === false || $ts_regreso === false) {
                throw new Exception("Fecha u hora inválida");
            }
            
            if ($ts_regreso <= $ts_salida) {
                throw new Exception("La fecha y hora aproximada de regreso debe ser posterior a la de salida");
            }
            // ============================================================================

            // Determinar asignación según el rol - CORREGIDO
            if ($rol_usuario === 'auxiliar') {
                // AUXILIAR → asignado_a = 'coordinador'
                $asignado_a = 'coordinador';
                
                $stmt = $pdo->prepare("
                    SELECT u.id_usuario 
                    FROM usuarios u 
                    INNER JOIN cargo c ON u.id_cargo = c.id_cargo 
                    WHERE LOWER(c.nombre_cargo) = 'coordinador'
                    LIMIT 1
                ");
                $stmt->execute();
                $coordinador = $stmt->fetch(PDO::FETCH_ASSOC);
                $id_asignado = $coordinador ? $coordinador['id_usuario'] : null;
                
                if (!$id_asignado) {
                    throw new Exception("No se encontró coordinador disponible para asignar el permiso");
                }
                
            } elseif (in_array($rol_usuario, ['administrador', 'coordinador', 'administrativo', 'gerente', 'gerencia'])) {
                // ADMINISTRADOR, COORDINADOR, ADMINISTRATIVO → asignado_a = 'gerente'
                $asignado_a = 'gerente';
                
                $stmt = $pdo->prepare("
                    SELECT u.id_usuario 
                    FROM usuarios u 
                    INNER JOIN cargo c ON u.id_cargo = c.id_cargo 
                    WHERE LOWER(c.nombre_cargo) IN ('gerencia', 'gerente')
                    ORDER BY u.id_usuario ASC
                    LIMIT 1
                ");
                $stmt->execute();
                $gerente = $stmt->fetch(PDO::FETCH_ASSOC);
                $id_asignado = $gerente ? $gerente['id_usuario'] : null;
                
                if (!$id_asignado) {
                    throw new Exception("No se encontró gerente disponible para asignar el permiso");
                }
                
            } else {
                throw new Exception("Rol no autorizado para crear solicitudes: " . $rol_usuario);
            }

            $stmt = $pdo->prepare("
                INSERT INTO permisos (
                    id_usuario, tipo_permiso, motivo, fecha_salida, hora_salida,
                    fecha_regreso_aprox, hora_regreso_aprox, encargado_ausencia, estado,
                    asignado_a, id_asignado
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pendiente', ?, ?)
            ");
            $stmt->execute([
                $id_usuario, $tipo_permiso, $motivo, $fecha_salida, $hora_salida,
                $fecha_regreso_aprox, $hora_regreso_aprox, $encargado_ausencia, $asignado_a, $id_asignado
            ]);

            $id_permiso_creado = $pdo->lastInsertId();

            // 🔥 ENVIAR CORREOS SEGÚN EL FLUJO ESPECÍFICO
            if ($rol_usuario === 'auxiliar' && $id_asignado) {
                $estadoCorreo->auxiliarCreaSolicitudSinPDF($id_permiso_creado, $id_usuario, $id_asignado);
            } elseif (in_array($rol_usuario, ['administrador', 'coordinador', 'administrativo', 'gerente', 'gerencia']) && $id_asignado) {
                // SI EL FORM ENVÍA ruta_documento (por compatibilidad), usar la versión CON PDF
                $ruta_pdf = $_POST['documento_pdf'] ?? null;
                if (!empty($ruta_pdf)) {
                    $estadoCorreo->solicitanteDirectoEnviaAGerenciaConPDF($id_permiso_creado, $id_usuario, $id_asignado, $ruta_pdf);
                } else {
                    $estadoCorreo->solicitanteDirectoEnviaAGerenteSinPDF($id_permiso_creado, $id_usuario, $id_asignado);
                }
            }

            $pdo->commit();
            echo json_encode([
                'success' => true, 
                'message' => 'Solicitud creada correctamente',
                'debug' => [
                    'asignado_a' => $asignado_a,
                    'id_asignado' => $id_asignado,
                    'rol_usuario' => $rol_usuario,
                ]
            ]);
            exit;
        }

        // APROBAR (solo gerente) - ACTUALIZADA para manejar persona encargada
        if ($accion === 'aprobar') {
            $es_gerente = in_array($rol_usuario, ['gerente', 'gerencia']) || 
                         stripos($rol_usuario, 'gerente') !== false || 
                         stripos($rol_usuario, 'gerencia') !== false;
            
            if (!$es_gerente) {
                throw new Exception("Acción no autorizada para el rol: '$rol_usuario'");
            }
            
            // Obtener persona encargada del formulario (puede estar vacía)
            $encargado_ausencia_gerente = trim($_POST['encargado_ausencia'] ?? '');
            
            // Actualizar permiso incluyendo persona encargada si se proporcionó
            $stmt = $pdo->prepare("
                UPDATE permisos 
                SET estado = 'aprobado', 
                    asignado_a = NULL, 
                    id_asignado = NULL, 
                    motivo_rechazo = NULL,
                    encargado_ausencia = ?
                WHERE id_permiso = ?
            ");
            $stmt->execute([
                !empty($encargado_ausencia_gerente) ? $encargado_ausencia_gerente : null,
                $id_permiso
            ]);
            
            if ($stmt->rowCount() === 0) {
                throw new Exception("Permiso no encontrado con ID: $id_permiso");
            }

            // 🔥 CORREO: GERENTE APRUEBA
            $estadoCorreo->gerenteAprueba($id_permiso);

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Solicitud aprobada exitosamente']);
            exit;
        }

        // RECHAZAR (solo gerente) - FLUJO CORREGIDO
        if ($accion === 'rechazar') {
            $es_gerente = in_array($rol_usuario, ['gerente', 'gerencia']) || 
                         stripos($rol_usuario, 'gerente') !== false || 
                         stripos($rol_usuario, 'gerencia') !== false;
            
            if (!$es_gerente) {
                throw new Exception("Acción no autorizada para el rol: '$rol_usuario'");
            }
            
            if (empty($motivo_rechazo)) {
                throw new Exception("El motivo del rechazo es obligatorio");
            }

            // Obtener información del solicitante
            $stmt = $pdo->prepare("
                SELECT p.id_usuario, c.nombre_cargo
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

            $cargo_solicitante = strtolower(trim($permiso['nombre_cargo']));
            $id_solicitante = $permiso['id_usuario'];
            
            // FLUJO CORREGIDO: Si es auxiliar → va al coordinador, otros roles van al mismo usuario
            if ($cargo_solicitante === 'auxiliar') {
                $asignado_a = 'coordinador';
                $stmt_coord = $pdo->prepare("
                    SELECT u.id_usuario FROM usuarios u
                    INNER JOIN cargo c ON u.id_cargo = c.id_cargo
                    WHERE c.nombre_cargo = 'Coordinador' LIMIT 1
                ");
                $stmt_coord->execute();
                $coord = $stmt_coord->fetch(PDO::FETCH_ASSOC);
                $id_asignado_final = $coord ? $coord['id_usuario'] : null;
            } else {
                // ADMINISTRADOR, COORDINADOR, ADMINISTRATIVO vuelven al mismo usuario
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
            $stmt->execute([$asignado_a, $id_asignado_final, $motivo_rechazo, $id_permiso]);

            if ($stmt->rowCount() === 0) {
                throw new Exception("No se pudo actualizar el permiso");
            }

            // 🔥 CORREOS: GERENTE RECHAZA (ahora con motivo)
            if ($cargo_solicitante === 'auxiliar' && $id_asignado_final) {
                $estadoCorreo->gerenteRechazaAuxiliar($id_permiso, $id_asignado_final, $motivo_rechazo);
            } else {
                $estadoCorreo->gerenteRechazaDirecto($id_permiso, $motivo_rechazo);
            }

            $pdo->commit();
            echo json_encode([
                'success' => true, 
                'message' => "Solicitud rechazada y reasignada"
            ]);
            exit;
        }

        // REENVIAR - ACTUALIZADA para nuevos campos
        if ($accion === 'reenviar') {
            // Obtener información del permiso actual
            $stmt = $pdo->prepare("
                SELECT p.*, c.nombre_cargo 
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

            $cargo_creador = strtolower(trim($permiso['nombre_cargo']));
            $id_creador = $permiso['id_usuario'];

            // Verificar que el usuario puede reenviar este permiso
            if ($id_creador != $id_usuario) {
                throw new Exception("Solo puedes reenviar tus propios permisos");
            }
            
            if ($permiso['estado'] !== 'rechazado') {
                throw new Exception("Solo se pueden reenviar permisos rechazados");
            }

            // OBTENER TODOS LOS NUEVOS DATOS DEL POST - ACTUALIZADOS
            $nuevo_tipo_permiso = $_POST['tipo_permiso'] ?? $permiso['tipo_permiso'];
            $nuevo_motivo = isset($_POST['motivo']) ? trim($_POST['motivo']) : $permiso['motivo'];
            $nueva_fecha_salida = $_POST['fecha_salida'] ?? $permiso['fecha_salida'];
            $nueva_hora_salida = $_POST['hora_salida'] ?? $permiso['hora_salida'];
            $nueva_fecha_regreso_aprox = $_POST['fecha_regreso_aprox'] ?? $permiso['fecha_regreso_aprox'];
            $nueva_hora_regreso_aprox = $_POST['hora_regreso_aprox'] ?? $permiso['hora_regreso_aprox'];
            $nuevo_encargado = $_POST['encargado_ausencia'] ?? $permiso['encargado_ausencia'];
            
            // Validar motivo mínimo
            if (strlen($nuevo_motivo) < 5) {
                throw new Exception("El motivo debe tener al menos 5 caracteres");
            }

            // Determinar nueva asignación según el flujo
            if ($rol_usuario === 'auxiliar') {
                $nuevo_asignado_a = 'coordinador';
                $stmt_coord = $pdo->prepare("
                    SELECT u.id_usuario FROM usuarios u 
                    INNER JOIN cargo c ON u.id_cargo = c.id_cargo 
                    WHERE c.nombre_cargo = 'Coordinador' LIMIT 1
                ");
                $stmt_coord->execute();
                $coordinador = $stmt_coord->fetch(PDO::FETCH_ASSOC);
                $nuevo_id_asignado = $coordinador ? $coordinador['id_usuario'] : null;
                
            } elseif (in_array($rol_usuario, ['coordinador', 'administrador', 'administrativo'])) {
                $nuevo_asignado_a = 'gerente';
                $stmt_gerente = $pdo->prepare("
                    SELECT u.id_usuario FROM usuarios u 
                    INNER JOIN cargo c ON u.id_cargo = c.id_cargo 
                    WHERE c.nombre_cargo = 'Gerencia' LIMIT 1
                ");
                $stmt_gerente->execute();
                $gerente = $stmt_gerente->fetch(PDO::FETCH_ASSOC);
                $nuevo_id_asignado = $gerente ? $gerente['id_usuario'] : null;
            } else {
                throw new Exception("Rol '$rol_usuario' no autorizado para reenviar permisos");
            }

            if (!$nuevo_id_asignado) {
                throw new Exception("No se encontró usuario para asignar el permiso reenviado");
            }

            // ACTUALIZAR TODO EN UNA SOLA OPERACIÓN - ACTUALIZADA
            $stmt_update = $pdo->prepare("
                UPDATE permisos 
                SET tipo_permiso = ?,
                    motivo = ?,
                    fecha_salida = ?,
                    hora_salida = ?,
                    fecha_regreso_aprox = ?,
                    hora_regreso_aprox = ?,
                    encargado_ausencia = ?,
                    estado = 'reenviado',
                    asignado_a = ?,
                    id_asignado = ?,
                    motivo_rechazo = NULL
                WHERE id_permiso = ?
            ");

            $parametros_update = [
                $nuevo_tipo_permiso,
                $nuevo_motivo,
                $nueva_fecha_salida,
                $nueva_hora_salida,
                $nueva_fecha_regreso_aprox,
                $nueva_hora_regreso_aprox,
                !empty($nuevo_encargado) ? $nuevo_encargado : null,
                $nuevo_asignado_a,
                $nuevo_id_asignado,
                $id_permiso
            ];

            error_log("[REENVIAR] Ejecutando UPDATE con parámetros: " . json_encode($parametros_update));

            $resultado = $stmt_update->execute($parametros_update);

            if (!$resultado || $stmt_update->rowCount() === 0) {
                throw new Exception("Error al actualizar el permiso - no se afectaron filas");
            }

            // Verificar que el motivo se actualizó correctamente
            $stmt_verificar = $pdo->prepare("SELECT motivo FROM permisos WHERE id_permiso = ?");
            $stmt_verificar->execute([$id_permiso]);
            $motivo_actual = $stmt_verificar->fetchColumn();

            error_log("[REENVIAR] Verificación: motivo enviado='" . $nuevo_motivo . "', motivo en BD='" . $motivo_actual . "'");

            if ($motivo_actual !== $nuevo_motivo) {
                throw new Exception("ERROR: El motivo no se actualizó correctamente en la base de datos");
            }

            // 🔥 CORREOS: REENVÍO SEGÚN FLUJO (el manager ahora enviará PDF a Gerencia si existe)
            if ($rol_usuario === 'auxiliar' && $nuevo_id_asignado) {
                $estadoCorreo->auxiliarReenviaCorregido($id_permiso, $id_usuario, $nuevo_id_asignado);
            } elseif (in_array($rol_usuario, ['coordinador', 'administrador', 'administrativo']) && $nuevo_id_asignado) {
                $estadoCorreo->solicitanteDirectoReenviaDirecto($id_permiso, $id_usuario, $nuevo_id_asignado);
            }

            $pdo->commit();
            
            $mensaje_destino = $rol_usuario === 'auxiliar' ? 'coordinador' : 'gerente';
            
            echo json_encode([
                'success' => true, 
                'message' => "Permiso corregido y reenviado al $mensaje_destino exitosamente",
                'debug' => [
                    'motivo_actualizado' => $motivo_actual === $nuevo_motivo,
                    'filas_afectadas' => $stmt_update->rowCount(),
                    'nuevo_estado' => 'reenviado',
                    'asignado_a' => $nuevo_asignado_a
                ]
            ]);
            exit;
        }

        // CANCELAR
        if ($accion === 'cancelar') {
            $stmt = $pdo->prepare("
                UPDATE permisos 
                SET estado = 'cancelado', asignado_a = NULL, id_asignado = NULL
                WHERE id_permiso = ?
            ");
            $stmt->execute([$id_permiso]);

            if ($stmt->rowCount() === 0) {
                throw new Exception("Permiso no encontrado con ID: $id_permiso");
            }

            // 🔥 CORREO: GERENTE CANCELA
            $estadoCorreo->gerenteCancela($id_permiso);

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Solicitud cancelada definitivamente']);
            exit;
        }

        // ENVIAR A GERENTE (coordinador)
        if ($accion === 'enviar_gerente') {
            if (!in_array($rol_usuario, ['coordinador', 'administrador'])) {
                throw new Exception("Solo coordinadores pueden enviar solicitudes a gerencia");
            }

            $stmt_gerente = $pdo->prepare("
                SELECT u.id_usuario FROM usuarios u
                INNER JOIN cargo c ON u.id_cargo = c.id_cargo
                WHERE c.nombre_cargo = 'Gerencia' LIMIT 1
            ");
            $stmt_gerente->execute();
            $gerente = $stmt_gerente->fetch(PDO::FETCH_ASSOC);

            if (!$gerente) {
                throw new Exception("No se encontró gerente para asignar la solicitud");
            }

            $stmt = $pdo->prepare("
                UPDATE permisos 
                SET estado = 'pendiente', asignado_a = 'gerente', id_asignado = ?
                WHERE id_permiso = ?
            ");
            $stmt->execute([$gerente['id_usuario'], $id_permiso]);

            if ($stmt->rowCount() === 0) {
                throw new Exception("No se pudo actualizar el permiso");
            }

            // 🔥 CORREO: COORDINADOR ENVÍA A GERENTE
            $estadoCorreo->coordinadorEnviaAGerente($id_permiso, $gerente['id_usuario']);

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Solicitud enviada a gerencia exitosamente']);
            exit;
        }

        // REENVIAR AL AUXILIAR (coordinador)
        if ($accion === 'reenviar_auxiliar') {
            if (!in_array($rol_usuario, ['coordinador', 'administrador'])) {
                throw new Exception("Solo coordinadores pueden reenviar al auxiliar");
            }

            $stmt_permiso = $pdo->prepare("
                SELECT p.*, u.nombre, c.nombre_cargo 
                FROM permisos p
                INNER JOIN usuarios u ON p.id_usuario = u.id_usuario
                INNER JOIN cargo c ON u.id_cargo = c.id_cargo
                WHERE p.id_permiso = ?
            ");
            $stmt_permiso->execute([$id_permiso]);
            $permiso = $stmt_permiso->fetch(PDO::FETCH_ASSOC);

            if (!$permiso) {
                throw new Exception("Permiso no encontrado");
            }

            if (strtolower($permiso['nombre_cargo']) !== 'auxiliar') {
                throw new Exception("Solo se pueden reenviar solicitudes de auxiliares");
            }

            $stmt = $pdo->prepare("
                UPDATE permisos 
                SET estado = 'rechazado', asignado_a = 'auxiliar', id_asignado = ?
                WHERE id_permiso = ?
            ");
            $stmt->execute([$permiso['id_usuario'], $id_permiso]);

            if ($stmt->rowCount() === 0) {
                throw new Exception("No se pudo actualizar el permiso");
            }

            // 🔥 CORREO: COORDINADOR REENVÍA AL AUXILIAR
            $estadoCorreo->coordinadorReenviaAAuxiliar($id_permiso, $permiso['id_usuario']);

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Solicitud reenviada al auxiliar para corrección']);
            exit;
        }

        throw new Exception("Acción '$accion' no válida o no implementada");

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("ERROR en permisos_acciones.php: " . $e->getMessage());
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
