<?php
session_start();
require 'conexion.php';

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['nombre'])) {
    header('Location: login.php?mensaje=Debes iniciar sesión para acceder a esta página.');
    exit();
}

// Verificar si el usuario tiene un cargo válido - EXPANDIR LA LISTA
$cargo = strtolower(trim($_SESSION['cargo'] ?? ''));
$cargos_validos = ['administrador', 'coordinador', 'auxiliar', 'administrativo', 'gerencia', 'gerente', 'admin'];

if (!in_array($cargo, $cargos_validos)) {
    // DEBUG: Log del cargo rechazado
    error_log("Cargo rechazado en ver_permisos.php: '$cargo' - Cargos válidos: " . implode(', ', $cargos_validos));
    header('Location: login.php?mensaje=No tienes permiso para acceder a esta página. Cargo: ' . $cargo);
    exit();
}

// Obtener el filtro de la URL
$filtro = $_GET['filtro'] ?? 'todos';
$estadosValidos = ['pendiente', 'aprobado', 'rechazado', 'reenviado', 'cancelado', 'finalizado', 'todos'];
if (!in_array($filtro, $estadosValidos)) {
    $filtro = 'todos';
}

// Consultar permisos con formato de tiempo actualizado
try {
    $stmt = $pdo->prepare("
        SELECT p.*,
               TO_CHAR(p.fecha_salida, 'DD/MM/YYYY') AS fecha_salida_formato,
               TO_CHAR(p.hora_salida, 'HH24:MI') AS hora_salida_formato,
               TO_CHAR(p.fecha_regreso_aprox, 'DD/MM/YYYY') AS fecha_regreso_aprox_formato,
               TO_CHAR(p.hora_regreso_aprox, 'HH24:MI') AS hora_regreso_aprox_formato,
               TO_CHAR(p.fecha_regreso_real, 'DD/MM/YYYY') AS fecha_regreso_real_formato,
               TO_CHAR(p.hora_regreso_real, 'HH24:MI') AS hora_regreso_real_formato,
               TO_CHAR(p.tiempo_total_ausencia, 'HH24:MI:SS') AS tiempo_total_formateado,
               CASE
                   WHEN p.tiempo_total_ausencia IS NOT NULL THEN
                       EXTRACT(HOUR FROM p.tiempo_total_ausencia)::int || ' hrs, ' ||
                       EXTRACT(MINUTE FROM p.tiempo_total_ausencia)::int || ' min, ' ||
                       EXTRACT(SECOND FROM p.tiempo_total_ausencia)::int || ' seg'
                   ELSE NULL
               END AS tiempo_total_legible
        FROM permisos p
        WHERE p.id_usuario = :id_usuario
        ORDER BY p.id_permiso DESC
    ");
    $stmt->execute(['id_usuario' => $_SESSION['usuario_id']]);
    $permisos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $permisos = [];
    error_log("Error al obtener permisos: " . $e->getMessage());
}

// Determinar el archivo del panel según el cargo del usuario
$archivoPanel = match (strtolower(trim($_SESSION['cargo'] ?? ''))) {
    'administrador' => 'admin_inicio.php',
    'coordinador' => 'coordinador_inicio.php',
    'auxiliar' => 'auxiliar_inicio.php',
    'administrativo' => 'administrativo_inicio.php',
    'gerencia', 'gerente' => 'gerente_inicio.php',
    default => 'login.php',
};
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Panel de Permisos</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      margin: 0;
      padding: 0;
      display: flex;
      height: 100vh;
    }
    .sidebar {
      width: 250px;
      background-color: #2c2f33; /* Fondo gris oscuro */
      color: #fff;
      display: flex;
      flex-direction: column;
      padding: 20px;
      box-shadow: 2px 0 5px rgba(0, 0, 0, 0.2);
    }
    .sidebar h2 {
      margin: 0 0 20px;
      font-size: 20px;
      text-align: left;
    }
    .sidebar a {
      color: #fff;
      text-decoration: none;
      padding: 10px 15px;
      margin-bottom: 10px;
      border-radius: 5px;
      transition: background-color 0.3s;
      display: block;
    }
    .sidebar a:hover {
      background-color: #444; /* Hover gris más claro */
    }
    .sidebar a.activo {
      background-color: #007bff; /* Azul para el activo */
    }
    .content {
      flex: 1;
      padding: 20px;
      overflow-y: auto;
      background-color: #f4f4f9; /* Fondo clarito */
    }
    .card {
      background-color: #fff;
      border-radius: 8px;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
      padding: 20px;
      margin-bottom: 20px;
    }
    .card h1 {
      font-size: 18px;
      margin-bottom: 10px;
    }
    .card p {
      margin: 5px 0;
      font-size: 14px;
    }
    .actions {
      margin-top: 15px;
    }
    .btn {
      padding: 10px 15px;
      color: #fff;
      text-decoration: none;
      border-radius: 4px;
      margin-right: 5px;
      display: inline-block;
    }
    .btn-view {
      background-color: #007bff;
    }
    .btn-view:hover {
      background-color: #0056b3;
    }
    .info-finalizacion {
      background-color: #e8f5e8;
      padding: 15px;
      border-radius: 8px;
      margin-top: 15px;
      border-left: 4px solid #28a745;
    }
    .info-finalizacion h4 {
      margin: 0 0 10px 0;
      color: #155724;
    }
    .tiempo-total {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 8px;
      margin-top: 10px;
      padding: 12px;
      background-color: #f8f9fa;
      border-radius: 6px;
    }
    .tiempo-digital {
      font-family: 'Courier New', monospace;
      font-size: 24px;
      font-weight: bold;
      color: #495057;
      background-color: #e9ecef;
      padding: 8px 12px;
      border-radius: 4px;
      letter-spacing: 2px;
    }
    .tiempo-texto {
      font-size: 14px;
      color: #6c757d;
      font-style: italic;
    }
  </style>
