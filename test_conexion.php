<?php
// Paso 1: verificar que PHP funciona
echo "<h2>PHP version: " . phpversion() . "</h2>";

// Paso 2: verificar extensiones PostgreSQL
$pdo_pgsql  = extension_loaded('pdo_pgsql') ? '✅ Cargada' : '❌ NO cargada';
$pgsql      = extension_loaded('pgsql')     ? '✅ Cargada' : '❌ NO cargada';

echo "<p><strong>pdo_pgsql:</strong> $pdo_pgsql</p>";
echo "<p><strong>pgsql:</strong>     $pgsql</p>";

if (!extension_loaded('pdo_pgsql')) {
    echo "<div style='background:#f8d7da;padding:15px;border-radius:5px;'>
        <strong>❌ Problema:</strong> La extensión pdo_pgsql no está activa.<br><br>
        <strong>Solución:</strong><br>
        1. En XAMPP → Apache → Config → PHP (php.ini)<br>
        2. Busca: <code>;extension=pdo_pgsql</code><br>
        3. Quítale el punto y coma: <code>extension=pdo_pgsql</code><br>
        4. Haz lo mismo con: <code>extension=pgsql</code><br>
        5. Guarda y reinicia Apache
    </div>";
    exit;
}

// Paso 3: verificar config.php
if (!file_exists(__DIR__ . '/config.php')) {
    echo "<div style='background:#f8d7da;padding:15px;'>❌ config.php no encontrado en " . __DIR__ . "</div>";
    exit;
}

require_once __DIR__ . '/config.php';
echo "<p>✅ config.php cargado</p>";

// Paso 4: probar conexión a Supabase
try {
    $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";sslmode=require";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM cargo");
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "<div style='background:#d4edda;padding:15px;border-radius:5px;'>
        <h2>✅ Conexión exitosa a Supabase</h2>
        <p>Cargos en BD: <strong>" . $resultado['total'] . "</strong></p>
    </div>";

    $stmt = $pdo->query("SELECT * FROM cargo ORDER BY id_cargo");
    $cargos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<ul>";
    foreach ($cargos as $c) {
        echo "<li>{$c['id_cargo']} — {$c['nombre_cargo']}</li>";
    }
    echo "</ul>";

} catch (PDOException $e) {
    echo "<div style='background:#f8d7da;padding:15px;border-radius:5px;'>
        <h2>❌ Error de conexión</h2>
        <p><strong>Detalle:</strong> " . $e->getMessage() . "</p>
    </div>";
}
?>
