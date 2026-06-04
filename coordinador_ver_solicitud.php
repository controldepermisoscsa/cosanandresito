<?php
session_start();
require 'conexion.php';

// Verificar si el usuario ha iniciado sesión y es coordinador
if (!isset($_SESSION['usuario_id']) || strtolower($_SESSION['cargo']) !== 'coordinador') {
    header('Location: login.php?mensaje=Debes iniciar sesión como coordinador.');
    exit();
}

$id_coordinador = $_SESSION['usuario_id'];
$id_permiso = $_GET['id'] ?? null;

if (!$id_permiso) {
    header('Location: coordinador_inicio.php?mensaje=ID de permiso no válido.');
    exit();
}

// Consultar el permiso asignado al coordinador (permitir también tomarlo si id_asignado es NULL)
$stmt = $pdo->prepare("
    SELECT p.*, u.nombre AS nombre_empleado, u.area, c.nombre_cargo AS cargo
    FROM permisos p
    JOIN usuarios u ON p.id_usuario = u.id_usuario
    JOIN cargo c ON u.id_cargo = c.id_cargo
    WHERE p.id_permiso = ? 
      AND p.asignado_a = 'coordinador'
      AND (p.id_asignado IS NULL OR p.id_asignado = ?)
");
$stmt->execute([$id_permiso, $id_coordinador]);
$permiso = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$permiso) {
    header('Location: coordinador_inicio.php?mensaje=Permiso no encontrado o no asignado a ti.');
    exit();
}

// Si el permiso no estaba reclamado por ningún coordinador, asignarlo al coordinador que lo abrió
if (empty($permiso['id_asignado']) || intval($permiso['id_asignado']) !== intval($id_coordinador)) {
    $upd = $pdo->prepare("UPDATE permisos SET id_asignado = ? WHERE id_permiso = ?");
    $upd->execute([$id_coordinador, $id_permiso]);
    // Refrescar el valor local para que la vista lo refleje
    $permiso['id_asignado'] = $id_coordinador;
}

// Solo auxiliares pueden tener sus permisos editados por coordinador
$esAuxiliar = strtolower($permiso['cargo']) === 'auxiliar';

// Función para calcular tiempo de ausencia
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
$tiempoAusenciaMinutos = calcularTiempoAusencia(
    $permiso['fecha_salida'], 
    $permiso['hora_salida'], 
    $permiso['fecha_regreso_aprox'], 
    $permiso['hora_regreso_aprox']
);

