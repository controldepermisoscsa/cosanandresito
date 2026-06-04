<?php
session_start();
require 'conexion.php';

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['nombre'])) {
    header('Location: login.php?mensaje=Debes iniciar sesión para acceder a esta página.');
    exit();
}

// Verificar si el usuario tiene el cargo de gerente
$cargo = strtolower($_SESSION['cargo'] ?? '');
if ($cargo !== 'gerente' && $cargo !== 'gerencia') {
    header('Location: login.php?mensaje=No tienes permiso para acceder a esta página.');
    exit();
}

// Obtener datos para los filtros
$stmtTipos = $pdo->query("SELECT DISTINCT tipo_permiso FROM permisos WHERE tipo_permiso IS NOT NULL ORDER BY tipo_permiso");
$tiposPermisos = $stmtTipos->fetchAll(PDO::FETCH_COLUMN);

// Obtener cargos (excluyendo Gerencia)
$stmtCargos = $pdo->query("SELECT DISTINCT nombre_cargo FROM cargo WHERE LOWER(nombre_cargo) NOT IN ('gerencia', 'gerente') ORDER BY nombre_cargo");
$cargos = $stmtCargos->fetchAll(PDO::FETCH_COLUMN);

// Obtener áreas
$stmtAreas = $pdo->query("SELECT DISTINCT area FROM usuarios WHERE area IS NOT NULL AND area != '' ORDER BY area");
$areas = $stmtAreas->fetchAll(PDO::FETCH_COLUMN);

// Obtener años disponibles para filtros
$stmtAnios = $pdo->query("SELECT DISTINCT YEAR(fecha_salida) AS anio FROM permisos WHERE fecha_salida IS NOT NULL ORDER BY anio DESC");
$anios = $stmtAnios->fetchAll(PDO::FETCH_COLUMN);
if (empty($anios)) {
    $anios = [date('Y')];
}

// Obtener filtros del formulario
$mesInicio = $_GET['mes_inicio'] ?? '';
$mesFin = $_GET['mes_fin'] ?? '';
$anioInicio = $_GET['anio_inicio'] ?? '';
$anioFin = $_GET['anio_fin'] ?? '';
$tipoPermiso = $_GET['tipo_permiso'] ?? 'todos';
$cargoFiltro = $_GET['cargo'] ?? 'todos';
$areaFiltro = $_GET['area'] ?? 'todas';

// Variables para los resultados
$resultados = [];
$mostrarTabla = false;

