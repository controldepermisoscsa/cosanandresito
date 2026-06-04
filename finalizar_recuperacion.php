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
	$id_rec = intval($_POST['id_recuperacion'] ?? 0);
	if (!$id_rec) throw new Exception('ID inválido');

	$stmt = $pdo->prepare("SELECT * FROM recuperacion_tiempo WHERE id_recuperacion = ? AND estado = 'aprobado'");
	$stmt->execute([$id_rec]);
	$r = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$r) throw new Exception('Recuperación no encontrada o no aprobada');

	if ($r['id_usuario'] != $_SESSION['usuario_id']) throw new Exception('No puedes finalizar una recuperación que no te pertenece');

	$start = new DateTime($r['fecha_inicio_recuperacion'] . ' ' . $r['hora_inicio_recuperacion']);
	$final = new DateTime(); // ahora

	// Calcular segundos realmente recuperados (solo franjas válidas entre start y final)
	$recoveredSecs = computeAllowedSeconds($start, $final);

	$timeRecovered = secToTime($recoveredSecs);

	// Actualizar recuperacion_tiempo
	$pdo->beginTransaction();

	$upd = $pdo->prepare("UPDATE recuperacion_tiempo SET estado = 'finalizado', tiempo_recuperado = ?, fecha_fin_recuperacion = ?, hora_fin_recuperacion = ? WHERE id_recuperacion = ?");
	$upd->execute([$timeRecovered, $final->format('Y-m-d'), $final->format('H:i:s'), $id_rec]);

	// Descontar de usuarios.tiempo_pendiente_recuperar
	$stmtUser = $pdo->prepare("SELECT TIME_TO_SEC(tiempo_pendiente_recuperar) as secs FROM usuarios WHERE id_usuario = ?");
	$stmtUser->execute([$r['id_usuario']]);
	$userSecs = intval($stmtUser->fetchColumn() ?? 0);
	$newSecs = max(0, $userSecs - $recoveredSecs);
	$updUser = $pdo->prepare("UPDATE usuarios SET tiempo_pendiente_recuperar = ? WHERE id_usuario = ?");
	$updUser->execute([secToTime($newSecs), $r['id_usuario']]);

	$pdo->commit();

	// Notificar a Gerencia
	try {
		sendFinalizacionRecuperacionCorreo($pdo, $id_rec);
	} catch (Exception $e) {
		error_log("Error enviando correo de finalización: " . $e->getMessage());
	}

	// Nuevo: notificar al usuario que su recuperación finalizó
	try {
		sendFinalizacionUsuarioCorreo($pdo, $id_rec);
	} catch (Exception $e) {
		error_log("Error enviando correo de finalización al usuario: " . $e->getMessage());
	}

	echo json_encode(['success'=>true,'tiempo_recuperado'=>$timeRecovered,'nuevo_tiempo_pendiente'=>secToTime($newSecs)]);
	exit;

} catch (Exception $e) {
	if ($pdo->inTransaction()) $pdo->rollBack();
	http_response_code(400);
	echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
	exit;
}
