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
    $stock = $_POST['stock'];

    // Insertar paquete en la base de datos
    $stmt = $pdo->prepare("INSERT INTO paquetes (nombre, descripcion, precio, stock) 
                           VALUES (:nombre, :descripcion, :precio, :stock)");
    $stmt->execute([
        'nombre' => $nombre_paquete,
        'descripcion' => $descripcion,
        'precio' => $precio,
        'stock' => $stock
    ]);

    // Redirigir después de la creación del paquete
    header('Location: gestionar_stock.php');
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
        <input type="text" name="nombre_paquete" placeholder="Nombre del Paquete" required><br><br>
        <textarea name="descripcion" placeholder="Descripción del Paquete" required></textarea><br><br>
        <input type="number" step="0.01" name="precio" placeholder="Precio" required><br><br>
        <input type="number" name="stock" placeholder="Stock disponible" required><br><br>
        <button type="submit" name="crear_paquete">Crear Paquete</button>
    </form>
</body>
</html>
