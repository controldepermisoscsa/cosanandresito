<?php
session_start();
require 'conexion.php';

if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['nombre'])) {
    header('Location: login.php?mensaje=Debes iniciar sesión para acceder a esta página.');
    exit();
}

$cargo = strtolower($_SESSION['cargo'] ?? '');
if (!in_array($cargo, ['gerente', 'gerencia'])) {
    header('Location: login.php?mensaje=No tienes permiso para acceder a esta página.');
    exit();
}

$filtro = $_GET['filtro'] ?? 'asignados';
$estadosValidos = ['asignados', 'pendiente', 'aprobado', 'rechazado', 'reenviado', 'todos', 'cancelados', 'finalizado'];
if (!in_array($filtro, $estadosValidos)) $filtro = 'asignados';

$id_gerente = $_SESSION['usuario_id'];

// Stats cards
$stmtStats = $pdo->prepare("
    SELECT
      COUNT(*) FILTER (WHERE (p.asignado_a = 'gerente') AND (p.id_asignado = ? OR p.id_asignado IS NULL)) AS asignados,
      COUNT(*) FILTER (WHERE p.estado = 'pendiente' AND p.asignado_a = 'gerente') AS pendientes,
      COUNT(*) FILTER (WHERE p.estado = 'aprobado') AS aprobados,
      COUNT(*) FILTER (WHERE p.estado = 'finalizado') AS finalizados,
      COUNT(*) FILTER (WHERE p.estado = 'rechazado') AS rechazados,
      COUNT(*) FILTER (WHERE p.estado = 'reenviado' AND p.asignado_a = 'gerente') AS reenviados
    FROM permisos p
    JOIN usuarios u ON p.id_usuario = u.id_usuario
");
$stmtStats->execute([$id_gerente]);
$stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

// Permisos según filtro
if ($filtro === 'asignados') {
    $stmtPermisos = $pdo->prepare("
        SELECT p.id_permiso, u.nombre AS nombre_empleado, c.nombre_cargo AS cargo, u.area, p.estado, p.fecha_salida
        FROM permisos p
        JOIN usuarios u ON p.id_usuario = u.id_usuario
        JOIN cargo c ON u.id_cargo = c.id_cargo
        WHERE (p.asignado_a = 'gerente')
          AND (p.id_asignado = ? OR p.id_asignado IS NULL)
        ORDER BY p.fecha_salida DESC
    ");
    $stmtPermisos->execute([$id_gerente]);
} elseif ($filtro === 'todos') {
    $stmtPermisos = $pdo->prepare("
        SELECT p.id_permiso, u.nombre AS nombre_empleado, c.nombre_cargo AS cargo, u.area, p.estado, p.fecha_salida
        FROM permisos p
        JOIN usuarios u ON p.id_usuario = u.id_usuario
        JOIN cargo c ON u.id_cargo = c.id_cargo
        WHERE (
            ((p.asignado_a = 'gerente') AND (p.id_asignado = ? OR p.id_asignado IS NULL))
            OR p.estado = 'aprobado'
            OR p.estado = 'finalizado'
            OR (p.estado = 'rechazado' AND p.asignado_a IN ('coordinador', 'auxiliar'))
            OR (p.estado = 'reenviado' AND p.asignado_a = 'gerente')
            OR p.estado = 'cancelado'
        )
        ORDER BY p.fecha_salida DESC
    ");
    $stmtPermisos->execute([$id_gerente]);
} elseif ($filtro === 'rechazado') {
    $stmtPermisos = $pdo->prepare("
        SELECT p.id_permiso, u.nombre AS nombre_empleado, c.nombre_cargo AS cargo, u.area, p.estado, p.fecha_salida
        FROM permisos p
        JOIN usuarios u ON p.id_usuario = u.id_usuario
        JOIN cargo c ON u.id_cargo = c.id_cargo
        WHERE p.estado = 'rechazado' AND p.motivo_rechazo IS NOT NULL
        ORDER BY p.fecha_salida DESC
    ");
    $stmtPermisos->execute();
} elseif ($filtro === 'reenviado') {
    $stmtPermisos = $pdo->prepare("
        SELECT p.id_permiso, u.nombre AS nombre_empleado, c.nombre_cargo AS cargo, u.area, p.estado, p.fecha_salida
        FROM permisos p
        JOIN usuarios u ON p.id_usuario = u.id_usuario
        JOIN cargo c ON u.id_cargo = c.id_cargo
        WHERE p.estado = 'reenviado' AND (p.asignado_a = 'gerente' OR p.id_asignado = ?)
        ORDER BY p.fecha_salida DESC
    ");
    $stmtPermisos->execute([$id_gerente]);
} elseif ($filtro === 'cancelados') {
    $stmtPermisos = $pdo->prepare("
        SELECT p.id_permiso, u.nombre AS nombre_empleado, c.nombre_cargo AS cargo, u.area, p.estado, p.fecha_salida
        FROM permisos p
        JOIN usuarios u ON p.id_usuario = u.id_usuario
        JOIN cargo c ON u.id_cargo = c.id_cargo
        WHERE p.estado = 'cancelado'
        ORDER BY p.fecha_salida DESC
    ");
    $stmtPermisos->execute();
} elseif ($filtro === 'pendiente') {
    $stmtPermisos = $pdo->prepare("
        SELECT p.id_permiso, u.nombre AS nombre_empleado, c.nombre_cargo AS cargo, u.area, p.estado, p.fecha_salida
        FROM permisos p
        JOIN usuarios u ON p.id_usuario = u.id_usuario
        JOIN cargo c ON u.id_cargo = c.id_cargo
        WHERE p.estado = 'pendiente' AND p.asignado_a = 'gerente'
        ORDER BY p.fecha_salida DESC
    ");
    $stmtPermisos->execute();
} elseif ($filtro === 'aprobado') {
    $stmtPermisos = $pdo->prepare("
        SELECT p.id_permiso, u.nombre AS nombre_empleado, c.nombre_cargo AS cargo, u.area, p.estado, p.fecha_salida
        FROM permisos p
        JOIN usuarios u ON p.id_usuario = u.id_usuario
        JOIN cargo c ON u.id_cargo = c.id_cargo
        WHERE p.estado = 'aprobado'
        ORDER BY p.fecha_salida DESC
    ");
    $stmtPermisos->execute();
} elseif ($filtro === 'finalizado') {
    $stmtPermisos = $pdo->prepare("
        SELECT p.id_permiso, u.nombre AS nombre_empleado, c.nombre_cargo AS cargo, u.area, p.estado, p.fecha_salida
        FROM permisos p
        JOIN usuarios u ON p.id_usuario = u.id_usuario
        JOIN cargo c ON u.id_cargo = c.id_cargo
        WHERE p.estado = 'finalizado'
        ORDER BY p.fecha_salida DESC
    ");
    $stmtPermisos->execute();
} else {
    $stmtPermisos = $pdo->prepare("
        SELECT p.id_permiso, u.nombre AS nombre_empleado, c.nombre_cargo AS cargo, u.area, p.estado, p.fecha_salida
        FROM permisos p
        JOIN usuarios u ON p.id_usuario = u.id_usuario
        JOIN cargo c ON u.id_cargo = c.id_cargo
        WHERE p.estado = ? AND (
            p.asignado_a = 'gerente'
            OR p.estado IN ('aprobado', 'cancelado')
            OR (p.estado = 'rechazado' AND p.motivo_rechazo IS NOT NULL)
        )
        ORDER BY p.fecha_salida DESC
    ");
    $stmtPermisos->execute([$filtro]);
}

$permisos = $stmtPermisos->fetchAll(PDO::FETCH_ASSOC);

// Spanish date
$dias_es  = ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'];
$meses_es = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
$hoy      = new DateTime();
$fecha_es = ucfirst($dias_es[(int)$hoy->format('w')]) . ', ' . $hoy->format('d') . ' de ' . $meses_es[(int)$hoy->format('n') - 1] . ' de ' . $hoy->format('Y');

$mensaje = $_GET['msg'] ?? '';

$filtroLabels = [
    'asignados' => 'Asignados a mí',
    'todos'     => 'Todos',
    'pendiente' => 'Pendientes',
    'aprobado'  => 'Aprobados',
    'finalizado'=> 'Finalizados',
    'rechazado' => 'Rechazados',
    'reenviado' => 'Reenviados',
    'cancelados'=> 'Cancelados',
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Gerente</title>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            display: flex; height: 100vh;
            background: #f0f2f5; overflow: hidden;
        }

        /* ── SIDEBAR ── */
        .sidebar {
            width: 240px; flex-shrink: 0;
            background: linear-gradient(180deg, #1a2535 0%, #2c3e50 100%);
            display: flex; flex-direction: column;
            box-shadow: 3px 0 15px rgba(0,0,0,0.3);
            overflow-y: auto;
        }
        .sidebar-brand { padding: 24px 20px 20px; border-bottom: 1px solid rgba(255,255,255,0.08); text-align: center; }
        .sidebar-brand .brand-icon {
            width: 48px; height: 48px;
            background: linear-gradient(135deg, #f39c12, #e67e22);
            border-radius: 12px; display: flex; align-items: center; justify-content: center;
            font-size: 22px; margin: 0 auto 10px;
            box-shadow: 0 4px 12px rgba(243,156,18,0.4);
        }
        .sidebar-brand h2 { color: #fff; font-size: 14px; font-weight: 600; letter-spacing: 0.5px; }
        .sidebar-user { padding: 16px 20px; border-bottom: 1px solid rgba(255,255,255,0.08); }
        .sidebar-user .user-name { color: #fff; font-size: 13px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .sidebar-user .user-role { color: #f39c12; font-size: 11px; margin-top: 2px; text-transform: uppercase; letter-spacing: 0.5px; }

        .sidebar-section { padding: 14px 12px 6px; }
        .sidebar-section-label { color: rgba(255,255,255,0.35); font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; padding: 0 6px; margin-bottom: 6px; }
        .nav-item {
            display: flex; align-items: center; gap: 10px;
            color: #adb5bd; text-decoration: none; padding: 10px 14px;
            border-radius: 10px; font-size: 13px; font-weight: 500;
            transition: all 0.2s; margin-bottom: 2px;
        }
        .nav-item:hover { background: rgba(255,255,255,0.08); color: #fff; }
        .nav-item.active { background: rgba(243,156,18,0.18); color: #f39c12; font-weight: 600; }
        .nav-item .nav-icon { font-size: 16px; width: 20px; text-align: center; flex-shrink: 0; }
        .nav-badge {
            margin-left: auto; background: #f39c12; color: #fff;
            font-size: 10px; font-weight: 700; padding: 2px 7px;
            border-radius: 10px; min-width: 20px; text-align: center;
        }
        .nav-badge.danger { background: #e74c3c; }
        .nav-badge.muted  { background: rgba(255,255,255,0.15); }

        .sidebar-logout { padding: 12px; border-top: 1px solid rgba(255,255,255,0.08); margin-top: auto; }
        .sidebar-logout a { display: flex; align-items: center; gap: 10px; color: #e74c3c; text-decoration: none; padding: 10px 14px; border-radius: 10px; font-size: 13px; font-weight: 500; transition: background 0.2s; }
        .sidebar-logout a:hover { background: rgba(231,76,60,0.15); }

        /* ── CONTENT ── */
        .content { flex: 1; overflow-y: auto; padding: 28px 32px; }

        /* ── HEADER ── */
        .page-header { margin-bottom: 24px; }
        .page-header-top { display: flex; justify-content: space-between; align-items: center; }
        .page-title { font-size: 22px; font-weight: 700; color: #1a2535; }
        .page-date { color: #6c757d; font-size: 13px; margin-top: 4px; }
        .page-header-sub { font-size: 13px; color: #6c757d; margin-top: 4px; }

        /* ── STATS GRID ── */
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
        .stat-card {
            background: #fff; border-radius: 14px;
            padding: 20px 22px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            display: flex; align-items: center; gap: 16px;
            transition: box-shadow 0.2s, transform 0.2s;
        }
        .stat-card:hover { box-shadow: 0 6px 18px rgba(0,0,0,0.10); transform: translateY(-2px); }
        .stat-icon {
            width: 46px; height: 46px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px; flex-shrink: 0;
        }
        .si-orange { background: #fff3cd; }
        .si-yellow { background: #fff3cd; }
        .si-green  { background: #d1fae5; }
        .si-blue   { background: #dbeafe; }
        .si-red    { background: #fee2e2; }
        .stat-info .stat-num  { font-size: 28px; font-weight: 800; color: #1a2535; line-height: 1; }
        .stat-info .stat-lbl  { font-size: 12px; color: #6c757d; margin-top: 3px; font-weight: 500; }

        /* ── CARD ── */
        .card { background: #fff; border-radius: 14px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); padding: 22px 24px; }
        .card-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; }
        .card-title { font-size: 14px; font-weight: 700; color: #1a2535; display: flex; align-items: center; gap: 8px; }
        .card-subtitle { font-size: 12px; color: #6c757d; }

        /* ── BADGE ── */
        .badge { display: inline-block; padding: 4px 11px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: capitalize; }
        .badge-pendiente  { background: #fff3cd; color: #856404; }
        .badge-aprobado   { background: #d1e7dd; color: #0a3622; }
        .badge-rechazado  { background: #f8d7da; color: #842029; }
        .badge-reenviado  { background: #cff4fc; color: #055160; }
        .badge-cancelado  { background: #e2e3e5; color: #41464b; }
        .badge-finalizado { background: #d3d3d3; color: #383838; }

        /* ── BTN VER ── */
        .btn-ver {
            display: inline-flex; align-items: center; gap: 5px;
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: #fff; text-decoration: none;
            padding: 6px 14px; border-radius: 8px; font-size: 12px; font-weight: 600;
            box-shadow: 0 2px 6px rgba(243,156,18,0.3);
            transition: all 0.2s;
        }
        .btn-ver:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(243,156,18,0.4); }

        /* ── DATATABLES OVERRIDES ── */
        .dt-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; flex-wrap: wrap; gap: 10px; }
        .dt-bottom { display: flex; justify-content: space-between; align-items: center; margin-top: 14px; flex-wrap: wrap; gap: 10px; }
        div.dataTables_wrapper div.dataTables_filter input {
            border: 1.5px solid #dee2e6; border-radius: 8px; padding: 7px 12px; font-size: 13px; outline: none; width: 220px;
        }
        div.dataTables_wrapper div.dataTables_filter input:focus { border-color: #f39c12; box-shadow: 0 0 0 3px rgba(243,156,18,0.12); }
        div.dataTables_wrapper div.dataTables_length select { border: 1.5px solid #dee2e6; border-radius: 8px; padding: 6px 10px; font-size: 13px; }
        table.dataTable thead th { background: #f8f9fa; color: #374151; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #e9ecef !important; padding: 12px 14px; }
        table.dataTable tbody td { padding: 12px 14px; font-size: 13px; color: #374151; vertical-align: middle; border-bottom: 1px solid #f0f2f5 !important; }
        table.dataTable tbody tr:hover { background: #fafbfc; }
        table.dataTable { border-collapse: collapse !important; }
        .paginate_button.current, .paginate_button.current:hover { background: linear-gradient(135deg,#f39c12,#e67e22) !important; color: #fff !important; border-color: #f39c12 !important; border-radius: 6px !important; }
        .paginate_button:hover { background: #f8f9fa !important; border-radius: 6px !important; }
        div.dataTables_info { font-size: 12px; color: #6c757d; }

        /* ── ALERT ── */
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; border-radius: 10px; padding: 12px 16px; margin-bottom: 20px; font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 8px; }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon">🏢</div>
        <h2>Coosanandresito</h2>
    </div>
    <div class="sidebar-user">
        <div class="user-name"><?= htmlspecialchars($_SESSION['nombre']) ?></div>
        <div class="user-role">Gerente</div>
    </div>

    <div class="sidebar-section">
        <div class="sidebar-section-label">Vista</div>
        <a href="?filtro=asignados" class="nav-item <?= $filtro === 'asignados' ? 'active' : '' ?>">
            <span class="nav-icon">📋</span> Asignados a mí
            <?php if ($stats['asignados'] > 0): ?><span class="nav-badge"><?= $stats['asignados'] ?></span><?php endif; ?>
        </a>
        <a href="?filtro=todos" class="nav-item <?= $filtro === 'todos' ? 'active' : '' ?>">
            <span class="nav-icon">🗂️</span> Todos
        </a>
    </div>

    <div class="sidebar-section">
        <div class="sidebar-section-label">Por Estado</div>
        <a href="?filtro=pendiente" class="nav-item <?= $filtro === 'pendiente' ? 'active' : '' ?>">
            <span class="nav-icon">⏳</span> Pendientes
            <?php if ($stats['pendientes'] > 0): ?><span class="nav-badge danger"><?= $stats['pendientes'] ?></span><?php endif; ?>
        </a>
        <a href="?filtro=aprobado" class="nav-item <?= $filtro === 'aprobado' ? 'active' : '' ?>">
            <span class="nav-icon">✅</span> Aprobados
            <?php if ($stats['aprobados'] > 0): ?><span class="nav-badge muted"><?= $stats['aprobados'] ?></span><?php endif; ?>
        </a>
        <a href="?filtro=finalizado" class="nav-item <?= $filtro === 'finalizado' ? 'active' : '' ?>">
            <span class="nav-icon">🏁</span> Finalizados
            <?php if ($stats['finalizados'] > 0): ?><span class="nav-badge muted"><?= $stats['finalizados'] ?></span><?php endif; ?>
        </a>
        <a href="?filtro=rechazado" class="nav-item <?= $filtro === 'rechazado' ? 'active' : '' ?>">
            <span class="nav-icon">❌</span> Rechazados
            <?php if ($stats['rechazados'] > 0): ?><span class="nav-badge muted"><?= $stats['rechazados'] ?></span><?php endif; ?>
        </a>
        <a href="?filtro=reenviado" class="nav-item <?= $filtro === 'reenviado' ? 'active' : '' ?>">
            <span class="nav-icon">🔄</span> Reenviados
            <?php if ($stats['reenviados'] > 0): ?><span class="nav-badge muted"><?= $stats['reenviados'] ?></span><?php endif; ?>
        </a>
        <a href="?filtro=cancelados" class="nav-item <?= $filtro === 'cancelados' ? 'active' : '' ?>">
            <span class="nav-icon">🚫</span> Cancelados
        </a>
    </div>

    <div class="sidebar-section" style="padding-bottom:4px;">
        <div class="sidebar-section-label">Herramientas</div>
        <a href="estadisticas_completas.php" class="nav-item">
            <span class="nav-icon">📊</span> Ver Estadísticas
        </a>
    </div>

    <div class="sidebar-logout">
        <a href="logout.php"><span style="font-size:16px;">🚪</span> Cerrar Sesión</a>
    </div>
</div>

<!-- CONTENT -->
<div class="content">

    <!-- HEADER -->
    <div class="page-header">
        <div class="page-header-top">
            <div>
                <div class="page-title">Panel de Gerente</div>
                <div class="page-date"><?= $fecha_es ?></div>
            </div>
        </div>
    </div>

    <!-- STATS -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon si-orange">📋</div>
            <div class="stat-info">
                <div class="stat-num"><?= $stats['asignados'] ?></div>
                <div class="stat-lbl">Asignados a mí</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon si-yellow">⏳</div>
            <div class="stat-info">
                <div class="stat-num"><?= $stats['pendientes'] ?></div>
                <div class="stat-lbl">Pendientes</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon si-green">✅</div>
            <div class="stat-info">
                <div class="stat-num"><?= $stats['aprobados'] ?></div>
                <div class="stat-lbl">Aprobados</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon si-blue">🏁</div>
            <div class="stat-info">
                <div class="stat-num"><?= $stats['finalizados'] ?></div>
                <div class="stat-lbl">Finalizados</div>
            </div>
        </div>
    </div>

    <!-- SUCCESS ALERT -->
    <?php if (!empty($mensaje)): ?>
    <div class="alert-success"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>

    <!-- TABLE CARD -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                📄 Solicitudes — <?= $filtroLabels[$filtro] ?? ucfirst($filtro) ?>
            </div>
            <div class="card-subtitle"><?= count($permisos) ?> registro(s)</div>
        </div>

        <table id="tablaPermisos" class="display" style="width:100%">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Empleado</th>
                    <th>Cargo</th>
                    <th>Área</th>
                    <th>Fecha Salida</th>
                    <th>Estado</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($permisos as $p): ?>
                <tr>
                    <td><?= htmlspecialchars($p['id_permiso']) ?></td>
                    <td><?= htmlspecialchars($p['nombre_empleado']) ?></td>
                    <td><?= htmlspecialchars($p['cargo']) ?></td>
                    <td><?= htmlspecialchars($p['area']) ?></td>
                    <td data-order="<?= $p['fecha_salida'] ?? '' ?>">
                        <?= !empty($p['fecha_salida']) ? date('d/m/Y', strtotime($p['fecha_salida'])) : '—' ?>
                    </td>
                    <td><span class="badge badge-<?= $p['estado'] ?>"><?= ucfirst($p['estado']) ?></span></td>
                    <td>
                        <a href="gerente_ver_solicitud.php?id=<?= $p['id_permiso'] ?>" class="btn-ver">👁 Ver</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div><!-- .content -->

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script>
    $('#tablaPermisos').DataTable({
        dom: '<"dt-top"lf>t<"dt-bottom"ip>',
        pageLength: 15,
        order: [[4, 'desc']],
        columnDefs: [{ orderable: false, targets: 6 }],
        language: {
            lengthMenu:    'Mostrar _MENU_ registros',
            zeroRecords:   'No se encontraron permisos',
            info:          'Mostrando _START_ a _END_ de _TOTAL_ registros',
            infoEmpty:     'Sin registros disponibles',
            infoFiltered:  '(filtrado de _MAX_ registros)',
            search:        'Buscar:',
            paginate: { first:'«', last:'»', next:'›', previous:'‹' }
        }
    });

    <?php if (!empty($mensaje)): ?>
    Swal.fire({
        icon: 'success',
        title: '¡Éxito!',
        text: <?= json_encode($mensaje) ?>,
        timer: 3000,
        timerProgressBar: true,
        showConfirmButton: false,
    });
    history.replaceState(null, '', '?filtro=<?= urlencode($filtro) ?>');
    <?php endif; ?>
</script>
</body>
</html>
