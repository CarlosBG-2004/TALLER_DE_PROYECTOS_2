<?php
session_start();
require_once('../config/db_config.php');

// Verificar si el usuario tiene el rol adecuado (por ejemplo, gerente)
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header('Location: index.php');
    exit;
}

// Actualizar stock y precio
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['actualizar_stock'])) {
    $id_paquete = $_POST['id_paquete'];
    $precio = $_POST['precio'];
    $stock = $_POST['stock'];

    // Actualizar paquete
    $stmt = $pdo->prepare("UPDATE paquetes SET precio = :precio, stock = :stock WHERE id = :id_paquete");
    $stmt->execute([
        'precio' => $precio,
        'stock' => $stock,
        'id_paquete' => $id_paquete
    ]);

    // Redirigir después de la actualización
    header('Location: index.php');
    exit;
}

// Obtener los paquetes existentes
$paquetesStmt = $pdo->query("SELECT * FROM paquetes");
$paquetes = $paquetesStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestionar Precios y Stock</title>
</head>
<body>
    <h1>Gestionar Precios y Stock de Paquetes</h1>
    <?php foreach ($paquetes as $paquete): ?>
        <form method="POST" action="gestionar_stock.php">
            <input type="hidden" name="id_paquete" value="<?php echo $paquete['id']; ?>">
            <input type="text" value="<?php echo $paquete['nombre']; ?>" disabled>
            <input type="number" name="precio" value="<?php echo $paquete['precio']; ?>" required>
            <input type="number" name="stock" value="<?php echo $paquete['stock']; ?>" required>
            <button type="submit" name="actualizar_stock">Actualizar</button>
        </form>
    <?php endforeach; ?>
</body>
</html>
