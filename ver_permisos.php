<?php
session_start();
require 'conexion.php';

if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['nombre'])) {
    header('Location: login.php?mensaje=Debes iniciar sesión para acceder a esta página.');
    exit();
}

$cargo = strtolower(trim($_SESSION['cargo'] ?? ''));
$cargos_validos = ['administrador', 'coordinador', 'auxiliar', 'administrativo', 'gerencia', 'gerente', 'admin'];

if (!in_array($cargo, $cargos_validos)) {
    error_log("Cargo rechazado en ver_permisos.php: '$cargo'");
    header('Location: login.php?mensaje=No tienes permiso para acceder a esta página.');
    exit();
}

$archivoPanel = match ($cargo) {
    'administrador' => 'admin_inicio.php',
    'coordinador'   => 'coordinador_inicio.php',
    'auxiliar'      => 'auxiliar_inicio.php',
    'administrativo'=> 'administrativo_inicio.php',
    'gerencia', 'gerente' => 'gerente_inicio.php',
    default         => 'login.php',
};

$navItems = match ($cargo) {
    'auxiliar' => [
        ['href' => 'auxiliar_inicio.php',          'icon' => '🏠', 'label' => 'Inicio'],
        ['href' => 'solicitar_permiso.php?nuevo=1', 'icon' => '📝', 'label' => 'Solicitar Permiso'],
        ['href' => 'ver_permisos.php',             'icon' => '📂', 'label' => 'Mis Permisos', 'activo' => true],
        ['href' => 'recuperar_tiempo.php',         'icon' => '⏱️', 'label' => 'Recuperar Tiempo'],
    ],
    'administrativo' => [
        ['href' => 'administrativo_inicio.php',    'icon' => '🏠', 'label' => 'Inicio'],
        ['href' => 'solicitar_permiso.php?nuevo=1','icon' => '📝', 'label' => 'Solicitar Permiso'],
        ['href' => 'ver_permisos.php',             'icon' => '📂', 'label' => 'Mis Permisos', 'activo' => true],
    ],
    'coordinador' => [
        ['href' => 'coordinador_inicio.php',       'icon' => '🏠', 'label' => 'Inicio'],
        ['href' => 'solicitar_permiso.php?nuevo=1','icon' => '📝', 'label' => 'Solicitar Permiso'],
        ['href' => 'ver_permisos.php',             'icon' => '📂', 'label' => 'Mis Permisos', 'activo' => true],
    ],
    'administrador' => [
        ['href' => 'admin_inicio.php',             'icon' => '🏠', 'label' => 'Inicio'],
        ['href' => 'solicitar_permiso.php?nuevo=1','icon' => '📝', 'label' => 'Solicitar Permiso'],
        ['href' => 'ver_permisos.php',             'icon' => '📂', 'label' => 'Mis Permisos', 'activo' => true],
        ['href' => 'gestionar_usuarios.php',       'icon' => '👥', 'label' => 'Gestionar Usuarios'],
    ],
    'gerente', 'gerencia' => [
        ['href' => 'gerente_inicio.php',           'icon' => '🏠', 'label' => 'Inicio'],
        ['href' => 'ver_permisos.php',             'icon' => '📂', 'label' => 'Ver Solicitudes', 'activo' => true],
    ],
    default => [['href' => 'inicio.php', 'icon' => '🏠', 'label' => 'Inicio']],
};

// Fecha en español
$dias_es  = ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'];
$meses_es = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
$hoy      = new DateTime();
$fecha_es = ucfirst($dias_es[(int)$hoy->format('w')]) . ', ' . $hoy->format('d') . ' de ' . $meses_es[(int)$hoy->format('n') - 1] . ' de ' . $hoy->format('Y');

