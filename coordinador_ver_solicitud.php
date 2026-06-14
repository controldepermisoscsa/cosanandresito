<?php
session_start();
require 'conexion.php';

if (!isset($_SESSION['usuario_id']) || strtolower($_SESSION['cargo']) !== 'coordinador') {
    header('Location: login.php?mensaje=Debes iniciar sesión como coordinador.');
    exit();
}

$id_coordinador = $_SESSION['usuario_id'];
$id_permiso     = $_GET['id'] ?? null;

if (!$id_permiso) {
    header('Location: coordinador_inicio.php?mensaje=ID de permiso no válido.');
    exit();
}

$stmt = $pdo->prepare("
    SELECT p.*, u.nombre AS nombre_empleado, u.area, c.nombre_cargo AS cargo
    FROM permisos p
    JOIN usuarios u ON p.id_usuario = u.id_usuario
    JOIN cargo c ON u.id_cargo = c.id_cargo
    WHERE p.id_permiso = ?
      AND p.asignado_a = 'coordinador'
      AND (p.id_asignado IS NULL OR p.id_asignado = ?)
");
$stmt->execute([$id_permiso, $id_coordinador]);
$permiso = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$permiso) {
    header('Location: coordinador_inicio.php?mensaje=Permiso no encontrado o no asignado a ti.');
    exit();
}

if (empty($permiso['id_asignado']) || intval($permiso['id_asignado']) !== intval($id_coordinador)) {
    $upd = $pdo->prepare("UPDATE permisos SET id_asignado = ? WHERE id_permiso = ?");
    $upd->execute([$id_coordinador, $id_permiso]);
    $permiso['id_asignado'] = $id_coordinador;
}

$esAuxiliar = strtolower($permiso['cargo']) === 'auxiliar';

function calcularTiempoAusencia($fs, $hs, $fr, $hr) {
    $hl = [
        1=>[['07:30','12:00'],['14:00','17:30']],2=>[['07:30','12:00'],['14:00','17:30']],
        3=>[['07:30','12:00'],['14:00','17:30']],4=>[['07:30','12:00'],['14:00','17:30']],
        5=>[['07:30','12:00'],['14:00','17:00']],6=>[['08:00','12:30']],
    ];
    $fi = new DateTime("$fs $hs"); $ff = new DateTime("$fr $hr");
    $min = 0; $fc = clone $fi;
    while ($fc <= $ff) {
        $d = (int)$fc->format('N'); $s = $fc->format('Y-m-d');
        if ($d <= 6 && isset($hl[$d])) {
            foreach ($hl[$d] as $r) {
                $a = max($fi, new DateTime("$s {$r[0]}")); $b = min($ff, new DateTime("$s {$r[1]}"));
                if ($a < $b) { $diff = $b->diff($a); $min += $diff->h * 60 + $diff->i; }
            }
        }
        $fc->add(new DateInterval('P1D')); $fc->setTime(0,0,0);
    }
    return $min;
}

