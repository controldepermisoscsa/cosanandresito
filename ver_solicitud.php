<?php
session_start();
require 'conexion.php';

if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['nombre'])) {
    header('Location: login.php?mensaje=Debes iniciar sesión para acceder a esta página.');
    exit();
}

$cargo = strtolower($_SESSION['cargo'] ?? '');

$esGerente          = in_array($cargo, ['gerente', 'gerencia']);
$esCoordinadorAdmin = in_array($cargo, ['coordinador', 'administrador']);

$panelRegreso = match (strtolower(trim($cargo))) {
    'administrador'       => 'admin_inicio.php',
    'coordinador'         => 'coordinador_inicio.php',
    'auxiliar'            => 'auxiliar_inicio.php',
    'administrativo'      => 'administrativo_inicio.php',
    'gerente', 'gerencia' => 'gerente_inicio.php',
    default               => 'inicio.php',
};

$nombrePanel = match (strtolower(trim($cargo))) {
    'administrador'       => 'Panel de Administrador',
    'coordinador'         => 'Panel de Coordinador',
    'auxiliar'            => 'Panel de Auxiliar',
    'administrativo'      => 'Panel de Administrativo',
    'gerente', 'gerencia' => 'Panel de Gerente',
    default               => 'Panel Principal',
};

$navItems = match (strtolower(trim($cargo))) {
    'auxiliar' => [
        ['href' => 'auxiliar_inicio.php',          'icon' => '🏠', 'label' => 'Inicio'],
        ['href' => 'solicitar_permiso.php?nuevo=1', 'icon' => '📝', 'label' => 'Solicitar Permiso'],
        ['href' => 'ver_permisos.php',             'icon' => '📂', 'label' => 'Mis Permisos'],
        ['href' => 'recuperar_tiempo.php',         'icon' => '⏱️', 'label' => 'Recuperar Tiempo'],
    ],
    'administrativo' => [
        ['href' => 'administrativo_inicio.php',    'icon' => '🏠', 'label' => 'Inicio'],
        ['href' => 'solicitar_permiso.php?nuevo=1','icon' => '📝', 'label' => 'Solicitar Permiso'],
        ['href' => 'ver_permisos.php',             'icon' => '📂', 'label' => 'Mis Permisos'],
    ],
    'coordinador' => [
        ['href' => 'coordinador_inicio.php',       'icon' => '🏠', 'label' => 'Inicio'],
        ['href' => 'solicitar_permiso.php?nuevo=1','icon' => '📝', 'label' => 'Solicitar Permiso'],
        ['href' => 'ver_permisos.php',             'icon' => '📂', 'label' => 'Mis Permisos'],
    ],
    'administrador' => [
        ['href' => 'admin_inicio.php',             'icon' => '🏠', 'label' => 'Inicio'],
        ['href' => 'solicitar_permiso.php?nuevo=1','icon' => '📝', 'label' => 'Solicitar Permiso'],
        ['href' => 'ver_permisos.php',             'icon' => '📂', 'label' => 'Mis Permisos'],
        ['href' => 'gestionar_usuarios.php',       'icon' => '👥', 'label' => 'Gestionar Usuarios'],
    ],
    'gerente', 'gerencia' => [
        ['href' => 'gerente_inicio.php',           'icon' => '🏠', 'label' => 'Inicio'],
        ['href' => 'ver_permisos.php',             'icon' => '📂', 'label' => 'Ver Solicitudes'],
    ],
    default => [['href' => 'inicio.php', 'icon' => '🏠', 'label' => 'Inicio']],
};

$id_permiso = $_GET['id'] ?? null;
if (!$id_permiso) {
    header('Location: ' . $panelRegreso . '?mensaje=ID de permiso no válido.');
    exit();
}

