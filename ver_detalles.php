<?php
session_start();
require 'conexion.php';

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php?mensaje=Debes iniciar sesión para acceder a esta página.');
    exit();
}

// Obtener el ID del permiso
$id_permiso = $_GET['id'] ?? null;
$cargo = strtolower($_SESSION['cargo'] ?? '');

if (!$id_permiso) {
    header('Location: ver_permisos.php?mensaje=ID de permiso no válido.');
    exit();
}

// Consultar los detalles completos del permiso
$stmt = $pdo->prepare("
    SELECT p.*, u.nombre AS nombre_empleado, u.area, c.nombre_cargo AS cargo
    FROM permisos p
    JOIN usuarios u ON p.id_usuario = u.id_usuario
    JOIN cargo c ON u.id_cargo = c.id_cargo
    WHERE p.id_permiso = :id_permiso AND p.id_usuario = :id_usuario
");
$stmt->execute(['id_permiso' => $id_permiso, 'id_usuario' => $_SESSION['usuario_id']]);
$permiso = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$permiso) {
    header('Location: ver_permisos.php?mensaje=Permiso no encontrado o no tienes acceso.');
    exit();
}

// Función para formatear hora en formato AM/PM
function formatearHoraAMPM($hora) {
    return date('g:i A', strtotime($hora));
}

// Función para calcular tiempo de ausencia en horario laboral
function calcularTiempoAusencia($fecha_salida, $hora_salida, $fecha_regreso_aprox, $hora_regreso_aprox) {
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
        $diaSemana = (int)$fechaActual->format('N');
        $fechaStr = $fechaActual->format('Y-m-d');
        
        if ($diaSemana <= 6 && isset($horariosLaborales[$diaSemana])) {
            foreach ($horariosLaborales[$diaSemana] as $rango) {
                $inicioRango = new DateTime("{$fechaStr} {$rango[0]}");
                $finRango = new DateTime("{$fechaStr} {$rango[1]}");
                
                $inicio = max($fechaInicio, $inicioRango);
                $fin = min($fechaFin, $finRango);
                
                if ($inicio < $fin) {
                    $diferencia = $fin->diff($inicio);
                    $minutos = ($diferencia->h * 60) + $diferencia->i;
                    $totalMinutos += $minutos;
                }
            }
        }
        
        $fechaActual->add(new DateInterval('P1D'));
        $fechaActual->setTime(0, 0, 0);
    }
    
    return $totalMinutos;
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

