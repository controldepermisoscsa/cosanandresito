<?php
session_start();
require_once __DIR__ . '/conexion.php';

$mensaje_estado = '';
$tipo_mensaje = '';

// Mensaje cuando el código expiró
if (isset($_GET['error']) && $_GET['error'] === 'codigo_expirado') {
    $mensaje_estado = "El código de verificación ha expirado. Por favor, solicita uno nuevo.";
    $tipo_mensaje = 'error';
}

// Si el formulario fue enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = trim($_POST['correo']);

    // Verificar si el correo existe
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE correo = ?");
    $stmt->execute([$correo]);
    $usuario = $stmt->fetch();

    if ($usuario) {
        // Generar código aleatorio de 6 dígitos
        $codigo = rand(100000, 999999);

        // Guardar código en sesión
        $_SESSION['codigo_recuperacion'] = $codigo;
        $_SESSION['correo_recuperacion'] = $correo;

        // Requerir configuración de correo actualizada
        require_once 'config_correo.php';

        try {
            // Preparar mensaje
            $asunto = 'Código de Recuperación de Contraseña - Coosanandresito';
            $mensaje = "Estimado(a) {$usuario['nombre']},\n\n";
            $mensaje .= "Has solicitado recuperar tu contraseña del Sistema de Control de Permisos.\n\n";
            $mensaje .= "Tu código de recuperación es: {$codigo}\n\n";
            $mensaje .= "Este código expira en 15 minutos por seguridad.\n\n";
            $mensaje .= "Si no solicitaste este código, ignora este mensaje.\n\n";
            $mensaje .= "Atentamente,\nSistema de Control de Permisos Coosanandresito";

            // Intentar envío
            $enviado = ConfigCorreo::enviarCorreo($correo, $asunto, $mensaje, $usuario['nombre']);
            
            if ($enviado) {
                $_SESSION['tiempo_codigo'] = time(); // Para expiración
                header("Location: confirmar_codigo.php");
                exit;
            } else {
                $mensaje_estado = "Error al enviar el correo. Por favor, inténtalo más tarde o contacta al administrador.";
                $tipo_mensaje = 'error';
            }
            
        } catch (Exception $e) {
            error_log("Error en envío de código: " . $e->getMessage());
            $mensaje_estado = "Error técnico al enviar el correo. Detalles registrados para revisión del administrador.";
            $tipo_mensaje = 'error';
        }
    } else {
        $mensaje_estado = "El correo electrónico no está registrado en el sistema.";
        $tipo_mensaje = 'error';
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Recuperar Contraseña - Coosanandresito</title>
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
      background: white;
      border: 1px solid #ddd;
      border-radius: 8px;
      padding: 30px;
      width: 100%;
      max-width: 400px;
      text-align: center;
      box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    h1 {
      color: #333;
      margin-bottom: 20px;
    }
    .mensaje {
      padding: 15px;
      margin-bottom: 20px;
      border-radius: 4px;
      text-align: left;
    }
    .mensaje.error {
      background-color: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }
    .mensaje.success {
      background-color: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }
    input {
      width: 100%;
      padding: 12px;
      margin-bottom: 15px;
      border: 1px solid #ddd;
      border-radius: 5px;
      box-sizing: border-box;
    }
    button {
      background-color: #f39c12;
      color: white;
      border: none;
      padding: 12px;
      width: 100%;
      border-radius: 5px;
      cursor: pointer;
      font-size: 16px;
    }
    button:hover {
      background-color: #e67e22;
    }
    .enlaces {
      margin-top: 20px;
    }
    .enlaces a {
      color: #007bff;
      text-decoration: none;
      margin: 0 10px;
    }
    .enlaces a:hover {
      text-decoration: underline;
    }
    .debug-link {
      margin-top: 15px;
      font-size: 14px;
    }
    .debug-link a {
      color: #6c757d;
      text-decoration: none;
    }
  </style>
</head>
<body>
  <div class="container">
    <h1>🔐 Recuperar Contraseña</h1>
    <p>Ingresa tu correo electrónico y te enviaremos un código para restablecer tu contraseña.</p>
    
    <?php if ($mensaje_estado): ?>
        <div class="mensaje <?= $tipo_mensaje ?>">
            <?= htmlspecialchars($mensaje_estado) ?>
        </div>
    <?php endif; ?>
    
    <form method="POST">
      <input 
        type="email" 
        name="correo" 
        placeholder="Tu correo electrónico" 
        required
        value="<?= isset($_POST['correo']) ? htmlspecialchars($_POST['correo']) : '' ?>"
      >
      <button type="submit">📧 Enviar Código</button>
    </form>
    
    <div class="enlaces">
        <a href="login.php">← Volver al inicio de sesión</a>
    </div>
    
  </div>
</body>
</html>
