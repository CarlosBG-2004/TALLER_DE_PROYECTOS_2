<?php
session_start();
require_once('../config/db_config.php');

// Verificar si el usuario tiene el rol de gerente (rol_id = 1)
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header('Location: index.php'); // Si no es gerente, redirigir
    exit;
}

// Obtener los roles
$rolesStmt = $pdo->query("SELECT * FROM roles");
$roles = $rolesStmt->fetchAll(PDO::FETCH_ASSOC);

// Procesar la creación de un nuevo rol
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_role'])) {
    $role_name = $_POST['role_name'];
    $role_description = $_POST['role_description'];

    // Insertar nuevo rol
    $stmt = $pdo->prepare("INSERT INTO roles (nombre, descripcion) VALUES (:nombre, :descripcion)");
    $stmt->execute(['nombre' => $role_name, 'descripcion' => $role_description]);

    // Redirigir para evitar que el formulario se envíe varias veces
    header('Location: roles_usuarios.php');
    exit;
}

// Procesar la creación de un nuevo usuario
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_user'])) {
    $user_name = $_POST['user_name'];
    $user_lastname = $_POST['user_lastname'];
    $user_email = $_POST['user_email'];
    $user_password = password_hash($_POST['user_password'], PASSWORD_DEFAULT);
    $user_role_id = $_POST['user_role_id'];

    // Insertar nuevo usuario
    $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, apellido, correo, contrasena_hash, rol_id) 
                           VALUES (:nombre, :apellido, :correo, :contrasena_hash, :rol_id)");
    $stmt->execute([
        'nombre' => $user_name,
        'apellido' => $user_lastname,
        'correo' => $user_email,
        'contrasena_hash' => $user_password,
        'rol_id' => $user_role_id
    ]);

    // Redirigir después de crear el usuario
    header('Location: roles_usuarios.php');
    exit;
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestionar Roles y Usuarios</title>
</head>
<body>
    <h1>Gestionar Roles y Usuarios</h1>

    <!-- Formulario para crear un nuevo rol -->
    <h2>Crear Nuevo Rol</h2>
    <form method="POST" action="roles_usuarios.php">
        <input type="text" name="role_name" placeholder="Nombre del Rol" required>
        <textarea name="role_description" placeholder="Descripción del Rol" required></textarea>
        <button type="submit" name="create_role">Crear Rol</button>
    </form>

    <hr>

    <!-- Lista de Roles Existentes -->
    <h2>Roles Existentes</h2>
    <ul>
        <?php foreach ($roles as $role): ?>
            <li><?php echo $role['nombre'] . " - " . $role['descripcion']; ?></li>
        <?php endforeach; ?>
    </ul>

    <hr>

    <!-- Formulario para crear un nuevo usuario -->
    <h2>Crear Nuevo Usuario</h2>
    <form method="POST" action="roles_usuarios.php">
        <input type="text" name="user_name" placeholder="Nombre" required>
        <input type="text" name="user_lastname" placeholder="Apellido" required>
        <input type="email" name="user_email" placeholder="Correo electrónico" required>
        <input type="password" name="user_password" placeholder="Contraseña" required>
        <select name="user_role_id" required>
            <?php foreach ($roles as $role): ?>
                <option value="<?php echo $role['id']; ?>"><?php echo $role['nombre']; ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" name="create_user">Crear Usuario</button>
    </form>

    <hr>

    <a href="index.php">Volver al Panel Principal</a>
</body>
</html>
