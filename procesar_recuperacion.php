<?php
session_start();
require 'conexion.php';
require_once 'validar_horario_recuperacion.php';
require_once 'enviar_correo_recuperacion.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
	http_response_code(401);
	echo json_encode(['success'=>false,'error'=>'No autorizado']);
	exit;
}

try {
	$id_usuario = $_SESSION['usuario_id'];
	$id_permiso = isset($_POST['id_permiso']) && $_POST['id_permiso'] !== '' ? intval($_POST['id_permiso']) : null;
	$fi = trim($_POST['fecha_inicio'] ?? '');
	$hi = trim($_POST['hora_inicio'] ?? '');
	$ff = trim($_POST['fecha_fin'] ?? '');
	$hf = trim($_POST['hora_fin'] ?? '');

	if (!$fi || !$hi || !$ff || !$hf) throw new Exception('Datos incompletos');

	// Nuevo: validar que el permiso (si fue enviado) pertenece al usuario y genera recuperación
	if (!$id_permiso) {
		throw new Exception('Debe seleccionar un permiso que genere recuperación.');
	}
	$stmtChk = $pdo->prepare("SELECT id_permiso FROM permisos WHERE id_permiso = ? AND id_usuario = ? AND tipo_pago = 'no_remunerado' AND estado = 'finalizado' AND genera_recuperacion = 1 LIMIT 1");
	$stmtChk->execute([$id_permiso, $id_usuario]);
	if (!$stmtChk->fetch()) throw new Exception('Permiso inválido o no autorizado para generar recuperación.');

	$start = new DateTime("$fi $hi");
	$end = new DateTime("$ff $hf");
	if ($end <= $start) throw new Exception('Intervalo inválido');

	// Validar que el intervalo esté totalmente dentro de horarios permitidos
	$requestedSecs = $end->getTimestamp() - $start->getTimestamp();
	$allowedSecs = computeAllowedSeconds($start, $end);
	if ($allowedSecs !== $requestedSecs) throw new Exception('El intervalo contiene horas fuera de los horarios permitidos');

	// Comprobar tiempo pendiente del usuario
	$stmt = $pdo->prepare("SELECT EXTRACT(EPOCH FROM tiempo_pendiente_recuperar)::int AS secs FROM usuarios WHERE id_usuario = ?");
	$stmt->execute([$id_usuario]);
	$userSecs = intval($stmt->fetchColumn() ?? 0);
	if ($userSecs <= 0) {
		$stmt = $pdo->prepare("SELECT COALESCE(SUM(EXTRACT(EPOCH FROM tiempo_a_recuperar)::int), 0) FROM recuperacion_tiempo WHERE id_usuario = ? AND estado IN ('pendiente','aprobado')");
		$stmt->execute([$id_usuario]);
		$userSecs = intval($stmt->fetchColumn() ?? 0);
	}
	if ($allowedSecs > $userSecs) throw new Exception('La duración solicitada supera el tiempo pendiente disponible');

	// Insertar solicitud
	$stmt = $pdo->prepare("
		INSERT INTO recuperacion_tiempo (
			id_usuario, id_permiso, fecha_solicitud, hora_solicitud,
			fecha_inicio_recuperacion, hora_inicio_recuperacion,
			fecha_fin_recuperacion, hora_fin_recuperacion,
			tiempo_a_recuperar, tiempo_recuperado, estado
		) VALUES (?, ?, CURRENT_DATE, CURRENT_TIME, ?, ?, ?, ?, ?, '00:00:00', 'pendiente')
	");
	$result = $stmt->execute([
		$id_usuario,
		$id_permiso,
		$start->format('Y-m-d'), $start->format('H:i:s'),
		$end->format('Y-m-d'), $end->format('H:i:s'),
		secToTime($allowedSecs)
	]);

	if (!$result) throw new Exception('No se pudo guardar la solicitud en la base de datos');

	$id_rec = $pdo->lastInsertId();

	// Enviar correo formal a Gerencia
	try {
		sendNuevaRecuperacionCorreo($pdo, $id_rec);
	} catch (Exception $e) {
		// No bloquear la creación si falla el correo; registrar
		error_log("Error al enviar correo de nueva recuperación: " . $e->getMessage());
	}

	echo json_encode(['success'=>true,'id_recuperacion'=>$id_rec]);
	exit;

} catch (Exception $e) {
	http_response_code(400);
	echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
	exit;
}