// Consultar permisos con tiempo en AM/PM
try {
    $stmt = $pdo->prepare("
        SELECT p.*,
               TO_CHAR(p.fecha_salida,        'DD/MM/YYYY')  AS fecha_salida_fmt,
               TO_CHAR(p.hora_salida,         'HH12:MI AM')  AS hora_salida_fmt,
               TO_CHAR(p.fecha_regreso_aprox, 'DD/MM/YYYY')  AS fecha_regreso_aprox_fmt,
               TO_CHAR(p.hora_regreso_aprox,  'HH12:MI AM')  AS hora_regreso_aprox_fmt,
               TO_CHAR(p.fecha_regreso_real,  'DD/MM/YYYY')  AS fecha_regreso_real_fmt,
               TO_CHAR(p.hora_regreso_real,   'HH12:MI AM')  AS hora_regreso_real_fmt,
               CASE
                   WHEN p.tiempo_total_ausencia IS NOT NULL THEN
                       EXTRACT(HOUR FROM p.tiempo_total_ausencia)::int || ' h, ' ||
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

// Contadores por estado
$contadores = ['todos' => count($permisos)];
foreach ($permisos as $p) {
    $contadores[$p['estado']] = ($contadores[$p['estado']] ?? 0) + 1;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Permisos</title>
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
            color: #fff;
            box-shadow: 0 4px 12px rgba(243,156,18,0.35);
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
        .page-header {
            text-align: center;
            margin-bottom: 24px;
        }
        .page-header h1 { font-size: 22px; color: #1a2535; font-weight: 700; }
        .page-header .sub { color: #6c757d; font-size: 13px; margin-top: 4px; }

        /* ── FILTER TABS ── */
        .filter-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 20px;
        }
        .filter-tab {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 16px; border-radius: 20px;
            font-size: 13px; font-weight: 600;
            cursor: pointer; border: 1.5px solid #dee2e6;
            background: #fff; color: #6c757d;
            transition: all 0.2s; user-select: none;
        }
        .filter-tab:hover { border-color: #f39c12; color: #f39c12; }
        .filter-tab.activo {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: #fff; border-color: transparent;
            box-shadow: 0 4px 10px rgba(243,156,18,0.35);
        }
        .filter-tab .count {
            background: rgba(0,0,0,0.1);
            border-radius: 10px;
            padding: 1px 7px;
            font-size: 11px;
        }
        .filter-tab.activo .count { background: rgba(255,255,255,0.25); }

        /* ── TABLE CARD ── */
        .table-card {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            overflow: hidden;
        }
        .table-card-header {
            padding: 18px 24px;
            border-bottom: 1px solid #f0f2f5;
            text-align: center;
        }
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
            text-decoration: none; transition: background 0.2s; white-space: nowrap;
        }
        .btn-ver:hover { background: #dce5ff; }

        .empty-state { text-align: center; padding: 40px 20px; color: #adb5bd; }
        .empty-state .empty-icon { font-size: 40px; margin-bottom: 10px; }
        .empty-state p { font-size: 14px; }

        .hora-sub { color: #f39c12; font-size: 12px; font-weight: 600; display: block; margin-top: 2px; }

        /* ── DATATABLES OVERRIDES ── */
        .dataTables_wrapper { font-family: 'Segoe UI', Arial, sans-serif; }
        .dt-top {
            display: flex; align-items: center; justify-content: space-between;
            padding: 14px 20px 10px; flex-wrap: wrap; gap: 10px;
        }
        .dt-bottom {
            display: flex; align-items: center; justify-content: space-between;
            padding: 12px 20px 16px; flex-wrap: wrap; gap: 8px;
            border-top: 1px solid #f0f2f5;
        }
        .dataTables_wrapper .dataTables_filter label,
        .dataTables_wrapper .dataTables_length label { font-size: 13px; color: #6c757d; font-weight: 500; }
        .dataTables_wrapper .dataTables_filter input {
            border: 1.5px solid #dee2e6; border-radius: 10px;
            padding: 7px 12px; font-size: 13px; color: #1a2535;
            margin-left: 8px; outline: none; font-family: inherit;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .dataTables_wrapper .dataTables_filter input:focus {
            border-color: #f39c12; box-shadow: 0 0 0 3px rgba(243,156,18,0.12);
        }
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
            box-shadow: none !important; transition: background 0.15s !important; cursor: pointer;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: #f0f2f5 !important; color: #1a2535 !important; border-color: transparent !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current,
        .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
            background: linear-gradient(135deg, #f39c12, #e67e22) !important;
            color: #fff !important; border-color: transparent !important;
            box-shadow: 0 3px 8px rgba(243,156,18,0.35) !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled,
        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled:hover {
            color: #adb5bd !important; cursor: default;
        }
        .dataTables_wrapper table.dataTable tbody tr.odd  { background: #fff; }
        .dataTables_wrapper table.dataTable tbody tr.even { background: #fafbfc; }
        .dataTables_wrapper table.dataTable.no-footer { border-bottom: none; }
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
        <div class="user-role"><?= htmlspecialchars(ucfirst($cargo)) ?></div>
    </div>
    <nav class="sidebar-nav">
        <?php foreach ($navItems as $item): ?>
        <a href="<?= htmlspecialchars($item['href']) ?>"
           class="nav-item<?= !empty($item['activo']) ? ' activo' : '' ?>">
            <span class="nav-icon"><?= $item['icon'] ?></span> <?= htmlspecialchars($item['label']) ?>
        </a>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-logout">
        <a href="logout.php"><span style="font-size:16px;">🚪</span> Cerrar Sesión</a>
    </div>
</div>

<!-- CONTENT -->
<div class="content">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <h1>📂 Mis Permisos</h1>
        <div class="sub"><?= $fecha_es ?></div>
    </div>

    <!-- FILTER TABS -->
    <div class="filter-tabs">
        <?php
        $tabs = [
            'todos'     => ['label' => 'Todos',      'emoji' => '📋'],
            'pendiente' => ['label' => 'Pendientes',  'emoji' => '⏳'],
            'aprobado'  => ['label' => 'Aprobados',   'emoji' => '✅'],
            'rechazado' => ['label' => 'Rechazados',  'emoji' => '❌'],
            'reenviado' => ['label' => 'Reenviados',  'emoji' => '🔄'],
            'cancelado' => ['label' => 'Cancelados',  'emoji' => '🚫'],
            'finalizado'=> ['label' => 'Finalizados', 'emoji' => '🏁'],
        ];
        foreach ($tabs as $key => $tab):
            $cnt = $contadores[$key] ?? 0;
            if ($cnt === 0 && $key !== 'todos') continue;
        ?>
        <span class="filter-tab<?= $key === 'todos' ? ' activo' : '' ?>"
              data-filtro="<?= $key ?>">
            <?= $tab['emoji'] ?> <?= $tab['label'] ?>
            <span class="count"><?= $cnt ?></span>
        </span>
        <?php endforeach; ?>
    </div>

    <!-- TABLE CARD -->
    <div class="table-card">
        <div class="table-card-header">
            <h3>Historial de Solicitudes</h3>
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
                    <th>Motivo</th>
                    <th>Salida</th>
                    <th>Regreso Aprox.</th>
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
                    <td data-order="<?= $p['fecha_regreso_aprox'] ?? '' ?>">
                        <?= $p['fecha_regreso_aprox_fmt'] ?? '—' ?>
                        <?php if (!empty($p['hora_regreso_aprox_fmt'])): ?>
                            <span class="hora-sub"><?= $p['hora_regreso_aprox_fmt'] ?></span>
                        <?php endif; ?>
                    </td>
                    <td data-order="<?= $p['estado'] ?>">
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

</div><!-- .content -->

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script>
$(document).ready(function () {
    const tabla = $('#tablaPermisos').DataTable({
        dom: '<"dt-top"lf>t<"dt-bottom"ip>',
        pageLength: 10,
        order: [[0, 'desc']],
        columnDefs: [
            { orderable: false, targets: 6 },
            { searchable: false, targets: [0, 6] }
        ],
        language: {
            decimal:      ',',
            thousands:    '.',
            emptyTable:   'No hay solicitudes disponibles',
            info:         'Mostrando _START_ a _END_ de _TOTAL_ solicitudes',
            infoEmpty:    'Sin resultados',
            infoFiltered: '(filtrado de _MAX_ en total)',
            lengthMenu:   'Mostrar _MENU_ solicitudes',
            search:       'Buscar:',
            zeroRecords:  'No se encontraron solicitudes coincidentes',
            paginate: { first: '«', last: '»', next: '›', previous: '‹' },
        },
    });

    // Filtro por tabs — busca en columna 5 (Estado)
    $('.filter-tab').on('click', function () {
        $('.filter-tab').removeClass('activo');
        $(this).addClass('activo');
        const filtro = $(this).data('filtro');
        tabla.column(5).search(filtro === 'todos' ? '' : filtro, false, false).draw();
    });
});
</script>
</body>
</html>
