<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once "Config/Database.php";

// ---- Autorización básica: sólo Admin o Gerencia ----
$rol = $_SESSION['user']['rol'] ?? '';
if (!in_array($rol, ['Admin','Gerencia'], true)) {
?>
<section class="content-header">
  <h1>Gerencia <small>Acceso restringido</small></h1>
</section>
<section class="content">
  <div class="callout callout-danger">
    <h4>No autorizado</h4>
    <p>Este panel es exclusivo para <strong>Gerencia</strong> y <strong>Administradores</strong>.</p>
  </div>
</section>
<?php
  return;
}

// ---- Conexión y fechas de trabajo ----
$pdo = Database::getConnection();
$hoy         = date('Y-m-d');
$inicioMes   = date('Y-m-01');
$finMes      = date('Y-m-t');
$hoyMas7     = date('Y-m-d', strtotime('+7 days'));
$hoyMenos30  = date('Y-m-d', strtotime('-30 days'));

// Utilidad: formato moneda simple (PEN por defecto)
function fmt($n){ return number_format((float)$n, 2, '.', ','); }

// ---------------- KPIs del mes ----------------
$ingresos = 0; $gastos = 0; $deudas = 0; $ventasMes = 0;

// Ingresos y gastos del mes (contabilidad_movimientos)
$stmt = $pdo->prepare("
  SELECT tipo, SUM(monto) total
  FROM contabilidad_movimientos
  WHERE fecha BETWEEN ? AND ?
  GROUP BY tipo
");
$stmt->execute([$inicioMes, $finMes]);
foreach ($stmt as $r) {
  if ($r['tipo'] === 'ingreso') $ingresos = (float)$r['total'];
  if ($r['tipo'] === 'egreso')  $gastos   = (float)$r['total'];
}

// Ventas del mes (expedientes)
$stmt = $pdo->prepare("
  SELECT COUNT(*) c FROM expedientes
  WHERE fecha_venta BETWEEN ? AND ?
");
$stmt->execute([$inicioMes, $finMes]);
$ventasMes = (int)$stmt->fetchColumn();

// Deudas por cobrar (saldo ventas)
$stmt = $pdo->query("
  SELECT SUM(saldo) AS total_deuda FROM (
    SELECT e.id,
           e.monto_total - IFNULL(p.total_pagado,0) AS saldo
    FROM expedientes e
    LEFT JOIN (
      SELECT expediente_id, SUM(monto) total_pagado
      FROM pagos
      GROUP BY expediente_id
    ) p ON p.expediente_id = e.id
  ) t
  WHERE t.saldo > 0
");
$deudas = (float)$stmt->fetchColumn();
$margen = $ingresos - $gastos;

// ---------------- Listas: ventas recientes (7) ----------------
$ventasRecientes = [];
$stmt = $pdo->prepare("
  SELECT e.id, e.codigo, e.fecha_venta, e.cliente_id, e.tour, e.programa, e.monto_total,
         CONCAT(c.nombre,' ',c.apellido) AS cliente
  FROM expedientes e
  LEFT JOIN clientes c ON c.id = e.cliente_id
  ORDER BY e.fecha_venta DESC, e.id DESC
  LIMIT 7
");
$stmt->execute();
$ventasRecientes = $stmt->fetchAll();

// ---------------- Próximos tours (Programación 7 días) ----------------
$proximosTours = [];
$stmt = $pdo->prepare("
  SELECT p.fecha, p.titulo, p.estado, e.codigo AS expediente
  FROM programacion_operaciones p
  LEFT JOIN expedientes e ON e.id = p.expediente_id
  WHERE p.fecha BETWEEN ? AND ?
  ORDER BY p.fecha ASC, p.id ASC
  LIMIT 10
");
$stmt->execute([$hoy, $hoyMas7]);
$proximosTours = $stmt->fetchAll();

// ---------------- Servicios pendientes (no reservado o saldo>0) ----------------
$serviciosPend = 0;
$stmt = $pdo->query("
  SELECT COUNT(*) FROM servicios_operaciones
  WHERE reservado = 0 OR saldo > 0
");
$serviciosPend = (int)$stmt->fetchColumn();

// ---------------- Top tours últimos 30 días ----------------
$topTours = [];
$stmt = $pdo->prepare("
  SELECT tour, COUNT(*) ventas, SUM(monto_total) total
  FROM expedientes
  WHERE fecha_venta >= ?
  GROUP BY tour
  ORDER BY ventas DESC, total DESC
  LIMIT 5
");
$stmt->execute([$hoyMenos30]);
$topTours = $stmt->fetchAll();

// ---------------- Top agencias últimos 30 días ----------------
$topAgencias = [];
$stmt = $pdo->prepare("
  SELECT IFNULL(a.nombre,'(Directo)') agencia, COUNT(*) ventas, SUM(e.monto_total) total
  FROM expedientes e
  LEFT JOIN agencias a ON a.id = e.agencia_id
  WHERE e.fecha_venta >= ?
  GROUP BY a.id
  ORDER BY ventas DESC, total DESC
  LIMIT 5
");
$stmt->execute([$hoyMenos30]);
$topAgencias = $stmt->fetchAll();

// ---------------- Caja chica (saldos actuales) ----------------
$cajas = [];
$stmt = $pdo->query("
  SELECT c.id, c.nombre,
         (c.saldo_inicial + IFNULL(SUM(
            CASE m.tipo WHEN 'ingreso' THEN m.monto ELSE -m.monto END
         ),0)) AS saldo_actual
  FROM caja_chica c
  LEFT JOIN caja_chica_movimientos m ON m.caja_id = c.id
  WHERE c.estado='abierta'
  GROUP BY c.id, c.nombre, c.saldo_inicial
  ORDER BY c.id DESC
");
$cajas = $stmt->fetchAll();

// ---------------- Auditoría reciente ----------------
$auditoria = [];
$stmt = $pdo->query("
  SELECT a.accion, a.tabla, a.registro_id, a.creado_en,
         CONCAT(u.nombre,' ',u.apellido) AS usuario
  FROM auditoria a
  LEFT JOIN usuarios u ON u.id = a.usuario_id
  ORDER BY a.id DESC
  LIMIT 8
");
$auditoria = $stmt->fetchAll();
?>

<section class="content-header">
  <h1>Panel Gerencial <small>Control integral</small></h1>
  <ol class="breadcrumb">
    <li><a href="Dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
    <li class="active">Gerencia</li>
  </ol>
</section>

<section class="content">

  <!-- KPIs -->
  <div class="row">
    <div class="col-lg-3 col-xs-6">
      <div class="small-box bg-green">
        <div class="inner">
          <h3>S/ <?= fmt($ingresos) ?></h3>
          <p>Ingresos (mes)</p>
        </div>
        <div class="icon"><i class="fa fa-arrow-up"></i></div>
        <a href="ContabilidadMovimientos" class="small-box-footer">Ver detalle <i class="fa fa-arrow-circle-right"></i></a>
      </div>
    </div>

    <div class="col-lg-3 col-xs-6">
      <div class="small-box bg-red">
        <div class="inner">
          <h3>S/ <?= fmt($gastos) ?></h3>
          <p>Gastos (mes)</p>
        </div>
        <div class="icon"><i class="fa fa-arrow-down"></i></div>
        <a href="ContabilidadMovimientos" class="small-box-footer">Ver detalle <i class="fa fa-arrow-circle-right"></i></a>
      </div>
    </div>

    <div class="col-lg-3 col-xs-6">
      <div class="small-box bg-yellow">
        <div class="inner">
          <h3>S/ <?= fmt($deudas) ?></h3>
          <p>Deudas por cobrar</p>
        </div>
        <div class="icon"><i class="fa fa-exclamation-triangle"></i></div>
        <a href="ReportesContables" class="small-box-footer">Reporte <i class="fa fa-arrow-circle-right"></i></a>
      </div>
    </div>

    <div class="col-lg-3 col-xs-6">
      <div class="small-box bg-aqua">
        <div class="inner">
          <h3><?= (int)$ventasMes ?></h3>
          <p>Ventas (mes)</p>
        </div>
        <div class="icon"><i class="fa fa-shopping-cart"></i></div>
        <a href="Expedientes" class="small-box-footer">Ir a ventas <i class="fa fa-arrow-circle-right"></i></a>
      </div>
    </div>
  </div>

  <!-- Fila principal -->
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
                <th>Código</th>
                <th>Cliente</th>
                <th>Tour</th>
                <th>Fecha</th>
                <th class="text-right">Monto</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($ventasRecientes)): ?>
                <tr><td colspan="5" class="text-center text-muted">Sin registros</td></tr>
              <?php else: foreach ($ventasRecientes as $v): ?>
                <tr>
                  <td><?= htmlspecialchars($v['codigo'] ?: 'EXP-'.$v['id']) ?></td>
                  <td><?= htmlspecialchars($v['cliente'] ?: '—') ?></td>
                  <td><?= htmlspecialchars($v['tour'] ?: $v['programa']) ?></td>
                  <td><?= htmlspecialchars($v['fecha_venta']) ?></td>
                  <td class="text-right">S/ <?= fmt($v['monto_total']) ?></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
        <div class="box-footer"><small class="text-muted">Fuente: Expedientes</small></div>
      </div>
    </div>

    <!-- Próximos tours -->
    <div class="col-md-6">
      <div class="box box-success">
        <div class="box-header with-border">
          <h3 class="box-title">Próximos tours (7 días)</h3>
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
              <?php if (empty($proximosTours)): ?>
                <tr><td colspan="4" class="text-center text-muted">Sin actividades programadas</td></tr>
              <?php else: foreach ($proximosTours as $p): ?>
                <tr>
                  <td><?= htmlspecialchars($p['fecha']) ?></td>
                  <td><?= htmlspecialchars($p['expediente'] ?: '—') ?></td>
                  <td><?= htmlspecialchars($p['titulo']) ?></td>
                  <td>
                    <?php
                      $label = 'label-default';
                      if ($p['estado']==='confirmado') $label='label-success';
                      elseif ($p['estado']==='pendiente') $label='label-warning';
                      elseif ($p['estado']==='realizado') $label='label-primary';
                      elseif ($p['estado']==='cancelado') $label='label-danger';
                    ?>
                    <span class="label <?= $label ?>"><?= htmlspecialchars($p['estado']) ?></span>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
        <div class="box-footer"><small class="text-muted">Fuente: Programación de Operaciones</small></div>
      </div>
    </div>
  </div>

  <!-- Topes y servicios -->
  <div class="row">
    <!-- Top tours -->
    <div class="col-md-6">
      <div class="box box-warning">
        <div class="box-header with-border">
          <h3 class="box-title">Top tours (30 días)</h3>
          <div class="box-tools pull-right"><i class="fa fa-line-chart"></i></div>
        </div>
        <div class="box-body table-responsive no-padding">
          <table class="table table-striped">
            <thead><tr><th>Tour</th><th>Ventas</th><th class="text-right">Total</th></tr></thead>
            <tbody>
              <?php if (empty($topTours)): ?>
                <tr><td colspan="3" class="text-center text-muted">Sin datos</td></tr>
              <?php else: foreach ($topTours as $t): ?>
                <tr>
                  <td><?= htmlspecialchars($t['tour']) ?></td>
                  <td><?= (int)$t['ventas'] ?></td>
                  <td class="text-right">S/ <?= fmt($t['total']) ?></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Top agencias -->
    <div class="col-md-6">
      <div class="box box-info">
        <div class="box-header with-border">
          <h3 class="box-title">Top agencias (30 días)</h3>
          <div class="box-tools pull-right"><i class="fa fa-building"></i></div>
        </div>
        <div class="box-body table-responsive no-padding">
          <table class="table table-striped">
            <thead><tr><th>Agencia</th><th>Ventas</th><th class="text-right">Total</th></tr></thead>
            <tbody>
              <?php if (empty($topAgencias)): ?>
                <tr><td colspan="3" class="text-center text-muted">Sin datos</td></tr>
              <?php else: foreach ($topAgencias as $a): ?>
                <tr>
                  <td><?= htmlspecialchars($a['agencia']) ?></td>
                  <td><?= (int)$a['ventas'] ?></td>
                  <td class="text-right">S/ <?= fmt($a['total']) ?></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Servicios pendientes + Cajas + Auditoría -->
  <div class="row">
    <!-- Servicios pendientes -->
    <div class="col-md-4">
      <div class="box box-danger">
        <div class="box-header with-border">
          <h3 class="box-title">Servicios pendientes</h3>
          <div class="box-tools pull-right"><a href="ServiciosOperaciones" class="btn btn-box-tool" title="Ir"><i class="fa fa-external-link"></i></a></div>
        </div>
        <div class="box-body">
          <p class="lead">Total: <strong><?= (int)$serviciosPend ?></strong></p>
          <p class="text-muted">Incluye servicios no reservados o con saldo por pagar.</p>
        </div>
      </div>
    </div>

    <!-- Cajas abiertas -->
    <div class="col-md-4">
      <div class="box box-primary">
        <div class="box-header with-border"><h3 class="box-title">Caja chica (abiertas)</h3></div>
        <div class="box-body">
          <?php if (empty($cajas)): ?>
            <p class="text-muted">No hay cajas abiertas.</p>
          <?php else: ?>
            <ul class="list-unstyled">
              <?php foreach ($cajas as $c): ?>
                <li>
                  <i class="fa fa-briefcase"></i>
                  <strong><?= htmlspecialchars($c['nombre']) ?></strong> —
                  S/ <?= fmt($c['saldo_actual']) ?>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
        <div class="box-footer"><a href="CajaChica" class="btn btn-xs btn-default">Ver cajas</a></div>
      </div>
    </div>

    <!-- Auditoría -->
    <div class="col-md-4">
      <div class="box box-default">
        <div class="box-header with-border"><h3 class="box-title">Actividad reciente</h3></div>
        <div class="box-body" style="max-height:230px; overflow:auto;">
          <?php if (empty($auditoria)): ?>
            <p class="text-muted">Sin actividad reciente.</p>
          <?php else: ?>
            <ul class="list-unstyled">
              <?php foreach ($auditoria as $au): ?>
                <li>
                  <i class="fa fa-clock-o"></i>
                  <strong><?= htmlspecialchars($au['accion']) ?></strong>
                  en <em><?= htmlspecialchars($au['tabla']) ?></em>
                  (#<?= (int)$au['registro_id'] ?>)
                  — <?= htmlspecialchars($au['usuario'] ?: 'Sistema') ?>
                  <br><small class="text-muted"><?= htmlspecialchars($au['creado_en']) ?></small>
                </li>
                <hr style="margin:6px 0;">
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

</section>
