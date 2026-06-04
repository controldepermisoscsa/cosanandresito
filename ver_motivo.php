<?php
session_start();
require 'conexion.php';

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php?mensaje=Debes iniciar sesión para acceder a esta página.');
    exit();
}

// Verificar si el usuario tiene un cargo válido
$cargo = strtolower($_SESSION['cargo'] ?? '');
if (!in_array($cargo, ['administrador', 'coordinador', 'auxiliar'])) {
    header('Location: login.php?mensaje=No tienes permiso para acceder a esta página.');
    exit();
}

// Obtener el ID del permiso
$id_permiso = $_GET['id'] ?? null;
if (!$id_permiso) {
    header('Location: ver_permisos.php?mensaje=ID de permiso no válido.');
    exit();
}

// Consultar el permiso con información completa
$stmt = $pdo->prepare("
    SELECT p.*, u.nombre as nombre_usuario
    FROM permisos p
    INNER JOIN usuarios u ON p.id_usuario = u.id_usuario
    WHERE p.id_permiso = :id_permiso AND p.id_usuario = :id_usuario
");
$stmt->execute(['id_permiso' => $id_permiso, 'id_usuario' => $_SESSION['usuario_id']]);
$permiso = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$permiso) {
    header('Location: ver_permisos.php?mensaje=Permiso no encontrado.');
    exit();
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
      background-color: #f4f4f9;
      margin: 0;
      padding: 0;
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
    }
    .container {
      background: #fff;
      padding: 25px;
      border-radius: 8px;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
      max-width: 500px;
      width: 100%;
    }
    h1 {
      color: #007bff;
      text-align: center;
      margin-bottom: 20px;
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
      padding: 15px;
      border-radius: 5px;
      margin-bottom: 20px;
      border-left: 4px solid #17a2b8;
    }
    .tiempo-ausencia h3 {
      color: #17a2b8;
      margin: 0 0 10px 0;
      font-size: 16px;
    }
    .tiempo-valor {
      font-size: 24px;
      font-weight: bold;
      color: #17a2b8;
      text-align: center;
    }
    .rechazo-info {
      background-color: #f8d7da;
      color: #721c24;
      padding: 15px;
      border-radius: 5px;
      margin-bottom: 20px;
      border-left: 4px solid #dc3545;
    }
    p {
      font-size: 14px;
      color: #333;
      margin: 8px 0;
    }
    .label {
      font-weight: bold;
      color: #495057;
    }
    .btn {
      display: inline-block;
      background-color: #007bff;
      color: #fff;
      padding: 10px 20px;
      border-radius: 5px;
      text-decoration: none;
      margin: 5px;
      text-align: center;
    }
    .btn:hover {
      background-color: #0056b3;
    }
    .btn-success {
      background-color: #28a745;
    }
    .btn-success:hover {
      background-color: #218838;
    }
    form {
      margin-top: 20px;
    }
    label {
      display: block;
      margin-bottom: 5px;
      font-weight: bold;
      color: #495057;
    }
    input, textarea {
      width: 100%;
      padding: 10px;
      margin-bottom: 15px;
      border: 1px solid #ced4da;
      border-radius: 5px;
      font-size: 14px;
      box-sizing: border-box;
    }
    textarea {
      resize: vertical;
      height: 80px;
    }
    .error {
      color: #dc3545;
      font-size: 14px;
      margin-bottom: 10px;
      background-color: #f8d7da;
      padding: 10px;
      border-radius: 5px;
      border: 1px solid #f5c6cb;
    }
    .form-row {
      display: flex;
      gap: 10px;
    }
    .form-row > div {
      flex: 1;
    }
    .loading {
      display: none;
      color: #007bff;
      text-align: center;
      margin: 10px 0;
    }
    .debug-info {
      background-color: #f8f9fa;
      border: 1px solid #dee2e6;
      border-radius: 5px;
      padding: 10px;
      margin: 10px 0;
      font-family: monospace;
      font-size: 12px;
      color: #495057;
      display: none;
    }
    .show-debug {
      display: block !important;
    }
  </style>
