<?php
require 'conexion.php';

$mensaje_exito = $_GET['registro_exitoso'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Registro de Usuario</title>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    body {
      font-family: Arial, sans-serif;
      background-color: #f8f9fa;
      margin: 0;
      padding: 0;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
    }
    .registro-container {
      background-color: #fff;
      border-radius: 8px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.08);
      padding: 25px;
      width: 350px;
      text-align: center; /* Centrar contenido dentro del contenedor */
    }
    .registro-container h1 {
      font-size: 22px;
      color: #d35400;
      margin-bottom: 20px;
      text-align: center;
    }
    .registro-container input,
    .registro-container select {
      width: 90%; /* Ajustar el ancho para centrar */
      padding: 10px;
      margin: 0 auto 10px auto; /* Centrar horizontalmente */
      border: 1px solid #ced4da;
      border-radius: 4px;
      font-size: 14px;
      display: block; /* Asegurar que se comporten como bloques */
    }
    .error-message {
      color: #c0392b;
      font-size: 12px;
      margin-bottom: 8px;
      text-align: left;
      display: none;
    }
    .registro-container button {
      background-color: #d35400;
      color: #fff;
      border: none;
      padding: 10px;
      width: 90%; /* Ajustar el ancho para centrar */
      border-radius: 4px;
      cursor: pointer;
      font-size: 16px;
      margin: 0 auto; /* Centrar horizontalmente */
      display: block;
    }
    .registro-container button:hover {
      background-color: #d35400;
    }
    .registro-container a {
      display: block;
      margin-top: 12px;
      color: #d35400;
      text-decoration: none;
      font-size: 14px;
      text-align: center;
    }
    .registro-container a:hover {
      text-decoration: underline;
    }
    /* Ojito moderno */
    .password-container {
      position: relative;
    }
    .toggle-password {
      position: absolute;
      right: 10px;
      top: 50%;
      transform: translateY(-50%);
      cursor: pointer;
      padding: 5px;
      border-radius: 50%;
      transition: background-color 0.2s ease, transform 0.2s ease;
    }
    .toggle-password:hover {
      background-color: rgba(0, 105, 217, 0.1);
      transform: translateY(-50%) scale(1.1);
    }
    .icon-eye {
      width: 20px;
      height: 20px;
      color: #d35400;
    }
    .mensaje-exito {
      background-color: #d4edda;
      color: #155724;
      padding: 10px;
      border: 1px solid #c3e6cb;
      border-radius: 4px;
      margin-bottom: 15px;
      font-size: 14px;
    }
    .disabled {
      background-color: #f5f5f5;
      color: #999;
      cursor: not-allowed;
    }
  </style>