if ($esGerente) {
    $stmt = $pdo->prepare("
        SELECT p.*, u.nombre AS nombre_empleado, u.area, c.nombre_cargo AS cargo
        FROM permisos p
        JOIN usuarios u ON p.id_usuario = u.id_usuario
        JOIN cargo c ON u.id_cargo = c.id_cargo
        WHERE p.id_permiso = :id_permiso
    ");
    $stmt->execute(['id_permiso' => $id_permiso]);
} else {
    $stmt = $pdo->prepare("
        SELECT p.*, u.nombre AS nombre_empleado, u.area, c.nombre_cargo AS cargo
        FROM permisos p
        JOIN usuarios u ON p.id_usuario = u.id_usuario
        JOIN cargo c ON u.id_cargo = c.id_cargo
        WHERE p.id_permiso = :id_permiso AND p.id_usuario = :id_usuario
    ");
    $stmt->execute(['id_permiso' => $id_permiso, 'id_usuario' => $_SESSION['usuario_id']]);
}

$permiso = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$permiso) {
    header("Location: {$panelRegreso}?mensaje=Permiso no encontrado o no tienes acceso.");
    exit();
}

function calcularTiempoAusencia($fecha_salida, $hora_salida, $fecha_regreso_aprox, $hora_regreso_aprox) {
    $horariosLaborales = [
        1 => [['07:30', '12:00'], ['14:00', '17:30']],
        2 => [['07:30', '12:00'], ['14:00', '17:30']],
        3 => [['07:30', '12:00'], ['14:00', '17:30']],
        4 => [['07:30', '12:00'], ['14:00', '17:30']],
        5 => [['07:30', '12:00'], ['14:00', '17:00']],
        6 => [['08:00', '12:30']],
    ];
    $fechaInicio  = new DateTime("{$fecha_salida} {$hora_salida}");
    $fechaFin     = new DateTime("{$fecha_regreso_aprox} {$hora_regreso_aprox}");
    $totalMinutos = 0;
    $fechaActual  = clone $fechaInicio;

    while ($fechaActual <= $fechaFin) {
        $diaSemana = (int)$fechaActual->format('N');
        $fechaStr  = $fechaActual->format('Y-m-d');
        if ($diaSemana <= 6 && isset($horariosLaborales[$diaSemana])) {
            foreach ($horariosLaborales[$diaSemana] as $rango) {
                $inicioRango = new DateTime("{$fechaStr} {$rango[0]}");
                $finRango    = new DateTime("{$fechaStr} {$rango[1]}");
                $inicio = max($fechaInicio, $inicioRango);
                $fin    = min($fechaFin, $finRango);
                if ($inicio < $fin) {
                    $diff = $fin->diff($inicio);
                    $totalMinutos += ($diff->h * 60) + $diff->i;
                }
            }
        }
        $fechaActual->add(new DateInterval('P1D'));
        $fechaActual->setTime(0, 0, 0);
    }
    return $totalMinutos;
}

function formatearHoraAMPM($hora) {
    return date('g:i A', strtotime($hora));
}

$tiempoAusenciaMinutos = 0;
if ($permiso['fecha_salida'] && $permiso['hora_salida'] && $permiso['fecha_regreso_aprox'] && $permiso['hora_regreso_aprox']) {
    $tiempoAusenciaMinutos = calcularTiempoAusencia(
        $permiso['fecha_salida'],
        $permiso['hora_salida'],
        $permiso['fecha_regreso_aprox'],
        $permiso['hora_regreso_aprox']
    );
}
$horasAusencia   = floor($tiempoAusenciaMinutos / 60);
$minutosAusencia = $tiempoAusenciaMinutos % 60;

$cargoSolicitante     = strtolower($permiso['cargo']);
$puedeEditarEncargado = $esGerente && in_array($cargoSolicitante, ['administrador', 'coordinador', 'administrativo']);

// ── TRACKER DE PROGRESO ──
$incluyeCoordinador = ($cargoSolicitante === 'auxiliar');

if ($incluyeCoordinador) {
    $tracker_pasos = [
        ['label' => 'Solicitada',   'icon' => '📝', 'desc' => 'Permiso enviado al sistema'],
        ['label' => 'Coordinador',  'icon' => '👨‍💼', 'desc' => 'En revisión del coordinador'],
        ['label' => 'Gerente',      'icon' => '🏢', 'desc' => 'En revisión del gerente'],
        ['label' => 'Aprobada',     'icon' => '✅', 'desc' => 'Permiso aprobado'],
        ['label' => 'Finalizada',   'icon' => '🏁', 'desc' => 'Regreso registrado'],
    ];
} else {
    $tracker_pasos = [
        ['label' => 'Solicitada',   'icon' => '📝', 'desc' => 'Permiso enviado al sistema'],
        ['label' => 'Gerente',      'icon' => '🏢', 'desc' => 'En revisión del gerente'],
        ['label' => 'Aprobada',     'icon' => '✅', 'desc' => 'Permiso aprobado'],
        ['label' => 'Finalizada',   'icon' => '🏁', 'desc' => 'Regreso registrado'],
    ];
}

$estadoPermiso = $permiso['estado'];
$asignadoA     = $permiso['asignado_a'] ?? '';

// Calcular qué paso está activo (0 = primer paso)
if ($estadoPermiso === 'finalizado') {
    $tracker_paso_actual = count($tracker_pasos) - 1;
    $tracker_modo        = 'done';
} elseif ($estadoPermiso === 'aprobado') {
    $tracker_paso_actual = count($tracker_pasos) - 2;
    $tracker_modo        = 'done';
} elseif ($estadoPermiso === 'rechazado') {
    if ($incluyeCoordinador) {
        $tracker_paso_actual = ($asignadoA === 'coordinador' || $asignadoA === 'auxiliar') ? 1 : 2;
    } else {
        $tracker_paso_actual = 1;
    }
    $tracker_modo = 'error';
} elseif ($estadoPermiso === 'cancelado') {
    $tracker_paso_actual = 0;
    $tracker_modo        = 'cancelled';
} elseif (in_array($estadoPermiso, ['pendiente', 'reenviado'])) {
    if ($incluyeCoordinador) {
        $tracker_paso_actual = ($asignadoA === 'gerente') ? 2 : 1;
    } else {
        $tracker_paso_actual = 1;
    }
    $tracker_modo = ($estadoPermiso === 'reenviado') ? 'reenviado' : 'active';
} else {
    $tracker_paso_actual = 0;
    $tracker_modo        = 'active';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalles del Permiso #<?= htmlspecialchars($permiso['id_permiso']) ?></title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
        .nav-item:hover { background: rgba(255,255,255,0.08); color: #fff; }
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

        /* ── PAGE HEADER ── */
        .page-header {
            text-align: center;
            margin-bottom: 28px;
        }
        .page-header h1 {
            font-size: 22px;
            color: #1a2535;
            font-weight: 700;
        }
        .page-header .sub {
            color: #6c757d;
            font-size: 13px;
            margin-top: 4px;
        }

        /* ── CARDS ── */
        .card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.07);
            padding: 0;
            margin-bottom: 20px;
            overflow: hidden;
        }
        .card-header {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 16px 22px;
            border-bottom: 1px solid #f0f2f5;
            background: #fafbfc;
        }
        .card-header-icon {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
        }
        .card-header-icon.blue   { background: #e8f0fe; }
        .card-header-icon.orange { background: #fff3e0; }
        .card-header-text { font-size: 14px; font-weight: 700; color: #1a2535; }
        .card-header-sub  { font-size: 11px; color: #9ca3af; margin-top: 1px; }
        .card-body { padding: 18px 22px; }
        .card-title {
            font-size: 14px;
            font-weight: 700;
            color: #1a2535;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* ── TWO-COLUMN INFO GRID ── */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        .info-grid .card { margin-bottom: 0; }

        /* ── DETAIL ROWS ── */
        .detail-row {
            display: flex;
            align-items: center;
            padding: 9px 0;
            border-bottom: 1px solid #f5f6f8;
            gap: 14px;
        }
        .detail-row:last-child { border-bottom: none; }
        .detail-label {
            color: #9ca3af;
            font-size: 10.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            min-width: 110px;
            flex-shrink: 0;
            padding-top: 2px;
        }
        .detail-value {
            color: #1a2535;
            font-size: 14px;
            font-weight: 500;
            word-break: break-word;
            flex: 1;
        }
        .hora-val { color: #f39c12; font-weight: 700; }

        /* ── BADGES ── */
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

        /* ── FECHAS VISUAL ── */
        .fechas-grid {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            gap: 0;
            align-items: center;
        }
        .fecha-col {
            text-align: center;
            padding: 10px 8px;
        }
        .fecha-col-label {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 10px;
        }
        .fecha-col-label.salida  { color: #f39c12; }
        .fecha-col-label.regreso { color: #10b981; }
        .fecha-icon-wrap {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            margin: 0 auto 10px;
        }
        .fecha-icon-wrap.salida  { background: #fff8ec; }
        .fecha-icon-wrap.regreso { background: #ecfdf5; }
        .fecha-date {
            font-size: 15px;
            font-weight: 700;
            color: #1a2535;
            margin-bottom: 4px;
        }
        .fecha-time {
            font-size: 18px;
            font-weight: 800;
        }
        .fecha-time.salida  { color: #f39c12; }
        .fecha-time.regreso { color: #10b981; }
        .fechas-arrow {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            padding: 0 6px;
        }
        .fechas-arrow-line {
            width: 40px;
            height: 2px;
            background: linear-gradient(90deg, #f39c12, #10b981);
            border-radius: 2px;
        }
        .fechas-arrow-tip {
            font-size: 14px;
            color: #9ca3af;
        }

        /* ── TIEMPO AUSENCIA ── */
        .tiempo-card {
            background: linear-gradient(135deg, #fffaf0 0%, #fff3e0 100%);
            border-radius: 16px;
            border: 1.5px solid #f8c86a;
            padding: 22px 24px;
            margin-bottom: 20px;
            text-align: center;
        }
        .tiempo-card .card-title { justify-content: center; color: #e67e22; }
        .tiempo-inner {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 24px;
            padding: 6px 0 4px;
        }
        .tiempo-bloque {
            text-align: center;
        }
        .tiempo-num {
            font-size: 42px;
            font-weight: 900;
            color: #e67e22;
            line-height: 1;
            letter-spacing: -1px;
        }
        .tiempo-unit {
            font-size: 12px;
            font-weight: 700;
            color: #f39c12;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 4px;
        }
        .tiempo-sep {
            font-size: 36px;
            font-weight: 900;
            color: #f8c86a;
            line-height: 1;
            margin-bottom: 14px;
        }
        .tiempo-valor {
            font-size: 34px;
            font-weight: 800;
            color: #e67e22;
            margin-top: 2px;
        }

        /* ── RECHAZO CARD ── */
        .rechazo-card {
            background: #fff5f5;
            border-radius: 14px;
            border: 1.5px solid #f8d7da;
            padding: 20px 24px;
            margin-bottom: 20px;
        }
        .rechazo-card .card-title { color: #842029; }
        .rechazo-text {
            color: #721c24;
            font-size: 14px;
            font-style: italic;
            line-height: 1.7;
            margin: 0;
        }

        /* ── ACTIONS CARD ── */
        .actions-card {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            padding: 22px 24px;
            margin-bottom: 20px;
        }

        /* ── ENCARGADO BOX ── */
        .encargado-box {
            background: #f0f8ff;
            border-radius: 12px;
            border: 1.5px solid #b8d9f5;
            padding: 16px 20px;
            margin-bottom: 20px;
        }
        .encargado-box .box-title {
            font-size: 13px;
            font-weight: 700;
            color: #1565c0;
            margin-bottom: 8px;
        }
        .encargado-box p {
            font-size: 13px;
            color: #495057;
            margin-bottom: 10px;
        }

        /* ── FORM ── */
        .form-group { margin-bottom: 16px; }
        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #495057;
            margin-bottom: 6px;
        }
        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid #dee2e6;
            border-radius: 10px;
            font-size: 14px;
            color: #1a2535;
            background: #fff;
            transition: border-color 0.2s, box-shadow 0.2s;
            box-sizing: border-box;
            font-family: inherit;
        }
        .form-control:focus {
            outline: none;
            border-color: #f39c12;
            box-shadow: 0 0 0 3px rgba(243,156,18,0.12);
        }
        textarea.form-control { resize: vertical; min-height: 100px; }
        .form-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        /* ── BUTTONS ── */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 11px 22px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            text-decoration: none;
            transition: all 0.2s;
            font-family: inherit;
        }
        .btn:disabled { opacity: 0.6; pointer-events: none; }
        .btn-approve {
            background: linear-gradient(135deg, #28a745, #218838);
            color: #fff;
            box-shadow: 0 4px 12px rgba(40,167,69,0.3);
        }
        .btn-approve:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(40,167,69,0.4); }
        .btn-reject {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: #fff;
            box-shadow: 0 4px 12px rgba(220,53,69,0.3);
        }
        .btn-reject:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(220,53,69,0.4); }
        .btn-resend {
            background: linear-gradient(135deg, #17a2b8, #138496);
            color: #fff;
            box-shadow: 0 4px 12px rgba(23,162,184,0.3);
        }
        .btn-resend:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(23,162,184,0.4); }
        .btn-finalize {
            background: linear-gradient(135deg, #0d9488, #0f766e);
            color: #fff;
            box-shadow: 0 4px 12px rgba(13,148,136,0.3);
            font-size: 14px;
            padding: 10px 22px;
        }
        .btn-finalize:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(13,148,136,0.4); }
        .regreso-box {
            margin-top: 18px;
            border-top: 1px solid #e9ecef;
            padding-top: 18px;
        }
        .regreso-info {
            background: #f0fdf4;
            border: 1px solid #a7f3d0;
            border-radius: 10px;
            padding: 16px 18px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            font-size: 13px;
            margin-top: 10px;
        }
        .regreso-info .ri-label { color: #6c757d; margin-bottom: 2px; font-size: 12px; }
        .regreso-info .ri-value { font-weight: 700; color: #065f46; font-size: 14px; }
        .regreso-info .ri-full  { grid-column: 1 / -1; }
        .btn-cancel-perm {
            background: linear-gradient(135deg, #6c757d, #545b62);
            color: #fff;
            box-shadow: 0 4px 12px rgba(108,117,125,0.3);
        }
        .btn-cancel-perm:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(108,117,125,0.4); }
        .btn-save {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: #fff;
            box-shadow: 0 4px 12px rgba(243,156,18,0.3);
        }
        .btn-save:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(243,156,18,0.4); }

        .btn-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 8px;
        }

        /* ── STATUS INFO (estados finales) ── */
        .status-info {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 6px 0 4px;
        }
        .status-icon { font-size: 34px; }
        .status-text .s-title { font-size: 16px; font-weight: 700; color: #1a2535; }
        .status-text .s-sub   { font-size: 13px; color: #6c757d; margin-top: 3px; }

        /* ── TRACKER DE PROGRESO ── */
        .tracker-wrap {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            padding: 24px 32px 20px;
            margin-bottom: 20px;
        }
        .tracker-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #9ca3af;
            margin-bottom: 20px;
        }
        .tracker {
            display: flex;
            align-items: flex-start;
            position: relative;
        }
        .tracker-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            flex: 1;
            position: relative;
            z-index: 1;
        }
        /* línea entre pasos */
        .tracker-step:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 20px;
            left: 50%;
            width: 100%;
            height: 3px;
            background: #e9ecef;
            z-index: 0;
            transition: background 0.4s;
        }
        .tracker-step.done:not(:last-child)::after,
        .tracker-step.prev-done:not(:last-child)::after {
            background: linear-gradient(90deg, #10b981, #10b981);
        }
        /* círculo del paso */
        .tracker-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 17px;
            font-weight: 700;
            border: 3px solid #e9ecef;
            background: #f8f9fa;
            color: #adb5bd;
            position: relative;
            z-index: 2;
            transition: all 0.3s;
            flex-shrink: 0;
        }
        .tracker-step.done .tracker-circle {
            background: #10b981;
            border-color: #10b981;
            color: #fff;
            box-shadow: 0 0 0 4px rgba(16,185,129,0.15);
        }
        .tracker-step.active .tracker-circle {
            background: #fff;
            border-color: #f39c12;
            color: #f39c12;
            box-shadow: 0 0 0 4px rgba(243,156,18,0.18);
            animation: pulse-ring 1.8s ease-in-out infinite;
        }
        .tracker-step.error .tracker-circle {
            background: #ef4444;
            border-color: #ef4444;
            color: #fff;
            box-shadow: 0 0 0 4px rgba(239,68,68,0.15);
        }
        .tracker-step.reenviado .tracker-circle {
            background: #fff;
            border-color: #3b82f6;
            color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59,130,246,0.15);
            animation: pulse-ring-blue 1.8s ease-in-out infinite;
        }
        .tracker-step.cancelled .tracker-circle {
            background: #6c757d;
            border-color: #6c757d;
            color: #fff;
        }
        /* etiqueta */
        .tracker-label {
            font-size: 11px;
            font-weight: 700;
            color: #adb5bd;
            margin-top: 8px;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .tracker-step.done .tracker-label    { color: #059669; }
        .tracker-step.active .tracker-label  { color: #d97706; }
        .tracker-step.error .tracker-label   { color: #ef4444; }
        .tracker-step.reenviado .tracker-label { color: #3b82f6; }
        .tracker-step.cancelled .tracker-label { color: #6c757d; }
        /* descripción debajo */
        .tracker-desc {
            font-size: 10px;
            color: #9ca3af;
            margin-top: 3px;
            text-align: center;
            max-width: 90px;
            line-height: 1.3;
        }
        .tracker-step.active .tracker-desc  { color: #f39c12; font-weight: 600; }
        .tracker-step.error .tracker-desc   { color: #ef4444; }
        .tracker-step.reenviado .tracker-desc { color: #3b82f6; font-weight: 600; }
        /* banner de estado debajo del tracker */
        .tracker-banner {
            margin-top: 16px;
            padding: 10px 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            gap: 8px;
        }
        .tracker-banner.active   { background: #fff8ec; color: #92400e; border: 1px solid #fcd97a; }
        .tracker-banner.done     { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .tracker-banner.error    { background: #fef2f2; color: #991b1b; border: 1px solid #fca5a5; }
        .tracker-banner.reenviado{ background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }
        .tracker-banner.cancelled{ background: #f3f4f6; color: #374151; border: 1px solid #d1d5db; }

        @keyframes pulse-ring {
            0%   { box-shadow: 0 0 0 0   rgba(243,156,18,0.4); }
            70%  { box-shadow: 0 0 0 8px rgba(243,156,18,0); }
            100% { box-shadow: 0 0 0 0   rgba(243,156,18,0); }
        }
        @keyframes pulse-ring-blue {
            0%   { box-shadow: 0 0 0 0   rgba(59,130,246,0.4); }
            70%  { box-shadow: 0 0 0 8px rgba(59,130,246,0); }
            100% { box-shadow: 0 0 0 0   rgba(59,130,246,0); }
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
        <div class="user-role"><?= htmlspecialchars(ucfirst($cargo)) ?></div>
    </div>
    <nav class="sidebar-nav">
        <?php foreach ($navItems as $item): ?>
        <a href="<?= htmlspecialchars($item['href']) ?>" class="nav-item">
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
        <h1>📄 Detalles del Permiso #<?= htmlspecialchars($permiso['id_permiso']) ?></h1>
        <div class="sub">Información completa del permiso de ausencia</div>
    </div>

    <!-- TRACKER DE PROGRESO -->
    <?php
    $bannerMsg = match(true) {
        $tracker_modo === 'active' && $estadoPermiso === 'pendiente' =>
            '⏳ Tu solicitud está siendo revisada. Te notificaremos cuando haya novedades.',
        $tracker_modo === 'reenviado' =>
            '🔄 Solicitud reenviada — en espera de nueva revisión.',
        $tracker_modo === 'done' && $estadoPermiso === 'aprobado' =>
            '✅ ¡Tu permiso fue aprobado! Recuerda registrar tu regreso al finalizar.',
        $tracker_modo === 'done' && $estadoPermiso === 'finalizado' =>
            '🏁 Proceso completado. El permiso ha sido cerrado exitosamente.',
        $tracker_modo === 'error' =>
            '❌ Tu solicitud fue rechazada. Puedes corregirla y reenviarla desde esta misma página.',
        $tracker_modo === 'cancelled' =>
            '🚫 Esta solicitud fue cancelada definitivamente.',
        default => '',
    };
    ?>
    <div class="tracker-wrap">
        <div class="tracker-title">Estado de tu solicitud</div>
        <div class="tracker">
            <?php foreach ($tracker_pasos as $i => $paso):
                if ($i < $tracker_paso_actual) {
                    $cls = 'done';
                } elseif ($i === $tracker_paso_actual) {
                    $cls = $tracker_modo === 'done' ? 'done' : $tracker_modo;
                } else {
                    $cls = 'pending';
                }
                // Añadir clase extra a los pasos anteriores completados para que la línea se pinte
                $extraCls = ($i < $tracker_paso_actual) ? ' prev-done' : '';
            ?>
            <div class="tracker-step <?= $cls . $extraCls ?>">
                <div class="tracker-circle">
                    <?php if ($cls === 'done'): ?>✓
                    <?php elseif ($cls === 'error'): ?>✕
                    <?php elseif ($cls === 'cancelled'): ?>✕
                    <?php else: ?><?= $paso['icon'] ?>
                    <?php endif; ?>
                </div>
                <div class="tracker-label"><?= $paso['label'] ?></div>
                <?php if ($i === $tracker_paso_actual): ?>
                <div class="tracker-desc"><?= $paso['desc'] ?></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php if ($bannerMsg): ?>
        <div class="tracker-banner <?= $tracker_modo ?>">
            <?= $bannerMsg ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- INFO GRID: empleado + fechas -->
    <div class="info-grid">

        <div class="card">
            <div class="card-header">
                <div class="card-header-icon blue">👤</div>
                <div>
                    <div class="card-header-text">Datos del Empleado</div>
                    <div class="card-header-sub">Información del solicitante</div>
                </div>
            </div>
            <div class="card-body">
                <div class="detail-row">
                    <span class="detail-label">Empleado</span>
                    <span class="detail-value"><?= htmlspecialchars($permiso['nombre_empleado']) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Área</span>
                    <span class="detail-value"><?= htmlspecialchars($permiso['area']) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Cargo</span>
                    <span class="detail-value"><?= htmlspecialchars($permiso['cargo']) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Tipo Permiso</span>
                    <span class="detail-value"><?= htmlspecialchars($permiso['tipo_permiso']) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Motivo</span>
                    <span class="detail-value"><?= htmlspecialchars($permiso['motivo']) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Estado</span>
                    <span class="detail-value">
                        <span class="badge badge-<?= htmlspecialchars($permiso['estado']) ?>">
                            <?= ucfirst(htmlspecialchars($permiso['estado'])) ?>
                        </span>
                    </span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Encargado</span>
                    <span class="detail-value"><?= htmlspecialchars($permiso['encargado_ausencia'] ?? 'No especificado') ?></span>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-header-icon orange">📅</div>
                <div>
                    <div class="card-header-text">Fechas y Horario</div>
                    <div class="card-header-sub">Salida y regreso aproximado</div>
                </div>
            </div>
            <div class="card-body">
                <div class="fechas-grid">
                    <div class="fecha-col">
                        <div class="fecha-col-label salida">Salida</div>
                        <div class="fecha-icon-wrap salida">🚀</div>
                        <div class="fecha-date"><?= date('d/m/Y', strtotime($permiso['fecha_salida'])) ?></div>
                        <div class="fecha-time salida"><?= formatearHoraAMPM($permiso['hora_salida']) ?></div>
                    </div>
                    <div class="fechas-arrow">
                        <div class="fechas-arrow-line"></div>
                        <div class="fechas-arrow-tip">→</div>
                    </div>
                    <div class="fecha-col">
                        <div class="fecha-col-label regreso">Regreso</div>
                        <div class="fecha-icon-wrap regreso">🏠</div>
                        <div class="fecha-date"><?= date('d/m/Y', strtotime($permiso['fecha_regreso_aprox'])) ?></div>
                        <div class="fecha-time regreso"><?= formatearHoraAMPM($permiso['hora_regreso_aprox']) ?></div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- TIEMPO AUSENCIA -->
    <div class="tiempo-card">
        <div class="card-title">⏰ Tiempo Aproximado de Ausencia en Horario Laboral</div>
        <div class="tiempo-inner">
            <div class="tiempo-bloque">
                <div class="tiempo-num"><?= $horasAusencia ?></div>
                <div class="tiempo-unit">horas</div>
            </div>
            <div class="tiempo-sep">:</div>
            <div class="tiempo-bloque">
                <div class="tiempo-num"><?= str_pad($minutosAusencia, 2, '0', STR_PAD_LEFT) ?></div>
                <div class="tiempo-unit">minutos</div>
            </div>
        </div>
    </div>

    <!-- MOTIVO RECHAZO -->
    <?php if ($permiso['estado'] === 'rechazado' && !empty($permiso['motivo_rechazo'])): ?>
    <div class="rechazo-card">
        <div class="card-title">❌ Motivo del Rechazo</div>
        <p class="rechazo-text"><?= htmlspecialchars($permiso['motivo_rechazo']) ?></p>
    </div>
    <?php endif; ?>

    <!-- ── ACCIONES ── -->

    <?php if ($esGerente && in_array($permiso['estado'], ['pendiente', 'reenviado']) && $permiso['asignado_a'] === 'gerente'): ?>

    <div class="actions-card">
        <div class="card-title">⚙️ Acciones del Gerente</div>

        <form id="formAccion">
            <input type="hidden" name="id_permiso" value="<?= $permiso['id_permiso'] ?>">

            <?php if ($puedeEditarEncargado): ?>
            <div class="encargado-box">
                <div class="box-title">👨‍💼 Asignar Persona Encargada</div>
                <p>Como gerente, puedes asignar quién estará encargado durante la ausencia de este empleado.</p>
                <div class="form-group" style="margin-bottom:0;">
                    <label for="encargado_ausencia_gerente" class="form-label">Persona Encargada en Ausencia</label>
                    <input type="text"
                           id="encargado_ausencia_gerente"
                           name="encargado_ausencia"
                           class="form-control"
                           value="<?= htmlspecialchars($permiso['encargado_ausencia'] ?? '') ?>"
                           placeholder="Nombre completo de la persona encargada">
                    <small style="color:#6c757d;font-style:italic;font-size:12px;margin-top:4px;display:block;">
                        Campo opcional. Déjalo vacío si no aplica.
                    </small>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($permiso['estado'] === 'pendiente'): ?>
                <div class="form-group">
                    <label for="motivo_rechazo" class="form-label">
                        Motivo del rechazo <span style="color:#dc3545">*</span>
                        <span style="font-weight:400;color:#6c757d;">(requerido solo para rechazar)</span>
                    </label>
                    <textarea id="motivo_rechazo" name="motivo_rechazo" class="form-control"
                              placeholder="Explica detalladamente por qué rechazas este permiso..."></textarea>
                </div>
                <div class="btn-row">
                    <button type="button" onclick="procesarAccion('aprobar')" class="btn btn-approve">✅ Aprobar</button>
                    <button type="button" onclick="procesarAccion('rechazar')" class="btn btn-reject">❌ Rechazar</button>
                </div>
            <?php else: ?>
                <div class="btn-row">
                    <button type="button" onclick="procesarAccion('aprobar')" class="btn btn-approve">✅ Aprobar</button>
                    <button type="button" onclick="procesarAccion('cancelar')" class="btn btn-cancel-perm">🚫 Cancelar Definitivamente</button>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <?php elseif ($permiso['estado'] === 'rechazado' && $permiso['id_usuario'] == $_SESSION['usuario_id']): ?>

    <div class="actions-card">
        <div class="card-title">🔄 Corregir y Reenviar Permiso</div>
        <p style="color:#6c757d;font-size:13px;margin-bottom:20px;">
            Tu permiso fue rechazado. Corrige los datos y reenvíalo para nueva revisión.
        </p>

        <form id="formActualizarCampos">
            <input type="hidden" name="id_permiso" value="<?= $permiso['id_permiso'] ?>">
            <input type="hidden" name="accion" value="actualizar_campos">

            <div class="form-group">
                <label for="motivo_edit" class="form-label">Motivo <span style="color:#dc3545">*</span></label>
                <textarea id="motivo_edit" name="motivo" class="form-control"
                          placeholder="Describe el motivo de tu permiso..."><?= htmlspecialchars($permiso['motivo']) ?></textarea>
            </div>

            <div class="form-grid-2">
                <div class="form-group">
                    <label for="fecha_salida_edit" class="form-label">Fecha de Salida <span style="color:#dc3545">*</span></label>
                    <input type="date" id="fecha_salida_edit" name="fecha_salida"
                           class="form-control" value="<?= $permiso['fecha_salida'] ?>">
                </div>
                <div class="form-group">
                    <label for="hora_salida_edit" class="form-label">Hora de Salida <span style="color:#dc3545">*</span></label>
                    <input type="time" id="hora_salida_edit" name="hora_salida"
                           class="form-control" value="<?= $permiso['hora_salida'] ?>">
                </div>
            </div>

            <div class="form-grid-2">
                <div class="form-group">
                    <label for="fecha_regreso_edit" class="form-label">Fecha de Regreso <span style="color:#dc3545">*</span></label>
                    <input type="date" id="fecha_regreso_edit" name="fecha_regreso_aprox"
                           class="form-control" value="<?= $permiso['fecha_regreso_aprox'] ?>">
                </div>
                <div class="form-group">
                    <label for="hora_regreso_edit" class="form-label">Hora de Regreso <span style="color:#dc3545">*</span></label>
                    <input type="time" id="hora_regreso_edit" name="hora_regreso_aprox"
                           class="form-control" value="<?= $permiso['hora_regreso_aprox'] ?>">
                </div>
            </div>

            <div class="btn-row">
                <button type="button" onclick="actualizarCampos()" class="btn btn-save">💾 Guardar Cambios</button>
                <button type="button" onclick="reenviarPermiso()" class="btn btn-resend">
                    🔄 Reenviar <?= $esCoordinadorAdmin ? 'al Gerente' : 'al Coordinador' ?>
                </button>
            </div>
        </form>
    </div>

    <?php elseif (in_array($permiso['estado'], ['cancelado', 'rechazado', 'aprobado', 'finalizado'])): ?>

    <div class="actions-card">
        <div class="status-info">
            <?php
            $iconoEstado = match ($permiso['estado']) {
                'aprobado'   => '✅',
                'rechazado'  => '❌',
                'cancelado'  => '🚫',
                'finalizado' => '🏁',
                default      => 'ℹ️',
            };
            $textoEstado = match ($permiso['estado']) {
                'aprobado'   => 'Este permiso fue aprobado exitosamente.',
                'rechazado'  => 'Este permiso fue rechazado.',
                'cancelado'  => 'Este permiso fue cancelado definitivamente.',
                'finalizado' => 'Este permiso ha sido completado.',
                default      => '',
            };
            ?>
            <div class="status-icon"><?= $iconoEstado ?></div>
            <div class="status-text">
                <div class="s-title">Permiso <?= ucfirst(htmlspecialchars($permiso['estado'])) ?></div>
                <div class="s-sub"><?= $textoEstado ?></div>
            </div>
        </div>

        <?php if ($permiso['estado'] === 'aprobado' && $permiso['id_usuario'] == $_SESSION['usuario_id']): ?>
        <div class="regreso-box">
            <p style="font-size:13px;color:#6c757d;margin-bottom:14px;">
                Cuando regreses de tu ausencia, registra tu hora de llegada para cerrar el proceso.
            </p>
            <button type="button" onclick="registrarRegreso()" class="btn btn-finalize">
                📍 Registrar mi Regreso
            </button>
        </div>
        <?php endif; ?>

        <?php if ($permiso['estado'] === 'finalizado' && !empty($permiso['fecha_regreso_real'])): ?>
        <div class="regreso-box">
            <div style="font-size:13px;font-weight:700;color:#065f46;margin-bottom:8px;">📋 Regreso Registrado</div>
            <div class="regreso-info">
                <div>
                    <div class="ri-label">Fecha de regreso</div>
                    <div class="ri-value"><?= date('d/m/Y', strtotime($permiso['fecha_regreso_real'])) ?></div>
                </div>
                <div>
                    <div class="ri-label">Hora de regreso</div>
                    <div class="ri-value"><?= date('g:i A', strtotime($permiso['hora_regreso_real'])) ?></div>
                </div>
                <?php if (!empty($permiso['tiempo_total_ausencia'])): ?>
                <div class="ri-full">
                    <div class="ri-label">Tiempo total de ausencia</div>
                    <div class="ri-value"><?= htmlspecialchars($permiso['tiempo_total_ausencia']) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <?php endif; ?>

</div><!-- .content -->

<script>
    let _finalizando = false;
    async function registrarRegreso() {
        console.log('[registrarRegreso] llamada _finalizando=' + _finalizando);
        if (_finalizando) { console.warn('[registrarRegreso] bloqueado'); return; }
        _finalizando = true;

        const res = await Swal.fire({
            title: '¿Registrar tu regreso?',
            text: 'Se registrará la hora actual como tu hora de regreso y el permiso quedará finalizado.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '📍 Sí, registrar regreso',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#0d9488',
            cancelButtonColor: '#adb5bd',
        });

        if (!res.isConfirmed) {
            _finalizando = false;
            return;
        }

        Swal.fire({ title: 'Registrando...', allowOutsideClick: false, allowEscapeKey: false, didOpen: () => Swal.showLoading() });

        const fd = new FormData();
        fd.append('id_permiso', <?= (int)$permiso['id_permiso'] ?>);

        fetch('finalizar_permiso.php', { method: 'POST', credentials: 'same-origin', body: fd })
        .then(r => r.json())
        .then(data => {
            console.log('[registrarRegreso] respuesta:', data);
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Regreso registrado!',
                    html: `Tu regreso fue registrado a las <strong>${data.hora_regreso}</strong>.<br>Tiempo de ausencia: <strong>${data.tiempo_total_ausencia}</strong>`,
                    confirmButtonText: 'Ir al panel',
                    confirmButtonColor: '#0d9488',
                }).then(() => {
                    window.location.href = '<?= $panelRegreso ?>';
                });
            } else {
                _finalizando = false;
                Swal.fire({ icon: 'error', title: 'Error', text: data.error || 'No se pudo registrar el regreso.' });
            }
        })
        .catch(err => {
            console.error('[registrarRegreso] error:', err.message);
            _finalizando = false;
            Swal.fire({ icon: 'error', title: 'Error de conexión', text: err.message });
        });
    }

    let _procesando = false;

    async function procesarAccion(accion) {
        console.log('[procesarAccion] llamada accion=' + accion + ' _procesando=' + _procesando);
        if (_procesando) { console.warn('[procesarAccion] bloqueado — ya en proceso'); return; }
        _procesando = true;
        console.log('[procesarAccion] flag activado');
        const form = document.getElementById('formAccion');

        if (accion === 'rechazar') {
            const motivo = document.getElementById('motivo_rechazo').value.trim();
            if (!motivo) {
                _procesando = false;
                Swal.fire({ icon: 'warning', title: 'Campo requerido', text: 'El motivo del rechazo es obligatorio.' });
                document.getElementById('motivo_rechazo').focus();
                return;
            }
            if (motivo.length < 10) {
                _procesando = false;
                Swal.fire({ icon: 'warning', title: 'Texto muy corto', text: 'El motivo debe tener al menos 10 caracteres.' });
                document.getElementById('motivo_rechazo').focus();
                return;
            }
        }

        const textos = {
            aprobar:  { title: '¿Aprobar este permiso?',           icon: 'question', confirm: '✅ Sí, aprobar',   color: '#28a745' },
            rechazar: { title: '¿Rechazar este permiso?',          icon: 'warning',  confirm: '❌ Sí, rechazar',  color: '#dc3545' },
            cancelar: { title: '¿Cancelar definitivamente?',       icon: 'warning',  confirm: '🚫 Sí, cancelar', color: '#6c757d' },
        };

        const t = textos[accion];
        const resultado = await Swal.fire({
            title: t.title,
            icon: t.icon,
            showCancelButton: true,
            confirmButtonText: t.confirm,
            cancelButtonText: 'No, volver',
            confirmButtonColor: t.color,
            cancelButtonColor: '#adb5bd',
        });
        if (!resultado.isConfirmed) {
            console.log('[procesarAccion] cancelado por usuario');
            _procesando = false;
            return;
        }

        console.log('[procesarAccion] confirmado — enviando fetch accion=' + accion);
        Swal.fire({ title: 'Procesando...', allowOutsideClick: false, allowEscapeKey: false, didOpen: () => Swal.showLoading() });

        const formData = new FormData(form);
        formData.set('accion', accion);

        const encargadoField = document.getElementById('encargado_ausencia_gerente');
        if (encargadoField) formData.set('encargado_ausencia', encargadoField.value.trim());

        fetch('permisos_acciones.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
            body: formData
        })
        .then(r => {
            if (!r.ok) return r.text().then(t => { throw new Error(`HTTP ${r.status}: ${t}`); });
            return r.text().then(txt => {
                try { return JSON.parse(txt); }
                catch (e) { throw new Error('Respuesta no es JSON válido: ' + txt); }
            });
        })
        .then(data => {
            console.log('[procesarAccion] respuesta servidor:', data);
            if (data && data.success) {
                const mensajes = {
                    aprobar:  '✅ Permiso aprobado exitosamente',
                    rechazar: '📝 Permiso rechazado y enviado de vuelta',
                    cancelar: '🚫 Permiso cancelado definitivamente',
                };
                Swal.fire({
                    icon: 'success', title: '¡Listo!',
                    text: mensajes[accion] || data.message,
                    timer: 2000, timerProgressBar: true, showConfirmButton: false,
                }).then(() => {
                    window.location.href = '<?= $panelRegreso ?>?msg=' + encodeURIComponent(mensajes[accion]);
                });
            } else {
                console.error('[procesarAccion] error del servidor:', data?.error);
                _procesando = false;
                Swal.fire({ icon: 'error', title: 'Error', text: data?.error || 'Error desconocido al procesar la solicitud.' });
            }
        })
        .catch(err => {
            console.error('[procesarAccion] error de red:', err.message);
            _procesando = false;
            Swal.fire({ icon: 'error', title: 'Error de conexión', text: err.message });
        });
    }

    let _guardando = false;
    async function actualizarCampos() {
        if (_guardando) return;
        _guardando = true;
        const motivo      = document.getElementById('motivo_edit').value.trim();
        const fechaSalida = document.getElementById('fecha_salida_edit').value;
        const horaSalida  = document.getElementById('hora_salida_edit').value;
        const fechaReg    = document.getElementById('fecha_regreso_edit').value;
        const horaReg     = document.getElementById('hora_regreso_edit').value;

        if (!motivo || !fechaSalida || !horaSalida || !fechaReg || !horaReg) {
            _guardando = false;
            Swal.fire({ icon: 'warning', title: 'Campos incompletos', text: 'Todos los campos son obligatorios.' });
            return;
        }
        if (motivo.length < 10) {
            _guardando = false;
            Swal.fire({ icon: 'warning', title: 'Texto muy corto', text: 'El motivo debe tener al menos 10 caracteres.' });
            return;
        }

        const salidaDT  = new Date(fechaSalida + 'T' + horaSalida);
        const regresoDT = new Date(fechaReg + 'T' + horaReg);
        if (salidaDT < new Date()) {
            _guardando = false;
            Swal.fire({ icon: 'warning', title: 'Fecha inválida', text: 'La fecha y hora de salida no puede ser en el pasado.' });
            return;
        }
        if (regresoDT <= salidaDT) {
            _guardando = false;
            Swal.fire({ icon: 'warning', title: 'Fechas inválidas', text: 'La fecha y hora de regreso debe ser posterior a la de salida.' });
            return;
        }

        const res = await Swal.fire({
            title: '¿Guardar cambios?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '💾 Sí, guardar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#f39c12',
            cancelButtonColor: '#adb5bd',
        });
        if (!res.isConfirmed) {
            _guardando = false;
            return;
        }

        Swal.fire({ title: 'Guardando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        const formData = new FormData(document.getElementById('formActualizarCampos'));
        fetch('actualizar_campos_permiso.php', { method: 'POST', credentials: 'same-origin', body: formData })
        .then(r => r.text())
        .then(txt => { try { return JSON.parse(txt); } catch (e) { throw new Error('Respuesta no es JSON válido: ' + txt); } })
        .then(data => {
            _guardando = false;
            if (data.success) {
                Swal.fire({ icon: 'success', title: '¡Guardado!', text: 'Datos actualizados. Ahora puedes reenviar el permiso.', timer: 2500, timerProgressBar: true, showConfirmButton: false });
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: data.error || 'Error desconocido.' });
            }
        })
        .catch(err => {
            _guardando = false;
            Swal.fire({ icon: 'error', title: 'Error de conexión', text: err.message });
        });
    }

    let _reenviando = false;
    async function reenviarPermiso() {
        if (_reenviando) return;
        _reenviando = true;
        const res = await Swal.fire({
            title: '¿Reenviar este permiso corregido?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '🔄 Sí, reenviar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#17a2b8',
            cancelButtonColor: '#adb5bd',
        });
        if (!res.isConfirmed) {
            _reenviando = false;
            return;
        }
        Swal.fire({ title: 'Reenviando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        const formData = new FormData();
        formData.append('id_permiso', <?= (int)$permiso['id_permiso'] ?>);

        fetch('reenviar_permiso.php', { method: 'POST', credentials: 'same-origin', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                Swal.fire({ icon: 'success', title: '¡Reenviado!', text: 'Permiso reenviado correctamente.', timer: 2000, timerProgressBar: true, showConfirmButton: false })
                .then(() => { window.location.href = '<?= $panelRegreso ?>?msg=' + encodeURIComponent('Permiso reenviado exitosamente'); });
            } else {
                _reenviando = false;
                Swal.fire({ icon: 'error', title: 'Error', text: data.error || 'Error desconocido.' });
            }
        })
        .catch(err => {
            _reenviando = false;
            Swal.fire({ icon: 'error', title: 'Error de conexión', text: err.message });
        });
    }
</script>
</body>
</html>
