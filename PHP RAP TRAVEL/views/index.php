<?php
session_start();
require_once('../config/db_config.php'); // Conexión a la base de datos

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); // Redirigir al login si no está autenticado
    exit;
}

// Traer información del usuario
$stmt = $pdo->prepare("SELECT nombre, apellido, role_id FROM usuarios WHERE id = :id");

$stmt->execute(['id' => $_SESSION['user_id']]);
$user = $stmt->fetch();

// Consultas básicas para mostrar en el dashboard
$total_users = $pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
$total_clients = $pdo->query("SELECT COUNT(*) FROM clientes")->fetchColumn();
$total_sales = $pdo->query("SELECT COUNT(*) FROM archivos")->fetchColumn();
$total_suppliers = $pdo->query("SELECT COUNT(*) FROM proveedores")->fetchColumn();
$total_tasks = $pdo->query("SELECT COUNT(*) FROM tareas_marketing WHERE estado != 'completada'")->fetchColumn();

// Si el usuario es gerente (role_id = 1), mostrar estadísticas adicionales
if ($user['role_id'] == 1) {
    // Consultas especiales para el gerente
    $total_revenue = $pdo->query("SELECT SUM(monto_total) FROM archivos")->fetchColumn(); // Ingresos totales por ventas
    $total_pending_payments = $pdo->query("SELECT SUM(monto_total - monto_depositado) FROM archivos WHERE monto_total > monto_depositado")->fetchColumn(); // Pagos pendientes
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Principal - Rap Travel</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header>
        <h1>Panel Principal</h1>
        <p>Bienvenido, <?php echo $user['nombre'] . " " . $user['apellido']; ?> | Rol: <?php echo ($user['role_id'] == 1) ? 'Gerente' : 'Usuario'; ?></p>
        <nav>
            <ul>
                <li><a href="ventas.php">Ventas</a></li>
                <li><a href="contabilidad.php">Contabilidad</a></li>
                <li><a href="operaciones.php">Operaciones</a></li>
                <li><a href="marketing.php">Marketing</a></li>
                <li><a href="gerencia.php">Gerencia</a></li>
                <li><a href="postventa.php">Postventa</a></li>
                <li><a href="logout.php">Cerrar sesión</a></li>
            </ul>
        </nav>
    </header>

    <main>
        <section id="dashboard">
            <div class="card">
                <h3>Usuarios</h3>
                <p><?php echo $total_users; ?></p>
            </div>
            <div class="card">
                <h3>Clientes</h3>
                <p><?php echo $total_clients; ?></p>
            </div>
            <div class="card">
                <h3>Ventas Registradas</h3>
                <p><?php echo $total_sales; ?></p>
            </div>
            <div class="card">
                <h3>Proveedores</h3>
                <p><?php echo $total_suppliers; ?></p>
            </div>
            <div class="card">
                <h3>Tareas Pendientes de Marketing</h3>
                <p><?php echo $total_tasks; ?></p>
            </div>

            <!-- Solo visible para el gerente -->
            <?php if ($user['role_id'] == 1): ?>
                <div class="card">
                    <h3>Ingresos Totales</h3>
                    <p><?php echo '$' . number_format($total_revenue, 2); ?></p>
                </div>
                <div class="card">
                    <h3>Pagos Pendientes</h3>
                    <p><?php echo '$' . number_format($total_pending_payments, 2); ?></p>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <footer>
        <p>&copy; 2025 Rap Travel - Sistema de Gestión</p>
    </footer>
</body>
</html>
