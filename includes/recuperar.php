<?php
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Recuperar Contraseña</title>
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
    .recuperar-container {
      background-color: #fff;
      border: 1px solid #f5c6a5;
      border-radius: 8px;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
      padding: 20px;
      width: 320px;
      text-align: center;
    }
    .recuperar-container h1 {
      font-size: 20px;
      color: #d35400;
      margin-bottom: 15px;
    }
    .recuperar-container p {
      font-size: 14px;
      color: #555;
      margin-bottom: 15px;
    }
    .recuperar-container input {
      width: 100%;
      padding: 10px;
      margin-bottom: 12px;
      border: 1px solid #ddd;
      border-radius: 4px;
      font-size: 14px;
    }
    .recuperar-container button {
      background-color: #f39c12;
      color: #fff;
      border: none;
      padding: 10px;
      width: 100%;
      border-radius: 4px;
      cursor: pointer;
      font-size: 16px;
    }
    .recuperar-container button:hover {
      background-color: #e67e22;
    }
    .recuperar-container a {
      display: block;
      margin-top: 10px;
      color: #d35400;
      text-decoration: none;
      font-size: 14px;
    }
    .recuperar-container a:hover {
      text-decoration: underline;
    }
  </style>
</head>
<body>
  <div class="recuperar-container">
    <h1>Recuperar Contraseña</h1>
    <p>Ingresa tu correo electrónico para enviarte un enlace o código de recuperación.</p>
    <form id="formRecuperar" action="#" method="POST">
      <input type="email" name="correo" placeholder="Correo electrónico" required>
      <button type="submit">Enviar enlace de recuperación</button>
    </form>
    <a href="login.php">← Volver al inicio de sesión</a>
  </div>

  <script>
    document.getElementById("formRecuperar").addEventListener("submit", function(event) {
      const correo = document.querySelector('input[name="correo"]').value;
      if (!correo.includes("@")) {
        alert("Por favor, ingresa un correo electrónico válido.");
        event.preventDefault();
      }
    });
  </script>
</body>
</html>
