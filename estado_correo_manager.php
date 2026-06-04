<?php
require_once __DIR__ . '/config_correo.php';
require_once __DIR__ . '/config.php';

class EstadoCorreoManager {
    
    private $pdo;
    
    public function __construct($pdo_connection) {
        $this->pdo = $pdo_connection;
        error_log("✅ EstadoCorreoManager inicializado correctamente");
    }
    
    /**
     * Crear notificación en la base de datos
     */
    private function crearNotificacion($id_usuario, $mensaje) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO notificaciones (id_usuario, mensaje, fecha_envio, leido) 
                VALUES (?, ?, NOW(), 0)
            ");
            return $stmt->execute([$id_usuario, $mensaje]);
        } catch (Exception $e) {
            error_log("Error creando notificación: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtener información completa del usuario
     */
    private function obtenerUsuario($id_usuario) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT u.*, c.nombre_cargo 
                FROM usuarios u 
                INNER JOIN cargo c ON u.id_cargo = c.id_cargo 
                WHERE u.id_usuario = ?
            ");
            $stmt->execute([$id_usuario]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo usuario: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener información del permiso
     */
    private function obtenerPermiso($id_permiso) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT p.*, u.nombre as nombre_solicitante, u.correo as correo_solicitante, c.nombre_cargo, p.documento_pdf
                FROM permisos p
                INNER JOIN usuarios u ON p.id_usuario = u.id_usuario
                INNER JOIN cargo c ON u.id_cargo = c.id_cargo
                WHERE p.id_permiso = ?
            ");
            $stmt->execute([$id_permiso]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo permiso: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 🔥 ENVIAR PDF SOLO A GERENCIA (mejor manejo de rutas y logging)
     * Envía UN correo formal a todos los integrantes de Gerencia con el PDF adjunto (solo UN correo con attachment).
     */
    public function enviarPDFSoloAGerencia($id_permiso, $rutaArchivo) {
        try {
            // Aceptar rutas tanto relativas como absolutas
            $fullPath = $rutaArchivo;
            if (!file_exists($fullPath)) {
                $alt = __DIR__ . '/' . ltrim($rutaArchivo, '/\\');
                if (file_exists($alt)) {
                    $fullPath = $alt;
                }
            }
            if (!file_exists($fullPath)) {
                throw new Exception("Archivo PDF no encontrado: {$rutaArchivo}");
            }

            $tamañoArchivo = filesize($fullPath);
            $tamañoMB = round($tamañoArchivo / 1024 / 1024, 2);

            // Obtener datos del permiso
            $permiso = $this->obtenerPermiso($id_permiso);
            if (!$permiso) {
                throw new Exception('Permiso no encontrado');
            }

            // Obtener SOLO gerentes
            $stmt = $this->pdo->prepare("
                SELECT u.correo, u.nombre, u.id_usuario
                FROM usuarios u 
                INNER JOIN cargo c ON u.id_cargo = c.id_cargo 
                WHERE LOWER(c.nombre_cargo) IN ('gerencia', 'gerente')
            ");
            $stmt->execute();
            $gerentes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($gerentes)) {
                throw new Exception("No se encontraron gerentes para envío de PDF del permiso #{$id_permiso}");
            }

            error_log("📎 Enviando (UNICO) PDF a gerencia: " . count($gerentes) . " destinatario(s) - archivo: {$fullPath}");

            // Configurar PHPMailer
            $mail = ConfigCorreo::configurarSMTP(false);
            if (!$mail) {
                throw new Exception("Error al configurar SMTP");
            }

            // Agregar gerentes como destinatarios
            foreach ($gerentes as $gerente) {
                $mail->addAddress($gerente['correo'], $gerente['nombre']);
                error_log("   📧 Agregando destinatario gerente: {$gerente['nombre']} ({$gerente['correo']})");
            }

            // Adjuntar PDF
            $mail->addAttachment($fullPath, "Permiso_{$id_permiso}_" . basename($fullPath));

            // Asunto formal y cuerpo formal con URL del sistema
            $mail->isHTML(true);
            $mail->Subject = "Control de Permisos - Documento confidencial (Permiso N°{$id_permiso})";

            $cuerpoHTML = "
            <html><body style='font-family: Arial, sans-serif;'>
                <p>Estimado/a miembro de Gerencia,</p>
                <p>Adjunto remito el documento PDF confidencial correspondiente a la solicitud de permiso N° <strong>{$id_permiso}</strong> para su revisión.</p>
                <p><strong>Solicitante:</strong> {$permiso['nombre_solicitante']} ({$permiso['nombre_cargo']})<br>
                   <strong>Correo solicitante:</strong> {$permiso['correo_solicitante']}<br>
                   <strong>Archivo:</strong> " . basename($fullPath) . " ({$tamañoMB} MB)</p>
                <p>Por favor, verifique el documento y proceda según las políticas internas.</p>
                <p>Acceda al sistema: " . APP_URL . "</p>
                <p>Atentamente,<br>Departamento de Control de Permisos<br>Coosanandresito</p>
            </body></html>";

            $mail->Body = $cuerpoHTML;

            $mail->AltBody = "DOCUMENTO PDF CONFIDENCIAL - PERMISO #{$id_permiso}\n\n" .
                "Solicitante: {$permiso['nombre_solicitante']} ({$permiso['nombre_cargo']})\n" .
                "Correo: {$permiso['correo_solicitante']}\n\n" .
                "Archivo: " . basename($fullPath) . " ({$tamañoMB} MB)\n\n" .
                "Acceda al sistema: " . APP_URL . "\n\n" .
                "Nota: Este PDF es confidencial y remitido exclusivamente a Gerencia.";

            $envio_exitoso = $mail->send();

            if ($envio_exitoso) {
                error_log("✅ PDF enviado (UNICO) a gerencia para permiso #{$id_permiso} (" . count($gerentes) . " destinatarios)");
                // Crear notificaciones en BD para cada gerente
                foreach ($gerentes as $gerente) {
                    $mensaje_notif = "Documento PDF del permiso N°{$id_permiso} recibido para revisión confidencial. Acceda: " . APP_URL . "";
                    $this->crearNotificacion($gerente['id_usuario'], $mensaje_notif);
                }
                return true;
            } else {
                throw new Exception("Error al enviar el correo con PDF");
            }

        } catch (Exception $e) {
            error_log("❌ Error enviando PDF SOLO a gerencia (permiso #{$id_permiso}): " . $e->getMessage());
            if (isset($mail)) {
                error_log("PHPMailer->ErrorInfo: " . ($mail->ErrorInfo ?? 'n/d'));
            }
            return false;
        }
    }

    /**
     * 🔥 COORDINADOR ENVÍA A GERENTE (ahora envía PDF a Gerencia si existe)
     */
    public function coordinadorEnviaAGerente($id_permiso, $id_gerente) {
        $gerente = $this->obtenerUsuario($id_gerente);
        $permiso = $this->obtenerPermiso($id_permiso);

        if (!$gerente) return false;
        if (!$permiso) return false;

        // Email a gerente → "Solicitud del Coordinador"
        $mensaje = "Tiene una solicitud de permiso N°{$id_permiso} enviada por el coordinador para su revisión. Acceda: " . APP_URL . "";
        $this->crearNotificacion($id_gerente, $mensaje);

        $asunto = "Control de Permisos - Solicitud enviada por Coordinador (Permiso N°{$id_permiso})";
        $correo = "Estimado(a) {$gerente['nombre']},\n\n";
        $correo .= "El coordinador ha enviado una solicitud de permiso N°{$id_permiso} para su revisión.\n";
        $correo .= "Solicitante original: {$permiso['nombre_solicitante']}\n\n";
        $correo .= "Acceda al sistema: " . APP_URL . "\n\n";
        $correo .= "Atentamente,\nSistema de Control de Permisos Coosanandresito";

        ConfigCorreo::enviarCorreo($gerente['correo'], $asunto, $correo, $gerente['nombre']);

        // Si existe PDF en el permiso, enviarlo ÚNICAMENTE a Gerencia (adjunto)
        if (!empty($permiso['documento_pdf'])) {
            $this->enviarPDFSoloAGerencia($id_permiso, $permiso['documento_pdf']);
        }

        return true;
    }

    /**
     * 🔥 SOLICITANTE DIRECTO REENVÍA DIRECTO A GERENTE
     * Ahora envía PDF a Gerencia si existe en el permiso.
     */
    public function solicitanteDirectoReenviaDirecto($id_permiso, $id_solicitante, $id_gerente) {
        $solicitante = $this->obtenerUsuario($id_solicitante);
        $gerente = $this->obtenerUsuario($id_gerente);

        if (!$solicitante || !$gerente) return false;

        // Email al solicitante → "Solicitud Reenviada Directa"
        $this->crearNotificacion($id_solicitante, "Su solicitud #{$id_permiso} ha sido reenviada correctamente a la Gerencia. Acceda: " . APP_URL . "");
        $asunto_sol = "Control de Permisos - Solicitud Reenviada";
        $correo_sol = "Estimado(a) {$solicitante['nombre']},\n\n";
        $correo_sol .= "Su solicitud corregida ha sido reenviada correctamente a la Gerencia.\n";
        $correo_sol .= "Puede consultar el estado en: " . APP_URL . "\n\n";
        $correo_sol .= "Atentamente,\nSistema de Control de Permisos Coosanandresito";

        ConfigCorreo::enviarCorreo($solicitante['correo'], $asunto_sol, $correo_sol, $solicitante['nombre']);

        // Email a gerencia → "Solicitud Corregida Recibida"
        $this->crearNotificacion($id_gerente, "Solicitud #{$id_permiso} de {$solicitante['nombre']} ha sido corregida y reenviada para revisión. Acceda: " . APP_URL . "");
        $asunto_ger = "Control de Permisos - Solicitud Corregida (Permiso N°{$id_permiso})";
        $correo_ger = "Estimada Gerencia,\n\n";
        $correo_ger .= "La solicitud de {$solicitante['nombre']} ha sido corregida y reenviada para su revisión.\n";
        $correo_ger .= "Acceda al sistema: " . APP_URL . "\n\n";
        $correo_ger .= "Atentamente,\nSistema de Control de Permisos Coosanandresito";

        ConfigCorreo::enviarCorreo($gerente['correo'], $asunto_ger, $correo_ger, $gerente['nombre']);

        // Si existe PDF en el permiso, enviarlo ÚNICAMENTE a Gerencia (adjunto)
        $permiso = $this->obtenerPermiso($id_permiso);
        if ($permiso && !empty($permiso['documento_pdf'])) {
            $this->enviarPDFSoloAGerencia($id_permiso, $permiso['documento_pdf']);
        }

        return true;
    }

    /**
     * 🔥 GERENTE RECHAZA (FLUJO DIRECTO) - ahora incluye motivo en el correo
     */
    public function gerenteRechazaDirecto($id_permiso, $motivo = '') {
        $permiso = $this->obtenerPermiso($id_permiso);
        if (!$permiso) return false;

        // Crear notificación con motivo
        $mensaje = "Su solicitud de permiso #{$id_permiso} ha sido rechazada por la Gerencia.";
        if (!empty($motivo)) {
            $mensaje .= " Motivo: {$motivo}";
        }
        $mensaje .= " Acceda: " . APP_URL . "";
        $this->crearNotificacion($permiso['id_usuario'], $mensaje);

        $asunto = "Control de Permisos - Solicitud Rechazada (Permiso N°{$id_permiso})";
        $correo = "Estimado(a) {$permiso['nombre_solicitante']},\n\n";
        $correo .= "Su solicitud de permiso ha sido rechazada por la Gerencia.\n\n";
        if (!empty($motivo)) {
            $correo .= "Motivo del rechazo: {$motivo}\n\n";
        }
        $correo .= "Puede revisar los detalles en: " . APP_URL . "\n\n";
        $correo .= "Atentamente,\nSistema de Control de Permisos Coosanandresito";

        ConfigCorreo::enviarCorreo($permiso['correo_solicitante'], $asunto, $correo, $permiso['nombre_solicitante']);

        return true;
    }

    /**
     * 🔥 GERENTE RECHAZA (FLUJO AUXILIAR) - ahora envía motivo tanto al coordinador como al auxiliar
     */
    public function gerenteRechazaAuxiliar($id_permiso, $id_coordinador, $motivo = '') {
        $coordinador = $this->obtenerUsuario($id_coordinador);
        $permiso = $this->obtenerPermiso($id_permiso);
        if (!$coordinador || !$permiso) return false;

        // Email al coordinador → incluir motivo
        $mensaje_coord = "La solicitud de permiso #{$id_permiso} ha sido rechazada por la Gerencia.";
        if (!empty($motivo)) {
            $mensaje_coord .= " Motivo: {$motivo}";
        }
        $mensaje_coord .= " Acceda: " . APP_URL . "";
        $this->crearNotificacion($id_coordinador, $mensaje_coord);

        $asunto_coord = "Control de Permisos - Solicitud Rechazada (Permiso N°{$id_permiso})";
        $correo_coord = "Estimado(a) {$coordinador['nombre']},\n\n";
        $correo_coord .= "Se le informa que la solicitud de permiso #{$id_permiso}, solicitada por {$permiso['nombre_solicitante']}, ha sido rechazada por la Gerencia.\n";
        if (!empty($motivo)) {
            $correo_coord .= "Motivo del rechazo: {$motivo}\n\n";
        }
        $correo_coord .= "Revise los detalles en: " . APP_URL . "\n\n";
        $correo_coord .= "Atentamente,\nSistema de Control de Permisos Coosanandresito";

        ConfigCorreo::enviarCorreo($coordinador['correo'], $asunto_coord, $correo_coord, $coordinador['nombre']);

        // Además notificar al auxiliar (solicitante) con el motivo
        $asunto_aux = "Control de Permisos - Solicitud Rechazada (Permiso N°{$id_permiso})";
        $correo_aux = "Estimado(a) {$permiso['nombre_solicitante']},\n\n";
        $correo_aux .= "Su solicitud de permiso N°{$id_permiso} ha sido rechazada por la Gerencia.\n\n";
        if (!empty($motivo)) {
            $correo_aux .= "Motivo del rechazo: {$motivo}\n\n";
        }
        $correo_aux .= "Para más información, contacte con su coordinador o revise el sistema: " . APP_URL . "\n\n";
        $correo_aux .= "Atentamente,\nSistema de Control de Permisos Coosanandresito";

        ConfigCorreo::enviarCorreo($permiso['correo_solicitante'], $asunto_aux, $correo_aux, $permiso['nombre_solicitante']);

        // Crear notificación para el solicitante también
        $mensaje_aux = "Su solicitud de permiso N°{$id_permiso} ha sido rechazada por la Gerencia.";
        if (!empty($motivo)) $mensaje_aux .= " Motivo: {$motivo}";
        $mensaje_aux .= " Acceda: " . APP_URL . "";
        $this->crearNotificacion($permiso['id_usuario'], $mensaje_aux);

        return true;
    }

    /**
     * 🔥 PERMISO FINALIZADO - Enviar correos (ahora SE NOTIFICA SIEMPRE A GERENCIA además del revisor)
     */
    public function permisoFinalizado($id_permiso, $tiempo_total_ausencia) {
        $permiso = $this->obtenerPermiso($id_permiso);
        if (!$permiso) return false;

        // Formatear el tiempo de forma legible
        $tiempo_formateado = $this->formatearTiempoLegible($tiempo_total_ausencia);

        // Email al solicitante → "Permiso Finalizado"
        $mensaje_sol = "Has finalizado tu permiso #{$id_permiso} correctamente. Tiempo total: {$tiempo_formateado}. Accede: " . APP_URL . "";
        $this->crearNotificacion($permiso['id_usuario'], $mensaje_sol);

        $asunto_sol = "Control de Permisos - Permiso Finalizado (Permiso N°{$id_permiso})";
        $correo_sol = "Estimado(a) {$permiso['nombre_solicitante']},\n\n";
        $correo_sol .= "Has finalizado tu permiso correctamente.\n";
        $correo_sol .= "Tiempo total de ausencia registrado: {$tiempo_formateado}\n\n";
        $correo_sol .= "Puedes consultar esta información en: " . APP_URL . "\n\n";
        $correo_sol .= "Atentamente,\nSistema de Control de Permisos Coosanandresito";

        ConfigCorreo::enviarCorreo($permiso['correo_solicitante'], $asunto_sol, $correo_sol, $permiso['nombre_solicitante']);

        // NOTIFICAR SIEMPRE A GERENCIA (sin adjuntos)
        try {
            $stmtG = $this->pdo->prepare("
                SELECT u.id_usuario, u.nombre, u.correo
                FROM usuarios u
                INNER JOIN cargo c ON u.id_cargo = c.id_cargo
                WHERE LOWER(c.nombre_cargo) IN ('gerencia','gerente')
            ");
            $stmtG->execute();
            $gerentes = $stmtG->fetchAll(PDO::FETCH_ASSOC);

            foreach ($gerentes as $gerente) {
                $mensaje_ger = "El usuario {$permiso['nombre_solicitante']} ha finalizado su permiso #{$id_permiso}. Tiempo total: {$tiempo_formateado}. Acceda: " . APP_URL . "";
                $this->crearNotificacion($gerente['id_usuario'], $mensaje_ger);

                $asunto_ger = "Control de Permisos - Permiso Finalizado (Permiso N°{$id_permiso})";
                $correo_ger = "Estimado(a) {$gerente['nombre']},\n\n";
                $correo_ger .= "El usuario {$permiso['nombre_solicitante']} ha finalizado su permiso (#{$id_permiso}).\n";
                $correo_ger .= "Tiempo total de ausencia registrado: {$tiempo_formateado}\n\n";
                $correo_ger .= "Revíselo en el sistema: " . APP_URL . "\n\n";
                $correo_ger .= "Atentamente,\nSistema de Control de Permisos Coosanandresito";

                ConfigCorreo::enviarCorreo($gerente['correo'], $asunto_ger, $correo_ger, $gerente['nombre']);
                error_log("✅ Correo enviado a Gerencia: {$gerente['nombre']} ({$gerente['correo']}) para permiso #{$id_permiso}");
            }
        } catch (Exception $e) {
            error_log("❌ Error notificando a Gerencia tras finalización permiso #{$id_permiso}: " . $e->getMessage());
        }

        // Mantener notificación al revisor si aplica (legacy)
        $revisor = $this->obtenerRevisorPorCargo($permiso['nombre_cargo'], $id_permiso);
        if ($revisor) {
            $mensaje_rev = "El usuario {$permiso['nombre_solicitante']} ha finalizado su permiso #{$id_permiso}. Tiempo total: {$tiempo_formateado}. Acceda: " . APP_URL . "";
            $this->crearNotificacion($revisor['id_usuario'], $mensaje_rev);

            $asunto_rev = "Control de Permisos - Permiso Finalizado (Permiso N°{$id_permiso})";
            $correo_rev = "Estimado(a) {$revisor['nombre']},\n\n";
            $correo_rev .= "El usuario {$permiso['nombre_solicitante']} ha finalizado su permiso.\n";
            $correo_rev .= "Tiempo total de ausencia registrado: {$tiempo_formateado}\n\n";
            $correo_rev .= "Revíselo en el sistema: " . APP_URL . "\n\n";
            $correo_rev .= "Atentamente,\nSistema de Control de Permisos Coosanandresito";

            ConfigCorreo::enviarCorreo($revisor['correo'], $asunto_rev, $correo_rev, $revisor['nombre']);
            error_log("✅ Correo enviado al revisor: {$revisor['nombre']} ({$revisor['correo']}) para permiso #{$id_permiso}");
        } else {
            error_log("❌ No se encontró revisor para el cargo: {$permiso['nombre_cargo']} del permiso #{$id_permiso}");
        }

        return true;
    }

    /**
     * Auxiliar crea solicitud (sin PDF): notifica al coordinador y al solicitante.
     */
    public function auxiliarCreaSolicitudSinPDF($id_permiso, $id_auxiliar, $id_coordinador) {
        try {
            $aux = $this->obtenerUsuario($id_auxiliar);
            $coord = $this->obtenerUsuario($id_coordinador);
            $permiso = $this->obtenerPermiso($id_permiso);

            if (!$aux || !$coord || !$permiso) {
                error_log("auxiliarCreaSolicitudSinPDF: datos incompletos para permiso #{$id_permiso}");
                return false;
            }

            // Notificar coordinador
            $mensaje_coord = "Tiene una nueva solicitud de permiso N°{$id_permiso} enviada por {$aux['nombre']}. Acceda: " . APP_URL . "";
            $this->crearNotificacion($id_coordinador, $mensaje_coord);

            $asunto_coord = "Control de Permisos - Nueva solicitud (Permiso N°{$id_permiso})";
            $correo_coord = "Estimado(a) {$coord['nombre']},\n\nHa recibido la solicitud de permiso N°{$id_permiso} enviada por {$aux['nombre']}.\nMotivo: {$permiso['motivo']}\n\nAcceda: " . APP_URL . "\n\nAtentamente,\nSistema de Control de Permisos Coosanandresito";
            ConfigCorreo::enviarCorreo($coord['correo'], $asunto_coord, $correo_coord, $coord['nombre']);

            // Notificar solicitante
            $this->crearNotificacion($id_auxiliar, "Su solicitud de permiso N°{$id_permiso} fue enviada al coordinador para revisión.");
            $asunto_aux = "Control de Permisos - Solicitud enviada (Permiso N°{$id_permiso})";
            $correo_aux = "Estimado(a) {$aux['nombre']},\n\nSu solicitud N°{$id_permiso} fue creada y enviada al coordinador para revisión.\n\nAcceda: " . APP_URL . "\n\nAtentamente,\nSistema de Control de Permisos Coosanandresito";
            ConfigCorreo::enviarCorreo($aux['correo'], $asunto_aux, $correo_aux, $aux['nombre']);

            error_log("auxiliarCreaSolicitudSinPDF: notificados coordinador({$coord['id_usuario']}) y solicitante({$aux['id_usuario']}) para permiso #{$id_permiso}");
            return true;
        } catch (Exception $e) {
            error_log("Error auxiliarCreaSolicitudSinPDF: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Solicitante directo -> Envia a Gerencia con PDF (envía notificaciones al gerente y envía PDF confidencial a todo Gerencia).
     */
    public function solicitanteDirectoEnviaAGerenciaConPDF($id_permiso, $id_solicitante, $id_gerente, $ruta_pdf = null) {
        try {
            $sol = $this->obtenerUsuario($id_solicitante);
            $ger = $this->obtenerUsuario($id_gerente);
            $permiso = $this->obtenerPermiso($id_permiso);

            if (!$sol || !$ger || !$permiso) {
                error_log("solicitanteDirectoEnviaAGerenciaConPDF: datos incompletos para permiso #{$id_permiso}");
                return false;
            }

            // Notificar gerente
            $mensaje_ger = "Tiene una solicitud de permiso N°{$id_permiso} enviada por {$sol['nombre']}. Acceda: " . APP_URL . "";
            $this->crearNotificacion($id_gerente, $mensaje_ger);

            $asunto = "Control de Permisos - Solicitud enviada por {$sol['nombre']} (Permiso N°{$id_permiso})";
            $correo = "Estimado(a) {$ger['nombre']},\n\nEl usuario {$sol['nombre']} ha enviado la solicitud N°{$id_permiso} para su revisión.\n\nAcceda: " . APP_URL . "\n\nAtentamente,\nSistema de Control de Permisos Coosanandresito";
            ConfigCorreo::enviarCorreo($ger['correo'], $asunto, $correo, $ger['nombre']);

            // Notificar solicitante
            $this->crearNotificacion($id_solicitante, "Su solicitud #{$id_permiso} fue enviada a Gerencia para su revisión.");
            $asunto_sol = "Control de Permisos - Solicitud enviada a Gerencia (Permiso N°{$id_permiso})";
            $correo_sol = "Estimado(a) {$sol['nombre']},\n\nSu solicitud N°{$id_permiso} fue enviada a Gerencia con el documento adjunto para su revisión.\n\nAcceda: " . APP_URL . "\n\nAtentamente,\nSistema de Control de Permisos Coosanandresito";
            ConfigCorreo::enviarCorreo($sol['correo'], $asunto_sol, $correo_sol, $sol['nombre']);

            // Enviar PDF confidencial a todo Gerencia (usa ruta pasada o la almacenada en permiso)
            $ruta = $ruta_pdf ?: ($permiso['documento_pdf'] ?? null);
            if (!empty($ruta)) {
                $this->enviarPDFSoloAGerencia($id_permiso, $ruta);
            }

            return true;
        } catch (Exception $e) {
            error_log("Error solicitanteDirectoEnviaAGerenciaConPDF: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Solicitante directo -> Envia a Gerencia sin PDF.
     */
    public function solicitanteDirectoEnviaAGerenciaSinPDF($id_permiso, $id_solicitante, $id_gerente) {
        try {
            $sol = $this->obtenerUsuario($id_solicitante);
            $ger = $this->obtenerUsuario($id_gerente);
            $permiso = $this->obtenerPermiso($id_permiso);

            if (!$sol || !$ger || !$permiso) {
                error_log("solicitanteDirectoEnviaAGerenciaSinPDF: datos incompletos para permiso #{$id_permiso}");
                return false;
            }

            $mensaje_ger = "Tiene una solicitud de permiso N°{$id_permiso} enviada por {$sol['nombre']}. Acceda: " . APP_URL . "";
            $this->crearNotificacion($id_gerente, $mensaje_ger);

            $asunto = "Control de Permisos - Solicitud enviada por {$sol['nombre']} (Permiso N°{$id_permiso})";
            $correo = "Estimado(a) {$ger['nombre']},\n\nEl usuario {$sol['nombre']} ha enviado la solicitud N°{$id_permiso} para su revisión.\n\nAcceda: " . APP_URL . "\n\nAtentamente,\nSistema de Control de Permisos Coosanandresito";
            ConfigCorreo::enviarCorreo($ger['correo'], $asunto, $correo, $ger['nombre']);

            $this->crearNotificacion($id_solicitante, "Su solicitud #{$id_permiso} fue enviada a Gerencia para su revisión.");
            $asunto_sol = "Control de Permisos - Solicitud enviada a Gerencia (Permiso N°{$id_permiso})";
            $correo_sol = "Estimado(a) {$sol['nombre']},\n\nSu solicitud N°{$id_permiso} fue enviada a Gerencia para revisión.\n\nAcceda: " . APP_URL . "\n\nAtentamente,\nSistema de Control de Permisos Coosanandresito";
            ConfigCorreo::enviarCorreo($sol['correo'], $asunto_sol, $correo_sol, $sol['nombre']);

            return true;
        } catch (Exception $e) {
            error_log("Error solicitanteDirectoEnviaAGerenciaSinPDF: " . $e->getMessage());
            return false;
        }
    }

    // Alias para compatibilidad con nombres usados en otros archivos
    public function solicitanteDirectoEnviaAGerenteSinPDF($id_permiso, $id_solicitante, $id_gerente) {
        return $this->solicitanteDirectoEnviaAGerenciaSinPDF($id_permiso, $id_solicitante, $id_gerente);
    }

    public function solicitanteDirectoEnviaAGerenteConPDF($id_permiso, $id_solicitante, $id_gerente, $ruta_pdf = null) {
        return $this->solicitanteDirectoEnviaAGerenciaConPDF($id_permiso, $id_solicitante, $id_gerente, $ruta_pdf);
    }

    /**
     * Auxiliar reenvía corregido → notifica al coordinador (flujo de reenvío).
     */
    public function auxiliarReenviaCorregido($id_permiso, $id_auxiliar, $id_coordinador) {
        try {
            $aux = $this->obtenerUsuario($id_auxiliar);
            $coord = $this->obtenerUsuario($id_coordinador);
            $permiso = $this->obtenerPermiso($id_permiso);

            if (!$aux || !$coord || !$permiso) {
                error_log("auxiliarReenviaCorregido: datos incompletos para permiso #{$id_permiso}");
                return false;
            }

            $mensaje = "El auxiliar {$aux['nombre']} ha reenviado corregida la solicitud N°{$id_permiso}. Acceda: " . APP_URL . "";
            $this->crearNotificacion($id_coordinador, $mensaje);
            ConfigCorreo::enviarCorreo($coord['correo'], "Control de Permisos - Solicitud reenviada corregida (N°{$id_permiso})", $mensaje, $coord['nombre']);

            $this->crearNotificacion($id_auxiliar, "Su solicitud #{$id_permiso} fue reenviada correctamente al coordinador.");
            ConfigCorreo::enviarCorreo($aux['correo'], "Control de Permisos - Solicitud reenviada", "Su solicitud #{$id_permiso} fue reenviada para revisión.", $aux['nombre']);

            return true;
        } catch (Exception $e) {
            error_log("Error auxiliarReenviaCorregido: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Coordinador reenvía al auxiliar → notifica al auxiliar para corrección.
     */
    public function coordinadorReenviaAAuxiliar($id_permiso, $id_auxiliar) {
        try {
            $aux = $this->obtenerUsuario($id_auxiliar);
            $permiso = $this->obtenerPermiso($id_permiso);

            if (!$aux || !$permiso) {
                error_log("coordinadorReenviaAAuxiliar: datos incompletos para permiso #{$id_permiso}");
                return false;
            }

            $mensaje = "Su solicitud de permiso N°{$id_permiso} ha sido devuelta para corrección por su coordinador. Acceda: " . APP_URL . "";
            $this->crearNotificacion($id_auxiliar, $mensaje);
            ConfigCorreo::enviarCorreo($aux['correo'], "Control de Permisos - Solicitud devuelta para corrección (N°{$id_permiso})", $mensaje, $aux['nombre']);

            return true;
        } catch (Exception $e) {
            error_log("Error coordinadorReenviaAAuxiliar: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Gerente aprueba → notifica al solicitante (y al revisor si aplica).
     */
    public function gerenteAprueba($id_permiso) {
        try {
            $permiso = $this->obtenerPermiso($id_permiso);
            if (!$permiso) return false;

            $mensaje = "Su solicitud de permiso #{$id_permiso} ha sido aprobada por la Gerencia. Acceda: " . APP_URL . "";
            $this->crearNotificacion($permiso['id_usuario'], $mensaje);

            $asunto = "Control de Permisos - Solicitud Aprobada (Permiso N°{$id_permiso})";
            $correo = "Estimado(a) {$permiso['nombre_solicitante']},\n\nSu solicitud de permiso ha sido aprobada por la Gerencia.\n\nAcceda: " . APP_URL . "\n\nAtentamente,\nSistema de Control de Permisos Coosanandresito";
            ConfigCorreo::enviarCorreo($permiso['correo_solicitante'], $asunto, $correo, $permiso['nombre_solicitante']);

            // Notificar revisor si aplica
            $revisor = $this->obtenerRevisorPorCargo($permiso['nombre_cargo'], $id_permiso);
            if ($revisor) {
                $this->crearNotificacion($revisor['id_usuario'], "La solicitud #{$id_permiso} ha sido aprobada por Gerencia.");
                ConfigCorreo::enviarCorreo($revisor['correo'], "Control de Permisos - Solicitud aprobada (N°{$id_permiso})", "La solicitud #{$id_permiso} fue aprobada por Gerencia.", $revisor['nombre']);
            }

            return true;
        } catch (Exception $e) {
            error_log("Error gerenteAprueba: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Gerente cancela → notifica al solicitante y a Gerencia si aplica.
     */
    public function gerenteCancela($id_permiso) {
        try {
            $permiso = $this->obtenerPermiso($id_permiso);
            if (!$permiso) return false;

            $mensaje = "Su solicitud de permiso #{$id_permiso} ha sido cancelada por la Gerencia. Acceda: " . APP_URL . "";
            $this->crearNotificacion($permiso['id_usuario'], $mensaje);

            $asunto = "Control de Permisos - Solicitud Cancelada (Permiso N°{$id_permiso})";
            $correo = "Estimado(a) {$permiso['nombre_solicitante']},\n\nSu solicitud de permiso ha sido cancelada por la Gerencia.\n\nAcceda: " . APP_URL . "\n\nAtentamente,\nSistema de Control de Permisos Coosanandresito";
            ConfigCorreo::enviarCorreo($permiso['correo_solicitante'], $asunto, $correo, $permiso['nombre_solicitante']);

            // Notificar Gerencia (opcional, aquí se notifica a todos los gerentes)
            try {
                $stmtG = $this->pdo->prepare("
                    SELECT u.id_usuario, u.nombre, u.correo
                    FROM usuarios u
                    INNER JOIN cargo c ON u.id_cargo = c.id_cargo
                    WHERE LOWER(c.nombre_cargo) IN ('gerencia','gerente')
                ");
                $stmtG->execute();
                $gerentes = $stmtG->fetchAll(PDO::FETCH_ASSOC);
                foreach ($gerentes as $gerente) {
                    $this->crearNotificacion($gerente['id_usuario'], "La solicitud #{$id_permiso} ha sido cancelada por Gerencia.");
                }
            } catch (Exception $e) {
                error_log("gerenteCancela - advertencia al notificar a Gerencia: " . $e->getMessage());
            }

            return true;
        } catch (Exception $e) {
            error_log("Error gerenteCancela: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Formatea segundos a cadena legible (días, horas, min).
     */
    private function formatearTiempoLegible($segundos) {
        if (!is_numeric($segundos)) return (string)$segundos;
        $s = (int)$segundos;
        $d = intdiv($s, 86400); $s %= 86400;
        $h = intdiv($s, 3600); $s %= 3600;
        $m = intdiv($s, 60); $s %= 60;
        $parts = [];
        if ($d) $parts[] = "{$d}d";
        if ($h) $parts[] = "{$h}h";
        if ($m) $parts[] = "{$m}m";
        if ($s) $parts[] = "{$s}s";
        return implode(' ', $parts) ?: '0s';
    }

    /**
     * Determina un "revisor" según el cargo del solicitante (coordinador para auxiliares, Gerencia para otros).
     */
    private function obtenerRevisorPorCargo($nombre_cargo, $id_permiso = null) {
        try {
            $cargo = strtolower(trim($nombre_cargo));
            if ($cargo === 'auxiliar') {
                $stmt = $this->pdo->prepare("
                    SELECT u.id_usuario, u.nombre, u.correo
                    FROM usuarios u
                    INNER JOIN cargo c ON u.id_cargo = c.id_cargo
                    WHERE LOWER(c.nombre_cargo) = 'coordinador' LIMIT 1
                ");
                $stmt->execute();
                return $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $stmt = $this->pdo->prepare("
                    SELECT u.id_usuario, u.nombre, u.correo
                    FROM usuarios u
                    INNER JOIN cargo c ON u.id_cargo = c.id_cargo
                    WHERE LOWER(c.nombre_cargo) IN ('gerencia','gerente') LIMIT 1
                ");
                $stmt->execute();
                return $stmt->fetch(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) {
            error_log("Error obtenerRevisorPorCargo: " . $e->getMessage());
            return null;
        }
    }
}
?>
