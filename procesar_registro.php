<?php
require 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'] ?? '';
    $usuario = $_POST['usuario'] ?? '';
    $telefono = $_POST['telefono'] ?? '';
    $correo = $_POST['correo'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirmar_password = $_POST['confirmar_password'] ?? '';
    $id_cargo = $_POST['id_cargo'] ?? '';
    $area = $_POST['area'] ?? '';

    // Validar que las contraseñas coincidan
    if ($password !== $confirmar_password) {
        header('Location: registro.php?mensaje=Las contraseñas no coinciden');
        exit();
    }

    if (empty($id_cargo)) {
        die("Error: El cargo es obligatorio.");
    }

    // Verificar el estado actual del sistema usando id_cargo
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE id_cargo = 1");
    $stmt->execute();
    $existe_admin = $stmt->fetchColumn() > 0;

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE id_cargo = 5");
    $stmt->execute();
    $existe_gerente = $stmt->fetchColumn() > 0;

    // Validar lógica de registro según el estado del sistema
    if (!$existe_admin && $id_cargo != 1) {
        header('Location: registro.php?mensaje=El primer registro debe ser un Administrador');
        exit();
    }

    if ($existe_admin && !$existe_gerente && $id_cargo != 5) {
        header('Location: registro.php?mensaje=Después del Administrador, debe registrarse la Gerencia');
        exit();
    }

    // Auxiliar = 3, Coordinador = 4 según tu tabla
    if (($id_cargo == 3 || $id_cargo == 4) && empty($area)) {
        header('Location: registro.php?mensaje=El área es obligatoria para el cargo seleccionado');
        exit();
    }

    // Validar coordinador para auxiliares (Auxiliar = 3, Coordinador = 4)
    if ($id_cargo == 3 && !empty($area)) {
        $stmt = $pdo->prepare("
            SELECT id_usuario 
            FROM usuarios
            WHERE id_cargo = 4 AND area = ?
            LIMIT 1
        ");
        $stmt->execute([$area]);
        $id_coordinador = $stmt->fetchColumn();

        if (!$id_coordinador) {
            header('Location: registro.php?mensaje=No es posible registrar un Auxiliar sin un Coordinador en el área: ' . $area);
            exit();
        }
    } else {
        $id_coordinador = null;
    }

    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    try {
        $stmt = $pdo->prepare("
            INSERT INTO usuarios (nombre, usuario, telefono, correo, password, id_cargo, area, id_coordinador)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$nombre, $usuario, $telefono, $correo, $password_hash, $id_cargo, $area, $id_coordinador]);

        header('Location: registro.php?registro_exitoso=Usuario registrado correctamente');
        exit();
    } catch (PDOException $e) {
        die("Error al registrar el usuario: " . $e->getMessage());
    }
} else {
    header('Location: registro.php');
    exit();
}
?>
