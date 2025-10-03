<?php
session_start();
require_once('../config/db_config.php');

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Obtener el estado de las compras
$stmt = $pdo->prepare("SELECT * FROM compras WHERE user_id = :user_id");
$stmt->execute(['user_id' => $_SESSION['user_id']]);
$compras = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Estado de Compras</title>
</head>
<body>
    <h1>Estado de Compras</h1>
    <table>
        <thead>
            <tr>
                <th>Paquete</th>
                <th>Cantidad</th>
                <th>Tipo de Compra</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($compras as $compra): ?>
                <tr>
                    <td><?php echo $compra['paquete_id']; ?></td>
                    <td><?php echo $compra['cantidad']; ?></td>
                    <td><?php echo $compra['tipo_compra']; ?></td>
                    <td><?php echo $compra['estado']; ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
