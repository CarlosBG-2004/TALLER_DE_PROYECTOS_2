<?php
session_start();
require_once('../config/db_config.php');

// Verificar si el usuario está logueado (ejemplo: rol de ventas o gerente)
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Procesar compra
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['registrar_compra'])) {
    $tipo_compra = $_POST['tipo_compra'];
    $numero_personas = ($tipo_compra === 'grupal') ? (int)$_POST['numero_personas'] : 1;

    // Validación: si es grupal debe ser al menos 2 personas
    if ($tipo_compra === 'grupal' && $numero_personas < 2) {
        die("Error: Una compra grupal debe tener al menos 2 personas.");
    }

    // Datos de ejemplo: normalmente vendrían del formulario completo
    $responsable_id = $_SESSION['user_id'];
    $cliente_id = 1; // aquí deberías tomar el cliente real seleccionado
    $programa = "Paquete Cusco";
    $tour = "Machu Picchu";
    $duracion = 3;
    $fecha_venta = date("Y-m-d");
    $fecha_tour_inicio = "2025-12-01";
    $fecha_tour_fin = "2025-12-03";
    $moneda = "PEN";
    $monto_persona = 500;
    $monto_total = $monto_persona * $numero_personas;
    $monto_depositado = 200;
    $agencia = "Explora Cusco";
    $medio_pago = "Tarjeta";
    $origen_cliente = "Web";
    $notas = "";

    // Insertar en la BD
    $stmt = $pdo->prepare("INSERT INTO archivos 
        (responsable_id, cliente_id, programa, tour, duracion, fecha_venta, fecha_tour_inicio, fecha_tour_fin, moneda, monto_persona, monto_total, monto_depositado, agencia, medio_pago, origen_cliente, notas, tipo_compra, numero_personas)
        VALUES 
        (:responsable_id, :cliente_id, :programa, :tour, :duracion, :fecha_venta, :fecha_tour_inicio, :fecha_tour_fin, :moneda, :monto_persona, :monto_total, :monto_depositado, :agencia, :medio_pago, :origen_cliente, :notas, :tipo_compra, :numero_personas)");

    $stmt->execute([
        'responsable_id' => $responsable_id,
        'cliente_id' => $cliente_id,
        'programa' => $programa,
        'tour' => $tour,
        'duracion' => $duracion,
        'fecha_venta' => $fecha_venta,
        'fecha_tour_inicio' => $fecha_tour_inicio,
        'fecha_tour_fin' => $fecha_tour_fin,
        'moneda' => $moneda,
        'monto_persona' => $monto_persona,
        'monto_total' => $monto_total,
        'monto_depositado' => $monto_depositado,
        'agencia' => $agencia,
        'medio_pago' => $medio_pago,
        'origen_cliente' => $origen_cliente,
        'notas' => $notas,
        'tipo_compra' => $tipo_compra,
        'numero_personas' => $numero_personas
    ]);

    // Redirigir tras registrar compra
    header("Location: index.php?mensaje=compra_registrada");
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registrar Compra</title>
</head>
<body>
    <h1>Registrar Nueva Compra</h1>
    <form method="POST" action="registrar_compra.php">
        <label>Tipo de compra:</label>
        <select name="tipo_compra" id="tipo_compra" onchange="toggleNumeroPersonas()" required>
            <option value="individual">Individual</option>
            <option value="grupal">Grupal</option>
        </select>

        <div id="grupo_personas" style="display:none;">
            <label>Número de personas:</label>
            <input type="number" name="numero_personas" min="2">
        </div>

        <!-- Aquí más adelante puedes poner inputs reales: cliente, fechas, tour, monto, etc. -->

        <button type="submit" name="registrar_compra">Registrar Compra</button>
    </form>

    <script>
        function toggleNumeroPersonas() {
            let tipo = document.getElementById('tipo_compra').value;
            let divPersonas = document.getElementById('grupo_personas');
            divPersonas.style.display = (tipo === 'grupal') ? 'block' : 'none';
        }
    </script>
</body>
</html>
