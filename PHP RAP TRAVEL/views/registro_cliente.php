<?php
session_start();
require_once('../config/db_config.php');

// Verificar si el usuario tiene acceso (rol de gerente o ventas)
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); // Redirigir al login si no está autenticado
    exit;
}

// Procesar el registro de cliente
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = $_POST['nombre'];
    $apellido = $_POST['apellido'];
    $correo = $_POST['correo'];
    $telefono = $_POST['telefono'];
    $pais = $_POST['pais'];
    $fecha_nacimiento = $_POST['fecha_nacimiento'];

    // Insertar nuevo cliente en la base de datos
    $stmt = $pdo->prepare("INSERT INTO clientes (nombre, apellido, correo, telefono, pais, fecha_nacimiento) 
                           VALUES (:nombre, :apellido, :correo, :telefono, :pais, :fecha_nacimiento)");
    $stmt->execute([
        'nombre' => $nombre,
        'apellido' => $apellido,
        'correo' => $correo,
        'telefono' => $telefono,
        'pais' => $pais,
        'fecha_nacimiento' => $fecha_nacimiento
    ]);

    // Redirigir después de crear el cliente
    header('Location: index.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registrar Cliente</title>
</head>
<body>
    <h1>Registrar Nuevo Cliente</h1>
    <form method="POST" action="registro_cliente.php">
        <input type="text" name="nombre" placeholder="Nombre" required>
        <input type="text" name="apellido" placeholder="Apellido" required>
        <input type="email" name="correo" placeholder="Correo electrónico" required>
        <input type="tel" name="telefono" placeholder="Teléfono" required>
        <input type="text" name="pais" placeholder="País" required>
        <input type="date" name="fecha_nacimiento" placeholder="Fecha de Nacimiento" required>
        <button type="submit">Registrar Cliente</button>
    </form>
</body>
</html>