</head>
<body>
  <!-- Panel lateral -->
  <div class="sidebar">
    <h2>Panel de Permisos</h2>
    <a href="?filtro=todos" class="<?= $filtro === 'todos' ? 'activo' : '' ?>">Todos</a>
    <a href="?filtro=pendiente" class="<?= $filtro === 'pendiente' ? 'activo' : '' ?>">Pendientes</a>
    <a href="?filtro=aprobado" class="<?= $filtro === 'aprobado' ? 'activo' : '' ?>">Aprobados</a>
    <a href="?filtro=rechazado" class="<?= $filtro === 'rechazado' ? 'activo' : '' ?>">Rechazados</a>
    <a href="?filtro=reenviado" class="<?= $filtro === 'reenviado' ? 'activo' : '' ?>">Reenviados</a>
    <a href="?filtro=cancelado" class="<?= $filtro === 'cancelado' ? 'activo' : '' ?>">Cancelados</a>
    <a href="?filtro=finalizado" class="<?= $filtro === 'finalizado' ? 'activo' : '' ?>">Finalizado</a>

    <a href="<?= $archivoPanel ?>" class="btn btn-close">Volver al Panel</a>
  </div>

  <!-- Contenido principal -->
  <div class="content">
    <h1>Solicitudes de Permisos - <?= ucfirst($filtro) ?></h1>

    <!-- Widget de recuperación (módulo independiente) -->
    <?php include 'widget_recuperacion.php'; ?>

    <?php if (!in_array($cargo, ['gerente','gerencia'])): ?>
      <a href="crear_recuperacion.php" class="btn btn-view" style="background-color:#17a2b8;padding:8px 12px;border-radius:6px;color:#fff;margin-bottom:10px;display:inline-block;">Solicitar Recuperación</a>
    <?php endif; ?>

    <?php foreach ($permisos as $permiso): ?>
      <?php if ($filtro === 'todos' || $permiso['estado'] === $filtro): ?>
        <div class="card">
          <h1>ID Permiso: <?= htmlspecialchars($permiso['id_permiso']) ?></h1>
          <p><strong>Tipo de Permiso:</strong> <?= htmlspecialchars($permiso['tipo_permiso']) ?></p>
          <p><strong>Motivo:</strong> <?= htmlspecialchars($permiso['motivo']) ?></p>
          <p><strong>Salida:</strong> <?= $permiso['fecha_salida_formato'] ?> a las <?= $permiso['hora_salida_formato'] ?></p>
          <p><strong>Regreso aproximado:</strong> <?= $permiso['fecha_regreso_aprox_formato'] ?> a las <?= $permiso['hora_regreso_aprox_formato'] ?></p>
          <?php if ($permiso['encargado_ausencia']): ?>
            <p><strong>Persona encargada:</strong> <?= htmlspecialchars($permiso['encargado_ausencia']) ?></p>
          <?php endif; ?>
          <?php if ($permiso['estado'] === 'finalizado' && $permiso['fecha_regreso_real']): ?>
            <div class="info-finalizacion">
              <h4>📊 Información de Finalización</h4>
              <p><strong>Regreso real:</strong> <?= $permiso['fecha_regreso_real_formato'] ?> a las <?= $permiso['hora_regreso_real_formato'] ?></p>
              <?php if ($permiso['tiempo_total_ausencia']): ?>
                <p><strong>⏱️ Tiempo total de ausencia:</strong></p>
                <div class="tiempo-total">
                  <span class="tiempo-digital"><?= $permiso['tiempo_total_formateado'] ?></span>
                  <span class="tiempo-texto"><?= $permiso['tiempo_total_legible'] ?></span>
                </div>
              <?php endif; ?>
            </div>
          <?php endif; ?>
          <?php if ($permiso['motivo_rechazo']): ?>
            <div class="motivo-rechazo">
              <strong>Motivo del rechazo:</strong>
              <p><?= htmlspecialchars($permiso['motivo_rechazo']) ?></p>
            </div>
          <?php endif; ?>
          <div class="actions">
            <a href="ver_detalles.php?id=<?= $permiso['id_permiso'] ?>" class="btn btn-view">Ver</a>
          </div>
        </div>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
</body>
</html>
