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
    $usuario_id = $_SESSION['usuario_id'];
    $id_permiso = intval($_POST['id_permiso'] ?? 0);
    
    if ($id_permiso <= 0) {
        throw new Exception('ID de permiso inválido');
    }
    
    $pdo->beginTransaction();
    
    // Verificar que el permiso pertenece al usuario y está aprobado
    $stmt = $pdo->prepare("
        SELECT p.*
        FROM permisos p
        WHERE p.id_permiso = ? 
        AND p.id_usuario = ? 
        AND p.estado = 'aprobado'
        AND (p.fecha_regreso_real IS NULL OR p.hora_regreso_real IS NULL)
    ");
    $stmt->execute([$id_permiso, $usuario_id]);
    $permiso = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$permiso) {
        throw new Exception('Permiso no encontrado o no válido para finalizar');
    }
    
    // Usar hora local de Colombia (UTC-5)
    date_default_timezone_set('America/Bogota');
    $ahora = new DateTime();
    
    $fecha_regreso_real = $ahora->format('Y-m-d');
    $hora_regreso_real = $ahora->format('H:i:s');
    
    // Calcular tiempo total en ausencia
    $inicio_ausencia = new DateTime($permiso['fecha_salida'] . ' ' . $permiso['hora_salida']);
    $fin_ausencia = $ahora;
    
    // Calcular diferencia total en segundos y luego convertir
    $diferencia_total = $fin_ausencia->getTimestamp() - $inicio_ausencia->getTimestamp();
    $tiempo_total_segundos = calcularTiempoLaboralEnSegundos($inicio_ausencia, $fin_ausencia);
    
    // Convertir segundos totales a formato HH:MM:SS
    $horas = floor($tiempo_total_segundos / 3600);
    $minutos = floor(($tiempo_total_segundos % 3600) / 60);
    $segundos = $tiempo_total_segundos % 60;
    $tiempo_total_formateado = sprintf('%02d:%02d:%02d', $horas, $minutos, $segundos);
    
    // Actualizar el permiso con fecha, hora y tiempo total
    $stmt = $pdo->prepare("
        UPDATE permisos 
        SET fecha_regreso_real = ?, 
            hora_regreso_real = ?, 
            tiempo_total_ausencia = ?,
            estado = 'finalizado'
        WHERE id_permiso = ? AND id_usuario = ?
    ");
    
    $resultado = $stmt->execute([
        $fecha_regreso_real,
        $hora_regreso_real,
        $tiempo_total_formateado,
        $id_permiso,
        $usuario_id
    ]);
    
    if (!$resultado || $stmt->rowCount() === 0) {
        throw new Exception('No se pudo finalizar el permiso');
    }
    
    $pdo->commit();
    
    // ✉️ ENVIAR CORREOS DE FINALIZACIÓN
    try {
        $correo_manager = new EstadoCorreoManager($pdo);
        $correo_manager->permisoFinalizado($id_permiso, $tiempo_total_formateado);
        error_log("✅ Correos de finalización enviados para permiso #{$id_permiso}");
    } catch (Exception $e) {
        error_log("❌ Error enviando correos de finalización: " . $e->getMessage());
        // No interrumpir el proceso si falla el envío de correos
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Permiso finalizado exitosamente',
        'fecha_regreso' => $fecha_regreso_real,
        'hora_regreso' => $ahora->format('g:i A'),
        'tiempo_total_ausencia' => $tiempo_total_formateado,
        'tiempo_total_segundos' => $tiempo_total_segundos
    ]);
    
} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    error_log("Error en finalizar_permiso.php: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage()
    ]);
}

/**
 * Calcula el tiempo laboral transcurrido en segundos entre dos fechas
 * considerando solo los horarios laborales establecidos
 */
function calcularTiempoLaboralEnSegundos($inicio, $fin) {
    // Horarios laborales por día de la semana
    $horariosLaborales = [
        1 => [['07:30', '12:00'], ['14:00', '17:30']], // Lunes
        2 => [['07:30', '12:00'], ['14:00', '17:30']], // Martes
        3 => [['07:30', '12:00'], ['14:00', '17:30']], // Miércoles
        4 => [['07:30', '12:00'], ['14:00', '17:30']], // Jueves
        5 => [['07:30', '12:00'], ['14:00', '17:00']], // Viernes
        6 => [['08:00', '12:30']]                      // Sábado
    ];
    
    $totalSegundos = 0;
    $fechaActual = clone $inicio;
    $fechaFin = clone $fin;
    
    // Iterar día por día
    while ($fechaActual->format('Y-m-d') <= $fechaFin->format('Y-m-d')) {
        $diaSemana = (int)$fechaActual->format('N'); // 1=lunes, 7=domingo
        
        // Solo procesar días laborales (lunes a sábado)
        if ($diaSemana <= 6 && isset($horariosLaborales[$diaSemana])) {
            $fechaStr = $fechaActual->format('Y-m-d');
            
            foreach ($horariosLaborales[$diaSemana] as $rango) {
                $inicioRango = new DateTime($fechaStr . ' ' . $rango[0]);
                $finRango = new DateTime($fechaStr . ' ' . $rango[1]);
                
                // Ajustar los límites según la fecha actual
                if ($fechaActual->format('Y-m-d') === $inicio->format('Y-m-d')) {
                    // Primer día: usar la hora de inicio real si es mayor
                    $inicioEfectivo = $inicio > $inicioRango ? $inicio : $inicioRango;
                } else {
                    $inicioEfectivo = $inicioRango;
                }
                
                if ($fechaActual->format('Y-m-d') === $fechaFin->format('Y-m-d')) {
                    // Último día: usar la hora de fin real si es menor
                    $finEfectivo = $fechaFin < $finRango ? $fechaFin : $finRango;
                } else {
                    $finEfectivo = $finRango;
                }
                
                // Solo contar si hay overlap válido
                if ($inicioEfectivo < $finEfectivo) {
                    // Calcular diferencia en segundos
                    $segundosEnRango = $finEfectivo->getTimestamp() - $inicioEfectivo->getTimestamp();
                    $totalSegundos += $segundosEnRango;
                }
            }
        }
        
        // Avanzar al siguiente día
        $fechaActual->add(new DateInterval('P1D'));
        $fechaActual->setTime(0, 0, 0); // Resetear a medianoche
    }
    
    return $totalSegundos;
}

/**
 * Función legacy para compatibilidad (mantiene la funcionalidad anterior en minutos)
 */
function calcularTiempoLaboralEnMinutos($inicio, $fin) {
    return floor(calcularTiempoLaboralEnSegundos($inicio, $fin) / 60);
}
?>