</head>
<body>
  <div class="container">
    <h1>Detalles del Permiso #<?= htmlspecialchars($permiso['id_permiso']) ?></h1>
    
    <div class="info-section">
      <p><span class="label">Solicitante:</span> <?= htmlspecialchars($permiso['nombre_usuario']) ?></p>
      <p><span class="label">Tipo de Permiso:</span> <?= htmlspecialchars($permiso['tipo_permiso']) ?></p>
      <p><span class="label">Estado:</span> <?= ucfirst($permiso['estado']) ?></p>
      <p><span class="label">Motivo:</span> <?= htmlspecialchars($permiso['motivo']) ?></p>
    </div>

    <div class="info-section">
      <p><span class="label">Fecha de Salida:</span> <?= date('d/m/Y', strtotime($permiso['fecha_salida'])) ?></p>
      <p><span class="label">Hora de Salida:</span> <?= date('H:i', strtotime($permiso['hora_salida'])) ?></p>
      <p><span class="label">Fecha Aprox. de Regreso:</span> <?= date('d/m/Y', strtotime($permiso['fecha_regreso_aprox'])) ?></p>
      <p><span class="label">Hora Aprox. de Regreso:</span> <?= date('H:i', strtotime($permiso['hora_regreso_aprox'])) ?></p>
    </div>

    <div class="tiempo-ausencia">
      <h3>⏰ Tiempo de Ausencia en Horario Laboral</h3>
      <div class="tiempo-valor">
        <?= $horasAusencia ?> horas y <?= $minutosAusencia ?> minutos
      </div>
    </div>

    <?php if ($permiso['estado'] === 'rechazado' && !empty($permiso['motivo_rechazo'])): ?>
      <div class="rechazo-info">
        <h3 style="margin: 0 0 10px 0; color: #721c24;">❌ Motivo del Rechazo</h3>
        <p style="margin: 0; color: #721c24;"><?= htmlspecialchars($permiso['motivo_rechazo']) ?></p>
      </div>
    <?php endif; ?>

    <!-- Información de debug (oculta por defecto) -->
    <div id="debug-info" class="debug-info">
      <strong>DEBUG INFO:</strong><br>
      - ID Usuario: <?= $_SESSION['usuario_id'] ?><br>
      - Cargo: <?= $cargo ?><br>
      - ID Permiso: <?= $id_permiso ?><br>
      - Estado Actual: <?= $permiso['estado'] ?><br>
      - Motivo Actual: <?= htmlspecialchars($permiso['motivo']) ?><br>
    </div>

    <!-- Mensaje de error/éxito dinámico -->
    <div id="mensaje" class="error" style="display: none;"></div>
    <div id="loading" class="loading">Procesando...</div>

    <?php if ($permiso['estado'] !== 'reenviado' && $permiso['estado'] === 'rechazado' && in_array($cargo, ['coordinador', 'administrador', 'auxiliar'])): ?>
      <form id="reenviarForm">
        <h2 style="color: #28a745; margin-bottom: 15px;">✏️ Corregir y Reenviar Solicitud</h2>

        <label for="tipo_permiso">Tipo de Permiso</label>
        <input type="text" id="tipo_permiso" name="tipo_permiso" value="<?= htmlspecialchars($permiso['tipo_permiso']) ?>" readonly>

        <label for="motivo">Motivo del Permiso <span style="color: red;">*</span></label>
        <textarea id="motivo" name="motivo" required placeholder="Explica detalladamente el motivo de tu permiso..." minlength="10"><?= htmlspecialchars($permiso['motivo']) ?></textarea>

        <div class="form-row">
          <div>
            <label for="fecha_salida">Fecha de Salida <span style="color: red;">*</span></label>
            <input type="date" id="fecha_salida" name="fecha_salida" value="<?= htmlspecialchars($permiso['fecha_salida']) ?>" required min="<?= date('Y-m-d') ?>">
          </div>
          <div>
            <label for="hora_salida">Hora de Salida <span style="color: red;">*</span></label>
            <input type="time" id="hora_salida" name="hora_salida" value="<?= htmlspecialchars($permiso['hora_salida']) ?>" required>
          </div>
        </div>

        <div class="form-row">
          <div>
            <label for="fecha_regreso_aprox">Fecha Aprox. de Regreso <span style="color: red;">*</span></label>
            <input type="date" id="fecha_regreso_aprox" name="fecha_regreso_aprox" value="<?= htmlspecialchars($permiso['fecha_regreso_aprox']) ?>" required min="<?= date('Y-m-d') ?>">
          </div>
          <div>
            <label for="hora_regreso_aprox">Hora Aprox. de Regreso <span style="color: red;">*</span></label>
            <input type="time" id="hora_regreso_aprox" name="hora_regreso_aprox" value="<?= htmlspecialchars($permiso['hora_regreso_aprox']) ?>" required>
          </div>
        </div>

        <button type="submit" class="btn btn-success">🔄 Corregir y Reenviar</button>
        <button type="button" onclick="toggleDebug()" class="btn" style="background-color: #6c757d;">🐛 Debug</button>
      </form>
    <?php elseif ($permiso['estado'] === 'reenviado'): ?>
      <div style="background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; text-align: center;">
        ✅ <strong>Este permiso ya ha sido corregido y reenviado.</strong><br>
        Está esperando revisión nuevamente.
      </div>
    <?php endif; ?>

    <div style="text-align: center; margin-top: 20px;">
      <a href="ver_permisos.php" class="btn">← Volver a Mis Permisos</a>
    </div>
  </div>

  <script>
  function toggleDebug() {
    const debugDiv = document.getElementById('debug-info');
    debugDiv.classList.toggle('show-debug');
  }

  document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('reenviarForm');
    if (!form) return; // Si no hay formulario, salir
    
    // Validación en tiempo real de fechas
    const fechaSalida = document.getElementById('fecha_salida');
    const fechaRegresoAprox = document.getElementById('fecha_regreso_aprox');
    const horaSalida = document.getElementById('hora_salida');
    const horaRegresoAprox = document.getElementById('hora_regreso_aprox');
    
    function validarFechas() {
      const fsalida = new Date(fechaSalida.value + 'T' + horaSalida.value);
      const fregreso = new Date(fechaRegresoAprox.value + 'T' + horaRegresoAprox.value);
      const ahora = new Date();
      
      if (fsalida < ahora) {
        mostrarError('La fecha y hora de salida no puede ser en el pasado');
        return false;
      }
      
      if (fregreso <= fsalida) {
        mostrarError('La fecha y hora aproximada de regreso debe ser posterior a la de salida');
        return false;
      }
      
      ocultarError();
      return true;
    }
    
    // Agregar eventos de validación
    [fechaSalida, fechaRegresoAprox, horaSalida, horaRegresoAprox].forEach(input => {
      input.addEventListener('change', validarFechas);
    });

    form.addEventListener('submit', function(e) {
      e.preventDefault();
      
      // Validar antes de enviar
      if (!validarFechas()) {
        return;
      }
      
      const loading = document.getElementById('loading');
      const btn = this.querySelector('button[type="submit"]');
      
      // Mostrar loading
      loading.style.display = 'block';
      ocultarError();
      btn.disabled = true;
      btn.textContent = '⏳ Procesando...';
      
      // Mostrar debug info durante el proceso
      document.getElementById('debug-info').classList.add('show-debug');
      
      // PASO 1: Actualizar campos corregidos
      const formData1 = new FormData();
      formData1.append('accion', 'actualizar_campos');
      formData1.append('id_permiso', <?= $id_permiso ?>);
      formData1.append('motivo', document.getElementById('motivo').value.trim());
      formData1.append('fecha_salida', document.getElementById('fecha_salida').value);
      formData1.append('hora_salida', document.getElementById('hora_salida').value);
      formData1.append('fecha_regreso_aprox', document.getElementById('fecha_regreso_aprox').value);
      formData1.append('hora_regreso_aprox', document.getElementById('hora_regreso_aprox').value);
      
      console.log('📤 PASO 1 - Enviando datos para actualizar campos:', {
        accion: 'actualizar_campos',
        id_permiso: <?= $id_permiso ?>,
        motivo: document.getElementById('motivo').value.trim().substring(0, 50) + '...',
        fecha_salida: document.getElementById('fecha_salida').value,
        hora_salida: document.getElementById('hora_salida').value
      });
      
      fetch('actualizar_campos_permiso.php', {
        method: 'POST',
        credentials: 'same-origin',
        body: formData1
      })
      .then(response => {
        console.log('📥 PASO 1 - Respuesta HTTP:', response.status);
        if (!response.ok) {
          throw new Error('Error HTTP: ' + response.status);
        }
        return response.text().then(text => {
          console.log('📄 PASO 1 - Respuesta cruda:', text);
          try {
            return JSON.parse(text);
          } catch (e) {
            console.error('PASO 1 - Respuesta no JSON:', text);
            throw new Error('Respuesta inválida del servidor: ' + text.substring(0, 100));
          }
        });
      })
      .then(data => {
        console.log('✅ PASO 1 - Datos procesados:', data);
        
        if (data.success) {
          console.log('📤 PASO 2 - Iniciando reenvío...');
          
          // PASO 2: Reenviar usando permisos_acciones.php
          const formData2 = new FormData();
          formData2.append('accion', 'reenviar');
          formData2.append('id_permiso', <?= $id_permiso ?>);
          
          return fetch('permisos_acciones.php', {
            method: 'POST',
            credentials: 'same-origin',
            body: formData2
          });
        } else {
          throw new Error(data.error || 'Error al actualizar campos');
        }
      })
      .then(response => {
        console.log('📥 PASO 2 - Respuesta HTTP:', response.status);
        if (!response.ok) {
          throw new Error('Error HTTP en reenvío: ' + response.status);
        }
        return response.text().then(text => {
          console.log('📄 PASO 2 - Respuesta cruda:', text);
          try {
            return JSON.parse(text);
          } catch (e) {
            console.error('PASO 2 - Respuesta no JSON:', text);
            throw new Error('Respuesta inválida en reenvío: ' + text.substring(0, 100));
          }
        });
      })
      .then(data => {
        console.log('✅ PASO 2 - Datos procesados:', data);
        
        loading.style.display = 'none';
        btn.disabled = false;
        btn.textContent = '🔄 Corregir y Reenviar';
        
        if (data.success) {
          // Mostrar mensaje de éxito
          mostrarExito('✅ Permiso corregido y reenviado exitosamente. Redirigiendo...');
          
          // Redirigir después de 3 segundos
          setTimeout(() => {
            window.location.href = 'ver_permisos.php?mensaje=' + encodeURIComponent('Permiso corregido y reenviado exitosamente');
          }, 3000);
        } else {
          mostrarError(data.error || 'Error desconocido en reenvío');
        }
      })
      .catch(error => {
        loading.style.display = 'none';
        btn.disabled = false;
        btn.textContent = '🔄 Corregir y Reenviar';
        mostrarError('Error: ' + error.message);
        console.error('💥 Error completo:', error);
      });
    });
    
    function mostrarError(mensaje) {
      const div = document.getElementById('mensaje');
      div.textContent = mensaje;
      div.className = 'error';
      div.style.display = 'block';
      div.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
    
    function mostrarExito(mensaje) {
      const div = document.getElementById('mensaje');
      div.textContent = mensaje;
      div.className = 'success-message';
      div.style.background = '#d4edda';
      div.style.color = '#155724';
      div.style.border = '1px solid #c3e6cb';
      div.style.display = 'block';
      div.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
    
    function ocultarError() {
      document.getElementById('mensaje').style.display = 'none';
    }
  });
  </script>
</body>
</html>