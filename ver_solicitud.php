<?php
session_start();
require 'conexion.php';

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['nombre'])) {
    header('Location: login.php?mensaje=Debes iniciar sesión para acceder a esta página.');
    exit();
}

$cargo = strtolower($_SESSION['cargo'] ?? '');

// Determinar si es vista de gerente o usuario normal
$esGerente = ($cargo === 'gerente');
$esCoordinadorAdmin = in_array($cargo, ['coordinador', 'administrador']);

// Determinar el archivo del panel de regreso según el cargo
$panelRegreso = match (strtolower(trim($cargo))) {
    'administrador' => 'admin_inicio.php',
    'coordinador' => 'coordinador_inicio.php',
    'auxiliar' => 'auxiliar_inicio.php',
    'administrativo' => 'administrativo_inicio.php',
    'gerente', 'gerencia' => 'gerente_inicio.php',
    default => 'inicio.php',
};

$nombrePanel = match (strtolower(trim($cargo))) {
    'administrador' => 'Panel de Administrador',
    'coordinador' => 'Panel de Coordinador',
    'auxiliar' => 'Panel de Auxiliar',
    'administrativo' => 'Panel de Administrativo',
    'gerente', 'gerencia' => 'Panel de Gerente',
    default => 'Panel Principal',
};

// Obtener el ID del permiso desde la URL
$id_permiso = $_GET['id'] ?? null;

if (!$id_permiso) {
    header('Location: gerente_inicio.php?mensaje=ID de permiso no válido.');
    exit();
}

