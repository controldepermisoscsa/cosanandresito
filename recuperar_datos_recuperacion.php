<?php
session_start();
require 'conexion.php';
require_once 'validar_horario_recuperacion.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
	http_response_code(401);
	echo json_encode(['success'=>false,'error'=>'No autorizado']);
	exit;
}

$id_usuario = $_SESSION['usuario_id'];

// Obtener recuperación activa aprobada más antigua
$stmt = $pdo->prepare("SELECT * FROM recuperacion_tiempo WHERE id_usuario = ? AND estado = 'aprobado' ORDER BY fecha_inicio_recuperacion ASC, hora_inicio_recuperacion ASC LIMIT 1");
$stmt->execute([$id_usuario]);
$r = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$r) {
	echo json_encode(['success'=>true,'active'=>false]);
	exit;
}

$start = new DateTime($r['fecha_inicio_recuperacion'] . ' ' . $r['hora_inicio_recuperacion']);
$end = new DateTime($r['fecha_fin_recuperacion'] . ' ' . $r['hora_fin_recuperacion']);
$now = new DateTime();

$from = $now > $start ? $now : $start;
$remainingSecs = computeAllowedSeconds($from, $end);

echo json_encode([
	'success' => true,
	'active' => true,
	'recuperacion' => [
		'id_recuperacion' => (int)$r['id_recuperacion'],
		'fecha_inicio' => $r['fecha_inicio_recuperacion'],
		'hora_inicio' => $r['hora_inicio_recuperacion'],
		'fecha_fin' => $r['fecha_fin_recuperacion'],
		'hora_fin' => $r['hora_fin_recuperacion'],
		'tiempo_a_recuperar' => $r['tiempo_a_recuperar'],
		'remaining_seconds' => $remainingSecs
	]
]);
