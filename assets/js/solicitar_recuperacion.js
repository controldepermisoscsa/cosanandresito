document.addEventListener('DOMContentLoaded', function(){
	const form = document.getElementById('formSolicitarRecuperacion');
	if (!form) return;
	const select = document.getElementById('id_permiso_select');
	const detalles = document.getElementById('permisoDetalles');
	const calc = document.getElementById('calcDuration');
	const pendingEl = document.getElementById('pendingTimeCounter');
	const btnReset = document.getElementById('btnResetForm');
	const msg = document.getElementById('formMessage');

	function updateDetails() {
		const id = select.value;
		if (!id) { detalles.innerHTML = 'Seleccione un permiso para ver detalles.'; return; }
		const opt = select.querySelector('option[value="'+id+'"]');
		detalles.innerHTML = '<strong>Motivo:</strong> ' + (opt.dataset.motivo || '-') + '<br><strong>Salida:</strong> ' + (opt.dataset.salida || '-') + ' <br><strong>Regreso approx:</strong> ' + (opt.dataset.regreso || '-');
	}
	select && select.addEventListener('change', updateDetails);
	updateDetails();

	function calcularDuracion() {
		const fi = form.fecha_inicio.value, hi = form.hora_inicio.value, ff = form.fecha_fin.value, hf = form.hora_fin.value;
		if (!fi || !hi || !ff || !hf) { calc.textContent = '--:--'; return; }
		const inicio = new Date(fi + 'T' + hi), fin = new Date(ff + 'T' + hf);
		if (isNaN(inicio) || isNaN(fin) || fin <= inicio) { calc.textContent = '--:--'; return; }
		const diff = Math.floor((fin - inicio) / 1000);
		const h = Math.floor(diff/3600), m = Math.floor((diff%3600)/60);
		calc.textContent = String(h).padStart(2,'0') + ':' + String(m).padStart(2,'0');
	}
	['fecha_inicio','hora_inicio','fecha_fin','hora_fin'].forEach(name => form[name].addEventListener('input', calcularDuracion));
	calcularDuracion();

	btnReset && btnReset.addEventListener('click', function(){ form.reset(); calcularDuracion(); updateDetails(); msg.textContent = ''; });

	form.addEventListener('submit', async function(e){
		e.preventDefault();
		msg.textContent = '';
		const submitBtn = form.querySelector('button[type="submit"]');
		submitBtn.disabled = true;

		const nombre = form.nombre.value.trim();
		if (!nombre) { alert('Por favor ingresa tu nombre.'); submitBtn.disabled = false; return; }
		if (!select.value) { alert('Selecciona el permiso que genera recuperación.'); submitBtn.disabled = false; return; }

		const fi = form.fecha_inicio.value, hi = form.hora_inicio.value, ff = form.fecha_fin.value, hf = form.hora_fin.value;
		if (!fi || !hi || !ff || !hf) { alert('Completa fecha y hora de inicio/fin.'); submitBtn.disabled = false; return; }

		// Validar horario permitido (server-side)
		try {
			const chk = await fetch('validar_horario_recuperacion.php', {
				method: 'POST',
				credentials: 'same-origin',
				body: new URLSearchParams({ action: 'check', fecha_inicio: fi, hora_inicio: hi, fecha_fin: ff, hora_fin: hf })
			});
			const chkJson = await chk.json();
			if (!chkJson.success) { alert(chkJson.error || 'Intervalo fuera de horarios permitidos'); submitBtn.disabled = false; return; }
			// Comprobar que no exceda tiempo pendiente (si se informó)
			const pending = pendingEl ? parseInt(pendingEl.getAttribute('data-seconds') || '0', 10) : 0;
			if (chkJson.seconds > pending) { alert('La duración solicitada supera el tiempo pendiente disponible.'); submitBtn.disabled = false; return; }
		} catch (err) { alert('Error validando horario: ' + err); submitBtn.disabled = false; return; }

		// Enviar al backend
		try {
			const fd = new FormData(form);
			const res = await fetch('procesar_recuperacion.php', { method: 'POST', credentials: 'same-origin', body: fd });
			const txt = await res.text();
			let data;
			try { data = JSON.parse(txt); } catch(e) { throw new Error('Respuesta no válida: ' + txt); }
			if (data.success) {
				alert('Solicitud enviada correctamente. ID: ' + (data.id_recuperacion || ''));
				window.location.href = 'ver_permisos.php?msg=' + encodeURIComponent('Solicitud de recuperación enviada');
			} else {
				throw new Error(data.error || 'Error desconocido');
			}
		} catch (err) {
			console.error(err);
			alert('Error: ' + err.message);
			msg.textContent = 'Error: ' + err.message;
		} finally {
			submitBtn.disabled = false;
		}
	});
});
