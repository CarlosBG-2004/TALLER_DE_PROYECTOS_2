<?php if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); } ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>SISTEMA TURÍSTICO | Iniciar sesión</title>
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">

  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">

  <!-- Bootstrap 5 (más moderno que AdminLTE 2) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <style>
    body {
      font-family: 'Poppins', sans-serif;
      background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), 
                  url('https://content.r9cdn.net/rimg/simg/2048/45618.jpg?width=1366&height=768&xhint=1020&yhint=831&crop=true') no-repeat center center fixed;
      background-size: cover;
      height: 100vh;
      margin: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #333;
    }

    .login-container {
      background: rgba(255, 255, 255, 0.92);
      border-radius: 20px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
      padding: 2.5rem;
      max-width: 420px;
      width: 100%;
    }

    .login-logo {
      text-align: center;
      margin-bottom: 1.5rem;
    }

    .login-logo h2 {
      font-weight: 600;
      color: #2c3e50;
      font-size: 1.8rem;
    }

    .login-logo span {
      color: #3498db;
    }

    .login-box-msg {
      text-align: center;
      color: #7f8c8d;
      margin-bottom: 1.5rem;
      font-size: 1rem;
    }

    .form-control {
      border: 1px solid #ddd;
      padding-left: 2.8rem;
      border-radius: 12px;
      height: 50px;
      font-size: 1rem;
    }

    .input-group-text {
      background: transparent;
      border: none;
      color: #3498db;
      font-size: 1.1rem;
    }

    .btn-login {
      background: #3498db;
      border: none;
      padding: 12px;
      font-weight: 600;
      border-radius: 12px;
      transition: all 0.3s ease;
      width: 100%;
    }

    .btn-login:hover {
      background: #2980b9;
      transform: translateY(-2px);
    }

    .forgot-password {
      text-align: center;
      margin-top: 15px;
      font-size: 0.9rem;
    }

    .forgot-password a {
      color: #3498db;
      text-decoration: none;
    }

    .forgot-password a:hover {
      text-decoration: underline;
    }

    .alert {
      border-radius: 12px;
    }
  </style>
</head>
<body>

<div class="login-container">
  <div class="login-logo">
    <h2><span>AGENCIAS</span> DE VIAJE</h2>
  </div>

  <p class="login-box-msg">Inicia sesión para continuar</p>

  <?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert-danger">
      <?php echo htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?>
    </div>
  <?php endif; ?>

  <form action="Login" method="post" autocomplete="off">
    <div class="mb-3 position-relative">
      <div class="input-group">
        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
        <input type="email" class="form-control" name="correo" placeholder="Correo electrónico" required>
      </div>
    </div>

    <div class="mb-4 position-relative">
      <div class="input-group">
        <span class="input-group-text"><i class="fas fa-lock"></i></span>
        <input type="password" class="form-control" name="contrasena" placeholder="Contraseña" required>
      </div>
    </div>

    <button type="submit" class="btn btn-login">Iniciar Sesión</button>
  </form>

  <p class="forgot-password">
    <a href="#" onclick="alert('Pídele al administrador restablecer tu contraseña.'); return false;">
      ¿Olvidaste tu contraseña?
    </a>
  </p>
</div>

<!-- JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>