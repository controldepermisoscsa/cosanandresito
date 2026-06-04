<?php
session_start();
require 'conexion.php';
require_once 'validar_horario_recuperacion.php';
require_once 'enviar_correo_recuperacion.php'; // <-- nuevo: envío de correo a Gerencia

if (!isset($_SESSION['usuario_id'])) {
	header('Location: login.php');
	exit;
}

$id_usuario = $_SESSION['usuario_id'];
$nombre = $_SESSION['nombre'] ?? 'Usuario';
$id_permiso = isset($_GET['id_permiso']) ? intval($_GET['id_permiso']) : null;

// Obtener tiempo pendiente desde usuarios.tiempo_pendiente_recuperar o sumatoria en recuperacion_tiempo
$stmt = $pdo->prepare("SELECT TIME_TO_SEC(tiempo_pendiente_recuperar) as secs FROM usuarios WHERE id_usuario = ?");
$stmt->execute([$id_usuario]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);
$pendingSecs = intval($data['secs'] ?? 0);

if ($pendingSecs <= 0) {
	$stmt = $pdo->prepare("SELECT COALESCE(SUM(TIME_TO_SEC(tiempo_a_recuperar)),0) as sumsecs FROM recuperacion_tiempo WHERE id_usuario = ? AND estado IN ('pendiente','aprobado')");
	$stmt->execute([$id_usuario]);
	$pendingSecs = intval($stmt->fetchColumn() ?? 0);
}

function formatHMS($secs) {
	$h = floor($secs / 3600);
	$m = floor(($secs % 3600) / 60);
	$s = $secs % 60;
	return sprintf('%02d:%02d:%02d', $h, $m, $s);
}
$pendingStr = formatHMS($pendingSecs);

// Nuevo: función legible y ruta "volver al panel"
function humanDuration($secs){
	$secs = max(0, (int)$secs);
	$h = floor($secs/3600);
	$m = floor(($secs%3600)/60);
	$s = $secs%60;
	$parts = [];
	if ($h) $parts[] = $h . ' ' . ($h === 1 ? 'hora' : 'horas');
	if ($m) $parts[] = $m . ' ' . ($m === 1 ? 'minuto' : 'minutos');
	if ($s || empty($parts)) $parts[] = $s . ' ' . ($s === 1 ? 'segundo' : 'segundos');
	return implode(', ', $parts);
}
$pendingHuman = humanDuration($pendingSecs);

// Determinar panel para "Volver al panel"
$cargo = strtolower(trim($_SESSION['cargo'] ?? ''));
$archivoPanel = match ($cargo) {
	'administrador' => 'admin_inicio.php',
	'coordinador' => 'coordinador_inicio.php',
	'auxiliar' => 'auxiliar_inicio.php',
	'administrativo' => 'administrativo_inicio.php',
	'gerente','gerencia' => 'gerente_inicio.php',
	default => 'ver_permisos.php',
};

