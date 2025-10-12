<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once "Config/Database.php";

/* -------- Acceso: Contabilidad, Admin o Gerencia -------- */
$areas = array_map('strtolower', $_SESSION['areas'] ?? []);
$rol   = $_SESSION['user']['rol'] ?? '';
if (!in_array('contabilidad', $areas, true) && !in_array($rol, ['Admin','Gerencia'], true)) {
?>
<section class="content-header"><h1>Contabilidad — Movimientos <small>Acceso restringido</small></h1></section>
<section class="content">
  <div class="callout callout-danger">
    <h4>No autorizado</h4>
    <p>Necesitas permisos de <b>Contabilidad</b> o ser <b>Admin/Gerencia</b>.</p>
  </div>
</section>
<?php
  return;
}

/* -------- Helpers -------- */
function flash($k='flash'){ if(!empty($_SESSION[$k])){ echo $_SESSION[$k]; unset($_SESSION[$k]); } }
function set_flash($type,$msg,$k='flash'){ $_SESSION[$k] = '<div class="alert alert-'.$type.'">'.$msg.'</div>'; }
function csrf(){ if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(16)); return $_SESSION['csrf']; }
function csrf_ok($t){ return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t ?? ''); }

/* -------- DB & detecciones -------- */
$pdo = Database::getConnection();
$dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();

/* Detectar nombre de columna FK a expedientes en contabilidad_movimientos */
$expCol = 'expediente_id';
$chk = $pdo->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='contabilidad_movimientos' AND COLUMN_NAME='expediente_id'");
$chk->execute([$dbName]);
if (!$chk->fetch()) {
  $chk = $pdo->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='contabilidad_movimientos' AND COLUMN_NAME='archivo_id'");
  $chk->execute([$dbName]);
  $expCol = $chk->fetch() ? 'archivo_id' : null;
}

/* Detectar la columna de método de pago (metodo | medio_pago | forma_pago | metodo_pago | payment_method) */
$metodoCol = null;
$posibles = ['metodo','medio_pago','forma_pago','metodo_pago','payment_method'];
foreach ($posibles as $cand) {
  $q = $pdo->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='contabilidad_movimientos' AND COLUMN_NAME=?");
  $q->execute([$dbName, $cand]);
  if ($q->fetch()) { $metodoCol = $cand; break; }
}

/* Catálogos */
$monedas = ['PEN'=>'PEN', 'USD'=>'USD', 'EUR'=>'EUR'];
$metodos = [
  'efectivo'      => 'Efectivo',
  'transferencia' => 'Transferencia',
  'deposito'      => 'Depósito',
  'tarjeta'       => 'Tarjeta',
  'yape_plin'     => 'Yape/Plin',
];

/* Categorías (tabla: contabilidad_categorias: id, nombre, tipo[ingreso|gasto]) */
$categorias = $pdo->query("SELECT id, nombre, tipo FROM contabilidad_categorias ORDER BY tipo, nombre")->fetchAll();
$catsIngreso = array_filter($categorias, fn($c)=>($c['tipo']??'')==='ingreso');
$catsGasto   = array_filter($categorias, fn($c)=>($c['tipo']??'')==='gasto');

