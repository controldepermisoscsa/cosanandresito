<?php
session_start();
require 'conexion.php';
require_once 'validar_horario_recuperacion.php';

if (!isset($_SESSION['usuario_id'])) {
	header('Location: login.php');
	exit;
}

// Gerencia no crea recuperaciones
$cargo = strtolower(trim($_SESSION['cargo'] ?? ''));
if (in_array($cargo, ['gerente','gerencia'])) {
	header('Location: aprobar_recuperacion.php');
	exit;
}

$id_usuario = $_SESSION['usuario_id'];
$nombre = $_SESSION['nombre'] ?? 'Usuario';

// Permisos que generan recuperación
$stmt = $pdo->prepare("SELECT id_permiso, tipo_permiso, motivo, fecha_salida, hora_salida, fecha_regreso_aprox, hora_regreso_aprox FROM permisos WHERE id_usuario = ? AND estado = 'finalizado' ORDER BY fecha_salida DESC");
$stmt->execute([$id_usuario]);
$permisos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Tiempo pendiente (primero usuarios.tiempo_pendiente_recuperar, fallback a sumatoria de recuperaciones pendientes/aprobadas)
$stmt = $pdo->prepare("SELECT EXTRACT(EPOCH FROM tiempo_pendiente_recuperar)::int AS secs FROM usuarios WHERE id_usuario = ?");
$stmt->execute([$id_usuario]);
$pendingSecs = intval($stmt->fetchColumn() ?? 0);
if ($pendingSecs <= 0) {
	$stmt = $pdo->prepare("SELECT COALESCE(SUM(EXTRACT(EPOCH FROM tiempo_a_recuperar)::int), 0) FROM recuperacion_tiempo WHERE id_usuario = ? AND estado IN ('pendiente','aprobado')");
	$stmt->execute([$id_usuario]);
	$pendingSecs = intval($stmt->fetchColumn() ?? 0);
}
function secToHMS(int $secs){ return gmdate('H:i:s', max(0, (int)$secs)); }
function humanDuration(int $secs){
	$secs = max(0, (int)$secs);
	$h = floor($secs/3600); $m = floor(($secs%3600)/60); $s = $secs%60;
	$parts = [];
	if ($h) $parts[] = $h . ' ' . ($h===1 ? 'hora' : 'horas');
	if ($m) $parts[] = $m . ' ' . ($m===1 ? 'minuto' : 'minutos');
	if ($s || empty($parts)) $parts[] = $s . ' ' . ($s===1 ? 'segundo' : 'segundos');
	return implode(', ', $parts);
}
$pendingStr = secToHMS($pendingSecs);
$pendingHuman = humanDuration($pendingSecs);

// Panel de regreso según cargo
$archivoPanel = match ($cargo) {
	'administrador' => 'admin_inicio.php',
	'coordinador' => 'coordinador_inicio.php',
	'auxiliar' => 'auxiliar_inicio.php',
	'administrativo' => 'administrativo_inicio.php',
	'gerente','gerencia' => 'gerente_inicio.php',
	default => 'ver_permisos.php',
};
?>
<!doctype html>
<html lang="es">
<head>
	<meta charset="utf-8">
	<title>Solicitar Recuperación</title>
	<link rel="stylesheet" href="assets/css/recuperacion.css">
</head>
<body>
	<main class="rec-card" aria-labelledby="rec-title">
		<h1 id="rec-title" class="rec-title">Solicita tu recuperación de tiempo</h1>
		<p class="rec-sub">Elige el permiso que genera recuperación y selecciona franjas válidas.</p>

		<div class="rec-top">
			<div>
				<?php if ($pendingSecs > 0): ?>
					<h2 class="rec-pending-title">Tienes <?= htmlspecialchars($pendingHuman) ?> por recuperar</h2>
					<div id="pendingTimeCounter" class="rec-pending-sub" data-seconds="<?= $pendingSecs ?>">Equivalente: <strong><?= htmlspecialchars($pendingStr) ?></strong></div>
				<?php else: ?>
					<h2 class="rec-pending-title">No tienes tiempo por recuperar</h2>
				<?php endif; ?>
			</div>
			<div class="rec-top-actions">
				<a href="<?= $archivoPanel ?>" class="rec-btn rec-btn-ghost">Volver al panel</a>
			</div>
		</div>

		<?php if (empty($permisos)): ?>
			<div class="rec-alert rec-alert-error">No se encontraron permisos que generen recuperación.</div>
		<?php endif; ?>

		<form id="formSolicitarRecuperacion" method="post" novalidate>
			<input type="hidden" name="id_usuario" value="<?= htmlspecialchars($id_usuario) ?>">

			<label class="rec-label" for="id_permiso_select">Permiso que genera recuperación</label>
			<select id="id_permiso_select" name="id_permiso" class="rec-input" <?= empty($permisos) ? 'disabled' : '' ?>>
				<option value="">-- Seleccione --</option>
				<?php foreach ($permisos as $p): ?>
					<?php $salida = $p['fecha_salida'] . ' ' . substr($p['hora_salida'],0,5); $reg = $p['fecha_regreso_aprox'] . ' ' . substr($p['hora_regreso_aprox'],0,5); ?>
					<option value="<?= $p['id_permiso'] ?>"
						data-motivo="<?= htmlspecialchars($p['motivo']) ?>"
						data-salida="<?= htmlspecialchars($salida) ?>"
						data-regreso="<?= htmlspecialchars($reg) ?>"><?= $p['fecha_salida'] ?> — <?= substr($p['motivo'],0,30) ?></option>
				<?php endforeach; ?>
			</select>

			<div id="permisoDetalles" class="rec-help" style="margin-bottom:12px;">Seleccione un permiso para ver detalles.</div>

			<label class="rec-label" for="nombre">Nombre</label>
			<input id="nombre" name="nombre" class="rec-input" value="<?= htmlspecialchars($nombre) ?>" placeholder="Tu nombre" required>

			<div class="rec-row">
				<div class="rec-col">
					<label class="rec-label" for="fecha_inicio">Fecha inicio</label>
					<input id="fecha_inicio" name="fecha_inicio" type="date" class="rec-input" required>
				</div>
				<div class="rec-col">
					<label class="rec-label" for="hora_inicio">Hora inicio</label>
					<input id="hora_inicio" name="hora_inicio" type="time" class="rec-input" required>
				</div>
			</div>

			<div class="rec-row">
				<div class="rec-col">
					<label class="rec-label" for="fecha_fin">Fecha final</label>
					<input id="fecha_fin" name="fecha_fin" type="date" class="rec-input" required>
				</div>
				<div class="rec-col">
					<label class="rec-label" for="hora_fin">Hora fin</label>
					<input id="hora_fin" name="hora_fin" type="time" class="rec-input" required>
				</div>
			</div>

			<div id="previewTime" class="rec-help">Tiempo estimado: <strong id="calcDuration">--:--</strong></div>

			<div class="rec-actions">
				<button type="submit" class="rec-btn" <?= $pendingSecs <= 0 || empty($permisos) ? 'disabled aria-disabled="true"' : '' ?>>Enviar solicitud</button>
				<button type="button" id="btnResetForm" class="rec-btn rec-btn-ghost">Limpiar</button>
			</div>

			<div id="formMessage" class="rec-help" style="margin-top:8px;"></div>
		</form>
	</main>

	<script src="assets/js/recuperacion.js" defer></script>
	<script src="assets/js/solicitar_recuperacion.js" defer></script>
</body>
</html>
