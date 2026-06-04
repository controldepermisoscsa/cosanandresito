<?php
session_start();
require 'conexion.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['cargo'] !== 'Auxiliar') {
    header('Location: login.php');
    exit();
}

$tiempo = isset($_GET['tiempo']) ? intval($_GET['tiempo']) : 0;
$horas = floor($tiempo / 60);
$minutos = $tiempo % 60;
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Formulario de Recuperación</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background-color: #f4f4f9;
      margin: 0;
      padding: 0;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
    }
    .container {
      background-color: #fff;
      padding: 20px;
      border-radius: 8px;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
      width: 400px;
      text-align: center;
    }
    .container h1 {
      font-size: 20px;
      margin-bottom: 20px;
    }
    .container p {
      margin-bottom: 20px;
    }
    .container button {
      background-color: #28a745;
      color: #fff;
      border: none;
      padding: 10px 20px;
      border-radius: 4px;
      cursor: pointer;
      font-size: 16px;
    }
    .container button:hover {
      background-color: #218838;
    }
  </style>
</head>
<body>
  <div class="container">
    <h1>Tiempo de Recuperación</h1>
    <p>Estarás fuera por <strong><?php echo $horas; ?> horas y <?php echo $minutos; ?> minutos</strong>.</p>
    <form action="procesar_recuperacion.php" method="POST">
      <input type="hidden" name="tiempo" value="<?php echo $tiempo; ?>">
      <label for="fecha_recuperacion">Fecha y hora de recuperación:</label>
      <input type="datetime-local" name="fecha_recuperacion" id="fecha_recuperacion" required>
      <button type="submit">Registrar Recuperación</button>
    </form>
  </div>
</body>
</html>