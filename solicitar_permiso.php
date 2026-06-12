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
    <style>
        /* Estilos generales */
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(180deg,#f1f5f9 0%, #ffffff 100%);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
            box-sizing: border-box;
        }

        .container {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 6px 24px rgba(16,24,40,0.12);
            padding: 28px;
            max-width: 820px;
            width: 95%;
            margin: 20px auto;
        }

        h1 {
            font-size: 20px;
            color: #0b5ed7;
            margin-bottom: 18px;
            text-align: center;
            font-weight: 700;
        }

        .mensaje {
            background:#eef2ff;
            color:#0b5ed7;
            padding:10px;
            border-radius:6px;
        }
        .success-message {
            background:#e6ffed;
            color:#0f5132;
            padding:10px;
            border-radius:6px;
        }
        .error-message {
            background:#fff3f2;
            color:#842029;
            padding:10px;
            border-radius:6px;
        }
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }
        .hidden {
            display: none;
        }
        label {
            font-weight: 600;
            font-size: 13px;
            color:#333;
            margin-bottom:6px;
            display: block;
        }
        input, select, textarea {
            width: 100%;
            padding: 10px 12px;
            margin-bottom: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 14px;
            background: #fbfdff;
        }
        textarea {
            min-height: 90px;
            resize: vertical;
        }
        button {
            background-color: #198754;
            color: #fff;
            border: none;
            padding: 12px;
            width: 100%;
            border-radius: 6px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
        }
        button:hover {
            background-color: #218838;
        }
        .btn-panel {
            background-color: #0d6efd;
            color: #fff;
            text-align: center;
            text-decoration: none;
            padding: 10px;
            border-radius: 6px;
            display: block;
            margin-top: 10px;
            font-size: 15px;
            font-weight: 600;
        }
        .btn-panel:hover {
            background-color: #0056b3;
        }
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            display: none;
        }
        .modal-content {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }
        .modal-content button {
            margin-top: 10px;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-accept {
            background-color: #28a745;
            color: #fff;
        }
        .btn-accept:hover {
            background-color: #218838;
        }
        .btn-cancel {
            background-color: #dc3545;
            color: #fff;
        }
        .btn-cancel:hover {
            background-color: #c82333;
        }
        .error-field {
            border-color: #dc3545 !important;
            background-color: #fff5f5 !important;
        }
        
        .field-error {
            color: #dc3545;
            font-size: 12px;
            margin-top: 2px;
            margin-bottom: 8px;
            display: block;
            font-weight: 500;
        }

        @media (max-width:600px) {
            .container { padding:16px; }
            input, select, textarea { font-size:13px; }
        }
        
        .modal-warning {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1001;
        }
        
        .modal-warning-content {
            background-color: #fff;
            padding: 30px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            max-width: 500px;
            width: 90%;
        }
        
        .modal-warning h3 {
            color: #f39c12;
            font-size: 18px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .modal-warning p {
            color: #333;
            font-size: 16px;
            line-height: 1.5;
            margin-bottom: 20px;
        }
        
        .btn-warning-accept {
            background-color: #f39c12;
            color: #fff;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            margin-right: 10px;
        }
        
        .btn-warning-accept:hover {
            background-color: #e67e22;
        }
        
        .btn-warning-cancel {
            background-color: #6c757d;
            color: #fff;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
        }
        
        .btn-warning-cancel:hover {
            background-color: #5a6268;
        }
    </style>
</head>
<body>
    <!-- Modal de advertencia inicial -->
    <div id="modalWarning" class="modal-warning">
        <div class="modal-warning-content">
            <h3>⚠️ Importante</h3>
            <p>Tenga en cuenta que para enviar una solicitud debe hacerlo mínimo con un día de anticipación.</p>
            <button id="btnWarningAccept" class="btn-warning-accept">Entendido</button>
            <button id="btnWarningCancel" class="btn-warning-cancel">Cancelar</button>
        </div>
    </div>

    <div class="container" id="mainContainer" style="display: none;">
        <h1>Solicitar Permiso</h1>

        <!-- Contenedor para mensajes dinámicos -->
        <div id="mensaje-container"></div>

        <?php if (!empty($mensaje)): ?>
            <div class="mensaje"><?= htmlspecialchars($mensaje); ?></div>
        <?php endif; ?>

        <form id="formPermiso" enctype="multipart/form-data">
            <input type="hidden" name="accion" value="crear">
            <label>Nombre y Apellido:</label>
            <input type="text" name="nombre_usuario" value="<?= htmlspecialchars($nombreUsuario) ?>" readonly>

            <label>Fecha de Solicitud:</label>
            <input type="date" name="fecha_solicitud" value="<?= htmlspecialchars($fechaActual) ?>" readonly>

            <label for="tipo_permiso">Tipo de Permiso:</label>
            <select id="tipo_permiso" name="tipo_permiso" required>
                <option value="">Seleccione...</option>
                <option value="Médicos">Médicos</option>
                <option value="Laborales">Laborales</option>
                <option value="Personales">Personales</option>
            </select>

            <label for="motivo">Motivo:</label>
            <textarea name="motivo" id="motivo" rows="4" required></textarea>

            <!-- Campo para cargar documento PDF -->
            <div id="documento-container">
                <label for="documento_pdf">Documento de Soporte (PDF - Opcional):</label>
                <input type="file" id="documento_pdf" name="documento_pdf" accept=".pdf" />
                <small style="color: #6c757d; font-style: italic; display: block; margin-bottom: 12px;">
                    📄 Campo opcional. Solo se permiten archivos PDF. Máximo 5MB.
                </small>
            </div>

            <label for="fecha_salida">Fecha de Salida:</label>
            <input type="date" name="fecha_salida" id="fecha_salida" required>
            <div id="error-fecha-salida" class="field-error" style="display: none;"></div>

            <label for="hora_salida">Hora de Salida:</label>
            <input type="time" name="hora_salida" id="hora_salida" required>
            <div id="error-hora-salida" class="field-error" style="display: none;"></div>

            <label for="fecha_regreso_aprox">Fecha Aproximada de Regreso:</label>
            <input type="date" name="fecha_regreso_aprox" id="fecha_regreso_aprox" required>
            <div id="error-fecha-regreso-aprox" class="field-error" style="display: none;"></div>

            <label for="hora_regreso_aprox">Hora Aproximada de Regreso:</label>
            <input type="time" name="hora_regreso_aprox" id="hora_regreso_aprox" required>
            <div id="error-hora-regreso-aprox" class="field-error" style="display: none;"></div>

            <?php if ($mostrarPersonaEncargada): ?>
            <!-- Solo auxiliares ven este campo -->
            <label for="encargado_ausencia">Persona Encargada en su Ausencia:</label>
            <input type="text" name="encargado_ausencia" id="encargado_ausencia" 
                   placeholder="El coordinador asignará la persona encargada" readonly
                   style="background-color: #f8f9fa; color: #6c757d;">
            <small style="color: #6c757d; font-style: italic; display: block; margin-bottom: 12px;">
                ℹ️ El coordinador asignará la persona encargada cuando revise su solicitud
            </small>
            <?php else: ?>
            <!-- Para administradores, coordinadores y administrativos: campo oculto vacío -->
            <input type="hidden" name="encargado_ausencia" value="">
            <?php endif; ?>

            <button type="button" id="btnEnviar">Enviar Solicitud</button>
        </form>

        <a href="<?= htmlspecialchars($archivoPanel) ?>" class="btn-panel" id="btnVolver">Volver al Panel</a>
    </div>

    <div id="modal" class="modal">
        <div class="modal-content">
            <h3 id="modal-message"></h3>
            <button id="btn-accept" class="btn-accept">Aceptar</button>
            <button id="btn-cancel" class="btn-cancel">Cancelar</button>
        </div>
    </div>

    <script>
        // Mostrar modal de advertencia al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            const modalWarning = document.getElementById('modalWarning');
            const mainContainer = document.getElementById('mainContainer');
            const btnWarningAccept = document.getElementById('btnWarningAccept');
            const btnWarningCancel = document.getElementById('btnWarningCancel');
            
            modalWarning.style.display = 'flex';
            
            btnWarningAccept.addEventListener('click', function() {
                modalWarning.style.display = 'none';
                mainContainer.style.display = 'block';
            });
            
            btnWarningCancel.addEventListener('click', function() {
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
                mostrarMensaje("La fecha y hora aproximada de regreso debe ser posterior a la fecha y hora de salida.", "error");
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
                    mostrarMensaje("✅ " + data.message, "success");
                    
                    form.classList.add("hidden");
                    
                    const btnVolver = document.getElementById("btnVolver");
                    btnVolver.style.backgroundColor = "#28a745";
                    btnVolver.style.fontSize = "18px";
                    btnVolver.style.padding = "15px";
                    btnVolver.textContent = "✅ Solicitud Enviada - Volver al Panel";
                    
                    setTimeout(() => {
                        window.location.href = 'ver_permisos.php?msg=' + encodeURIComponent('Solicitud creada exitosamente');
                    }, 3000);
                    
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
            const container = document.getElementById("mensaje-container");
            let className = "";
            
            switch(tipo) {
                case "success":
                    className = "success-message";
                    break;
                case "error":
                    className = "error-message";
                    break;
                case "info":
                    className = "mensaje";
                    break;
                default:
                    className = "mensaje";
            }
            
            container.innerHTML = `<div class="${className}">${mensaje}</div>`;
            container.scrollIntoView({ behavior: 'smooth' });
        }
    </script>
</body>
</html>