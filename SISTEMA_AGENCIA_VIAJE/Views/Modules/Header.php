<header class="main-header">
  <!-- Logo -->
  <a href="Dashboard" class="logo">
    <!-- mini logo for sidebar mini 50x50 pixels -->
    <span class="logo-mini"><b>ERP</b>T</span>
    <!-- logo for regular state and mobile devices -->
    <span class="logo-lg"><b>AGENCIAS</b> VIAJES</span>
  </a>

  <!-- Header Navbar -->
  <nav class="navbar navbar-static-top">
    <!-- Sidebar toggle button-->
    <a href="#" class="sidebar-toggle" data-toggle="push-menu" role="button">
      <span class="sr-only">Toggle navigation</span>
      <span class="icon-bar"></span>
      <span class="icon-bar"></span>
      <span class="icon-bar"></span>
    </a>

    <div class="navbar-custom-menu">
      <ul class="nav navbar-nav">
        <!-- Messages -->
        <li class="dropdown messages-menu">
          <a href="#" class="dropdown-toggle" data-toggle="dropdown">
            <i class="fa fa-envelope-o"></i>
            <span class="label label-success">4</span>
          </a>
          <ul class="dropdown-menu">
            <li class="header">You have 4 messages</li>
            <li>
              <ul class="menu">
                <li>
                  <a href="#">
                    <div class="pull-left">
                      <img src="Views/Images/Users/images.test.jpg" class="img-circle" alt="User Image">
                    </div>
                    <h4>
                      Support Team
                      <small><i class="fa fa-clock-o"></i> 5 mins</small>
                    </h4>
                    <p>Why not buy a new awesome theme?</p>
                  </a>
                </li>
              </ul>
            </li>
            <li class="footer"><a href="#">See All Messages</a></li>
          </ul>
        </li>

        <!-- Notifications -->
        <li class="dropdown notifications-menu">
          <a href="#" class="dropdown-toggle" data-toggle="dropdown">
            <i class="fa fa-bell-o"></i>
            <span class="label label-warning">10</span>
          </a>
          <ul class="dropdown-menu">
            <li class="header">You have 10 notifications</li>
            <li>
              <ul class="menu">
                <li>
                  <a href="#">
                    <i class="fa fa-users text-aqua"></i> 5 new members joined today
                  </a>
                </li>
              </ul>
            </li>
            <li class="footer"><a href="#">View all</a></li>
          </ul>
        </li>

        <!-- User Account -->
        <li class="dropdown user user-menu">
          <a href="#" class="dropdown-toggle" data-toggle="dropdown">
            <img src="Views/Images/Users/images.test.jpg" class="user-image" alt="User Image">
            <span class="hidden-xs">
              <?php
                if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
                echo isset($_SESSION['user']['nombre'])
                  ? htmlspecialchars($_SESSION['user']['nombre'].' '.$_SESSION['user']['apellido'])
                  : 'Usuario';
              ?>
            </span>
          </a>
          <ul class="dropdown-menu">
            <!-- User image -->
            <li class="user-header">
              <img src="Views/Images/Users/images.test.jpg" class="img-circle" alt="User Image">
              <p>
                <?php
                  $nombre = isset($_SESSION['user']['nombre']) ? $_SESSION['user']['nombre'].' '.$_SESSION['user']['apellido'] : 'Usuario';
                  $rol = isset($_SESSION['user']['rol']) ? $_SESSION['user']['rol'] : 'Rol';
                ?>
                <?php echo htmlspecialchars($nombre); ?> - <?php echo htmlspecialchars($rol); ?>
                <small>ERP Turismo</small>
              </p>
            </li>

            <!-- Menu Footer-->
            <li class="user-footer">
              <div class="pull-left">
                <a href="Perfil" class="btn btn-primary">PERFIL</a>
              </div>
              <div class="pull-right">
                <!-- IMPORTANTE: este enlace cierra sesión -->
                <a href="Logout" class="btn btn-danger">CERRAR SESION</a>
                <!-- Si no usas URLs limpias, usa: href="?Pages=Logout" -->
              </div>
            </li>
          </ul>
        </li>

        <!-- Configuración (opcional)
        <li>
          <a href="#" data-toggle="control-sidebar"><i class="fa fa-gears"></i></a>
        </li> -->
      </ul>
    </div>
  </nav>
</header>
