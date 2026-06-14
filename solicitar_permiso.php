<?php
session_start();
require 'conexion.php';

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['nombre']) || !isset($_SESSION['cargo'])) {
    header('Location: login.php?mensaje=Debes iniciar sesión para acceder a esta página.');
    exit();
}

// Obtener datos del usuario logueado
$nombreUsuario = $_SESSION['nombre'];
$idUsuario = $_SESSION['usuario_id'];
$fechaActual = date('Y-m-d');
$cargo = strtolower(trim($_SESSION['cargo'] ?? ''));

// Determinar la ruta del panel según el cargo
$archivoPanel = 'login.php'; // Default fallback

if (in_array($cargo, ['administrador', 'admin'])) {
    $archivoPanel = 'admin_inicio.php';
} elseif (in_array($cargo, ['coordinador', 'coord'])) {
    $archivoPanel = 'coordinador_inicio.php';
} elseif (in_array($cargo, ['auxiliar', 'aux'])) {
    $archivoPanel = 'auxiliar_inicio.php';
} elseif (in_array($cargo, ['administrativo', 'admin_operativo'])) {
    $archivoPanel = 'administrativo_inicio.php';
} elseif (in_array($cargo, ['gerente', 'gerencia', 'ger'])) {
    $archivoPanel = 'gerente_inicio.php';
} else {
    // Si no coincide ninguno, usar el id_cargo como respaldo
    $id_cargo = $_SESSION['id_cargo'] ?? 0;
    switch ($id_cargo) {
        case 1:
            $archivoPanel = 'admin_inicio.php';
            break;
        case 2:
            $archivoPanel = 'administrativo_inicio.php';
            break;
        case 3:
            $archivoPanel = 'auxiliar_inicio.php';
            break;
        case 4:
            $archivoPanel = 'coordinador_inicio.php';
            break;
        case 5:
            $archivoPanel = 'gerente_inicio.php';
            break;
        default:
            $archivoPanel = 'login.php';
    }
}

// Determinar si mostrar campo "Persona Encargada" según el cargo
// Solo auxiliares ven este campo (como solo lectura)
$mostrarPersonaEncargada = in_array($cargo, ['auxiliar', 'aux']);

