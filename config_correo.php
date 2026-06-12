<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class ConfigCorreo {

    public static function configurarSMTP($debug = false) {
        try {
            $mail = new PHPMailer(true);

            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = SMTP_PORT;

            $mail->SMTPDebug  = ($debug || (defined('APP_ENV') && APP_ENV === 'desarrollo'))
                                    ? SMTP::DEBUG_SERVER
                                    : SMTP::DEBUG_OFF;

            $mail->Debugoutput = function ($str, $level) {
                error_log("PHPMailer DEBUG: $str");
            };

            $mail->setFrom(SMTP_FROM, SMTP_NAME);
            $mail->CharSet    = 'UTF-8';
            $mail->XMailer    = ' ';
            $mail->addReplyTo(SMTP_FROM, SMTP_NAME);
            $mail->addCustomHeader('Precedence', 'bulk');
            $mail->addCustomHeader('X-Auto-Response-Suppress', 'OOF, AutoReply');

            return $mail;

        } catch (Exception $e) {
            error_log("Error configurando PHPMailer: " . $e->getMessage());
            return false;
        }
    }

    public static function htmlTemplate(string $titulo, string $cuerpo): string {
        return "<!DOCTYPE html>
<html lang='es'>
<head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'></head>
<body style='margin:0;padding:0;background:#f4f6f8;font-family:Arial,sans-serif;'>
  <table width='100%' cellpadding='0' cellspacing='0' style='background:#f4f6f8;padding:30px 0;'>
    <tr><td align='center'>
      <table width='580' cellpadding='0' cellspacing='0' style='background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);'>
        <tr>
          <td style='background:#1a56db;padding:24px 32px;'>
            <h1 style='color:#ffffff;margin:0;font-size:18px;font-weight:700;'>Sistema de Permisos &mdash; Coosanandresito</h1>
          </td>
        </tr>
        <tr>
          <td style='padding:32px;color:#333333;font-size:15px;line-height:1.6;'>
            <h2 style='color:#1a56db;font-size:16px;margin-top:0;'>{$titulo}</h2>
            {$cuerpo}
          </td>
        </tr>
        <tr>
          <td style='background:#f4f6f8;padding:16px 32px;text-align:center;color:#888;font-size:12px;border-top:1px solid #e2e8f0;'>
            Este es un mensaje automático del Sistema de Control de Permisos &mdash; Coosanandresito.<br>
            Por favor no responda directamente a este correo.
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body></html>";
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

            // Enviar como HTML con plantilla
            $mail->isHTML(true);
            $mail->Subject = $asunto;
            $mail->Body    = self::htmlTemplate($asunto, nl2br(htmlspecialchars($mensaje)));
            $mail->AltBody = $mensaje;
            
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