</head>
<body>
  <div class="registro-container">
    <h1>Crear cuenta nueva</h1>
    <?php if (isset($_GET['mensaje'])): ?>
        <div class="mensaje-exito" style="background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 10px; border-radius: 4px; margin-bottom: 15px;">
            <?= htmlspecialchars($_GET['mensaje']) ?>
        </div>
    <?php endif; ?>


    <form id="formRegistro" action="procesar_registro.php" method="POST" novalidate>
      <input type="text" name="nombre" placeholder="Nombre completo" required>
      <div class="error-message" id="error-nombre"></div>

      <input type="text" name="usuario" placeholder="Nombre de usuario" required>
      <div class="error-message" id="error-usuario"></div>

      <input type="tel" name="telefono" placeholder="Teléfono (10 dígitos)">
      <div class="error-message" id="error-telefono"></div>

      <input type="email" name="correo" placeholder="Correo electrónico" required>
      <div class="error-message" id="error-correo"></div>

      <div class="password-container">
        <input type="password" name="password" placeholder="Contraseña" required>
        <span class="toggle-password" title="Mostrar/Ocultar contraseña">
          <svg xmlns="http://www.w3.org/2000/svg" class="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
            <circle cx="12" cy="12" r="3"/>
          </svg>
        </span>
      </div>
      <div class="error-message" id="error-password"></div>

      <div class="password-container">
        <input type="password" name="confirmar_password" placeholder="Confirmar contraseña" required>
        <span class="toggle-password" title="Mostrar/Ocultar contraseña">
          <svg xmlns="http://www.w3.org/2000/svg" class="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
            <circle cx="12" cy="12" r="3"/>
          </svg>
        </span>
      </div>
      <div class="error-message" id="error-confirm"></div>

      <!-- Campo para seleccionar el cargo -->
      <select name="id_cargo" id="id_cargo" required disabled class="disabled">
        <option value="">Seleccione su cargo</option>
      </select>
      <div class="error-message" id="error-cargo"></div>

      <!-- Campo para seleccionar el área (solo se muestra si el cargo es Auxiliar o Coordinador) -->
      <div id="area-container" style="display: none;">
        <select name="area" id="area" disabled class="disabled">
          <option value="">Seleccione su área</option>
          <option value="Contabilidad">Contabilidad</option>
          <option value="Riesgos">Riesgos</option>
          <option value="Operaciones">Operaciones</option>
          <option value="Sistemas">Sistemas</option>
          <option value="Parqueadero">Parqueadero</option>
        </select>
        <div class="error-message" id="error-area"></div>
      </div>

      <button type="submit">Registrarme</button>
    </form>
    <a href="login.php">← Volver al inicio de sesión</a>
  </div>

  <script>
    let cargosDisponibles = [];
    
    // Cargar cargos disponibles al iniciar
    async function cargarCargosDisponibles() {
      try {
        const response = await fetch('verificar_estado_sistema.php');
        const data = await response.json();
        
        if (data.success) {
          cargosDisponibles = data.cargos;
          actualizarComboCargos();
        }
      } catch (error) {
        console.error('Error al cargar cargos:', error);
      }
    }

    function actualizarComboCargos() {
      const cargoSelect = document.getElementById('id_cargo');
      cargoSelect.innerHTML = '<option value="">Seleccione su cargo</option>';
      
      cargosDisponibles.forEach(cargo => {
        const option = document.createElement('option');
        option.value = cargo.id;
        option.textContent = cargo.nombre;
        cargoSelect.appendChild(option);
      });
    }

    // Validar campos obligatorios para habilitar combo de cargo
    function validarCamposObligatorios() {
      const nombre = document.querySelector('input[name="nombre"]').value.trim();
      const usuario = document.querySelector('input[name="usuario"]').value.trim();
      const correo = document.querySelector('input[name="correo"]').value.trim();
      const password = document.querySelector('input[name="password"]').value.trim();
      const confirmarPassword = document.querySelector('input[name="confirmar_password"]').value.trim();

      const cargoSelect = document.getElementById('id_cargo');
      
      if (nombre && usuario && correo && password && confirmarPassword && password === confirmarPassword) {
        cargoSelect.disabled = false;
        cargoSelect.classList.remove('disabled');
      } else {
        cargoSelect.disabled = true;
        cargoSelect.classList.add('disabled');
        cargoSelect.value = '';
        // Resetear área cuando se deshabilita cargo
        resetearArea();
      }
    }

    // Función para manejar la visibilidad y habilitación del área
    function manejarArea() {
      const cargo = document.getElementById("id_cargo").value;
      const areaContainer = document.getElementById("area-container");
      const areaSelect = document.getElementById("area");
      
      // Mostrar y habilitar área para Auxiliar (3) o Coordinador (4) según tu tabla
      if (cargo === "3" || cargo === "4") {
        areaContainer.style.display = "block";
        areaSelect.disabled = false;
        areaSelect.classList.remove('disabled');
      } else {
        resetearArea();
      }
    }

    // Función para resetear el área
    function resetearArea() {
      const areaContainer = document.getElementById("area-container");
      const areaSelect = document.getElementById("area");
      
      areaContainer.style.display = "none";
      areaSelect.disabled = true;
      areaSelect.classList.add('disabled');
      areaSelect.value = '';
    }

    // Event listeners
    document.addEventListener('DOMContentLoaded', function() {
      cargarCargosDisponibles();
      
      // Validar campos en tiempo real
      const camposObligatorios = ['nombre', 'usuario', 'correo', 'password', 'confirmar_password'];
      camposObligatorios.forEach(campo => {
        document.querySelector(`input[name="${campo}"]`).addEventListener('input', validarCamposObligatorios);
      });

      // Event listener para el cambio de cargo
      document.getElementById('id_cargo').addEventListener('change', manejarArea);
    });

    // Validación del formulario al enviar
    document.getElementById("formRegistro").addEventListener("submit", async function(event) {
      let valido = true;

      // Limpiar mensajes
      document.querySelectorAll(".error-message").forEach(e => e.style.display = "none");

      const nombre = document.querySelector('input[name="nombre"]').value.trim();
      const usuario = document.querySelector('input[name="usuario"]').value.trim();
      const telefono = document.querySelector('input[name="telefono"]').value.trim();
      const correo = document.querySelector('input[name="correo"]').value.trim();
      const pass = document.querySelector('input[name="password"]').value;
      const confirm = document.querySelector('input[name="confirmar_password"]').value;
      const cargo = document.querySelector('select[name="id_cargo"]').value;
      const area = document.querySelector('select[name="area"]').value;

      const soloLetras = /^[A-Za-zÁÉÍÓÚáéíóúÑñ\s]+$/;
      const soloNumeros = /^[0-9]+$/;

      // Validaciones básicas
      if (!soloLetras.test(nombre) || nombre.length < 6) {
        mostrarError("error-nombre", "El nombre debe contener solo letras y tener al menos 6 caracteres.");
        valido = false;
      }

      if (!soloLetras.test(usuario) || usuario.length < 6) {
        mostrarError("error-usuario", "El usuario debe contener solo letras y tener al menos 6 caracteres.");
        valido = false;
      }

      if (telefono && (!soloNumeros.test(telefono) || telefono.length !== 10)) {
        mostrarError("error-telefono", "El teléfono debe tener exactamente 10 dígitos numéricos.");
        valido = false;
      }

      if (!correo.includes("@") || !correo.includes(".")) {
        mostrarError("error-correo", "Ingrese un correo electrónico válido.");
        valido = false;
      }

      if (pass.length < 6) {
        mostrarError("error-password", "La contraseña debe tener al menos 6 caracteres.");
        valido = false;
      }

      if (pass !== confirm) {
        mostrarError("error-confirm", "Las contraseñas no coinciden.");
        valido = false;
      }

      if (!cargo) {
        mostrarError("error-cargo", "Debe seleccionar un cargo.");
        valido = false;
      }

      // Validación específica para cargos que requieren área (Auxiliar=3, Coordinador=4)
      if ((cargo === "3" || cargo === "4") && !area) {
        mostrarError("error-area", "Debe seleccionar un área para este cargo.");
        valido = false;
      }

      // Validar que haya un Coordinador en el área si el cargo es Auxiliar (3)
      if (cargo === "3" && area && valido) {
        try {
          const response = await fetch(`validar_coordinador.php?area=${encodeURIComponent(area)}`);
          const data = await response.json();
          
          // Debug: mostrar información en consola
          console.log('Respuesta del servidor:', data);
          console.log('Área seleccionada:', area);
          console.log('¿Hay coordinador?:', data.hayCoordinador);

          if (!data.hayCoordinador) {
            mostrarError("error-area", `No se puede registrar un Auxiliar sin un Coordinador en el área: ${area}`);
            valido = false;
          }
        } catch (error) {
          console.error("Error al validar coordinador:", error);
          mostrarError("error-area", "Error al validar coordinador. Intente nuevamente.");
          valido = false;
        }
      }

      if (!valido) {
        event.preventDefault();
      }
    });

    function mostrarError(id, mensaje) {
      const campo = document.getElementById(id);
      campo.textContent = mensaje;
      campo.style.display = "block";
    }

    // Mostrar SweetAlert de éxito y redirigir al login
    <?php if (!empty($mensaje_exito)): ?>
    Swal.fire({
      icon: 'success',
      title: '¡Registro exitoso!',
      text: 'Tu cuenta ha sido creada correctamente.',
      timer: 2000,
      timerProgressBar: true,
      showConfirmButton: false,
      allowOutsideClick: false,
      allowEscapeKey: false
    }).then(() => {
      window.location.href = 'login.php';
    });
    <?php endif; ?>

    // Mostrar/ocultar contraseña
    document.querySelectorAll(".toggle-password").forEach(toggle => {
      toggle.addEventListener("click", function() {
        const input = this.previousElementSibling;
        const icon = this.querySelector("svg");

        if (input.type === "password") {
          input.type = "text";
          icon.innerHTML = `
            <path d="M17.94 17.94a10.94 10.94 0 01-5.94 1.66c-7 0-11-8-11-8a21.9 21.9 0 013.06-3.95m3.02-2.54A10.94 10.94 0 0112 4c7 0 11 8 11 8a21.9 21.9 0 01-3.06 3.95M1 1l22 22"/>`;
        } else {
          input.type = "password";
          icon.innerHTML = `
            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
            <circle cx="12" cy="12" r="3"/>`;
        }
      });
    });
  </script>
</body>
</html>
