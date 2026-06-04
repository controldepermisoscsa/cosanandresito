<?php
session_start();
require 'conexion.php';

// Verificar si el usuario tiene el cargo de Administrador
if (!isset($_SESSION['usuario_id']) || strtolower($_SESSION['cargo']) !== 'administrador') {
    header('Location: login.php?mensaje=Acceso denegado. Solo los administradores pueden acceder a este panel.');
    exit();
}

// Obtener el cargo seleccionado en el filtro (si existe)
$filtro_cargo = $_GET['filtro_cargo'] ?? '';

// Consulta para obtener usuarios registrados con el filtro de cargo
$query = "
    SELECT u.id_usuario, u.nombre AS usuario_nombre, u.usuario, u.correo, c.nombre_cargo AS cargo, u.area
    FROM usuarios u
    JOIN cargo c ON u.id_cargo = c.id_cargo
";
if (!empty($filtro_cargo)) {
    $query .= " WHERE c.nombre_cargo = :filtro_cargo";
}
$stmtUsuarios = $pdo->prepare($query);

// Si hay un filtro, ejecutarlo con el parámetro
if (!empty($filtro_cargo)) {
    $stmtUsuarios->execute(['filtro_cargo' => $filtro_cargo]);
} else {
    $stmtUsuarios->execute();
}

