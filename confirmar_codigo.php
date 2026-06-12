<?php
session_start();

// Si no se envió antes el código, volver al inicio
if (!isset($_SESSION['codigo_recuperacion']) || !isset($_SESSION['correo_recuperacion'])) {
    header("Location: enviar_codigo.php");
    exit;
}

// Verificar que el código no haya expirado (15 minutos)
if (!isset($_SESSION['tiempo_codigo']) || (time() - $_SESSION['tiempo_codigo']) > 900) {
    unset($_SESSION['codigo_recuperacion'], $_SESSION['correo_recuperacion'], $_SESSION['tiempo_codigo']);
    header("Location: enviar_codigo.php?error=codigo_expirado");
    exit;
}

$codigo_enviado = $_SESSION['codigo_recuperacion'];
$correo = $_SESSION['correo_recuperacion'];
$error = "";

// Si se envió el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo_ingresado = trim($_POST['codigo']);

    if ($codigo_ingresado == $codigo_enviado) {
        // Marcar que el código fue verificado y limpiar el código de sesión
        $_SESSION['codigo_verificado'] = true;
        unset($_SESSION['codigo_recuperacion'], $_SESSION['tiempo_codigo']);
        header("Location: nueva_contraseña.php");
        exit;
    } else {
        $error = "El código ingresado es incorrecto.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Confirmar Código</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #fdf3e6;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .container {
            background: #fff;
            border: 1px solid #f5c6a5;
            border-radius: 8px;
            padding: 20px;
            width: 320px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            text-align: center;
        }
        h1 {
            color: #d35400;
            margin-bottom: 20px;
        }
        input[type="text"] {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-align: center;
            font-size: 18px;
            letter-spacing: 3px;
        }
        button {
            background-color: #f39c12;
            color: #fff;
            border: none;
            padding: 10px;
            width: 100%;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
        }
        button:hover {
            background-color: #e67e22;
        }
        .error {
            color: red;
            margin-top: 10px;
        }
        a {
            display: block;
            margin-top: 15px;
            color: #d35400;
            text-decoration: none;
        }
        a:hover {
            color: #e67e22;
            text-decoration: underline;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Confirmar Código</h1>
    <p>Ingresa el código que enviamos a <b><?php echo htmlspecialchars($correo); ?></b></p>
    <form method="POST">
        <input type="text" name="codigo" placeholder="Código de 6 dígitos" required>
        <button type="submit">Confirmar</button>
    </form>
    <?php if (!empty($error)) echo "<p class='error'>$error</p>"; ?>
    <a href="enviar_codigo.php">Reenviar código</a>
</div>
</body>
</html>
