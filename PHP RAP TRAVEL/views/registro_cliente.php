<?php
session_start();
require_once('../config/db_config.php');

// Verificar si el usuario tiene acceso (rol de gerente o ventas)
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); // Redirigir al login si no está autenticado
    exit;
}

// Procesar el registro de cliente
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = $_POST['nombre'];
    $apellido = $_POST['apellido'];
    $correo = $_POST['correo'];
    $telefono = $_POST['telefono'];
    $pais = $_POST['pais'];
    $fecha_nacimiento = $_POST['fecha_nacimiento'];

    // Insertar nuevo cliente en la base de datos
    $stmt = $pdo->prepare("INSERT INTO clientes (nombre, apellido, correo, telefono, pais, fecha_nacimiento) 
                           VALUES (:nombre, :apellido, :correo, :telefono, :pais, :fecha_nacimiento)");
    $stmt->execute([
        'nombre' => $nombre,
        'apellido' => $apellido,
        'correo' => $correo,
        'telefono' => $telefono,
        'pais' => $pais,
        'fecha_nacimiento' => $fecha_nacimiento
    ]);

    // Redirigir después de crear el cliente
    header('Location: index.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Cliente</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            width: 100%;
            max-width: 600px;
        }

        .card {
            background: #fff;
            border-radius: 3px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.24);
            margin-bottom: 20px;
        }

        .card-header {
            background: #3c8dbc;
            color: #fff;
            padding: 15px;
            border-radius: 3px 3px 0 0;
            border-bottom: 0;
        }

        .card-header h1 {
            font-size: 24px;
            font-weight: 400;
            margin: 0;
        }

        .card-body {
            padding: 25px;
        }

        .form-group {
            margin-bottom: 20px;
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
            padding: 12px 15px;
            border: 1px solid #d2d6de;
            border-radius: 3px;
            font-size: 14px;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
            background: #fff;
            color: #555;
        }

        .form-control:focus {
            border-color: #3c8dbc;
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(60, 141, 188, 0.25);
        }

        .form-control::placeholder {
            color: #999;
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
            font-size: 16px;
            padding: 12px;
            margin-top: 10px;
        }

        .btn-primary:hover {
            background: #367fa9;
            border-color: #204d74;
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.15);
        }

        .btn-primary:active {
            transform: translateY(0);
            box-shadow: 0 1px 3px rgba(0,0,0,0.12);
        }

        .btn-primary i {
            margin-right: 8px;
        }

        .btn-secondary {
            background: #fff;
            color: #333;
            border: 1px solid #d2d6de;
            padding: 10px 20px;
            margin-top: 10px;
            width: 100%;
        }

        .btn-secondary:hover {
            background: #f4f4f4;
            border-color: #8c8c8c;
        }

        .row {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .col-6 {
            flex: 1;
            min-width: 0;
        }

        @media (max-width: 576px) {
            .col-6 {
                flex: 100%;
            }
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 3px;
        }

        .alert-success {
            background: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }

        /* Input icons */
        .input-group {
            position: relative;
            display: flex;
            align-items: stretch;
            width: 100%;
        }

        .input-group-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            pointer-events: none;
            z-index: 1;
        }

        .input-group .form-control {
            padding-left: 38px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h1><i class="fas fa-user-plus"></i> Registrar Nuevo Cliente</h1>
            </div>
            <div class="card-body">
                <form method="POST" action="registro_cliente.php">
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label><i class="fas fa-user"></i> Nombre</label>
                                <div class="input-group">
                                    <i class="fas fa-user input-group-icon"></i>
                                    <input type="text" name="nombre" class="form-control" placeholder="Ingrese el nombre" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label><i class="fas fa-user"></i> Apellido</label>
                                <div class="input-group">
                                    <i class="fas fa-user input-group-icon"></i>
                                    <input type="text" name="apellido" class="form-control" placeholder="Ingrese el apellido" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Correo Electrónico</label>
                        <div class="input-group">
                            <i class="fas fa-envelope input-group-icon"></i>
                            <input type="email" name="correo" class="form-control" placeholder="ejemplo@correo.com" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-phone"></i> Teléfono</label>
                        <div class="input-group">
                            <i class="fas fa-phone input-group-icon"></i>
                            <input type="tel" name="telefono" class="form-control" placeholder="+51 999 999 999" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label><i class="fas fa-globe"></i> País</label>
                                <div class="input-group">
                                    <i class="fas fa-globe input-group-icon"></i>
                                    <input type="text" name="pais" class="form-control" placeholder="Perú" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label><i class="fas fa-calendar"></i> Fecha de Nacimiento</label>
                                <div class="input-group">
                                    <i class="fas fa-calendar input-group-icon"></i>
                                    <input type="date" name="fecha_nacimiento" class="form-control" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Registrar Cliente
                    </button>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Volver
                    </a>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
