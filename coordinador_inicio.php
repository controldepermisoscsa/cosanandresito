<?php
session_start();
require 'conexion.php';

if (!isset($_SESSION['usuario_id']) || strtolower($_SESSION['cargo']) !== 'coordinador') {
    header('Location: login.php');
    exit();
}

$id_coordinador = $_SESSION['usuario_id'];
$vista = $_GET['vista'] ?? 'permisos_auxiliares';

// Fecha en español
$dias_es  = ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'];
$meses_es = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
$hoy      = new DateTime();
$fecha_es = ucfirst($dias_es[(int)$hoy->format('w')]) . ', ' . $hoy->format('d') . ' de ' . $meses_es[(int)$hoy->format('n') - 1] . ' de ' . $hoy->format('Y');

if ($vista === 'mis_permisos') {
    $stmt = $pdo->prepare("
        SELECT p.*,
               u.nombre AS solicitante,
               c.nombre_cargo,
               TO_CHAR(p.fecha_salida,        'DD/MM/YYYY') AS fecha_salida_fmt,
               TO_CHAR(p.hora_salida,         'HH12:MI AM') AS hora_salida_fmt,
               TO_CHAR(p.fecha_regreso_aprox, 'DD/MM/YYYY') AS fecha_regreso_fmt,
               TO_CHAR(p.hora_regreso_aprox,  'HH12:MI AM') AS hora_regreso_fmt
        FROM permisos p
        INNER JOIN usuarios u ON p.id_usuario = u.id_usuario
        INNER JOIN cargo c ON u.id_cargo = c.id_cargo
        WHERE p.id_usuario = :id_coordinador
        ORDER BY p.id_permiso DESC
    ");
    $stmt->execute(['id_coordinador' => $id_coordinador]);
} else {
    $stmt = $pdo->prepare("
        SELECT p.*,
               u.nombre AS solicitante,
               c.nombre_cargo,
               p.asignado_a,
               p.id_asignado,
               TO_CHAR(p.fecha_salida,        'DD/MM/YYYY') AS fecha_salida_fmt,
               TO_CHAR(p.hora_salida,         'HH12:MI AM') AS hora_salida_fmt,
               TO_CHAR(p.fecha_regreso_aprox, 'DD/MM/YYYY') AS fecha_regreso_fmt,
               TO_CHAR(p.hora_regreso_aprox,  'HH12:MI AM') AS hora_regreso_fmt
        FROM permisos p
        INNER JOIN usuarios u ON p.id_usuario = u.id_usuario
        INNER JOIN cargo c ON u.id_cargo = c.id_cargo
        WHERE (p.id_asignado = :id_coordinador OR p.id_asignado IS NULL)
          AND p.asignado_a = 'coordinador'
          AND LOWER(c.nombre_cargo) = 'auxiliar'
        ORDER BY p.id_permiso DESC
    ");
    $stmt->execute(['id_coordinador' => $id_coordinador]);
}

$permisos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas
$total     = count($permisos);
$pendiente = count(array_filter($permisos, fn($p) => $p['estado'] === 'pendiente'));
$aprobado  = count(array_filter($permisos, fn($p) => $p['estado'] === 'aprobado'));
$rechazado = count(array_filter($permisos, fn($p) => in_array($p['estado'], ['rechazado', 'reenviado'])));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Coordinador</title>
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
            font-size: 22px; margin: 0 auto 10px;
            box-shadow: 0 4px 12px rgba(243,156,18,0.4);
        }
        .sidebar-brand h2 { color: #fff; font-size: 14px; font-weight: 600; letter-spacing: 0.5px; }
        .sidebar-user {
            padding: 16px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .sidebar-user .user-name { color: #fff; font-size: 13px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .sidebar-user .user-role { color: #f39c12; font-size: 11px; margin-top: 2px; text-transform: uppercase; letter-spacing: 0.5px; }
        .sidebar-nav { flex: 1; padding: 16px 12px; display: flex; flex-direction: column; gap: 4px; }
        .nav-item {
            display: flex; align-items: center; gap: 12px;
            color: #adb5bd; text-decoration: none;
            padding: 11px 14px; border-radius: 10px;
            font-size: 14px; font-weight: 500; transition: all 0.2s;
        }
        .nav-item:hover { background: rgba(255,255,255,0.08); color: #fff; }
        .nav-item.activo {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: #fff; box-shadow: 0 4px 12px rgba(243,156,18,0.35);
        }
        .nav-item .nav-icon { font-size: 18px; width: 22px; text-align: center; }
        .sidebar-logout { padding: 12px; border-top: 1px solid rgba(255,255,255,0.08); }
        .sidebar-logout a {
            display: flex; align-items: center; gap: 10px;
            color: #e74c3c; text-decoration: none;
            padding: 10px 14px; border-radius: 10px;
            font-size: 14px; font-weight: 500; transition: background 0.2s;
        }
        .sidebar-logout a:hover { background: rgba(231,76,60,0.15); }

        /* ── CONTENT ── */
        .content { flex: 1; overflow-y: auto; padding: 28px 32px; }

        /* ── PAGE HEADER ── */
        .page-header { margin-bottom: 28px; }
        .page-header h1 { font-size: 24px; color: #1a2535; font-weight: 700; }
        .page-header .sub { color: #6c757d; font-size: 13px; margin-top: 4px; }

        /* ── ALERTS ── */
        .alert {
            padding: 12px 18px; border-radius: 10px;
            font-size: 14px; margin-bottom: 20px;
        }
        .alert-success { background: #d1e7dd; color: #0a3622; }
        .alert-danger  { background: #f8d7da; color: #842029; }

        /* ── STATS ── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 28px;
        }
        .stat-card {
            background: #fff; border-radius: 14px;
            padding: 20px; display: flex; align-items: center;
            gap: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 6px 18px rgba(0,0,0,0.1); }
        .stat-icon {
            width: 48px; height: 48px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px; flex-shrink: 0;
        }
        .stat-card.total     .stat-icon { background: #ebf5fb; }
        .stat-card.pendiente .stat-icon { background: #fef9e7; }
        .stat-card.aprobado  .stat-icon { background: #eafaf1; }
        .stat-card.rechazado .stat-icon { background: #fdf2f8; }
        .stat-info .stat-number { font-size: 28px; font-weight: 700; color: #1a2535; line-height: 1; }
        .stat-info .stat-label  { font-size: 12px; color: #6c757d; margin-top: 4px; font-weight: 500; }

        /* ── TABLE CARD ── */
        .table-card { background: #fff; border-radius: 14px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); overflow: hidden; }
        .table-card-header { padding: 18px 24px; border-bottom: 1px solid #f0f2f5; text-align: center; }
        .table-card-header h3 { font-size: 16px; color: #1a2535; font-weight: 700; }

        table { width: 100%; border-collapse: collapse; }
        thead th {
            background: #f8f9fa; padding: 12px 16px;
            font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;
            color: #6c757d; font-weight: 700; text-align: left;
        }
        tbody tr { border-bottom: 1px solid #f0f2f5; transition: background 0.15s; }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: #fafbfc; }
        tbody td { padding: 13px 16px; font-size: 14px; color: #2c3e50; }

        .badge {
            display: inline-block; padding: 4px 10px;
            border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: capitalize;
        }
        .badge-pendiente  { background: #fff3cd; color: #856404; }
        .badge-aprobado   { background: #d1e7dd; color: #0a3622; }
        .badge-rechazado  { background: #f8d7da; color: #842029; }
        .badge-reenviado  { background: #cff4fc; color: #055160; }
        .badge-cancelado  { background: #e2e3e5; color: #41464b; }
        .badge-finalizado { background: #d3d3d3; color: #383838; }

        .btn-ver {
            background: #f0f4ff; color: #3a5bd9;
            border: none; padding: 6px 14px;
            border-radius: 8px; font-size: 12px; font-weight: 600;
            text-decoration: none; transition: background 0.2s;
        }
        .btn-ver:hover { background: #dce5ff; }

        .hora-sub { color: #f39c12; font-size: 12px; font-weight: 600; display: block; margin-top: 2px; }

        .empty-state { text-align: center; padding: 40px 20px; color: #adb5bd; }
        .empty-state .empty-icon { font-size: 40px; margin-bottom: 10px; }
        .empty-state p { font-size: 14px; }

        .btn-primary {
            display: inline-flex; align-items: center; gap: 8px;
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: #fff; text-decoration: none;
            padding: 11px 22px; border-radius: 10px;
            font-size: 14px; font-weight: 600;
            box-shadow: 0 4px 12px rgba(243,156,18,0.35);
            transition: all 0.2s;
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(243,156,18,0.45); }

        /* ── DATATABLES OVERRIDES ── */
        .dataTables_wrapper { font-family: 'Segoe UI', Arial, sans-serif; }
        .dt-top { display: flex; align-items: center; justify-content: space-between; padding: 14px 20px 10px; flex-wrap: wrap; gap: 10px; }
        .dt-bottom { display: flex; align-items: center; justify-content: space-between; padding: 12px 20px 16px; flex-wrap: wrap; gap: 8px; border-top: 1px solid #f0f2f5; }
        .dataTables_wrapper .dataTables_filter label,
        .dataTables_wrapper .dataTables_length label { font-size: 13px; color: #6c757d; font-weight: 500; }
        .dataTables_wrapper .dataTables_filter input {
            border: 1.5px solid #dee2e6; border-radius: 10px;
            padding: 7px 12px; font-size: 13px; color: #1a2535;
            margin-left: 8px; outline: none; font-family: inherit;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .dataTables_wrapper .dataTables_filter input:focus { border-color: #f39c12; box-shadow: 0 0 0 3px rgba(243,156,18,0.12); }
        .dataTables_wrapper .dataTables_length select {
            border: 1.5px solid #dee2e6; border-radius: 8px;
            padding: 6px 10px; font-size: 13px; color: #1a2535;
            margin: 0 6px; outline: none; font-family: inherit; cursor: pointer;
        }
        .dataTables_wrapper .dataTables_info { font-size: 12px; color: #6c757d; }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            border-radius: 8px !important; font-size: 13px !important;
            padding: 5px 11px !important; color: #495057 !important;
            border: 1px solid transparent !important; background: none !important;
            box-shadow: none !important; cursor: pointer;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover { background: #f0f2f5 !important; color: #1a2535 !important; }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current,
        .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
            background: linear-gradient(135deg, #f39c12, #e67e22) !important;
            color: #fff !important; border-color: transparent !important;
            box-shadow: 0 3px 8px rgba(243,156,18,0.35) !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled,
        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled:hover { color: #adb5bd !important; cursor: default; }
        .dataTables_wrapper table.dataTable tbody tr.odd  { background: #fff; }
        .dataTables_wrapper table.dataTable tbody tr.even { background: #fafbfc; }
        .dataTables_wrapper table.dataTable.no-footer { border-bottom: none; }

        /* ── WIDGET ── */
        .widget-tiempo-ausencia {
            position: fixed; bottom: 20px; right: 20px;
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
            color: white; padding: 18px; border-radius: 15px;
            box-shadow: 0 8px 25px rgba(243,156,18,0.4);
            min-width: 300px; max-width: 340px; z-index: 999;
            font-family: 'Segoe UI', sans-serif;
            border: 2px solid rgba(255,255,255,0.2);
            transition: all 0.3s ease; cursor: move; user-select: none;
        }
        .widget-tiempo-ausencia:hover { transform: translateY(-3px); box-shadow: 0 12px 30px rgba(243,156,18,0.5); }
        .widget-tiempo-ausencia.dragging { transform: rotate(3deg) scale(1.05); z-index: 1001; }
        .widget-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; cursor: move; padding: 5px 0; }
        .widget-header h4 { margin: 0; font-size: 15px; font-weight: 700; color: white; flex-grow: 1; text-align: center; }
        .widget-controls { display: flex; gap: 5px; }
        .widget-btn { background: rgba(255,255,255,0.2); border: none; color: white; width: 24px; height: 24px; border-radius: 50%; cursor: pointer; font-size: 12px; font-weight: bold; transition: all 0.3s; display: flex; align-items: center; justify-content: center; }
        .widget-btn:hover { background: rgba(255,255,255,0.3); transform: scale(1.1); }
        .tiempo-digital { font-family: 'Courier New', monospace; font-size: 24px; font-weight: bold; text-align: center; margin: 15px 0; padding: 12px; background: rgba(255,255,255,0.15); border-radius: 10px; letter-spacing: 1px; border: 1px solid rgba(255,255,255,0.3); color: #fff; display: flex; flex-direction: column; align-items: center; gap: 5px; }
        .tiempo-principal { font-size: 26px; line-height: 1; }
        .tiempo-info { font-size: 11px; opacity: 0.8; font-family: Arial, sans-serif; }
        .widget-info { font-size: 12px; opacity: 0.95; margin-bottom: 15px; text-align: center; line-height: 1.4; color: #fff; }
        .btn-finalizar { background: linear-gradient(45deg, #e74c3c, #c0392b); color: white; border: none; padding: 12px 18px; border-radius: 10px; cursor: pointer; font-weight: 700; width: 100%; transition: all 0.3s; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; }
        .btn-finalizar:hover { transform: translateY(-2px); }
        .btn-finalizar.confirmar { animation: pulse 1.5s infinite; }
        .btn-cancelar { background: linear-gradient(45deg, #6c757d, #545b62); color: white; border: none; padding: 8px 15px; border-radius: 8px; cursor: pointer; font-weight: 600; width: 100%; margin-top: 8px; transition: all 0.3s; font-size: 11px; text-transform: uppercase; }
        .widget-hidden { display: none !important; }
        .widget-minimized { min-width: 80px; padding: 10px; border-radius: 30px; }
        .widget-minimized .widget-info, .widget-minimized .btn-finalizar { display: none; }
        .widget-minimized .tiempo-digital { font-size: 16px; margin: 5px 0; padding: 8px; }
        .widget-fuera-horario { opacity: 0.8; background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%); }
        .content { margin-bottom: 100px; }
        @keyframes pulse { 0%,100%{transform:scale(1);}50%{transform:scale(1.05);} }
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
        <div class="user-role">Coordinador</div>
    </div>
    <nav class="sidebar-nav">
        <a href="coordinador_inicio.php" class="nav-item<?= $vista === 'permisos_auxiliares' ? ' activo' : '' ?>">
            <span class="nav-icon">🏠</span> Inicio
        </a>
        <a href="coordinador_inicio.php?vista=permisos_auxiliares" class="nav-item<?= $vista === 'permisos_auxiliares' ? '' : '' ?>">
            <span class="nav-icon">👥</span> Permisos Auxiliares
        </a>
        <a href="ver_permisos.php" class="nav-item">
            <span class="nav-icon">📂</span> Mis Permisos
        </a>
        <a href="solicitar_permiso.php?nuevo=1" class="nav-item">
            <span class="nav-icon">📝</span> Solicitar Permiso
        </a>
        <a href="recuperar_tiempo.php" class="nav-item">
            <span class="nav-icon">⏱️</span> Recuperar Tiempo
        </a>
    </nav>
    <div class="sidebar-logout">
        <a href="logout.php"><span style="font-size:16px;">🚪</span> Cerrar Sesión</a>
    </div>
</div>

<!-- CONTENT -->
<div class="content">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
            <div>
                <h1>Bienvenido, <?= htmlspecialchars(explode(' ', $_SESSION['nombre'])[0]) ?> 👋</h1>
                <span class="sub"><?= $fecha_es ?></span>
            </div>
            <a href="solicitar_permiso.php?nuevo=1" class="btn-primary">
                <span>＋</span> Nuevo Permiso
            </a>
        </div>
    </div>

    <!-- ALERTS -->
    <?php if (isset($_SESSION['mensaje'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['mensaje']); unset($_SESSION['mensaje']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <!-- STATS -->
    <div class="stats-grid">
        <div class="stat-card total">
            <div class="stat-icon">📋</div>
            <div class="stat-info">
                <div class="stat-number"><?= $total ?></div>
                <div class="stat-label"><?= $vista === 'mis_permisos' ? 'Mis Solicitudes' : 'Total Asignados' ?></div>
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
                <div class="stat-label">Rechazados / Reenviados</div>
            </div>
        </div>
    </div>

    <!-- TABLE CARD -->
    <div class="table-card">
        <div class="table-card-header">
            <h3>
                <?= $vista === 'mis_permisos' ? '📄 Mis Permisos Enviados' : '👥 Permisos de Auxiliares Asignados' ?>
            </h3>
        </div>

        <?php if (empty($permisos)): ?>
        <div class="empty-state">
            <div class="empty-icon"><?= $vista === 'mis_permisos' ? '📄' : '📋' ?></div>
            <p><?= $vista === 'mis_permisos' ? 'Aún no has enviado permisos.' : 'No hay permisos de auxiliares asignados.' ?></p>
        </div>
        <?php else: ?>

        <?php if ($vista === 'mis_permisos'): ?>
        <table id="tablaPermisos" style="width:100%">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Tipo</th>
                    <th>Motivo</th>
                    <th>Fecha Salida</th>
                    <th>Estado</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($permisos as $p): ?>
                <tr>
                    <td><?= $p['id_permiso'] ?></td>
                    <td><?= htmlspecialchars($p['tipo_permiso'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($p['motivo'] ?? '—') ?></td>
                    <td data-order="<?= $p['fecha_salida'] ?? '' ?>">
                        <?= $p['fecha_salida_fmt'] ?? '—' ?>
                        <?php if (!empty($p['hora_salida_fmt'])): ?>
                            <span class="hora-sub"><?= $p['hora_salida_fmt'] ?></span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge badge-<?= $p['estado'] ?>"><?= ucfirst($p['estado']) ?></span></td>
                    <td><a href="ver_solicitud.php?id=<?= $p['id_permiso'] ?>" class="btn-ver">Ver</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php else: /* permisos_auxiliares */ ?>
        <table id="tablaPermisos" style="width:100%">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Auxiliar</th>
                    <th>Tipo</th>
                    <th>Fecha Salida</th>
                    <th>Regreso Aprox.</th>
                    <th>Estado</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($permisos as $p): ?>
                <tr>
                    <td><?= $p['id_permiso'] ?></td>
                    <td><?= htmlspecialchars($p['solicitante'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($p['tipo_permiso'] ?? '—') ?></td>
                    <td data-order="<?= $p['fecha_salida'] ?? '' ?>">
                        <?= $p['fecha_salida_fmt'] ?? '—' ?>
                        <?php if (!empty($p['hora_salida_fmt'])): ?>
                            <span class="hora-sub"><?= $p['hora_salida_fmt'] ?></span>
                        <?php endif; ?>
                    </td>
                    <td data-order="<?= $p['fecha_regreso_aprox'] ?? '' ?>">
                        <?= $p['fecha_regreso_fmt'] ?? '—' ?>
                        <?php if (!empty($p['hora_regreso_fmt'])): ?>
                            <span class="hora-sub"><?= $p['hora_regreso_fmt'] ?></span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge badge-<?= $p['estado'] ?>"><?= ucfirst($p['estado']) ?></span></td>
                    <td><a href="coordinador_ver_solicitud.php?id=<?= $p['id_permiso'] ?>" class="btn-ver">Ver</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <?php endif; ?>
    </div>

</div><!-- .content -->

<!-- WIDGET TIEMPO AUSENCIA -->
<div id="widgetTiempoAusencia" class="widget-tiempo-ausencia widget-hidden">
    <div class="widget-header">
        <div class="widget-controls">
            <button class="widget-btn" onclick="toggleMinimizar()" id="btnMinimizar">−</button>
            <button class="widget-btn" onclick="resetPosition()" id="btnReset">⌂</button>
        </div>
        <h4 id="widgetTitulo">⏰ En Ausencia Laboral</h4>
        <div style="width:54px;"></div>
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

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script>
$(document).ready(function () {
    $('#tablaPermisos').DataTable({
        dom: '<"dt-top"lf>t<"dt-bottom"ip>',
        pageLength: 10,
        order: [[0, 'desc']],
        columnDefs: [{ orderable: false, targets: -1 }],
        language: {
            emptyTable:   'No hay solicitudes disponibles',
            info:         'Mostrando _START_ a _END_ de _TOTAL_ solicitudes',
            infoEmpty:    'Sin resultados',
            infoFiltered: '(filtrado de _MAX_ en total)',
            lengthMenu:   'Mostrar _MENU_ solicitudes',
            search:       'Buscar:',
            zeroRecords:  'No se encontraron solicitudes',
            paginate: { first: '«', last: '»', next: '›', previous: '‹' },
        },
    });
});

// ── WIDGET JS ──
let permisoActivo = null, intervalTimer = null, widgetMinimizado = false;
let isDragging = false, dragOffset = { x: 0, y: 0 }, modoConfirmacion = false;

document.addEventListener('DOMContentLoaded', function() {
    verificarPermisoActivo();
    setInterval(verificarPermisoActivo, 2 * 60 * 1000);
    inicializarDraggable();
    cargarPosicionGuardada();
});

function inicializarDraggable() {
    const widget = document.getElementById('widgetTiempoAusencia');
    const header = widget.querySelector('.widget-header');
    header.addEventListener('mousedown', iniciarDrag);
    document.addEventListener('mousemove', duranteDrag);
    document.addEventListener('mouseup', finalizarDrag);
    header.addEventListener('touchstart', iniciarDrag, { passive: false });
    document.addEventListener('touchmove', duranteDrag, { passive: false });
    document.addEventListener('touchend', finalizarDrag);
}

function iniciarDrag(e) {
    const widget = document.getElementById('widgetTiempoAusencia');
    isDragging = true; widget.classList.add('dragging');
    const rect = widget.getBoundingClientRect();
    const src = e.touches ? e.touches[0] : e;
    dragOffset.x = src.clientX - rect.left;
    dragOffset.y = src.clientY - rect.top;
    e.preventDefault();
}

function duranteDrag(e) {
    if (!isDragging) return;
    const src = e.touches ? e.touches[0] : e;
    posicionarWidget(src.clientX - dragOffset.x, src.clientY - dragOffset.y);
    e.preventDefault();
}

function posicionarWidget(x, y) {
    const widget = document.getElementById('widgetTiempoAusencia');
    const rect = widget.getBoundingClientRect();
    x = Math.max(0, Math.min(x, window.innerWidth - rect.width));
    y = Math.max(0, Math.min(y, window.innerHeight - rect.height));
    widget.style.left = x + 'px'; widget.style.top = y + 'px';
    widget.style.right = 'auto'; widget.style.bottom = 'auto';
}

function finalizarDrag() {
    if (!isDragging) return;
    isDragging = false;
    document.getElementById('widgetTiempoAusencia').classList.remove('dragging');
    guardarPosicion();
}

function guardarPosicion() {
    const rect = document.getElementById('widgetTiempoAusencia').getBoundingClientRect();
    localStorage.setItem('widgetPosition', JSON.stringify({ x: rect.left, y: rect.top, minimizado: widgetMinimizado }));
}

function cargarPosicionGuardada() {
    try {
        const pos = JSON.parse(localStorage.getItem('widgetPosition') || 'null');
        if (!pos) return;
        const w = document.getElementById('widgetTiempoAusencia');
        w.style.left = pos.x + 'px'; w.style.top = pos.y + 'px';
        w.style.right = 'auto'; w.style.bottom = 'auto';
        if (pos.minimizado) toggleMinimizar();
    } catch (e) {}
}

function resetPosition() {
    const w = document.getElementById('widgetTiempoAusencia');
    w.style.left = 'auto'; w.style.top = 'auto';
    w.style.right = '20px'; w.style.bottom = '20px';
    localStorage.removeItem('widgetPosition');
}

function verificarPermisoActivo() {
    fetch('widget_tiempo_ausencia.php', { credentials: 'same-origin' })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.permiso_activo) { mostrarWidgetTiempo(data); window.permisoActivo = data; }
        else { ocultarWidgetTiempo(); window.permisoActivo = null; }
    })
    .catch(() => ocultarWidgetTiempo());
}

function mostrarWidgetTiempo(data) {
    permisoActivo = data;
    const widget = document.getElementById('widgetTiempoAusencia');
    document.getElementById('tipoPermiso').textContent = data.tipo_permiso;
    document.getElementById('infoPermiso').innerHTML = `Desde: ${data.fecha_salida} ${data.hora_salida}`;
    if (intervalTimer) clearInterval(intervalTimer);
    const inicio = new Date((data.inicio_ausencia).replace(' ', 'T'));
    function tick() {
        const diff = Math.max(0, Math.floor((new Date() - inicio) / 1000));
        const h = Math.floor(diff / 3600), m = Math.floor((diff % 3600) / 60), s = diff % 60;
        document.getElementById('tiempoPrincipal').textContent = `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
        const ahora = new Date();
        document.getElementById('tiempoInfo').textContent = ahora.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit', hour12: true });
    }
    tick(); intervalTimer = setInterval(tick, 1000);
    if (!data.en_horario_laboral) {
        widget.classList.add('widget-fuera-horario');
        document.getElementById('widgetTitulo').textContent = '⏸️ Fuera de Horario';
        document.getElementById('btnFinalizar').textContent = '⏸️ PAUSADO';
    } else {
        widget.classList.remove('widget-fuera-horario');
        document.getElementById('widgetTitulo').textContent = '⏰ En Ausencia Laboral';
        document.getElementById('btnFinalizar').textContent = '🏁 FINALIZAR PERMISO';
    }
    widget.classList.remove('widget-hidden');
}

function ocultarWidgetTiempo() {
    document.getElementById('widgetTiempoAusencia').classList.add('widget-hidden');
    if (intervalTimer) clearInterval(intervalTimer);
}

function toggleMinimizar() {
    const w = document.getElementById('widgetTiempoAusencia');
    const btn = document.getElementById('btnMinimizar');
    widgetMinimizado = !widgetMinimizado;
    w.classList.toggle('widget-minimized', widgetMinimizado);
    btn.textContent = widgetMinimizado ? '+' : '−';
    guardarPosicion();
}

function mostrarConfirmacionFinalizar(idPermiso) {
    if (modoConfirmacion) { finalizarPermiso(idPermiso); return; }
    modoConfirmacion = true;
    const btn = document.getElementById('btnFinalizar');
    btn.textContent = '✅ CONFIRMAR FINALIZACIÓN'; btn.classList.add('confirmar');
    if (!document.querySelector('.btn-cancelar')) {
        const bc = document.createElement('button');
        bc.className = 'btn-cancelar'; bc.textContent = '❌ CANCELAR';
        bc.onclick = cancelarFinalizacion;
        btn.parentNode.insertBefore(bc, btn.nextSibling);
    }
    setTimeout(() => { if (modoConfirmacion) cancelarFinalizacion(); }, 10000);
}

function cancelarFinalizacion() {
    modoConfirmacion = false;
    const btn = document.getElementById('btnFinalizar');
    btn.textContent = '🏁 FINALIZAR PERMISO'; btn.classList.remove('confirmar'); btn.disabled = false;
    const bc = document.querySelector('.btn-cancelar'); if (bc) bc.remove();
}

function finalizarPermiso(idPermiso) {
    if (!idPermiso) { cancelarFinalizacion(); return; }
    const btn = document.getElementById('btnFinalizar');
    btn.textContent = '⏳ FINALIZANDO...'; btn.disabled = true;
    const fd = new FormData(); fd.append('id_permiso', idPermiso);
    fetch('finalizar_permiso.php', { method: 'POST', credentials: 'same-origin', body: fd })
    .then(r => r.json())
    .then(data => {
        if (data.success) { ocultarWidgetTiempo(); cancelarFinalizacion(); alert('✅ Permiso finalizado.'); setTimeout(() => location.reload(), 1200); }
        else { throw new Error(data.error || 'Error desconocido'); }
    })
    .catch(err => {
        btn.textContent = '🏁 FINALIZAR PERMISO'; btn.disabled = false;
        cancelarFinalizacion(); alert('❌ Error: ' + err.message);
    });
}
</script>
</body>
</html>
