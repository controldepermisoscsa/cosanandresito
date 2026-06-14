<?php
session_start();
require 'conexion.php';

if (!isset($_SESSION['usuario_id']) || strtolower($_SESSION['cargo']) !== 'administrador') {
    header('Location: login.php?mensaje=Acceso denegado.');
    exit();
}

$stmtUsuarios = $pdo->query("
    SELECT u.id_usuario, u.nombre AS usuario_nombre, u.usuario, u.correo, c.nombre_cargo AS cargo, u.area
    FROM usuarios u
    JOIN cargo c ON u.id_cargo = c.id_cargo
    ORDER BY u.id_usuario ASC
");
$usuarios = $stmtUsuarios->fetchAll(PDO::FETCH_ASSOC);

$stmtStats = $pdo->query("
    SELECT
      COUNT(*) AS total,
      COUNT(*) FILTER (WHERE LOWER(c.nombre_cargo) = 'auxiliar')     AS auxiliares,
      COUNT(*) FILTER (WHERE LOWER(c.nombre_cargo) = 'coordinador')  AS coordinadores,
      COUNT(*) FILTER (WHERE LOWER(c.nombre_cargo) IN ('gerente','gerencia')) AS gerentes
    FROM usuarios u
    JOIN cargo c ON u.id_cargo = c.id_cargo
");
$stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

$dias_es  = ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'];
$meses_es = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
$hoy      = new DateTime();
$fecha_es = ucfirst($dias_es[(int)$hoy->format('w')]) . ', ' . $hoy->format('d') . ' de ' . $meses_es[(int)$hoy->format('n') - 1] . ' de ' . $hoy->format('Y');

$mensaje = $_GET['mensaje'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Usuarios</title>
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
        .sidebar-nav { flex: 1; padding: 16px 12px; display: flex; flex-direction: column; gap: 4px; }
        .nav-item { display: flex; align-items: center; gap: 12px; color: #adb5bd; text-decoration: none; padding: 11px 14px; border-radius: 10px; font-size: 14px; font-weight: 500; transition: all 0.2s; }
        .nav-item:hover { background: rgba(255,255,255,0.08); color: #fff; }
        .nav-item.active { background: rgba(243,156,18,0.18); color: #f39c12; font-weight: 600; }
        .nav-item .nav-icon { font-size: 18px; width: 22px; text-align: center; flex-shrink: 0; }
        .sidebar-logout { padding: 12px; border-top: 1px solid rgba(255,255,255,0.08); }
        .sidebar-logout a { display: flex; align-items: center; gap: 10px; color: #e74c3c; text-decoration: none; padding: 10px 14px; border-radius: 10px; font-size: 14px; font-weight: 500; transition: background 0.2s; }
        .sidebar-logout a:hover { background: rgba(231,76,60,0.15); }

        /* ── CONTENT ── */
        .content { flex: 1; overflow-y: auto; padding: 28px 32px; }

        /* ── HEADER ── */
        .page-header { margin-bottom: 24px; }
        .page-title { font-size: 22px; font-weight: 700; color: #1a2535; }
        .page-date  { color: #6c757d; font-size: 13px; margin-top: 4px; }

        /* ── STATS GRID ── */
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
        .stat-card {
            background: #fff; border-radius: 14px; padding: 20px 22px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            display: flex; align-items: center; gap: 16px;
            transition: box-shadow 0.2s, transform 0.2s;
        }
        .stat-card:hover { box-shadow: 0 6px 18px rgba(0,0,0,0.10); transform: translateY(-2px); }
        .stat-icon { width: 46px; height: 46px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 22px; flex-shrink: 0; }
        .si-orange { background: #fff3cd; }
        .si-blue   { background: #dbeafe; }
        .si-green  { background: #d1fae5; }
        .si-purple { background: #ede9fe; }
        .stat-info .stat-num { font-size: 28px; font-weight: 800; color: #1a2535; line-height: 1; }
        .stat-info .stat-lbl { font-size: 12px; color: #6c757d; margin-top: 3px; font-weight: 500; }

        /* ── CARD ── */
        .card { background: #fff; border-radius: 14px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); padding: 22px 24px; }
        .card-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; }
        .card-title { font-size: 14px; font-weight: 700; color: #1a2535; display: flex; align-items: center; gap: 8px; }
        .card-subtitle { font-size: 12px; color: #6c757d; }

        /* ── BADGE ── */
        .badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .badge-admin    { background: #fff3cd; color: #856404; }
        .badge-auxiliar { background: #d1fae5; color: #065f46; }
        .badge-coord    { background: #dbeafe; color: #1e40af; }
        .badge-gerente  { background: #ede9fe; color: #6b21a8; }
        .badge-adm      { background: #f3f4f6; color: #374151; }

        /* ── BUTTONS ── */
        .btn-eliminar {
            display: inline-flex; align-items: center; gap: 5px;
            background: #fee2e2; color: #b91c1c; border: 1.5px solid #fca5a5;
            padding: 6px 12px; border-radius: 8px; font-size: 12px; font-weight: 600;
            cursor: pointer; text-decoration: none; transition: all 0.2s; font-family: inherit;
        }
        .btn-eliminar:hover { background: #fca5a5; border-color: #f87171; }
        .btn-disabled {
            display: inline-flex; align-items: center; gap: 5px;
            background: #f3f4f6; color: #9ca3af; border: 1.5px solid #e5e7eb;
            padding: 6px 12px; border-radius: 8px; font-size: 12px; font-weight: 600;
            cursor: not-allowed;
        }

        /* ── DATATABLES OVERRIDES ── */
        .dt-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; flex-wrap: wrap; gap: 10px; }
        .dt-bottom { display: flex; justify-content: space-between; align-items: center; margin-top: 14px; flex-wrap: wrap; gap: 10px; }
        div.dataTables_wrapper div.dataTables_filter input { border: 1.5px solid #dee2e6; border-radius: 8px; padding: 7px 12px; font-size: 13px; outline: none; width: 220px; }
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
        .alert-danger  { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; border-radius: 10px; padding: 12px 16px; margin-bottom: 20px; font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 8px; }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon">⚙️</div>
        <h2>Coosanandresito</h2>
    </div>
    <div class="sidebar-user">
        <div class="user-name"><?= htmlspecialchars($_SESSION['nombre']) ?></div>
        <div class="user-role">Administrador</div>
    </div>
    <nav class="sidebar-nav">
        <a href="admin_inicio.php" class="nav-item"><span class="nav-icon">🏠</span> Inicio</a>
        <a href="gestionar_usuarios.php" class="nav-item active"><span class="nav-icon">👥</span> Gestionar Usuarios</a>
        <a href="ver_permisos.php" class="nav-item"><span class="nav-icon">📂</span> Mis Permisos</a>
        <a href="solicitar_permiso.php?nuevo=1" class="nav-item"><span class="nav-icon">📝</span> Solicitar Permiso</a>
        <a href="recuperar_tiempo.php" class="nav-item"><span class="nav-icon">⏱️</span> Recuperar Tiempo</a>
    </nav>
    <div class="sidebar-logout">
        <a href="logout.php"><span style="font-size:16px;">🚪</span> Cerrar Sesión</a>
    </div>
</div>

<!-- CONTENT -->
<div class="content">

    <!-- HEADER -->
    <div class="page-header">
        <div class="page-title">Gestionar Usuarios</div>
        <div class="page-date"><?= $fecha_es ?></div>
    </div>

    <!-- STATS -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon si-orange">👥</div>
            <div class="stat-info">
                <div class="stat-num"><?= $stats['total'] ?></div>
                <div class="stat-lbl">Total Usuarios</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon si-green">🧑‍💼</div>
            <div class="stat-info">
                <div class="stat-num"><?= $stats['auxiliares'] ?></div>
                <div class="stat-lbl">Auxiliares</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon si-blue">🗂️</div>
            <div class="stat-info">
                <div class="stat-num"><?= $stats['coordinadores'] ?></div>
                <div class="stat-lbl">Coordinadores</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon si-purple">🏢</div>
            <div class="stat-info">
                <div class="stat-num"><?= $stats['gerentes'] ?></div>
                <div class="stat-lbl">Gerentes</div>
            </div>
        </div>
    </div>

    <!-- ALERT -->
    <?php if (!empty($mensaje)): ?>
    <div class="alert-success"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>

    <!-- TABLE CARD -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">👤 Usuarios del Sistema</div>
            <div class="card-subtitle"><?= count($usuarios) ?> usuario(s) registrado(s)</div>
        </div>

        <table id="tablaUsuarios" class="display" style="width:100%">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Usuario</th>
                    <th>Correo</th>
                    <th>Cargo</th>
                    <th>Área</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $u): ?>
                <?php
                $cl = strtolower($u['cargo']);
                $badgeClass = match(true) {
                    $cl === 'administrador'             => 'badge-admin',
                    $cl === 'auxiliar'                  => 'badge-auxiliar',
                    $cl === 'coordinador'               => 'badge-coord',
                    in_array($cl, ['gerente','gerencia'])=> 'badge-gerente',
                    default                             => 'badge-adm',
                };
                ?>
                <tr>
                    <td><?= htmlspecialchars($u['id_usuario']) ?></td>
                    <td><?= htmlspecialchars($u['usuario_nombre']) ?></td>
                    <td><?= htmlspecialchars($u['usuario']) ?></td>
                    <td><?= htmlspecialchars($u['correo']) ?></td>
                    <td><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($u['cargo']) ?></span></td>
                    <td><?= htmlspecialchars($u['area'] ?? 'N/A') ?></td>
                    <td>
                        <?php if ($u['cargo'] === 'Administrador'): ?>
                            <span class="btn-disabled">🔒 No permitido</span>
                        <?php else: ?>
                            <button class="btn-eliminar"
                                onclick="confirmarEliminacion(<?= $u['id_usuario'] ?>, '<?= htmlspecialchars(addslashes($u['usuario_nombre'])) ?>')">
                                🗑 Eliminar
                            </button>
                        <?php endif; ?>
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
    $('#tablaUsuarios').DataTable({
        dom: '<"dt-top"lf>t<"dt-bottom"ip>',
        pageLength: 10,
        order: [[0, 'asc']],
        columnDefs: [{ orderable: false, targets: 6 }],
        language: {
            lengthMenu:   'Mostrar _MENU_ registros',
            zeroRecords:  'No se encontraron usuarios',
            info:         'Mostrando _START_ a _END_ de _TOTAL_ registros',
            infoEmpty:    'Sin registros disponibles',
            infoFiltered: '(filtrado de _MAX_ registros)',
            search:       'Buscar:',
            paginate: { first:'«', last:'»', next:'›', previous:'‹' }
        }
    });

    <?php if (!empty($mensaje)): ?>
    Swal.fire({ icon: 'success', title: '¡Éxito!', text: <?= json_encode($mensaje) ?>, timer: 3000, timerProgressBar: true, showConfirmButton: false });
    history.replaceState(null, '', 'gestionar_usuarios.php');
    <?php endif; ?>

    async function confirmarEliminacion(idUsuario, nombre) {
        const res = await Swal.fire({
            title: '¿Eliminar usuario?',
            html: `¿Está seguro de que desea eliminar a <strong>${nombre}</strong>?<br><small style="color:#6c757d;">Esta acción no se puede deshacer.</small>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: '🗑 Sí, eliminar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#e74c3c',
            cancelButtonColor: '#adb5bd',
        });
        if (res.isConfirmed) {
            window.location.href = 'eliminar_usuario.php?id=' + idUsuario;
        }
    }
</script>
</body>
</html>
