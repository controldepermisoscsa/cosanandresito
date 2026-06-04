<?php
// ...archivo de utilidades para validar horarios de recuperación...

/**
 * Devuelve los rangos permitidos (HH:MM) para un día de la semana (1=Lun..7=Dom)
 */
function getAllowedSlotsForDay(int $weekday): array {
	$slots = [];
	if ($weekday >= 1 && $weekday <= 4) {
		$slots[] = ['12:00', '14:00'];
		$slots[] = ['17:30', '19:00'];
	} elseif ($weekday === 5) {
		$slots[] = ['12:00', '14:00'];
		$slots[] = ['17:00', '19:00'];
	}
	return $slots;
}

function secToTime(int $secs): string {
	return gmdate('H:i:s', max(0, (int)$secs));
}

/**
 * Calcula segundos que caen dentro de los rangos permitidos entre dos DateTime
 */
function computeAllowedSeconds(DateTime $start, DateTime $end): int {
	if ($end <= $start) return 0;
	$total = 0;
	$cursor = clone $start;
	$cursor->setTime((int)$cursor->format('H'), (int)$cursor->format('i'), (int)$cursor->format('s'));
	$oneDay = new DateInterval('P1D');

	while ($cursor < $end) {
		$fecha = $cursor->format('Y-m-d');
		$weekday = (int)$cursor->format('N');
		foreach (getAllowedSlotsForDay($weekday) as $slot) {
			$slotStart = DateTime::createFromFormat('Y-m-d H:i', "$fecha {$slot[0]}");
			$slotEnd = DateTime::createFromFormat('Y-m-d H:i', "$fecha {$slot[1]}");
			$overlapStart = max($start, $slotStart);
			$overlapEnd = min($end, $slotEnd);
			if ($overlapStart < $overlapEnd) {
				$total += $overlapEnd->getTimestamp() - $overlapStart->getTimestamp();
			}
		}
		$cursor->add($oneDay);
		$cursor->setTime(0, 0, 0);
	}
	return (int)$total;
}

function isDateTimeWithinAllowed(DateTime $dt): bool {
	$weekday = (int)$dt->format('N');
	$fecha = $dt->format('Y-m-d');
	foreach (getAllowedSlotsForDay($weekday) as $slot) {
		$slotStart = DateTime::createFromFormat('Y-m-d H:i', "$fecha {$slot[0]}");
		$slotEnd = DateTime::createFromFormat('Y-m-d H:i', "$fecha {$slot[1]}");
		if ($dt >= $slotStart && $dt <= $slotEnd) return true;
	}
	return false;
}

// Si se llama directamente vía POST action=check -> respuesta JSON (para validación AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'check') {
	header('Content-Type: application/json; charset=utf-8');
	$fi = trim($_POST['fecha_inicio'] ?? '');
	$hi = trim($_POST['hora_inicio'] ?? '');
	$ff = trim($_POST['fecha_fin'] ?? '');
	$hf = trim($_POST['hora_fin'] ?? '');
	if (!$fi || !$hi || !$ff || !$hf) {
		echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
		exit;
	}
	try {
		$start = new DateTime("$fi $hi");
		$end = new DateTime("$ff $hf");
		if ($end <= $start) {
			echo json_encode(['success' => false, 'error' => 'El intervalo debe terminar después del inicio']);
			exit;
		}
		$requestedSeconds = $end->getTimestamp() - $start->getTimestamp();
		$allowedSeconds = computeAllowedSeconds($start, $end);
		if ($allowedSeconds !== $requestedSeconds) {
			echo json_encode(['success' => false, 'error' => 'El intervalo contiene horas fuera de los horarios permitidos']);
			exit;
		}
		// OK
		echo json_encode(['success' => true, 'seconds' => $allowedSeconds, 'time' => secToTime($allowedSeconds)]);
	} catch (Exception $e) {
		echo json_encode(['success' => false, 'error' => 'Formato de fecha/hora inválido']);
	}
	exit;
}
