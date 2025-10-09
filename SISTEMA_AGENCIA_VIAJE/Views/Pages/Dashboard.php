<!-- Views/Pages/Dashboard.php -->
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
          <h3>S/ <span id="kpiIngresos">0</span></h3>
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
          <h3>S/ <span id="kpiGastos">0</span></h3>
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
          <h3>S/ <span id="kpiDeudas">0</span></h3>
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
          <h3><span id="kpiVentasHoy">0</span></h3>
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
              <tr>
                <td>EXP-001</td>
                <td>John Doe</td>
                <td>Machu Picchu Full Day</td>
                <td>2025-10-08</td>
                <td>S/ 1,200.00</td>
              </tr>
              <tr>
                <td>EXP-002</td>
                <td>Jane Smith</td>
                <td>Valle Sagrado</td>
                <td>2025-10-08</td>
                <td>S/ 800.00</td>
              </tr>
              <tr>
                <td>EXP-003</td>
                <td>Pedro Pérez</td>
                <td>Montaña de Colores</td>
                <td>2025-10-07</td>
                <td>S/ 500.00</td>
              </tr>
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
              <tr>
                <td>2025-10-09</td>
                <td>EXP-001</td>
                <td>Salida Machu Picchu</td>
                <td><span class="label label-success">Confirmado</span></td>
              </tr>
              <tr>
                <td>2025-10-09</td>
                <td>EXP-004</td>
                <td>City Tour Cusco</td>
                <td><span class="label label-warning">Pendiente</span></td>
              </tr>
              <tr>
                <td>2025-10-10</td>
                <td>EXP-002</td>
                <td>Valle Sagrado</td>
                <td><span class="label label-info">Programado</span></td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="box-footer">
          <small class="text-muted">Fuente: Programación de Operaciones</small>
        </div>
      </div>
    </div>
  </div>

  <!-- Paneles adicionales (opcionales) -->
  <div class="row">
    <div class="col-md-6">
      <div class="box box-warning">
        <div class="box-header with-border">
          <h3 class="box-title">Caja chica (últimos movimientos)</h3>
          <div class="box-tools pull-right">
            <a href="CajaChicaMovimientos" class="btn btn-box-tool" title="Ver movimientos"><i class="fa fa-list-ul"></i></a>
          </div>
        </div>
        <div class="box-body">
          <ul class="list-unstyled">
            <li><i class="fa fa-plus text-green"></i> Ingreso S/ 200.00 - Reembolso proveedor</li>
            <li><i class="fa fa-minus text-red"></i> Egreso S/ 80.00 - Combustible</li>
            <li><i class="fa fa-minus text-red"></i> Egreso S/ 45.00 - Útiles</li>
          </ul>
        </div>
        <div class="box-footer">
          <small class="text-muted">Fuente: CajaChica / CajaChicaMovimientos</small>
        </div>
      </div>
    </div>

    <div class="col-md-6">
      <div class="box box-info">
        <div class="box-header with-border">
          <h3 class="box-title">Campañas activas</h3>
          <div class="box-tools pull-right">
            <a href="Campanas" class="btn btn-box-tool" title="Ver campañas"><i class="fa fa-envelope"></i></a>
          </div>
        </div>
        <div class="box-body">
          <ul class="list-unstyled">
            <li><i class="fa fa-envelope-o"></i> Promo Feriados Octubre — <span class="text-green">Programado</span></li>
            <li><i class="fa fa-envelope-o"></i> Recordatorio Tours — <span class="text-yellow">Pendiente</span></li>
            <li><i class="fa fa-envelope-o"></i> Aniversarios — <span class="text-blue">Enviado</span></li>
          </ul>
        </div>
        <div class="box-footer">
          <small class="text-muted">Fuente: Plantillas / Campañas / Destinatarios</small>
        </div>
      </div>
    </div>
  </div>

</section>

<script>
  // Placeholders para KPIs (puedes reemplazar con valores desde PHP)
  document.getElementById('kpiIngresos').textContent = '0';
  document.getElementById('kpiGastos').textContent = '0';
  document.getElementById('kpiDeudas').textContent = '0';
  document.getElementById('kpiVentasHoy').textContent = '0';
</script>
