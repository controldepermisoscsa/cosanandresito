<?php
session_start();
require 'conexion.php';

$cargo = strtolower(trim($_SESSION['cargo'] ?? ''));
if (!isset($_SESSION['usuario_id']) || !in_array($cargo, ['gerente', 'gerencia'])) {
    header('Location: login.php?mensaje=Debes iniciar sesión como gerente.');
    exit();
}

$id_gerente = $_SESSION['usuario_id'];
$id_permiso = $_GET['id'] ?? null;

if (!$id_permiso) {
    header('Location: gerente_inicio.php?mensaje=ID de permiso no válido.');
    exit();
}

$stmt = $pdo->prepare("
    SELECT p.*, u.nombre AS nombre_empleado, u.area, c.nombre_cargo AS cargo_solicitante
    FROM permisos p
    JOIN usuarios u ON p.id_usuario = u.id_usuario
    JOIN cargo c ON u.id_cargo = c.id_cargo
    WHERE p.id_permiso = ?
      AND p.asignado_a = 'gerente'
");
$stmt->execute([$id_permiso]);
$permiso = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$permiso) {
    header('Location: gerente_inicio.php?mensaje=Permiso no encontrado o no asignado a gerencia.');
    exit();
}

// El gerente puede editar el encargado para cargos no-auxiliar
$cargoSol = strtolower($permiso['cargo_solicitante']);
$puedeEditarEncargado = in_array($cargoSol, ['administrador', 'coordinador', 'administrativo']);

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