// Procesar filtros si se envió el formulario (solo si hay mes inicio, mes fin y años)
if (!empty($mesInicio) && !empty($mesFin) && !empty($anioInicio) && !empty($anioFin)) {
    $mostrarTabla = true;
    
    // Construir rango de fechas completas a partir de mes+anio
    $anioInicioInt = (int)$anioInicio;
    $anioFinInt = (int)$anioFin;
    $mesInicioInt = (int)$mesInicio;
    $mesFinInt = (int)$mesFin;

    $fechaInicio = sprintf('%04d-%02d-01 00:00:00', $anioInicioInt, $mesInicioInt);
    $ultimoDia = date('t', strtotime(sprintf('%04d-%02d-01', $anioFinInt, $mesFinInt)));
    $fechaFin = sprintf('%04d-%02d-%02d 23:59:59', $anioFinInt, $mesFinInt, $ultimoDia);

    // Asegurar orden correcto (si el usuario invirtió valores)
    if (strtotime($fechaInicio) > strtotime($fechaFin)) {
        $tmp = $fechaInicio;
        $fechaInicio = $fechaFin;
        $fechaFin = $tmp;
    }
    
    // CONSULTA CORREGIDA: Mostrar CADA PERMISO individual (no agrupar) usando rango de fechas
    $sql = "
        SELECT 
            p.tipo_permiso AS tipo,
            u.nombre AS nombre,
            c.nombre_cargo AS cargo,
            COALESCE(u.area, 'Sin área') AS area,
            COALESCE(p.tiempo_total_ausencia, '00:00:00') AS total_horas,
            p.motivo
        FROM permisos p
        INNER JOIN usuarios u ON p.id_usuario = u.id_usuario
        INNER JOIN cargo c ON u.id_cargo = c.id_cargo
        WHERE 1=1
            -- SOLO permisos FINALIZADOS (tienen tiempo_total_ausencia calculado)
            AND p.estado = 'finalizado'
            -- FILTRO RANGO DE FECHAS (mes+año)
            AND p.fecha_salida BETWEEN ? AND ?
    ";
    
    $params = [$fechaInicio, $fechaFin];
    
    // FILTRO TIPO
    if ($tipoPermiso !== 'todos') {
        $sql .= " AND p.tipo_permiso = ?";
        $params[] = $tipoPermiso;
    }
    
    // FILTRO CARGO (excluyendo Gerencia automáticamente)
    $sql .= " AND LOWER(c.nombre_cargo) NOT IN ('gerencia', 'gerente')";
    if ($cargoFiltro !== 'todos') {
        $sql .= " AND c.nombre_cargo = ?";
        $params[] = $cargoFiltro;
    }
    
    // FILTRO ÁREA
    if ($areaFiltro !== 'todas') {
        $sql .= " AND u.area = ?";
        $params[] = $areaFiltro;
    }
    
    // ORDENAR por nombre
    $sql .= " ORDER BY u.nombre, p.tipo_permiso";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Array de meses para los selects
$meses = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Módulo de Estadísticas - Panel de Gerente</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      margin: 0;
      padding: 20px;
      background-color: #f8f9fa;
    }
    
    .header {
      background-color: #343a40;
      color: white;
      padding: 15px 20px;
      margin: -20px -20px 30px -20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    .header h1 {
      margin: 0;
      font-size: 24px;
    }
    
    .back-link {
      background-color: #f39c12;
      color: white;
      padding: 10px 20px;
      text-decoration: none;
      border-radius: 5px;
      font-weight: bold;
      transition: background-color 0.3s;
    }
    
    .back-link:hover {
      background-color: #e67e22;
    }
    
    .content {
      max-width: 1200px;
      margin: 0 auto;
      background-color: white;
      padding: 30px;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    h2 {
      color: #343a40;
      text-align: center;
      margin-bottom: 30px;
      font-size: 28px;
    }
    
    /* Estilos para filtros */
    .filtros-container {
      background-color: #f8f9fa;
      padding: 25px;
      border-radius: 8px;
      margin-bottom: 30px;
      border: 2px solid #28a745;
    }
    
    .filtros-titulo {
      text-align: center;
      color: #28a745;
      font-size: 20px;
      font-weight: bold;
      margin-bottom: 20px;
    }
    
    .filtros-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 20px;
      margin-bottom: 20px;
    }
    
    .filtro-group {
      display: flex;
      flex-direction: column;
    }
    
    .filtro-group label {
      margin-bottom: 8px;
      font-weight: bold;
      color: #495057;
      font-size: 14px;
    }
    
    .filtro-group select {
      padding: 12px;
      border: 2px solid #ced4da;
      border-radius: 5px;
      font-size: 14px;
      transition: border-color 0.3s;
      background-color: white;
    }
    
    .filtro-group select:focus {
      border-color: #28a745;
      outline: none;
    }
    
    .required {
      color: #dc3545;
      font-weight: bold;
    }
    
    .botones-filtro {
      display: flex;
      gap: 15px;
      justify-content: center;
      margin-top: 20px;
    }
    
    .btn {
      padding: 12px 25px;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      text-decoration: none;
      font-size: 16px;
      font-weight: bold;
      transition: all 0.3s;
    }
    
    .btn-filtrar {
      background-color: #28a745;
      color: white;
    }
    
    .btn-filtrar:hover {
      background-color: #218838;
      transform: translateY(-2px);
    }
    
    .btn-limpiar {
      background-color: #6c757d;
      color: white;
    }
    
    .btn-limpiar:hover {
      background-color: #5a6268;
      transform: translateY(-2px);
    }
    
    /* Estilos para tablas */
    .tabla-container {
      overflow-x: auto;
      margin-top: 20px;
    }
    
    table {
      width: 100%;
      border-collapse: collapse;
      background-color: white;
      border-radius: 8px;
      overflow: hidden;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    table th, table td {
      padding: 15px;
      text-align: left;
      border-bottom: 1px solid #dee2e6;
    }
    
    table th {
      background-color: #28a745;
      color: white;
      font-weight: bold;
      position: sticky;
      top: 0;
      font-size: 14px;
    }
    
    table tr:hover {
      background-color: #f8f9fa;
    }
    
    .horas-column {
      text-align: center;
      font-weight: bold;
      color: #28a745;
      font-size: 16px;
    }
    
    .permisos-column {
      text-align: center;
      font-weight: bold;
      color: #343a40;
      font-size: 16px;
    }
    
    .fecha-column {
      text-align: center;
      font-size: 14px;
      color: #6c757d;
    }
    
    .motivo-column {
      font-size: 14px;
      max-width: 200px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    
    .tabla-vacia {
      text-align: center;
      padding: 60px 40px;
      color: #6c757d;
      font-style: italic;
      background-color: #f8f9fa;
      border-radius: 8px;
      border: 2px dashed #dee2e6;
    }
    
    .tabla-vacia h3 {
      color: #495057;
      margin-bottom: 15px;
      font-size: 24px;
    }
    
    .tabla-vacia p {
      font-size: 16px;
      margin: 10px 0;
    }
    
    .resumen-filtros {
      background-color: #e9ecef;
      padding: 15px;
      border-radius: 5px;
      margin-bottom: 20px;
      font-size: 14px;
      border-left: 4px solid #28a745;
    }
    
    .icono-estadisticas {
      font-size: 48px;
      color: #28a745;
      margin-bottom: 20px;
    }
    
    .instrucciones {
      background-color: #d1ecf1;
      border: 1px solid #bee5eb;
      color: #0c5460;
      padding: 15px;
      border-radius: 5px;
      margin-bottom: 20px;
    }
    
    .instrucciones h4 {
      margin: 0 0 10px 0;
    }
    
    .instrucciones ul {
      margin: 10px 0;
      padding-left: 20px;
    }
    
    .tabla-total {
      background-color: #28a745 !important;
      color: white !important;
      font-weight: bold !important;
      font-size: 16px !important;
    }
    
    .tabla-total td {
      background-color: #28a745 !important;
      color: white !important;
      font-weight: bold !important;
      text-align: center !important;
      padding: 20px 15px !important;
    }
  </style>
</head>
<body>
  <!-- Header con título y botón de regreso -->
  <div class="header">
    <h1>📊 Módulo de Estadísticas para Gerencia</h1>
    <a href="gerente_inicio.php" class="back-link">← Volver al Panel Principal</a>
  </div>

  <!-- Contenido principal -->
  <div class="content">
    <div style="text-align: center;">
      <div class="icono-estadisticas">📈</div>
      <h2>Estadísticas de Permisos por Filtros</h2>
    </div>

    <!-- Instrucciones ACTUALIZADAS -->
    <div class="instrucciones">
      <h4>🔍 Instrucciones de uso:</h4>
      <ul>
        <li><strong>Mes Inicio, Mes Fin, Año Inicio y Año Fin:</strong> Son obligatorios para generar el reporte</li>
        <li><strong>Tipo, Cargo, Área:</strong> Filtros opcionales para refinar búsqueda</li>
        <li><strong>Solo se muestran:</strong> Permisos FINALIZADOS con tiempo total calculado (excluyendo Gerencia)</li>
        <li><strong>Cada fila:</strong> Representa un permiso individual con su tiempo específico de ausencia</li>
      </ul>
    </div>

    <!-- Formulario de filtros -->
    <div class="filtros-container">
      <div class="filtros-titulo">🔍 CONFIGURAR FILTROS DE BÚSQUEDA</div>
      <form method="GET">
        <div class="filtros-grid">
          <!-- MES INICIO -->
          <div class="filtro-group">
            <label for="mes_inicio">📅 Mes Inicio <span class="required">*</span></label>
            <select name="mes_inicio" id="mes_inicio" required>
              <option value="">Seleccionar mes inicio</option>
              <?php foreach ($meses as $num => $nombre): ?>
                <option value="<?= $num ?>" <?= $mesInicio == $num ? 'selected' : '' ?>>
                  <?= $nombre ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- AÑO INICIO -->
          <div class="filtro-group">
            <label for="anio_inicio">📅 Año Inicio <span class="required">*</span></label>
            <select name="anio_inicio" id="anio_inicio" required>
              <option value="">Seleccionar año inicio</option>
              <?php foreach ($anios as $anio): ?>
                <option value="<?= $anio ?>" <?= $anioInicio == $anio ? 'selected' : '' ?>>
                  <?= $anio ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- MES FIN -->
          <div class="filtro-group">
            <label for="mes_fin">📅 Mes Fin <span class="required">*</span></label>
            <select name="mes_fin" id="mes_fin" required>
              <option value="">Seleccionar mes fin</option>
              <?php foreach ($meses as $num => $nombre): ?>
                <option value="<?= $num ?>" <?= $mesFin == $num ? 'selected' : '' ?>>
                  <?= $nombre ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- AÑO FIN -->
          <div class="filtro-group">
            <label for="anio_fin">📅 Año Fin <span class="required">*</span></label>
            <select name="anio_fin" id="anio_fin" required>
              <option value="">Seleccionar año fin</option>
              <?php foreach ($anios as $anio): ?>
                <option value="<?= $anio ?>" <?= $anioFin == $anio ? 'selected' : '' ?>>
                  <?= $anio ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- TIPO DE PERMISO -->
          <div class="filtro-group">
            <label for="tipo_permiso">🏷️ Tipo de Permiso</label>
            <select name="tipo_permiso" id="tipo_permiso">
              <option value="todos" <?= $tipoPermiso === 'todos' ? 'selected' : '' ?>>Todos los tipos</option>
              <?php foreach ($tiposPermisos as $tipo): ?>
                <option value="<?= htmlspecialchars($tipo) ?>" <?= $tipoPermiso === $tipo ? 'selected' : '' ?>>
                  <?= htmlspecialchars($tipo) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- CARGO (excluyendo Gerencia) -->
          <div class="filtro-group">
            <label for="cargo">👔 Cargo</label>
            <select name="cargo" id="cargo">
              <option value="todos" <?= $cargoFiltro === 'todos' ? 'selected' : '' ?>>Todos los cargos</option>
              <?php foreach ($cargos as $cargoItem): ?>
                <option value="<?= htmlspecialchars($cargoItem) ?>" <?= $cargoFiltro === $cargoItem ? 'selected' : '' ?>>
                  <?= htmlspecialchars($cargoItem) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- ÁREA -->
          <div class="filtro-group">
            <label for="area">🏢 Área</label>
            <select name="area" id="area">
              <option value="todas" <?= $areaFiltro === 'todas' ? 'selected' : '' ?>>Todas las áreas</option>
              <?php foreach ($areas as $area): ?>
                <option value="<?= htmlspecialchars($area) ?>" <?= $areaFiltro === $area ? 'selected' : '' ?>>
                  <?= htmlspecialchars($area) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="botones-filtro">
          <button type="submit" class="btn btn-filtrar">🔍 Generar Estadísticas</button>
          <a href="estadisticas_completas.php" class="btn btn-limpiar">🗑️ Limpiar Filtros</a>
        </div>
      </form>
    </div>

    <!-- Resumen de filtros aplicados -->
    <?php if ($mostrarTabla): ?>
      <div class="resumen-filtros">
        <strong>📋 Filtros aplicados:</strong>
        Período: <?= $meses[$mesInicio] ?> <?= htmlspecialchars($anioInicio) ?> - <?= $meses[$mesFin] ?> <?= htmlspecialchars($anioFin) ?> |
        Tipo: <?= $tipoPermiso === 'todos' ? 'Todos' : htmlspecialchars($tipoPermiso) ?> |
        Cargo: <?= $cargoFiltro === 'todos' ? 'Todos' : htmlspecialchars($cargoFiltro) ?> |
        Área: <?= $areaFiltro === 'todas' ? 'Todas' : htmlspecialchars($areaFiltro) ?>
        <br><strong>Estado:</strong> Solo permisos FINALIZADOS | <strong>Total permisos encontrados:</strong> <?= count($resultados) ?>
      </div>
    <?php endif; ?>

    <!-- TABLA DE RESULTADOS -->
    <div class="tabla-container">
      <?php if (!$mostrarTabla): ?>
        <!-- TABLA VACÍA (inicial) -->
        <div class="tabla-vacia">
          <h3>⚙️ Seleccione los filtros para generar las estadísticas</h3>
          <p><strong>Paso 1:</strong> Seleccione el mes y año de inicio y fin (obligatorio)</p>
          <p><strong>Paso 2:</strong> Configure filtros adicionales si desea (opcional)</p>
          <p><strong>Paso 3:</strong> Haga clic en "Generar Estadísticas"</p>
          <br>
          <p>La tabla mostrará cada permiso individual con su información detallada.</p>
          <p><strong>⚠️ Importante:</strong> Solo se consideran permisos FINALIZADOS para estadísticas precisas.</p>
        </div>
      <?php elseif (empty($resultados)): ?>
        <div class="tabla-vacia">
          <h3>❌ No se encontraron resultados</h3>
          <p>No hay permisos <strong>FINALIZADOS</strong> que coincidan con los filtros seleccionados.</p>
          <p><strong>Sugerencias:</strong></p>
          <ul style="text-align: left; display: inline-block;">
            <li>Verifique el rango de meses y años seleccionado</li>
            <li>Pruebe con filtros menos específicos</li>
            <li>Asegúrese de que existan permisos FINALIZADOS en el período consultado</li>
            <li>Recuerde: Solo permisos finalizados aparecen en estadísticas</li>
          </ul>
        </div>
      <?php else: ?>
        <!-- TABLA CON RESULTADOS (cada permiso individual) -->
        <?php
        // CALCULAR TOTALES CORREGIDO
        $totalPermisos = count($resultados);
        $totalSegundos = 0;
        
        foreach ($resultados as $resultado) {
            $tiempo = $resultado['total_horas'];
            if ($tiempo && $tiempo !== '00:00:00') {
                // Separar horas, minutos y segundos
                $partes = explode(':', $tiempo);
                if (count($partes) === 3) {
                    $horas = (int)$partes[0];
                    $minutos = (int)$partes[1];
                    $segundos = (int)$partes[2];
                    
                    // Convertir todo a segundos y sumar
                    $totalSegundos += ($horas * 3600) + ($minutos * 60) + $segundos;
                }
            }
        }
        
        // Convertir de vuelta a formato HH:MM:SS
        $horasTotales = floor($totalSegundos / 3600);
        $minutosTotales = floor(($totalSegundos % 3600) / 60);
        $segundosTotales = $totalSegundos % 60;
        
        $horasTotalesFormato = sprintf('%02d:%02d:%02d', $horasTotales, $minutosTotales, $segundosTotales);
        ?>
        
        <table>
          <thead>
            <tr>
              <th>Tipo de Permiso</th>
              <th>Nombre</th>
              <th>Cargo</th>
              <th>Área</th>
              <th>Motivo</th>
              <th>Horas en Ausencia</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($resultados as $resultado): ?>
            <tr>
              <td><?= htmlspecialchars($resultado['tipo']) ?></td>
              <td><?= htmlspecialchars($resultado['nombre']) ?></td>
              <td><?= htmlspecialchars($resultado['cargo']) ?></td>
              <td><?= htmlspecialchars($resultado['area']) ?></td>
              <td class="motivo-column" title="<?= htmlspecialchars($resultado['motivo']) ?>">
                <?= htmlspecialchars($resultado['motivo']) ?>
              </td>
              <td class="horas-column"><?= htmlspecialchars($resultado['total_horas']) ?></td>
            </tr>
            <?php endforeach; ?>
            
            <!-- FILA DE TOTALES CORREGIDA -->
            <tr class="tabla-total">
              <td colspan="4"><strong>TOTAL HORAS EN AUSENCIA</strong></td>
              <td><strong>TOTAL PERMISOS</strong></td>
              <td><strong>RESUMEN</strong></td>
            </tr>
            <tr class="tabla-total">
              <td colspan="4"><strong><?= $horasTotalesFormato ?></strong></td>
              <td><strong><?= $totalPermisos ?></strong></td>
              <td><strong>📊</strong></td>
            </tr>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