// Determinar el archivo del panel según el cargo del usuario
$archivoPanel = match ($cargo) {
    'administrador' => 'admin_inicio.php',
    'coordinador' => 'coordinador_inicio.php',
    'auxiliar' => 'auxiliar_inicio.php',
    default => 'login.php',
};
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
            max-width: 700px;
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
        .motivo-rechazo {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
            border-left: 4px solid #dc3545;
        }
        .motivo-rechazo h3 {
            margin: 0 0 10px 0;
            color: #721c24;
        }
        .edit-section {
            background-color: #fff3cd;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #ffc107;
        }
        .edit-section h3 {
            color: #856404;
            margin: 0 0 15px 0;
            display: flex;
            align-items: center;
            gap: 8px;
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
        .hora-formato {
            font-weight: bold;
            color: #17a2b8;
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
        .btn-reenviar {
            background-color: #28a745;
            width: 100%;
            margin-top: 10px;
            padding: 12px;
            font-size: 16px;
            font-weight: bold;
        }
        .btn-reenviar:hover {
            background-color: #218838;
        }
        .btn-cancel {
            background-color: #6c757d;
            width: 100%;
            margin-top: 5px;
            padding: 12px;
            font-size: 16px;
            font-weight: bold;
        }
        .btn-cancel:hover {
            background-color: #545b62;
        }
        label {
            font-weight: bold;
            margin-bottom: 5px;
            display: block;
            color: #495057;
        }
        input, select, textarea {
            width: 100%;
            padding: 8px;
            margin-bottom: 15px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }
        textarea {
            resize: vertical;
            height: 100px;
        }
        .form-row {
            display: flex;
            gap: 15px;
        }
        .form-col {
            flex: 1;
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
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }
        .actions-section {
            text-align: center;
            margin-top: 20px;
        }
        .char-counter {
            font-size: 12px;
            color: #6c757d;
            text-align: right;
            margin-top: -10px;
            margin-bottom: 15px;
        }
        .readonly-info {
            background-color: #e9ecef;
            color: #495057;
            padding: 8px;
            border-radius: 4px;
            margin-bottom: 15px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📄 Detalles del Permiso #<?= htmlspecialchars($permiso['id_permiso']) ?></h1>
        
        <div class="info-section">
            <p><span class="label">👤 Empleado:</span> <?= htmlspecialchars($permiso['nombre_empleado']) ?></p>
            <p><span class="label">🏢 Área:</span> <?= htmlspecialchars($permiso['area']) ?></p>
            <p><span class="label">💼 Cargo:</span> <?= htmlspecialchars($permiso['cargo']) ?></p>
            <p><span class="label">📝 Tipo:</span> <?= htmlspecialchars($permiso['tipo_permiso']) ?></p>
            <p><span class="label">📊 Estado:</span> <strong><?= ucfirst(htmlspecialchars($permiso['estado'])) ?></strong></p>
            <p><span class="label">👨‍💼 Encargado en ausencia:</span> <?= htmlspecialchars($permiso['encargado_ausencia'] ?? 'No especificado') ?></p>
        </div>

        <!-- Mostrar tiempo de ausencia -->
        <div class="tiempo-ausencia">
            <h3>⏰ Tiempo de Ausencia en Horario Laboral</h3>
            <div class="tiempo-valor">
                <?= $horasAusencia ?> horas y <?= $minutosAusencia ?> minutos
            </div>
        </div>

        <?php if ($permiso['estado'] === 'rechazado' && !empty($permiso['motivo_rechazo'])): ?>
            <div class="motivo-rechazo">
                <h3>❌ Motivo del Rechazo</h3>
                <p style="margin: 0; font-style: italic;"><?= htmlspecialchars($permiso['motivo_rechazo']) ?></p>
            </div>
        <?php endif; ?>

        <div id="mensaje-container"></div>

        <?php 
        if ($permiso['estado'] === 'rechazado' && $permiso['id_usuario'] == $_SESSION['usuario_id']): 
        ?>
            <div class="edit-section">
                <h3>✏️ Corregir y Reenviar Solicitud</h3>
                <p style="color: #856404; margin-bottom: 15px; font-size: 13px;">
                    Revisa el motivo del rechazo y realiza las correcciones necesarias antes de reenviar.
                </p>
                
                <form id="formCorregir">
                    <!-- CAMPOS OCULTOS NECESARIOS -->
                    <input type="hidden" id="id_permiso_hidden" name="id_permiso" value="<?= htmlspecialchars($permiso['id_permiso']) ?>">
                    <input type="hidden" id="accion_hidden" name="accion" value="reenviar">

                    <label for="tipo_permiso">Tipo de Permiso:</label>
                    <select id="tipo_permiso" name="tipo_permiso" required>
                        <option value="Médicos" <?= $permiso['tipo_permiso'] === 'Médicos' ? 'selected' : '' ?>>Médicos</option>
                        <option value="Laborales" <?= $permiso['tipo_permiso'] === 'Laborales' ? 'selected' : '' ?>>Laborales</option>
                        <option value="Personales" <?= $permiso['tipo_permiso'] === 'Personales' ? 'selected' : '' ?>>Personales</option>
                    </select>

                    <label for="motivo">Motivo (Corregido):</label>
                    <textarea id="motivo" name="motivo" rows="4" required placeholder="Describe detalladamente el motivo corregido de tu solicitud..."><?= htmlspecialchars($permiso['motivo']) ?></textarea>
                    <div class="char-counter" id="char-counter">0/500</div>

                    <div class="form-row">
                        <div class="form-col">
                            <label for="fecha_salida">Fecha de Salida:</label>
                            <input type="date" id="fecha_salida" name="fecha_salida" value="<?= $permiso['fecha_salida'] ?>" required>
                        </div>
                        <div class="form-col">
                            <label for="hora_salida">Hora de Salida:</label>
                            <input type="time" id="hora_salida" name="hora_salida" value="<?= $permiso['hora_salida'] ?>" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <label for="fecha_regreso_aprox">Fecha Aproximada de Regreso:</label>
                            <input type="date" id="fecha_regreso_aprox" name="fecha_regreso_aprox" value="<?= $permiso['fecha_regreso_aprox'] ?>" required>
                        </div>
                        <div class="form-col">
                            <label for="hora_regreso_aprox">Hora Aproximada de Regreso:</label>
                            <input type="time" id="hora_regreso_aprox" name="hora_regreso_aprox" value="<?= $permiso['hora_regreso_aprox'] ?>" required>
                        </div>
                    </div>

                    <label for="encargado_ausencia">Persona Encargada en su Ausencia:</label>
                    <input type="text" id="encargado_ausencia" name="encargado_ausencia" value="<?= htmlspecialchars($permiso['encargado_ausencia'] ?? '') ?>" placeholder="Nombre completo">

                    <button type="button" onclick="reenviarPermiso()" class="btn btn-reenviar">📤 Reenviar Solicitud Corregida</button>
                </form>
            </div>
        <?php else: ?>
            <!-- Solo mostrar información del permiso -->
            <div class="info-section">
                <p><span class="label">💬 Motivo:</span> <?= htmlspecialchars($permiso['motivo']) ?></p>
                <p><span class="label">📅 Fecha Salida:</span> <?= date('d/m/Y', strtotime($permiso['fecha_salida'])) ?></p>
                <p><span class="label">🕐 Hora Salida:</span> <span class="hora-formato"><?= formatearHoraAMPM($permiso['hora_salida']) ?></span></p>
                <p><span class="label">📅 Fecha Aprox. Regreso:</span> <?= date('d/m/Y', strtotime($permiso['fecha_regreso_aprox'])) ?></p>
                <p><span class="label">🕐 Hora Aprox. Regreso:</span> <span class="hora-formato"><?= formatearHoraAMPM($permiso['hora_regreso_aprox']) ?></span></p>
            </div>
        <?php endif; ?>
        <div class="actions-section">
            <a href="ver_permisos.php" class="btn btn-back">← Volver a Mis Permisos</a>
            <a href="<?= $archivoPanel ?>" class="btn btn-back" style="background-color: #6c757d;">🏠 Panel Principal</a>
        </div>
    </div>

    <script>
        // Contador de caracteres para el motivo
        document.getElementById('motivo')?.addEventListener('input', function() {
            const counter = document.getElementById('char-counter');
            const length = this.value.length;
            counter.textContent = `${length}/500`;
            
            if (length < 5) {
                counter.style.color = '#dc3545';
            } else if (length < 100) {
                counter.style.color = '#ffc107';
            } else {
                counter.style.color = '#28a745';
            }
        });

        // Inicializar contador
        document.addEventListener('DOMContentLoaded', function() {
            const motivo = document.getElementById('motivo');
            if (motivo) {
                motivo.dispatchEvent(new Event('input'));
            }
        });

        function reenviarPermiso() {
            const container = document.querySelector('.container');
            const idPermiso = <?= $permiso['id_permiso'] ?>;
            
            // VALIDACIÓN MEJORADA DEL ID_PERMISO
            const idPermisoInput = document.getElementById('id_permiso_hidden');
            const idPermisoValue = idPermisoInput ? idPermisoInput.value : '';
            
            console.log('🔍 Debug del formulario:', {
                id_permiso_element: idPermisoInput,
                id_permiso_value: idPermisoValue,
                id_permiso_php: idPermiso
            });

            if (!idPermisoValue || idPermisoValue === '' || isNaN(parseInt(idPermisoValue))) {
                mostrarMensaje('❌ Error: ID de permiso no válido (' + idPermisoValue + ')', 'error');
                console.error('ID de permiso inválido:', idPermisoValue);
                return;
            }
            
            // Validar campos requeridos
            const motivo = document.getElementById('motivo').value.trim();
            if (motivo.length < 5) {
                mostrarMensaje('⚠️ El motivo debe tener al menos 5 caracteres para ser claro y específico.', 'error');
                document.getElementById('motivo').focus();
                return;
            }
            
            const fechaSalida = document.getElementById('fecha_salida').value;
            const fechaRegresoAprox = document.getElementById('fecha_regreso_aprox').value;
            const horaSalida = document.getElementById('hora_salida').value;
            const horaRegresoAprox = document.getElementById('hora_regreso_aprox').value;
            
            if (!fechaSalida || !fechaRegresoAprox || !horaSalida || !horaRegresoAprox) {
                mostrarMensaje('⚠️ Todos los campos de fecha y hora son obligatorios.', 'error');
                return;
            }
            
            const fechaHoraSalida = new Date(`${fechaSalida}T${horaSalida}`);
            const fechaHoraRegreso = new Date(`${fechaRegresoAprox}T${horaRegresoAprox}`);
            
            if (fechaHoraRegreso <= fechaHoraSalida) {
                mostrarMensaje('⚠️ La fecha y hora aproximada de regreso debe ser posterior a la fecha y hora de salida.', 'error');
                return;
            }

            if (!confirm('¿Confirmas que quieres reenviar esta solicitud con las correcciones realizadas?')) {
                return;
            }

            // Mostrar estado de carga
            container.classList.add('loading');
            mostrarMensaje('📤 Actualizando y reenviando solicitud...', 'info');

            // PREPARAR DATOS DE FORMA MANUAL PARA ASEGURAR QUE SE ENVÍEN CORRECTAMENTE
            const formData = new FormData();
            
            // Agregar datos manualmente
            formData.append('accion', 'reenviar');
            formData.append('id_permiso', idPermiso);
            formData.append('tipo_permiso', document.getElementById('tipo_permiso').value);
            formData.append('motivo', motivo);
            formData.append('fecha_salida', fechaSalida);
            formData.append('hora_salida', horaSalida);
            formData.append('fecha_regreso_aprox', fechaRegresoAprox);
            formData.append('hora_regreso_aprox', horaRegresoAprox);
            formData.append('encargado_ausencia', document.getElementById('encargado_ausencia').value);

            console.log('📤 Datos que se enviarán:');
            for (let [key, value] of formData.entries()) {
                console.log(`  ${key}: ${value}`);
            }

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
                    mostrarMensaje('✅ Solicitud corregida y reenviada exitosamente', 'success');
                    
                    // Ocultar formulario de edición
                    const editSection = document.querySelector('.edit-section');
                    if (editSection) editSection.style.display = 'none';
                    
                    // Redirigir después de 2 segundos
                    setTimeout(() => {
                        window.location.href = 'ver_permisos.php?msg=' + encodeURIComponent('Solicitud reenviada exitosamente');
                    }, 2000);
                } else {
                    const error = data?.error || 'Error desconocido al reenviar la solicitud';
                    mostrarMensaje('❌ Error: ' + error, 'error');
                    console.error('❌ Error del servidor:', data);
                }
            })
            .catch(error => {
                container.classList.remove('loading');
                console.error('💥 Error de fetch:', error);
                mostrarMensaje('🔌 Error de conexión: ' + error.message, 'error');
            });
        }

        function cerrarFormulario() {
            if (confirm('¿Estás seguro de que quieres cancelar? Se perderán los cambios realizados.')) {
                window.location.href = 'ver_permisos.php';
            }
        }

        function mostrarMensaje(mensaje, tipo) {
            const container = document.getElementById('mensaje-container');
            const className = tipo === 'success' ? 'success-message' : (tipo === 'info' ? 'info-message' : 'error-message');
            container.innerHTML = `<div class="${className}">${mensaje}</div>`;
            container.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    </script>
</body>
</html>