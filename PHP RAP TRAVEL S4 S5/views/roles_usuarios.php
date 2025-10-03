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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Roles y Usuarios</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Source Sans Pro', 'Helvetica Neue', Arial, sans-serif;
            background: #ecf0f5;
            color: #333;
            min-height: 100vh;
            padding: 20px;
        }

        .wrapper {
            max-width: 1200px;
            margin: 0 auto;
        }

        .page-header {
            background: #fff;
            padding: 20px 25px;
            margin-bottom: 20px;
            border-radius: 3px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.24);
            border-left: 4px solid #dd4b39;
        }

        .page-header h1 {
            font-size: 28px;
            font-weight: 400;
            color: #333;
            margin: 0;
        }

        .page-header h1 i {
            margin-right: 10px;
            color: #dd4b39;
        }

        .content-wrapper {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .card {
            background: #fff;
            border-radius: 3px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.24);
            overflow: hidden;
        }

        .card-header {
            padding: 15px 20px;
            border-bottom: 1px solid #f4f4f4;
            background: #fafafa;
        }

        .card-header.primary {
            background: #3c8dbc;
            color: #fff;
            border-bottom: 0;
        }

        .card-header.success {
            background: #00a65a;
            color: #fff;
            border-bottom: 0;
        }

        .card-header.warning {
            background: #f39c12;
            color: #fff;
            border-bottom: 0;
        }

        .card-header h2 {
            font-size: 18px;
            font-weight: 500;
            margin: 0;
        }

        .card-header h2 i {
            margin-right: 8px;
        }

        .card-body {
            padding: 20px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 600;
            font-size: 14px;
        }

        .form-group label i {
            margin-right: 8px;
            color: #3c8dbc;
            width: 16px;
            text-align: center;
        }

        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d2d6de;
            border-radius: 3px;
            font-size: 14px;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
            background: #fff;
            color: #555;
            font-family: inherit;
        }

        .form-control:focus {
            border-color: #3c8dbc;
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(60, 141, 188, 0.25);
        }

        textarea.form-control {
            min-height: 80px;
            resize: vertical;
        }

        select.form-control {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23333' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 35px;
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 400;
            text-align: center;
            white-space: nowrap;
            vertical-align: middle;
            cursor: pointer;
            border: 1px solid transparent;
            border-radius: 3px;
            transition: all 0.15s ease-in-out;
            text-decoration: none;
        }

        .btn-primary {
            background: #3c8dbc;
            color: #fff;
            border-color: #367fa9;
            width: 100%;
            font-size: 15px;
            padding: 11px;
        }

        .btn-primary:hover {
            background: #367fa9;
            border-color: #204d74;
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.15);
        }

        .btn-success {
            background: #00a65a;
            color: #fff;
            border-color: #008d4c;
            width: 100%;
            font-size: 15px;
            padding: 11px;
        }

        .btn-success:hover {
            background: #008d4c;
            border-color: #007d42;
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.15);
        }

        .btn-warning {
            background: #f39c12;
            color: #fff;
            border-color: #e08e0b;
            width: 100%;
            font-size: 15px;
            padding: 11px;
        }

        .btn-warning:hover {
            background: #e08e0b;
            border-color: #c87f0a;
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.15);
        }

        .btn-default {
            background: #fff;
            color: #333;
            border: 1px solid #d2d6de;
            padding: 10px 20px;
        }

        .btn-default:hover {
            background: #f4f4f4;
            border-color: #8c8c8c;
        }

        .btn i {
            margin-right: 8px;
        }

        /* Lista de roles */
        .roles-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .role-item {
            padding: 15px;
            border-bottom: 1px solid #f4f4f4;
            transition: background 0.2s;
        }

        .role-item:last-child {
            border-bottom: 0;
        }

        .role-item:hover {
            background: #f9f9f9;
        }

        .role-name {
            font-weight: 600;
            color: #333;
            font-size: 15px;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
        }

        .role-name i {
            margin-right: 10px;
            color: #3c8dbc;
            font-size: 16px;
        }

        .role-description {
            color: #666;
            font-size: 13px;
            padding-left: 26px;
        }

        .empty-state {
            text-align: center;
            padding: 30px;
            color: #999;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        .back-link {
            background: #fff;
            padding: 15px 20px;
            border-radius: 3px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.24);
            display: inline-block;
            text-decoration: none;
            color: #333;
            transition: all 0.2s;
        }

        .back-link:hover {
            background: #f4f4f4;
            transform: translateX(-3px);
        }

        .back-link i {
            margin-right: 8px;
        }

        .full-width {
            grid-column: 1 / -1;
        }

        @media (max-width: 992px) {
            .content-wrapper {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 576px) {
            body {
                padding: 10px;
            }

            .page-header {
                padding: 15px;
            }

            .page-header h1 {
                font-size: 22px;
            }

            .card-body {
                padding: 15px;
            }
        }

        /* Animaciones */
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .card {
            animation: slideIn 0.3s ease-out;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="page-header">
            <h1><i class="fas fa-users-cog"></i> Gestionar Roles y Usuarios</h1>
        </div>

        <div class="content-wrapper">
            <!-- Formulario para crear un nuevo rol -->
            <div class="card">
                <div class="card-header primary">
                    <h2><i class="fas fa-user-tag"></i> Crear Nuevo Rol</h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="roles_usuarios.php">
                        <div class="form-group">
                            <label><i class="fas fa-tag"></i> Nombre del Rol</label>
                            <input type="text" name="role_name" class="form-control" placeholder="Ej: Vendedor, Supervisor, etc." required>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-align-left"></i> Descripción del Rol</label>
                            <textarea name="role_description" class="form-control" placeholder="Describe las responsabilidades de este rol..." required></textarea>
                        </div>
                        <button type="submit" name="create_role" class="btn btn-primary">
                            <i class="fas fa-plus-circle"></i> Crear Rol
                        </button>
                    </form>
                </div>
            </div>

            <!-- Lista de Roles Existentes -->
            <div class="card">
                <div class="card-header primary">
                    <h2><i class="fas fa-list"></i> Roles Existentes</h2>
                </div>
                <div class="card-body" style="padding: 0;">
                    <?php if (count($roles) > 0): ?>
                        <ul class="roles-list">
                            <?php foreach ($roles as $role): ?>
                                <li class="role-item">
                                    <div class="role-name">
                                        <i class="fas fa-shield-alt"></i>
                                        <?php echo htmlspecialchars($role['nombre']); ?>
                                    </div>
                                    <div class="role-description">
                                        <?php echo htmlspecialchars($role['descripcion']); ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-folder-open"></i>
                            <p>No hay roles registrados</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Formulario para crear un nuevo usuario -->
            <div class="card full-width">
                <div class="card-header success">
                    <h2><i class="fas fa-user-plus"></i> Crear Nuevo Usuario</h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="roles_usuarios.php">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
                            <div class="form-group">
                                <label><i class="fas fa-user"></i> Nombre</label>
                                <input type="text" name="user_name" class="form-control" placeholder="Nombre del usuario" required>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-user"></i> Apellido</label>
                                <input type="text" name="user_lastname" class="form-control" placeholder="Apellido del usuario" required>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-envelope"></i> Correo Electrónico</label>
                                <input type="email" name="user_email" class="form-control" placeholder="ejemplo@correo.com" required>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-lock"></i> Contraseña</label>
                                <input type="password" name="user_password" class="form-control" placeholder="Mínimo 8 caracteres" required>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-user-tag"></i> Rol del Usuario</label>
                                <select name="user_role_id" class="form-control" required>
                                    <option value="">Seleccione un rol</option>
                                    <?php foreach ($roles as $role): ?>
                                        <option value="<?php echo $role['id']; ?>"><?php echo htmlspecialchars($role['nombre']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <button type="submit" name="create_user" class="btn btn-success">
                            <i class="fas fa-user-check"></i> Crear Usuario
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <a href="index.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Volver al Panel Principal
        </a>
    </div>
</body>
</html>