/* Expedientes para vincular (opcional) */
$expedientes = $pdo->query("
  SELECT e.id, e.codigo, COALESCE(e.titulo, e.programa) AS titulo,
         CONCAT(c.nombre,' ',c.apellido) AS cliente
  FROM expedientes e
  LEFT JOIN clientes c ON c.id=e.cliente_id
  ORDER BY e.id DESC
  LIMIT 500
")->fetchAll();

/* -------- POST: crear/editar/eliminar -------- */
try {
  if (($_POST['action'] ?? '') === 'create') {
    if (!csrf_ok($_POST['csrf'] ?? '')) throw new Exception('CSRF inválido.');
    $fecha       = $_POST['fecha'] ?: date('Y-m-d');
    $tipo        = ($_POST['tipo'] ?? '') === 'ingreso' ? 'ingreso' : 'gasto';
    $categoriaId = (int)($_POST['categoria_id'] ?? 0);
    $descripcion = trim($_POST['descripcion'] ?? '');
    $monto       = (float)($_POST['monto'] ?? 0);
    $moneda      = strtoupper(trim($_POST['moneda'] ?? 'PEN'));
    $metodoVal   = trim($_POST['metodo'] ?? ''); // sólo si existe columna
    $notas       = trim($_POST['notas'] ?? '');
    $expId       = (int)($_POST['expediente_id'] ?? 0);

    if ($monto <= 0) throw new Exception('El monto debe ser mayor a cero.');
    if (!$categoriaId) throw new Exception('Selecciona una categoría.');

    // Comprobante (opcional)
    $comprobante_url = null;
    if (!empty($_FILES['comprobante']['name']) && $_FILES['comprobante']['error'] === UPLOAD_ERR_OK) {
      $dir = "Views/Uploads/Contabilidad";
      if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
      $ext = strtolower(pathinfo($_FILES['comprobante']['name'], PATHINFO_EXTENSION));
      if (!in_array($ext, ['pdf','jpg','jpeg','png'], true)) throw new Exception('Comprobante no permitido (pdf/jpg/png).');
      $fname = 'CONTA-'.time().'.'.$ext;
      $dest  = $dir.'/'.$fname;
      if (!move_uploaded_file($_FILES['comprobante']['tmp_name'], $dest)) throw new Exception('No se pudo guardar el comprobante.');
      $comprobante_url = $dest;
    }

    // Armar SQL dinámico según columnas disponibles
    $cols = ['fecha','tipo','categoria_id','descripcion','monto','moneda'];
    $vals = [$fecha,$tipo,$categoriaId,$descripcion,$monto,$moneda];

    if ($metodoCol) { $cols[] = $metodoCol; $vals[] = ($metodoVal ?: null); }
    if ($expCol)    { $cols[] = $expCol;    $vals[] = ($expId ?: null); }
    $cols[] = 'notas';            $vals[] = ($notas ?: null);
    $cols[] = 'comprobante_url';  $vals[] = $comprobante_url;

    $placeholders = implode(',', array_fill(0, count($cols), '?'));
    $sql = "INSERT INTO contabilidad_movimientos (".implode(',', $cols).") VALUES ($placeholders)";
    $st = $pdo->prepare($sql);
    $st->execute($vals);

    set_flash('success','Movimiento registrado.');
    header("Location: ContabilidadMovimientos"); exit;
  }

  if (($_POST['action'] ?? '') === 'update') {
    if (!csrf_ok($_POST['csrf'] ?? '')) throw new Exception('CSRF inválido.');
    $id          = (int)($_POST['id'] ?? 0);
    $fecha       = $_POST['fecha'] ?: date('Y-m-d');
    $tipo        = ($_POST['tipo'] ?? '') === 'ingreso' ? 'ingreso' : 'gasto';
    $categoriaId = (int)($_POST['categoria_id'] ?? 0);
    $descripcion = trim($_POST['descripcion'] ?? '');
    $monto       = (float)($_POST['monto'] ?? 0);
    $moneda      = strtoupper(trim($_POST['moneda'] ?? 'PEN'));
    $metodoVal   = trim($_POST['metodo'] ?? '');
    $notas       = trim($_POST['notas'] ?? '');
    $expId       = (int)($_POST['expediente_id'] ?? 0);

    if ($id<=0) throw new Exception('ID inválido.');
    if ($monto <= 0) throw new Exception('El monto debe ser mayor a cero.');
    if (!$categoriaId) throw new Exception('Selecciona una categoría.');

    // Comprobante (opcional: reemplaza)
    $setFile = ''; $argsFile = [];
    if (!empty($_FILES['comprobante']['name']) && $_FILES['comprobante']['error'] === UPLOAD_ERR_OK) {
      $dir = "Views/Uploads/Contabilidad";
      if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
      $ext = strtolower(pathinfo($_FILES['comprobante']['name'], PATHINFO_EXTENSION));
      if (!in_array($ext, ['pdf','jpg','jpeg','png'], true)) throw new Exception('Comprobante no permitido (pdf/jpg/png).');
      $fname = 'CONTA-'.time().'.'.$ext;
      $dest  = $dir.'/'.$fname;
      if (!move_uploaded_file($_FILES['comprobante']['tmp_name'], $dest)) throw new Exception('No se pudo guardar el comprobante.');
      $setFile = ", comprobante_url=?";
      $argsFile[] = $dest;
    }

    // Armar SET dinámico
    $sets = ['fecha=?','tipo=?','categoria_id=?','descripcion=?','monto=?','moneda=?','notas=?'];
    $args = [$fecha,$tipo,$categoriaId,$descripcion,$monto,$moneda,$notas ?: null];

    if ($metodoCol) { $sets[] = "{$metodoCol}=?"; $args[] = ($metodoVal ?: null); }
    if ($expCol)    { $sets[] = "{$expCol}=?";    $args[] = ($expId ?: null); }
    if ($setFile)   { $sets[] = "actualizado_en=NOW()"; } // ya hay cambio seguro
    else            { $sets[] = "actualizado_en=NOW()"; } // actualiza timestamp igual

    $sql = "UPDATE contabilidad_movimientos SET ".implode(',', $sets).($setFile ? $setFile : '')." WHERE id=?";
    $args = array_merge($args, $argsFile, [$id]);

    $st = $pdo->prepare($sql);
    $st->execute($args);

    set_flash('success','Movimiento actualizado.');
    header("Location: ContabilidadMovimientos"); exit;
  }

  if (($_POST['action'] ?? '') === 'delete') {
    if (!csrf_ok($_POST['csrf'] ?? '')) throw new Exception('CSRF inválido.');
    $id = (int)($_POST['id'] ?? 0);
    if ($id<=0) throw new Exception('ID inválido.');
    $pdo->prepare("DELETE FROM contabilidad_movimientos WHERE id=?")->execute([$id]);
    set_flash('success','Movimiento eliminado.');
    header("Location: ContabilidadMovimientos"); exit;
  }

} catch (Throwable $e) {
  set_flash('danger','Error: '.$e->getMessage());
  header("Location: ContabilidadMovimientos"); exit;
}

/* -------- Filtros -------- */
$csrf = csrf();
$q   = trim($_GET['q'] ?? '');
$df  = trim($_GET['df'] ?? date('Y-m-01'));
$dt  = trim($_GET['dt'] ?? date('Y-m-t'));
$tipoF = trim($_GET['tipo'] ?? '');
$catF  = (int)($_GET['cat'] ?? 0);
$monF  = trim($_GET['mon'] ?? '');
$metF  = trim($_GET['met'] ?? '');
$expF  = (int)($_GET['ex'] ?? 0);

$where = [];
$args  = [];
if ($q!==''){
  $where[] = "(m.descripcion LIKE ? OR c.nombre LIKE ? OR e.codigo LIKE ? OR COALESCE(e.titulo, e.programa) LIKE ?)";
  $like = "%{$q}%"; array_push($args,$like,$like,$like,$like);
}
if ($df!==''){ $where[] = "m.fecha >= ?"; $args[] = $df; }
if ($dt!==''){ $where[] = "m.fecha <= ?"; $args[] = $dt; }
if ($tipoF==='ingreso' || $tipoF==='gasto'){ $where[] = "m.tipo = ?"; $args[] = $tipoF; }
if ($catF>0){ $where[] = "m.categoria_id = ?"; $args[] = $catF; }
if ($monF!==''){ $where[] = "m.moneda = ?"; $args[] = $monF; }
if ($metF!=='' && $metodoCol){ $where[] = "m.{$metodoCol} = ?"; $args[] = $metF; }
if ($expF>0 && $expCol){ $where[] = "m.{$expCol} = ?"; $args[] = $expF; }

$wsql = $where ? "WHERE ".implode(" AND ", $where) : "";

/* -------- Listado + totales -------- */
$joinExp = $expCol ? "LEFT JOIN expedientes e ON e.id=m.{$expCol}" : "LEFT JOIN expedientes e ON 1=0";
$selMetodo = $metodoCol ? "m.{$metodoCol} AS metodo" : "NULL AS metodo";
$sql = "
  SELECT m.id, m.fecha, m.tipo, m.descripcion, m.monto, m.moneda, $selMetodo, m.comprobante_url,
         c.nombre AS categoria,
         e.codigo, COALESCE(e.titulo, e.programa) AS expediente_titulo
  FROM contabilidad_movimientos m
  LEFT JOIN contabilidad_categorias c ON c.id=m.categoria_id
  $joinExp
  $wsql
  ORDER BY m.fecha DESC, m.id DESC
  LIMIT 500
";
$st = $pdo->prepare($sql);
$st->execute($args);
$rows = $st->fetchAll();

$sumIng = 0.0; $sumGto = 0.0;
foreach ($rows as $r) {
  if ($r['tipo']==='ingreso') $sumIng += (float)$r['monto'];
  else                        $sumGto += (float)$r['monto'];
}
$balance = $sumIng - $sumGto;
?>
<section class="content-header">
  <h1>Contabilidad <small>Movimientos</small></h1>
  <ol class="breadcrumb">
    <li><a href="Dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
    <li class="active">Contabilidad</li>
  </ol>
</section>

<section class="content">
  <?php if (empty($categorias)): ?>
  <div class="callout callout-warning">
    <h4>Primero crea categorías</h4>
    <p>No hay categorías. Ve a <a href="ContabilidadCategorias"><b>Contabilidad &raquo; Categorías</b></a> para crear <i>ingresos</i> y <i>gastos</i>.</p>
  </div>
  <?php endif; ?>

  <?php flash(); ?>

  <div class="row">
    <div class="col-md-3">
      <div class="small-box bg-green">
        <div class="inner">
          <h3><?= number_format($sumIng, 2) ?></h3>
          <p>Ingresos (listado)</p>
        </div>
        <div class="icon"><i class="fa fa-arrow-circle-up"></i></div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="small-box bg-red">
        <div class="inner">
          <h3><?= number_format($sumGto, 2) ?></h3>
          <p>Gastos (listado)</p>
        </div>
        <div class="icon"><i class="fa fa-arrow-circle-down"></i></div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="small-box bg-aqua">
        <div class="inner">
          <h3><?= number_format($balance, 2) ?></h3>
          <p>Balance</p>
        </div>
        <div class="icon"><i class="fa fa-balance-scale"></i></div>
      </div>
    </div>
    <div class="col-md-3">
      <button type="button" class="btn btn-success btn-block" data-toggle="modal" data-target="#modalNuevo">
        <i class="fa fa-plus"></i> Nuevo movimiento
      </button>
    </div>
  </div>

  <div class="box box-primary">
    <div class="box-header with-border">
      <form class="form-inline" method="get" action="ContabilidadMovimientos" style="margin:0;">
        <div class="form-group">
          <input type="text" name="q" class="form-control" placeholder="Buscar (desc, cat, expediente)"
                 value="<?= htmlspecialchars($q) ?>" style="min-width:240px;">
        </div>
        <div class="form-group">
          <label>Desde</label>
          <input type="date" name="df" class="form-control" value="<?= htmlspecialchars($df) ?>">
        </div>
        <div class="form-group">
          <label>Hasta</label>
          <input type="date" name="dt" class="form-control" value="<?= htmlspecialchars($dt) ?>">
        </div>
        <div class="form-group">
          <label>Tipo</label>
          <select name="tipo" class="form-control">
            <option value="">Todos</option>
            <option value="ingreso" <?= $tipoF==='ingreso'?'selected':'' ?>>Ingreso</option>
            <option value="gasto"   <?= $tipoF==='gasto'?'selected':'' ?>>Gasto</option>
          </select>
        </div>
        <div class="form-group">
          <label>Cat.</label>
          <select name="cat" class="form-control">
            <option value="0">Todas</option>
            <?php foreach ($categorias as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= $catF===(int)$c['id']?'selected':'' ?>>
                <?= htmlspecialchars(($c['tipo']==='ingreso'?'[I] ':'[G] ').$c['nombre']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Moneda</label>
          <select name="mon" class="form-control">
            <option value="">Todas</option>
            <?php foreach ($monedas as $k=>$v): ?>
              <option value="<?= $k ?>" <?= $monF===$k?'selected':'' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php if ($metodoCol): ?>
        <div class="form-group">
          <label>Método</label>
          <select name="met" class="form-control">
            <option value="">Todos</option>
            <?php foreach ($metodos as $k=>$v): ?>
              <option value="<?= $k ?>" <?= $metF===$k?'selected':'' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
        <?php if ($expCol): ?>
        <div class="form-group">
          <label>Expediente</label>
          <select name="ex" class="form-control">
            <option value="0">Todos</option>
            <?php foreach ($expedientes as $e): ?>
              <option value="<?= (int)$e['id'] ?>" <?= $expF===(int)$e['id']?'selected':'' ?>>
                <?= htmlspecialchars($e['codigo'].' - '.($e['titulo'] ?: '').' / '.($e['cliente'] ?: '')) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
        <button class="btn btn-default"><i class="fa fa-search"></i></button>
        <a class="btn btn-default" href="ContabilidadMovimientos?df=<?= date('Y-m-01') ?>&dt=<?= date('Y-m-t') ?>"><i class="fa fa-calendar"></i> Mes actual</a>
      </form>
    </div>

    <div class="box-body table-responsive no-padding">
      <table class="table table-hover">
        <thead>
          <tr>
            <th style="width:100px;">Fecha</th>
            <th>Tipo</th>
            <th>Categoría</th>
            <th>Descripción</th>
            <th>Expediente</th>
            <th style="text-align:right;">Monto</th>
            <th>Moneda</th>
            <?php if ($metodoCol): ?><th>Método</th><?php endif; ?>
            <th>Comprobante</th>
            <th style="width:150px;">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="<?= $metodoCol ? 10 : 9 ?>" class="text-center text-muted">Sin registros</td></tr>
          <?php else: foreach ($rows as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r['fecha']) ?></td>
            <td>
              <?php if ($r['tipo']==='ingreso'): ?>
                <span class="label label-success">Ingreso</span>
              <?php else: ?>
                <span class="label label-danger">Gasto</span>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($r['categoria'] ?: '—') ?></td>
            <td><?= htmlspecialchars($r['descripcion'] ?: '—') ?></td>
            <td>
              <?php if (!empty($r['codigo'])): ?>
                <span class="label label-primary"><?= htmlspecialchars($r['codigo']) ?></span>
                <div class="text-muted"><?= htmlspecialchars($r['expediente_titulo'] ?: '—') ?></div>
              <?php else: ?>
                <span class="text-muted">—</span>
              <?php endif; ?>
            </td>
            <td style="text-align:right;"><?= number_format((float)$r['monto'], 2) ?></td>
            <td><?= htmlspecialchars($r['moneda']) ?></td>
            <?php if ($metodoCol): ?>
              <td><?= htmlspecialchars($metodos[$r['metodo']] ?? $r['metodo'] ?? '—') ?></td>
            <?php endif; ?>
            <td>
              <?php if (!empty($r['comprobante_url'])): ?>
                <a class="btn btn-xs btn-default" href="<?= htmlspecialchars($r['comprobante_url']) ?>" target="_blank"><i class="fa fa-paperclip"></i> Ver</a>
              <?php else: ?>
                <span class="text-muted">—</span>
              <?php endif; ?>
            </td>
            <td>
              <button class="btn btn-xs btn-warning"
                      data-toggle="modal" data-target="#modalEditar"
                      data-id="<?= (int)$r['id'] ?>"
                      data-fecha="<?= htmlspecialchars($r['fecha']) ?>"
                      data-tipo="<?= htmlspecialchars($r['tipo']) ?>"
                      data-monto="<?= htmlspecialchars($r['monto']) ?>"
                      data-moneda="<?= htmlspecialchars($r['moneda']) ?>"
                      <?php if ($metodoCol): ?>data-metodo="<?= htmlspecialchars($r['metodo'] ?? '') ?>"<?php endif; ?>
                      data-desc="<?= htmlspecialchars($r['descripcion'], ENT_QUOTES) ?>">
                <i class="fa fa-pencil"></i> Editar
              </button>
              <form method="post" action="ContabilidadMovimientos" style="display:inline;" onsubmit="return confirm('¿Eliminar movimiento?');">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button class="btn btn-xs btn-danger"><i class="fa fa-trash"></i></button>
              </form>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <div class="box-footer">
      <strong>Ingresos:</strong> <?= number_format($sumIng,2) ?> &nbsp;
      <strong>Gastos:</strong> <?= number_format($sumGto,2) ?> &nbsp;
      <strong>Balance:</strong> <?= number_format($balance,2) ?>
      <small class="text-muted"> (según filtros y hasta 500 movimientos)</small>
    </div>
  </div>
</section>

<!-- MODAL NUEVO -->
<div class="modal fade" id="modalNuevo">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="ContabilidadMovimientos" enctype="multipart/form-data" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="action" value="create">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title"><i class="fa fa-plus"></i> Nuevo movimiento</h4>
      </div>
      <div class="modal-body">
        <div class="row">
          <div class="col-xs-6">
            <div class="form-group">
              <label>Fecha</label>
              <input type="date" name="fecha" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>
          </div>
          <div class="col-xs-6">
            <label>Tipo</label>
            <select name="tipo" class="form-control">
              <option value="ingreso">Ingreso</option>
              <option value="gasto">Gasto</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label>Categoría *</label>
          <select name="categoria_id" class="form-control" required>
            <option value="">-- Seleccione --</option>
            <?php if (!empty($catsIngreso)): ?>
            <optgroup label="Ingresos">
              <?php foreach ($catsIngreso as $c): ?>
              <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
              <?php endforeach; ?>
            </optgroup>
            <?php endif; ?>
            <?php if (!empty($catsGasto)): ?>
            <optgroup label="Gastos">
              <?php foreach ($catsGasto as $c): ?>
              <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
              <?php endforeach; ?>
            </optgroup>
            <?php endif; ?>
          </select>
        </div>

        <div class="form-group">
          <label>Descripción</label>
          <input type="text" name="descripcion" class="form-control" placeholder="Ej. Comisión / Insumos">
        </div>

        <div class="row">
          <div class="col-xs-4">
            <div class="form-group">
              <label>Monto *</label>
              <input type="number" step="0.01" min="0.01" name="monto" class="form-control" required>
            </div>
          </div>
          <div class="col-xs-4">
            <div class="form-group">
              <label>Moneda</label>
              <select name="moneda" class="form-control">
                <?php foreach ($monedas as $k=>$v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
              </select>
            </div>
          </div>
          <?php if ($metodoCol): ?>
          <div class="col-xs-4">
            <div class="form-group">
              <label>Método</label>
              <select name="metodo" class="form-control">
                <?php foreach ($metodos as $k=>$v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
              </select>
            </div>
          </div>
          <?php endif; ?>
        </div>

        <?php if ($expCol): ?>
        <div class="form-group">
          <label>Vincular a Expediente (opcional)</label>
          <select name="expediente_id" class="form-control">
            <option value="">(Ninguno)</option>
            <?php foreach ($expedientes as $e): ?>
              <option value="<?= (int)$e['id'] ?>"><?= htmlspecialchars($e['codigo'].' - '.($e['titulo'] ?: '').' / '.($e['cliente'] ?: '')) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>

        <div class="form-group">
          <label>Comprobante (pdf/jpg/png)</label>
          <input type="file" name="comprobante" class="form-control">
        </div>

        <div class="form-group">
          <label>Notas</label>
          <textarea name="notas" class="form-control" rows="2"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-default" data-dismiss="modal">Cancelar</button>
        <button class="btn btn-success"><i class="fa fa-save"></i> Guardar</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL EDITAR -->
<div class="modal fade" id="modalEditar">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="ContabilidadMovimientos" enctype="multipart/form-data" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="id" id="e_id">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title"><i class="fa fa-pencil"></i> Editar movimiento</h4>
      </div>
      <div class="modal-body">
        <div class="row">
          <div class="col-xs-6">
            <div class="form-group">
              <label>Fecha</label>
              <input type="date" name="fecha" id="e_fecha" class="form-control">
            </div>
          </div>
          <div class="col-xs-6">
            <label>Tipo</label>
            <select name="tipo" id="e_tipo" class="form-control">
              <option value="ingreso">Ingreso</option>
              <option value="gasto">Gasto</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label>Categoría *</label>
          <select name="categoria_id" id="e_categoria" class="form-control" required>
            <option value="">-- Seleccione --</option>
            <?php if (!empty($catsIngreso)): ?>
            <optgroup label="Ingresos">
              <?php foreach ($catsIngreso as $c): ?>
              <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
              <?php endforeach; ?>
            </optgroup>
            <?php endif; ?>
            <?php if (!empty($catsGasto)): ?>
            <optgroup label="Gastos">
              <?php foreach ($catsGasto as $c): ?>
              <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
              <?php endforeach; ?>
            </optgroup>
            <?php endif; ?>
          </select>
        </div>

        <div class="form-group">
          <label>Descripción</label>
          <input type="text" name="descripcion" id="e_desc" class="form-control">
        </div>

        <div class="row">
          <div class="col-xs-4">
            <div class="form-group">
              <label>Monto *</label>
              <input type="number" step="0.01" min="0.01" name="monto" id="e_monto" class="form-control" required>
            </div>
          </div>
          <div class="col-xs-4">
            <div class="form-group">
              <label>Moneda</label>
              <select name="moneda" id="e_moneda" class="form-control">
                <?php foreach ($monedas as $k=>$v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
              </select>
            </div>
          </div>
          <?php if ($metodoCol): ?>
          <div class="col-xs-4">
            <div class="form-group">
              <label>Método</label>
              <select name="metodo" id="e_metodo" class="form-control">
                <?php foreach ($metodos as $k=>$v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
              </select>
            </div>
          </div>
          <?php endif; ?>
        </div>

        <?php if ($expCol): ?>
        <div class="form-group">
          <label>Vincular a Expediente (opcional)</label>
          <select name="expediente_id" id="e_exp" class="form-control">
            <option value="">(Ninguno)</option>
            <?php foreach ($expedientes as $e): ?>
              <option value="<?= (int)$e['id'] ?>"><?= htmlspecialchars($e['codigo'].' - '.($e['titulo'] ?: '').' / '.($e['cliente'] ?: '')) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>

        <div class="form-group">
          <label>Reemplazar comprobante (opcional)</label>
          <input type="file" name="comprobante" class="form-control">
        </div>

        <div class="form-group">
          <label>Notas</label>
          <textarea name="notas" id="e_notas" class="form-control" rows="2"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-default" data-dismiss="modal">Cancelar</button>
        <button class="btn btn-warning"><i class="fa fa-save"></i> Guardar cambios</button>
      </div>
    </form>
  </div>
</div>

<script>
// Completar modal editar
$('#modalEditar').on('show.bs.modal', function (e) {
  var b = $(e.relatedTarget);
  $('#e_id').val(b.data('id'));
  $('#e_fecha').val(b.data('fecha'));
  $('#e_tipo').val(b.data('tipo'));
  $('#e_monto').val(b.data('monto'));
  $('#e_moneda').val(b.data('moneda'));
  <?php /* Sólo si existe columna de método */ ?>
  <?php if ($metodoCol): ?> $('#e_metodo').val(b.data('metodo') || ''); <?php endif; ?>
  $('#e_desc').val(b.data('desc') || '');
  // Nota: categoría y expediente no se pasan por data-*. Selección manual al editar.
});
</script>
