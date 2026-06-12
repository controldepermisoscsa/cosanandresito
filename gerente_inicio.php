<?php
session_start();
require 'conexion.php';

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['nombre'])) {
    header('Location: login.php?mensaje=Debes iniciar sesión para acceder a esta página.');
    exit();
}

// Verificar si el usuario tiene el cargo de gerente
$cargo = strtolower($_SESSION['cargo'] ?? '');
if (!in_array($cargo, ['gerente', 'gerencia'])) {
    header('Location: login.php?mensaje=No tienes permiso para acceder a esta página.');
    exit();
}

// Obtener el filtro de la URL
$filtro = $_GET['filtro'] ?? 'asignados';
$estadosValidos = ['asignados', 'pendiente', 'aprobado', 'rechazado', 'reenviado', 'todos', 'cancelados', 'finalizado'];
if (!in_array($filtro, $estadosValidos)) {
    $filtro = 'asignados';
}

$id_gerente = $_SESSION['usuario_id'];

// Consulta según el filtro
if ($filtro === 'asignados') {
    // Solo permisos asignados al gerente actual - CORREGIDO
    $stmtPermisos = $pdo->prepare("
        SELECT p.id_permiso, u.nombre AS nombre_empleado, c.nombre_cargo AS cargo, u.area, p.estado,
               p.asignado_a, p.id_asignado
        FROM permisos p
        JOIN usuarios u ON p.id_usuario = u.id_usuario
        JOIN cargo c ON u.id_cargo = c.id_cargo
        WHERE (p.asignado_a = 'gerente') 
        AND (p.id_asignado = ? OR p.id_asignado IS NULL)
        ORDER BY p.fecha_salida DESC
    ");
    $stmtPermisos->execute([$id_gerente]);
} elseif ($filtro === 'todos') {
    // CORREGIDO: Solo mostrar permisos que han llegado al gerente o que el gerente ha procesado
    $stmtPermisos = $pdo->prepare("
        SELECT p.id_permiso, u.nombre AS nombre_empleado, c.nombre_cargo AS cargo, u.area, p.estado,
               p.asignado_a, p.id_asignado
        FROM permisos p
        JOIN usuarios u ON p.id_usuario = u.id_usuario
        JOIN cargo c ON u.id_cargo = c.id_cargo
        WHERE (
            -- Permisos que están asignados al gerente actualmente
            ((p.asignado_a = 'gerente') AND (p.id_asignado = ? OR p.id_asignado IS NULL))
            OR 
            -- Permisos que el gerente ya procesó (aprobados)
            p.estado = 'aprobado'
            OR
            -- Permisos finalizados (completados)
            p.estado = 'finalizado'
            OR
            -- Permisos que el gerente rechazó y están en el flujo de vuelta
            (p.estado = 'rechazado' AND p.asignado_a IN ('coordinador', 'auxiliar'))
            OR
            -- Permisos reenviados que van hacia el gerente
            (p.estado = 'reenviado' AND (p.asignado_a = 'gerente'))
            OR
            -- Permisos cancelados por el gerente
            p.estado = 'cancelado'
        )
        ORDER BY p.fecha_salida DESC
    ");
    $stmtPermisos->execute([$id_gerente]);
} elseif ($filtro === 'rechazado') {
    // Solo rechazos que pasaron por el gerente
    $stmtPermisos = $pdo->prepare("
        SELECT p.id_permiso, u.nombre AS nombre_empleado, c.nombre_cargo AS cargo, u.area, p.estado
        FROM permisos p
        JOIN usuarios u ON p.id_usuario = u.id_usuario
        JOIN cargo c ON u.id_cargo = c.id_cargo
        WHERE p.estado = 'rechazado' AND p.motivo_rechazo IS NOT NULL
        ORDER BY p.fecha_salida DESC
    ");
    $stmtPermisos->execute();
} elseif ($filtro === 'reenviado') {
    // Solo reenviados que van al gerente o que el gerente procesó
    $stmtPermisos = $pdo->prepare("
        SELECT p.id_permiso, u.nombre AS nombre_empleado, c.nombre_cargo AS cargo, u.area, p.estado
        FROM permisos p
        JOIN usuarios u ON p.id_usuario = u.id_usuario
        JOIN cargo c ON u.id_cargo = c.id_cargo
        WHERE p.estado = 'reenviado' AND (
            p.asignado_a = 'gerente' OR p.id_asignado = ?
        )
        ORDER BY p.fecha_salida DESC
    ");
    $stmtPermisos->execute([$id_gerente]);
} elseif ($filtro === 'cancelados') {
    // Solo cancelados (sin restricciones porque son definitivos)
    $stmtPermisos = $pdo->prepare("
        SELECT p.id_permiso, u.nombre AS nombre_empleado, c.nombre_cargo AS cargo, u.area, p.estado
        FROM permisos p
        JOIN usuarios u ON p.id_usuario = u.id_usuario
        JOIN cargo c ON u.id_cargo = c.id_cargo
        WHERE p.estado = 'cancelado'
        ORDER BY p.fecha_salida DESC
    ");
    $stmtPermisos->execute();
} elseif ($filtro === 'pendiente') {
    // Solo pendientes asignados al gerente
    $stmtPermisos = $pdo->prepare("
        SELECT p.id_permiso, u.nombre AS nombre_empleado, c.nombre_cargo AS cargo, u.area, p.estado
        FROM permisos p
        JOIN usuarios u ON p.id_usuario = u.id_usuario
        JOIN cargo c ON u.id_cargo = c.id_cargo
        WHERE p.estado = 'pendiente' AND p.asignado_a = 'gerente'
        ORDER BY p.fecha_salida DESC
    ");
    $stmtPermisos->execute();
} elseif ($filtro === 'aprobado') {
    // Solo aprobados por el gerente
    $stmtPermisos = $pdo->prepare("
        SELECT p.id_permiso, u.nombre AS nombre_empleado, c.nombre_cargo AS cargo, u.area, p.estado
        FROM permisos p
        JOIN usuarios u ON p.id_usuario = u.id_usuario
        JOIN cargo c ON u.id_cargo = c.id_cargo
        WHERE p.estado = 'aprobado'
        ORDER BY p.fecha_salida DESC
    ");
    $stmtPermisos->execute();
} elseif ($filtro === 'finalizado') {
    // Solo finalizados
    $stmtPermisos = $pdo->prepare("
        SELECT p.id_permiso, u.nombre AS nombre_empleado, c.nombre_cargo AS cargo, u.area, p.estado
        FROM permisos p
        JOIN usuarios u ON p.id_usuario = u.id_usuario
        JOIN cargo c ON u.id_cargo = c.id_cargo
        WHERE p.estado = 'finalizado'
        ORDER BY p.fecha_salida DESC
    ");
    $stmtPermisos->execute();
} else {
    // Filtro genérico con restricción
    $stmtPermisos = $pdo->prepare("
        SELECT p.id_permiso, u.nombre AS nombre_empleado, c.nombre_cargo AS cargo, u.area, p.estado
        FROM permisos p
        JOIN usuarios u ON p.id_usuario = u.id_usuario
        JOIN cargo c ON u.id_cargo = c.id_cargo
        WHERE p.estado = ? AND (
            p.asignado_a = 'gerente' OR 
            p.estado IN ('aprobado', 'cancelado') OR
            (p.estado = 'rechazado' AND p.motivo_rechazo IS NOT NULL)
        )
        ORDER BY p.fecha_salida DESC
    ");
    $stmtPermisos->execute([$filtro]);
}

// Mensaje de éxito después de aprobar o rechazar
$mensaje = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Panel de Gerente</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      margin: 0;
      padding: 0;
      display: flex;
    }
    .sidebar {
      width: 250px;
      background-color: #343a40;
      color: #fff;
      height: 100vh;
      padding: 20px;
      box-sizing: border-box;
    }
    .sidebar h2 {
      color: #fff;
      text-align: center;
      margin-bottom: 20px;
    }
    .sidebar a {
      display: block;
      color: #fff;
      text-decoration: none;
      padding: 10px 15px;
      margin-bottom: 10px;
      border-radius: 5px;
      transition: background-color 0.3s;
    }
    .sidebar a:hover {
      background-color: #495057;
    }
    .sidebar .activo {
      background-color: #f39c12;
    }
    .content {
      flex: 1;
      padding: 20px;
      box-sizing: border-box;
    }
    h1 {
      color: #333;
      text-align: center;
    }
    .mensaje {
      background-color: #d4edda;
      color: #155724;
      padding: 10px;
      border: 1px solid #c3e6cb;
      border-radius: 4px;
      margin-bottom: 15px;
      text-align: center;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 20px;
    }
    table th, table td {
      border: 1px solid #ddd;
      padding: 8px;
      text-align: left;
    }
    table th {
      background-color: #f4f4f4;
    }
    .btn {
      padding: 5px 10px;
      color: #fff;
      text-decoration: none;
      border-radius: 4px;
      margin-right: 5px;
    }
    .btn-approve {
      background-color: #f39c12;
    }
    .btn-approve:hover {
      background-color: #e67e22;
    }
    .btn-reject {
      background-color: #f39c12;
    }
    .btn-reject:hover {
      background-color: #e67e22;
    }
  </style>
</head>
<body>
  <!-- Panel lateral -->
  <div class="sidebar">
    <h2>Panel de Gerente</h2>
    <a href="?filtro=asignados" class="<?= $filtro === 'asignados' ? 'activo' : '' ?>">Asignados a mí</a>
    <a href="?filtro=todos" class="<?= $filtro === 'todos' ? 'activo' : '' ?>">Todos</a>
    <a href="?filtro=pendiente" class="<?= $filtro === 'pendiente' ? 'activo' : '' ?>">Pendientes</a>
    <a href="?filtro=aprobado" class="<?= $filtro === 'aprobado' ? 'activo' : '' ?>">Aprobados</a>
    <a href="?filtro=finalizado" class="<?= $filtro === 'finalizado' ? 'activo' : '' ?>">Finalizados</a>
    <a href="?filtro=rechazado" class="<?= $filtro === 'rechazado' ? 'activo' : '' ?>">Rechazados</a>
    <a href="?filtro=reenviado" class="<?= $filtro === 'reenviado' ? 'activo' : '' ?>">Reenviados</a>
    <a href="?filtro=cancelados" class="<?= $filtro === 'cancelados' ? 'activo' : '' ?>">Cancelados</a>
    <a href="estadisticas_completas.php">Ver Estadísticas</a>
    <a href="logout.php">Cerrar Sesión</a>
  </div>

  <!-- Contenido principal -->
  <div class="content">
    <h1>Solicitudes de Permisos - <?= ucfirst($filtro) ?></h1>

    <!-- Mostrar mensaje de éxito si existe -->
    <?php if (!empty($mensaje)): ?>
      <div class="mensaje"><?= htmlspecialchars($mensaje); ?></div>
    <?php endif; ?>

    <table>
      <thead>
        <tr>
          <th>ID Permiso</th>
          <th>Nombre del Empleado</th>
          <th>Cargo</th>
          <th>Área</th>
          <th>Estado</th>
          <th>Acción</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($permiso = $stmtPermisos->fetch(PDO::FETCH_ASSOC)): ?>
          <tr>
            <td><?= htmlspecialchars($permiso['id_permiso']) ?></td>
            <td><?= htmlspecialchars($permiso['nombre_empleado']) ?></td>
            <td><?= htmlspecialchars($permiso['cargo']) ?></td>
            <td><?= htmlspecialchars($permiso['area']) ?></td>
            <td><?= ucfirst($permiso['estado']) ?></td>
            <td>
              <a href="ver_solicitud.php?id=<?= $permiso['id_permiso'] ?>" class="btn btn-approve">Ver</a>
                </small>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</body>
</html>