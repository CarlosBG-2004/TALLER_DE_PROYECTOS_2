<?php
session_start();
require_once('../config/db_config.php');

// Verificar si el usuario tiene el rol adecuado (por ejemplo, gerente)
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header('Location: index.php'); // Si no es gerente, redirigir
    exit;
}

// Procesar creación de paquete
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['crear_paquete'])) {
    $nombre_paquete = $_POST['nombre_paquete'];
    $descripcion = $_POST['descripcion'];
    $precio = $_POST['precio'];
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_fin = $_POST['fecha_fin'];
    $numero_personas = $_POST['numero_personas'];
    $tipo_compra = $_POST['tipo_compra'];

    // Insertar paquete en la base de datos
    $stmt = $pdo->prepare("INSERT INTO paquetes (nombre, descripcion, precio, fecha_inicio, fecha_fin, numero_personas, tipo_compra) 
                           VALUES (:nombre, :descripcion, :precio, :fecha_inicio, :fecha_fin, :numero_personas, :tipo_compra)");
    $stmt->execute([
        'nombre' => $nombre_paquete,
        'descripcion' => $descripcion,
        'precio' => $precio,
        'fecha_inicio' => $fecha_inicio,
        'fecha_fin' => $fecha_fin,
        'numero_personas' => $numero_personas,
        'tipo_compra' => $tipo_compra
    ]);

    // Redirigir después de la creación del paquete
    header('Location: index.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Crear Paquete Turístico</title>
</head>
<body>
    <h1>Crear Nuevo Paquete</h1>
    <form method="POST" action="crear_paquete.php">
        <input type="text" name="nombre_paquete" placeholder="Nombre del Paquete" required>
        <textarea name="descripcion" placeholder="Descripción del Paquete" required></textarea>
        <input type="number" name="precio" placeholder="Precio" required>
        <input type="date" name="fecha_inicio" placeholder="Fecha de Inicio" required>
        <input type="date" name="fecha_fin" placeholder="Fecha de Fin" required>
        <input type="number" name="numero_personas" placeholder="Número de Personas" required>
        <select name="tipo_compra" required>
            <option value="individual">Compra Individual</option>
            <option value="grupal">Compra Grupal</option>
        </select>
        <button type="submit" name="crear_paquete">Crear Paquete</button>
    </form>
</body>
</html>
