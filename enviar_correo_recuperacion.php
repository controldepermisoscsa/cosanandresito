<?php
require_once 'config_correo.php';

/**
 * Enviar correo formal a Gerencia sobre nueva solicitud de recuperación
 */
function sendNuevaRecuperacionCorreo(PDO $pdo, int $id_recuperacion): bool {
	$stmt = $pdo->prepare("
		SELECT r.*, u.nombre as usuario_nombre, c.nombre_cargo 
		FROM recuperacion_tiempo r
		JOIN usuarios u ON r.id_usuario = u.id_usuario
		JOIN cargo c ON u.id_cargo = c.id_cargo
		WHERE r.id_recuperacion = ?
	");
	$stmt->execute([$id_recuperacion]);
	$r = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$r) return false;

	$asunto = 'Control de Permisos – Nueva Solicitud de Recuperación de Tiempo';
	$mensaje = "Estimada Gerencia,\n\nSe ha registrado una nueva solicitud de recuperación de tiempo por parte de {$r['usuario_nombre']}.\n\nDetalles:\n- Usuario: {$r['usuario_nombre']}\n- Cargo: {$r['nombre_cargo']}\n- Tiempo a recuperar: {$r['tiempo_a_recuperar']}\n- Fecha inicio propuesta: {$r['fecha_inicio_recuperacion']} {$r['hora_inicio_recuperacion']}\n- Fecha fin propuesta: {$r['fecha_fin_recuperacion']} {$r['hora_fin_recuperacion']}\n\nPor favor, ingrese al sistema para revisar y gestionar esta solicitud:\nhttp://localhost/cosanandresito\n\nAtentamente,\nSistema de Control de Permisos Coosanandresito";

	// Obtener destinatarios Gerencia
	$stmt = $pdo->query("SELECT u.correo, u.nombre FROM usuarios u JOIN cargo c ON u.id_cargo = c.id_cargo WHERE LOWER(c.nombre_cargo) IN ('gerencia','gerente') AND u.correo <> ''");
	$sent = false;
	while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
		try {
			$enviado = ConfigCorreo::enviarCorreo($row['correo'], $asunto, $mensaje, $row['nombre']);
			$sent = $sent || (bool)$enviado;
		} catch (Exception $e) {
			error_log("Error enviando correo a {$row['correo']}: " . $e->getMessage());
		}
	}
	return $sent;
}

/**
 * Enviar correo formal a Gerencia sobre finalización de recuperación
 */
function sendFinalizacionRecuperacionCorreo(PDO $pdo, int $id_recuperacion): bool {
	$stmt = $pdo->prepare("
		SELECT r.*, u.nombre as usuario_nombre, c.nombre_cargo 
		FROM recuperacion_tiempo r
		JOIN usuarios u ON r.id_usuario = u.id_usuario
		JOIN cargo c ON u.id_cargo = c.id_cargo
		WHERE r.id_recuperacion = ?
	");
	$stmt->execute([$id_recuperacion]);
	$r = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$r) return false;

	$asunto = 'Control de Permisos – Recuperación de Tiempo Finalizada';
	$mensaje = "Estimada Gerencia,\n\nEl usuario {$r['usuario_nombre']} ha finalizado exitosamente su proceso de recuperación de tiempo.\n\nResumen:\n- Tiempo recuperado: {$r['tiempo_recuperado']}\n- Fecha de finalización: {$r['fecha_fin_recuperacion']} {$r['hora_fin_recuperacion']}\n\nPuede consultar el detalle completo en el sistema:\nhttp://localhost/cosanandresito\n\nAtentamente,\nSistema de Control de Permisos Coosanandresito";

	$stmt = $pdo->query("SELECT u.correo, u.nombre FROM usuarios u JOIN cargo c ON u.id_cargo = c.id_cargo WHERE LOWER(c.nombre_cargo) IN ('gerencia','gerente') AND u.correo <> ''");
	$sent = false;
	while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
		try {
			$enviado = ConfigCorreo::enviarCorreo($row['correo'], $asunto, $mensaje, $row['nombre']);
			$sent = $sent || (bool)$enviado;
		} catch (Exception $e) {
			error_log("Error enviando correo finalización a {$row['correo']}: " . $e->getMessage());
		}
	}
	return $sent;
}

/**
 * Enviar correo al usuario cuando su recuperación es aprobada
 */
function sendAprobacionRecuperacionCorreo(PDO $pdo, int $id_recuperacion): bool {
	$stmt = $pdo->prepare("
		SELECT r.*, u.nombre as usuario_nombre, u.correo
		FROM recuperacion_tiempo r
		JOIN usuarios u ON r.id_usuario = u.id_usuario
		WHERE r.id_recuperacion = ?
	");
	$stmt->execute([$id_recuperacion]);
	$r = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$r || empty($r['correo'])) return false;

	$asunto = 'Control de Permisos – Recuperación de Tiempo Aprobada';
	$mensaje = "Estimado(a) {$r['usuario_nombre']},\n\nSu solicitud de recuperación de tiempo (ID: {$r['id_recuperacion']}) ha sido aprobada.\nPuede iniciar el proceso desde su perfil en el siguiente enlace:\nhttp://localhost/cosanandresito\n\nAtentamente,\nSistema de Control de Permisos Coosanandresito";

	try {
		return ConfigCorreo::enviarCorreo($r['correo'], $asunto, $mensaje, $r['usuario_nombre']);
	} catch (Exception $e) {
		error_log("Error enviando correo de aprobación al usuario ({$r['correo']}): " . $e->getMessage());
		return false;
	}
}

/**
 * Enviar correo al usuario cuando su recuperación ha finalizado
 */
function sendFinalizacionUsuarioCorreo(PDO $pdo, int $id_recuperacion): bool {
	$stmt = $pdo->prepare("
		SELECT r.*, u.nombre as usuario_nombre, u.correo
		FROM recuperacion_tiempo r
		JOIN usuarios u ON r.id_usuario = u.id_usuario
		WHERE r.id_recuperacion = ?
	");
	$stmt->execute([$id_recuperacion]);
	$r = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$r || empty($r['correo'])) return false;

	$asunto = 'Control de Permisos – Recuperación de Tiempo Finalizada';
	$mensaje = "Estimado(a) {$r['usuario_nombre']},\n\nSu proceso de recuperación de tiempo (ID: {$r['id_recuperacion']}) ha finalizado correctamente. El tiempo recuperado ha sido registrado en el sistema.\n\nPuede consultar su estado actual en:\nhttp://localhost/cosanandresito\n\nAtentamente,\nSistema de Control de Permisos Coosanandresito";

	try {
		return ConfigCorreo::enviarCorreo($r['correo'], $asunto, $mensaje, $r['usuario_nombre']);
	} catch (Exception $e) {
		error_log("Error enviando correo de finalización al usuario ({$r['correo']}): " . $e->getMessage());
		return false;
	}
}
