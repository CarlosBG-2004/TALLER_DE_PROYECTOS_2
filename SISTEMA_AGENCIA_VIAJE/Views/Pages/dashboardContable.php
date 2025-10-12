<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once "Config/Database.php";

/* ===== Acceso: Contabilidad o Admin/Gerencia ===== */
$areas = array_map('strtolower', $_SESSION['areas'] ?? []);
$rol   = $_SESSION['user']['rol'] ?? '';
if (!in_array('contabilidad', $areas, true) && !in_array($rol, ['Admin','Gerencia'], true)) {
?>
<section class="content-header"><h1>Dashboard Contable <small>Acceso restringido</small></h1></section>
<section class="content">
  <div class="callout callout-danger">
    <h4>No autorizado</h4>
    <p>Necesitas permisos de <b>Contabilidad</b> o ser <b>Admin/Gerencia</b>.</p>
  </div>
</section>
<?php
  return;
}

/* ===== Utils ===== */
function mlabel($m){
  if ($m === 'PEN') return 'S/.';
  if ($m === 'USD') return '$';
  if ($m === 'EUR') return '€';
  return $m;
}

$pdo = Database::getConnection();

/* ===== Filtros ===== */
$from   = $_GET['from']   ?? date('Y-m-01');
$to     = $_GET['to']     ?? date('Y-m-t');
$moneda = $_GET['moneda'] ?? ''; // '', PEN, USD, EUR

$where = ["1=1"];
$args  = [];

if ($from !== '') { $where[] = "m.fecha >= ?"; $args[] = $from; }
if ($to   !== '') { $where[] = "m.fecha <= ?"; $args[] = $to; }
if (in_array($moneda, ['PEN','USD','EUR'], true)) { $where[] = "m.moneda = ?"; $args[] = $moneda; }

$wsql = 'WHERE '.implode(' AND ', $where);

