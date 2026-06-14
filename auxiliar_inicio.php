<?php
session_start();
require 'conexion.php';

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php?mensaje=Debes iniciar sesión para acceder a esta página.');
    exit();
}

// Verificar si el usuario tiene el rol de Auxiliar
$cargo_usuario = strtolower($_SESSION['cargo'] ?? '');
if ($cargo_usuario !== 'auxiliar') {
    header('Location: login.php?mensaje=No tienes permiso para acceder a esta página.');
    exit();
}

// Consultar los permisos del auxiliar
$stmt = $pdo->prepare("
    SELECT *, asignado_a, id_asignado FROM permisos
    WHERE (asignado_a = 'auxiliar' AND (id_asignado = :id_auxiliar OR id_asignado IS NULL))
    OR id_usuario = :id_auxiliar_owner
    ORDER BY fecha_salida DESC
");
$stmt->execute([
    'id_auxiliar' => $_SESSION['usuario_id'],
    'id_auxiliar_owner' => $_SESSION['usuario_id']
]);
$permisos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular estadísticas
$total     = count($permisos);
$pendiente = count(array_filter($permisos, fn($p) => $p['estado'] === 'pendiente'));
$aprobado  = count(array_filter($permisos, fn($p) => $p['estado'] === 'aprobado'));
$rechazado = count(array_filter($permisos, fn($p) => $p['estado'] === 'rechazado'));
$recientes = array_slice($permisos, 0, 5);

// Fecha en español
$dias_es   = ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'];
$meses_es  = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
$hoy       = new DateTime();
$fecha_es  = ucfirst($dias_es[(int)$hoy->format('w')]) . ', ' . $hoy->format('d') . ' de ' . $meses_es[(int)$hoy->format('n') - 1] . ' de ' . $hoy->format('Y');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Panel de Auxiliar</title>
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'Segoe UI', Arial, sans-serif;
      display: flex;
      height: 100vh;
      background: #f0f2f5;
      overflow: hidden;
    }

    /* ── SIDEBAR ── */
    .sidebar {
      width: 240px;
      background: linear-gradient(180deg, #1a2535 0%, #2c3e50 100%);
      display: flex;
      flex-direction: column;
      flex-shrink: 0;
      box-shadow: 3px 0 15px rgba(0,0,0,0.3);
    }

    .sidebar-brand {
      padding: 24px 20px 20px;
      border-bottom: 1px solid rgba(255,255,255,0.08);
      text-align: center;
    }
    .sidebar-brand .brand-icon {
      width: 48px; height: 48px;
      background: linear-gradient(135deg, #f39c12, #e67e22);
      border-radius: 12px;
      display: flex; align-items: center; justify-content: center;
      font-size: 22px;
      margin: 0 auto 10px;
      box-shadow: 0 4px 12px rgba(243,156,18,0.4);
    }
    .sidebar-brand h2 {
      color: #fff;
      font-size: 14px;
      font-weight: 600;
      letter-spacing: 0.5px;
    }

    .sidebar-user {
      padding: 16px 20px;
      border-bottom: 1px solid rgba(255,255,255,0.08);
    }
    .sidebar-user .user-name {
      color: #fff;
      font-size: 13px;
      font-weight: 600;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .sidebar-user .user-role {
      color: #f39c12;
      font-size: 11px;
      margin-top: 2px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .sidebar-nav {
      flex: 1;
      padding: 16px 12px;
      display: flex;
      flex-direction: column;
      gap: 4px;
    }
    .nav-item {
      display: flex;
      align-items: center;
      gap: 12px;
      color: #adb5bd;
      text-decoration: none;
      padding: 11px 14px;
      border-radius: 10px;
      font-size: 14px;
      font-weight: 500;
      transition: all 0.2s;
    }
    .nav-item:hover {
      background: rgba(255,255,255,0.08);
      color: #fff;
    }
    .nav-item.activo {
      background: linear-gradient(135deg, #f39c12, #e67e22);
      color: #fff;
      box-shadow: 0 4px 12px rgba(243,156,18,0.35);
    }
    .nav-item .nav-icon { font-size: 18px; width: 22px; text-align: center; }

    .sidebar-logout {
      padding: 12px;
      border-top: 1px solid rgba(255,255,255,0.08);
    }
    .sidebar-logout a {
      display: flex;
      align-items: center;
      gap: 10px;
      color: #e74c3c;
      text-decoration: none;
      padding: 10px 14px;
      border-radius: 10px;
      font-size: 14px;
      font-weight: 500;
      transition: background 0.2s;
    }
    .sidebar-logout a:hover { background: rgba(231,76,60,0.15); }

    /* ── CONTENT ── */
    .content {
      flex: 1;
      overflow-y: auto;
      padding: 28px 32px;
    }

    .page-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 28px;
      flex-wrap: wrap;
      gap: 12px;
    }
    .page-header h1 {
      font-size: 24px;
      color: #1a2535;
      font-weight: 700;
    }
    .page-header .fecha {
      color: #6c757d;
      font-size: 13px;
    }

    .btn-primary {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: linear-gradient(135deg, #f39c12, #e67e22);
      color: #fff;
      text-decoration: none;
      padding: 11px 22px;
      border-radius: 10px;
      font-size: 14px;
      font-weight: 600;
      box-shadow: 0 4px 12px rgba(243,156,18,0.35);
      transition: all 0.2s;
    }
    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 18px rgba(243,156,18,0.45);
    }

    /* Stats cards */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 16px;
      margin-bottom: 28px;
    }
    .stat-card {
      background: #fff;
      border-radius: 14px;
      padding: 20px;
      display: flex;
      align-items: center;
      gap: 16px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.06);
      transition: transform 0.2s, box-shadow 0.2s;
    }
    .stat-card:hover { transform: translateY(-3px); box-shadow: 0 6px 18px rgba(0,0,0,0.1); }

    .stat-icon {
      width: 48px; height: 48px;
      border-radius: 12px;
      display: flex; align-items: center; justify-content: center;
      font-size: 22px;
      flex-shrink: 0;
    }
    .stat-card.total     .stat-icon { background: #ebf5fb; }
    .stat-card.pendiente .stat-icon { background: #fef9e7; }
    .stat-card.aprobado  .stat-icon { background: #eafaf1; }
    .stat-card.rechazado .stat-icon { background: #fdf2f8; }

    .stat-info .stat-number {
      font-size: 28px;
      font-weight: 700;
      color: #1a2535;
      line-height: 1;
    }
    .stat-info .stat-label {
      font-size: 12px;
      color: #6c757d;
      margin-top: 4px;
      font-weight: 500;
    }

    /* Table card */
    .table-card {
      background: #fff;
      border-radius: 14px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.06);
      overflow: hidden;
    }
    .table-card-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 18px 24px;
      border-bottom: 1px solid #f0f2f5;
    }
    .table-card-header h3 {
      font-size: 16px;
      color: #1a2535;
      font-weight: 700;
    }
    .table-card-header a {
      font-size: 13px;
      color: #f39c12;
      text-decoration: none;
      font-weight: 600;
    }
    .table-card-header a:hover { text-decoration: underline; }

    table {
      width: 100%;
      border-collapse: collapse;
    }
    thead th {
      background: #f8f9fa;
      padding: 12px 20px;
      font-size: 12px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: #6c757d;
      font-weight: 600;
      text-align: left;
    }
    tbody tr {
      border-bottom: 1px solid #f0f2f5;
      transition: background 0.15s;
    }
    tbody tr:last-child { border-bottom: none; }
    tbody tr:hover { background: #fafbfc; }
    tbody td {
      padding: 14px 20px;
      font-size: 14px;
      color: #2c3e50;
    }

    .badge {
      display: inline-block;
      padding: 4px 10px;
      border-radius: 20px;
      font-size: 11px;
      font-weight: 600;
      text-transform: capitalize;
    }
    .badge-pendiente  { background: #fff3cd; color: #856404; }
    .badge-aprobado   { background: #d1e7dd; color: #0a3622; }
    .badge-rechazado  { background: #f8d7da; color: #842029; }
    .badge-reenviado  { background: #cff4fc; color: #055160; }
    .badge-cancelado  { background: #e2e3e5; color: #41464b; }
    .badge-finalizado { background: #d3d3d3; color: #383838; }

    .btn-ver {
      background: #f0f4ff;
      color: #3a5bd9;
      border: none;
      padding: 6px 14px;
      border-radius: 8px;
      font-size: 12px;
      font-weight: 600;
      text-decoration: none;
      transition: background 0.2s;
    }
    .btn-ver:hover { background: #dce5ff; }

    .empty-state {
      text-align: center;
      padding: 40px 20px;
      color: #adb5bd;
    }
    .empty-state .empty-icon { font-size: 40px; margin-bottom: 10px; }
    .empty-state p { font-size: 14px; }

    /* ── DATATABLES OVERRIDES ── */
    .dataTables_wrapper { font-family: 'Segoe UI', Arial, sans-serif; }
    .dt-top {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 14px 20px 10px;
      flex-wrap: wrap;
      gap: 10px;
    }
    .dt-bottom {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 12px 20px 16px;
      flex-wrap: wrap;
      gap: 8px;
      border-top: 1px solid #f0f2f5;
    }
    .dataTables_wrapper .dataTables_filter label,
    .dataTables_wrapper .dataTables_length label {
      font-size: 13px;
      color: #6c757d;
      font-weight: 500;
    }
    .dataTables_wrapper .dataTables_filter input {
      border: 1.5px solid #dee2e6;
      border-radius: 10px;
      padding: 7px 12px;
      font-size: 13px;
      color: #1a2535;
      margin-left: 8px;
      outline: none;
      font-family: inherit;
      transition: border-color 0.2s, box-shadow 0.2s;
    }
    .dataTables_wrapper .dataTables_filter input:focus {
      border-color: #f39c12;
      box-shadow: 0 0 0 3px rgba(243,156,18,0.12);
    }
    .dataTables_wrapper .dataTables_length select {
      border: 1.5px solid #dee2e6;
      border-radius: 8px;
      padding: 6px 10px;
      font-size: 13px;
      color: #1a2535;
      margin: 0 6px;
      outline: none;
      font-family: inherit;
      cursor: pointer;
    }
    .dataTables_wrapper .dataTables_info {
      font-size: 12px;
      color: #6c757d;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button {
      border-radius: 8px !important;
      font-size: 13px !important;
      padding: 5px 11px !important;
      color: #495057 !important;
      border: 1px solid transparent !important;
      background: none !important;
      box-shadow: none !important;
      transition: background 0.15s !important;
      cursor: pointer;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
      background: #f0f2f5 !important;
      color: #1a2535 !important;
      border-color: transparent !important;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button.current,
    .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
      background: linear-gradient(135deg, #f39c12, #e67e22) !important;
      color: #fff !important;
      border-color: transparent !important;
      box-shadow: 0 3px 8px rgba(243,156,18,0.35) !important;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button.disabled,
    .dataTables_wrapper .dataTables_paginate .paginate_button.disabled:hover {
      color: #adb5bd !important;
      cursor: default;
    }
    .dataTables_wrapper table.dataTable thead th {
      cursor: pointer;
    }
    .dataTables_wrapper table.dataTable thead .sorting::after,
    .dataTables_wrapper table.dataTable thead .sorting_asc::after,
    .dataTables_wrapper table.dataTable thead .sorting_desc::after {
      opacity: 0.5;
    }
    .dataTables_wrapper table.dataTable tbody tr.odd  { background: #fff; }
    .dataTables_wrapper table.dataTable tbody tr.even { background: #fafbfc; }
    .dataTables_wrapper table.dataTable.no-footer { border-bottom: none; }

    /* Mantener margin para widget */
    .content { margin-bottom: 0; }
    
    /* Estilos para el widget movible (idénticos al admin) */
    .widget-tiempo-ausencia {
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
        color: white;
        padding: 18px;
        border-radius: 15px;
        box-shadow: 0 8px 25px rgba(243, 156, 18, 0.4);
        min-width: 300px;
        max-width: 340px;
        z-index: 999;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        backdrop-filter: blur(10px);
        border: 2px solid rgba(255, 255, 255, 0.2);
        transition: all 0.3s ease;
        cursor: move;
        user-select: none;
    }
    
    .widget-tiempo-ausencia:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 30px rgba(243, 156, 18, 0.5);
    }
    
    .widget-tiempo-ausencia.dragging {
        transform: rotate(3deg) scale(1.05);
        box-shadow: 0 15px 40px rgba(243, 156, 18, 0.6);
        z-index: 1001;
    }
    
    .widget-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 12px;
        cursor: move;
        padding: 5px 0;
    }
    
    .widget-header h4 {
        margin: 0;
        font-size: 15px;
        font-weight: 700;
        color: white;
        text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
        flex-grow: 1;
        text-align: center;
    }
    
    .widget-controls {
        display: flex;
        gap: 5px;
    }
    
    .widget-btn {
        background: rgba(255, 255, 255, 0.2);
        border: none;
        color: white;
        width: 24px;
        height: 24px;
        border-radius: 50%;
        cursor: pointer;
        font-size: 12px;
        font-weight: bold;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .widget-btn:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: scale(1.1);
    }
    
    .tiempo-digital {
        font-family: 'Courier New', 'Monaco', monospace;
        font-size: 24px;
        font-weight: bold;
        text-align: center;
        margin: 15px 0;
        padding: 12px;
        background: rgba(255, 255, 255, 0.15);
        border-radius: 10px;
        letter-spacing: 1px;
        border: 1px solid rgba(255, 255, 255, 0.3);
        color: #fff;
        text-shadow: 1px 1px 3px rgba(0,0,0,0.5);
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 5px;
    }
    
    .tiempo-principal {
        font-size: 26px;
        line-height: 1;
    }
    
    .tiempo-info {
        font-size: 11px;
        opacity: 0.8;
        font-family: Arial, sans-serif;
        letter-spacing: 0px;
    }
    
    .widget-info {
        font-size: 12px;
        opacity: 0.95;
        margin-bottom: 15px;
        text-align: center;
        line-height: 1.4;
        color: #fff;
        text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
    }
    
    .btn-finalizar {
        background: linear-gradient(45deg, #e74c3c, #c0392b);
        color: white;
        border: none;
        padding: 12px 18px;
        border-radius: 10px;
        cursor: pointer;
        font-weight: 700;
        width: 100%;
        transition: all 0.3s ease;
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3);
    }
    
    .btn-finalizar:hover {
        background: linear-gradient(45deg, #c0392b, #a93226);
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(231, 76, 60, 0.4);
    }
    
    .btn-finalizar.confirmar {
        background: linear-gradient(45deg, #dc3545, #b02a37);
        animation: pulse 1.5s infinite;
    }
    
    .btn-cancelar {
        background: linear-gradient(45deg, #6c757d, #545b62);
        color: white;
        border: none;
        padding: 8px 15px;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 600;
        width: 100%;
        margin-top: 8px;
        transition: all 0.3s ease;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }
    
    .btn-cancelar:hover {
        background: linear-gradient(45deg, #545b62, #495057);
        transform: translateY(-1px);
    }
    
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); }
        100% { transform: scale(1); }
    }
    
    .widget-hidden {
        display: none !important;
    }
    
    .widget-minimized {
        min-width: 80px;
        padding: 10px;
        border-radius: 30px;
        cursor: move;
    }
    
    .widget-minimized .widget-info,
    .widget-minimized .btn-finalizar {
        display: none;
    }
    
    .widget-minimized .tiempo-digital {
        font-size: 16px;
        margin: 5px 0;
        padding: 8px;
    }
    
    .widget-minimized .tiempo-principal {
        font-size: 16px;
    }
    
    .widget-minimized .tiempo-info {
        font-size: 8px;
    }
    
    .widget-fuera-horario {
        opacity: 0.8;
        background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
    }
    
    .widget-fuera-horario .tiempo-digital {
        background: rgba(255, 255, 255, 0.1);
    }
    
    /* Posición guardada */
    .widget-position-indicator {
        position: absolute;
        top: -5px;
        left: -5px;
        width: 10px;
        height: 10px;
        background: rgba(255, 255, 255, 0.5);
        border-radius: 50%;
        opacity: 0;
        transition: opacity 0.3s;
    }
    
    .widget-tiempo-ausencia:hover .widget-position-indicator {
        opacity: 1;
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
        .widget-tiempo-ausencia {
            position: fixed;
            bottom: 10px;
            left: 10px;
            right: 10px;
            min-width: auto;
            width: calc(100% - 20px);
            max-width: none;
        }
        
        .widget-minimized {
            width: 100px;
            left: auto;
            right: 10px;
            bottom: 10px;
        }
    }
    
    /* Asegurar que no interfiera con otros elementos */
    .content {
        margin-bottom: 120px;
    }
    
    @media (max-width: 768px) {
        .content {
            margin-bottom: 140px;
        }
    }
  </style>
</head>
<body>
  <!-- SIDEBAR -->
  <div class="sidebar">
    <div class="sidebar-brand">
      <div class="brand-icon">📋</div>
      <h2>Coosanandresito</h2>
    </div>
    <div class="sidebar-user">
      <div class="user-name"><?= htmlspecialchars($_SESSION['nombre']) ?></div>
      <div class="user-role">Auxiliar</div>
    </div>
    <nav class="sidebar-nav">
      <a href="auxiliar_inicio.php" class="nav-item activo">
        <span class="nav-icon">🏠</span> Inicio
      </a>
      <a href="solicitar_permiso.php?nuevo=1" class="nav-item">
        <span class="nav-icon">📝</span> Solicitar Permiso
      </a>
      <a href="ver_permisos.php" class="nav-item">
        <span class="nav-icon">📂</span> Mis Permisos
      </a>
      <a href="recuperar_tiempo.php" class="nav-item">
        <span class="nav-icon">⏱️</span> Recuperar Tiempo
      </a>
    </nav>
    <div class="sidebar-logout">
      <a href="logout.php">
        <span style="font-size:16px;">🚪</span> Cerrar Sesión
      </a>
    </div>
  </div>

  <!-- CONTENIDO -->
  <div class="content">

    <div class="page-header">
      <div>
        <h1>Bienvenido, <?= htmlspecialchars(explode(' ', $_SESSION['nombre'])[0]) ?> 👋</h1>
        <span class="fecha"><?= $fecha_es ?></span>
      </div>
      <a href="solicitar_permiso.php?nuevo=1" class="btn-primary">
        <span>＋</span> Nuevo Permiso
      </a>
    </div>

    <!-- TARJETAS DE ESTADÍSTICAS -->
    <div class="stats-grid">
      <div class="stat-card total">
        <div class="stat-icon">📋</div>
        <div class="stat-info">
          <div class="stat-number"><?= $total ?></div>
          <div class="stat-label">Total Solicitudes</div>
        </div>
      </div>
      <div class="stat-card pendiente">
        <div class="stat-icon">⏳</div>
        <div class="stat-info">
          <div class="stat-number"><?= $pendiente ?></div>
          <div class="stat-label">Pendientes</div>
        </div>
      </div>
      <div class="stat-card aprobado">
        <div class="stat-icon">✅</div>
        <div class="stat-info">
          <div class="stat-number"><?= $aprobado ?></div>
          <div class="stat-label">Aprobados</div>
        </div>
      </div>
      <div class="stat-card rechazado">
        <div class="stat-icon">❌</div>
        <div class="stat-info">
          <div class="stat-number"><?= $rechazado ?></div>
          <div class="stat-label">Rechazados</div>
        </div>
      </div>
    </div>

    <!-- TABLA DE SOLICITUDES (DataTables) -->
    <div class="table-card">
      <div class="table-card-header" style="justify-content:center;">
        <h3>Mis Solicitudes de Permiso</h3>
      </div>
      <?php if (empty($permisos)): ?>
        <div class="empty-state">
          <div class="empty-icon">📭</div>
          <p>Aún no tienes solicitudes de permiso.</p>
        </div>
      <?php else: ?>
      <table id="tablaPermisos" style="width:100%">
        <thead>
          <tr>
            <th>#</th>
            <th>Tipo</th>
            <th>Fecha Salida</th>
            <th>Motivo</th>
            <th>Estado</th>
            <th>Acción</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($permisos as $p): ?>
          <tr>
            <td><?= $p['id_permiso'] ?></td>
            <td><?= htmlspecialchars($p['tipo_permiso'] ?? '—') ?></td>
            <td data-order="<?= $p['fecha_salida'] ?? '' ?>">
              <?php if (isset($p['fecha_salida'])): ?>
                <?= date('d/m/Y', strtotime($p['fecha_salida'])) ?>
                <?php if (!empty($p['hora_salida'])): ?>
                  <span style="color:#6c757d;font-size:12px;display:block;"><?= date('g:i A', strtotime($p['hora_salida'])) ?></span>
                <?php endif; ?>
              <?php else: ?>—<?php endif; ?>
            </td>
            <td><?= htmlspecialchars($p['motivo'] ?? '—') ?></td>
            <td>
              <span class="badge badge-<?= $p['estado'] ?>">
                <?= ucfirst($p['estado']) ?>
              </span>
            </td>
            <td>
              <a href="ver_solicitud.php?id=<?= $p['id_permiso'] ?>" class="btn-ver">Ver</a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>

  </div>
  
  <!-- Widget de tiempo en ausencia movible (idéntico al admin) -->
  <div id="widgetTiempoAusencia" class="widget-tiempo-ausencia widget-hidden">
      <div class="widget-position-indicator"></div>
      <div class="widget-header">
          <div class="widget-controls">
              <button class="widget-btn" onclick="toggleMinimizar()" title="Minimizar/Maximizar" id="btnMinimizar">−</button>
              <button class="widget-btn" onclick="resetPosition()" title="Resetear posición" id="btnReset">⌂</button>
          </div>
          <h4 id="widgetTitulo">⏰ En Ausencia Laboral</h4>
          <div style="width: 54px;"></div>
      </div>
      <div class="widget-info" id="widgetInfo">
          <strong id="tipoPermiso">-</strong><br>
          <span id="infoPermiso">-</span>
      </div>
      <div class="tiempo-digital" id="tiempoDigital">
          <div class="tiempo-principal" id="tiempoPrincipal">00:00:00</div>
          <div class="tiempo-info" id="tiempoInfo">00:00 AM - Iniciado</div>
      </div>
      <button class="btn-finalizar" onclick="mostrarConfirmacionFinalizar(permisoActivo ? permisoActivo.id_permiso : null)" id="btnFinalizar">
          🏁 FINALIZAR PERMISO
      </button>
  </div>
  
  <script>
    // JS del widget (idéntico al admin): draggable, contador, confirmación y finalizar_permiso.php
    let permisoActivo = null;
    let tiempoInicial = null;
    let intervalTimer = null;
    let widgetMinimizado = false;
    let isDragging = false;
    let dragOffset = { x: 0, y: 0 };
    let modoConfirmacion = false;

    document.addEventListener('DOMContentLoaded', function() {
        verificarPermisoActivo();
        setInterval(verificarPermisoActivo, 2 * 60 * 1000);
        inicializarDraggable();
        cargarPosicionGuardada();
    });

    function inicializarDraggable() {
        const widget = document.getElementById('widgetTiempoAusencia');
        const header = widget.querySelector('.widget-header');

        // Eventos de mouse
        header.addEventListener('mousedown', iniciarDrag);
        document.addEventListener('mousemove', duranteDrag);
        document.addEventListener('mouseup', finalizarDrag);

        // Eventos táctiles para móvil
        header.addEventListener('touchstart', iniciarDragTouch, { passive: false });
        document.addEventListener('touchmove', duranteDragTouch, { passive: false });
        document.addEventListener('touchend', finalizarDrag);
    }

    function iniciarDrag(e) {
        const widget = document.getElementById('widgetTiempoAusencia');
        isDragging = true;
        widget.classList.add('dragging');

        const rect = widget.getBoundingClientRect();
        dragOffset.x = e.clientX - rect.left;
        dragOffset.y = e.clientY - rect.top;

        e.preventDefault();
    }

    function iniciarDragTouch(e) {
        const touch = e.touches[0];
        const widget = document.getElementById('widgetTiempoAusencia');
        isDragging = true;
        widget.classList.add('dragging');

        const rect = widget.getBoundingClientRect();
        dragOffset.x = touch.clientX - rect.left;
        dragOffset.y = touch.clientY - rect.top;

        e.preventDefault();
    }

    function duranteDrag(e) {
        if (!isDragging) return;

        const x = e.clientX - dragOffset.x;
        const y = e.clientY - dragOffset.y;

        posicionarWidget(x, y);
        e.preventDefault();
    }

    function duranteDragTouch(e) {
        if (!isDragging) return;

        const touch = e.touches[0];
        const x = touch.clientX - dragOffset.x;
        const y = touch.clientY - dragOffset.y;

        posicionarWidget(x, y);
        e.preventDefault();
    }

    function posicionarWidget(x, y) {
        const widget = document.getElementById('widgetTiempoAusencia');
        const rect = widget.getBoundingClientRect();
        const windowWidth = window.innerWidth;
        const windowHeight = window.innerHeight;

        // Limitar a los bordes de la ventana
        const maxX = windowWidth - rect.width;
        const maxY = windowHeight - rect.height;

        x = Math.max(0, Math.min(x, maxX));
        y = Math.max(0, Math.min(y, maxY));

        widget.style.left = x + 'px';
        widget.style.top = y + 'px';
        widget.style.right = 'auto';
        widget.style.bottom = 'auto';
    }

    function finalizarDrag() {
        if (!isDragging) return;

        const widget = document.getElementById('widgetTiempoAusencia');
        isDragging = false;
        widget.classList.remove('dragging');

        // Guardar posición
        guardarPosicion();
    }

    function guardarPosicion() {
        const widget = document.getElementById('widgetTiempoAusencia');
        const rect = widget.getBoundingClientRect();

        const posicion = {
            x: rect.left,
            y: rect.top,
            minimizado: widgetMinimizado
        };

        localStorage.setItem('widgetPosition', JSON.stringify(posicion));
    }

    function cargarPosicionGuardada() {
        const posicionGuardada = localStorage.getItem('widgetPosition');
        if (posicionGuardada) {
            try {
                const posicion = JSON.parse(posicionGuardada);
                const widget = document.getElementById('widgetTiempoAusencia');

                widget.style.left = posicion.x + 'px';
                widget.style.top = posicion.y + 'px';
                widget.style.right = 'auto';
                widget.style.bottom = 'auto';

                if (posicion.minimizado) {
                    toggleMinimizar();
                }
            } catch (e) {
                console.warn('Error al cargar posición guardada:', e);
            }
        }
    }

    function resetPosition() {
        const widget = document.getElementById('widgetTiempoAusencia');

        // Resetear a posición original
        widget.style.left = 'auto';
        widget.style.top = 'auto';
        widget.style.right = '20px';
        widget.style.bottom = '20px';

        // Limpiar posición guardada
        localStorage.removeItem('widgetPosition');

        // Animación de confirmación
        widget.style.animation = 'bounce 0.5s ease';
        setTimeout(() => {
            widget.style.animation = '';
        }, 500);
    }

    function verificarPermisoActivo() {
        fetch('widget_tiempo_ausencia.php', {
            method: 'GET',
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            // Si hay permiso activo mostramos widget
            if (data.success && data.permiso_activo) {
                mostrarWidgetTiempo(data);
                window.permisoActivo = data; // para compatibilidad con otras funciones
            } else {
                ocultarWidgetTiempo();
            }
        })
        .catch(error => {
            console.error('❌ Error verificando permiso activo:', error);
            ocultarWidgetTiempo();
        });
    }

    function mostrarWidgetTiempo(data) {
        permisoActivo = data;
        tiempoInicial = data.minutos_transcurridos;

        const widget = document.getElementById('widgetTiempoAusencia');
        const tipoPermiso = document.getElementById('tipoPermiso');
        const infoPermiso = document.getElementById('infoPermiso');
        const titulo = document.getElementById('widgetTitulo');

        tipoPermiso.textContent = data.tipo_permiso;
        infoPermiso.innerHTML = `Desde: ${formatearFechaHora(data.fecha_salida, data.hora_salida)}<br>Hasta: ${formatearFechaHora(data.fecha_regreso_aprox, data.hora_regreso_aprox)}`;

        if (!data.en_horario_laboral) {
            widget.classList.add('widget-fuera-horario');
            titulo.textContent = '⏸️ Fuera de Horario Laboral';
            document.getElementById('btnFinalizar').textContent = '⏸️ PAUSADO';
        } else {
            widget.classList.remove('widget-fuera-horario');
            titulo.textContent = '⏰ En Ausencia Laboral';
            document.getElementById('btnFinalizar').textContent = '🏁 FINALIZAR PERMISO';
        }

        widget.classList.remove('widget-hidden');
        iniciarContadorTiempo();
    }

    function ocultarWidgetTiempo() {
        const widget = document.getElementById('widgetTiempoAusencia');
        widget.classList.add('widget-hidden');

        if (intervalTimer) {
            clearInterval(intervalTimer);
            intervalTimer = null;
        }

        permisoActivo = null;
    }

    function iniciarContadorTiempo() {
        if (intervalTimer) {
            clearInterval(intervalTimer);
        }
        
        // Usar la hora de inicio del permiso desde el backend
        const inicioAusencia = new Date(permisoActivo.inicio_ausencia);
        
        intervalTimer = setInterval(() => {
            if (!permisoActivo) return;
            
            const ahora = new Date();
            const segundosTranscurridos = calcularTiempoLaboralEnSegundos(inicioAusencia, ahora);
            actualizarDisplayConSegundos(segundosTranscurridos);
        }, 1000);
    }

    function calcularTiempoLaboralEnSegundos(inicio, fin) {
        // Horarios laborales por día de la semana (igual que en PHP)
        const horariosLaborales = {
            1: [['07:30', '12:00'], ['14:00', '17:30']], // Lunes
            2: [['07:30', '12:00'], ['14:00', '17:30']], // Martes
            3: [['07:30', '12:00'], ['14:00', '17:30']], // Miércoles
            4: [['07:30', '12:00'], ['14:00', '17:30']], // Jueves
            5: [['07:30', '12:00'], ['14:00', '17:00']], // Viernes
            6: [['08:00', '12:30']]                      // Sábado
        };
        
        let totalSegundos = 0;
        const fechaActual = new Date(inicio);
        const fechaFin = new Date(fin);
        
        // Iterar día por día
        while (fechaActual.toDateString() <= fechaFin.toDateString()) {
            const diaSemana = fechaActual.getDay() === 0 ? 7 : fechaActual.getDay(); // 1=lunes, 7=domingo
            
            // Solo procesar días laborales (lunes a sábado)
            if (diaSemana <= 6 && horariosLaborales[diaSemana]) {
                const fechaStr = fechaActual.toISOString().split('T')[0];
                
                for (const rango of horariosLaborales[diaSemana]) {
                    const inicioRango = new Date(fechaStr + 'T' + rango[0] + ':00');
                    const finRango = new Date(fechaStr + 'T' + rango[1] + ':00');
                    
                    // Ajustar los límites según la fecha actual
                    let inicioEfectivo = inicioRango;
                    let finEfectivo = finRango;
                    
                    if (fechaActual.toDateString() === inicio.toDateString()) {
                        // Primer día: usar la hora de inicio real si es mayor
                        inicioEfectivo = inicio > inicioRango ? inicio : inicioRango;
                    }
                    
                    if (fechaActual.toDateString() === fechaFin.toDateString()) {
                        // Último día: usar la hora de fin real si es menor
                        finEfectivo = fechaFin < finRango ? fechaFin : finRango;
                    }
                    
                    // Solo contar si hay overlap válido
                    if (inicioEfectivo < finEfectivo) {
                        const segundosEnRango = Math.floor((finEfectivo - inicioEfectivo) / 1000);
                        totalSegundos += segundosEnRango;
                    }
                }
            }
            
            // Avanzar al siguiente día
            fechaActual.setDate(fechaActual.getDate() + 1);
            fechaActual.setHours(0, 0, 0, 0);
        }
        
        return totalSegundos;
    }

    function actualizarDisplayConSegundos(segundosTotal) {
        const horas = Math.floor(segundosTotal / 3600);
        const minutos = Math.floor((segundosTotal % 3600) / 60);
        const segundos = segundosTotal % 60;
        
        const tiempoFormateado = `${String(horas).padStart(2, '0')}:${String(minutos).padStart(2, '0')}:${String(segundos).padStart(2, '0')}`;
        
        // Obtener hora actual en formato AM/PM
        const ahora = new Date();
        const horaActual = formatearHoraAMPM(ahora);
        
        // Calcular tiempo de inicio
        const inicioAusencia = new Date(permisoActivo.inicio_ausencia);
        const horaInicio = formatearHoraAMPM(inicioAusencia);
        
        document.getElementById('tiempoPrincipal').textContent = tiempoFormateado;
        document.getElementById('tiempoInfo').textContent = `${horaActual} - Desde ${horaInicio}`;
    }

    function formatearHoraAMPM(fecha) {
        return fecha.toLocaleString('es-ES', {
            hour: '2-digit',
            minute: '2-digit',
            hour12: true
        });
    }

    function formatearFechaHora(fecha, hora) {
        const fechaObj = new Date(fecha + 'T' + hora);
        return fechaObj.toLocaleString('es-ES', {
            day: '2-digit',
            month: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            hour12: true
        });
    }

    function toggleMinimizar() {
        const widget = document.getElementById('widgetTiempoAusencia');
        const btnMinimizar = document.getElementById('btnMinimizar');

        widgetMinimizado = !widgetMinimizado;

        if (widgetMinimizado) {
            widget.classList.add('widget-minimized');
            btnMinimizar.textContent = '+';
            btnMinimizar.title = 'Maximizar';
        } else {
            widget.classList.remove('widget-minimized');
            btnMinimizar.textContent = '−';
            btnMinimizar.title = 'Minimizar';
        }

        // Guardar estado
        guardarPosicion();
    }

    function mostrarConfirmacionFinalizar(idPermiso) {
        if (modoConfirmacion) {
            // Ya está en modo confirmación, finalizar directamente
            finalizarPermiso(idPermiso);
        } else {
            // Entrar en modo confirmación
            modoConfirmacion = true;
            const btnFinalizar = document.getElementById('btnFinalizar');

            btnFinalizar.textContent = '✅ CONFIRMAR FINALIZACIÓN';
            btnFinalizar.classList.add('confirmar');

            // Crear y agregar botón cancelar
            if (!document.querySelector('.btn-cancelar')) {
                const btnCancelar = document.createElement('button');
                btnCancelar.className = 'btn-cancelar';
                btnCancelar.textContent = '❌ CANCELAR';
                btnCancelar.onclick = function() {
                    cancelarFinalizacion();
                };

                // Insertar después del botón finalizar
                btnFinalizar.parentNode.insertBefore(btnCancelar, btnFinalizar.nextSibling);
            }

            // Auto cancelar después de 10 segundos
            setTimeout(() => {
                if (modoConfirmacion) {
                    cancelarFinalizacion();
                }
            }, 10000);
        }
    }

    function cancelarFinalizacion() {
        modoConfirmacion = false;
        const btnFinalizar = document.getElementById('btnFinalizar');
        const btnCancelar = document.querySelector('.btn-cancelar');

        btnFinalizar.textContent = '🏁 FINALIZAR PERMISO';
        btnFinalizar.classList.remove('confirmar');
        btnFinalizar.disabled = false;

        if (btnCancelar) {
            btnCancelar.remove();
        }
    }

    function finalizarPermiso(idPermiso) {
        if (!idPermiso) {
            console.error('ID de permiso no válido');
            cancelarFinalizacion();
            return;
        }

        // Mostrar estado de procesamiento
        const btnFinalizar = document.getElementById('btnFinalizar');
        const btnCancelar = document.querySelector('.btn-cancelar');

        btnFinalizar.textContent = '⏳ FINALIZANDO...';
        btnFinalizar.disabled = true;

        if (btnCancelar) {
            btnCancelar.style.display = 'none';
        }

        const formData = new FormData();
        formData.append('id_permiso', idPermiso);

        fetch('finalizar_permiso.php', {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Error en la respuesta del servidor');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Éxito - ocultar widget y recargar para reflejar cambios
                ocultarWidgetTiempo();
                cancelarFinalizacion();
                alert('✅ Permiso finalizado correctamente.');
                setTimeout(() => { window.location.reload(); }, 1200);
            } else {
                throw new Error(data.error || 'Error desconocido al finalizar el permiso');
            }
        })
        .catch(error => {
            console.error('Error al finalizar permiso:', error);
            // Resetear botones en caso de error
            btnFinalizar.textContent = '🏁 FINALIZAR PERMISO';
            btnFinalizar.disabled = false;

            if (btnCancelar) {
                btnCancelar.style.display = 'block';
            }

            cancelarFinalizacion();
            alert('❌ Error finalizando permiso: ' + (error.message || 'Desconocido'));
        });
    }

    // Funciones legacy para compatibilidad
    function calcularMinutosLaboralesTranscurridos(inicio, fin) {
        // Esta función ya no se usa, pero la mantenemos para compatibilidad
        return Math.floor(calcularTiempoLaboralEnSegundos(inicio, fin) / 60);
    }
    
    function actualizarDisplay(minutosTotal) {
        // Esta función ya no se usa, reemplazada por actualizarDisplayConSegundos
        const segundosTotal = minutosTotal * 60;
        actualizarDisplayConSegundos(segundosTotal);
    }

    // Agregar estilo de animación para el bounce
    const style = document.createElement('style');
    style.textContent = `
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-10px); }
            60% { transform: translateY(-5px); }
        }
    `;
    document.head.appendChild(style);
  </script>

  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
  <script>
    $(document).ready(function () {
      $('#tablaPermisos').DataTable({
        dom: '<"dt-top"lf>t<"dt-bottom"ip>',
        pageLength: 10,
        order: [[2, 'desc']],
        columnDefs: [
          { orderable: false, targets: 5 }
        ],
        language: {
          decimal:        ',',
          thousands:      '.',
          emptyTable:     'No hay solicitudes disponibles',
          info:           'Mostrando _START_ a _END_ de _TOTAL_ solicitudes',
          infoEmpty:      'Mostrando 0 a 0 de 0 solicitudes',
          infoFiltered:   '(filtrado de _MAX_ solicitudes en total)',
          lengthMenu:     'Mostrar _MENU_ solicitudes',
          loadingRecords: 'Cargando...',
          processing:     'Procesando...',
          search:         'Buscar:',
          zeroRecords:    'No se encontraron solicitudes coincidentes',
          paginate: {
            first:    '«',
            last:     '»',
            next:     '›',
            previous: '‹',
          },
        },
      });
    });
  </script>
</body>
</html>