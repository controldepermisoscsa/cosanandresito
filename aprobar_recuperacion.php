<?php
session_start();
require 'conexion.php';
require_once 'enviar_correo_recuperacion.php';

if (!isset($_SESSION['usuario_id']) || !in_array(strtolower($_SESSION['cargo'] ?? ''), ['gerente','gerencia'])) {
	header('Location: login.php');
	exit;
}

$mensaje = '';
try {
	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		$id_rec = intval($_POST['id_recuperacion'] ?? 0);
		$accion = $_POST['accion'] ?? '';
		if (!$id_rec) throw new Exception('ID inválido');

		if ($accion === 'aprobar') {
			$stmt = $pdo->prepare("UPDATE recuperacion_tiempo SET estado='aprobado', aprobado_por = ?, fecha_aprobacion = CURRENT_DATE, hora_aprobacion = CURRENT_TIME WHERE id_recuperacion = ?");
			$stmt->execute([$_SESSION['usuario_id'], $id_rec]);
			$mensaje = 'Solicitud aprobada';
			// Notificar al usuario
			try {
				sendAprobacionRecuperacionCorreo($pdo, $id_rec);
			} catch (Exception $e) {
				error_log("Error enviando correo de aprobación al usuario (ID_rec: $id_rec): " . $e->getMessage());
			}
		} elseif ($accion === 'rechazar') {
			$motivo = trim($_POST['motivo'] ?? '');
			$stmt = $pdo->prepare("UPDATE recuperacion_tiempo SET estado='rechazado', tiempo_recuperado = '00:00:00' WHERE id_recuperacion = ?");
			$stmt->execute([$id_rec]);
			$mensaje = 'Solicitud rechazada';
		}
	}
} catch (Exception $e) {
	$mensaje = 'Error: ' . $e->getMessage();
}

// Listar pendientes
$stmt = $pdo->query("SELECT r.*, u.nombre as usuario, c.nombre_cargo FROM recuperacion_tiempo r JOIN usuarios u ON r.id_usuario = u.id_usuario JOIN cargo c ON u.id_cargo = c.id_cargo WHERE r.estado = 'pendiente' ORDER BY r.fecha_solicitud DESC");
$pendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><title>Aprobar Recuperaciones</title></head>
<body style="font-family:Arial;padding:20px;">
	<h1>Solicitudes de Recuperación Pendientes</h1>
	<?php if ($mensaje) echo "<p><strong>$mensaje</strong></p>"; ?>
	<?php if (empty($pendientes)): ?>
		<p>No hay solicitudes pendientes.</p>
	<?php else: ?>
		<table border="1" cellpadding="6" style="border-collapse:collapse;">
			<tr><th>ID</th><th>Usuario</th><th>Cargo</th><th>Inicio</th><th>Fin</th><th>Tiempo</th><th>Acciones</th></tr>
			<?php foreach ($pendientes as $p): ?>
				<tr>
					<td><?= $p['id_recuperacion'] ?></td>
					<td><?= htmlspecialchars($p['usuario']) ?></td>
					<td><?= htmlspecialchars($p['nombre_cargo']) ?></td>
					<td><?= $p['fecha_inicio_recuperacion'] ?> <?= $p['hora_inicio_recuperacion'] ?></td>
					<td><?= $p['fecha_fin_recuperacion'] ?> <?= $p['hora_fin_recuperacion'] ?></td>
					<td><?= $p['tiempo_a_recuperar'] ?></td>
					<td>
						<form method="POST" style="display:inline;">
							<input type="hidden" name="id_recuperacion" value="<?= $p['id_recuperacion'] ?>">
							<button type="submit" name="accion" value="aprobar">Aprobar</button>
						</form>
						<form method="POST" style="display:inline;">
							<input type="hidden" name="id_recuperacion" value="<?= $p['id_recuperacion'] ?>">
							<button type="submit" name="accion" value="rechazar">Rechazar</button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
	<?php endif; ?>
</body>
</html>
