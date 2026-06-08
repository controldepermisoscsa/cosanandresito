<?php
require 'includes/conexion.php';

echo "=== PRUEBA DE CONEXIÓN A POSTGRESQL ===" . PHP_EOL . PHP_EOL;

try {
    // Prueba básica
    $result = $pdo->query("SELECT NOW()");
    $row = $result->fetch(PDO::FETCH_ASSOC);

    echo "✓ Conexión exitosa a PostgreSQL" . PHP_EOL;
    echo "Hora del servidor: " . $row['now'] . PHP_EOL . PHP_EOL;

    // Verificar tabla usuarios
    echo "Verificando tablas..." . PHP_EOL;
    $result = $pdo->query("
        SELECT table_name
        FROM information_schema.tables
        WHERE table_schema = 'public'
        ORDER BY table_name
    ");

    $tables = $result->fetchAll(PDO::FETCH_COLUMN);

    if (count($tables) > 0) {
        echo "✓ Tablas encontradas:" . PHP_EOL;
        foreach ($tables as $table) {
            echo "  - $table" . PHP_EOL;
        }
    } else {
        echo "✗ No hay tablas en la base de datos" . PHP_EOL;
    }

} catch (PDOException $e) {
    echo "✗ Error de conexión:" . PHP_EOL;
    echo $e->getMessage() . PHP_EOL;
}
?>