// Mostrar mensaje de éxito si existe
$mensaje = $_SESSION['mensaje'] ?? null;
if ($mensaje) {
    unset($_SESSION['mensaje']);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitar Permiso</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            display: flex;
            height: 100vh;
            background: #f0f2f5;
            overflow: hidden;
        }

        /* ── SIDEBAR ── */
        .sidebar {
            width: 240px;
            background: linear-gradient(180deg, #1a2535 0%, #2c3e50 100%);
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            box-shadow: 3px 0 15px rgba(0,0,0,0.3);
        }
        .sidebar-brand {
            padding: 24px 20px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            text-align: center;
        }
        .sidebar-brand .brand-icon {
            width: 48px; height: 48px;
            background: linear-gradient(135deg, #f39c12, #e67e22);
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px; margin: 0 auto 10px;
            box-shadow: 0 4px 12px rgba(243,156,18,0.4);
        }
        .sidebar-brand h2 { color: #fff; font-size: 14px; font-weight: 600; }
        .sidebar-user {
            padding: 16px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .sidebar-user .user-name { color: #fff; font-size: 13px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .sidebar-user .user-role { color: #f39c12; font-size: 11px; margin-top: 2px; text-transform: uppercase; letter-spacing: 0.5px; }
        .sidebar-nav { flex: 1; padding: 16px 12px; display: flex; flex-direction: column; gap: 4px; }
        .nav-item {
            display: flex; align-items: center; gap: 12px;
            color: #adb5bd; text-decoration: none;
            padding: 11px 14px; border-radius: 10px;
            font-size: 14px; font-weight: 500; transition: all 0.2s;
        }
        .nav-item:hover { background: rgba(255,255,255,0.08); color: #fff; }
        .nav-item.activo {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: #fff; box-shadow: 0 4px 12px rgba(243,156,18,0.35);
        }
        .nav-icon { font-size: 18px; width: 22px; text-align: center; }
        .sidebar-logout { padding: 12px; border-top: 1px solid rgba(255,255,255,0.08); }
        .sidebar-logout a {
            display: flex; align-items: center; gap: 10px;
            color: #e74c3c; text-decoration: none;
            padding: 10px 14px; border-radius: 10px;
            font-size: 14px; font-weight: 500; transition: background 0.2s;
        }
        .sidebar-logout a:hover { background: rgba(231,76,60,0.15); }

        /* ── MAIN ── */
        .main {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 28px 32px;
            display: flex;
            flex-direction: column;
        }

        .page-header {
            text-align: center;
            margin-bottom: 24px;
        }
        .page-header h1 { font-size: 22px; color: #1a2535; font-weight: 700; }
        .btn-back {
            display: inline-flex; align-items: center; gap: 6px;
            background: linear-gradient(135deg, #2c3e50, #1a2535);
            color: #fff;
            text-decoration: none; padding: 10px 20px;
            border-radius: 9px; font-size: 13px; font-weight: 600;
            box-shadow: 0 3px 10px rgba(0,0,0,0.2);
            transition: all 0.2s;
        }
        .btn-back:hover { transform: translateY(-1px); box-shadow: 0 5px 14px rgba(0,0,0,0.25); }

        /* ── FORM CARD ── */
        .form-card {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            padding: 28px 32px;
        }

        /* Mensajes */
        .msg-info    { background:#eef2ff; color:#0b5ed7; padding:11px 14px; border-radius:8px; margin-bottom:16px; font-size:14px; }
        .msg-success { background:#e6ffed; color:#0f5132; padding:11px 14px; border-radius:8px; margin-bottom:16px; font-size:14px; }
        .msg-error   { background:#fff3f2; color:#842029; padding:11px 14px; border-radius:8px; margin-bottom:16px; font-size:14px; }

        /* Grid helpers */
        .row { display: grid; gap: 16px; margin-bottom: 16px; }
        .row.cols-2 { grid-template-columns: 1fr 1fr; }
        .row.cols-4 { grid-template-columns: 1fr 1fr 1fr 1fr; }
        .row.cols-1 { grid-template-columns: 1fr; }
        .col-full   { grid-column: 1 / -1; }

        /* Fields */
        .field { display: flex; flex-direction: column; }
        .field label {
            font-size: 11px; font-weight: 700; color: #6c757d;
            text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;
        }
        .field input, .field select, .field textarea {
            padding: 10px 13px;
            border: 1.5px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            color: #2c3e50;
            background: #fff;
            transition: border-color 0.2s, box-shadow 0.2s;
            width: 100%;
            font-family: inherit;
        }
        .field input:focus, .field select:focus, .field textarea:focus {
            outline: none;
            border-color: #f39c12;
            box-shadow: 0 0 0 3px rgba(243,156,18,0.12);
        }
        .field input[readonly] { background: #f8f9fa; color: #868e96; cursor: default; }
        .field textarea { min-height: 80px; resize: vertical; }
        .field .hint { font-size: 11px; color: #adb5bd; margin-top: 5px; font-style: italic; }
        .field-error { color: #dc3545; font-size: 11px; margin-top: 4px; font-weight: 600; display: none; }
        .error-field { border-color: #dc3545 !important; background: #fff5f5 !important; }

        /* Section divider */
        .section-title {
            font-size: 11px; font-weight: 700; color: #adb5bd;
            text-transform: uppercase; letter-spacing: 1px;
            margin-bottom: 14px; margin-top: 6px;
            padding-bottom: 8px; border-bottom: 1px solid #f0f2f5;
        }

        /* Actions */
        .form-actions {
            display: flex; justify-content: center; align-items: center;
            gap: 12px; margin-top: 24px; padding-top: 20px;
            border-top: 1px solid #f0f2f5;
        }
        .btn-submit {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: #fff; border: none;
            padding: 12px 28px; border-radius: 10px;
            font-size: 15px; font-weight: 700; cursor: pointer;
            box-shadow: 0 4px 12px rgba(243,156,18,0.35);
            transition: all 0.2s; font-family: inherit;
        }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(243,156,18,0.45); }

        /* Loading */
        .loading { opacity: 0.6; pointer-events: none; }
        .hidden  { display: none !important; }

        /* ── MODALS ── */
        .modal-overlay {
            position: fixed; inset: 0;
            background: rgba(0,0,0,0.55);
            display: flex; align-items: center; justify-content: center;
            z-index: 1000;
        }
        .modal-box {
            background: #fff; border-radius: 16px;
            padding: 32px; text-align: center;
            box-shadow: 0 12px 40px rgba(0,0,0,0.2);
            max-width: 460px; width: 90%;
        }
        .modal-box .modal-icon { font-size: 40px; margin-bottom: 12px; }
        .modal-box h3 { font-size: 18px; color: #1a2535; margin-bottom: 10px; }
        .modal-box p  { font-size: 14px; color: #6c757d; line-height: 1.6; margin-bottom: 22px; }
        .modal-btns   { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
        .modal-btn {
            padding: 11px 24px; border: none; border-radius: 9px;
            font-size: 14px; font-weight: 600; cursor: pointer;
            transition: all 0.2s; font-family: inherit;
        }
        .modal-btn-primary { background: linear-gradient(135deg,#f39c12,#e67e22); color:#fff; box-shadow:0 4px 12px rgba(243,156,18,0.3); }
        .modal-btn-primary:hover { transform:translateY(-1px); }
        .modal-btn-secondary { background: #f0f2f5; color: #495057; }
        .modal-btn-secondary:hover { background: #e9ecef; }
        .modal-btn-success { background: linear-gradient(135deg,#2ecc71,#27ae60); color:#fff; }
        .modal-btn-danger  { background: linear-gradient(135deg,#e74c3c,#c0392b); color:#fff; }

        @media (max-width: 768px) {
            body { flex-direction: column; height: auto; overflow: auto; }
            .sidebar { width: 100%; height: auto; }
            .row.cols-4 { grid-template-columns: 1fr 1fr; }
            .main { padding: 16px; }
        }
    </style>
</head>
<body>

    <!-- MODAL ADVERTENCIA INICIAL -->
    <div id="modalWarning" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-icon">⚠️</div>
            <h3>Antes de continuar</h3>
            <p>Tenga en cuenta que para enviar una solicitud de permiso debe hacerlo <strong>mínimo con un día de anticipación</strong>.</p>
            <div class="modal-btns">
                <button id="btnWarningAccept" class="modal-btn modal-btn-primary">Entendido, continuar</button>
                <button id="btnWarningCancel" class="modal-btn modal-btn-secondary">Cancelar</button>
            </div>
        </div>
    </div>

    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="sidebar-brand">
            <div class="brand-icon">📋</div>
            <h2>Coosanandresito</h2>
        </div>
        <div class="sidebar-user">
            <div class="user-name"><?= htmlspecialchars($nombreUsuario) ?></div>
            <div class="user-role"><?= htmlspecialchars(ucfirst($cargo)) ?></div>
        </div>
        <nav class="sidebar-nav">
            <a href="<?= htmlspecialchars($archivoPanel) ?>" class="nav-item">
                <span class="nav-icon">🏠</span> Inicio
            </a>
            <a href="solicitar_permiso.php?nuevo=1" class="nav-item activo">
                <span class="nav-icon">📝</span> Solicitar Permiso
            </a>
            <a href="ver_permisos.php" class="nav-item">
                <span class="nav-icon">📂</span> Mis Permisos
            </a>
            <?php if ($cargo === 'auxiliar'): ?>
            <a href="recuperar_tiempo.php" class="nav-item">
                <span class="nav-icon">⏱️</span> Recuperar Tiempo
            </a>
            <?php endif; ?>
        </nav>
        <div class="sidebar-logout">
            <a href="logout.php"><span style="font-size:16px;">🚪</span> Cerrar Sesión</a>
        </div>
    </div>

    <!-- CONTENIDO PRINCIPAL -->
    <div class="main" id="mainContainer" style="display:none;">

        <div class="page-header">
            <h1>📝 Solicitar Permiso</h1>
        </div>

        <div class="form-card container">

            <div id="mensaje-container"></div>
            <?php if (!empty($mensaje)): ?>
                <div class="msg-info"><?= htmlspecialchars($mensaje) ?></div>
            <?php endif; ?>

            <form id="formPermiso" enctype="multipart/form-data">
                <input type="hidden" name="accion" value="crear">

                <!-- Fila 1: Datos del solicitante -->
                <div class="section-title">Datos del Solicitante</div>
                <div class="row cols-2">
                    <div class="field">
                        <label>Nombre y Apellido</label>
                        <input type="text" name="nombre_usuario" value="<?= htmlspecialchars($nombreUsuario) ?>" readonly>
                    </div>
                    <div class="field">
                        <label>Fecha de Solicitud</label>
                        <input type="date" name="fecha_solicitud" value="<?= htmlspecialchars($fechaActual) ?>" readonly>
                    </div>
                </div>

                <!-- Fila 2: Tipo + Documento -->
                <div class="section-title">Detalles del Permiso</div>
                <div class="row cols-2">
                    <div class="field">
                        <label>Tipo de Permiso *</label>
                        <select id="tipo_permiso" name="tipo_permiso" required>
                            <option value="">Seleccione...</option>
                            <option value="Médicos">Médicos</option>
                            <option value="Laborales">Laborales</option>
                            <option value="Personales">Personales</option>
                        </select>
                    </div>
                    <div class="field" id="documento-container">
                        <label>Documento de Soporte (PDF)</label>
                        <input type="file" id="documento_pdf" name="documento_pdf" accept=".pdf">
                        <span class="hint">📄 Opcional · Solo PDF · Máximo 5 MB</span>
                    </div>
                </div>

                <!-- Fila 3: Motivo -->
                <div class="row cols-1">
                    <div class="field">
                        <label>Motivo *</label>
                        <textarea name="motivo" id="motivo" rows="3" required placeholder="Describa el motivo del permiso..."></textarea>
                    </div>
                </div>

                <!-- Fila 4: Fechas y horas -->
                <div class="section-title">Fechas y Horario</div>
                <div class="row cols-4">
                    <div class="field">
                        <label>Fecha de Salida *</label>
                        <input type="date" name="fecha_salida" id="fecha_salida" required>
                        <span id="error-fecha-salida" class="field-error"></span>
                    </div>
                    <div class="field">
                        <label>Hora de Salida *</label>
                        <input type="time" name="hora_salida" id="hora_salida" required>
                        <span id="error-hora-salida" class="field-error"></span>
                    </div>
                    <div class="field">
                        <label>Fecha Aprox. Regreso *</label>
                        <input type="date" name="fecha_regreso_aprox" id="fecha_regreso_aprox" required>
                        <span id="error-fecha-regreso-aprox" class="field-error"></span>
                    </div>
                    <div class="field">
                        <label>Hora Aprox. Regreso *</label>
                        <input type="time" name="hora_regreso_aprox" id="hora_regreso_aprox" required>
                        <span id="error-hora-regreso-aprox" class="field-error"></span>
                    </div>
                </div>

                <!-- Persona encargada (solo auxiliares) -->
                <?php if ($mostrarPersonaEncargada): ?>
                <div class="row cols-1">
                    <div class="field">
                        <label>Persona Encargada en su Ausencia</label>
                        <input type="text" name="encargado_ausencia" id="encargado_ausencia"
                               placeholder="El coordinador asignará la persona encargada" readonly>
                        <span class="hint">ℹ️ El coordinador asignará la persona encargada al revisar su solicitud</span>
                    </div>
                </div>
                <?php else: ?>
                <input type="hidden" name="encargado_ausencia" value="">
                <?php endif; ?>

                <div class="form-actions">
                    <a href="<?= htmlspecialchars($archivoPanel) ?>" class="btn-back">Cancelar</a>
                    <button type="button" id="btnEnviar" class="btn-submit">Enviar Solicitud →</button>
                </div>

            </form>
        </div>
    </div>

    <!-- MODAL CONFIRMACIÓN DE ENVÍO -->
    <div id="modal" class="modal-overlay" style="display:none;">
        <div class="modal-box">
            <div class="modal-icon">⏱️</div>
            <h3>Confirmar Solicitud</h3>
            <p id="modal-message"></p>
            <div class="modal-btns">
                <button id="btn-accept" class="modal-btn modal-btn-success">Sí, enviar</button>
                <button id="btn-cancel" class="modal-btn modal-btn-secondary">Revisar</button>
            </div>
        </div>
    </div>

    <script>
        // Mostrar modal solo si viene con ?nuevo=1
        document.addEventListener('DOMContentLoaded', function() {
            const modalWarning  = document.getElementById('modalWarning');
            const mainContainer = document.getElementById('mainContainer');
            const esNuevo = new URLSearchParams(window.location.search).get('nuevo') === '1';

            if (esNuevo) {
                modalWarning.style.display = 'flex';
            } else {
                modalWarning.style.display = 'none';
                mainContainer.style.display = 'flex';
            }

            document.getElementById('btnWarningAccept').addEventListener('click', function() {
                modalWarning.style.display = 'none';
                mainContainer.style.display = 'flex';
                // Limpiar ?nuevo=1 de la URL para que F5 no vuelva a mostrar el modal
                history.replaceState(null, '', 'solicitar_permiso.php');
            });

            document.getElementById('btnWarningCancel').addEventListener('click', function() {
                window.location.href = '<?= htmlspecialchars($archivoPanel) ?>';
            });
        });

        // Función para mostrar error en campo específico
        function mostrarErrorCampo(campoId, mensaje) {
            const campo = document.getElementById(campoId);
            const errorDiv = document.getElementById('error-' + campoId.replace('_', '-'));
            
            if (campo && errorDiv) {
                campo.classList.add('error-field');
                errorDiv.textContent = mensaje;
                errorDiv.style.display = 'block';
            }
        }

        // Función para limpiar error en campo específico
        function limpiarErrorCampo(campoId) {
            const campo = document.getElementById(campoId);
            const errorDiv = document.getElementById('error-' + campoId.replace('_', '-'));
            
            if (campo && errorDiv) {
                campo.classList.remove('error-field');
                errorDiv.textContent = '';
                errorDiv.style.display = 'none';
            }
        }

        // =========================================================================
        // VALIDACIONES SIMPLIFICADAS PARA TESTING
        // =========================================================================
        function validarFecha(fecha, campoId) {
            // VALIDACIÓN MÍNIMA: Solo verificar que no esté vacía
            if (!fecha) {
                limpiarErrorCampo(campoId);
                return true;
            }
            
            const fechaSeleccionada = new Date(fecha + 'T00:00:00');
            
            if (isNaN(fechaSeleccionada.getTime())) {
                mostrarErrorCampo(campoId, 'Fecha inválida');
                return false;
            }
            
            limpiarErrorCampo(campoId);
            return true;
        }
        // =========================================================================


        // Validar archivo PDF
        document.getElementById("documento_pdf").addEventListener("change", function() {
            const file = this.files[0];
            if (file) {
                // Verificar tipo de archivo
                if (file.type !== 'application/pdf') {
                    mostrarMensaje("❌ Solo se permiten archivos PDF", "error");
                    this.value = "";
                    return;
                }
                
                // Verificar tamaño (5MB máximo)
                if (file.size > 5 * 1024 * 1024) {
                    mostrarMensaje("❌ El archivo debe ser menor a 5MB", "error");
                    this.value = "";
                    return;
                }
            }
        });

        // Helper: valida que una fecha+hora sea válida
        function parseDateTime(dateStr, timeStr) {
            if (!dateStr || !timeStr) return null;
            const iso = dateStr + 'T' + timeStr;
            const d = new Date(iso);
            return isNaN(d.getTime()) ? null : d;
        }

        // Validación antes de mostrar modal - ACTUALIZADA
        document.getElementById("btnEnviar").addEventListener("click", function () {
            const fechaSalida = document.getElementById("fecha_salida").value;
            const horaSalida = document.getElementById("hora_salida").value;
            const fechaRegresoAprox = document.getElementById("fecha_regreso_aprox").value;
            const horaRegresoAprox = document.getElementById("hora_regreso_aprox").value;

            let hayErrores = false;

            // Validaciones básicas de presencia
            if (!fechaSalida || !horaSalida) {
                mostrarMensaje("Por favor ingresa la fecha y hora de salida.", "error");
                return;
            }
            if (!fechaRegresoAprox || !horaRegresoAprox) {
                mostrarMensaje("Por favor ingresa la fecha y hora aproximada de regreso.", "error");
                return;
            }

            // =========================================================================
            // VALIDACIONES COMENTADAS PARA TESTING
            // =========================================================================
            /*
            // Validar fechas antes de enviar
            if (!validarFecha(fechaSalida, 'fecha_salida')) {
                hayErrores = true;
            }
            
            if (!validarFecha(fechaRegresoAprox, 'fecha_regreso_aprox')) {
                hayErrores = true;
            }

            // Si hay errores de validación, no continuar
            if (hayErrores) {
                mostrarMensaje("Por favor corrige los errores. Recuerda que el permiso debe solicitarse con un día de anticipación.", "error");
                return;
            }
            */
            // =========================================================================

            const dtSalida = parseDateTime(fechaSalida, horaSalida);
            const dtRegreso = parseDateTime(fechaRegresoAprox, horaRegresoAprox);

            if (!dtSalida || !dtRegreso) {
                mostrarMensaje("Fechas u horas inválidas.", "error");
                return;
            }

            if (dtRegreso <= dtSalida) {
                mostrarMensaje("La fecha y hora aproximada de regreso debe ser posterior a la fecha y hora de salida.\n\nEjemplo: si sales el 13/06/2026 a las 08:00 AM, el regreso debe ser el mismo día después de las 08:00 AM o en una fecha posterior.", "error");
                return;
            }

            // Cálculo de tiempo de ausencia en horario laboral
            const horariosLaborales = {
                lunes: [["07:30", "12:00"], ["14:00", "17:30"]],
                martes: [["07:30", "12:00"], ["14:00", "17:30"]],
                miércoles: [["07:30", "12:00"], ["14:00", "17:30"]],
                jueves: [["07:30", "12:00"], ["14:00", "17:30"]],
                viernes: [["07:30", "12:00"], ["14:00", "17:00"]],
                sábado: [["08:00", "12:30"]],
            };

            const calcularMinutosLaborales = (fechaInicio, fechaFin) => {
                let minutos = 0;
                const fechaInicioTemp = new Date(fechaInicio);
                const festivos = [];

                while (fechaInicioTemp <= fechaFin) {
                    const diaSemana = fechaInicioTemp.toLocaleDateString("es-ES", { weekday: "long" }).toLowerCase();
                    const fechaActual = fechaInicioTemp.toISOString().split("T")[0];

                    if (horariosLaborales[diaSemana] && !festivos.includes(fechaActual)) {
                        horariosLaborales[diaSemana].forEach((rango) => {
                            const inicioRango = new Date(`${fechaActual}T${rango[0]}`);
                            const finRango = new Date(`${fechaActual}T${rango[1]}`);

                            if (fechaInicio > finRango || fechaFin < inicioRango) {
                                return;
                            }

                            const inicio = fechaInicio > inicioRango ? fechaInicio : inicioRango;
                            const fin = fechaFin < finRango ? fechaFin : finRango;

                            minutos += (fin - inicio) / (1000 * 60);
                        });
                    }

                    fechaInicioTemp.setDate(fechaInicioTemp.getDate() + 1);
                    fechaInicioTemp.setHours(0, 0, 0, 0);
                }

                return minutos;
            };

            const totalMinutos = calcularMinutosLaborales(new Date(dtSalida), new Date(dtRegreso));
            const horas = Math.floor(totalMinutos / 60);
            const minutos = totalMinutos % 60;

            const modal = document.getElementById("modal");
            const modalMessage = document.getElementById("modal-message");

            modalMessage.textContent = `Vas a estar en ausencia afectando el horario laboral ${horas} horas y ${minutos} minutos (aproximado). ¿Deseas enviar la solicitud?`;
            modal.style.display = "flex";

            document.getElementById("btn-accept").onclick = function () {
                modal.style.display = "none";
                enviarSolicitud();
            };

            document.getElementById("btn-cancel").onclick = function () {
                modal.style.display = "none";
            };
        });

        function enviarSolicitud() {
            const form = document.getElementById("formPermiso");
            const container = document.querySelector(".container");
            
            // Validar campos obligatorios antes de enviar
            const motivo = document.getElementById("motivo").value.trim();
            const tipoPermiso = document.getElementById("tipo_permiso").value;
            
            if (!tipoPermiso) {
                mostrarMensaje("❌ Por favor selecciona el tipo de permiso", "error");
                container.classList.remove("loading");
                return;
            }
            
            if (!motivo || motivo.length < 5) {
                mostrarMensaje("❌ El motivo debe tener al menos 5 caracteres", "error");
                container.classList.remove("loading");
                return;
            }
            
            // Mostrar estado de carga
            container.classList.add("loading");
            mostrarMensaje("📤 Enviando solicitud...", "info");

            // Preparar datos del formulario
            const formData = new FormData();
            
            formData.append('accion', 'crear');
            formData.append('tipo_permiso', document.getElementById('tipo_permiso').value);
            formData.append('motivo', motivo);
            formData.append('fecha_salida', document.getElementById('fecha_salida').value);
            formData.append('hora_salida', document.getElementById('hora_salida').value);
            formData.append('fecha_regreso_aprox', document.getElementById('fecha_regreso_aprox').value);
            formData.append('hora_regreso_aprox', document.getElementById('hora_regreso_aprox').value);
            
            // Solo agregar encargado_ausencia si existe el campo
            const encargadoField = document.getElementById('encargado_ausencia');
            if (encargadoField) {
                formData.append('encargado_ausencia', encargadoField.value);
            } else {
                formData.append('encargado_ausencia', '');
            }
            
            // Agregar documento PDF si está cargado
            const documentoPDF = document.getElementById('documento_pdf').files[0];
            if (documentoPDF) {
                formData.append('documento_pdf', documentoPDF);
            }

            // Enviar a crear_permiso_procesar.php
            fetch('crear_permiso_procesar.php', {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            })
            .then(response => {
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        throw new Error(`Respuesta no es JSON válido: ${text.substring(0, 200)}`);
                    }
                });
            })
            .then(data => {
                container.classList.remove("loading");
                
                if (data.success) {
                    form.classList.add("hidden");
                    mostrarMensaje("✅ " + data.message, "success");
                    
                } else {
                    mostrarMensaje("❌ Error: " + (data.error || 'Error desconocido'), "error");
                }
            })
            .catch(error => {
                container.classList.remove("loading");
                mostrarMensaje("🔌 Error de conexión: " + error.message, "error");
            });
        }

        function mostrarMensaje(mensaje, tipo) {
            const texto = mensaje.replace(/^[❌✅📤🔌⚠️]\s*/, '');

            if (tipo === 'info') {
                Swal.fire({
                    title: 'Enviando solicitud...',
                    text: 'Por favor espera.',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    didOpen: () => Swal.showLoading()
                });
                return;
            }

            if (tipo === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: '¡Solicitud enviada!',
                    text: texto,
                    confirmButtonColor: '#f39c12',
                    confirmButtonText: 'Ver mis permisos',
                    allowOutsideClick: false,
                    timer: 3000,
                    timerProgressBar: true
                }).then(() => {
                    window.location.href = 'ver_permisos.php?msg=' + encodeURIComponent('Solicitud creada exitosamente');
                });
                return;
            }

            Swal.fire({
                icon: 'warning',
                title: 'Atención',
                html: texto.replace(/\n\n/g, '<br><br>').replace(/\n/g, '<br>'),
                confirmButtonColor: '#f39c12',
                confirmButtonText: 'Entendido'
            });
        }
    </script>
</body>
</html>