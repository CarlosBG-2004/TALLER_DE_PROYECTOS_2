<?php
session_start();
require_once('../config/db_config.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Obtener datos del formulario
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Buscar usuario en la base de datos
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        // Iniciar sesión si las credenciales son correctas
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role_id'] = $user['role_id'];
        $_SESSION['email'] = $user['email'];
        header('Location: index.php'); // Redirige al panel principal
        exit;
    } else {
        $error = "Credenciales incorrectas.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema de Gestión</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div id="login-form">
        <h2>Iniciar sesión</h2>
        <?php if (isset($error)): ?>
            <p style="color: red;"><?php echo $error; ?></p>
        <?php endif; ?>
        <form method="POST" action="login.php">
            <div>
                <label for="email">Correo electrónico</label>
                <input type="email" name="email" required>
            </div>
            <div>
                <label for="password">Contraseña</label>
                <input type="password" name="password" required>
            </div>
            <div>
                <button type="submit">Iniciar sesión</button>
            </div>
        </form>
    </div>
</body>
</html>
