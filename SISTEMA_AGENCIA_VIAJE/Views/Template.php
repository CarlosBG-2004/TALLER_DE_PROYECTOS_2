<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>ERP TURISMO</title>
    <!-- Tell the browser to be responsive to screen width -->
    <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
    <!-- Bootstrap 3.3.7 -->
    <link rel="stylesheet" href="Views/Resources/bower_components/bootstrap/dist/css/bootstrap.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="Views/Resources/bower_components/font-awesome/css/font-awesome.min.css">
    <!-- Ionicons -->
    <link rel="stylesheet" href="Views/Resources/bower_components/Ionicons/css/ionicons.min.css">
    <!-- Theme style -->
    <link rel="stylesheet" href="Views/Resources/dist/css/AdminLTE.min.css">
    <!-- AdminLTE Skins -->
    <link rel="stylesheet" href="Views/Resources/dist/css/skins/_all-skins.min.css">

    <!--[if lt IE 9]>
      <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
      <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->

    <!-- Google Font -->
    <link rel="stylesheet"
          href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,600,700,300italic,400italic,600italic">
</head>

<body class="hold-transition skin-blue sidebar-mini">
    <!-- Site wrapper -->
    <div class="wrapper">

        <!-- HEADER -->
        <?php include "Views/Modules/Header.php"; ?>

        <!-- MENU -->
        <?php include "Views/Modules/Menu.php"; ?>

        <div class="content-wrapper">
            <?php
                // Lista blanca de páginas permitidas
                $allowedPages = [
                    // existentes
                    'Gerencia','Ventas','Marketing','Role','Users','Operaciones',

                    // nuevas
                    'Dashboard',
                    'Clientes','Agencias','Proveedores', 'DashboardContable',
                    'Expedientes','Pagos',
                    'ServiciosOperaciones','Programacion',
                    'InventarioBienes','InventarioMovimientos',
                    'Contabilidad','ContabilidadMovimientos','ContabilidadCategorias',
                    'CajaChica','CajaChicaMovimientos','ReportesContables',
                    'MarketingTareas','Plantillas','Campanas',
                    'Calendario','CalendarioMarketing',
                    'Postventa','Auditoria','Permisos','Logistica'
                ];

                // Lee parámetro ?Pages=... (por tu .htaccess Opción A)
                $page = isset($_GET['Pages']) ? $_GET['Pages'] : 'Dashboard';

                // Seguridad básica: evita rutas con / o caracteres raros
                $page = preg_replace('/[^A-Za-z0-9]/', '', $page);

                if (in_array($page, $allowedPages, true)) {
                    $file = "Views/Pages/{$page}.php";
                    if (file_exists($file)) {
                        include $file;
                    } else {
                        // 404 si aún no has creado el archivo físico
                        echo '<section class="content"><div class="callout callout-danger"><h4>404</h4><p>La página existe en el router pero falta el archivo: <code>Views/Pages/'.$page.'.php</code></p></div></section>';
                    }
                } else {
                    // Fallback a Dashboard
                    if (file_exists("Views/Pages/Dashboard.php")) {
                        include "Views/Pages/Dashboard.php";
                    } else {
                        echo '<section class="content"><div class="box"><div class="box-body">Bienvenido al ERP TURISMO. Crea <code>Views/Pages/Dashboard.php</code> para iniciar.</div></div></section>';
                    }
                }
            ?>
        </div>

        <!-- FOOTER -->
        <?php include "Views/Modules/Footer.php"; ?>

    </div>
    <!-- ./wrapper -->

    <!-- jQuery 3 -->
    <script src="Views/Resources/bower_components/jquery/dist/jquery.min.js"></script>
    <!-- Bootstrap 3.3.7 -->
    <script src="Views/Resources/bower_components/bootstrap/dist/js/bootstrap.min.js"></script>
    <!-- SlimScroll -->
    <script src="Views/Resources/bower_components/jquery-slimscroll/jquery.slimscroll.min.js"></script>
    <!-- FastClick -->
    <script src="Views/Resources/bower_components/fastclick/lib/fastclick.js"></script>
    <!-- AdminLTE App -->
    <script src="Views/Resources/dist/js/adminlte.min.js"></script>
    <!-- AdminLTE for demo purposes -->
    <script src="Views/Resources/dist/js/demo.js"></script>
    <script>
        $(document).ready(function () {
            $('.sidebar-menu').tree();
        });
    </script>
</body>
</html>
