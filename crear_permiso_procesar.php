<?php
session_start();
require 'conexion.php';
require 'envio_correo.php';
require 'estado_correo_manager.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

try {
    // Validar acción
    if ($_POST['accion'] !== 'crear') {
        throw new Exception('Acción no válida');
    }

    // Obtener datos del formulario
    $id_usuario = $_SESSION['usuario_id'];
    $tipo_permiso = $_POST['tipo_permiso'] ?? '';
    $motivo = trim($_POST['motivo'] ?? '');
    $fecha_salida = $_POST['fecha_salida'] ?? '';
    $hora_salida = $_POST['hora_salida'] ?? '';
    $fecha_regreso_aprox = $_POST['fecha_regreso_aprox'] ?? '';
    $hora_regreso_aprox = $_POST['hora_regreso_aprox'] ?? '';
    $encargado_ausencia = trim($_POST['encargado_ausencia'] ?? '');
    
    // Validaciones básicas
    if (!$tipo_permiso || !$motivo || !$fecha_salida || !$hora_salida || !$fecha_regreso_aprox || !$hora_regreso_aprox) {
        throw new Exception('Todos los campos obligatorios deben completarse');
    }

    if (strlen($motivo) < 5) {
        throw new Exception('El motivo debe tener al menos 5 caracteres');
    }

    // Procesamiento de archivo PDF (OPCIONAL) - MEJORADO
    $documento_pdf = null;
    $nombreArchivoOriginal = null;
    
    if (isset($_FILES['documento_pdf']) && $_FILES['documento_pdf']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['documento_pdf'];
        $nombreArchivoOriginal = $file['name'];
        
        // Validar tipo de archivo
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        
        if ($mimeType !== 'application/pdf') {
            throw new Exception('Solo se permiten archivos PDF válidos');
        }
        
        // Validar tamaño (5MB máximo)
        if ($file['size'] > 5 * 1024 * 1024) {
            throw new Exception('El archivo debe ser menor a 5MB');
        }
        
        // Crear directorio si no existe
        $uploadDir = __DIR__ . '/uploads/permisos/';
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                throw new Exception('Error al crear directorio de uploads');
            }
        }
        
        // Generar nombre único para el archivo
        $extension = 'pdf';
        $timestamp = date('Ymd_His');
        $nombreArchivo = "permiso_{$id_usuario}_{$timestamp}_" . uniqid() . ".{$extension}";
        $rutaCompleta = $uploadDir . $nombreArchivo;
        
        // Mover archivo
        if (move_uploaded_file($file['tmp_name'], $rutaCompleta)) {
            $documento_pdf = 'uploads/permisos/' . $nombreArchivo;
            error_log("✅ PDF guardado: {$rutaCompleta} (tamaño: " . round($file['size']/1024, 2) . " KB)");
        } else {
            throw new Exception('Error al subir el archivo PDF');
        }
    }

    // Determinar asignado_a según cargo del usuario - CORREGIDO
    $stmt = $pdo->prepare("
        SELECT c.nombre_cargo 
        FROM usuarios u 
        JOIN cargo c ON u.id_cargo = c.id_cargo 
        WHERE u.id_usuario = ?
    ");
    $stmt->execute([$id_usuario]);
    $usuario = $stmt->fetch();
    $cargo = strtolower($usuario['nombre_cargo']);

    // Lógica de asignación según cargo - CORREGIDA con id_asignado
    $id_asignado = null;
    
    switch ($cargo) {
        case 'auxiliar':
            $asignado_a = 'coordinador';
            $stmt_coord = $pdo->prepare("
                SELECT u.id_usuario 
                FROM usuarios u 
                INNER JOIN cargo c ON u.id_cargo = c.id_cargo 
                WHERE LOWER(c.nombre_cargo) = 'coordinador' 
                LIMIT 1
            ");
            $stmt_coord->execute();
            $coordinador = $stmt_coord->fetch(PDO::FETCH_ASSOC);
            $id_asignado = $coordinador ? $coordinador['id_usuario'] : null;
            break;
            
        case 'administrativo':
        case 'coordinador':
        case 'administrador':
            $asignado_a = 'gerente';
            $stmt_gerente = $pdo->prepare("
                SELECT u.id_usuario 
                FROM usuarios u 
                INNER JOIN cargo c ON u.id_cargo = c.id_cargo 
                WHERE LOWER(c.nombre_cargo) IN ('gerencia', 'gerente')
                ORDER BY u.id_usuario ASC
                LIMIT 1
            ");
            $stmt_gerente->execute();
            $gerente = $stmt_gerente->fetch(PDO::FETCH_ASSOC);
            $id_asignado = $gerente ? $gerente['id_usuario'] : null;
            break;
            
        default:
            $asignado_a = 'coordinador';
            $stmt_coord = $pdo->prepare("
                SELECT u.id_usuario 
                FROM usuarios u 
                INNER JOIN cargo c ON u.id_cargo = c.id_cargo 
                WHERE LOWER(c.nombre_cargo) = 'coordinador' 
                LIMIT 1
            ");
            $stmt_coord->execute();
            $coordinador = $stmt_coord->fetch(PDO::FETCH_ASSOC);
            $id_asignado = $coordinador ? $coordinador['id_usuario'] : null;
            break;
    }
    
    if (!$id_asignado) {
        throw new Exception("No se encontró usuario disponible para asignar el permiso (tipo: {$asignado_a})");
    }

    // Insertar permiso dentro de una transacción para garantizar atomicidad
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO permisos (
            id_usuario, tipo_permiso, motivo, documento_pdf,
            fecha_salida, hora_salida, fecha_regreso_aprox, hora_regreso_aprox,
            encargado_ausencia, asignado_a, id_asignado, estado
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente')
    ");

    $resultado = $stmt->execute([
        $id_usuario,
        $tipo_permiso,
        $motivo,
        $documento_pdf,
        $fecha_salida,
        $hora_salida,
        $fecha_regreso_aprox,
        $hora_regreso_aprox,
        $encargado_ausencia ?: null,
        $asignado_a,
        $id_asignado
    ]);

    if (!$resultado) {
        $pdo->rollBack();
        if ($documento_pdf && file_exists(__DIR__ . '/' . $documento_pdf)) {
            unlink(__DIR__ . '/' . $documento_pdf);
        }
        throw new Exception('Error al guardar la solicitud en base de datos');
    }

    $id_permiso = $pdo->lastInsertId();
    $pdo->commit();

    // 🔥 NUEVO SISTEMA DE CORREOS SEPARADO Y ORGANIZADO
    $correoManager = new EstadoCorreoManager($pdo);
    $resultadoCorreosSinPDF = false;
    $mensajeCorreo = '';
    
    try {
        // Obtener datos para el envío
        $stmt_usuario = $pdo->prepare("
            SELECT u.*, c.nombre_cargo 
            FROM usuarios u 
            INNER JOIN cargo c ON u.id_cargo = c.id_cargo 
            WHERE u.id_usuario = ?
        ");
        $stmt_usuario->execute([$id_usuario]);
        $datosUsuario = $stmt_usuario->fetch(PDO::FETCH_ASSOC);
        
        if (!$datosUsuario) {
            throw new Exception('Error al obtener datos del usuario');
        }
        
        $cargoUsuario = strtolower($datosUsuario['nombre_cargo']);
        error_log("🚀 Iniciando envío de correos para cargo: {$cargoUsuario} (permiso #{$id_permiso})");
        
        // PASO 1: ENVIAR CORREOS SIN PDF (SIEMPRE) / CON PDF solo a Gerencia
        switch ($cargoUsuario) {
            case 'auxiliar':
                // Auxiliar → Coordinador (ambos SIN PDF)
                $stmt_coord = $pdo->prepare("
                    SELECT u.id_usuario 
                    FROM usuarios u 
                    INNER JOIN cargo c ON u.id_cargo = c.id_cargo 
                    WHERE LOWER(c.nombre_cargo) = 'coordinador' 
                    LIMIT 1
                ");
                $stmt_coord->execute();
                $coordinador = $stmt_coord->fetch(PDO::FETCH_ASSOC);
                
                if ($coordinador) {
                    $resultadoCorreosSinPDF = $correoManager->auxiliarCreaSolicitudSinPDF(
                        $id_permiso, 
                        $id_usuario, 
                        $coordinador['id_usuario']
                    );
                    error_log("📧 Correos sin PDF (auxiliar → coordinador): " . ($resultadoCorreosSinPDF ? "✅ OK" : "❌ ERROR"));
                } else {
                    throw new Exception('No se encontró coordinador disponible');
                }
                break;
                
            case 'administrativo':
            case 'coordinador': 
            case 'administrador':
                // Directo a gerencia (si hay PDF, enviar confidencialmente a Gerencia)
                if ($gerente) {
                    if (!empty($documento_pdf)) {
                        // Enviar PDF a Gerencia (la función se encarga de notificar al solicitante también)
                        $resultadoCorreosSinPDF = $correoManager->solicitanteDirectoEnviaAGerenciaConPDF(
                            $id_permiso,
                            $id_usuario,
                            $gerente['id_usuario'],
                            $documento_pdf
                        );
                        error_log("📧 Correos (directo → gerencia) CON PDF: " . ($resultadoCorreosSinPDF ? "✅ OK" : "❌ ERROR"));
                    } else {
                        // Sin PDF, flujo normal
                        $resultadoCorreosSinPDF = $correoManager->solicitanteDirectoEnviaAGerenciaSinPDF(
                            $id_permiso,
                            $id_usuario,
                            $gerente['id_usuario']
                        );
                        error_log("📧 Correos (directo → gerencia) SIN PDF: " . ($resultadoCorreosSinPDF ? "✅ OK" : "❌ ERROR"));
                    }
                } else {
                    throw new Exception('No se encontró gerente disponible');
                }
                break;
                
            default:
                // Otros cargos → Coordinador por defecto
                $stmt_coord = $pdo->prepare("
                    SELECT u.id_usuario 
                    FROM usuarios u 
                    INNER JOIN cargo c ON u.id_cargo = c.id_cargo 
                    WHERE LOWER(c.nombre_cargo) = 'coordinador' 
                    LIMIT 1
                ");
                $stmt_coord->execute();
                $coordinador = $stmt_coord->fetch(PDO::FETCH_ASSOC);
                
                if ($coordinador) {
                    $resultadoCorreosSinPDF = $correoManager->auxiliarCreaSolicitudSinPDF(
                        $id_permiso, 
                        $id_usuario, 
                        $coordinador['id_usuario']
                    );
                    error_log("📧 Correos sin PDF (otros → coordinador): " . ($resultadoCorreosSinPDF ? "✅ OK" : "❌ ERROR"));
                } else {
                    throw new Exception('No se encontró coordinador disponible para cargo: ' . $cargoUsuario);
                }
                break;
        }
        
        // GENERAR MENSAJE DE RESULTADO
        $partesExito = [];
        $partesError = [];
        
        if ($resultadoCorreosSinPDF) {
            $partesExito[] = "notificaciones enviadas";
        } else {
            $partesError[] = "error en notificaciones";
        }
        
        if ($documento_pdf) {
            if ($resultadoCorreosSinPDF) {
                $partesExito[] = "PDF enviado a gerencia";
            } else {
                $partesError[] = "error enviando PDF";
            }
        }
        
        if (!empty($partesExito)) {
            $mensajeCorreo = ' (' . implode(', ', $partesExito) . ')';
        }
        
        if (!empty($partesError)) {
            $mensajeCorreo .= ' [' . implode(', ', $partesError) . ']';
        }
        
        $correos_completamente_exitosos = $resultadoCorreosSinPDF;
        
        error_log($correos_completamente_exitosos ? 
            "✅ TODOS los correos enviados exitosamente para permiso #{$id_permiso}" :
            "⚠️ Correos parcialmente exitosos para permiso #{$id_permiso}: sin-PDF=" . ($resultadoCorreosSinPDF ? "OK" : "FAIL")
        );
        
    } catch (Exception $e) {
        error_log("❌ Error en sistema de correos: " . $e->getMessage());
        $mensajeCorreo = ' (permiso creado - error en correos: ' . $e->getMessage() . ')';
        $correos_completamente_exitosos = false;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Solicitud creada exitosamente' . $mensajeCorreo,
        'id_permiso' => $id_permiso,
        'datos' => [
            'tiene_documento' => !is_null($documento_pdf),
            'ruta_pdf' => $documento_pdf,
            'tamaño_original' => isset($file) ? round($file['size']/1024, 2) . ' KB' : null,
            'nombre_original' => $nombreArchivoOriginal,
            'correos_sin_pdf_enviados' => $resultadoCorreosSinPDF,
            'pdf_enviado_a_gerencia' => $documento_pdf ? $resultadoCorreosSinPDF : null,
            'correos_completamente_exitosos' => $correos_completamente_exitosos,
            'asignado_a' => $asignado_a,
            'id_asignado' => $id_asignado,
            'flujo_correo' => [
                'cargo_solicitante' => $cargoUsuario,
                'paso_1_sin_pdf' => $resultadoCorreosSinPDF ? 'exitoso' : 'error',
                'paso_2_pdf_gerencia' => $documento_pdf ? ($resultadoCorreosSinPDF ? 'exitoso' : 'error') : 'no_aplica'
            ]
        ]
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Error en crear_permiso_procesar.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug_info' => [
            'archivo' => basename(__FILE__),
            'linea_aprox' => 'Procesamiento principal',
            'datos_recibidos' => array_keys($_POST)
        ]
    ]);
}
?>
