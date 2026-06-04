<?php
session_start();
require 'conexion.php';

header('Content-Type: text/html; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    die('Sesión no válida');
}

echo "<h1>🔍 Verificar Asignaciones de Permisos</h1>";
echo "<style>body{font-family:Arial;margin:20px;} pre{background:#f5f5f5;padding:10px;border-radius:5px;overflow-x:auto;} .info{background:#e8f4fd;padding:15px;margin:10px 0;border-left:4px solid #007bff;} .error{background:#f8d7da;padding:15px;margin:10px 0;border-left:4px solid #dc3545;color:#721c24;} .success{background:#d4edda;padding:15px;margin:10px 0;border-left:4px solid #28a745;color:#155724;}</style>";

echo "<h2>🔍 Verificación de Asignaciones en Permisos</h2>\n";

// 1. Mostrar permisos sin id_asignado
echo "<h3>❌ Permisos SIN id_asignado:</h3>\n";
$stmt = $pdo->prepare("
    SELECT p.id_permiso, p.estado, p.asignado_a, p.id_asignado, u.nombre, c.nombre_cargo
    FROM permisos p
    INNER JOIN usuarios u ON p.id_usuario = u.id_usuario  
    INNER JOIN cargo c ON u.id_cargo = c.id_cargo
    WHERE p.id_asignado IS NULL AND p.estado IN ('pendiente', 'reenviado')
    ORDER BY p.id_permiso DESC
");
$stmt->execute();
$permisos_sin_asignar = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($permisos_sin_asignar as $permiso) {
    echo "- Permiso #{$permiso['id_permiso']}: {$permiso['nombre']} ({$permiso['nombre_cargo']}) → asignado_a='{$permiso['asignado_a']}' pero id_asignado=NULL<br>\n";
}

// 2. Corregir automáticamente
echo "<h3>🔧 Corrigiendo Asignaciones...</h3>\n";

foreach ($permisos_sin_asignar as $permiso) {
    $id_asignado = null;
    $cargo_solicitante = strtolower($permiso['nombre_cargo']);
    
    if ($permiso['asignado_a'] === 'coordinador') {
        $stmt_coord = $pdo->prepare("
            SELECT u.id_usuario FROM usuarios u
            INNER JOIN cargo c ON u.id_cargo = c.id_cargo
            WHERE LOWER(c.nombre_cargo) = 'coordinador' LIMIT 1
        ");
        $stmt_coord->execute();
        $coord = $stmt_coord->fetch(PDO::FETCH_ASSOC);
        $id_asignado = $coord['id_usuario'] ?? null;
        
    } elseif ($permiso['asignado_a'] === 'gerente') {
        $stmt_gerente = $pdo->prepare("
            SELECT u.id_usuario FROM usuarios u
            INNER JOIN cargo c ON u.id_cargo = c.id_cargo
            WHERE LOWER(c.nombre_cargo) IN ('gerencia', 'gerente') LIMIT 1
        ");
        $stmt_gerente->execute();
        $ger = $stmt_gerente->fetch(PDO::FETCH_ASSOC);
        $id_asignado = $ger['id_usuario'] ?? null;
    }
    
    if ($id_asignado) {
        $update = $pdo->prepare("UPDATE permisos SET id_asignado = ? WHERE id_permiso = ?");
        $update->execute([$id_asignado, $permiso['id_permiso']]);
        echo "✅ Permiso #{$permiso['id_permiso']} corregido: id_asignado = {$id_asignado}<br>\n";
    } else {
        echo "❌ No se pudo corregir permiso #{$permiso['id_permiso']}: no se encontró usuario para '{$permiso['asignado_a']}'<br>\n";
    }
}

// 3. Mostrar estado final
echo "<h3>✅ Estado Final:</h3>\n";
$stmt_final = $pdo->prepare("
    SELECT p.id_permiso, p.estado, p.asignado_a, p.id_asignado, u.nombre, c.nombre_cargo,
           ua.nombre as nombre_asignado
    FROM permisos p
    INNER JOIN usuarios u ON p.id_usuario = u.id_usuario  
    INNER JOIN cargo c ON u.id_cargo = c.id_cargo
    LEFT JOIN usuarios ua ON p.id_asignado = ua.id_usuario
    WHERE p.estado IN ('pendiente', 'reenviado')
    ORDER BY p.id_permiso DESC LIMIT 10
");
$stmt_final->execute();
$permisos_actuales = $stmt_final->fetchAll(PDO::FETCH_ASSOC);

foreach ($permisos_actuales as $permiso) {
    $asignado_nombre = $permiso['nombre_asignado'] ?? 'SIN ASIGNAR';
    echo "- Permiso #{$permiso['id_permiso']}: {$permiso['nombre']} → {$permiso['asignado_a']} ({$asignado_nombre})<br>\n";
}

echo "<p><strong>🎯 Verificación completada. Los permisos deberían mostrarse correctamente ahora.</strong></p>";

echo "<p><a href='ver_permisos.php'>← Volver a Permisos</a> | <a href='admin_inicio.php'>← Panel Admin</a></p>";
?>