/* ===== KPIs globales ===== */
$st = $pdo->prepare("
  SELECT
    SUM(CASE WHEN m.tipo='ingreso' THEN m.monto ELSE 0 END) AS ingresos,
    SUM(CASE WHEN m.tipo='gasto'   THEN m.monto ELSE 0 END) AS gastos,
    SUM(CASE WHEN m.tipo='deuda'   THEN m.monto ELSE 0 END) AS deudas
  FROM contabilidad_movimientos m
  $wsql
");
$st->execute($args);
$k = $st->fetch();
$ingresos = (float)($k['ingresos'] ?? 0);
$gastos   = (float)($k['gastos']   ?? 0);
$deudas   = (float)($k['deudas']   ?? 0);
$neto     = $ingresos - $gastos;

/* ===== Totales por moneda ===== */
$st = $pdo->prepare("
  SELECT m.moneda,
         SUM(CASE WHEN m.tipo='ingreso' THEN m.monto ELSE 0 END) AS ingresos,
         SUM(CASE WHEN m.tipo='gasto'   THEN m.monto ELSE 0 END) AS gastos,
         SUM(CASE WHEN m.tipo='deuda'   THEN m.monto ELSE 0 END) AS deudas
  FROM contabilidad_movimientos m
  $wsql
  GROUP BY m.moneda
  ORDER BY FIELD(m.moneda,'PEN','USD','EUR')
");
$st->execute($args);
$porMoneda = $st->fetchAll();

/* ===== Tendencia diaria (neto = ingresos - gastos) ===== */
$st = $pdo->prepare("
  SELECT m.fecha,
         SUM(CASE WHEN m.tipo='ingreso' THEN m.monto
                  WHEN m.tipo='gasto'   THEN -m.monto
                  ELSE 0 END) AS neto
  FROM contabilidad_movimientos m
  $wsql
  GROUP BY m.fecha
  ORDER BY m.fecha ASC
");
$st->execute($args);
$trend = $st->fetchAll();
$labelsTrend = array_map(fn($r)=>$r['fecha'], $trend);
$dataTrend   = array_map(fn($r)=>(float)$r['neto'], $trend);

/* ===== Top categorías (por monto absoluto combinado) ===== */
$st = $pdo->prepare("
  SELECT COALESCE(c.nombre,'(Sin categoría)') AS categoria,
         SUM(CASE WHEN m.tipo='ingreso' THEN m.monto ELSE 0 END) AS ingresos,
         SUM(CASE WHEN m.tipo='gasto'   THEN m.monto ELSE 0 END) AS gastos,
         SUM(CASE WHEN m.tipo='deuda'   THEN m.monto ELSE 0 END) AS deudas
  FROM contabilidad_movimientos m
  LEFT JOIN contabilidad_categorias c ON c.id = m.categoria_id
  $wsql
  GROUP BY categoria
");
$st->execute($args);
$catAll = $st->fetchAll();

/* Ordenamos por (ingresos+gastos+deudas) desc y tomamos top 8 */
usort($catAll, function($a,$b){
  $ta = (float)$a['ingresos'] + (float)$a['gastos'] + (float)$a['deudas'];
  $tb = (float)$b['ingresos'] + (float)$b['gastos'] + (float)$b['deudas'];
  return $tb <=> $ta;
});
$topCat = array_slice($catAll, 0, 8);

$labelsCat   = array_map(fn($r)=>$r['categoria'], $topCat);
$ingCatData  = array_map(fn($r)=>(float)$r['ingresos'], $topCat);
$gasCatData  = array_map(fn($r)=>(float)$r['gastos'],   $topCat);
$deuCatData  = array_map(fn($r)=>(float)$r['deudas'],   $topCat);

/* ===== Saldos de Cajas Chicas (histórico) ===== */
$st = $pdo->query("
  SELECT c.id, c.nombre, c.moneda, c.saldo_inicial
  FROM caja_chica c
  WHERE c.activo = 1
  ORDER BY c.nombre ASC
  LIMIT 10
");
$cajas = $st->fetchAll();

$saldoCajas = [];
if (!empty($cajas)) {
  $stMov = $pdo->prepare("
    SELECT
      SUM(CASE WHEN tipo='ingreso' THEN monto ELSE 0 END) AS ing,
      SUM(CASE WHEN tipo='gasto'   THEN monto ELSE 0 END) AS ga
    FROM caja_chica_movimientos
    WHERE caja_id = ?
  ");
  foreach ($cajas as $cx) {
    $stMov->execute([$cx['id']]);
    $mm = $stMov->fetch();
    $ing = (float)($mm['ing'] ?? 0);
    $ga  = (float)($mm['ga']  ?? 0);
    $saldoCajas[] = [
      'id'     => (int)$cx['id'],
      'nombre' => $cx['nombre'],
      'moneda' => $cx['moneda'],
      'saldo'  => (float)$cx['saldo_inicial'] + $ing - $ga
    ];
  }
}

/* ===== Movimientos recientes ===== */
$st = $pdo->prepare("
  SELECT m.id, m.fecha, m.tipo, COALESCE(c.nombre,'—') AS categoria,
         m.descripcion, m.monto, m.moneda
  FROM contabilidad_movimientos m
  LEFT JOIN contabilidad_categorias c ON c.id = m.categoria_id
  $wsql
  ORDER BY m.fecha DESC, m.id DESC
  LIMIT 12
");
$st->execute($args);
$recientes = $st->fetchAll();

?>
<section class="content-header">
  <h1>Dashboard Contable <small>visión general</small></h1>
  <ol class="breadcrumb">
    <li><a href="Dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
    <li class="active">Dashboard Contable</li>
  </ol>
</section>

<section class="content">

  <!-- Filtros -->
  <div class="box box-default">
    <div class="box-header with-border">
      <h3 class="box-title"><i class="fa fa-filter"></i> Filtros</h3>
      <div class="box-tools">
        <a class="btn btn-sm btn-primary" href="ContabilidadMovimientos"><i class="fa fa-list"></i> Movimientos</a>
        <a class="btn btn-sm btn-success" href="ReportesContables"><i class="fa fa-line-chart"></i> Reportes</a>
      </div>
    </div>
    <div class="box-body">
      <form class="form-inline" method="get" action="DashboardContable" style="margin:0;">
        <label>Desde</label>
        <input type="date" class="form-control" name="from" value="<?= htmlspecialchars($from) ?>">
        <label>Hasta</label>
        <input type="date" class="form-control" name="to" value="<?= htmlspecialchars($to) ?>">
        <label>Moneda</label>
        <select name="moneda" class="form-control">
          <option value="">Todas</option>
          <option value="PEN" <?= $moneda==='PEN'?'selected':'' ?>>PEN</option>
          <option value="USD" <?= $moneda==='USD'?'selected':'' ?>>USD</option>
          <option value="EUR" <?= $moneda==='EUR'?'selected':'' ?>>EUR</option>
        </select>
        <button class="btn btn-default"><i class="fa fa-search"></i> Aplicar</button>
      </form>
    </div>
  </div>

  <!-- KPIs -->
  <div class="row">
    <div class="col-sm-3">
      <div class="small-box bg-green">
        <div class="inner">
          <h3><?= number_format($ingresos,2) ?></h3>
          <p>Ingresos (rango<?= $moneda ? ' - '.$moneda : '' ?>)</p>
        </div>
        <div class="icon"><i class="fa fa-arrow-circle-up"></i></div>
      </div>
    </div>
    <div class="col-sm-3">
      <div class="small-box bg-red">
        <div class="inner">
          <h3><?= number_format($gastos,2) ?></h3>
          <p>Gastos (rango<?= $moneda ? ' - '.$moneda : '' ?>)</p>
        </div>
        <div class="icon"><i class="fa fa-arrow-circle-down"></i></div>
      </div>
    </div>
    <div class="col-sm-3">
      <div class="small-box bg-yellow">
        <div class="inner">
          <h3><?= number_format($deudas,2) ?></h3>
          <p>Deudas (rango<?= $moneda ? ' - '.$moneda : '' ?>)</p>
        </div>
        <div class="icon"><i class="fa fa-exclamation-triangle"></i></div>
      </div>
    </div>
    <div class="col-sm-3">
      <div class="small-box bg-aqua">
        <div class="inner">
          <h3><?= number_format($neto,2) ?></h3>
          <p>Neto (Ingresos − Gastos)</p>
        </div>
        <div class="icon"><i class="fa fa-balance-scale"></i></div>
      </div>
    </div>
  </div>

  <!-- Totales por moneda -->
  <div class="box box-solid">
    <div class="box-header with-border">
      <h3 class="box-title"><i class="fa fa-money"></i> Totales por moneda</h3>
    </div>
    <div class="box-body table-responsive no-padding">
      <table class="table table-hover">
        <thead>
          <tr>
            <th>Moneda</th>
            <th>Ingresos</th>
            <th>Gastos</th>
            <th>Deudas</th>
            <th>Neto</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($porMoneda)): ?>
            <tr><td colspan="5" class="text-center text-muted">Sin datos</td></tr>
          <?php else: foreach ($porMoneda as $r):
            $net = (float)$r['ingresos'] - (float)$r['gastos'];
          ?>
          <tr>
            <td><b><?= htmlspecialchars($r['moneda']) ?></b></td>
            <td><?= mlabel($r['moneda']).' '.number_format((float)$r['ingresos'],2) ?></td>
            <td><?= mlabel($r['moneda']).' '.number_format((float)$r['gastos'],2) ?></td>
            <td><?= mlabel($r['moneda']).' '.number_format((float)$r['deudas'],2) ?></td>
            <td><b><?= mlabel($r['moneda']).' '.number_format($net,2) ?></b></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="row">
    <!-- Tendencia -->
    <div class="col-md-7">
      <div class="box box-primary">
        <div class="box-header with-border">
          <h3 class="box-title"><i class="fa fa-line-chart"></i> Neto diario (ingresos − gastos)</h3>
        </div>
        <div class="box-body">
          <canvas id="chartTrend" height="130"></canvas>
          <p class="help-block">Las deudas no se incluyen en la curva; sí en KPIs y totales.</p>
        </div>
      </div>
    </div>

    <!-- Top categorías -->
    <div class="col-md-5">
      <div class="box box-warning">
        <div class="box-header with-border">
          <h3 class="box-title"><i class="fa fa-bar-chart"></i> Top categorías (rango)</h3>
        </div>
        <div class="box-body">
          <canvas id="chartCategorias" height="130"></canvas>
          <?php if (empty($topCat)): ?>
            <p class="text-muted">Sin datos.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Cajas chicas -->
  <div class="box box-success">
    <div class="box-header with-border">
      <h3 class="box-title"><i class="fa fa-briefcase"></i> Cajas chicas (saldo actual)</h3>
    </div>
    <div class="box-body table-responsive no-padding">
      <table class="table table-hover">
        <thead>
          <tr>
            <th>Nombre</th>
            <th>Moneda</th>
            <th>Saldo</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($saldoCajas)): ?>
            <tr><td colspan="4" class="text-center text-muted">No hay cajas activas</td></tr>
          <?php else: foreach ($saldoCajas as $cx): ?>
          <tr>
            <td><?= htmlspecialchars($cx['nombre']) ?></td>
            <td><?= htmlspecialchars($cx['moneda']) ?></td>
            <td><b><?= mlabel($cx['moneda']).' '.number_format($cx['saldo'],2) ?></b></td>
            <td>
              <a class="btn btn-xs btn-default" href="CajaChicaMovimientos?caja_id=<?= (int)$cx['id'] ?>">
                <i class="fa fa-list"></i> Ver movimientos
              </a>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Movimientos recientes -->
  <div class="box box-info">
    <div class="box-header with-border">
      <h3 class="box-title"><i class="fa fa-clock-o"></i> Movimientos recientes</h3>
    </div>
    <div class="box-body table-responsive no-padding">
      <table class="table table-hover">
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Tipo</th>
            <th>Categoría</th>
            <th>Descripción</th>
            <th>Monto</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($recientes)): ?>
            <tr><td colspan="5" class="text-center text-muted">Sin registros</td></tr>
          <?php else: foreach ($recientes as $m): ?>
          <tr>
            <td><?= htmlspecialchars($m['fecha']) ?></td>
            <td>
              <?php if ($m['tipo']==='ingreso'): ?>
                <span class="label label-success">Ingreso</span>
              <?php elseif ($m['tipo']==='gasto'): ?>
                <span class="label label-danger">Gasto</span>
              <?php else: ?>
                <span class="label label-warning">Deuda</span>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($m['categoria']) ?></td>
            <td><?= $m['descripcion'] ? htmlspecialchars($m['descripcion']) : '—' ?></td>
            <td><b><?= mlabel($m['moneda']).' '.number_format((float)$m['monto'],2) ?></b></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</section>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function(){
  // Trend
  var ctx1 = document.getElementById('chartTrend');
  if (ctx1) {
    new Chart(ctx1, {
      type: 'line',
      data: {
        labels: <?= json_encode(array_values($labelsTrend)) ?>,
        datasets: [{
          label: 'Neto diario',
          data: <?= json_encode(array_values($dataTrend)) ?>,
          fill: false
        }]
      },
      options: {
        responsive: true,
        plugins: { legend: { display: true } },
        scales: { y: { beginAtZero: true } }
      }
    });
  }

  // Categorías
  var ctx2 = document.getElementById('chartCategorias');
  if (ctx2) {
    new Chart(ctx2, {
      type: 'bar',
      data: {
        labels: <?= json_encode(array_values($labelsCat)) ?>,
        datasets: [
          { label: 'Ingresos', data: <?= json_encode(array_values($ingCatData)) ?> },
          { label: 'Gastos',   data: <?= json_encode(array_values($gasCatData)) ?> },
          { label: 'Deudas',   data: <?= json_encode(array_values($deuCatData)) ?> }
        ]
      },
      options: {
        responsive: true,
        plugins: { legend: { display: true } },
        scales: { x: { stacked: false }, y: { beginAtZero: true } }
      }
    });
  }
})();
</script>
