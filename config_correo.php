<?php
require_once 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class ConfigCorreo {
    
    // Configuración SMTP mejorada
    private static $config = [
        'host' => 'smtp.gmail.com',
        'port' => 587,
        'username' => 'vargaszunigafabianstiven@gmail.com',  // ⚠️ CAMBIAR por tu email real
        'password' => 'krwv nzxo hloe cffw',     // ⚠️ CAMBIAR por tu app password
        'encryption' => PHPMailer::ENCRYPTION_STARTTLS,
        'from_email' => 'vargaszunigafabianstiven@gmail.com', // ⚠️ CAMBIAR por tu email real
        'from_name' => 'Sistema Coosanandresito'
    ];
    
    public static function configurarSMTP() {
        try {
            $mail = new PHPMailer(true);
            
            // Configuración del servidor
            $mail->isSMTP();
            $mail->Host = self::$config['host'];
            $mail->SMTPAuth = true;
            $mail->Username = self::$config['username'];
            $mail->Password = self::$config['password'];
            $mail->SMTPSecure = self::$config['encryption'];
            $mail->Port = self::$config['port'];
            
            // Configuración de depuración (temporal para debugging)
            $mail->SMTPDebug = SMTP::DEBUG_SERVER; // ⚠️ Cambiar a DEBUG_OFF en producción
            $mail->Debugoutput = function($str, $level) {
                error_log("PHPMailer DEBUG: $str");
            };
            
            // Configuración del remitente
            $mail->setFrom(self::$config['from_email'], self::$config['from_name']);
            $mail->CharSet = 'UTF-8';
            
            error_log("✅ PHPMailer configurado correctamente");
            return $mail;
            
        } catch (Exception $e) {
            error_log("❌ Error configurando PHPMailer: " . $e->getMessage());
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