// Obtener todos los cargos para el filtro
$stmtCargos = $pdo->query("SELECT nombre_cargo FROM cargo");
$cargos = $stmtCargos->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administrador</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
        }
        .container {
            margin: 20px auto;
            width: 90%;
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        h1 {
            text-align: center;
            color: #333;
        }
        .menu {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .menu a {
            text-decoration: none;
            color: #fff;
            background-color: #f39c12;
            padding: 10px 20px;
            border-radius: 5px;
            transition: background-color 0.3s;
            text-align: center;
        }
        .menu a:hover {
            background-color: #e67e22;
        }
        .btn-solicitar, .btn-cerrar, .btn-ver {
            background-color: #f39c12;
            color: #fff;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
            margin-top: 20px;
            text-align: center;
        }
        .btn-solicitar:hover, .btn-cerrar:hover, .btn-ver:hover {
            background-color: #e67e22;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f39c12;
            color: #fff;
        }
        .btn {
            padding: 5px 10px;
            background-color: #dc3545;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn:hover {
            background-color: #c82333;
        }
        .btn-disabled {
            background-color: #6c757d;
            cursor: not-allowed;
        }
        .mensaje {
            background-color: #d4edda;
            color: #155724;
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .filtro {
            margin-bottom: 20px;
        }
        .filtro select {
            padding: 10px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 14px;
        }
        .filtro button {
            padding: 10px 20px;
            background-color: #f39c12;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .filtro button:hover {
            background-color: #e67e22;
        }
        
        /* Estilos para el widget movible */
        .widget-tiempo-ausencia {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
            color: white;
            padding: 18px;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(243, 156, 18, 0.4);
            min-width: 300px;
            max-width: 340px;
            z-index: 999;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
            cursor: move;
            user-select: none;
        }
        
        .widget-tiempo-ausencia:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(243, 156, 18, 0.5);
        }
        
        .widget-tiempo-ausencia.dragging {
            transform: rotate(3deg) scale(1.05);
            box-shadow: 0 15px 40px rgba(243, 156, 18, 0.6);
            z-index: 1001;
        }
        
        .widget-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
            cursor: move;
            padding: 5px 0;
        }
        
        .widget-header h4 {
            margin: 0;
            font-size: 15px;
            font-weight: 700;
            color: white;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
            flex-grow: 1;
            text-align: center;
        }
        
        .widget-controls {
            display: flex;
            gap: 5px;
        }
        
        .widget-btn {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 12px;
            font-weight: bold;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .widget-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.1);
        }
        
        .tiempo-digital {
            font-family: 'Courier New', 'Monaco', monospace;
            font-size: 24px;
            font-weight: bold;
            text-align: center;
            margin: 15px 0;
            padding: 12px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 10px;
            letter-spacing: 1px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: #fff;
            text-shadow: 1px 1px 3px rgba(0,0,0,0.5);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
        }
        
        .tiempo-principal {
            font-size: 26px;
            line-height: 1;
        }
        
        .tiempo-info {
            font-size: 11px;
            opacity: 0.8;
            font-family: Arial, sans-serif;
            letter-spacing: 0px;
        }
        
        .widget-info {
            font-size: 12px;
            opacity: 0.95;
            margin-bottom: 15px;
            text-align: center;
            line-height: 1.4;
            color: #fff;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
        }
        
        .btn-finalizar {
            background: linear-gradient(45deg, #e74c3c, #c0392b);
            color: white;
            border: none;
            padding: 12px 18px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 700;
            width: 100%;
            transition: all 0.3s ease;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3);
        }
        
        .btn-finalizar:hover {
            background: linear-gradient(45deg, #c0392b, #a93226);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(231, 76, 60, 0.4);
        }
        
        .btn-finalizar.confirmar {
            background: linear-gradient(45deg, #dc3545, #b02a37);
            animation: pulse 1.5s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .btn-cancelar {
            background: linear-gradient(45deg, #6c757d, #545b62);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            width: 100%;
            margin-top: 8px;
            transition: all 0.3s ease;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        
        .btn-cancelar:hover {
            background: linear-gradient(45deg, #545b62, #495057);
            transform: translateY(-1px);
        }
        
        .widget-hidden {
            display: none !important;
        }
        
        .widget-minimized {
            min-width: 80px;
            padding: 10px;
            border-radius: 30px;
            cursor: move;
        }
        
        .widget-minimized .widget-info,
        .widget-minimized .btn-finalizar {
            display: none;
        }
        
        .widget-minimized .tiempo-digital {
            font-size: 16px;
            margin: 5px 0;
            padding: 8px;
        }
        
        .widget-minimized .tiempo-principal {
            font-size: 16px;
        }
        
        .widget-minimized .tiempo-info {
            font-size: 8px;
        }
        
        .widget-fuera-horario {
            opacity: 0.8;
            background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
        }
        
        .widget-fuera-horario .tiempo-digital {
            background: rgba(255, 255, 255, 0.1);
        }
        
        /* Posición guardada */
        .widget-position-indicator {
            position: absolute;
            top: -5px;
            left: -5px;
            width: 10px;
            height: 10px;
            background: rgba(255, 255, 255, 0.5);
            border-radius: 50%;
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .widget-tiempo-ausencia:hover .widget-position-indicator {
            opacity: 1;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .widget-tiempo-ausencia {
                position: fixed;
                bottom: 10px;
                left: 10px;
                right: 10px;
                min-width: auto;
                width: calc(100% - 20px);
                max-width: none;
            }
            
            .widget-minimized {
                width: 100px;
                left: auto;
                right: 10px;
                bottom: 10px;
            }
        }
        
        /* Asegurar que no interfiera con otros elementos */
        .container {
            margin-bottom: 120px;
        }
        
        @media (max-width: 768px) {
            .container {
                margin-bottom: 140px;
            }
        }

        /* Remover estilos del modal */
        .modal-overlay {
            display: none !important;
        }
    </style>
    <script>
        // Función para confirmar la eliminación de un usuario
        function confirmarEliminacion(nombreUsuario) {
            const confirmacion = confirm(`¿Está seguro de que desea eliminar al usuario "${nombreUsuario}"?`);
            return confirmacion; // Si el usuario selecciona "Sí", se procede; si selecciona "No", se cancela.
        }

        let permisoActivo = null;
        let tiempoInicial = null;
        let intervalTimer = null;
        let widgetMinimizado = false;
        let isDragging = false;
        let dragOffset = { x: 0, y: 0 };
        let modoConfirmacion = false;
        
        document.addEventListener('DOMContentLoaded', function() {
            verificarPermisoActivo();
            setInterval(verificarPermisoActivo, 2 * 60 * 1000);
            inicializarDraggable();
            cargarPosicionGuardada();
        });
        
        function inicializarDraggable() {
            const widget = document.getElementById('widgetTiempoAusencia');
            const header = widget.querySelector('.widget-header');
            
            // Eventos de mouse
            header.addEventListener('mousedown', iniciarDrag);
            document.addEventListener('mousemove', duranteDrag);
            document.addEventListener('mouseup', finalizarDrag);
            
            // Eventos táctiles para móvil
            header.addEventListener('touchstart', iniciarDragTouch, { passive: false });
            document.addEventListener('touchmove', duranteDragTouch, { passive: false });
            document.addEventListener('touchend', finalizarDrag);
        }
        
        function iniciarDrag(e) {
            const widget = document.getElementById('widgetTiempoAusencia');
            isDragging = true;
            widget.classList.add('dragging');
            
            const rect = widget.getBoundingClientRect();
            dragOffset.x = e.clientX - rect.left;
            dragOffset.y = e.clientY - rect.top;
            
            e.preventDefault();
        }
        
        function iniciarDragTouch(e) {
            const touch = e.touches[0];
            const widget = document.getElementById('widgetTiempoAusencia');
            isDragging = true;
            widget.classList.add('dragging');
            
            const rect = widget.getBoundingClientRect();
            dragOffset.x = touch.clientX - rect.left;
            dragOffset.y = touch.clientY - rect.top;
            
            e.preventDefault();
        }
        
        function duranteDrag(e) {
            if (!isDragging) return;
            
            const widget = document.getElementById('widgetTiempoAusencia');
            const x = e.clientX - dragOffset.x;
            const y = e.clientY - dragOffset.y;
            
            posicionarWidget(x, y);
            e.preventDefault();
        }
        
        function duranteDragTouch(e) {
            if (!isDragging) return;
            
            const touch = e.touches[0];
            const widget = document.getElementById('widgetTiempoAusencia');
            const x = touch.clientX - dragOffset.x;
            const y = touch.clientY - dragOffset.y;
            
            posicionarWidget(x, y);
            e.preventDefault();
        }
        
        function posicionarWidget(x, y) {
            const widget = document.getElementById('widgetTiempoAusencia');
            const rect = widget.getBoundingClientRect();
            const windowWidth = window.innerWidth;
            const windowHeight = window.innerHeight;
            
            // Limitar a los bordes de la ventana
            const maxX = windowWidth - rect.width;
            const maxY = windowHeight - rect.height;
            
            x = Math.max(0, Math.min(x, maxX));
            y = Math.max(0, Math.min(y, maxY));
            
            widget.style.left = x + 'px';
            widget.style.top = y + 'px';
            widget.style.right = 'auto';
            widget.style.bottom = 'auto';
        }
        
        function finalizarDrag() {
            if (!isDragging) return;
            
            const widget = document.getElementById('widgetTiempoAusencia');
            isDragging = false;
            widget.classList.remove('dragging');
            
            // Guardar posición
            guardarPosicion();
        }
        
        function guardarPosicion() {
            const widget = document.getElementById('widgetTiempoAusencia');
            const rect = widget.getBoundingClientRect();
            
            const posicion = {
                x: rect.left,
                y: rect.top,
                minimizado: widgetMinimizado
            };
            
            localStorage.setItem('widgetPosition', JSON.stringify(posicion));
        }
        
        function cargarPosicionGuardada() {
            const posicionGuardada = localStorage.getItem('widgetPosition');
            if (posicionGuardada) {
                try {
                    const posicion = JSON.parse(posicionGuardada);
                    const widget = document.getElementById('widgetTiempoAusencia');
                    
                    widget.style.left = posicion.x + 'px';
                    widget.style.top = posicion.y + 'px';
                    widget.style.right = 'auto';
                    widget.style.bottom = 'auto';
                    
                    if (posicion.minimizado) {
                        toggleMinimizar();
                    }
                } catch (e) {
                    console.warn('Error al cargar posición guardada:', e);
                }
            }
        }
        
        function resetPosition() {
            const widget = document.getElementById('widgetTiempoAusencia');
            
            // Resetear a posición original
            widget.style.left = 'auto';
            widget.style.top = 'auto';
            widget.style.right = '20px';
            widget.style.bottom = '20px';
            
            // Limpiar posición guardada
            localStorage.removeItem('widgetPosition');
            
            // Animación de confirmación
            widget.style.animation = 'bounce 0.5s ease';
            setTimeout(() => {
                widget.style.animation = '';
            }, 500);
        }
        
        function verificarPermisoActivo() {
            fetch('widget_tiempo_ausencia.php', {
                method: 'GET',
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                console.log('🔍 Verificando permiso activo:', data);
                if (data.success && data.permiso_activo) {
                    mostrarWidgetTiempo(data);
                } else {
                    ocultarWidgetTiempo();
                }
            })
            .catch(error => {
                console.error('❌ Error verificando permiso activo:', error);
                ocultarWidgetTiempo();
            });
        }
        
        function mostrarWidgetTiempo(data) {
            permisoActivo = data;
            tiempoInicial = data.minutos_transcurridos;
            
            const widget = document.getElementById('widgetTiempoAusencia');
            const tipoPermiso = document.getElementById('tipoPermiso');
            const infoPermiso = document.getElementById('infoPermiso');
            const titulo = document.getElementById('widgetTitulo');
            
            tipoPermiso.textContent = data.tipo_permiso;
            infoPermiso.innerHTML = `Desde: ${formatearFechaHora(data.fecha_salida, data.hora_salida)}<br>Hasta: ${formatearFechaHora(data.fecha_regreso_aprox, data.hora_regreso_aprox)}`;
            
            if (!data.en_horario_laboral) {
                widget.classList.add('widget-fuera-horario');
                titulo.textContent = '⏸️ Fuera de Horario Laboral';
                document.getElementById('btnFinalizar').textContent = '⏸️ PAUSADO';
            } else {
                widget.classList.remove('widget-fuera-horario');
                titulo.textContent = '⏰ En Ausencia Laboral';
                document.getElementById('btnFinalizar').textContent = '🏁 FINALIZAR PERMISO';
            }
            
            widget.classList.remove('widget-hidden');
            iniciarContadorTiempo();
        }
        
        function ocultarWidgetTiempo() {
            const widget = document.getElementById('widgetTiempoAusencia');
            widget.classList.add('widget-hidden');
            
            if (intervalTimer) {
                clearInterval(intervalTimer);
                intervalTimer = null;
            }
            
            permisoActivo = null;
        }
        
        function iniciarContadorTiempo() {
            if (intervalTimer) {
                clearInterval(intervalTimer);
            }
            
            intervalTimer = setInterval(() => {
                if (!permisoActivo) return;
                
                const ahora = new Date();
                const inicioAusencia = new Date(permisoActivo.inicio_ausencia);
                const minutosTranscurridosActual = calcularMinutosLaboralesTranscurridos(inicioAusencia, ahora);
                actualizarDisplay(minutosTranscurridosActual);
            }, 1000);
        }
        
        function calcularMinutosLaboralesTranscurridos(inicio, fin) {
            const horariosLaborales = {
                1: [['07:30', '12:00'], ['14:00', '17:30']],
                2: [['07:30', '12:00'], ['14:00', '17:30']],
                3: [['07:30', '12:00'], ['14:00', '17:30']],
                4: [['07:30', '12:00'], ['14:00', '17:30']],
                5: [['07:30', '12:00'], ['14:00', '17:00']],
                6: [['08:00', '12:30']]
            };
            
            let totalMinutos = 0;
            const fechaActual = new Date(inicio);
            
            while (fechaActual < fin) {
                const diaSemana = fechaActual.getDay() === 0 ? 7 : fechaActual.getDay();
                const fechaStr = fechaActual.toISOString().split('T')[0];
                
                if (diaSemana <= 6 && horariosLaborales[diaSemana]) {
                    for (const rango of horariosLaborales[diaSemana]) {
                        const inicioRango = new Date(`${fechaStr}T${rango[0]}`);
                        const finRango = new Date(`${fechaStr}T${rango[1]}`);
                        
                        const inicioEfectivo = new Date(Math.max(inicio.getTime(), inicioRango.getTime()));
                        const finEfectivo = new Date(Math.min(fin.getTime(), finRango.getTime()));
                        
                        if (inicioEfectivo < finEfectivo) {
                            totalMinutos += (finEfectivo - inicioEfectivo) / (1000 * 60);
                        }
                    }
                }
                
                fechaActual.setDate(fechaActual.getDate() + 1);
                fechaActual.setHours(0, 0, 0, 0);
            }
            
            return Math.floor(totalMinutos);
        }
        
        function actualizarDisplay(minutosTotal) {
            const horas = Math.floor(minutosTotal / 60);
            const minutos = minutosTotal % 60;
            const segundos = Math.floor((Date.now() / 1000) % 60);
            
            const tiempoFormateado = `${String(horas).padStart(2, '0')}:${String(minutos).padStart(2, '0')}:${String(segundos).padStart(2, '0')}`;
            
            // Obtener hora actual en formato AM/PM
            const ahora = new Date();
            const horaActual = formatearHoraAMPM(ahora);
            
            // Calcular tiempo de inicio
            const inicioAusencia = new Date(permisoActivo.inicio_ausencia);
            const horaInicio = formatearHoraAMPM(inicioAusencia);
            
            document.getElementById('tiempoPrincipal').textContent = tiempoFormateado;
            document.getElementById('tiempoInfo').textContent = `${horaActual} - Desde ${horaInicio}`;
        }
        
        function formatearHoraAMPM(fecha) {
            return fecha.toLocaleString('es-ES', {
                hour: '2-digit',
                minute: '2-digit',
                hour12: true
            });
        }
        
        function formatearFechaHora(fecha, hora) {
            const fechaObj = new Date(fecha + 'T' + hora);
            return fechaObj.toLocaleString('es-ES', {
                day: '2-digit',
                month: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                hour12: true
            });
        }
        
        function toggleMinimizar() {
            const widget = document.getElementById('widgetTiempoAusencia');
            const btnMinimizar = document.getElementById('btnMinimizar');
            
            widgetMinimizado = !widgetMinimizado;
            
            if (widgetMinimizado) {
                widget.classList.add('widget-minimized');
                btnMinimizar.textContent = '+';
                btnMinimizar.title = 'Maximizar';
            } else {
                widget.classList.remove('widget-minimized');
                btnMinimizar.textContent = '−';
                btnMinimizar.title = 'Minimizar';
            }
            
            // Guardar estado
            guardarPosicion();
        }
        
        function mostrarConfirmacionFinalizar(idPermiso) {
            if (modoConfirmacion) {
                // Ya está en modo confirmación, finalizar directamente
                finalizarPermiso(idPermiso);
            } else {
                // Entrar en modo confirmación
                modoConfirmacion = true;
                const btnFinalizar = document.getElementById('btnFinalizar');
                
                btnFinalizar.textContent = '✅ CONFIRMAR FINALIZACIÓN';
                btnFinalizar.classList.add('confirmar');
                
                // Crear y agregar botón cancelar
                if (!document.querySelector('.btn-cancelar')) {
                    const btnCancelar = document.createElement('button');
                    btnCancelar.className = 'btn-cancelar';
                    btnCancelar.textContent = '❌ CANCELAR';
                    btnCancelar.onclick = function() {
                        cancelarFinalizacion();
                    };
                    
                    // Insertar después del botón finalizar
                    btnFinalizar.parentNode.insertBefore(btnCancelar, btnFinalizar.nextSibling);
                }
                
                // Auto cancelar después de 10 segundos
                setTimeout(() => {
                    if (modoConfirmacion) {
                        cancelarFinalizacion();
                    }
                }, 10000);
            }
        }
        
        function cancelarFinalizacion() {
            modoConfirmacion = false;
            const btnFinalizar = document.getElementById('btnFinalizar');
            const btnCancelar = document.querySelector('.btn-cancelar');
            
            btnFinalizar.textContent = '🏁 FINALIZAR PERMISO';
            btnFinalizar.classList.remove('confirmar');
            btnFinalizar.disabled = false;
            
            if (btnCancelar) {
                btnCancelar.remove();
            }
        }
        
        function finalizarPermiso(idPermiso) {
            if (!idPermiso) {
                console.error('ID de permiso no válido');
                cancelarFinalizacion();
                return;
            }
            
            // Mostrar estado de procesamiento
            const btnFinalizar = document.getElementById('btnFinalizar');
            const btnCancelar = document.querySelector('.btn-cancelar');
            
            btnFinalizar.textContent = '⏳ FINALIZANDO...';
            btnFinalizar.disabled = true;
            
            if (btnCancelar) {
                btnCancelar.style.display = 'none';
            }
            
            const formData = new FormData();
            formData.append('id_permiso', idPermiso);
            
            fetch('finalizar_permiso.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Error en la respuesta del servidor');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Éxito - ocultar widget
                    ocultarWidgetTiempo();
                    cancelarFinalizacion();
                    console.log('Permiso finalizado correctamente');
                } else {
                    throw new Error(data.error || 'Error desconocido al finalizar el permiso');
                }
            })
            .catch(error => {
                console.error('Error al finalizar permiso:', error);
                // Resetear botones en caso de error
                btnFinalizar.textContent = '🏁 FINALIZAR PERMISO';
                btnFinalizar.disabled = false;
                
                if (btnCancelar) {
                    btnCancelar.style.display = 'block';
                }
                
                cancelarFinalizacion();
            });
        }
        
        // Cerrar modal al hacer clic fuera de él
        document.addEventListener('click', function(event) {
            const modal = document.getElementById('modalConfirmacion');
            if (event.target === modal) {
                ocultarModalConfirmacion();
            }
        });
        
        // Agregar estilo de animación para el bounce
        const style = document.createElement('style');
        style.textContent = `
            @keyframes bounce {
                0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
                40% { transform: translateY(-10px); }
                60% { transform: translateY(-5px); }
            }
        `;
        document.head.appendChild(style);
    </script>
</head>
<body>
    <div class="container">
        <h1>Bienvenido al Panel de Administrador</h1>

        <!-- Mostrar mensaje de éxito o error -->
        <?php if (isset($_GET['mensaje'])): ?>
            <div class="mensaje">
                <?= htmlspecialchars($_GET['mensaje']) ?>
            </div>
        <?php endif; ?>

        <div class="menu">
            <a href="ver_permisos.php" class="btn-ver">Ver Mis Permisos</a>
            <a href="solicitar_permiso.php" class="btn-solicitar">Solicitar Permiso</a>
            <?php if (!in_array(strtolower(trim($_SESSION['cargo'] ?? '')), ['gerente','gerencia'])): ?>
                <a href="recuperar_tiempo.php" class="btn-solicitar">Recuperar Tiempo</a>
            <?php endif; ?>
            <a href="logout.php" class="btn-cerrar">Cerrar Sesión</a>
        </div>

        <!-- Filtro por cargo -->
        <div class="filtro">
            <form method="GET" action="admin_inicio.php">
                <label for="filtro_cargo">Filtrar por cargo:</label>
                <select name="filtro_cargo" id="filtro_cargo">
                    <option value="">Todos</option>
                    <?php foreach ($cargos as $cargo): ?>
                        <option value="<?= htmlspecialchars($cargo) ?>" <?= $filtro_cargo === $cargo ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cargo) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit">Filtrar</button>
            </form>
        </div>

        <h2>Usuarios Registrados</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Usuario</th>
                    <th>Correo</th>
                    <th>Cargo</th>
                    <th>Área</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($usuario = $stmtUsuarios->fetch(PDO::FETCH_ASSOC)): ?>
                    <tr>
                        <td><?= htmlspecialchars($usuario['id_usuario']) ?></td>
                        <td><?= htmlspecialchars($usuario['usuario_nombre']) ?></td>
                        <td><?= htmlspecialchars($usuario['usuario']) ?></td>
                        <td><?= htmlspecialchars($usuario['correo']) ?></td>
                        <td><?= htmlspecialchars($usuario['cargo']) ?></td>
                        <td><?= htmlspecialchars($usuario['area'] ?? 'N/A') ?></td>
                        <td>
                            <?php if ($usuario['cargo'] === 'Administrador'): ?>
                                <button class="btn btn-disabled" disabled>No permitido</button>
                            <?php else: ?>
                                <a href="eliminar_usuario.php?id=<?= $usuario['id_usuario'] ?>" 
                                   class="btn" 
                                   onclick="return confirmarEliminacion('<?= htmlspecialchars($usuario['usuario_nombre']) ?>')">Eliminar</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Widget de tiempo en ausencia movible -->
    <div id="widgetTiempoAusencia" class="widget-tiempo-ausencia widget-hidden">
        <div class="widget-position-indicator"></div>
        <div class="widget-header">
            <div class="widget-controls">
                <button class="widget-btn" onclick="toggleMinimizar()" title="Minimizar/Maximizar" id="btnMinimizar">−</button>
                <button class="widget-btn" onclick="resetPosition()" title="Resetear posición" id="btnReset">⌂</button>
            </div>
            <h4 id="widgetTitulo">⏰ En Ausencia Laboral</h4>
            <div style="width: 54px;"></div>
        </div>
        <div class="widget-info" id="widgetInfo">
            <strong id="tipoPermiso">-</strong><br>
            <span id="infoPermiso">-</span>
        </div>
        <div class="tiempo-digital" id="tiempoDigital">
            <div class="tiempo-principal" id="tiempoPrincipal">00:00:00</div>
            <div class="tiempo-info" id="tiempoInfo">00:00 AM - Iniciado</div>
        </div>
        <button class="btn-finalizar" onclick="mostrarConfirmacionFinalizar(permisoActivo ? permisoActivo.id_permiso : null)" id="btnFinalizar">
            🏁 FINALIZAR PERMISO
        </button>
    </div>
</body>
</html>