$minutos  = calcularTiempoAusencia($permiso['fecha_salida'],$permiso['hora_salida'],$permiso['fecha_regreso_aprox'],$permiso['hora_regreso_aprox']);
$horas    = floor($minutos / 60);
$minResto = $minutos % 60;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Revisar Permiso #<?= $permiso['id_permiso'] ?></title>
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
            width: 240px;
            background: linear-gradient(180deg, #1a2535 0%, #2c3e50 100%);
            display: flex; flex-direction: column; flex-shrink: 0;
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
        .nav-item .nav-icon { font-size: 18px; width: 22px; text-align: center; }
        .sidebar-logout { padding: 12px; border-top: 1px solid rgba(255,255,255,0.08); }
        .sidebar-logout a { display: flex; align-items: center; gap: 10px; color: #e74c3c; text-decoration: none; padding: 10px 14px; border-radius: 10px; font-size: 14px; font-weight: 500; transition: background 0.2s; }
        .sidebar-logout a:hover { background: rgba(231,76,60,0.15); }

        /* ── CONTENT ── */
        .content { flex: 1; overflow-y: auto; padding: 28px 32px; }

        /* ── PAGE HEADER ── */
        .page-header { text-align: center; margin-bottom: 28px; }
        .page-header h1 { font-size: 22px; color: #1a2535; font-weight: 700; }
        .page-header .sub { color: #6c757d; font-size: 13px; margin-top: 4px; }

        /* ── INFO GRID (2 cols equal height) ── */
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .info-grid .card { margin-bottom: 0; }

        /* ── MOTIVO CARD (full width) ── */
        .motivo-card { margin-bottom: 20px; }

        /* ── BOTTOM GRID (tiempo + acción lado a lado) ── */
        .bottom-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; align-items: stretch; }

        /* ── CARDS ── */
        .card {
            background: #fff; border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.07);
            padding: 0;
            transition: box-shadow 0.2s, transform 0.2s;
            overflow: hidden;
        }
        .card:hover { box-shadow: 0 6px 20px rgba(0,0,0,0.10); transform: translateY(-1px); }

        .card-header {
            display: flex; align-items: center; justify-content: center; gap: 10px;
            padding: 16px 22px;
            border-bottom: 1px solid #f0f2f5;
            background: #fafbfc;
            text-align: center;
        }
        .card-header-icon {
            width: 34px; height: 34px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 16px; flex-shrink: 0;
        }
        .card-header-icon.blue   { background: #e8f0fe; }
        .card-header-icon.orange { background: #fff3e0; }
        .card-header-icon.purple { background: #ede9fe; }
        .card-header-text { font-size: 14px; font-weight: 700; color: #1a2535; }
        .card-header-sub  { font-size: 11px; color: #9ca3af; margin-top: 1px; }
        .card-body { padding: 18px 22px; }

        /* ── CARD TITLE (usado en tiempo-card y accion-card) ── */
        .card-title {
            font-size: 13px; font-weight: 700; color: #1a2535;
            margin-bottom: 18px; display: flex; align-items: center; gap: 10px;
            text-transform: uppercase; letter-spacing: 0.6px;
        }
        .icon-badge {
            width: 30px; height: 30px; border-radius: 8px;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 15px; flex-shrink: 0;
        }
        .icon-badge-blue   { background: #dbeafe; }
        .icon-badge-orange { background: #fff3cd; }
        .icon-badge-green  { background: #d1fae5; }
        .icon-badge-purple { background: #ede9fe; }
        .icon-badge-gray   { background: #f0f2f5; }

        /* ── DETAIL ROWS ── */
        .detail-row { display: flex; align-items: center; padding: 9px 0; border-bottom: 1px solid #f5f6f8; gap: 14px; }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { color: #9ca3af; font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; min-width: 110px; flex-shrink: 0; }
        .detail-value { color: #1a2535; font-size: 14px; font-weight: 500; word-break: break-word; flex: 1; line-height: 1.5; }
        .hora-val { color: #f39c12; font-weight: 700; }

        /* ── FECHAS VISUAL ── */
        .fechas-grid { display: grid; grid-template-columns: 1fr auto 1fr; gap: 0; align-items: center; }
        .fecha-col { text-align: center; padding: 10px 8px; }
        .fecha-col-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 10px; }
        .fecha-col-label.salida  { color: #f39c12; }
        .fecha-col-label.regreso { color: #10b981; }
        .fecha-icon-wrap {
            width: 44px; height: 44px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; margin: 0 auto 10px;
        }
        .fecha-icon-wrap.salida  { background: #fff8ec; }
        .fecha-icon-wrap.regreso { background: #ecfdf5; }
        .fecha-date { font-size: 15px; font-weight: 700; color: #1a2535; margin-bottom: 4px; }
        .fecha-time { font-size: 18px; font-weight: 800; }
        .fecha-time.salida  { color: #f39c12; }
        .fecha-time.regreso { color: #10b981; }
        .fechas-arrow { display: flex; flex-direction: column; align-items: center; gap: 4px; padding: 0 6px; }
        .fechas-arrow-line { width: 40px; height: 2px; background: linear-gradient(90deg, #f39c12, #10b981); border-radius: 2px; }
        .fechas-arrow-tip { font-size: 14px; color: #9ca3af; }

        /* ── MOTIVO VALUE ── */
        .motivo-card .card-header { justify-content: center; text-align: center; }
        .motivo-text { color: #374151; font-size: 14px; font-weight: 400; line-height: 1.6; padding: 4px 0; text-align: center; }

        /* ── BADGE ── */
        .badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: capitalize; }
        .badge-pendiente  { background: #fff3cd; color: #856404; }
        .badge-aprobado   { background: #d1e7dd; color: #0a3622; }
        .badge-rechazado  { background: #f8d7da; color: #842029; }
        .badge-reenviado  { background: #cff4fc; color: #055160; }
        .badge-cancelado  { background: #e2e3e5; color: #41464b; }
        .badge-finalizado { background: #d3d3d3; color: #383838; }

        /* ── TIEMPO CARD (KPI widget) ── */
        .tiempo-card {
            background: linear-gradient(135deg, #f59e0b 0%, #f97316 55%, #ea580c 100%);
            border-radius: 16px;
            box-shadow: 0 8px 28px rgba(245,158,11,0.35);
            padding: 28px 24px 26px;
            position: relative; overflow: hidden;
            display: flex; flex-direction: column; justify-content: center; align-items: center;
            text-align: center;
            transition: box-shadow 0.2s, transform 0.2s;
        }
        .tiempo-card:hover { box-shadow: 0 14px 36px rgba(245,158,11,0.45); transform: translateY(-2px); }
        .tiempo-card::before {
            content: ''; position: absolute;
            top: -28px; right: -28px;
            width: 110px; height: 110px;
            background: rgba(255,255,255,0.10); border-radius: 50%;
            pointer-events: none;
        }
        .tiempo-card::after {
            content: ''; position: absolute;
            bottom: -36px; left: -16px;
            width: 130px; height: 130px;
            background: rgba(255,255,255,0.07); border-radius: 50%;
            pointer-events: none;
        }
        .tc-icon { font-size: 26px; margin-bottom: 10px; display: block; }
        .tc-label { font-size: 11px; font-weight: 700; text-transform: uppercase;
            letter-spacing: 1.3px; color: rgba(255,255,255,0.78); margin-bottom: 18px; }
        .tc-display { display: flex; align-items: flex-end; justify-content: center; gap: 0; }
        .tc-blk { text-align: center; }
        .tc-num { font-size: 62px; font-weight: 900; color: #fff; line-height: 1; letter-spacing: -3px; }
        .tc-unit { font-size: 11px; font-weight: 700; color: rgba(255,255,255,0.65);
            text-transform: uppercase; letter-spacing: 1.2px; margin-top: 6px; }
        .tc-sep { font-size: 44px; font-weight: 900; color: rgba(255,255,255,0.35);
            line-height: 1; padding: 0 10px 14px; align-self: flex-end; }

        /* ── ACCIÓN CARD ── */
        .accion-card {
            background: #fff; border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow: hidden;
            transition: box-shadow 0.2s, transform 0.2s;
        }
        .accion-card:hover { box-shadow: 0 8px 28px rgba(0,0,0,0.12); transform: translateY(-1px); }
        .accion-card-header {
            display: flex; align-items: center; justify-content: center; gap: 10px;
            padding: 16px 22px;
            border-bottom: 1px solid #f0f2f5;
            background: #fafbfc;
            text-align: center;
        }
        .accion-card-body { padding: 22px 26px; }
        .accion-desc { color: #6c757d; font-size: 13px; margin-bottom: 18px; line-height: 1.5; }

        /* ── FORM ── */
        .form-label { display: block; font-size: 12px; font-weight: 700; color: #374151; text-transform: uppercase; letter-spacing: 0.4px; margin-bottom: 7px; }
        .form-label span { font-weight: 400; color: #9ca3af; text-transform: none; }
        .form-control {
            width: 100%; padding: 11px 14px;
            border: 1.5px solid #e5e7eb; border-radius: 10px;
            font-size: 14px; color: #1a2535; background: #fafafa;
            transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
            box-sizing: border-box; font-family: inherit;
        }
        .form-control:focus { outline: none; border-color: #10b981; box-shadow: 0 0 0 3px rgba(16,185,129,0.12); background: #fff; }

        /* ── BUTTONS ── */
        .btn { display: inline-flex; align-items: center; gap: 7px; padding: 11px 24px; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; border: none; text-decoration: none; transition: all 0.2s; font-family: inherit; }
        .btn-send { background: linear-gradient(135deg, #10b981, #059669); color: #fff; box-shadow: 0 4px 12px rgba(16,185,129,0.3); width: 100%; justify-content: center; }
        .btn-send:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(16,185,129,0.42); }
        .btn-row { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 18px; }

        /* ── ESTADO FINAL ── */
        .status-box { display: flex; align-items: center; gap: 16px; padding: 4px 0; }
        .status-icon { font-size: 36px; }
        .status-text .s-title { font-size: 15px; font-weight: 700; color: #1a2535; }
        .status-text .s-sub   { font-size: 13px; color: #6c757d; margin-top: 4px; line-height: 1.5; }
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
        <a href="coordinador_inicio.php" class="nav-item"><span class="nav-icon">🏠</span> Inicio</a>
        <a href="coordinador_inicio.php?vista=permisos_auxiliares" class="nav-item"><span class="nav-icon">👥</span> Permisos Auxiliares</a>
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

    <!-- PAGE HEADER -->
    <div class="page-header">
        <h1>Revisar Permiso #<?= htmlspecialchars($permiso['id_permiso']) ?></h1>
        <div class="sub">Solicitud de permiso de ausencia para revisión del coordinador</div>
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
                    <span class="detail-label">Auxiliar</span>
                    <span class="detail-value"><?= htmlspecialchars($permiso['nombre_empleado']) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Área</span>
                    <span class="detail-value"><?= htmlspecialchars($permiso['area']) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Tipo Permiso</span>
                    <span class="detail-value"><?= htmlspecialchars($permiso['tipo_permiso']) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Estado</span>
                    <span class="detail-value">
                        <span class="badge badge-<?= $permiso['estado'] ?>"><?= ucfirst($permiso['estado']) ?></span>
                    </span>
                </div>
                <?php if (!empty($permiso['encargado_ausencia'])): ?>
                <div class="detail-row">
                    <span class="detail-label">Encargado</span>
                    <span class="detail-value"><?= htmlspecialchars($permiso['encargado_ausencia']) ?></span>
                </div>
                <?php endif; ?>
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
                        <div class="fecha-time salida"><?= date('g:i A', strtotime($permiso['hora_salida'])) ?></div>
                    </div>
                    <div class="fechas-arrow">
                        <div class="fechas-arrow-line"></div>
                        <div class="fechas-arrow-tip">→</div>
                    </div>
                    <div class="fecha-col">
                        <div class="fecha-col-label regreso">Regreso</div>
                        <div class="fecha-icon-wrap regreso">🏠</div>
                        <div class="fecha-date"><?= date('d/m/Y', strtotime($permiso['fecha_regreso_aprox'])) ?></div>
                        <div class="fecha-time regreso"><?= date('g:i A', strtotime($permiso['hora_regreso_aprox'])) ?></div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- MOTIVO (full width) -->
    <div class="card motivo-card">
        <div class="card-header">
            <div class="card-header-icon purple">💬</div>
            <div>
                <div class="card-header-text">Motivo de la Ausencia</div>
                <div class="card-header-sub">Razón declarada por el empleado</div>
            </div>
        </div>
        <div class="card-body">
            <p class="motivo-text"><?= htmlspecialchars($permiso['motivo']) ?></p>
        </div>
    </div>

    <!-- BOTTOM GRID: tiempo + acción -->
    <div class="bottom-grid">

        <!-- TIEMPO AUSENCIA -->
        <div class="tiempo-card">
            <span class="tc-icon">⏰</span>
            <div class="tc-label">Tiempo de Ausencia Laboral</div>
            <div class="tc-display">
                <div class="tc-blk">
                    <div class="tc-num"><?= $horas ?></div>
                    <div class="tc-unit">horas</div>
                </div>
                <div class="tc-sep">:</div>
                <div class="tc-blk">
                    <div class="tc-num"><?= str_pad($minResto, 2, '0', STR_PAD_LEFT) ?></div>
                    <div class="tc-unit">minutos</div>
                </div>
            </div>
        </div>

        <!-- ACCIÓN -->
        <?php if ($esAuxiliar && $permiso['estado'] === 'pendiente'): ?>
        <div class="accion-card">
            <div class="accion-card-header">
                <div class="card-header-icon green" style="background:#d1fae5;">📤</div>
                <div class="card-header-text">Enviar a Gerencia</div>
            </div>
            <div class="accion-card-body">
                <p class="accion-desc">
                    Asigna la persona que quedará encargada durante la ausencia y envía el permiso a Gerencia para aprobación final.
                </p>
                <form id="formEncargado">
                    <input type="hidden" name="id_permiso" value="<?= $permiso['id_permiso'] ?>">
                    <label for="encargado_ausencia" class="form-label">
                        Persona Encargada en Ausencia
                        <span>(opcional)</span>
                    </label>
                    <input type="text"
                           id="encargado_ausencia"
                           name="encargado_ausencia"
                           class="form-control"
                           value="<?= htmlspecialchars($permiso['encargado_ausencia'] ?? '') ?>"
                           placeholder="Nombre completo de la persona que se hará cargo">
                    <div class="btn-row">
                        <button type="button" onclick="asignarYEnviar()" class="btn btn-send">📤 Enviar a Gerencia</button>
                    </div>
                </form>
            </div>
        </div>

        <?php else: ?>
        <div class="accion-card">
            <div class="accion-card-header">
                <div class="card-header-icon" style="background:#f0f2f5;">ℹ️</div>
                <div class="card-header-text">Estado del Permiso</div>
            </div>
            <div class="accion-card-body">
                <div class="status-box">
                    <?php
                    $ico = match($permiso['estado']) {
                        'aprobado'   => '✅', 'rechazado' => '❌',
                        'cancelado'  => '🚫', 'finalizado' => '🏁',
                        'reenviado'  => '🔄', default => 'ℹ️',
                    };
                    $txt = match($permiso['estado']) {
                        'aprobado'   => 'Este permiso ya fue aprobado por gerencia.',
                        'rechazado'  => 'Este permiso fue rechazado.',
                        'cancelado'  => 'Este permiso fue cancelado.',
                        'finalizado' => 'Este permiso fue completado exitosamente.',
                        'reenviado'  => 'Este permiso ya fue enviado a gerencia y está pendiente de aprobación.',
                        default      => 'No requiere acción adicional.',
                    };
                    ?>
                    <div class="status-icon"><?= $ico ?></div>
                    <div class="status-text">
                        <div class="s-title">Permiso <?= ucfirst($permiso['estado']) ?></div>
                        <div class="s-sub"><?= $txt ?></div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>

</div><!-- .content -->

<script>
    let _enviando = false;

    async function asignarYEnviar() {
        console.log('[asignarYEnviar] llamada _enviando=' + _enviando);
        if (_enviando) { console.warn('[asignarYEnviar] bloqueado — ya en proceso'); return; }
        _enviando = true;
        console.log('[asignarYEnviar] flag activado');
        const encargado = document.getElementById('encargado_ausencia').value.trim();
        const idPermiso = <?= (int)$permiso['id_permiso'] ?>;

        const res = await Swal.fire({
            title: '¿Enviar a Gerencia?',
            text: 'El permiso pasará a revisión final del gerente.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '📤 Sí, enviar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#adb5bd',
        });
        if (!res.isConfirmed) {
            console.log('[asignarYEnviar] cancelado por usuario');
            _enviando = false;
            return;
        }
        console.log('[asignarYEnviar] confirmado — enviando fetch');
        Swal.fire({ title: 'Enviando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        const fd = new FormData();
        fd.append('accion', 'enviar_gerente');
        fd.append('id_permiso', idPermiso);
        fd.append('encargado_ausencia', encargado);

        fetch('coordinador_acciones.php', { method: 'POST', credentials: 'same-origin', body: fd })
        .then(r => r.text().then(t => { try { return JSON.parse(t); } catch(e) { throw new Error('Respuesta no JSON: ' + t); } }))
        .then(data => {
            console.log('[asignarYEnviar] respuesta servidor:', data);
            if (data.success) {
                Swal.fire({ icon: 'success', title: '¡Enviado!', text: 'Permiso enviado a gerencia exitosamente.', timer: 2000, timerProgressBar: true, showConfirmButton: false })
                .then(() => { window.location.href = 'coordinador_inicio.php?msg=' + encodeURIComponent('Permiso enviado a gerencia exitosamente'); });
            } else {
                console.error('[asignarYEnviar] error del servidor:', data.error);
                _enviando = false;
                Swal.fire({ icon: 'error', title: 'Error', text: data.error || 'Error desconocido.' });
            }
        })
        .catch(err => {
            console.error('[asignarYEnviar] error de red:', err.message);
            _enviando = false;
            Swal.fire({ icon: 'error', title: 'Error de conexión', text: err.message });
        });
    }
</script>
</body>
</html>
