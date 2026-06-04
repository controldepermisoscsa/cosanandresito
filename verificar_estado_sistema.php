<?php
require 'conexion.php';

header('Content-Type: application/json');

try {
    // Verificar si existe administrador (id_cargo = 1)
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM usuarios WHERE id_cargo = 1");
    $stmt->execute();
    $existe_admin = $stmt->fetch()['total'] > 0;

    // Verificar si existe gerente (id_cargo = 5)  
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM usuarios WHERE id_cargo = 5");
    $stmt->execute();
    $existe_gerente = $stmt->fetch()['total'] > 0;

    // Determinar qué cargos mostrar
    $cargos_disponibles = [];
    
    if (!$existe_admin) {
        // Solo administrador si es el primer registro
        $cargos_disponibles[] = ['id' => '1', 'nombre' => 'Administrador'];
    } elseif (!$existe_gerente) {
        // Solo gerente si ya existe admin pero no gerente
        $cargos_disponibles[] = ['id' => '5', 'nombre' => 'Gerencia'];
    } else {
        // Todos los demás cargos si ya existen admin y gerente (IDs corregidos según tu tabla)
        $cargos_disponibles = [
            ['id' => '2', 'nombre' => 'Administrativo'],
            ['id' => '3', 'nombre' => 'Auxiliar'],
            ['id' => '4', 'nombre' => 'Coordinador']
        ];
    }

    echo json_encode([
        'success' => true,
        'cargos' => $cargos_disponibles,
        'existe_admin' => $existe_admin,
        'existe_gerente' => $existe_gerente
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error en la base de datos: ' . $e->getMessage()
    ]);
}
?>
