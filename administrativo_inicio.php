<?php
session_start();
require 'conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php?mensaje=Debes iniciar sesión para acceder a esta página.');
    exit();
}

$id_cargo_usuario = $_SESSION['id_cargo'] ?? 0;
if ($id_cargo_usuario != 2) {
    header('Location: login.php');
    exit();
}

$id_usuario = $_SESSION['usuario_id'];

// Permisos del administrativo
$stmt = $pdo->prepare("
    SELECT p.id_permiso, p.tipo_permiso, p.motivo, p.fecha_salida, p.hora_salida, p.estado
    FROM permisos p
    WHERE p.id_usuario = ?
    ORDER BY p.fecha_salida DESC
");
$stmt->execute([$id_usuario]);
$permisos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Stats
$stmtStats = $pdo->prepare("
    SELECT
      COUNT(*) AS total,
      COUNT(*) FILTER (WHERE p.estado = 'pendiente')  AS pendientes,
      COUNT(*) FILTER (WHERE p.estado = 'aprobado')   AS aprobados,
      COUNT(*) FILTER (WHERE p.estado = 'rechazado')  AS rechazados,
      COUNT(*) FILTER (WHERE p.estado = 'finalizado') AS finalizados
    FROM permisos p
    WHERE p.id_usuario = ?
");
$stmtStats->execute([$id_usuario]);
$stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

// Spanish date
$dias_es  = ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'];
$meses_es = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
$hoy      = new DateTime();
$fecha_es = ucfirst($dias_es[(int)$hoy->format('w')]) . ', ' . $hoy->format('d') . ' de ' . $meses_es[(int)$hoy->format('n') - 1] . ' de ' . $hoy->format('Y');

$mensaje = $_GET['mensaje'] ?? $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Administrativo</title>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Arial, sans-serif; display: flex; height: 100vh; background: #f0f2f5; overflow: hidden; }

        /* ── SIDEBAR ── */
        .sidebar { width: 240px; flex-shrink: 0; background: linear-gradient(180deg, #1a2535 0%, #2c3e50 100%); display: flex; flex-direction: column; box-shadow: 3px 0 15px rgba(0,0,0,0.3); }
        .sidebar-brand { padding: 24px 20px 20px; border-bottom: 1px solid rgba(255,255,255,0.08); text-align: center; }
        .sidebar-brand .brand-icon { width: 48px; height: 48px; background: linear-gradient(135deg, #f39c12, #e67e22); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 22px; margin: 0 auto 10px; box-shadow: 0 4px 12px rgba(243,156,18,0.4); }
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

        /* ── STATS ── */
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: #fff; border-radius: 14px; padding: 20px 22px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); display: flex; align-items: center; gap: 16px; transition: box-shadow 0.2s, transform 0.2s; }
        .stat-card:hover { box-shadow: 0 6px 18px rgba(0,0,0,0.10); transform: translateY(-2px); }
        .stat-icon { width: 46px; height: 46px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 22px; flex-shrink: 0; }
        .si-orange { background: #fff3cd; } .si-yellow { background: #fef9c3; }
        .si-green  { background: #d1fae5; } .si-red { background: #fee2e2; }
        .stat-info .stat-num { font-size: 28px; font-weight: 800; color: #1a2535; line-height: 1; }
        .stat-info .stat-lbl { font-size: 12px; color: #6c757d; margin-top: 3px; font-weight: 500; }

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
        .btn-ver { display: inline-flex; align-items: center; gap: 5px; background: linear-gradient(135deg, #f39c12, #e67e22); color: #fff; text-decoration: none; padding: 6px 14px; border-radius: 8px; font-size: 12px; font-weight: 600; box-shadow: 0 2px 6px rgba(243,156,18,0.3); transition: all 0.2s; }
        .btn-ver:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(243,156,18,0.4); }

        /* ── ALERT ── */
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; border-radius: 10px; padding: 12px 16px; margin-bottom: 20px; font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 8px; }

        /* ── DATATABLES ── */
        .dt-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; flex-wrap: wrap; gap: 10px; }
        .dt-bottom { display: flex; justify-content: space-between; align-items: center; margin-top: 14px; flex-wrap: wrap; gap: 10px; }
        div.dataTables_wrapper div.dataTables_filter input { border: 1.5px solid #dee2e6; border-radius: 8px; padding: 7px 12px; font-size: 13px; outline: none; width: 220px; }
        div.dataTables_wrapper div.dataTables_filter input:focus { border-color: #f39c12; box-shadow: 0 0 0 3px rgba(243,156,18,0.12); }
        div.dataTables_wrapper div.dataTables_length select { border: 1.5px solid #dee2e6; border-radius: 8px; padding: 6px 10px; font-size: 13px; }
        table.dataTable thead th { background: #f8f9fa; color: #374151; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #e9ecef !important; padding: 12px 14px; }
        table.dataTable tbody td { padding: 12px 14px; font-size: 13px; color: #374151; vertical-align: middle; border-bottom: 1px solid #f0f2f5 !important; }
        table.dataTable tbody tr:hover { background: #fafbfc; }
        table.dataTable { border-collapse: collapse !important; }
        .paginate_button.current,.paginate_button.current:hover { background: linear-gradient(135deg,#f39c12,#e67e22) !important; color: #fff !important; border-color: #f39c12 !important; border-radius: 6px !important; }
        .paginate_button:hover { background: #f8f9fa !important; border-radius: 6px !important; }
        div.dataTables_info { font-size: 12px; color: #6c757d; }

        /* ── WIDGET TIEMPO AUSENCIA ── */
        .widget-tiempo-ausencia { position: fixed; bottom: 20px; right: 20px; background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%); color: white; padding: 18px; border-radius: 15px; box-shadow: 0 8px 25px rgba(243,156,18,0.4); min-width: 300px; max-width: 340px; z-index: 999; font-family: 'Segoe UI', sans-serif; border: 2px solid rgba(255,255,255,0.2); transition: all 0.3s ease; cursor: move; user-select: none; }
        .widget-tiempo-ausencia:hover { transform: translateY(-3px); box-shadow: 0 12px 30px rgba(243,156,18,0.5); }
        .widget-tiempo-ausencia.dragging { transform: rotate(3deg) scale(1.05); z-index: 1001; }
        .widget-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; cursor: move; padding: 5px 0; }
        .widget-header h4 { margin: 0; font-size: 15px; font-weight: 700; color: white; flex-grow: 1; text-align: center; text-shadow: 1px 1px 2px rgba(0,0,0,0.3); }
        .widget-controls { display: flex; gap: 5px; }
        .widget-btn { background: rgba(255,255,255,0.2); border: none; color: white; width: 24px; height: 24px; border-radius: 50%; cursor: pointer; font-size: 12px; font-weight: bold; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
        .widget-btn:hover { background: rgba(255,255,255,0.3); transform: scale(1.1); }
        .tiempo-digital { font-family: 'Courier New', monospace; font-size: 24px; font-weight: bold; text-align: center; margin: 15px 0; padding: 12px; background: rgba(255,255,255,0.15); border-radius: 10px; letter-spacing: 1px; border: 1px solid rgba(255,255,255,0.3); color: #fff; display: flex; flex-direction: column; align-items: center; gap: 5px; }
        .tiempo-principal { font-size: 26px; line-height: 1; }
        .tiempo-info { font-size: 11px; opacity: 0.8; font-family: Arial, sans-serif; }
        .widget-info { font-size: 12px; opacity: 0.95; margin-bottom: 15px; text-align: center; line-height: 1.4; color: #fff; }
        .btn-finalizar { background: linear-gradient(45deg, #e74c3c, #c0392b); color: white; border: none; padding: 12px 18px; border-radius: 10px; cursor: pointer; font-weight: 700; width: 100%; transition: all 0.3s; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; box-shadow: 0 4px 15px rgba(231,76,60,0.3); }
        .btn-finalizar:hover { background: linear-gradient(45deg,#c0392b,#a93226); transform: translateY(-2px); }
        .btn-finalizar.confirmar { animation: pulse 1.5s infinite; }
        .btn-cancelar { background: linear-gradient(45deg, #6c757d, #545b62); color: white; border: none; padding: 8px 15px; border-radius: 8px; cursor: pointer; font-weight: 600; width: 100%; margin-top: 8px; transition: all 0.3s; font-size: 11px; text-transform: uppercase; }
        .btn-cancelar:hover { background: linear-gradient(45deg,#545b62,#495057); transform: translateY(-1px); }
        .widget-hidden { display: none !important; }
        .widget-minimized { min-width: 80px; padding: 10px; border-radius: 30px; }
        .widget-minimized .widget-info, .widget-minimized .btn-finalizar { display: none; }
        .widget-minimized .tiempo-digital { font-size: 16px; margin: 5px 0; padding: 8px; }
        .widget-minimized .tiempo-principal { font-size: 16px; }
        .widget-fuera-horario { opacity: 0.8; background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%); }
        @keyframes pulse { 0%{transform:scale(1)} 50%{transform:scale(1.05)} 100%{transform:scale(1)} }
        @keyframes bounce { 0%,20%,50%,80%,100%{transform:translateY(0)} 40%{transform:translateY(-10px)} 60%{transform:translateY(-5px)} }
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
        <div class="user-role">Administrativo</div>
    </div>
    <nav class="sidebar-nav">
        <a href="administrativo_inicio.php" class="nav-item active"><span class="nav-icon">🏠</span> Inicio</a>
        <a href="solicitar_permiso.php?nuevo=1" class="nav-item"><span class="nav-icon">📝</span> Solicitar Permiso</a>
        <a href="ver_permisos.php" class="nav-item"><span class="nav-icon">📂</span> Mis Permisos</a>
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
        <div class="page-title">Panel Administrativo</div>
        <div class="page-date"><?= $fecha_es ?></div>
    </div>

    <!-- STATS -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon si-orange">📋</div>
            <div class="stat-info">
                <div class="stat-num"><?= $stats['total'] ?></div>
                <div class="stat-lbl">Total Permisos</div>
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
            <div class="stat-icon si-red">❌</div>
            <div class="stat-info">
                <div class="stat-num"><?= $stats['rechazados'] ?></div>
                <div class="stat-lbl">Rechazados</div>
            </div>
        </div>
    </div>

    <!-- ALERT -->
    <?php if (!empty($mensaje)): ?>
    <div class="alert-success"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>

    <!-- TABLE -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">📄 Mis Solicitudes de Permiso</div>
            <div class="card-subtitle"><?= count($permisos) ?> registro(s)</div>
        </div>

        <table id="tablaPermisos" class="display" style="width:100%">
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
                    <td><?= htmlspecialchars($p['id_permiso']) ?></td>
                    <td><?= htmlspecialchars($p['tipo_permiso']) ?></td>
                    <td><?= htmlspecialchars(mb_strimwidth($p['motivo'], 0, 50, '…')) ?></td>
                    <td data-order="<?= $p['fecha_salida'] ?? '' ?>">
                        <?= !empty($p['fecha_salida']) ? date('d/m/Y', strtotime($p['fecha_salida'])) : '—' ?>
                        <?php if (!empty($p['hora_salida'])): ?>
                            <span style="color:#6c757d;font-size:12px;display:block;"><?= date('g:i A', strtotime($p['hora_salida'])) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge badge-<?= $p['estado'] ?>"><?= ucfirst($p['estado']) ?></span></td>
                    <td><a href="ver_solicitud.php?id=<?= $p['id_permiso'] ?>" class="btn-ver">👁 Ver</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div><!-- .content -->

<!-- WIDGET TIEMPO AUSENCIA -->
<div id="widgetTiempoAusencia" class="widget-tiempo-ausencia widget-hidden">
    <div class="widget-header">
        <div class="widget-controls">
            <button class="widget-btn" onclick="toggleMinimizar()" id="btnMinimizar" title="Minimizar">−</button>
            <button class="widget-btn" onclick="resetPosition()" title="Resetear posición">⌂</button>
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
    $('#tablaPermisos').DataTable({
        dom: '<"dt-top"lf>t<"dt-bottom"ip>',
        pageLength: 10,
        order: [[3, 'desc']],
        columnDefs: [{ orderable: false, targets: 5 }],
        language: {
            lengthMenu:'Mostrar _MENU_ registros', zeroRecords:'No se encontraron permisos',
            info:'Mostrando _START_ a _END_ de _TOTAL_ registros', infoEmpty:'Sin registros',
            infoFiltered:'(filtrado de _MAX_)', search:'Buscar:',
            paginate:{ first:'«', last:'»', next:'›', previous:'‹' }
        }
    });

    <?php if (!empty($mensaje)): ?>
    Swal.fire({ icon:'success', title:'¡Éxito!', text:<?= json_encode($mensaje) ?>, timer:3000, timerProgressBar:true, showConfirmButton:false });
    history.replaceState(null, '', 'administrativo_inicio.php');
    <?php endif; ?>

    // ── WIDGET ──
    let permisoActivo=null, intervalTimer=null, widgetMinimizado=false;
    let isDragging=false, dragOffset={x:0,y:0}, modoConfirmacion=false;

    document.addEventListener('DOMContentLoaded', function() {
        verificarPermisoActivo();
        setInterval(verificarPermisoActivo, 2*60*1000);
        inicializarDraggable();
        cargarPosicionGuardada();
    });

    function inicializarDraggable() {
        const w=document.getElementById('widgetTiempoAusencia'), h=w.querySelector('.widget-header');
        h.addEventListener('mousedown', iniciarDrag);
        document.addEventListener('mousemove', duranteDrag);
        document.addEventListener('mouseup', finalizarDrag);
        h.addEventListener('touchstart', iniciarDragTouch, {passive:false});
        document.addEventListener('touchmove', duranteDragTouch, {passive:false});
        document.addEventListener('touchend', finalizarDrag);
    }
    function iniciarDrag(e) { const w=document.getElementById('widgetTiempoAusencia'); isDragging=true; w.classList.add('dragging'); const r=w.getBoundingClientRect(); dragOffset.x=e.clientX-r.left; dragOffset.y=e.clientY-r.top; e.preventDefault(); }
    function iniciarDragTouch(e) { const t=e.touches[0],w=document.getElementById('widgetTiempoAusencia'); isDragging=true; w.classList.add('dragging'); const r=w.getBoundingClientRect(); dragOffset.x=t.clientX-r.left; dragOffset.y=t.clientY-r.top; e.preventDefault(); }
    function duranteDrag(e) { if(!isDragging)return; posicionarWidget(e.clientX-dragOffset.x, e.clientY-dragOffset.y); e.preventDefault(); }
    function duranteDragTouch(e) { if(!isDragging)return; const t=e.touches[0]; posicionarWidget(t.clientX-dragOffset.x, t.clientY-dragOffset.y); e.preventDefault(); }
    function posicionarWidget(x,y) { const w=document.getElementById('widgetTiempoAusencia'),r=w.getBoundingClientRect(); x=Math.max(0,Math.min(x,window.innerWidth-r.width)); y=Math.max(0,Math.min(y,window.innerHeight-r.height)); w.style.left=x+'px'; w.style.top=y+'px'; w.style.right='auto'; w.style.bottom='auto'; }
    function finalizarDrag() { if(!isDragging)return; document.getElementById('widgetTiempoAusencia').classList.remove('dragging'); isDragging=false; guardarPosicion(); }
    function guardarPosicion() { const r=document.getElementById('widgetTiempoAusencia').getBoundingClientRect(); localStorage.setItem('widgetPosition',JSON.stringify({x:r.left,y:r.top,minimizado:widgetMinimizado})); }
    function cargarPosicionGuardada() { try { const p=JSON.parse(localStorage.getItem('widgetPosition')); if(!p)return; const w=document.getElementById('widgetTiempoAusencia'); w.style.left=p.x+'px'; w.style.top=p.y+'px'; w.style.right='auto'; w.style.bottom='auto'; if(p.minimizado)toggleMinimizar(); } catch(e){} }
    function resetPosition() { const w=document.getElementById('widgetTiempoAusencia'); w.style.left='auto'; w.style.top='auto'; w.style.right='20px'; w.style.bottom='20px'; localStorage.removeItem('widgetPosition'); }
    function toggleMinimizar() { const w=document.getElementById('widgetTiempoAusencia'),b=document.getElementById('btnMinimizar'); widgetMinimizado=!widgetMinimizado; w.classList.toggle('widget-minimized',widgetMinimizado); b.textContent=widgetMinimizado?'+':'−'; b.title=widgetMinimizado?'Maximizar':'Minimizar'; guardarPosicion(); }

    function verificarPermisoActivo() {
        fetch('widget_tiempo_ausencia.php',{method:'GET',credentials:'same-origin'})
        .then(r=>r.json())
        .then(data=>{ if(data.success&&data.permiso_activo) mostrarWidgetTiempo(data); else ocultarWidgetTiempo(); })
        .catch(()=>ocultarWidgetTiempo());
    }
    function mostrarWidgetTiempo(data) {
        permisoActivo=data;
        document.getElementById('tipoPermiso').textContent=data.tipo_permiso;
        document.getElementById('infoPermiso').innerHTML=`Desde: ${fmt(data.fecha_salida,data.hora_salida)}<br>Hasta: ${fmt(data.fecha_regreso_aprox,data.hora_regreso_aprox)}`;
        const w=document.getElementById('widgetTiempoAusencia');
        if(!data.en_horario_laboral){ w.classList.add('widget-fuera-horario'); document.getElementById('widgetTitulo').textContent='⏸️ Fuera de Horario Laboral'; document.getElementById('btnFinalizar').textContent='⏸️ PAUSADO'; }
        else{ w.classList.remove('widget-fuera-horario'); document.getElementById('widgetTitulo').textContent='⏰ En Ausencia Laboral'; document.getElementById('btnFinalizar').textContent='🏁 FINALIZAR PERMISO'; }
        w.classList.remove('widget-hidden');
        if(intervalTimer) clearInterval(intervalTimer);
        intervalTimer=setInterval(()=>{ if(!permisoActivo)return; const seg=calcSeg(new Date(permisoActivo.inicio_ausencia),new Date()); const h=Math.floor(seg/3600),m=Math.floor((seg%3600)/60),s=seg%60; document.getElementById('tiempoPrincipal').textContent=`${pad(h)}:${pad(m)}:${pad(s)}`; document.getElementById('tiempoInfo').textContent=`${fmtAMPM(new Date())} - Desde ${fmtAMPM(new Date(permisoActivo.inicio_ausencia))}`; },1000);
    }
    function ocultarWidgetTiempo() { document.getElementById('widgetTiempoAusencia').classList.add('widget-hidden'); if(intervalTimer){clearInterval(intervalTimer);intervalTimer=null;} permisoActivo=null; }
    function pad(n){return String(n).padStart(2,'0');}
    function fmtAMPM(f){return f.toLocaleString('es-ES',{hour:'2-digit',minute:'2-digit',hour12:true});}
    function fmt(fecha,hora){const f=new Date(fecha+'T'+hora);return f.toLocaleString('es-ES',{day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit',hour12:true});}
    function calcSeg(inicio,fin){const hl={1:[['07:30','12:00'],['14:00','17:30']],2:[['07:30','12:00'],['14:00','17:30']],3:[['07:30','12:00'],['14:00','17:30']],4:[['07:30','12:00'],['14:00','17:30']],5:[['07:30','12:00'],['14:00','17:00']],6:[['08:00','12:30']]}; let t=0; const fc=new Date(inicio); while(fc.toDateString()<=fin.toDateString()){const d=fc.getDay()===0?7:fc.getDay(),s=fc.toISOString().split('T')[0]; if(hl[d])for(const r of hl[d]){const a=new Date(Math.max(inicio,new Date(s+'T'+r[0]))),b=new Date(Math.min(fin,new Date(s+'T'+r[1]))); if(a<b)t+=(b-a)/1000;} fc.setDate(fc.getDate()+1); fc.setHours(0,0,0,0);} return Math.floor(t);}

    function mostrarConfirmacionFinalizar(id){if(modoConfirmacion){finalizarPermiso(id);return;} modoConfirmacion=true; const b=document.getElementById('btnFinalizar'); b.textContent='✅ CONFIRMAR FINALIZACIÓN'; b.classList.add('confirmar'); if(!document.querySelector('.btn-cancelar')){const bc=document.createElement('button');bc.className='btn-cancelar';bc.textContent='❌ CANCELAR';bc.onclick=cancelarFinalizacion;b.parentNode.insertBefore(bc,b.nextSibling);} setTimeout(()=>{if(modoConfirmacion)cancelarFinalizacion();},10000);}
    function cancelarFinalizacion(){modoConfirmacion=false;const b=document.getElementById('btnFinalizar'),bc=document.querySelector('.btn-cancelar');b.textContent='🏁 FINALIZAR PERMISO';b.classList.remove('confirmar');b.disabled=false;if(bc)bc.remove();}
    function finalizarPermiso(id){if(!id){cancelarFinalizacion();return;} const b=document.getElementById('btnFinalizar'),bc=document.querySelector('.btn-cancelar');b.textContent='⏳ FINALIZANDO...';b.disabled=true;if(bc)bc.style.display='none'; const fd=new FormData();fd.append('id_permiso',id); fetch('finalizar_permiso.php',{method:'POST',body:fd}).then(r=>r.json()).then(data=>{if(data.success){ocultarWidgetTiempo();cancelarFinalizacion();}else throw new Error(data.error);}).catch(()=>{b.textContent='🏁 FINALIZAR PERMISO';b.disabled=false;if(bc)bc.style.display='block';cancelarFinalizacion();});}
</script>
</body>
</html>
