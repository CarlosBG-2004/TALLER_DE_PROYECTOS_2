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
    <title>Iniciar Sesión | Rap Travel Perú</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

    <style>
        :root {
            --color-primario-rojo: #D60000;
            --color-fondo-verde: #0A4F32;
            --color-blanco: #FFFFFF;
            --color-texto-gris: #E0E0E0;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body, html { height: 100%; font-family: 'Montserrat', sans-serif; overflow: hidden; }

        .main-container {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            position: relative;
        }

        .background-image {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%; z-index: -2;
            background-image: url('https://images.pexels.com/photos/2929906/pexels-photo-2929906.jpeg');
            background-size: cover;
            background-position: center;
            animation: kenBurns 25s ease-out infinite;
        }

        @keyframes kenBurns {
            0% { transform: scale(1.0) translateX(0); }
            50% { transform: scale(1.1) translateX(-2%); }
            100% { transform: scale(1.0) translateX(0); }
        }

        .background-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: linear-gradient(to top, rgba(10, 79, 50, 0.6), rgba(10, 79, 50, 0.3));
            z-index: -1;
        }

        .login-box {
            background: rgba(0, 0, 0, 0.7); 
            padding: 40px 30px;
            border-radius: 15px;
            width: 100%;
            max-width: 380px;
            text-align: center;
            color: var(--color-blanco);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(10px); 
            border: 1px solid rgba(255, 255, 255, 0.1);
            animation: fadeInFromBottom 0.8s ease-out forwards;
            opacity: 0;
            z-index: 1;
        }

        .logo { max-width: 200px; margin-bottom: 20px; }
        .login-box h2 { font-weight: 700; margin-bottom: 10px; font-size: 1.8rem; }
        .login-box p { color: var(--color-texto-gris); margin-bottom: 30px; font-weight: 500; }
        .input-group { position: relative; margin-bottom: 25px; }
        .input-group .icon { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--color-texto-gris); transition: color 0.3s ease; }
        .input-field { width: 100%; padding: 15px 15px 15px 45px; background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 8px; color: var(--color-blanco); font-size: 1rem; transition: all 0.3s ease; }
        .input-field::placeholder { color: var(--color-texto-gris); }
        .input-field:focus { outline: none; border-color: var(--color-primario-rojo); box-shadow: 0 0 10px rgba(214, 0, 0, 0.5); }
        .input-field:focus + .icon { color: var(--color-primario-rojo); }
        .submit-btn { width: 100%; padding: 15px; background: var(--color-primario-rojo); border: none; border-radius: 8px; color: var(--color-blanco); font-size: 1.1rem; font-weight: 700; cursor: pointer; transition: all 0.3s ease; text-transform: uppercase; letter-spacing: 1px; }
        .submit-btn:hover { background: #B80000; transform: translateY(-3px); box-shadow: 0 5px 15px rgba(214, 0, 0, 0.4); }
        .submit-btn:active { transform: translateY(0); }
        .extra-options { display: flex; justify-content: space-between; align-items: center; margin-top: 20px; font-size: 0.9rem; }
        .extra-options a { color: var(--color-texto-gris); text-decoration: none; transition: color 0.3s ease; }
        .extra-options a:hover { color: var(--color-primario-rojo); text-decoration: underline; }

        @keyframes fadeInFromBottom {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="background-image"></div>
        <div class="background-overlay"></div>
        <div class="login-box">
            <img src="https://www.raptravelperu.com/wp-content/uploads/Rap-Travel-Peru-1.png" alt="Logo Rap Travel Perú" class="logo">
            <h2>Bienvenido de Nuevo</h2>
            <p>Inicia sesión para continuar tu aventura</p>
            <?php if (isset($error)): ?>
                <p style="color: red;"><?php echo $error; ?></p>
            <?php endif; ?>
            <form method="POST" action="login.php">
                <div class="input-group">
                    <input type="email" name="email" class="input-field" placeholder="Correo electrónico" required>
                    <i class="fas fa-user icon"></i>
                </div>
                <div class="input-group">
                    <input type="password" name="password" class="input-field" placeholder="Contraseña" required>
                    <i class="fas fa-lock icon"></i>
                </div>
                <button type="submit" class="submit-btn">Iniciar sesión</button>
                <div class="extra-options">
                    <a href="#">Crear cuenta</a>
                    <a href="#">¿Olvidaste tu contraseña?</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>