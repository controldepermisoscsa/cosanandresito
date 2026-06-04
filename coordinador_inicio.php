<?php
session_start();
require 'conexion.php';

// Verificar si el usuario ha iniciado sesión y tiene el cargo de Coordinador
if (!isset($_SESSION['usuario_id']) || strtolower($_SESSION['cargo']) !== 'coordinador') {
    header('Location: login.php');
    exit();
}

// Obtener el ID del Coordinador
$id_coordinador = $_SESSION['usuario_id'];

// Determinar qué vista mostrar
$vista = $_GET['vista'] ?? 'permisos_auxiliares';

// Consultar MIS permisos (creados por mí como coordinador)
if ($vista === 'mis_permisos') {
    $stmt = $pdo->prepare("
        SELECT p.*, u.nombre AS solicitante, c.nombre_cargo
        FROM permisos p
        INNER JOIN usuarios u ON p.id_usuario = u.id_usuario
        INNER JOIN cargo c ON u.id_cargo = c.id_cargo
        WHERE p.id_usuario = :id_coordinador
        ORDER BY p.fecha_salida DESC
    ");
    $stmt->execute(['id_coordinador' => $id_coordinador]);
    $permisos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
// Consultar permisos de auxiliares ASIGNADOS A MÍ - CORREGIDO
} elseif ($vista === 'permisos_auxiliares') {
    $stmt = $pdo->prepare("
        SELECT p.*, u.nombre AS solicitante, c.nombre_cargo,
               p.asignado_a, p.id_asignado
        FROM permisos p
        INNER JOIN usuarios u ON p.id_usuario = u.id_usuario
        INNER JOIN cargo c ON u.id_cargo = c.id_cargo
        WHERE (p.id_asignado = :id_coordinador OR p.id_asignado IS NULL)
        AND p.asignado_a = 'coordinador'
        AND LOWER(c.nombre_cargo) = 'auxiliar'
        ORDER BY p.fecha_salida DESC
    ");
    $stmt->execute(['id_coordinador' => $id_coordinador]);
    $permisos = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Coordinador</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            height: 100vh;
        }
        .sidebar {
            width: 250px;
            background-color: #2c2f33;
            color: #fff;
            display: flex;
            flex-direction: column;
            padding: 20px;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.2);
        }
        .sidebar h2 {
            margin: 0 0 20px;
            font-size: 20px;
            text-align: left;
        }
        .sidebar a {
            color: #fff;
            text-decoration: none;
            padding: 10px 15px;
            margin-bottom: 10px;
            border-radius: 5px;
            transition: background-color 0.3s;
            display: block;
        }
        .sidebar a:hover {
            background-color: #444;
        }
        .sidebar a.activo {
            background-color: #f39c12;
        }
        .content {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            background-color: #f4f4f9;
        }
        h1 {
            color: #333;
        }
        .card {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .card h1 {
            font-size: 18px;
            margin-bottom: 10px;
        }
        .card p {
            margin: 5px 0;
            font-size: 14px;
        }
        .actions {
            margin-top: 15px;
        }
        .btn {
            padding: 10px 15px;
            color: #fff;
            text-decoration: none;
            border-radius: 4px;
            margin-right: 5px;
            display: inline-block;
        }
        .btn-view {
            background-color: #f39c12;
        }
        .btn-view:hover {
            background-color: #e67e22;
        }
        
        /* Widget movible para coordinador con colores específicos */
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

        .tiempo-principal { font-size: 26px; line-height: 1; }
        .tiempo-info { font-size: 11px; opacity: 0.8; font-family: Arial, sans-serif; letter-spacing: 0px; }

        .widget-info { font-size: 12px; opacity: 0.95; margin-bottom: 15px; text-align: center; line-height: 1.4; color: #fff; text-shadow: 1px 1px 2px rgba(0,0,0,0.3); }

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

        .btn-finalizar:hover { background: linear-gradient(45deg, #c0392b, #a93226); transform: translateY(-2px); box-shadow: 0 6px 20px rgba(231, 76, 60, 0.4); }

        .btn-finalizar.confirmar { background: linear-gradient(45deg, #dc3545, #b02a37); animation: pulse 1.5s infinite; }

        .btn-cancelar { background: linear-gradient(45deg, #6c757d, #545b62); color: white; border: none; padding: 8px 15px; border-radius: 8px; cursor: pointer; font-weight: 600; width: 100%; margin-top: 8px; transition: all 0.3s ease; font-size: 11px; text-transform: uppercase; letter-spacing: 0.3px; }

        .btn-cancelar:hover { background: linear-gradient(45deg, #545b62, #495057); transform: translateY(-1px); }

        .widget-hidden { display: none !important; }
        .widget-minimized { min-width: 80px; padding: 10px; border-radius: 30px; cursor: move; }
        .widget-minimized .widget-info, .widget-minimized .btn-finalizar { display: none; }
        .widget-minimized .tiempo-digital { font-size: 16px; margin: 5px 0; padding: 8px; }
        .widget-minimized .tiempo-principal { font-size: 16px; }
        .widget-minimized .tiempo-info { font-size: 8px; }
        .widget-fuera-horario { opacity: 0.8; background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%); }
        .widget-fuera-horario .tiempo-digital { background: rgba(255, 255, 255, 0.1); color: #bdc3c7; }

        @keyframes pulse { 0% { transform: scale(1); } 50% { transform: scale(1.05); } 100% { transform: scale(1); } }

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
        }
        
    </style>
</head>
<body>
    <!-- Panel lateral -->
    <div class="sidebar">
        <h2>Panel de Coordinador</h2>
        <a href="ver_permisos.php">Ver mis permisos</a>
        <a href="coordinador_inicio.php?vista=permisos_auxiliares" class="<?= $vista === 'permisos_auxiliares' ? 'activo' : '' ?>">Permisos de Auxiliares</a>
        <a href="solicitar_permiso.php">Solicitar Permiso</a>
        <?php if (!in_array(strtolower(trim($_SESSION['cargo'] ?? '')), ['gerente','gerencia'])): ?>
            <a href="recuperar_tiempo.php">Recuperar Tiempo</a>
        <?php endif; ?>
        <a href="logout.php">Cerrar Sesión</a>
    </div>

    <!-- Contenido principal -->
    <div class="content">
        <h1>Bienvenido al Panel de Coordinador</h1>

        <!-- Mostrar mensajes -->
        <?php if (isset($_SESSION['mensaje'])): ?>
            <div style="background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin-bottom: 20px;">
                <?= $_SESSION['mensaje']; unset($_SESSION['mensaje']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin-bottom: 20px;">
                <?= $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <?php if ($vista === 'mis_permisos'): ?>
            <h2>Mis Permisos Enviados</h2>
            <?php if (count($permisos) === 0): ?>
                <div class="card">
                    <p style="text-align: center; color: #666; font-style: italic;">📄 No has enviado permisos aún</p>
                    <div style="text-align: center; margin-top: 15px;">
                        <a href="solicitar_permiso.php" class="btn btn-view">+ Crear mi primera solicitud</a>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($permisos as $permiso): ?>
                    <div class="card">
                        <h1>ID Permiso: <?= htmlspecialchars($permiso['id_permiso']) ?></h1>
                        <p><strong>Tipo de Permiso:</strong> <?= htmlspecialchars($permiso['tipo_permiso']) ?></p>
                        <p><strong>Estado:</strong> <?= ucfirst($permiso['estado']) ?></p>
                        
                        <div class="actions">
                            <a href="ver_detalles.php?id=<?= $permiso['id_permiso'] ?>" class="btn btn-view">Ver Detalles</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

        <?php else: /* permisos_auxiliares */ ?>
            <h2>Permisos de Auxiliares Asignados</h2>
            <?php if (count($permisos) === 0): ?>
                <div class="card">
                    <p style="text-align: center; color: #666; font-style: italic;">📋 No hay permisos de auxiliares asignados a ti</p>
                </div>
            <?php else: ?>
                <?php foreach ($permisos as $permiso): ?>
                    <div class="card">
                        <h1>ID Permiso: <?= htmlspecialchars($permiso['id_permiso']) ?></h1>
                        <p><strong>Auxiliar:</strong> <?= htmlspecialchars($permiso['solicitante']) ?></p>
                        <p><strong>Tipo de Permiso:</strong> <?= htmlspecialchars($permiso['tipo_permiso']) ?></p>
                        <p><strong>Estado:</strong> <?= ucfirst($permiso['estado']) ?></p>
                        <p><strong>Fecha de Salida:</strong> <?= htmlspecialchars($permiso['fecha_salida']) ?></p>
                        <p><strong>Fecha de Regreso:</strong> <?= htmlspecialchars($permiso['fecha_regreso_aprox'] ?? 'No asignada') ?></p>
                        
                        <div class="actions">
                            <a href="coordinador_ver_solicitud.php?id=<?= $permiso['id_permiso'] ?>" class="btn btn-view">Ver Solicitud</a>
                            <?php if (isset($permiso['id_asignado'])): ?>
                                <small style="display: block; color: #666; font-size: 11px; margin-top: 5px;">
                                    Asignado ID: <?= htmlspecialchars($permiso['id_asignado']) ?>
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endif; ?>
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
        <!-- ONCLICK actualizado para usar el flujo de confirmación -->
        <button class="btn-finalizar" onclick="mostrarConfirmacionFinalizar(permisoActivo ? permisoActivo.id_permiso : null)" id="btnFinalizar">
            🏁 FINALIZAR PERMISO
        </button>
    </div>
    
    <script>
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

            header.addEventListener('mousedown', iniciarDrag);
            document.addEventListener('mousemove', duranteDrag);
            document.addEventListener('mouseup', finalizarDrag);

            header.addEventListener('touchstart', iniciarDragTouch, { passive: false });
            document.addEventListener('touchmove', duranteDragTouch, { passive: false });
            document.addEventListener('touchend', finalizarDrag);
        }

        function iniciarDrag(e) {
            const widget = document.getElementById('widgetTiempoAusencia');
            isDragging = true;
            widget.classList.add('dragging');

            const rect = widget.getBoundingClientRect();
            dragOffset.x = (e.clientX !== undefined ? e.clientX : e.touches[0].clientX) - rect.left;
            dragOffset.y = (e.clientY !== undefined ? e.clientY : e.touches[0].clientY) - rect.top;

            e.preventDefault();
        }

        function iniciarDragTouch(e) {
            iniciarDrag(e);
        }

        function duranteDrag(e) {
            if (!isDragging) return;
            const clientX = e.clientX !== undefined ? e.clientX : (e.touches && e.touches[0] ? e.touches[0].clientX : 0);
            const clientY = e.clientY !== undefined ? e.clientY : (e.touches && e.touches[0] ? e.touches[0].clientY : 0);
            posicionarWidget(clientX - dragOffset.x, clientY - dragOffset.y);
            e.preventDefault();
        }

        function duranteDragTouch(e) {
            duranteDrag(e);
        }

        function posicionarWidget(x, y) {
            const widget = document.getElementById('widgetTiempoAusencia');
            const rect = widget.getBoundingClientRect();
            const windowWidth = window.innerWidth;
            const windowHeight = window.innerHeight;

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

            widget.style.left = 'auto';
            widget.style.top = 'auto';
            widget.style.right = '20px';
            widget.style.bottom = '20px';

            localStorage.removeItem('widgetPosition');

            widget.style.animation = 'bounce 0.5s ease';
            setTimeout(() => { widget.style.animation = ''; }, 500);
        }

        function verificarPermisoActivo() {
            fetch('widget_tiempo_ausencia.php', { method: 'GET', credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => {
                if (data.success && data.permiso_activo) {
                    mostrarWidgetTiempo(data);
                    window.permisoActivo = data;
                } else {
                    ocultarWidgetTiempo();
                    window.permisoActivo = null;
                }
            })
            .catch(() => ocultarWidgetTiempo());
        }

        function mostrarWidgetTiempo(data) {
            permisoActivo = data;
            tiempoInicial = data.minutos_transcurridos;

            const widget = document.getElementById('widgetTiempoAusencia');
            document.getElementById('tipoPermiso').textContent = data.tipo_permiso;
            document.getElementById('infoPermiso').textContent = `Salida: ${data.fecha_salida} ${data.hora_salida}`;

            if (intervalTimer) clearInterval(intervalTimer);

            const inicio = new Date((data.inicio_ausencia || data.inicio_ausencia).replace(' ', 'T'));

            function actualizarTiempoDigital() {
                const ahora = new Date();
                let diff = Math.floor((ahora - inicio) / 1000);
                if (diff < 0) diff = 0;
                const h = Math.floor(diff / 3600);
                const m = Math.floor((diff % 3600) / 60);
                const s = diff % 60;
                document.getElementById('tiempoPrincipal').textContent = `${h.toString().padStart(2,'0')}:${m.toString().padStart(2,'0')}:${s.toString().padStart(2,'0')}`;
                const horaActual = ahora.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                document.getElementById('tiempoInfo').textContent = `${horaActual} - En curso`;
            }
            actualizarTiempoDigital();
            intervalTimer = setInterval(actualizarTiempoDigital, 1000);

            const titulo = document.getElementById('widgetTitulo');
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
        }

        function ocultarWidgetTiempo() {
            document.getElementById('widgetTiempoAusencia').classList.add('widget-hidden');
            if (intervalTimer) clearInterval(intervalTimer);
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
            guardarPosicion();
        }

        function mostrarConfirmacionFinalizar(idPermiso) {
            if (modoConfirmacion) {
                finalizarPermiso(idPermiso);
            } else {
                modoConfirmacion = true;
                const btnFinalizar = document.getElementById('btnFinalizar');
                btnFinalizar.textContent = '✅ CONFIRMAR FINALIZACIÓN';
                btnFinalizar.classList.add('confirmar');

                if (!document.querySelector('.btn-cancelar')) {
                    const btnCancelar = document.createElement('button');
                    btnCancelar.className = 'btn-cancelar';
                    btnCancelar.textContent = '❌ CANCELAR';
                    btnCancelar.onclick = cancelarFinalizacion;
                    btnFinalizar.parentNode.insertBefore(btnCancelar, btnFinalizar.nextSibling);
                }

                setTimeout(() => { if (modoConfirmacion) cancelarFinalizacion(); }, 10000);
            }
        }

        function cancelarFinalizacion() {
            modoConfirmacion = false;
            const btnFinalizar = document.getElementById('btnFinalizar');
            const btnCancelar = document.querySelector('.btn-cancelar');
            btnFinalizar.textContent = '🏁 FINALIZAR PERMISO';
            btnFinalizar.classList.remove('confirmar');
            btnFinalizar.disabled = false;
            if (btnCancelar) btnCancelar.remove();
        }

        function finalizarPermiso(idPermiso) {
            if (!idPermiso) { console.error('ID de permiso no válido'); cancelarFinalizacion(); return; }

            const btnFinalizar = document.getElementById('btnFinalizar');
            const btnCancelar = document.querySelector('.btn-cancelar');

            btnFinalizar.textContent = '⏳ FINALIZANDO...';
            btnFinalizar.disabled = true;
            if (btnCancelar) btnCancelar.style.display = 'none';

            const formData = new FormData();
            formData.append('id_permiso', idPermiso);

            fetch('finalizar_permiso.php', {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            })
            .then(response => { if (!response.ok) throw new Error('Error en la respuesta del servidor'); return response.json(); })
            .then(data => {
                if (data.success) {
                    ocultarWidgetTiempo();
                    cancelarFinalizacion();
                    alert('✅ Permiso finalizado correctamente.');
                    setTimeout(() => { window.location.reload(); }, 1200);
                } else {
                    throw new Error(data.error || 'Error desconocido al finalizar el permiso');
                }
            })
            .catch(error => {
                console.error('Error al finalizar permiso:', error);
                btnFinalizar.textContent = '🏁 FINALIZAR PERMISO';
                btnFinalizar.disabled = false;
                if (btnCancelar) btnCancelar.style.display = 'block';
                cancelarFinalizacion();
                alert('❌ Error finalizando permiso: ' + (error.message || 'Desconocido'));
            });
        }

        // estilo bounce añadido dinámicamente
        const style = document.createElement('style');
        style.textContent = `@keyframes bounce {0%,20%,50%,80%,100%{transform:translateY(0);}40%{transform:translateY(-10px);}60%{transform:translateY(-5px);}}`;
        document.head.appendChild(style);

        function iniciarContadorTiempo() {
            if (intervalTimer) {
                clearInterval(intervalTimer);
            }
            
            // Usar la hora de inicio del permiso desde el backend
            const inicioAusencia = new Date(permisoActivo.inicio_ausencia);
            
            intervalTimer = setInterval(() => {
                if (!permisoActivo) return;
                
                const ahora = new Date();
                const segundosTranscurridos = calcularTiempoLaboralEnSegundos(inicioAusencia, ahora);
                actualizarDisplayConSegundos(segundosTranscurridos);
            }, 1000);
        }
        
        function calcularTiempoLaboralEnSegundos(inicio, fin) {
            // Horarios laborales por día de la semana (igual que en PHP)
            const horariosLaborales = {
                1: [['07:30', '12:00'], ['14:00', '17:30']], // Lunes
                2: [['07:30', '12:00'], ['14:00', '17:30']], // Martes
                3: [['07:30', '12:00'], ['14:00', '17:30']], // Miércoles
                4: [['07:30', '12:00'], ['14:00', '17:30']], // Jueves
                5: [['07:30', '12:00'], ['14:00', '17:00']], // Viernes
                6: [['08:00', '12:30']]                      // Sábado
            };
            
            let totalSegundos = 0;
            const fechaActual = new Date(inicio);
            const fechaFin = new Date(fin);
            
            // Iterar día por día
            while (fechaActual.toDateString() <= fechaFin.toDateString()) {
                const diaSemana = fechaActual.getDay() === 0 ? 7 : fechaActual.getDay(); // 1=lunes, 7=domingo
                
                // Solo procesar días laborales (lunes a sábado)
                if (diaSemana <= 6 && horariosLaborales[diaSemana]) {
                    const fechaStr = fechaActual.toISOString().split('T')[0];
                    
                    for (const rango of horariosLaborales[diaSemana]) {
                        const inicioRango = new Date(fechaStr + 'T' + rango[0] + ':00');
                        const finRango = new Date(fechaStr + 'T' + rango[1] + ':00');
                        
                        // Ajustar los límites según la fecha actual
                        let inicioEfectivo = inicioRango;
                        let finEfectivo = finRango;
                        
                        if (fechaActual.toDateString() === inicio.toDateString()) {
                            // Primer día: usar la hora de inicio real si es mayor
                            inicioEfectivo = inicio > inicioRango ? inicio : inicioRango;
                        }
                        
                        if (fechaActual.toDateString() === fechaFin.toDateString()) {
                            // Último día: usar la hora de fin real si es menor
                            finEfectivo = fechaFin < finRango ? fechaFin : finRango;
                        }
                        
                        // Solo contar si hay overlap válido
                        if (inicioEfectivo < finEfectivo) {
                            const segundosEnRango = Math.floor((finEfectivo - inicioEfectivo) / 1000);
                            totalSegundos += segundosEnRango;
                        }
                    }
                }
                
                // Avanzar al siguiente día
                fechaActual.setDate(fechaActual.getDate() + 1);
                fechaActual.setHours(0, 0, 0, 0);
            }
            
            return totalSegundos;
        }
        
        function actualizarDisplayConSegundos(segundosTotal) {
            const horas = Math.floor(segundosTotal / 3600);
            const minutos = Math.floor((segundosTotal % 3600) / 60);
            const segundos = segundosTotal % 60;
            
            const tiempoFormateado = `${String(horas).padStart(2, '0')}:${String(minutos).padStart(2, '0')}:${String(segundos).padStart(2, '0')}`;
            
            // Obtener hora actual
            const ahora = new Date();
            const horaActual = formatearHoraAMPM(ahora);
            
            // Obtener hora de inicio
            const inicioAusencia = new Date(permisoActivo.inicio_ausencia);
            const horaInicio = formatearHoraAMPM(inicioAusencia);
            
            document.getElementById('tiempoPrincipal').textContent = tiempoFormateado;
            document.getElementById('tiempoInfo').textContent = `${horaActual} - Desde ${horaInicio}`;
        }

        function calcularMinutosLaboralesTranscurridos(inicio, fin) {
            // Esta función ya no se usa, pero la mantenemos para compatibilidad
            return Math.floor(calcularTiempoLaboralEnSegundos(inicio, fin) / 60);
        }
        
        function actualizarDisplay(minutosTotal) {
            // Esta función ya no se usa, reemplazada por actualizarDisplayConSegundos
            const segundosTotal = minutosTotal * 60;
            actualizarDisplayConSegundos(segundosTotal);
        }
    </script>
</body>
</html>