// Consultar los detalles del permiso
if ($esGerente) {
    // Consultar los detalles del permiso para gerente
    $stmt = $pdo->prepare("
        SELECT p.*, u.nombre AS nombre_empleado, u.area, c.nombre_cargo AS cargo
        FROM permisos p
        JOIN usuarios u ON p.id_usuario = u.id_usuario
        JOIN cargo c ON u.id_cargo = c.id_cargo
        WHERE p.id_permiso = :id_permiso
    ");
    $stmt->execute(['id_permiso' => $id_permiso]);
} else {
    // Para usuarios normales, solo pueden ver sus propios permisos
    $stmt = $pdo->prepare("
        SELECT p.*, u.nombre AS nombre_empleado, u.area, c.nombre_cargo AS cargo
        FROM permisos p
        JOIN usuarios u ON p.id_usuario = u.id_usuario
        JOIN cargo c ON u.id_cargo = c.id_cargo
        WHERE p.id_permiso = :id_permiso AND p.id_usuario = :id_usuario
    ");
    $stmt->execute(['id_permiso' => $id_permiso, 'id_usuario' => $_SESSION['usuario_id']]);
}

$permiso = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$permiso) {
    $redirectUrl = $esGerente ? 'gerente_inicio.php' : 'inicio.php';
    header("Location: {$redirectUrl}?mensaje=Permiso no encontrado o no tienes acceso.");
    exit();
}

// Función para calcular tiempo de ausencia en horario laboral
function calcularTiempoAusencia($fecha_salida, $hora_salida, $fecha_regreso_aprox, $hora_regreso_aprox) {
    // Horarios laborales por día
    $horariosLaborales = [
        1 => [['07:30', '12:00'], ['14:00', '17:30']], // Lunes
        2 => [['07:30', '12:00'], ['14:00', '17:30']], // Martes
        3 => [['07:30', '12:00'], ['14:00', '17:30']], // Miércoles
        4 => [['07:30', '12:00'], ['14:00', '17:30']], // Jueves
        5 => [['07:30', '12:00'], ['14:00', '17:00']], // Viernes
        6 => [['08:00', '12:30']]                      // Sábado
    ];
    
    $fechaInicio = new DateTime("{$fecha_salida} {$hora_salida}");
    $fechaFin = new DateTime("{$fecha_regreso_aprox} {$hora_regreso_aprox}");
    $totalMinutos = 0;
    $fechaActual = clone $fechaInicio;
    
    while ($fechaActual <= $fechaFin) {
        $diaSemana = (int)$fechaActual->format('N'); // 1=lunes, 7=domingo
        $fechaStr = $fechaActual->format('Y-m-d');
        
        // Solo procesar días laborales (lunes a sábado)
        if ($diaSemana <= 6 && isset($horariosLaborales[$diaSemana])) {
            foreach ($horariosLaborales[$diaSemana] as $rango) {
                $inicioRango = new DateTime("{$fechaStr} {$rango[0]}");
                $finRango = new DateTime("{$fechaStr} {$rango[1]}");
                
                // Verificar intersección con el periodo de ausencia
                $inicio = max($fechaInicio, $inicioRango);
                $fin = min($fechaFin, $finRango);
                
                if ($inicio < $fin) {
                    $diferencia = $fin->diff($inicio);
                    $minutos = ($diferencia->h * 60) + $diferencia->i;
                    $totalMinutos += $minutos;
                }
            }
        }
        
        // Avanzar al siguiente día
        $fechaActual->add(new DateInterval('P1D'));
        $fechaActual->setTime(0, 0, 0);
    }
    
    return $totalMinutos;
}

// Función para formatear hora en formato AM/PM
function formatearHoraAMPM($hora) {
    return date('g:i A', strtotime($hora));
}

// Calcular tiempo de ausencia
$tiempoAusenciaMinutos = 0;
if ($permiso['fecha_salida'] && $permiso['hora_salida'] && $permiso['fecha_regreso_aprox'] && $permiso['hora_regreso_aprox']) {
    $tiempoAusenciaMinutos = calcularTiempoAusencia(
        $permiso['fecha_salida'], 
        $permiso['hora_salida'], 
        $permiso['fecha_regreso_aprox'], 
        $permiso['hora_regreso_aprox']
    );
}

$horasAusencia = floor($tiempoAusenciaMinutos / 60);
$minutosAusencia = $tiempoAusenciaMinutos % 60;

// Determinar si el gerente puede editar persona encargada
$cargoSolicitante = strtolower($permiso['cargo']);
$puedeEditarEncargado = $esGerente && in_array($cargoSolicitante, ['administrador', 'coordinador', 'administrativo']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalles del Permiso</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f9;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 25px;
            max-width: 600px;
            width: 100%;
        }
        h1 {
            font-size: 24px;
            color: #333;
            margin-bottom: 20px;
            text-align: center;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        .info-section {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #007bff;
        }
        .tiempo-ausencia {
            background-color: #e8f4fd;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #17a2b8;
            text-align: center;
        }
        .tiempo-ausencia h3 {
            color: #17a2b8;
            margin: 0 0 15px 0;
            font-size: 18px;
        }
        .tiempo-valor {
            font-size: 28px;
            font-weight: bold;
            color: #17a2b8;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
        }
        .rechazo-section {
            background-color: #fff3cd;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #ffc107;
        }
        p {
            margin: 10px 0;
            font-size: 14px;
        }
        .label {
            font-weight: bold;
            color: #495057;
            display: inline-block;
            min-width: 120px;
        }
        .btn {
            display: inline-block;
            padding: 10px 15px;
            color: #fff;
            text-decoration: none;
            border-radius: 4px;
            margin: 5px;
            text-align: center;
            font-size: 14px;
            cursor: pointer;
            border: none;
        }
        .btn-back {
            background-color: #007bff;
        }
        .btn-back:hover {
            background-color: #0056b3;
        }
        .btn-approve {
            background-color: #28a745;
        }
        .btn-approve:hover {
            background-color: #218838;
        }
        .btn-reject {
            background-color: #dc3545;
        }
        .btn-reject:hover {
            background-color: #c82333;
        }
        .btn-cancel {
            background-color: #6c757d;
        }
        .btn-cancel:hover {
            background-color: #5a6268;
        }
        textarea {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ced4da;
            border-radius: 4px;
            resize: vertical;
            font-size: 14px;
            box-sizing: border-box;
        }
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 4px;
            margin: 10px 0;
            border: 1px solid #c3e6cb;
        }
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 4px;
            margin: 10px 0;
            border: 1px solid #f5c6cb;
        }
        .actions-section {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }
        .motivo-rechazo {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
            border-left: 4px solid #dc3545;
        }
        #rechazo-container {
            background-color: #fff;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #dee2e6;
            margin-top: 10px;
        }
        .motivo-rechazo-container {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 20px;
            margin-top: 15px;
            border-left: 4px solid #ffc107;
        }
        .motivo-rechazo-container h4 {
            color: #856404;
            margin: 0 0 15px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .motivo-rechazo-container .required-text {
            font-size: 13px;
            color: #6c757d;
            margin-bottom: 10px;
            font-style: italic;
        }
        .textarea-container {
            position: relative;
        }
        .char-counter {
            position: absolute;
            bottom: 5px;
            right: 10px;
            font-size: 11px;
            color: #6c757d;
            background: rgba(255,255,255,0.8);
            padding: 2px 6px;
            border-radius: 3px;
        }
        .btn-rechazar-final {
            width: 100%;
            margin-top: 10px;
            padding: 12px;
            font-size: 16px;
            font-weight: bold;
        }
        .hora-formato {
            font-weight: bold;
            color: #17a2b8;
        }
        /* Eliminar estilos del botón superior */
        .btn-volver-panel {
            display: none; /* Ocultar completamente */
        }
        
        /* Simplificar header sin botón */
        .header-container {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #dee2e6;
            text-align: center;
        }
        
        .header-container h1 {
            margin: 0;
            font-size: 24px;
            color: #333;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
            display: inline-block;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header simplificado solo con título -->
        <div class="header-container">
            <h1>📄 Detalles del Permiso #<?= htmlspecialchars($permiso['id_permiso']) ?></h1>
        </div>
        
        <div class="info-section">
            <p><span class="label">👤 Empleado:</span> <?= htmlspecialchars($permiso['nombre_empleado']) ?></p>
            <p><span class="label">🏢 Área:</span> <?= htmlspecialchars($permiso['area']) ?></p>
            <p><span class="label">💼 Cargo:</span> <?= htmlspecialchars($permiso['cargo']) ?></p>
            <p><span class="label">📝 Tipo:</span> <?= htmlspecialchars($permiso['tipo_permiso']) ?></p>
            <p><span class="label">📊 Estado:</span> <strong><?= ucfirst(htmlspecialchars($permiso['estado'])) ?></strong></p>
            <p><span class="label">👨‍💼 Encargado en ausencia:</span> <?= htmlspecialchars($permiso['encargado_ausencia'] ?? 'No especificado') ?></p>
        </div>

        <div class="info-section">
            <p><span class="label">💬 Motivo:</span> <?= htmlspecialchars($permiso['motivo']) ?></p>
            <p><span class="label">📅 Fecha Salida:</span> <?= date('d/m/Y', strtotime($permiso['fecha_salida'])) ?></p>
            <p><span class="label">🕐 Hora Salida:</span> <span class="hora-formato"><?= formatearHoraAMPM($permiso['hora_salida']) ?></span></p>
            <p><span class="label">📅 Fecha Aprox. Regreso:</span> <?= date('d/m/Y', strtotime($permiso['fecha_regreso_aprox'])) ?></p>
            <p><span class="label">🕐 Hora Aprox. Regreso:</span> <span class="hora-formato"><?= formatearHoraAMPM($permiso['hora_regreso_aprox']) ?></span></p>
        </div>

        <!-- Mostrar tiempo de ausencia -->
        <div class="tiempo-ausencia">
            <h3>⏰ Tiempo aproximado de Ausencia en Horario Laboral</h3>
            <div class="tiempo-valor">
                <?= $horasAusencia ?> horas y <?= $minutosAusencia ?> minutos
            </div>
        </div>

        <?php if ($permiso['estado'] === 'rechazado' && !empty($permiso['motivo_rechazo'])): ?>
            <div class="motivo-rechazo">
                <h3 style="margin: 0 0 10px 0; color: #721c24;">❌ Motivo del Rechazo</h3>
                <p style="margin: 0; color: #721c24; font-style: italic;"><?= htmlspecialchars($permiso['motivo_rechazo']) ?></p>
            </div>
        <?php endif; ?>

        <div id="mensaje-container"></div>

        <?php if (in_array($permiso['estado'], ['pendiente', 'reenviado']) && $permiso['asignado_a'] === 'gerente'): ?>
            <div class="actions-section">
                <form id="formAccion">
                    <input type="hidden" name="id_permiso" value="<?= $permiso['id_permiso'] ?>">

                    <!-- CAMPO EDITABLE PARA GERENTE: Persona Encargada (solo para admin/coord/administrativo) -->
                    <?php if ($puedeEditarEncargado): ?>
                    <div style="background-color: #e8f4fd; padding: 15px; border-radius: 8px; margin-bottom: 15px; border-left: 4px solid #17a2b8;">
                        <h4 style="color: #17a2b8; margin: 0 0 10px 0;">👨‍💼 Asignar Persona Encargada</h4>
                        <p style="color: #495057; font-size: 13px; margin-bottom: 10px;">
                            Como gerente, puedes asignar quién estará encargado durante la ausencia de este empleado.
                        </p>
                        <label for="encargado_ausencia_gerente" style="font-weight: bold; margin-bottom: 5px; display: block;">
                            Persona Encargada en Ausencia:
                        </label>
                        <input type="text" 
                               id="encargado_ausencia_gerente" 
                               name="encargado_ausencia" 
                               value="<?= htmlspecialchars($permiso['encargado_ausencia'] ?? '') ?>"
                               placeholder="Nombre completo de la persona encargada"
                               style="width: 100%; padding: 8px; border: 1px solid #ced4da; border-radius: 4px; box-sizing: border-box;">
                        <small style="color: #6c757d; font-style: italic;">
                            Este campo es opcional. Déjalo vacío si no aplica.
                        </small>
                    </div>
                    <?php endif; ?>

                    <?php if ($permiso['estado'] === 'pendiente'): ?>
                        <!-- PENDIENTE: mostrar Aprobar, Rechazar (con motivo obligatorio) y Volver -->
                        <div id="rechazo-container">
                            <label for="motivo_rechazo">Motivo del rechazo <span style="color:#dc3545">*</span></label>
                            <textarea id="motivo_rechazo" name="motivo_rechazo" rows="4" required placeholder="Explica detalladamente por qué rechazas este permiso..."></textarea>
                        </div>

                        <div style="margin-top:10px;">
                            <button type="button" onclick="procesarAccion('aprobar')" class="btn btn-approve">✅ Aprobar</button>
                            <button type="button" onclick="procesarAccion('rechazar')" class="btn btn-reject">❌ Rechazar (con motivo)</button>
                            <button type="button" onclick="window.location.href='<?= $panelRegreso ?>'" class="btn btn-back">← Volver al <?= $nombrePanel ?></button>
                        </div>

                    <?php else: /* reenviado */ ?>
                        <!-- REENVIADO: permitir Aprobar, Cancelar definitivamente y Volver -->
                        <div style="margin-top:10px;">
                            <button type="button" onclick="procesarAccion('aprobar')" class="btn btn-approve">✅ Aprobar</button>
                            <button type="button" onclick="procesarAccion('cancelar')" class="btn btn-cancel">🚫 Cancelar Definitivamente</button>
                            <button type="button" onclick="window.location.href='<?= $panelRegreso ?>'" class="btn btn-back">← Volver al <?= $nombrePanel ?></button>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        <?php elseif ($permiso['estado'] === 'rechazado' && $permiso['id_usuario'] == $_SESSION['usuario_id']): ?>
            <!-- PERMISO RECHAZADO: mostrar opciones para corregir y reenviar -->
            <div class="actions-section">
                <h3 style="color: #333; margin-bottom: 15px;">🔄 Permiso Rechazado - Opciones Disponibles</h3>
                <p style="color: #666; margin-bottom: 15px;">
                    Tu permiso ha sido rechazado. Puedes corregir los campos y reenviarlo.
                </p>
                
                <!-- Formulario para actualizar campos -->
                <div id="editar-container" style="background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 15px;">
                    <h4 style="color: #495057; margin-bottom: 15px;">📝 Corregir Datos del Permiso</h4>
                    
                    <form id="formActualizarCampos">
                        <input type="hidden" name="id_permiso" value="<?= $permiso['id_permiso'] ?>">
                        <input type="hidden" name="accion" value="actualizar_campos">
                        
                        <div style="margin-bottom: 15px;">
                            <label for="motivo_edit" style="display: block; font-weight: bold; margin-bottom: 5px;">Motivo *</label>
                            <textarea id="motivo_edit" name="motivo" rows="3" required 
                                      style="width: 100%; padding: 8px; border: 1px solid #ced4da; border-radius: 4px; box-sizing: border-box;"
                                      placeholder="Describe el motivo de tu permiso..."><?= htmlspecialchars($permiso['motivo']) ?></textarea>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                            <div>
                                <label for="fecha_salida_edit" style="display: block; font-weight: bold; margin-bottom: 5px;">Fecha de Salida *</label>
                                <input type="date" id="fecha_salida_edit" name="fecha_salida" required 
                                       value="<?= $permiso['fecha_salida'] ?>"
                                       style="width: 100%; padding: 8px; border: 1px solid #ced4da; border-radius: 4px; box-sizing: border-box;">
                            </div>
                            <div>
                                <label for="hora_salida_edit" style="display: block; font-weight: bold; margin-bottom: 5px;">Hora de Salida *</label>
                                <input type="time" id="hora_salida_edit" name="hora_salida" required 
                                       value="<?= $permiso['hora_salida'] ?>"
                                       style="width: 100%; padding: 8px; border: 1px solid #ced4da; border-radius: 4px; box-sizing: border-box;">
                            </div>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                            <div>
                                <label for="fecha_regreso_edit" style="display: block; font-weight: bold; margin-bottom: 5px;">Fecha de Regreso *</label>
                                <input type="date" id="fecha_regreso_edit" name="fecha_regreso_aprox" required 
                                       value="<?= $permiso['fecha_regreso_aprox'] ?>"
                                       style="width: 100%; padding: 8px; border: 1px solid #ced4da; border-radius: 4px; box-sizing: border-box;">
                            </div>
                            <div>
                                <label for="hora_regreso_edit" style="display: block; font-weight: bold; margin-bottom: 5px;">Hora de Regreso *</label>
                                <input type="time" id="hora_regreso_edit" name="hora_regreso_aprox" required 
                                       value="<?= $permiso['hora_regreso_aprox'] ?>"
                                       style="width: 100%; padding: 8px; border: 1px solid #ced4da; border-radius: 4px; box-sizing: border-box;">
                            </div>
                        </div>
                        
                        <div style="text-align: center;">
                            <button type="button" onclick="actualizarCampos()" class="btn btn-approve" style="margin-right: 10px;">
                                💾 Guardar Cambios
                            </button>
                            <button type="button" onclick="reenviarPermiso()" class="btn" style="background-color: #17a2b8; margin-right: 10px;">
                                🔄 Reenviar <?= $esCoordinadorAdmin ? 'al Gerente' : 'al Coordinador' ?>
                            </button>
                            <button type="button" onclick="window.location.href='<?= $panelRegreso ?>'" class="btn btn-back">
                                ← Volver al <?= $nombrePanel ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php elseif (in_array($permiso['estado'], ['cancelado', 'rechazado', 'aprobado', 'finalizado'])): ?>
            <!-- ESTADOS FINALES: solo mostrar botón volver -->
            <div class="actions-section">
                <h3 style="color: #333; margin-bottom: 15px;">ℹ️ Permiso <?= ucfirst($permiso['estado']) ?></h3>
                <p style="color: #666; margin-bottom: 15px;">
                    <?php if ($permiso['estado'] === 'cancelado'): ?>
                        Este permiso ha sido cancelado definitivamente.
                    <?php elseif ($permiso['estado'] === 'rechazado'): ?>
                        Este permiso ha sido rechazado.
                    <?php elseif ($permiso['estado'] === 'finalizado'): ?>
                        Este permiso ha sido completado exitosamente.
                    <?php else: ?>
                        Este permiso ha sido aprobado exitosamente.
                    <?php endif; ?>
                </p>
                <button type="button" onclick="window.location.href='<?= $panelRegreso ?>'" class="btn btn-back">← Volver al <?= $nombrePanel ?></button>
            </div>
        <?php endif; ?>

    </div>

    <script>
        function mostrarRechazo() {
            // El contenedor ya está visible, solo hacer focus en el textarea
            document.getElementById('motivo_rechazo').focus();
            
            // Scroll suave hacia el contenedor de rechazo
            document.getElementById('rechazo-container').scrollIntoView({ 
                behavior: 'smooth', 
                block: 'nearest' 
            });
        }

        function actualizarContador() {
            const textarea = document.getElementById('motivo_rechazo');
            if (!textarea) return;
            
            const counter = document.getElementById('char-counter');
            if (!counter) return;
            
            const length = textarea.value.length;
            
            counter.textContent = `${length}/500`;
            
            // Cambiar color según la cantidad de caracteres
            if (length < 10) {
                counter.style.color = '#dc3545'; // Rojo - muy poco texto
            } else if (length < 50) {
                counter.style.color = '#ffc107'; // Amarillo - poco texto
            } else {
                counter.style.color = '#28a745'; // Verde - texto adecuado
            }
        }

        function procesarAccion(accion) {
            const form = document.getElementById('formAccion');
            const container = document.querySelector('.container');
            
            console.log('🔍 Procesando acción:', accion);
            
            // Validar motivo de rechazo si es necesario
            if (accion === 'rechazar') {
                const motivo = document.getElementById('motivo_rechazo').value.trim();
                
                if (!motivo) {
                    mostrarMensaje('⚠️ El motivo del rechazo es obligatorio.', 'error');
                    document.getElementById('motivo_rechazo').focus();
                    return;
                }
                
                if (motivo.length < 10) {
                    mostrarMensaje('⚠️ El motivo debe tener al menos 10 caracteres.', 'error');
                    document.getElementById('motivo_rechazo').focus();
                    return;
                }
            }

            // Confirmar acción
            const confirmaciones = {
                'aprobar': '✅ ¿Aprobar este permiso?',
                'rechazar': `❌ ¿Rechazar con motivo: "${document.getElementById('motivo_rechazo')?.value.substring(0, 50)}..."?`,
                'cancelar': '🚫 ¿Cancelar definitivamente este permiso?'
            };

            if (!confirm(confirmaciones[accion])) {
                return;
            }

            // Mostrar estado de carga
            container.classList.add('loading');
            mostrarMensaje('⏳ Procesando...', 'info');

            // Preparar datos - INCLUIR PERSONA ENCARGADA SI EXISTE
            const formData = new FormData(form);
            formData.append('accion', accion);
            
            // Agregar persona encargada si el gerente puede editarla
            const encargadoField = document.getElementById('encargado_ausencia_gerente');
            if (encargadoField) {
                formData.append('encargado_ausencia', encargadoField.value.trim());
            }

            console.log('📤 Enviando datos:', {
                accion: accion,
                id_permiso: formData.get('id_permiso'),
                motivo_rechazo: formData.get('motivo_rechazo')?.substring(0, 50) + '...',
                encargado_ausencia: formData.get('encargado_ausencia') || 'No especificado'
            });

            // Enviar petición AJAX
            fetch('permisos_acciones.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json'
                },
                body: formData
            })
            .then(response => {
                console.log('📥 Respuesta HTTP status:', response.status);
                if (!response.ok) {
                    return response.text().then(text => { 
                        throw new Error(`HTTP ${response.status}: ${text}`); 
                    });
                }
                return response.text().then(txt => {
                    console.log('📄 Respuesta cruda:', txt);
                    try {
                        return JSON.parse(txt);
                    } catch (e) {
                        throw new Error(`Respuesta no es JSON válido: ${txt}`);
                    }
                });
            })
            .then(data => {
                container.classList.remove('loading');
                console.log('✅ Datos procesados:', data);
                
                if (data && data.success) {
                    const mensajes = {
                        'aprobar': '✅ Permiso aprobado exitosamente',
                        'rechazar': '📝 Permiso rechazado y enviado de vuelta',
                        'cancelar': '🚫 Permiso cancelado definitivamente'
                    };
                    
                    mostrarMensaje(mensajes[accion] || data.message, 'success');
                    
                    // Ocultar formulario de acciones
                    const actionsSection = document.querySelector('.actions-section');
                    if (actionsSection) actionsSection.style.display = 'none';
                    
                    // Redirigir después de 2 segundos
                    setTimeout(() => {
                        window.location.href = '<?= $panelRegreso ?>?msg=' + encodeURIComponent(mensajes[accion]);
                    }, 2000);
                } else {
                    const error = data?.error || 'Error desconocido al procesar la solicitud';
                    mostrarMensaje('❌ Error: ' + error, 'error');
                    console.error('❌ Error del servidor:', data);
                }
            })
            .catch(error => {
                container.classList.remove('loading');
                console.error('💥 Error de fetch:', error);
                mostrarMensaje('🔌 Error: ' + error.message, 'error');
            });
        }

        function mostrarMensaje(mensaje, tipo) {
            const container = document.getElementById('mensaje-container');
            const className = tipo === 'success' ? 'success-message' : (tipo === 'info' ? 'info-message' : 'error-message');
            container.innerHTML = `<div class="${className}">${mensaje}</div>`;
            container.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        // Inicializar contador cuando carga la página
        document.addEventListener('DOMContentLoaded', function() {
            const textarea = document.getElementById('motivo_rechazo');
            if (textarea) {
                actualizarContador();
            }
        });

        function actualizarCampos() {
            const form = document.getElementById('formActualizarCampos');
            const container = document.querySelector('.container');
            
            // Validar campos
            const motivo = document.getElementById('motivo_edit').value.trim();
            const fechaSalida = document.getElementById('fecha_salida_edit').value;
            const horaSalida = document.getElementById('hora_salida_edit').value;
            const fechaRegreso = document.getElementById('fecha_regreso_edit').value;
            const horaRegreso = document.getElementById('hora_regreso_edit').value;
            
            console.log('🔍 Datos a enviar:', {
                motivo: motivo,
                fechaSalida: fechaSalida,
                horaSalida: horaSalida,
                fechaRegreso: fechaRegreso,
                horaRegreso: horaRegreso
            });
            
            if (!motivo || !fechaSalida || !horaSalida || !fechaRegreso || !horaRegreso) {
                mostrarMensaje('⚠️ Todos los campos son obligatorios.', 'error');
                return;
            }
            
            if (motivo.length < 10) {
                mostrarMensaje('⚠️ El motivo debe tener al menos 10 caracteres.', 'error');
                document.getElementById('motivo_edit').focus();
                return;
            }
            
            // Validar fechas
            const ahora = new Date();
            const salidaDateTime = new Date(fechaSalida + ' ' + horaSalida);
            const regresoDateTime = new Date(fechaRegreso + ' ' + horaRegreso);
            
            if (salidaDateTime < ahora) {
                mostrarMensaje('⚠️ La fecha y hora de salida no puede ser en el pasado.', 'error');
                return;
            }
            
            if (regresoDateTime <= salidaDateTime) {
                mostrarMensaje('⚠️ La fecha y hora de regreso debe ser posterior a la de salida.', 'error');
                return;
            }
            
            if (!confirm('💾 ¿Actualizar los campos del permiso?')) {
                return;
            }
            
            // Mostrar estado de carga
            container.classList.add('loading');
            mostrarMensaje('⏳ Actualizando campos...', 'info');
            
            // Enviar datos
            const formData = new FormData(form);
            
            // Debug: mostrar todos los datos del FormData
            console.log('📤 FormData completo:');
            for (let [key, value] of formData.entries()) {
                console.log(`  ${key}: ${value}`);
            }
            
            fetch('actualizar_campos_permiso.php', {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            })
            .then(response => {
                console.log('📥 Status:', response.status);
                return response.text();
            })
            .then(text => {
                console.log('📄 Respuesta cruda:', text);
                try {
                    return JSON.parse(text);
                } catch (e) {
                    throw new Error(`Respuesta no es JSON válido: ${text}`);
                }
            })
            .then(data => {
                container.classList.remove('loading');
                console.log('✅ Datos procesados:', data);
                
                if (data.success) {
                    mostrarMensaje('✅ Campos actualizados correctamente. Ahora puedes reenviar el permiso.', 'success');
                    
                    // Actualizar los valores mostrados en la página (opcional)
                    if (data.debug && data.debug.valores_actualizados) {
                        console.log('🔄 Actualizando interfaz con nuevos valores...');
                    }
                } else {
                    mostrarMensaje('❌ Error: ' + (data.error || 'Error desconocido'), 'error');
                    console.error('❌ Error del servidor:', data);
                }
            })
            .catch(error => {
                container.classList.remove('loading');
                console.error('💥 Error completo:', error);
                mostrarMensaje('🔌 Error de conexión: ' + error.message, 'error');
            });
        }
        
        function reenviarPermiso() {
            const container = document.querySelector('.container');
            const idPermiso = <?= $permiso['id_permiso'] ?>;
            
            if (!confirm('🔄 ¿Reenviar este permiso corregido?')) {
                return;
            }
            
            // Mostrar estado de carga
            container.classList.add('loading');
            mostrarMensaje('⏳ Reenviando permiso...', 'info');
            
            // Preparar datos
            const formData = new FormData();
            formData.append('id_permiso', idPermiso);
            
            fetch('reenviar_permiso.php', {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                container.classList.remove('loading');
                
                if (data.success) {
                    mostrarMensaje('✅ Permiso reenviado correctamente', 'success');
                    
                    // Ocultar formulario de edición
                    const editarContainer = document.getElementById('editar-container');
                    if (editarContainer) editarContainer.style.display = 'none';
                    
                    // Redirigir después de 2 segundos
                    setTimeout(() => {
                        window.location.href = '<?= $panelRegreso ?>?msg=' + encodeURIComponent('Permiso reenviado exitosamente');
                    }, 2000);
                } else {
                    mostrarMensaje('❌ Error: ' + (data.error || 'Error desconocido'), 'error');
                }
            })
            .catch(error => {
                container.classList.remove('loading');
                mostrarMensaje('🔌 Error de conexión: ' + error.message, 'error');
            });
        }
    </script>
</body>
</html>
