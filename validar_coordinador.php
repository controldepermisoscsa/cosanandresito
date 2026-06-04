<?php
require 'conexion.php';

header('Content-Type: application/json');

if (isset($_GET['area'])) {
    $area = $_GET['area'];
    
    try {
        // Buscar por id_cargo = 4 (Coordinador según tu tabla)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total
            FROM usuarios
            WHERE id_cargo = 4 AND area = ?
        ");
        $stmt->execute([$area]);
        $resultado = $stmt->fetch();
        
        // Debug: información del coordinador si existe
        $stmt_debug = $pdo->prepare("
            SELECT nombre, area, id_cargo
            FROM usuarios
            WHERE id_cargo = 4 AND area = ?
        ");
        $stmt_debug->execute([$area]);
        $coordinador = $stmt_debug->fetch();
        
        echo json_encode([
            'hayCoordinador' => $resultado['total'] > 0,
            'coordinador_info' => $coordinador,
            'area_buscada' => $area
        ]);
        
    } catch (PDOException $e) {
        echo json_encode([
            'hayCoordinador' => false,
            'error' => 'Error en la base de datos: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'hayCoordinador' => false,
        'error' => 'Área no especificada'
    ]);
}
?>