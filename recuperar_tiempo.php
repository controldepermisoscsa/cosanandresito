<?php
session_start();
require 'conexion.php';

// Si no está logueado, enviar al login
if (!isset($_SESSION['usuario_id'])) {
	header('Location: login.php?mensaje=Debes iniciar sesión para acceder a Recuperar Tiempo');
	exit;
}

$cargo = strtolower(trim($_SESSION['cargo'] ?? ''));
$id_permiso = isset($_GET['id_permiso']) ? intval($_GET['id_permiso']) : null;

// Gerencia no crea recuperaciones: llevar a la lista de aprobaciones
if (in_array($cargo, ['gerente', 'gerencia'])) {
	header('Location: aprobar_recuperacion.php');
	exit;
}

// Usuarios normales → formulario de creación (preservar id_permiso)
$url = 'solicitar_recuperacion.php' . ($id_permiso ? '?id_permiso=' . $id_permiso : '');
header('Location: ' . $url);
exit;
