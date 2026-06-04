<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class ConfigCorreo {

    public static function configurarSMTP() {
        try {
            $mail = new PHPMailer(true);

            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = SMTP_PORT;

            // Debug: OFF en producción, SERVER solo en desarrollo
            $mail->SMTPDebug  = (defined('APP_ENV') && APP_ENV === 'desarrollo')
                                    ? SMTP::DEBUG_SERVER
                                    : SMTP::DEBUG_OFF;

            $mail->Debugoutput = function ($str, $level) {
                error_log("PHPMailer DEBUG: $str");
            };

            $mail->setFrom(SMTP_FROM, SMTP_NAME);
            $mail->CharSet = 'UTF-8';

            return $mail;

        } catch (Exception $e) {
            error_log("Error configurando PHPMailer: " . $e->getMessage());
            return false;
        }
    }
    
    public static function enviarCorreo($destinatario, $asunto, $mensaje, $nombre_destinatario = '') {
        try {
            // Validar parámetros básicos
            if (empty($destinatario) || empty($asunto) || empty($mensaje)) {
                throw new Exception("Parámetros incompletos: destinatario, asunto o mensaje vacíos");
            }
            
            // Validar formato de email
            if (!filter_var($destinatario, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Email destinatario inválido: {$destinatario}");
            }
            
            $mail = self::configurarSMTP();
            if (!$mail) {
                throw new Exception("Error al configurar SMTP");
            }
            
            // Configurar destinatario
            $mail->addAddress($destinatario, $nombre_destinatario);
            
            // Configurar contenido
            $mail->isHTML(false); // Empezar con texto plano para evitar problemas
            $mail->Subject = $asunto;
            $mail->Body = $mensaje;
            
            error_log("📧 Intentando enviar correo:");
            error_log("   Destinatario: {$destinatario}");
            error_log("   Asunto: {$asunto}");
            
            // Enviar
            $resultado = $mail->send();
            
            if ($resultado) {
                error_log("✅ Correo enviado exitosamente a {$destinatario}");
                return true;
            } else {
                throw new Exception("PHPMailer->send() retornó false");
            }
            
        } catch (Exception $e) {
            error_log("❌ Error enviando correo a {$destinatario}: " . $e->getMessage());
            error_log("❌ Asunto: {$asunto}");
            return false;
        }
    }
    
    // Método de prueba para verificar configuración
    public static function probarEnvio($email_destino) {
        try {
            $asunto = "Prueba de Configuración SMTP";
            $mensaje = "Este es un correo de prueba para verificar que la configuración SMTP funciona correctamente.\n\n";
            $mensaje .= "Si recibe este mensaje, el sistema está funcionando.\n\n";
            $mensaje .= "Fecha/Hora: " . date('Y-m-d H:i:s');
            
            $resultado = self::enviarCorreo($email_destino, $asunto, $mensaje, "Usuario de Prueba");
            
            if ($resultado) {
                error_log("✅ Prueba de correo exitosa a {$email_destino}");
                return true;
            } else {
                error_log("❌ Prueba de correo falló a {$email_destino}");
                return false;
            }
            
        } catch (Exception $e) {
            error_log("❌ Error en prueba de correo: " . $e->getMessage());
            return false;
        }
    }
}
?>
