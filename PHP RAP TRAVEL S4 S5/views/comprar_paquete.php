<?php
session_start();
require_once('../config/db_config.php');

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id_paquete = $_POST['id_paquete'];
    $cantidad = $_POST['cantidad'];
    $tipo_compra = $_POST['tipo_compra']; // 'individual' o 'grupal'

    // Verificar el stock
    $stmt = $pdo->prepare("SELECT stock FROM paquetes WHERE id = :id_paquete");
    $stmt->execute(['id_paquete' => $id_paquete]);
    $paquete = $stmt->fetch();

    if ($paquete['stock'] >= $cantidad) {
        // Actualizar el stock
        $nuevo_stock = $paquete['stock'] - $cantidad;
        $stmt = $pdo->prepare("UPDATE paquetes SET stock = :nuevo_stock WHERE id = :id_paquete");
        $stmt->execute(['nuevo_stock' => $nuevo_stock, 'id_paquete' => $id_paquete]);

        // Insertar compra
        $stmt = $pdo->prepare("INSERT INTO compras (user_id, paquete_id, cantidad, tipo_compra) VALUES (:user_id, :paquete_id, :cantidad, :tipo_compra)");
        $stmt->execute([
            'user_id' => $_SESSION['user_id'],
            'paquete_id' => $id_paquete,
            'cantidad' => $cantidad,
            'tipo_compra' => $tipo_compra
        ]);

        // Redirigir a confirmación
        header('Location: confirmacion.php');
        exit;
    } else {
        $error = "No hay suficiente stock para la cantidad solicitada.";
    }
}

// Obtener los paquetes disponibles
$paquetesStmt = $pdo->query("SELECT * FROM paquetes WHERE stock > 0");
$paquetes = $paquetesStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Comprar Paquete</title>
</head>
<body>
    <h1>Comprar Paquete</h1>
    <?php if (isset($error)) echo "<p style='color: red;'>$error</p>"; ?>
    <form method="POST" action="comprar_paquete.php">
        <select name="id_paquete" required>
            <?php foreach ($paquetes as $paquete): ?>
                <option value="<?php echo $paquete['id']; ?>"><?php echo $paquete['nombre']; ?> - $<?php echo $paquete['precio']; ?></option>
            <?php endforeach; ?>
        </select>
        <input type="number" name="cantidad" placeholder="Cantidad" required>
        <select name="tipo_compra" required>
            <option value="individual">Compra Individual</option>
            <option value="grupal">Compra Grupal</option>
        </select>
        <button type="submit">Comprar</button>
    </form>
</body>
</html>