// Validación completa en POST, bloqueo cuando no hay tiempo pendiente, cálculo de segundos permitidos, inserción en DB y envío de correo a Gerencia; mostrar mensaje de éxito/errores y enviar id_permiso en el formulario.
$errors = [];
$old = [];
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$old = $_POST;
	$nombre = trim($_POST['nombre'] ?? '');
	$fecha_inicio = trim($_POST['fecha_inicio'] ?? '');
	$hora_inicio = trim($_POST['hora_inicio'] ?? '');
	$fecha_fin = trim($_POST['fecha_fin'] ?? $_POST['fecha_final'] ?? '');
	$hora_fin = trim($_POST['hora_fin'] ?? '');
	$id_permiso_post = isset($_POST['id_permiso']) && $_POST['id_permiso'] !== '' ? intval($_POST['id_permiso']) : ($id_permiso ?? null);

	// Validaciones básicas
	if ($nombre === '') $errors[] = 'Por favor ingresa tu nombre.';
	if ($fecha_inicio === '' || $hora_inicio === '') $errors[] = 'Fecha y hora de inicio son obligatorias.';
	if ($fecha_fin === '' || $hora_fin === '') $errors[] = 'Fecha y hora final son obligatorias.';
	if ($pendingSecs <= 0) $errors[] = 'No tienes tiempo pendiente por recuperar.';

	// Validación de intervalos y horarios permitidos
	if (empty($errors)) {
		try {
			$start = new DateTime("$fecha_inicio $hora_inicio");
			$end = new DateTime("$fecha_fin $hora_fin");
			if ($end <= $start) {
				$errors[] = 'La fecha/hora final debe ser posterior a la de inicio.';
			} else {
				$requestedSecs = $end->getTimestamp() - $start->getTimestamp();
				$allowedSecs = computeAllowedSeconds($start, $end);
				if ($allowedSecs !== $requestedSecs) {
					$errors[] = 'El intervalo contiene horas fuera de los horarios permitidos (lun‑vie 12:00–14:00; lun‑jue 17:30–19:00; vie 17:00–19:00).';
				} elseif ($allowedSecs <= 0) {
					$errors[] = 'El intervalo seleccionado no contiene horas válidas para recuperación.';
				} elseif ($allowedSecs > $pendingSecs) {
					$errors[] = 'La duración solicitada (' . secToTime($allowedSecs) . ') supera el tiempo pendiente disponible (' . formatHMS($pendingSecs) . ').';
				} else {
					// Guardar en BD
					try {
						$pdo->beginTransaction();
						$stmt = $pdo->prepare("
							INSERT INTO recuperacion_tiempo (
								id_usuario, id_permiso, fecha_solicitud, hora_solicitud,
								fecha_inicio_recuperacion, hora_inicio_recuperacion,
								fecha_fin_recuperacion, hora_fin_recuperacion,
								tiempo_a_recuperar, tiempo_recuperado, estado
							) VALUES (?, ?, CURDATE(), CURTIME(), ?, ?, ?, ?, ?, '00:00:00', 'pendiente')
						");
						$tiempoARecuperar = secToTime($allowedSecs);
						$ok = $stmt->execute([
							$id_usuario,
							$id_permiso_post,
							$start->format('Y-m-d'), $start->format('H:i:s'),
							$end->format('Y-m-d'), $end->format('H:i:s'),
							$tiempoARecuperar
						]);
						if (!$ok) {
							$pdo->rollBack();
							$errors[] = 'No se pudo guardar la solicitud. Intenta nuevamente más tarde.';
						} else {
							$id_rec = $pdo->lastInsertId();
							$pdo->commit();
							// Notificar a Gerencia (no bloquear si falla)
							try { sendNuevaRecuperacionCorreo($pdo, (int)$id_rec); } catch (Exception $e) { error_log('Correo fallo: '.$e->getMessage()); }
							$success = "Solicitud enviada correctamente (ID #{$id_rec}). Se notificó a Gerencia.";
							$old = [];
						}
					} catch (Exception $ex) {
						if ($pdo->inTransaction()) $pdo->rollBack();
						$errors[] = 'Error al procesar la solicitud: ' . $ex->getMessage();
					}
				}
			}
		} catch (Exception $e) {
			$errors[] = 'Formato de fecha/hora inválido.';
		}
	}
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="utf-8">
	<title>Solicitar Recuperación</title>
	<link rel="stylesheet" href="assets/css/recuperacion.css">
</head>
<body>
	<main class="rec-card" aria-labelledby="rec-title">
		<h1 id="rec-title" class="rec-title">Solicita tu recuperación de tiempo</h1>
		<p class="rec-sub">Rápido y sencillo — completa las fechas y revisa el tiempo estimado antes de enviar.</p>

		<!-- Nuevo: mensaje de tiempo pendiente + botón volver al panel -->
		<div class="rec-top">
			<div>
				<?php if ($pendingSecs > 0): ?>
					<h2 id="pendingTimeTitle" class="rec-pending-title" aria-live="polite">Tienes <?= htmlspecialchars($pendingHuman) ?> por recuperar</h2>
					<div id="pendingTimeCounter" class="rec-pending-sub" data-seconds="<?= $pendingSecs ?>">Equivalente: <strong><?= htmlspecialchars($pendingStr) ?></strong></div>
				<?php else: ?>
					<h2 id="pendingTimeTitle" class="rec-pending-title" aria-live="polite">No tienes tiempo por recuperar</h2>
				<?php endif; ?>
			</div>
			<div class="rec-top-actions">
				<a href="<?= $archivoPanel ?>" class="rec-btn rec-btn-ghost">Volver al panel</a>
			</div>
		</div>

		<!-- Mostrar éxito/errores -->
		<?php if (!empty($success)): ?>
			<div class="rec-alert rec-alert-success" role="status"><?= htmlspecialchars($success) ?></div>
		<?php endif; ?>

		<?php if (!empty($errors)): ?>
			<div class="rec-alert rec-alert-error" role="alert">
				<ul>
					<?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<form id="formRecuperacion" method="post" novalidate>
			<input type="hidden" name="id_permiso" value="<?= htmlspecialchars($id_permiso ?? '') ?>">
			<label class="rec-label" for="nombre">Nombre</label>
			<input id="nombre" name="nombre" class="rec-input" value="<?= htmlspecialchars($old['nombre'] ?? $_SESSION['nombre'] ?? '') ?>" placeholder="Ej: Fabián Vargas" required>

			<div class="rec-row">
				<div class="rec-col">
					<label class="rec-label" for="fecha_inicio">Fecha inicio</label>
					<input id="fecha_inicio" name="fecha_inicio" type="date" class="rec-input" value="<?= htmlspecialchars($old['fecha_inicio'] ?? '') ?>" required>
				</div>
				<div class="rec-col">
					<label class="rec-label" for="hora_inicio">Hora inicio</label>
					<input id="hora_inicio" name="hora_inicio" type="time" class="rec-input" value="<?= htmlspecialchars($old['hora_inicio'] ?? '') ?>" required>
				</div>
			</div>

			<div class="rec-row">
				<div class="rec-col">
					<label class="rec-label" for="fecha_fin">Fecha final</label>
					<input id="fecha_fin" name="fecha_fin" type="date" class="rec-input" value="<?= htmlspecialchars($old['fecha_fin'] ?? '') ?>" required>
				</div>
				<div class="rec-col">
					<label class="rec-label" for="hora_fin">Hora fin</label>
					<input id="hora_fin" name="hora_fin" type="time" class="rec-input" value="<?= htmlspecialchars($old['hora_fin'] ?? '') ?>" required>
				</div>
			</div>

			<div id="previewTime" class="rec-help">Tiempo estimado: <strong id="calcDuration">--:--</strong></div>

			<div class="rec-actions">
				<button type="submit" class="rec-btn" <?= $pendingSecs <= 0 ? 'disabled aria-disabled="true"' : '' ?>>Enviar solicitud</button>
				<button type="button" id="btnResetForm" class="rec-btn rec-btn-ghost">Limpiar</button>
			</div>

			<?php if ($pendingSecs <= 0): ?>
				<div class="rec-help" style="color:#dc3545">No tienes tiempo pendiente para recuperar. Si crees que esto es un error, contacta a tu coordinador.</div>
			<?php endif; ?>
		</form>
	</main>

	<script src="assets/js/recuperacion.js" defer></script>
</body>
</html>
