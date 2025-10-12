<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once "Config/Database.php";

/* ======= Control de acceso ======= */
$areas = array_map('strtolower', $_SESSION['areas'] ?? []);
$rol   = $_SESSION['user']['rol'] ?? '';
if (!in_array('contabilidad', $areas, true) && !in_array($rol, ['Admin','Gerencia'], true)) {
?>
<section class="content-header"><h1>Reportes contables <small>Acceso restringido</small></h1></section>
<section class="content">
  <div class="callout callout-danger">
    <h4>No autorizado</h4>
    <p>Necesitas permisos de <b>Contabilidad</b> o ser <b>Admin/Gerencia</b>.</p>
  </div>
</section>
<?php
  return;
}

/* ======= Utilidades ======= */
function mlabel($m){
  if ($m === 'PEN') return 'S/.';
  if ($m === 'USD') return '$';
  if ($m === 'EUR') return '€';
  return $m;
}

/* ======= DB y compatibilidad mínima ======= */
$pdo = Database::getConnection();

/* Crea tablas si no existieran (compatibles con tus páginas anteriores) */
$pdo->exec("
  CREATE TABLE IF NOT EXISTS contabilidad_categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE
  ) ENGINE=InnoDB
");

$pdo->exec("
  CREATE TABLE IF NOT EXISTS contabilidad_movimientos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fecha DATE NOT NULL,
    tipo ENUM('ingreso','gasto','deuda') NOT NULL,
    categoria_id INT NULL,
    descripcion TEXT NULL,
    monto DECIMAL(12,2) NOT NULL,
    moneda ENUM('PEN','USD','EUR') NOT NULL DEFAULT 'PEN',
    metodo ENUM('efectivo','tarjeta','transferencia','otro') NOT NULL DEFAULT 'efectivo',
    comprobante_url VARCHAR(255) NULL,
    archivo_id INT NULL,
    creado_en TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_cm_cat FOREIGN KEY (categoria_id) REFERENCES contabilidad_categorias(id) ON DELETE SET NULL
  ) ENGINE=InnoDB
");

/* ======= Filtros ======= */
$from   = $_GET['from']   ?? date('Y-m-01');
$to     = $_GET['to']     ?? date('Y-m-t');
$moneda = $_GET['moneda'] ?? '';  // '', PEN, USD, EUR
$tipo   = $_GET['tipo']   ?? '';  // '', ingreso, gasto, deuda
$q      = trim($_GET['q'] ?? ''); // búsqueda libre en descripción

$where = ["1=1"];
$args  = [];

if ($from !== '') { $where[] = "m.fecha >= ?"; $args[] = $from; }
if ($to   !== '') { $where[] = "m.fecha <= ?"; $args[] = $to; }
if ($moneda === 'PEN' || $moneda === 'USD' || $moneda === 'EUR') { $where[] = "m.moneda = ?"; $args[] = $moneda; }
if ($tipo === 'ingreso' || $tipo === 'gasto' || $tipo === 'deuda') { $where[] = "m.tipo = ?"; $args[] = $tipo; }
if ($q !== '') { $where[] = "(m.descripcion LIKE ?)"; $args[] = '%'.$q.'%'; }

$wsql = 'WHERE '.implode(' AND ', $where);

