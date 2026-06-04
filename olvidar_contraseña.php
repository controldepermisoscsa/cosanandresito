<?php
session_start();
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Olvidar Contraseña</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background-color: #fdf3e6; /* Fondo naranja claro */
      margin: 0;
      padding: 0;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
    }
    .container {
      background-color: #fff;
      border: 1px solid #f5c6a5;
      border-radius: 8px;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
      padding: 25px;
      width: 320px;
      text-align: center;
    }
    h2 {
      color: #d35400;
      margin-bottom: 15px;
    }
    p {
      color: #7f8c8d;
      font-size: 14px;
      margin-bottom: 20px;
    }
    input[type="email"] {
      width: 100%;
      padding: 10px;
      margin-bottom: 15px;
      border: 1px solid #ddd;
      border-radius: 4px;
      font-size: 14px;
    }
    button {
      background-color: #f39c12;
      color: #fff;
      border: none;
      padding: 10px;
      width: 100%;
      border-radius: 4px;
      cursor: pointer;
      font-size: 16px;
      transition: background 0.3s ease;
    }
    button:hover {
      background-color: #e67e22;
    }
    a {
      display: block;
      margin-top: 15px;
      color: #d35400;
      text-decoration: none;
      font-size: 14px;
      transition: color 0.3s ease;
    }
    a:hover {
      color: #e67e22;
      text-decoration: underline;
    }
  </style>
</head>
<body>
  <div class="container">
    <h2>Recuperar Contraseña</h2>
    <p>Ingresa tu correo electrónico y te enviaremos un código para restablecer tu contraseña.</p>
    <form action="enviar_codigo.php" method="POST">
      <input type="email" name="correo" placeholder="Correo electrónico" required>
      <button type="submit">Enviar Código</button>
    </form>
    <a href="login.php">Volver al inicio de sesión</a>
  </div>
</body>
</html>
