<?php
session_start();

// Si no hay correo guardado en sesión, volver al login
if (!isset($_SESSION['correo_recuperacion'])) {
    header("Location: login.php");
    exit;
}

$correo = $_SESSION['correo_recuperacion'];

// Conexión DB
$host = 'localhost';
$db = 'control_permisos_pruebas';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die('Error de conexión: ' . $e->getMessage());
}

$mensaje = "";

// Si envía nueva contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass1 = $_POST['nueva_password'] ?? '';
    $pass2 = $_POST['confirmar_password'] ?? '';

    if (!$pass1 || !$pass2) {
        $mensaje = "Por favor, completa todos los campos.";
    } elseif ($pass1 !== $pass2) {
        $mensaje = "Las contraseñas no coinciden.";
    } elseif (strlen($pass1) < 6) {
        $mensaje = "La contraseña debe tener al menos 6 caracteres.";
    } else {
        // Verificar si la contraseña ya fue usada
        $stmt = $pdo->prepare("SELECT password FROM usuarios WHERE correo = ?");
        $stmt->execute([$correo]);
        $usuario = $stmt->fetch();

        if (password_verify($pass1, $usuario['password'])) {
            $mensaje = "No puedes usar una contraseña que ya hayas utilizado antes.";
        } else {
            // Hashear y guardar nueva contraseña
            $passHash = password_hash($pass1, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE usuarios SET password = ?, codigo_recuperacion = NULL, fecha_codigo = NULL WHERE correo = ?");
            $stmt->execute([$passHash, $correo]);

            // Borrar sesión y redirigir
            unset($_SESSION['correo_recuperacion']);
            header("Location: login.php?msg=contraseña_actualizada");
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Nueva Contraseña</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background-color: #fdf3e6;
      margin: 0;
      padding: 0;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
    }
    .container {
      background: white;
      border: 1px solid #f5c6a5;
      border-radius: 8px;
      padding: 20px;
      width: 320px;
      text-align: center;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    h1 {
      color: #d35400;
      margin-bottom: 20px;
    }
    .password-container {
      position: relative;
      width: 100%;
      margin-bottom: 15px;
    }
    .password-container input {
      width: 100%;
      padding: 10px;
      padding-right: 40px; /* Espacio para el ícono */
      border: 1px solid #ddd;
      border-radius: 5px;
      font-size: 14px;
      box-sizing: border-box;
    }
    .password-container .toggle-password {
      position: absolute;
      top: 50%;
      right: 10px;
      transform: translateY(-50%);
      cursor: pointer;
      font-size: 16px;
      color: #e67e22;
    }
    .password-container .toggle-password:hover {
      color: #d35400;
    }
    .password-container .toggle-password svg {
      width: 20px;
      height: 20px;
    }
    button {
      background-color: #f39c12;
      color: white;
      border: none;
      padding: 10px;
      width: 100%;
      border-radius: 5px;
      cursor: pointer;
      font-size: 16px;
    }
    button:hover {
      background-color: #e67e22;
    }
    .error {
      color: red;
      font-size: 14px;
      margin-bottom: 10px;
    }
  </style>
</head>
<body>
  <div class="container">
    <h1>Nueva Contraseña</h1>
    <?php if ($mensaje): ?>
        <p class="error"><?php echo $mensaje; ?></p>
    <?php endif; ?>
    <form method="POST">
      <div class="password-container">
        <input type="password" name="nueva_password" id="nueva_password" placeholder="Nueva contraseña" required>
        <span class="toggle-password" onclick="togglePassword('nueva_password')" title="Mostrar/Ocultar contraseña">
          <svg xmlns="http://www.w3.org/2000/svg" class="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
            <circle cx="12" cy="12" r="3"/>
          </svg>
        </span>
      </div>
      <div class="password-container">
        <input type="password" name="confirmar_password" id="confirmar_password" placeholder="Confirmar contraseña" required>
        <span class="toggle-password" onclick="togglePassword('confirmar_password')" title="Mostrar/Ocultar contraseña">
          <svg xmlns="http://www.w3.org/2000/svg" class="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
            <circle cx="12" cy="12" r="3"/>
          </svg>
        </span>
      </div>
      <button type="submit">Guardar Contraseña</button>
    </form>
  </div>

  <script>
    // Función para mostrar/ocultar la contraseña
    function togglePassword(inputId) {
      const passwordInput = document.getElementById(inputId);
      const toggleIcon = passwordInput.nextElementSibling.querySelector('svg');
      if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.innerHTML = `
          <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
          <line x1="1" y1="1" x2="23" y2="23" stroke="currentColor" stroke-width="2"/>
        `;
      } else {
        passwordInput.type = 'password';
        toggleIcon.innerHTML = `
          <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
          <circle cx="12" cy="12" r="3"/>
        `;
      }
    }
  </script>
</body>
</html>
