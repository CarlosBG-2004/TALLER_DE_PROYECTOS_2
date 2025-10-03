<?php
session_start();
require_once('../config/db_config.php');

// Verificar si el usuario tiene el rol adecuado (ej: gerente)
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header('Location: index.php');
    exit;
}

// --- ELIMINAR paquete ---
if (isset($_GET['eliminar'])) {
    $id = $_GET['eliminar'];
    $stmt = $pdo->prepare("DELETE FROM paquetes WHERE id = :id");
    $stmt->execute(['id' => $id]);
    header("Location: gestionar_stock.php");
    exit;
}

// --- ACTUALIZAR paquete ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['actualizar_stock'])) {
    $id_paquete = $_POST['id_paquete'];
    $nombre = $_POST['nombre'];
    $descripcion = $_POST['descripcion'];
    $precio = $_POST['precio'];
    $stock = $_POST['stock'];

    $stmt = $pdo->prepare("UPDATE paquetes 
                           SET nombre = :nombre, descripcion = :descripcion, precio = :precio, stock = :stock 
                           WHERE id = :id_paquete");
    $stmt->execute([
        'nombre' => $nombre,
        'descripcion' => $descripcion,
        'precio' => $precio,
        'stock' => $stock,
        'id_paquete' => $id_paquete
    ]);

    header("Location: gestionar_stock.php");
    exit;
}

// Obtener paquetes
$paquetesStmt = $pdo->query("SELECT * FROM paquetes ORDER BY creado_en DESC");
$paquetes = $paquetesStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestionar Precios y Stock</title>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; }
        th { background-color: #f4f4f4; }
        input, textarea { width: 100%; }
        button { padding: 5px 10px; margin: 2px; }
    </style>
</head>
<body>
    <h1>Gestionar Paquetes Turísticos</h1>
    <a href="crear_paquete.php">➕ Crear Paquete</a>
    <br><br>

    <table>
        <tr>
            <th>ID</th>
            <th>Nombre</th>
            <th>Descripción</th>
            <th>Precio</th>
            <th>Stock</th>
            <th>Acciones</th>
        </tr>
        <?php foreach ($paquetes as $paquete): ?>
        <tr>
            <form method="POST" action="gestionar_stock.php">
                <td><?= htmlspecialchars($paquete['id']) ?></td>
                <td><input type="text" name="nombre" value="<?= htmlspecialchars($paquete['nombre']) ?>" required></td>
                <td><textarea name="descripcion" required><?= htmlspecialchars($paquete['descripcion']) ?></textarea></td>
                <td><input type="number" step="0.01" name="precio" value="<?= htmlspecialchars($paquete['precio']) ?>" required></td>
                <td><input type="number" name="stock" value="<?= htmlspecialchars($paquete['stock']) ?>" required></td>
                <td>
                    <input type="hidden" name="id_paquete" value="<?= $paquete['id'] ?>">
                    <button type="submit" name="actualizar_stock">💾 Guardar</button>
                    <a href="gestionar_stock.php?eliminar=<?= $paquete['id'] ?>" 
                       onclick="return confirm('¿Seguro que deseas eliminar este paquete?')">❌ Eliminar</a>
                </td>
            </form>
        </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>
