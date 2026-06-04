document.addEventListener('DOMContentLoaded', function(){
	const fi = document.querySelector('input[name="fecha_inicio"]');
	const hi = document.querySelector('input[name="hora_inicio"]');
	const ff = document.querySelector('input[name="fecha_fin"]');
	const hf = document.querySelector('input[name="hora_fin"]');
	const calc = document.getElementById('calcDuration');
	const form = document.getElementById('formRecuperacion');
	const btnReset = document.getElementById('btnResetForm');

	function actualizarDuracion(){
		if(!fi.value || !hi.value || !ff.value || !hf.value){ calc.textContent = '--:--'; return; }
		const inicio = new Date(fi.value + 'T' + hi.value);
		const fin = new Date(ff.value + 'T' + hf.value);
		if(isNaN(inicio.getTime()) || isNaN(fin.getTime()) || fin <= inicio){ calc.textContent = '--:--'; return; }
		const diff = Math.floor((fin - inicio)/1000);
		const h = Math.floor(diff/3600), m = Math.floor((diff%3600)/60);
		calc.textContent = String(h).padStart(2,'0') + ':' + String(m).padStart(2,'0');
	}

	[fi,hi,ff,hf].forEach(el => { if(el) el.addEventListener('input', actualizarDuracion); });
	actualizarDuracion();

	// Nuevo: mostrar/contar tiempo pendiente (si existe) y bloquear submit si es 0
	(function(){
		const pendingEl = document.getElementById('pendingTimeCounter');
		const pendingTitle = document.getElementById('pendingTimeTitle');
		const submitBtn = document.querySelector('#formRecuperacion button[type="submit"]');
		if (!pendingEl || !pendingTitle) return;

		let pendingSecs = parseInt(pendingEl.getAttribute('data-seconds') || '0', 10);

		function formatHumanDuration(s){
			s = Math.max(0, Math.floor(s));
			const h = Math.floor(s/3600), m = Math.floor((s%3600)/60), sec = s%60;
			const parts = [];
			if (h) parts.push(h + ' ' + (h===1 ? 'hora' : 'horas'));
			if (m) parts.push(m + ' ' + (m===1 ? 'minuto' : 'minutos'));
			if (sec || parts.length === 0) parts.push(sec + ' ' + (sec===1 ? 'segundo' : 'segundos'));
			return parts.join(', ');
		}
		function formatHMS(s){
			const i = Math.max(0, Math.floor(s));
			const h = Math.floor(i/3600), m = Math.floor((i%3600)/60), sec = i%60;
			return String(h).padStart(2,'0') + ':' + String(m).padStart(2,'0') + ':' + String(sec).padStart(2,'0');
		}

		function tickPending(){
			if (pendingSecs > 0) {
				pendingTitle.textContent = 'Tienes ' + formatHumanDuration(pendingSecs) + ' por recuperar';
				pendingEl.innerHTML = 'Equivalente: <strong>' + formatHMS(pendingSecs) + '</strong>';
				pendingSecs--;
				pendingEl.setAttribute('data-seconds', String(pendingSecs));
				if (submitBtn) submitBtn.disabled = false;
			} else {
				pendingTitle.textContent = 'No tienes tiempo por recuperar';
				pendingEl.innerHTML = '';
				if (submitBtn) submitBtn.disabled = true;
				clearInterval(pendingInterval);
			}
		}
		tickPending();
		const pendingInterval = setInterval(tickPending, 1000);
	})();

	// Evitar envío si backend anunció que no hay tiempo pendiente (última comprobación en DOM)
	form.addEventListener('submit', function(e){
		const pendingEl = document.getElementById('pendingTimeCounter');
		if (pendingEl && parseInt(pendingEl.getAttribute('data-seconds') || '0', 10) <= 0) {
			e.preventDefault();
			alert('No tienes tiempo pendiente por recuperar. Si crees que esto es un error, contacta a tu coordinador.');
			return;
		}
		const nombre = (document.querySelector('input[name="nombre"]').value || '').trim();
		const errors = [];
		if(!nombre) errors.push('Por favor ingresa tu nombre.');
		if(!fi.value || !hi.value) errors.push('Fecha/hora inicio incompleta.');
		if(!ff.value || !hf.value) errors.push('Fecha/hora final incompleta.');
		const inicio = new Date(fi.value + 'T' + hi.value);
		const fin = new Date(ff.value + 'T' + hf.value);
		if(fin <= inicio) errors.push('La fecha/hora final debe ser posterior a la de inicio.');
		if(errors.length){
			e.preventDefault();
			alert('Corrige antes de enviar:\n• ' + errors.join('\n• '));
			return;
		}
		if(!confirm('¿Deseas enviar la solicitud de recuperación?')) e.preventDefault();
	});

	btnReset && btnReset.addEventListener('click', function(){
		form.reset();
		calcularTimeout = setTimeout(actualizarDuracion, 50);
	});
});
