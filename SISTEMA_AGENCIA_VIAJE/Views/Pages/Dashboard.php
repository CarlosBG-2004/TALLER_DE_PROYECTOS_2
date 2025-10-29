<?php
// Incluir la conexión a la base de datos
require_once "Config/Database.php";

// Obtener la conexión a la base de datos
$pdo = Database::getConnection();

// 1. Obtener Ingresos del mes
$ingresosQuery = $pdo->prepare("SELECT SUM(monto) as total_ingresos FROM contabilidad_movimientos WHERE tipo = 'ingreso' AND MONTH(fecha) = MONTH(CURRENT_DATE()) AND YEAR(fecha) = YEAR(CURRENT_DATE())");
$ingresosQuery->execute();
$ingresos = $ingresosQuery->fetch(PDO::FETCH_ASSOC);
$totalIngresos = $ingresos['total_ingresos'] ?? 0;

// 2. Obtener Gastos del mes
$gastosQuery = $pdo->prepare("SELECT SUM(monto) as total_gastos FROM contabilidad_movimientos WHERE tipo = 'gasto' AND MONTH(fecha) = MONTH(CURRENT_DATE()) AND YEAR(fecha) = YEAR(CURRENT_DATE())");
$gastosQuery->execute();
$gastos = $gastosQuery->fetch(PDO::FETCH_ASSOC);
$totalGastos = $gastos['total_gastos'] ?? 0;

// 3. Obtener Deudas por cobrar (monto total de pagos pendientes)
$deudasQuery = $pdo->prepare("SELECT SUM(monto_total - monto_depositado) AS deudas FROM expedientes WHERE estado != 'anulado' AND fecha_venta <= CURRENT_DATE()");
$deudasQuery->execute();
$deudas = $deudasQuery->fetch(PDO::FETCH_ASSOC);
$totalDeudas = $deudas['deudas'] ?? 0;

// 4. Obtener Ventas del día
$ventasHoyQuery = $pdo->prepare("SELECT SUM(monto_total) AS ventas_hoy FROM expedientes WHERE DATE(fecha_venta) = CURRENT_DATE()");
$ventasHoyQuery->execute();
$ventasHoy = $ventasHoyQuery->fetch(PDO::FETCH_ASSOC);
$totalVentasHoy = $ventasHoy['ventas_hoy'] ?? 0;
?>

<section class="content-header">
  <h1>
    Dashboard
    <small>Resumen general</small>
  </h1>
  <ol class="breadcrumb">
    <li><a href="Dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
    <li class="active">Dashboard</li>
  </ol>
</section>

