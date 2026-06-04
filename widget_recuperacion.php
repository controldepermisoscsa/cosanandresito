<?php
// Widget embebible: requiere sesión iniciada
if (!isset($_SESSION)) session_start();
$allowRequest = !in_array(strtolower(trim($_SESSION['cargo'] ?? '')), ['gerente','gerencia']);
?>
<div id="widget-recuperacion" style="border:1px solid #ddd;padding:12px;border-radius:8px;max-width:360px;margin:10px 0;">
	<div id="widget-inner">Cargando widget de recuperación...</div>
</div>

<script>
(function(){
	const urlEstado = 'recuperar_datos_recuperacion.php';
	const urlFinalizar = 'finalizar_recuperacion.php';
	const allowRequest = <?= $allowRequest ? 'true' : 'false' ?>;

	let intervalId = null;
	let remaining = 0;
	let idRec = null;

	function secToHMS(s){
		s = Math.max(0, Math.floor(s));
		const h = Math.floor(s/3600); const m = Math.floor((s%3600)/60); const sec = s%60;
		return `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:${String(sec).padStart(2,'0')}`;
	}

	function isNowInAllowed(){
		const now = new Date();
		const wd = now.getDay(); // 0=Dom..6=Sab
		const hhmm = (t)=> t.getHours()*60 + t.getMinutes();
		const minutes = hhmm(now);
		// Map to allowed ranges (Mon=1..Fri=5)
		const day = wd === 0 ? 7 : wd;
		const ranges = [];
		if (day >=1 && day <=4) { ranges.push([12*60,14*60]); ranges.push([17*60+30,19*60]); }
		else if (day ===5) { ranges.push([12*60,14*60]); ranges.push([17*60,19*60]); }
		for (const r of ranges) if (minutes >= r[0] && minutes < r[1]) return true;
		return false;
	}

	async function fetchEstado(){
		try {
			const r = await fetch(urlEstado, {credentials:'same-origin'});
			const j = await r.json();
			if (!j.success || !j.active) {
				document.getElementById('widget-inner').innerHTML = '<strong>No hay recuperación activa.</strong>' + (allowRequest ? ' <a href="crear_recuperacion.php">Solicitar recuperación</a>' : '');
				clearInterval(intervalId);
				return;
			}
			idRec = j.recuperacion.id_recuperacion;
			remaining = parseInt(j.recuperacion.remaining_seconds,10);
			render();
			if (!intervalId) intervalId = setInterval(tick, 1000);
		} catch (e) {
			document.getElementById('widget-inner').innerText = 'Error al obtener estado: ' + e.message;
		}
	}

	function render(){
		const inner = document.getElementById('widget-inner');
		const estadoNow = isNowInAllowed();
		let html = `<div><strong>Recuperación en curso:</strong> <span id="timer">${secToHMS(remaining)}</span></div>`;
		html += `<div style="margin-top:6px;">${estadoNow ? '<span style="color:green">Activa</span>' : '<span style="color:orange">Pausada (fuera de horario permitido)</span>'}</div>`;
		html += `<div style="margin-top:8px;"><button id="btn-finalizar">FINALIZAR RECUPERACIÓN</button></div>`;
		inner.innerHTML = html;
		document.getElementById('btn-finalizar').addEventListener('click', finalizar);
	}

	function tick(){
		// Si in allowed slot, decrementar
		if (isNowInAllowed() && remaining > 0) remaining--;
		document.getElementById('timer').innerText = secToHMS(remaining);
		if (remaining <= 0) {
			document.getElementById('timer').innerText = '00:00:00';
		}
	}

	async function finalizar(){
		if (!confirm('¿Finalizar recuperación ahora?')) return;
		try {
			const fd = new FormData();
			fd.append('id_recuperacion', idRec);
			const r = await fetch(urlFinalizar, {method:'POST', credentials:'same-origin', body:fd});
			const j = await r.json();
			if (j.success) {
				document.getElementById('widget-inner').innerHTML = '<strong>Recuperación finalizada correctamente.</strong>';
				if (intervalId) clearInterval(intervalId);
				setTimeout(()=>location.reload(),1400);
			} else {
				alert('Error: ' + (j.error || 'Error desconocido'));
			}
		} catch (e) {
			alert('Error de conexión: ' + e.message);
		}
	}

	// Iniciar
	fetchEstado();
	// refrescar cada 10s para cambios de servidor
	setInterval(fetchEstado, 10000);
})();
</script>
