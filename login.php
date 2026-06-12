<?php
session_start();

// Obtener errores específicos y valor del usuario
$error_usuario = $_SESSION['error_usuario'] ?? '';
$error_password = $_SESSION['error_password'] ?? '';
$error_general = $_SESSION['error_login'] ?? '';
$username_value = $_SESSION['username_value'] ?? '';

// Mensajes de estado vía GET (ej: después de restablecer contraseña)
$msg_exito = '';
if (isset($_GET['msg']) && $_GET['msg'] === 'contraseña_actualizada') {
    $msg_exito = 'Tu contraseña ha sido actualizada correctamente. Ya puedes iniciar sesión.';
}

// Limpiar errores de la sesión después de obtenerlos
unset($_SESSION['error_usuario']);
unset($_SESSION['error_password']);
unset($_SESSION['error_login']);
unset($_SESSION['username_value']);

require 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = $_POST['usuario'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("
        SELECT u.id_usuario, u.nombre, u.usuario, u.password, c.nombre_cargo AS cargo
        FROM usuarios u
        JOIN cargo c ON u.id_cargo = c.id_cargo
        WHERE u.usuario = ?
    ");
    $stmt->execute([$usuario]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['usuario_id'] = $user['id_usuario'];
        $_SESSION['nombre'] = $user['nombre'];
        $_SESSION['id_cargo'] = $user['id_cargo'];

        // Mapear cargo por id_cargo (más confiable que string matching)
        $cargo_map = [
            1 => 'administrador',
            2 => 'administrativo',
            3 => 'auxiliar',
            4 => 'coordinador',
            5 => 'gerente'  // ID 5 = Gerente (aunque en la BD se llame "Gerencia")
        ];

        $cargo_normalizado = $cargo_map[$user['id_cargo']] ?? 'auxiliar';
        $_SESSION['cargo'] = $cargo_normalizado;

        // Redirigir según el cargo
        switch($cargo_normalizado) {
            case 'administrador':
                header('Location: admin_inicio.php');
                break;
            case 'gerente':
                header('Location: gerente_inicio.php');
                break;
            case 'coordinador':
                header('Location: coordinador_inicio.php');
                break;
            case 'auxiliar':
                header('Location: auxiliar_inicio.php');
                break;
            case 'administrativo':
                header('Location: administrativo_inicio.php');
                break;
            default:
                header('Location: auxiliar_inicio.php');
        }
        exit();
    } else {
        header('Location: login.php?mensaje=Credenciales incorrectas.');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Iniciar Sesión</title>
  <style>
    /* Estilos generales */
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
    .login-container {
      background-color: #fff;
      border: 1px solid #f5c6a5;
      border-radius: 8px;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
      padding: 30px; /* Aumentado de 20px a 30px */
      width: 380px; /* Aumentado de 300px a 380px */
      text-align: center;
      max-width: 90%; /* Para responsividad en móviles */
    }
    .login-container img {
      width: 100px;
      margin-bottom: 20px;
    }
    .login-container h1 {
      font-size: 24px;
      color: #d35400;
      margin-bottom: 20px;
    }
    .form-group {
      position: relative;
      margin-bottom: 4px; /* Reducido de 8px a 4px */
      text-align: left;
    }
    .form-group input {
      width: 100%;
      padding: 10px; /* Aumentado de 8px a 10px */
      border: 1px solid #ddd;
      border-radius: 4px;
      font-size: 14px;
      box-sizing: border-box;
      transition: border-color 0.3s;
    }
    .form-group input.error {
      border-color: #e74c3c;
      background-color: #fdf2f2;
    }
    .form-group input:focus {
      outline: none;
      border-color: #3498db;
    }
    .form-group input.error:focus {
      border-color: #e74c3c;
    }
    .error-message {
      color: #e74c3c;
      font-size: 11px;
      margin-top: 2px;
      margin-bottom: 3px; /* Añadido margen inferior pequeño */
      display: block;
      text-align: left;
      min-height: 12px; /* Reducido de 14px a 12px */
      line-height: 1.2;
    }
    .general-error {
      color: #e74c3c;
      font-size: 12px;
      margin-bottom: 15px; /* Aumentado de 10px a 15px */
      text-align: center;
      background-color: #fdf2f2;
      padding: 10px; /* Aumentado de 8px a 10px */
      border-radius: 4px;
      border: 1px solid #e74c3c;
    }
    .password-container {
      position: relative;
      width: 100%;
    }
    .password-container input {
      padding-right: 40px; /* Aumentado de 35px a 40px */
    }
    .password-container .toggle-password {
      position: absolute;
      top: 50%;
      right: 10px; /* Aumentado de 8px a 10px */
      transform: translateY(-50%);
      cursor: pointer;
      font-size: 16px; /* Aumentado de 14px a 16px */
      color: #e67e22;
    }
    .password-container .toggle-password:hover {
      color: #d35400;
    }
    .password-container .toggle-password svg {
      width: 20px; /* Aumentado de 18px a 20px */
      height: 20px;
    }
    .login-container button {
      background-color: #f39c12;
      color: #fff;
      border: none;
      padding: 12px; /* Aumentado de 8px a 12px */
      width: 100%;
      border-radius: 4px;
      cursor: pointer;
      font-size: 16px; /* Aumentado de 15px a 16px */
      transition: background 0.3s ease;
      margin-top: 10px; /* Aumentado de 5px a 10px */
    }
    .login-container button:hover {
      background-color: #e67e22;
    }
    .links {
      margin-top: 15px; /* Aumentado de 10px a 15px */
      display: flex;
      flex-direction: column;
      gap: 8px; /* Aumentado de 5px a 8px */
    }
    .links a {
      color: #e67e22;
      font-size: 14px; /* Aumentado de 13px a 14px */
      text-decoration: none;
    }
    .links a:hover {
      text-decoration: underline;
    }
    
    /* Responsividad para móviles */
    @media (max-width: 480px) {
      .login-container {
        width: 320px;
        padding: 20px;
      }
    }
  </style>
</head>
<body>
<div class="login-container">
    <img src="assets/img/logo.jpg" alt="Logo">
    <h1>Iniciar Sesión</h1>

    <!-- Mostrar mensaje de éxito si existe -->
    <?php if (!empty($msg_exito)): ?>
      <div class="general-error" style="background-color:#d4edda;border-color:#c3e6cb;color:#155724;"><?= htmlspecialchars($msg_exito) ?></div>
    <?php endif; ?>

    <!-- Mostrar error general si existe -->
    <?php if (!empty($error_general)): ?>
      <div class="general-error"><?= htmlspecialchars($error_general) ?></div>
    <?php endif; ?>

    <!-- Formulario de inicio de sesión -->
    <form action="procesar_login.php" method="POST">
      <!-- Campo Usuario/Correo -->
      <div class="form-group">
        <input 
          type="text" 
          name="username" 
          placeholder="Correo o usuario" 
          value="<?= htmlspecialchars($username_value) ?>"
          class="<?= !empty($error_usuario) ? 'error' : '' ?>"
          required
        >
        <span class="error-message"><?= htmlspecialchars($error_usuario) ?></span>
      </div>

      <!-- Campo Contraseña -->
      <div class="form-group">
        <div class="password-container">
          <input 
            type="password" 
            name="password" 
            id="password" 
            placeholder="Contraseña" 
            class="<?= !empty($error_password) ? 'error' : '' ?>"
            required
          >
          <span class="toggle-password" onclick="togglePassword()" title="Mostrar/Ocultar contraseña">
            <svg xmlns="http://www.w3.org/2000/svg" class="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
              <circle cx="12" cy="12" r="3"/>
            </svg>
          </span>
        </div>
        <span class="error-message"><?= htmlspecialchars($error_password) ?></span>
      </div>

      <button type="submit">Entrar</button>
    </form>

    <!-- Enlaces adicionales -->
    <div class="links"> 
      <a href="olvidar_contraseña.php">¿Olvidaste tu contraseña?</a>
      <a href="registro.php">Crear cuenta nueva</a>
    </div>
</div>

<script>
  // Función para mostrar/ocultar la contraseña
  function togglePassword() {
    const passwordInput = document.getElementById('password');
    const toggleIcon = document.querySelector('.toggle-password svg');
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

  // Limpiar mensajes de error cuando el usuario empiece a escribir
  document.addEventListener('DOMContentLoaded', function() {
    const usernameInput = document.querySelector('input[name="username"]');
    const passwordInput = document.querySelector('input[name="password"]');
    
    usernameInput.addEventListener('input', function() {
      this.classList.remove('error');
      const errorMsg = this.parentNode.querySelector('.error-message');
      if (errorMsg) errorMsg.textContent = '';
    });
    
    passwordInput.addEventListener('input', function() {
      this.classList.remove('error');
      const errorMsg = this.parentNode.querySelector('.error-message');
      if (errorMsg) errorMsg.textContent = '';
    });
  });
</script>
</body>
</html>