$puedeActuar = in_array($permiso['estado'], ['pendiente','reenviado']);
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
        body { font-family: 'Segoe UI', Arial, sans-serif; display: flex; height: 100vh; background: #f0f2f5; overflow: hidden; }

        /* ── SIDEBAR ── */
        .sidebar { width: 240px; background: linear-gradient(180deg, #1a2535 0%, #2c3e50 100%); display: flex; flex-direction: column; flex-shrink: 0; box-shadow: 3px 0 15px rgba(0,0,0,0.3); }
        .sidebar-brand { padding: 24px 20px 20px; border-bottom: 1px solid rgba(255,255,255,0.08); text-align: center; }
        .sidebar-brand .brand-icon { width: 48px; height: 48px; background: linear-gradient(135deg, #f39c12, #e67e22); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 22px; margin: 0 auto 10px; box-shadow: 0 4px 12px rgba(243,156,18,0.4); }
        .sidebar-brand h2 { color: #fff; font-size: 14px; font-weight: 600; letter-spacing: 0.5px; }
        .sidebar-user { padding: 16px 20px; border-bottom: 1px solid rgba(255,255,255,0.08); }
        .sidebar-user .user-name { color: #fff; font-size: 13px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .sidebar-user .user-role { color: #f39c12; font-size: 11px; margin-top: 2px; text-transform: uppercase; letter-spacing: 0.5px; }
        .sidebar-nav { flex: 1; padding: 16px 12px; display: flex; flex-direction: column; gap: 4px; }
        .nav-item { display: flex; align-items: center; gap: 12px; color: #adb5bd; text-decoration: none; padding: 11px 14px; border-radius: 10px; font-size: 14px; font-weight: 500; transition: all 0.2s; }
        .nav-item:hover { background: rgba(255,255,255,0.08); color: #fff; }
        .nav-item .nav-icon { font-size: 18px; width: 22px; text-align: center; flex-shrink: 0; }
        .sidebar-logout { padding: 12px; border-top: 1px solid rgba(255,255,255,0.08); }
        .sidebar-logout a { display: flex; align-items: center; gap: 10px; color: #e74c3c; text-decoration: none; padding: 10px 14px; border-radius: 10px; font-size: 14px; font-weight: 500; transition: background 0.2s; }
        .sidebar-logout a:hover { background: rgba(231,76,60,0.15); }

        /* ── CONTENT ── */
        .content { flex: 1; overflow-y: auto; padding: 28px 32px; }

        /* ── PAGE HEADER ── */
        .page-header { text-align: center; margin-bottom: 28px; }
        .page-header h1 { font-size: 22px; color: #1a2535; font-weight: 700; }
        .page-header .sub { color: #6c757d; font-size: 13px; margin-top: 4px; }

        /* ── INFO GRID ── */
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .info-grid .card { margin-bottom: 0; }

        /* ── MOTIVO CARD ── */
        .motivo-card { margin-bottom: 20px; }

        /* ── BOTTOM GRID ── */
        .bottom-grid { display: grid; grid-template-columns: 1fr 1.4fr; gap: 20px; margin-bottom: 20px; align-items: start; }

        /* ── CARDS ── */
        .card { background: #fff; border-radius: 14px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); padding: 22px 24px; transition: box-shadow 0.2s, transform 0.2s; }
        .card:hover { box-shadow: 0 6px 20px rgba(0,0,0,0.10); transform: translateY(-1px); }
        .card-title { font-size: 13px; font-weight: 700; color: #1a2535; margin-bottom: 18px; display: flex; align-items: center; gap: 10px; text-transform: uppercase; letter-spacing: 0.6px; }

        /* ── ICON BADGE ── */
        .icon-badge { width: 30px; height: 30px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; font-size: 15px; flex-shrink: 0; }
        .ib-blue   { background: #dbeafe; }
        .ib-orange { background: #fff3cd; }
        .ib-purple { background: #ede9fe; }
        .ib-green  { background: #d1fae5; }
        .ib-red    { background: #fee2e2; }
        .ib-gray   { background: #f0f2f5; }

        /* ── DETAIL ROWS ── */
        .detail-row { display: flex; align-items: flex-start; padding: 11px 0; border-bottom: 1px solid #f0f2f5; gap: 14px; }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { color: #9ca3af; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; min-width: 110px; flex-shrink: 0; padding-top: 3px; }
        .detail-value { color: #1a2535; font-size: 14px; font-weight: 500; word-break: break-word; flex: 1; line-height: 1.5; }
        .hora-val { color: #f39c12; font-weight: 700; }
        .motivo-text { color: #374151; font-size: 14px; line-height: 1.6; padding: 4px 0; }

        /* ── BADGE ── */
        .badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: capitalize; }
        .badge-pendiente  { background: #fff3cd; color: #856404; }
        .badge-aprobado   { background: #d1e7dd; color: #0a3622; }
        .badge-rechazado  { background: #f8d7da; color: #842029; }
        .badge-reenviado  { background: #cff4fc; color: #055160; }
        .badge-cancelado  { background: #e2e3e5; color: #41464b; }
        .badge-finalizado { background: #d3d3d3; color: #383838; }

        /* ── RECHAZO PREVIO ── */
        .rechazo-banner { background: #fff5f5; border-radius: 14px; border: 1.5px solid #fca5a5; padding: 18px 22px; margin-bottom: 20px; }
        .rechazo-banner .card-title { color: #b91c1c; margin-bottom: 10px; }
        .rechazo-text { color: #7f1d1d; font-size: 14px; font-style: italic; line-height: 1.6; }

        /* ── TIEMPO CARD ── */
        .tiempo-card { background: linear-gradient(135deg, #fffaf0 0%, #fff8ec 100%); border-radius: 14px; border: 1.5px solid #fcd97a; padding: 26px 24px; box-shadow: 0 2px 8px rgba(243,156,18,0.08); transition: box-shadow 0.2s, transform 0.2s; }
        .tiempo-card:hover { box-shadow: 0 6px 20px rgba(243,156,18,0.18); transform: translateY(-1px); }
        .tiempo-card .card-title { justify-content: center; color: #92400e; margin-bottom: 20px; }
        .tiempo-stats { display: flex; align-items: stretch; }
        .tiempo-stat { flex: 1; text-align: center; padding: 12px 8px; }
        .tiempo-stat:first-child { border-right: 1.5px solid #fcd97a; }
        .tiempo-stat-num { font-size: 48px; font-weight: 800; color: #e67e22; line-height: 1; }
        .tiempo-stat-label { font-size: 11px; font-weight: 700; color: #b45309; text-transform: uppercase; letter-spacing: 1px; margin-top: 6px; }

        /* ── ACCIÓN CARD ── */
        .accion-card { background: #fff; border-radius: 14px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); overflow: hidden; transition: box-shadow 0.2s, transform 0.2s; }
        .accion-card:hover { box-shadow: 0 6px 20px rgba(0,0,0,0.10); transform: translateY(-1px); }
        .accion-card-header { padding: 16px 24px; display: flex; align-items: center; gap: 10px; border-bottom: 1.5px solid rgba(0,0,0,0.06); }
        .accion-card-header.aprobar-header  { background: linear-gradient(135deg, #f0fdf4, #dcfce7); border-bottom-color: #bbf7d0; }
        .accion-card-header.aprobar-header .card-title { color: #166534; }
        .accion-card-header.info-header { background: linear-gradient(135deg, #f8f9fa, #f0f2f5); }
        .accion-card-header.info-header .card-title { color: #374151; }
        .accion-card-body { padding: 20px 24px; }

        /* ── ENCARGADO BOX ── */
        .encargado-box { background: #f0f9ff; border-radius: 10px; border: 1.5px solid #bae6fd; padding: 14px 16px; margin-bottom: 16px; }
        .encargado-box .box-label { font-size: 12px; font-weight: 700; color: #0369a1; text-transform: uppercase; letter-spacing: 0.4px; margin-bottom: 8px; }

        /* ── FORM ── */
        .form-label { display: block; font-size: 12px; font-weight: 700; color: #374151; text-transform: uppercase; letter-spacing: 0.4px; margin-bottom: 7px; }
        .form-label span { font-weight: 400; color: #9ca3af; text-transform: none; }
        .form-control { width: 100%; padding: 11px 14px; border: 1.5px solid #e5e7eb; border-radius: 10px; font-size: 14px; color: #1a2535; background: #fafafa; transition: border-color 0.2s, box-shadow 0.2s; box-sizing: border-box; font-family: inherit; }
        .form-control:focus { outline: none; border-color: #f39c12; box-shadow: 0 0 0 3px rgba(243,156,18,0.12); background: #fff; }
        textarea.form-control { resize: vertical; min-height: 90px; }
        .form-group { margin-bottom: 14px; }

        /* ── BUTTONS ── */
        .btn { display: inline-flex; align-items: center; gap: 7px; padding: 11px 22px; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; border: none; text-decoration: none; transition: all 0.2s; font-family: inherit; }
        .btn-approve { background: linear-gradient(135deg, #16a34a, #15803d); color: #fff; box-shadow: 0 4px 12px rgba(22,163,74,0.3); }
        .btn-approve:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(22,163,74,0.4); }
        .btn-reject  { background: linear-gradient(135deg, #dc2626, #b91c1c); color: #fff; box-shadow: 0 4px 12px rgba(220,38,38,0.3); }
        .btn-reject:hover  { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(220,38,38,0.4); }
        .btn-cancel  { background: linear-gradient(135deg, #6c757d, #545b62); color: #fff; box-shadow: 0 4px 12px rgba(108,117,125,0.25); }
        .btn-cancel:hover  { transform: translateY(-2px); }
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
        <div class="brand-icon">🏢</div>
        <h2>Coosanandresito</h2>
    </div>
    <div class="sidebar-user">
        <div class="user-name"><?= htmlspecialchars($_SESSION['nombre']) ?></div>
        <div class="user-role">Gerente</div>
    </div>
    <nav class="sidebar-nav">
        <a href="gerente_inicio.php" class="nav-item"><span class="nav-icon">🏠</span> Inicio</a>
        <a href="gerente_inicio.php?filtro=asignados" class="nav-item"><span class="nav-icon">📋</span> Asignados a mí</a>
        <a href="ver_permisos.php" class="nav-item"><span class="nav-icon">📂</span> Mis Permisos</a>
        <a href="estadisticas_completas.php" class="nav-item"><span class="nav-icon">📊</span> Estadísticas</a>
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
        <div class="sub">Solicitud de permiso de ausencia para aprobación</div>
    </div>

    <!-- INFO GRID -->
    <div class="info-grid">

        <div class="card">
            <div class="card-title"><span class="icon-badge ib-blue">👤</span> Datos del Empleado</div>
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
                <span class="detail-value"><?= htmlspecialchars($permiso['cargo_solicitante']) ?></span>
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

        <div class="card">
            <div class="card-title"><span class="icon-badge ib-orange">📅</span> Fechas y Horario</div>
            <div class="detail-row">
                <span class="detail-label">Fecha Salida</span>
                <span class="detail-value"><?= date('d/m/Y', strtotime($permiso['fecha_salida'])) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Hora Salida</span>
                <span class="detail-value hora-val"><?= date('g:i A', strtotime($permiso['hora_salida'])) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Fecha Regreso</span>
                <span class="detail-value"><?= date('d/m/Y', strtotime($permiso['fecha_regreso_aprox'])) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Hora Regreso</span>
                <span class="detail-value hora-val"><?= date('g:i A', strtotime($permiso['hora_regreso_aprox'])) ?></span>
            </div>
        </div>

    </div>

    <!-- MOTIVO -->
    <div class="card motivo-card">
        <div class="card-title"><span class="icon-badge ib-purple">💬</span> Motivo de la Ausencia</div>
        <p class="motivo-text"><?= htmlspecialchars($permiso['motivo']) ?></p>
    </div>

    <!-- RECHAZO PREVIO -->
    <?php if ($permiso['estado'] === 'reenviado' && !empty($permiso['motivo_rechazo'])): ?>
    <div class="rechazo-banner">
        <div class="card-title"><span class="icon-badge ib-red">❌</span> Motivo del Rechazo Anterior</div>
        <p class="rechazo-text"><?= htmlspecialchars($permiso['motivo_rechazo']) ?></p>
    </div>
    <?php endif; ?>

    <!-- BOTTOM GRID -->
    <div class="bottom-grid">

        <!-- TIEMPO -->
        <div class="tiempo-card">
            <div class="card-title"><span class="icon-badge ib-orange">⏰</span> Tiempo de Ausencia Laboral</div>
            <div class="tiempo-stats">
                <div class="tiempo-stat">
                    <div class="tiempo-stat-num"><?= $horas ?></div>
                    <div class="tiempo-stat-label">Horas</div>
                </div>
                <div class="tiempo-stat">
                    <div class="tiempo-stat-num"><?= $minResto ?></div>
                    <div class="tiempo-stat-label">Minutos</div>
                </div>
            </div>
        </div>

        <!-- ACCIÓN -->
        <?php if ($puedeActuar): ?>
        <div class="accion-card">
            <div class="accion-card-header aprobar-header">
                <div class="card-title"><span class="icon-badge ib-green">⚙️</span> Decisión del Gerente</div>
            </div>
            <div class="accion-card-body">
                <form id="formAccion">
                    <input type="hidden" name="id_permiso" value="<?= $permiso['id_permiso'] ?>">

                    <?php if ($puedeEditarEncargado): ?>
                    <div class="encargado-box">
                        <div class="box-label">👨‍💼 Persona Encargada en Ausencia <span style="font-weight:400;color:#9ca3af;">(opcional)</span></div>
                        <input type="text" id="encargado_ausencia_gerente" name="encargado_ausencia"
                               class="form-control"
                               value="<?= htmlspecialchars($permiso['encargado_ausencia'] ?? '') ?>"
                               placeholder="Nombre completo de quien se hará cargo">
                    </div>
                    <?php endif; ?>

                    <?php if ($permiso['estado'] === 'pendiente'): ?>
                    <div class="form-group">
                        <label for="motivo_rechazo" class="form-label">
                            Motivo del rechazo <span style="color:#dc2626;">*</span>
                            <span>(requerido solo para rechazar)</span>
                        </label>
                        <textarea id="motivo_rechazo" name="motivo_rechazo" class="form-control"
                                  placeholder="Explica detalladamente el motivo del rechazo..."></textarea>
                    </div>
                    <div class="btn-row">
                        <button type="button" onclick="procesarAccion('aprobar')" class="btn btn-approve">✅ Aprobar</button>
                        <button type="button" onclick="procesarAccion('rechazar')" class="btn btn-reject">❌ Rechazar</button>
                    </div>
                    <?php else: /* reenviado */ ?>
                    <div class="btn-row">
                        <button type="button" onclick="procesarAccion('aprobar')" class="btn btn-approve">✅ Aprobar</button>
                        <button type="button" onclick="procesarAccion('cancelar')" class="btn btn-cancel">🚫 Cancelar Definitivamente</button>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <?php else: ?>
        <div class="accion-card">
            <div class="accion-card-header info-header">
                <div class="card-title"><span class="icon-badge ib-gray">ℹ️</span> Estado del Permiso</div>
            </div>
            <div class="accion-card-body">
                <div class="status-box">
                    <?php
                    $ico = match($permiso['estado']) { 'aprobado'=>'✅','rechazado'=>'❌','cancelado'=>'🚫','finalizado'=>'🏁',default=>'ℹ️' };
                    $txt = match($permiso['estado']) {
                        'aprobado'  => 'Este permiso fue aprobado exitosamente.',
                        'rechazado' => 'Este permiso fue rechazado.',
                        'cancelado' => 'Este permiso fue cancelado definitivamente.',
                        'finalizado'=> 'Este permiso fue completado.',
                        default     => 'No requiere acción adicional.',
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
    let _procesando = false;

    async function procesarAccion(accion) {
        console.log('[gerente procesarAccion] llamada accion=' + accion + ' _procesando=' + _procesando);
        if (_procesando) { console.warn('[gerente procesarAccion] bloqueado — ya en proceso'); return; }
        _procesando = true;
        console.log('[gerente procesarAccion] flag activado');
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
                return;
            }
        }

        const cfg = {
            aprobar:  { title:'¿Aprobar este permiso?',     icon:'question', confirm:'✅ Sí, aprobar',   color:'#16a34a' },
            rechazar: { title:'¿Rechazar este permiso?',    icon:'warning',  confirm:'❌ Sí, rechazar',  color:'#dc2626' },
            cancelar: { title:'¿Cancelar definitivamente?', icon:'warning',  confirm:'🚫 Sí, cancelar', color:'#6c757d' },
        };
        const c = cfg[accion];
        const res = await Swal.fire({
            title: c.title, icon: c.icon, showCancelButton: true,
            confirmButtonText: c.confirm, cancelButtonText: 'No, volver',
            confirmButtonColor: c.color, cancelButtonColor: '#adb5bd',
        });
        if (!res.isConfirmed) {
            console.log('[gerente procesarAccion] cancelado por usuario');
            _procesando = false;
            return;
        }

        console.log('[gerente procesarAccion] confirmado — enviando fetch accion=' + accion);
        Swal.fire({ title: 'Procesando...', allowOutsideClick: false, allowEscapeKey: false, didOpen: () => Swal.showLoading() });

        const fd = new FormData(form);
        fd.set('accion', accion);
        const encargadoField = document.getElementById('encargado_ausencia_gerente');
        if (encargadoField) fd.set('encargado_ausencia', encargadoField.value.trim());

        fetch('permisos_acciones.php', { method: 'POST', credentials: 'same-origin', headers: { 'Accept': 'application/json' }, body: fd })
        .then(r => r.text().then(t => { try { return JSON.parse(t); } catch(e) { throw new Error('Respuesta inválida: ' + t); } }))
        .then(data => {
            console.log('[gerente procesarAccion] respuesta servidor:', data);
            if (data && data.success) {
                const msgs = { aprobar:'✅ Permiso aprobado exitosamente', rechazar:'📝 Permiso rechazado', cancelar:'🚫 Permiso cancelado' };
                Swal.fire({ icon:'success', title:'¡Listo!', text: msgs[accion], timer:2000, timerProgressBar:true, showConfirmButton:false })
                .then(() => { window.location.href = 'gerente_inicio.php?msg=' + encodeURIComponent(msgs[accion]); });
            } else {
                console.error('[gerente procesarAccion] error del servidor:', data?.error);
                _procesando = false;
                Swal.fire({ icon:'error', title:'Error', text: data?.error || 'Error desconocido.' });
            }
        })
        .catch(err => {
            console.error('[gerente procesarAccion] error de red:', err.message);
            _procesando = false;
            Swal.fire({ icon:'error', title:'Error de conexión', text: err.message });
        });
    }
</script>
</body>
</html>