/* ======= Export CSV ======= */
if (isset($_GET['export']) && $_GET['export'] === 'movs') {
  $st = $pdo->prepare("
    SELECT m.id, m.fecha, m.tipo, COALESCE(c.nombre,'—') AS categoria, m.descripcion,
           m.monto, m.moneda, m.metodo
    FROM contabilidad_movimientos m
    LEFT JOIN contabilidad_categorias c ON c.id = m.categoria_id
    $wsql
    ORDER BY m.fecha ASC, m.id ASC
  ");
  $st->execute($args);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename=movimientos_contables.csv');
  $out = fopen('php://output', 'w');
  fputcsv($out, array_keys($rows[0] ?? [
    'id'=>'','fecha'=>'','tipo'=>'','categoria'=>'','descripcion'=>'','monto'=>'','moneda'=>'','metodo'=>''
  ]));
  foreach ($rows as $r) { fputcsv($out, $r); }
  fclose($out);
  exit;
}

if (isset($_GET['export']) && $_GET['export'] === 'cat') {
  $st = $pdo->prepare("
    SELECT COALESCE(c.nombre,'(Sin categoría)') AS categoria, m.moneda, m.tipo, SUM(m.monto) AS total
    FROM contabilidad_movimientos m
    LEFT JOIN contabilidad_categorias c ON c.id = m.categoria_id
    $wsql
    GROUP BY categoria, m.moneda, m.tipo
    ORDER BY categoria, m.moneda, m.tipo
  ");
  $st->execute($args);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename=desglose_categorias.csv');
  $out = fopen('php://output', 'w');
  fputcsv($out, ['categoria','moneda','tipo','total']);
  foreach ($rows as $r) { fputcsv($out, $r); }
  fclose($out);
  exit;
}

/* ======= KPIs por moneda ======= */
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
$kpis = $st->fetchAll();

/* ======= Desglose por categoría ======= */
$st = $pdo->prepare("
  SELECT COALESCE(c.nombre,'(Sin categoría)') AS categoria,
         m.moneda,
         SUM(CASE WHEN m.tipo='ingreso' THEN m.monto ELSE 0 END) AS ingresos,
         SUM(CASE WHEN m.tipo='gasto'   THEN m.monto ELSE 0 END) AS gastos,
         SUM(CASE WHEN m.tipo='deuda'   THEN m.monto ELSE 0 END) AS deudas
  FROM contabilidad_movimientos m
  LEFT JOIN contabilidad_categorias c ON c.id = m.categoria_id
  $wsql
  GROUP BY categoria, m.moneda
  ORDER BY categoria ASC, FIELD(m.moneda,'PEN','USD','EUR')
");
$st->execute($args);
$byCat = $st->fetchAll();

/* ======= Tendencia diaria (neto por día) ======= */
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

/* ======= Listado de movimientos (máx 500) ======= */
$st = $pdo->prepare("
  SELECT m.id, m.fecha, m.tipo, COALESCE(c.nombre,'—') AS categoria,
         m.descripcion, m.monto, m.moneda, m.metodo, m.comprobante_url
  FROM contabilidad_movimientos m
  LEFT JOIN contabilidad_categorias c ON c.id = m.categoria_id
  $wsql
  ORDER BY m.fecha DESC, m.id DESC
  LIMIT 500
");
$st->execute($args);
$movs = $st->fetchAll();

/* ======= Datos para la gráfica ======= */
$labels = array_map(fn($r)=>$r['fecha'], $trend);
$series = array_map(fn($r)=>(float)$r['neto'], $trend);
?>
<section class="content-header">
  <h1>Reportes contables <small>Ingresos, gastos y deudas</small></h1>
  <ol class="breadcrumb">
    <li><a href="Dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
    <li class="active">Reportes contables</li>
  </ol>
</section>

<section class="content">

  <!-- Filtros -->
  <div class="box box-default">
    <div class="box-header with-border">
      <h3 class="box-title"><i class="fa fa-filter"></i> Filtros</h3>
    </div>
    <div class="box-body">
      <form class="form-inline" method="get" action="ReportesContables" style="margin:0;">
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

        <label>Tipo</label>
        <select name="tipo" class="form-control">
          <option value="">Todos</option>
          <option value="ingreso" <?= $tipo==='ingreso'?'selected':'' ?>>Ingreso</option>
          <option value="gasto"   <?= $tipo==='gasto'  ?'selected':'' ?>>Gasto</option>
          <option value="deuda"   <?= $tipo==='deuda'  ?'selected':'' ?>>Deuda</option>
        </select>

        <input type="text" name="q" class="form-control" placeholder="Buscar descripción…" value="<?= htmlspecialchars($q) ?>">
        <button class="btn btn-default"><i class="fa fa-search"></i> Aplicar</button>

        <div class="pull-right">
          <a class="btn btn-success" href="ReportesContables?<?= http_build_query(array_merge($_GET, ['export'=>'movs'])) ?>">
            <i class="fa fa-file-excel-o"></i> Exportar movimientos (CSV)
          </a>
          <a class="btn btn-success" href="ReportesContables?<?= http_build_query(array_merge($_GET, ['export'=>'cat'])) ?>">
            <i class="fa fa-file-excel-o"></i> Exportar categorías (CSV)
          </a>
        </div>
      </form>
    </div>
  </div>

  <!-- KPIs por moneda -->
  <div class="row">
    <?php if (empty($kpis)): ?>
      <div class="col-sm-12"><p class="text-muted">No hay datos en el rango seleccionado.</p></div>
    <?php else:
      foreach ($kpis as $k):
        $ing = (float)$k['ingresos'];
        $gas = (float)$k['gastos'];
        $deu = (float)$k['deudas'];
        $net = $ing - $gas;
    ?>
    <div class="col-sm-3">
      <div class="box box-solid">
        <div class="box-header with-border">
          <h3 class="box-title">Moneda: <b><?= htmlspecialchars($k['moneda']) ?></b></h3>
        </div>
        <div class="box-body">
          <p><b>Ingresos:</b> <?= mlabel($k['moneda']).' '.number_format($ing,2) ?></p>
          <p><b>Gastos:</b> <?= mlabel($k['moneda']).' '.number_format($gas,2) ?></p>
          <p><b>Deudas:</b> <?= mlabel($k['moneda']).' '.number_format($deu,2) ?></p>
          <hr>
          <p><b>Neto (Ingresos − Gastos):</b> <?= mlabel($k['moneda']).' '.number_format($net,2) ?></p>
        </div>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </div>

  <!-- Gráfica tendencia (neto diario) -->
  <div class="box box-primary">
    <div class="box-header with-border">
      <h3 class="box-title"><i class="fa fa-line-chart"></i> Tendencia (Neto diario)</h3>
    </div>
    <div class="box-body">
      <canvas id="chartNeto" height="90"></canvas>
      <p class="help-block">Neto = ingresos − gastos (las deudas no se incluyen en la curva, pero se muestran en los KPIs).</p>
    </div>
  </div>

  <!-- Desglose por categoría -->
  <div class="box box-warning">
    <div class="box-header with-border">
      <h3 class="box-title"><i class="fa fa-pie-chart"></i> Desglose por categoría</h3>
    </div>
    <div class="box-body table-responsive no-padding">
      <table class="table table-hover">
        <thead>
          <tr>
            <th>Categoría</th>
            <th>Moneda</th>
            <th>Ingresos</th>
            <th>Gastos</th>
            <th>Deudas</th>
            <th>Neto</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($byCat)): ?>
            <tr><td colspan="6" class="text-center text-muted">Sin datos</td></tr>
          <?php else: foreach ($byCat as $r):
            $net = (float)$r['ingresos'] - (float)$r['gastos'];
          ?>
          <tr>
            <td><?= htmlspecialchars($r['categoria']) ?></td>
            <td><?= htmlspecialchars($r['moneda']) ?></td>
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

  <!-- Listado de movimientos -->
  <div class="box box-info">
    <div class="box-header with-border">
      <h3 class="box-title"><i class="fa fa-list"></i> Movimientos (máx. 500)</h3>
    </div>
    <div class="box-body table-responsive no-padding">
      <table class="table table-hover">
        <thead>
          <tr>
            <th>ID</th>
            <th>Fecha</th>
            <th>Tipo</th>
            <th>Categoría</th>
            <th>Descripción</th>
            <th>Monto</th>
            <th>Moneda</th>
            <th>Método</th>
            <th>Comprobante</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($movs)): ?>
            <tr><td colspan="9" class="text-center text-muted">No hay movimientos en el rango</td></tr>
          <?php else: foreach ($movs as $m): ?>
          <tr>
            <td><?= (int)$m['id'] ?></td>
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
            <td><?= htmlspecialchars($m['moneda']) ?></td>
            <td><?= htmlspecialchars(ucfirst($m['metodo'])) ?></td>
            <td>
              <?php if (!empty($m['comprobante_url'])): ?>
                <a class="btn btn-xs btn-default" href="<?= htmlspecialchars($m['comprobante_url']) ?>" target="_blank">
                  <i class="fa fa-paperclip"></i> Ver
                </a>
              <?php else: ?>
                <span class="text-muted">—</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</section>

<!-- Chart.js CDN (ligero y suficiente para la línea) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function(){
  var ctx = document.getElementById('chartNeto');
  if (!ctx) return;
  var labels = <?= json_encode(array_values($labels)) ?>;
  var data   = <?= json_encode(array_values($series)) ?>;

  new Chart(ctx, {
    type: 'line',
    data: {
      labels: labels,
      datasets: [{
        label: 'Neto diario',
        data: data,
        fill: false
      }]
    },
    options: {
      responsive: true,
      plugins:{ legend:{ display:true } },
      scales: {
        x: { display:true, title:{ display:false } },
        y: { display:true, beginAtZero:true }
      }
    }
  });
})();
</script>
