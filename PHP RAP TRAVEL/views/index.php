<?php
require_once('../config/db_config.php');

// Consultas básicas para mostrar en el panel
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_clients = $pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();
$total_sales = $pdo->query("SELECT COUNT(*) FROM files")->fetchColumn();
$total_suppliers = $pdo->query("SELECT COUNT(*) FROM suppliers")->fetchColumn();
$total_tasks = $pdo->query("SELECT COUNT(*) FROM marketing_tasks WHERE estado != 'completada'")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Principal - Sistema de Gestión</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header>
        <h1>Panel de Administración</h1>
        <nav>
            <ul>
                <li><a href="ventas.php">Ventas</a></li>
                <li><a href="contabilidad.php">Contabilidad</a></li>
                <li><a href="operaciones.php">Operaciones</a></li>
                <li><a href="marketing.php">Marketing</a></li>
                <li><a href="gerencia.php">Gerencia</a></li>
                <li><a href="postventa.php">Postventa</a></li>
            </ul>
        </nav>
    </header>

    <main>
        <section id="dashboard">
            <div class="card">
                <h3>Usuarios</h3>
                <p><?= $total_users ?></p>
            </div>
            <div class="card">
                <h3>Clientes</h3>
                <p><?= $total_clients ?></p>
            </div>
            <div class="card">
                <h3>Ventas Registradas</h3>
                <p><?= $total_sales ?></p>
            </div>
            <div class="card">
                <h3>Proveedores</h3>
                <p><?= $total_suppliers ?></p>
            </div>
            <div class="card">
                <h3>Tareas Pendientes de Marketing</h3>
                <p><?= $total_tasks ?></p>
            </div>
        </section>
    </main>

    <footer>
        <p>&copy; 2025 Rap Travel - Sistema de Gestión</p>
    </footer>
</body>
</html>