<section class="content">

  <!-- KPIs -->
  <div class="row">
    <div class="col-lg-3 col-xs-6">
      <div class="small-box bg-green">
        <div class="inner">
          <h3>S/ <?php echo number_format($totalIngresos, 2); ?></h3>
          <p>Ingresos del mes</p>
        </div>
        <div class="icon">
          <i class="fa fa-arrow-up"></i>
        </div>
        <a href="ContabilidadMovimientos" class="small-box-footer">
          Ver detalle <i class="fa fa-arrow-circle-right"></i>
        </a>
      </div>
    </div>

    <div class="col-lg-3 col-xs-6">
      <div class="small-box bg-red">
        <div class="inner">
          <h3>S/ <?php echo number_format($totalGastos, 2); ?></h3>
          <p>Gastos del mes</p>
        </div>
        <div class="icon">
          <i class="fa fa-arrow-down"></i>
        </div>
        <a href="ContabilidadMovimientos" class="small-box-footer">
          Ver detalle <i class="fa fa-arrow-circle-right"></i>
        </a>
      </div>
    </div>

    <div class="col-lg-3 col-xs-6">
      <div class="small-box bg-yellow">
        <div class="inner">
          <h3>S/ <?php echo number_format($totalDeudas, 2); ?></h3>
          <p>Deudas por cobrar</p>
        </div>
        <div class="icon">
          <i class="fa fa-exclamation-triangle"></i>
        </div>
        <a href="ReportesContables" class="small-box-footer">
          Ver reporte <i class="fa fa-arrow-circle-right"></i>
        </a>
      </div>
    </div>

    <div class="col-lg-3 col-xs-6">
      <div class="small-box bg-aqua">
        <div class="inner">
          <h3>S/ <?php echo number_format($totalVentasHoy, 2); ?></h3>
          <p>Ventas de hoy</p>
        </div>
        <div class="icon">
          <i class="fa fa-shopping-cart"></i>
        </div>
        <a href="Expedientes" class="small-box-footer">
          Ver ventas <i class="fa fa-arrow-circle-right"></i>
        </a>
      </div>
    </div>
  </div>

  <!-- Listas rápidas -->
  <div class="row">
    <!-- Ventas recientes -->
    <div class="col-md-6">
      <div class="box box-primary">
        <div class="box-header with-border">
          <h3 class="box-title">Ventas recientes</h3>
          <div class="box-tools pull-right">
            <a href="Expedientes" class="btn btn-box-tool" title="Ver todo"><i class="fa fa-external-link"></i></a>
          </div>
        </div>
        <div class="box-body table-responsive no-padding">
          <table class="table table-hover">
            <thead>
              <tr>
                <th>ID</th>
                <th>Cliente</th>
                <th>Programa/Tour</th>
                <th>Fecha venta</th>
                <th>Monto</th>
              </tr>
            </thead>
            <tbody>
              <!-- Placeholder: reemplaza con datos reales -->
              <?php
              // Obtener ventas recientes
              $ventasRecientes = $pdo->query("SELECT e.id, CONCAT(c.nombre, ' ', c.apellido) AS cliente, e.programa, e.fecha_venta, e.monto_total
                                              FROM expedientes e
                                              LEFT JOIN clientes c ON c.id = e.cliente_id
                                              ORDER BY e.fecha_venta DESC LIMIT 5");
              while ($venta = $ventasRecientes->fetch(PDO::FETCH_ASSOC)) {
                echo "<tr>
                        <td>{$venta['id']}</td>
                        <td>{$venta['cliente']}</td>
                        <td>{$venta['programa']}</td>
                        <td>{$venta['fecha_venta']}</td>
                        <td>S/ " . number_format($venta['monto_total'], 2) . "</td>
                      </tr>";
              }
              ?>
            </tbody>
          </table>
        </div>
        <div class="box-footer">
          <small class="text-muted">Fuente: Expedientes / Pagos</small>
        </div>
      </div>
    </div>

    <!-- Próximos tours (Programación) -->
    <div class="col-md-6">
      <div class="box box-success">
        <div class="box-header with-border">
          <h3 class="box-title">Próximos tours (48h)</h3>
          <div class="box-tools pull-right">
            <a href="Programacion" class="btn btn-box-tool" title="Programación completa"><i class="fa fa-calendar"></i></a>
          </div>
        </div>
        <div class="box-body table-responsive no-padding">
          <table class="table table-hover">
            <thead>
              <tr>
                <th>Fecha</th>
                <th>Expediente</th>
                <th>Título</th>
                <th>Estado</th>
              </tr>
            </thead>
            <tbody>
              <!-- Placeholder: reemplaza con datos reales -->
              <?php
              // Obtener próximos tours en 48 horas
              $proximosTours = $pdo->query("SELECT p.fecha, e.codigo, p.titulo, p.estado
                                            FROM programacion_operaciones p
                                            LEFT JOIN expedientes e ON e.id = p.expediente_id
                                            WHERE p.fecha BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 2 DAY)
                                            ORDER BY p.fecha ASC LIMIT 5");
              while ($tour = $proximosTours->fetch(PDO::FETCH_ASSOC)) {
                echo "<tr>
                        <td>{$tour['fecha']}</td>
                        <td>{$tour['codigo']}</td>
                        <td>{$tour['titulo']}</td>
                        <td><span class='label label-" . ($tour['estado'] === 'pendiente' ? 'warning' : 'success') . "'>{$tour['estado']}</span></td>
                      </tr>";
              }
              ?>
            </tbody>
          </table>
        </div>
        <div class="box-footer">
          <small class="text-muted">Fuente: Programación de Operaciones</small>
        </div>
      </div>
    </div>
  </div>

</section>
