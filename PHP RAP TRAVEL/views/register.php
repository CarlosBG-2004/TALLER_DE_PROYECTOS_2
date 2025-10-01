<?php
session_start();
require_once('../config/db_config.php'); // Conexión a la DB

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = $_POST['nombre'];
    $apellido = $_POST['apellido'];
    $correo = $_POST['correo'];
    $contrasena = $_POST['contrasena'];

    // Rol por defecto: 2 (Ventas, por ejemplo). Asegúrate de tener un registro en la tabla roles con id=2
    $rol_id = 2;

    // Verificar si el correo ya existe
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE correo = :correo");
    $stmt->execute(['correo' => $correo]);

    if ($stmt->rowCount() > 0) {
        $error = "❌ Este correo ya está registrado.";
    } else {
        // Encriptar la contraseña
        $hash = password_hash($contrasena, PASSWORD_DEFAULT);

        // Insertar el usuario
        $stmt = $pdo->prepare("
            INSERT INTO usuarios (nombre, apellido, correo, contrasena_hash, rol_id) 
            VALUES (:nombre, :apellido, :correo, :hash, :rol_id)
        ");
        $stmt->execute([
            'nombre' => $nombre,
            'apellido' => $apellido,
            'correo' => $correo,
            'hash' => $hash,
            'rol_id' => $rol_id
        ]);

        // Redirigir al login después de registrarse
        header('Location: login.php');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Cuenta | Rap Travel</title>
    <style>
        body { font-family: Arial, sans-serif; background: #0A4F32; display: flex; justify-content: center; align-items: center; height: 100vh; }
        .box { background: rgba(0,0,0,0.7); padding: 30px; border-radius: 10px; color: white; width: 100%; max-width: 380px; }
        h2 { text-align: center; margin-bottom: 20px; }
        input { width: 100%; padding: 12px; margin-bottom: 15px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.2); background: rgba(255,255,255,0.1); color: white; }
        button { width: 100%; padding: 12px; background: #D60000; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; color: white; }
        button:hover { background: #B80000; }
        p.error { color: #ff4d4d; font-weight: bold; text-align: center; }
        .link-login { text-align: center; margin-top: 15px; }
        .link-login a { color: #E0E0E0; text-decoration: none; }
        .link-login a:hover { color: #D60000; text-decoration: underline; }
    </style>
</head>
<body>
    <div class="box">
        <h2>Crear Cuenta</h2>
        <?php if(isset($error)) echo "<p class='error'>$error</p>"; ?>
        <form method="POST" action="register.php">
            <input type="text" name="nombre" placeholder="Nombre" required>
            <input type="text" name="apellido" placeholder="Apellido" required>
            <input type="email" name="correo" placeholder="Correo electrónico" required>
            <input type="password" name="contrasena" placeholder="Contraseña" required>
            <button type="submit">Registrar</button>
        </form>
        <div class="link-login">
            <p>¿Ya tienes cuenta? <a href="login.php">Inicia sesión</a></p>
        </div>
    </div>
</body>
</html>
