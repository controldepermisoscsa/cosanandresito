<?php
session_start();
require 'conexion.php';

// Solo para administradores
if (!isset($_SESSION['usuario_id']) || strtolower($_SESSION['cargo']) !== 'administrador') {
    die('Solo para administradores');
}

echo "<h2>🔍 Verificación de Estructura de Base de Datos</h2>";

try {
    // 1. Verificar estructura de la tabla permisos
    echo "<h3>📋 Estructura de tabla 'permisos':</h3>";
    $stmt = $pdo->query("DESCRIBE permisos");
    $estructura = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($estructura as $campo) {
        echo "<tr>";
        echo "<td><strong>{$campo['Field']}</strong></td>";
        echo "<td>{$campo['Type']}</td>";
        echo "<td>{$campo['Null']}</td>";
        echo "<td>{$campo['Key']}</td>";
        echo "<td>{$campo['Default']}</td>";
        echo "<td>{$campo['Extra']}</td>";
        echo "</tr>";
    }
    echo "</table>";

    // 2. Verificar valores ENUM específicos
    echo "<h3>🎯 Valores ENUM para 'asignado_a':</h3>";
    $stmt = $pdo->query("SHOW COLUMNS FROM permisos LIKE 'asignado_a'");
    $enum_info = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p><strong>Tipo completo:</strong> {$enum_info['Type']}</p>";
    
    // Extraer valores del ENUM
    preg_match("/^enum\((.+)\)$/", $enum_info['Type'], $matches);
    if ($matches) {
        $enum_values = str_getcsv($matches[1], ',', "'");
        echo "<p><strong>Valores permitidos:</strong></p>";
        echo "<ul>";
        foreach ($enum_values as $value) {
            echo "<li>'$value'</li>";
        }
        echo "</ul>";
    }

    // 3. Verificar usuarios con cargo de Gerencia
    echo "<h3>👨‍💼 Usuarios con cargo de Gerencia:</h3>";
    $stmt = $pdo->query("
        SELECT u.id_usuario, u.nombre, c.nombre_cargo 
        FROM usuarios u 
        INNER JOIN cargo c ON u.id_cargo = c.id_cargo 
        WHERE c.nombre_cargo = 'Gerencia'
    ");
    $gerentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($gerentes)) {
        echo "<p style='color: red;'>❌ No se encontraron usuarios con cargo 'Gerencia'</p>";
        
        // Mostrar todos los cargos disponibles
        echo "<h4>📝 Cargos disponibles en la base de datos:</h4>";
        $stmt = $pdo->query("SELECT * FROM cargo");
        $cargos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cargos as $cargo) {
            echo "<p>ID: {$cargo['id_cargo']} - Nombre: '{$cargo['nombre_cargo']}'</p>";
        }
    } else {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID Usuario</th><th>Nombre</th><th>Cargo</th></tr>";
        foreach ($gerentes as $gerente) {
            echo "<tr>";
            echo "<td>{$gerente['id_usuario']}</td>";
            echo "<td>{$gerente['nombre']}</td>";
            echo "<td>{$gerente['nombre_cargo']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    // 4. Probar UPDATE directamente
    $id_permiso = $_GET['test_id'] ?? null;
    if ($id_permiso) {
        echo "<h3>🧪 Prueba de UPDATE directo para permiso ID: $id_permiso</h3>";
        
        // Obtener estado actual
        $stmt = $pdo->prepare("SELECT estado, asignado_a, id_asignado FROM permisos WHERE id_permiso = ?");
        $stmt->execute([$id_permiso]);
        $antes = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($antes) {
            echo "<p><strong>Estado actual:</strong></p>";
            echo "<pre>" . json_encode($antes, JSON_PRETTY_PRINT) . "</pre>";
            
            // Intentar UPDATE paso a paso
            $pdo->beginTransaction();
            
            try {
                // Paso 1: Solo estado
                echo "<p>📝 Actualizando solo estado...</p>";
                $stmt = $pdo->prepare("UPDATE permisos SET estado = 'reenviado' WHERE id_permiso = ?");
                $resultado1 = $stmt->execute([$id_permiso]);
                echo "<p>Resultado: " . ($resultado1 ? '✅' : '❌') . " (Filas: {$stmt->rowCount()})</p>";
                
                // Paso 2: asignado_a
                echo "<p>📝 Actualizando asignado_a...</p>";
                $stmt = $pdo->prepare("UPDATE permisos SET asignado_a = 'gerente' WHERE id_permiso = ?");
                $resultado2 = $stmt->execute([$id_permiso]);
                echo "<p>Resultado: " . ($resultado2 ? '✅' : '❌') . " (Filas: {$stmt->rowCount()})</p>";
                
                // Paso 3: id_asignado (si hay gerente)
                if (!empty($gerentes)) {
                    $id_gerente = $gerentes[0]['id_usuario'];
                    echo "<p>📝 Actualizando id_asignado a $id_gerente...</p>";
                    $stmt = $pdo->prepare("UPDATE permisos SET id_asignado = ? WHERE id_permiso = ?");
                    $resultado3 = $stmt->execute([$id_gerente, $id_permiso]);
                    echo "<p>Resultado: " . ($resultado3 ? '✅' : '❌') . " (Filas: {$stmt->rowCount()})</p>";
                }
                
                // Verificar resultado final
                $stmt = $pdo->prepare("SELECT estado, asignado_a, id_asignado FROM permisos WHERE id_permiso = ?");
                $stmt->execute([$id_permiso]);
                $despues = $stmt->fetch(PDO::FETCH_ASSOC);
                
                echo "<p><strong>Estado después:</strong></p>";
                echo "<pre>" . json_encode($despues, JSON_PRETTY_PRINT) . "</pre>";
                
                if (isset($_GET['confirmar'])) {
                    $pdo->commit();
                    echo "<p style='color: green;'>✅ Cambios confirmados</p>";
                } else {
                    $pdo->rollBack();
                    echo "<p style='color: orange;'>⚠️ Rollback realizado</p>";
                    echo "<p><a href='?test_id=$id_permiso&confirmar=1'>Confirmar cambios</a></p>";
                }
                
            } catch (Exception $e) {
                $pdo->rollBack();
                echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
            }
        } else {
            echo "<p style='color: red;'>❌ Permiso no encontrado</p>";
        }
    } else {
        echo "<p><a href='?test_id=1'>🧪 Probar UPDATE con permiso ID 1</a></p>";
        echo "<p><em>Cambia el ID según el permiso que quieras probar</em></p>";
    }

} catch (Exception $e) {
    echo "<p style='color: red;'><strong>❌ ERROR: " . $e->getMessage() . "</strong></p>";
}
?>
