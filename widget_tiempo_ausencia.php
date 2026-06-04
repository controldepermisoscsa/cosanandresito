<?php
session_start();
require 'conexion.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

try {
    $usuario_id = $_SESSION['usuario_id'];
    
    // Configurar zona horaria de Colombia
    date_default_timezone_set('America/Bogota');
    $ahora = new DateTime();
    
    // CORREGIDO: Solo buscar permisos APROBADOS que estén activos
    $stmt = $pdo->prepare("
        SELECT p.*, 
               CONCAT(p.fecha_salida, ' ', p.hora_salida) as inicio_ausencia,
               CONCAT(p.fecha_regreso_aprox, ' ', p.hora_regreso_aprox) as fin_ausencia_aprox
        FROM permisos p
        WHERE p.id_usuario = ? 
        AND p.estado = 'aprobado'
        AND p.fecha_regreso_real IS NULL 
        AND p.hora_regreso_real IS NULL
        AND CONCAT(p.fecha_salida, ' ', p.hora_salida) <= NOW()
        ORDER BY p.fecha_salida DESC
        LIMIT 1
    ");
    $stmt->execute([$usuario_id]);
    $permiso_activo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$permiso_activo) {
        echo json_encode(['success' => true, 'permiso_activo' => false]);
        exit;
    }
    
    // Verificar que estamos dentro del horario laboral actual
    $now = new DateTime();
    $diaSemanaActual = (int)$now->format('N'); // 1=lunes, 7=domingo
    $horaActual = $now->format('H:i');
    
    // Horarios laborales
    $horariosLaborales = [
        1 => [['07:30', '12:00'], ['14:00', '17:30']], // Lunes
        2 => [['07:30', '12:00'], ['14:00', '17:30']], // Martes
        3 => [['07:30', '12:00'], ['14:00', '17:30']], // Miércoles
        4 => [['07:30', '12:00'], ['14:00', '17:30']], // Jueves
        5 => [['07:30', '12:00'], ['14:00', '17:00']], // Viernes
        6 => [['08:00', '12:30']]                      // Sábado
    ];
    
    // Verificar si estamos en horario laboral
    $enHorarioLaboral = false;
    if ($diaSemanaActual <= 6 && isset($horariosLaborales[$diaSemanaActual])) {
        foreach ($horariosLaborales[$diaSemanaActual] as $rango) {
            if ($horaActual >= $rango[0] && $horaActual <= $rango[1]) {
                $enHorarioLaboral = true;
                break;
            }
        }
    }
    
    // Calcular tiempo transcurrido de forma simple
    $inicio_ausencia = new DateTime($permiso_activo['inicio_ausencia']);
    $fin_ausencia_aprox = new DateTime($permiso_activo['fin_ausencia_aprox']);
    
    // Calcular minutos transcurridos desde el inicio
    $diferencia = $ahora->diff($inicio_ausencia);
    $minutosTranscurridos = ($diferencia->days * 24 * 60) + ($diferencia->h * 60) + $diferencia->i;
    
    // Calcular minutos estimados total
    $diferenciaTotal = $fin_ausencia_aprox->diff($inicio_ausencia);
    $minutosEstimadosTotal = ($diferenciaTotal->days * 24 * 60) + ($diferenciaTotal->h * 60) + $diferenciaTotal->i;
    
    $response = [
        'success' => true,
        'permiso_activo' => true,
        'en_horario_laboral' => $enHorarioLaboral,
        'id_permiso' => $permiso_activo['id_permiso'],
        'tipo_permiso' => $permiso_activo['tipo_permiso'],
        'inicio_ausencia' => $permiso_activo['inicio_ausencia'],
        'fin_ausencia_aprox' => $permiso_activo['fin_ausencia_aprox'],
        'minutos_transcurridos' => $minutosTranscurridos,
        'minutos_estimados_total' => $minutosEstimadosTotal,
        'fecha_salida' => $permiso_activo['fecha_salida'],
        'hora_salida' => $permiso_activo['hora_salida'],
        'fecha_regreso_aprox' => $permiso_activo['fecha_regreso_aprox'],
        'hora_regreso_aprox' => $permiso_activo['hora_regreso_aprox'],
        'hora_actual_colombia' => $ahora->format('H:i:s')
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Error en widget_tiempo_ausencia.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
