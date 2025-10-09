<?php if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); } ?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>ERP TURISMO | Iniciar sesión</title>
  <meta content="width=device-width, initial-scale=1, maximum-scale=1" name="viewport">

  <!-- CSS AdminLTE 2 -->
  <link rel="stylesheet" href="Views/Resources/bower_components/bootstrap/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="Views/Resources/bower_components/font-awesome/css/font-awesome.min.css">
  <link rel="stylesheet" href="Views/Resources/dist/css/AdminLTE.min.css">
  <link rel="stylesheet" href="Views/Resources/plugins/iCheck/square/blue.css"><!-- opcional si usas iCheck -->
</head>
<body class="hold-transition login-page">
<div class="login-box">
  <div class="login-logo">
    <a href="#"><b>ERP</b> TURISMO</a>
  </div>
  <div class="login-box-body">
    <p class="login-box-msg">Inicia sesión para continuar</p>

    <?php if (!empty($_SESSION['flash_error'])): ?>
      <div class="alert alert-danger">
        <?php echo htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?>
      </div>
    <?php endif; ?>

    <form action="Login" method="post" autocomplete="off">
      <div class="form-group has-feedback">
        <input type="email" class="form-control" name="correo" placeholder="Correo" required>
        <span class="glyphicon glyphicon-envelope form-control-feedback"></span>
      </div>

      <div class="form-group has-feedback">
        <input type="password" class="form-control" name="contrasena" placeholder="Contraseña" required>
        <span class="glyphicon glyphicon-lock form-control-feedback"></span>
      </div>

      <div class="row">
        <div class="col-xs-8">
          <!-- <div class="checkbox icheck"><label><input type="checkbox"> Recordarme</label></div> -->
        </div>
        <div class="col-xs-4">
          <button type="submit" class="btn btn-primary btn-block btn-flat">Entrar</button>
        </div>
      </div>
    </form>

    <p class="text-center" style="margin-top:10px;">
      <a href="#" onclick="alert('Pídele al admin restablecer tu clave.');return false;">¿Olvidaste tu contraseña?</a>
    </p>
  </div>
</div>

<!-- JS -->
<script src="Views/Resources/bower_components/jquery/dist/jquery.min.js"></script>
<script src="Views/Resources/bower_components/bootstrap/dist/js/bootstrap.min.js"></script>
<script src="Views/Resources/plugins/iCheck/icheck.min.js"></script>
<script>
  // iCheck opcional
  $(function(){ $('input').iCheck({checkboxClass:'icheckbox_square-blue', radioClass:'iradio_square-blue', increaseArea:'20%'}); });
</script>
</body>
</html>