$horasAusencia = floor($tiempoAusenciaMinutos / 60);
$minutosAusencia = $tiempoAusenciaMinutos % 60;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Revisar Permiso - Coordinador</title>
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
            border-bottom: 2px solid #28a745;
            padding-bottom: 10px;
        }
        .info-section {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }
        .tiempo-ausencia {
            background-color: #e8f4fd;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #17a2b8;
            text-align: center;
        }
        .tiempo-valor {
            font-size: 28px;
            font-weight: bold;
            color: #17a2b8;
        }
        .edit-encargado {
            background-color: #fff3cd;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #ffc107;
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
        .btn-enviar { background-color: #28a745; }
        .btn-enviar:hover { background-color: #218838; }
        .btn-back { background-color: #007bff; }
        .btn-back:hover { background-color: #0056b3; }
        input[type="text"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
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
    </style>
</head>
<body>
    <div class="container">
        <h1>📋 Revisar Permiso #<?= htmlspecialchars($permiso['id_permiso']) ?></h1>
        
        <div class="info-section">
            <p><span class="label">👤 Auxiliar:</span> <?= htmlspecialchars($permiso['nombre_empleado']) ?></p>
            <p><span class="label">🏢 Área:</span> <?= htmlspecialchars($permiso['area']) ?></p>
            <p><span class="label">📝 Tipo:</span> <?= htmlspecialchars($permiso['tipo_permiso']) ?></p>
            <p><span class="label">📊 Estado:</span> <strong><?= ucfirst(htmlspecialchars($permiso['estado'])) ?></strong></p>
            <p><span class="label">💬 Motivo:</span> <?= htmlspecialchars($permiso['motivo']) ?></p>
        </div>

        <div class="info-section">
            <p><span class="label">📅 Fecha Salida:</span> <?= date('d/m/Y', strtotime($permiso['fecha_salida'])) ?></p>
            <p><span class="label">🕐 Hora Salida:</span> <?= date('g:i A', strtotime($permiso['hora_salida'])) ?></p>
            <p><span class="label">📅 Fecha Aprox. Regreso:</span> <?= date('d/m/Y', strtotime($permiso['fecha_regreso_aprox'])) ?></p>
            <p><span class="label">🕐 Hora Aprox. Regreso:</span> <?= date('g:i A', strtotime($permiso['hora_regreso_aprox'])) ?></p>
        </div>

        <div class="tiempo-ausencia">
            <h3>⏰ Tiempo de Ausencia en Horario Laboral</h3>
            <div class="tiempo-valor">
                <?= $horasAusencia ?> horas y <?= $minutosAusencia ?> minutos
            </div>
        </div>

        <div id="mensaje-container"></div>

        <?php if ($esAuxiliar && $permiso['estado'] === 'pendiente'): ?>
        <div class="edit-encargado">
            <h3 style="color: #856404; margin: 0 0 15px 0;">👨‍💼 Asignar Persona Encargada</h3>
            <p style="color: #495057; font-size: 14px; margin-bottom: 15px;">
                Como coordinador, puedes asignar quién estará encargado durante la ausencia del auxiliar.
            </p>
            
            <form id="formEncargado">
                <input type="hidden" name="id_permiso" value="<?= $permiso['id_permiso'] ?>">
                <input type="hidden" name="accion" value="asignar_encargado">
                
                <label for="encargado_ausencia" style="font-weight: bold; margin-bottom: 8px; display: block;">
                    Persona Encargada en Ausencia:
                </label>
                <input type="text" 
                       id="encargado_ausencia" 
                       name="encargado_ausencia" 
                       value="<?= htmlspecialchars($permiso['encargado_ausencia'] ?? '') ?>"
                       placeholder="Nombre completo de la persona que se hará cargo"
                       style="margin-bottom: 10px;">
                <small style="color: #6c757d; font-style: italic; display: block; margin-bottom: 15px;">
                    Este campo es opcional. Puedes dejarlo vacío si no aplica.
                </small>
                
                <div style="text-align: center;">
                    <button type="button" onclick="asignarYEnviar()" class="btn btn-enviar">
                        📤  Enviar a Gerencia
                    </button>
                    <button type="button" onclick="window.location.href='coordinador_inicio.php'" class="btn btn-back">
                        ← Volver al Panel
                    </button>
                </div>
            </form>
        </div>
        <?php else: ?>
        <div style="text-align: center; margin-top: 20px;">
            <button type="button" onclick="window.location.href='coordinador_inicio.php'" class="btn btn-back">
                ← Volver al Panel
            </button>
        </div>
        <?php endif; ?>
    </div>

    <script>
        function asignarYEnviar() {
            const container = document.querySelector('.container');
            const encargado = document.getElementById('encargado_ausencia').value.trim();
            const idPermiso = <?= $permiso['id_permiso'] ?>;
            
            if (!confirm('¿Enviar este permiso a Gerencia?')) {
                return;
            }
            
            container.classList.add('loading');
            mostrarMensaje('⏳ Procesando asignación y enviando a gerencia...', 'info');
            
            const formData = new FormData();
            formData.append('accion', 'enviar_gerente');
            formData.append('id_permiso', idPermiso);
            formData.append('encargado_ausencia', encargado);
            
            fetch('coordinador_acciones.php', {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            })
            .then(response => response.text().then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    throw new Error(`Respuesta no JSON: ${text}`);
                }
            }))
            .then(data => {
                container.classList.remove('loading');
                
                if (data.success) {
                    mostrarMensaje('✅ Permiso enviado a gerencia', 'success');
                    
                    setTimeout(() => {
                        window.location.href = 'coordinador_inicio.php?msg=' + encodeURIComponent('Permiso enviado a gerencia exitosamente');
                    }, 2000);
                } else {
                    mostrarMensaje('❌ Error: ' + (data.error || 'Error desconocido'), 'error');
                }
            })
            .catch(error => {
                container.classList.remove('loading');
                mostrarMensaje('🔌 Error: ' + error.message, 'error');
            });
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
