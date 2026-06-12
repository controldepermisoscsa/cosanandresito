<?php
session_start();
require 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = $_POST['username'];
    $password = $_POST['password'];

    try {
        // Verificar si el usuario existe y obtener su información junto con el cargo
        $stmt = $pdo->prepare("
            SELECT u.id_usuario, u.nombre, u.password, u.id_cargo, c.nombre_cargo 
            FROM usuarios u
            JOIN cargo c ON u.id_cargo = c.id_cargo
            WHERE u.usuario = ? OR u.correo = ?
        ");
        $stmt->execute([$usuario, $usuario]);
        $usuario_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($usuario_data) {
            // Usuario encontrado, verificar la contraseña
            if (password_verify($password, $usuario_data['password'])) {
                // Regenerar ID de sesión para prevenir session fixation
                session_regenerate_id(true);

                // Contraseña correcta - limpiar errores y redirigir
                unset($_SESSION['error_usuario']);
                unset($_SESSION['error_password']);
                unset($_SESSION['error_login']);

                // Guardar datos en la sesión
                $_SESSION['usuario_id'] = $usuario_data['id_usuario'];
                $_SESSION['nombre'] = $usuario_data['nombre'];
                $_SESSION['id_cargo'] = $usuario_data['id_cargo'];

                $cargo_map = [
                    1 => 'administrador',
                    2 => 'administrativo',
                    3 => 'auxiliar',
                    4 => 'coordinador',
                    5 => 'gerente',
                ];
                $_SESSION['cargo'] = $cargo_map[$usuario_data['id_cargo']] ?? 'auxiliar';

                // Redirigir según el id_cargo (más confiable que el nombre)
                switch ($usuario_data['id_cargo']) {
                    case 1: // Administrador
                        header('Location: admin_inicio.php');
                        break;
                    case 2: // Administrativo
                        header('Location: administrativo_inicio.php');
                        break;
                    case 3: // Auxiliar
                        header('Location: auxiliar_inicio.php');
                        break;
                    case 4: // Coordinador
                        header('Location: coordinador_inicio.php');
                        break;
                    case 5: // Gerencia
                        header('Location: gerente_inicio.php');
                        break;
                    default:
                        $_SESSION['error_login'] = 'Cargo no reconocido. ID: ' . $usuario_data['id_cargo'];
                        header('Location: login.php');
                        break;
                }
                exit();
            } else {
                // Usuario existe pero contraseña incorrecta
                unset($_SESSION['error_usuario']);
                $_SESSION['error_password'] = 'Contraseña incorrecta.';
                $_SESSION['username_value'] = $usuario;
                header('Location: login.php');
                exit();
            }
        } else {
            // Usuario no encontrado
            $_SESSION['error_usuario'] = 'Usuario o correo no encontrado.';
            unset($_SESSION['error_password']);
            unset($_SESSION['username_value']);
            header('Location: login.php');
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['error_login'] = 'Error en la base de datos. Intenta nuevamente.';
        header('Location: login.php');
        exit();
    }
} else {
    header('Location: login.php');
    exit();
}
?>
