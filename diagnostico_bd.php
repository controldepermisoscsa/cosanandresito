<?php
echo "=== DIAGNÓSTICO DE CONEXIÓN A BASE DE DATOS ===" . PHP_EOL . PHP_EOL;

$host = 'localhost';
$db = 'control_permisos_pruebas';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

echo "Configuración:" . PHP_EOL;
echo "- Host: $host" . PHP_EOL;
echo "- Usuario: $user" . PHP_EOL;
echo "- Contraseña: " . (empty($pass) ? "(vacía)" : "(definida)") . PHP_EOL;
echo "- Base de datos: $db" . PHP_EOL;
echo "- Charset: $charset" . PHP_EOL . PHP_EOL;

echo "Verificando conexión a MySQL..." . PHP_EOL;
try {
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    echo "✓ Conexión a MySQL: EXITOSA" . PHP_EOL;

    echo "\nVerificando base de datos..." . PHP_EOL;
    $result = $pdo->query("SHOW DATABASES LIKE '$db'");
    if ($result->rowCount() > 0) {
        echo "✓ Base de datos '$db': EXISTE" . PHP_EOL;

        echo "\nVerificando tabla 'usuarios'..." . PHP_EOL;
        try {
            $result = $pdo->query("SELECT COUNT(*) FROM $db.usuarios");
            $count = $result->fetchColumn();
            echo "✓ Tabla 'usuarios': EXISTE ($count registros)" . PHP_EOL;
        } catch (PDOException $e) {
            echo "✗ Tabla 'usuarios': NO EXISTE" . PHP_EOL;
        }
    } else {
        echo "✗ Base de datos '$db': NO EXISTE" . PHP_EOL;
        echo "\nBasas de datos disponibles:" . PHP_EOL;
        $result = $pdo->query("SHOW DATABASES");
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            echo "  - " . $row['Database'] . PHP_EOL;
        }
    }
} catch (PDOException $e) {
    echo "✗ Conexión a MySQL: FALLÓ" . PHP_EOL;
    echo "Error: " . $e->getMessage() . PHP_EOL;
    echo "\nPosibles soluciones:" . PHP_EOL;
    echo "1. Verifica que MySQL esté ejecutándose" . PHP_EOL;
    echo "2. Verifica que las credenciales (usuario/contraseña) sean correctas" . PHP_EOL;
    echo "3. Verifica que el host sea correcto (localhost)" . PHP_EOL;
}
?>
