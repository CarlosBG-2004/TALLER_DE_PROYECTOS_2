<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once "Config/Database.php";

/* --- Acceso: Ventas, Admin o Gerencia --- */
$areas = array_map('strtolower', $_SESSION['areas'] ?? []);
$rol   = $_SESSION['user']['rol'] ?? '';
if (!in_array('ventas', $areas, true) && !in_array($rol, ['Admin','Gerencia'], true)) {
?>
<section class="content-header"><h1>Pagos <small>Acceso restringido</small></h1></section>
<section class="content">
  <div class="callout callout-danger">
    <h4>No autorizado</h4>
    <p>Necesitas permisos de <b>Ventas</b> o ser <b>Admin/Gerencia</b>.</p>
  </div>
</section>
<?php
  return;
}

/* --- Helpers --- */
function flash($k='flash'){ if(!empty($_SESSION[$k])){ echo $_SESSION[$k]; unset($_SESSION[$k]); } }
function set_flash($type,$msg,$k='flash'){ $_SESSION[$k] = '<div class="alert alert-'.$type.'">'.$msg.'</div>'; }
function csrf(){ if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(16)); return $_SESSION['csrf']; }
function csrf_ok($t){ return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t ?? ''); }

$pdo = Database::getConnection();

/* --- Catálogos --- */
$expedientes = $pdo->query("
  SELECT e.id, e.codigo, COALESCE(e.titulo, e.programa) AS titulo,
         CONCAT(c.nombre,' ',c.apellido) AS cliente
  FROM expedientes e
  LEFT JOIN clientes c ON c.id=e.cliente_id
  ORDER BY e.id DESC
  LIMIT 500
")->fetchAll();

$monedas = ['PEN'=>'PEN','USD'=>'USD','EUR'=>'EUR'];
$metodos = [
  'efectivo'      => 'Efectivo',
  'tarjeta'       => 'Tarjeta',
  'transferencia' => 'Transferencia',
  'deposito'      => 'Depósito',
  'yape_plin'     => 'Yape/Plin'
];

/* --- Función: recalcular monto_depositado de un expediente --- */
function actualizarMontoDepositado(PDO $pdo, int $expedienteId): void {
  $s = $pdo->prepare("SELECT COALESCE(SUM(monto),0) FROM pagos WHERE expediente_id=?");
  $s->execute([$expedienteId]);
  $total = (float)$s->fetchColumn();
  $u = $pdo->prepare("UPDATE expedientes SET monto_depositado=?, actualizado_en=NOW() WHERE id=?");
  $u->execute([$total, $expedienteId]);
}

/* --- POST (crear/editar/eliminar) --- */
try {
  if (($_POST['action'] ?? '') === 'create') {
    if (!csrf_ok($_POST['csrf'] ?? '')) throw new Exception('CSRF inválido.');
    $expediente_id = (int)($_POST['expediente_id'] ?? 0);
    $fecha         = $_POST['fecha'] ?: date('Y-m-d');
    $monto         = (float)($_POST['monto'] ?? 0);
    $moneda        = strtoupper(trim($_POST['moneda'] ?? 'PEN'));
    $metodo        = $_POST['metodo'] ?? 'efectivo';
    $notas         = trim($_POST['notas'] ?? '');

    if ($expediente_id<=0 || $monto<=0) throw new Exception('Expediente y monto son obligatorios.');

    // Subida de archivo (opcional)
    $comprobante_url = null;
    if (!empty($_FILES['comprobante']['name']) && $_FILES['comprobante']['error'] === UPLOAD_ERR_OK) {
      $dir = "Views/Uploads/Pagos";
      if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
      $ext = strtolower(pathinfo($_FILES['comprobante']['name'], PATHINFO_EXTENSION));
      $permit = ['pdf','jpg','jpeg','png'];
      if (!in_array($ext, $permit, true)) throw new Exception('Comprobante no permitido (pdf/jpg/png).');
      $fname = 'PAY-'.$expediente_id.'-'.time().'.'.$ext;
      $dest = $dir.'/'.$fname;
      if (!move_uploaded_file($_FILES['comprobante']['tmp_name'], $dest)) throw new Exception('No se pudo guardar el comprobante.');
      $comprobante_url = $dest;
    }

    $st = $pdo->prepare("
      INSERT INTO pagos (expediente_id, fecha, monto, moneda, metodo, comprobante_url, notas)
      VALUES (?,?,?,?,?,?,?)
    ");
    $st->execute([$expediente_id, $fecha, $monto, $moneda, $metodo, $comprobante_url, $notas]);

    actualizarMontoDepositado($pdo, $expediente_id);
    set_flash('success','Pago registrado correctamente.');
    header("Location: Pagos"); exit;
  }

  if (($_POST['action'] ?? '') === 'update') {
    if (!csrf_ok($_POST['csrf'] ?? '')) throw new Exception('CSRF inválido.');
    $id = (int)($_POST['id'] ?? 0);
    if ($id<=0) throw new Exception('ID inválido.');

    // Obtener expediente_id actual para recalcular luego
    $curr = $pdo->prepare("SELECT expediente_id FROM pagos WHERE id=?");
    $curr->execute([$id]);
    $expediente_id = (int)$curr->fetchColumn();
    if ($expediente_id<=0) throw new Exception('Pago no encontrado.');

    $fecha   = $_POST['fecha'] ?: date('Y-m-d');
    $monto   = (float)($_POST['monto'] ?? 0);
    $moneda  = strtoupper(trim($_POST['moneda'] ?? 'PEN'));
    $metodo  = $_POST['metodo'] ?? 'efectivo';
    $notas   = trim($_POST['notas'] ?? '');

    if ($monto<=0) throw new Exception('Monto inválido.');

    // Comprobante opcional (reemplaza)
    $setFile = '';
    $args = [$fecha, $monto, $moneda, $metodo, $notas, $id];
    if (!empty($_FILES['comprobante']['name']) && $_FILES['comprobante']['error'] === UPLOAD_ERR_OK) {
      $dir = "Views/Uploads/Pagos";
      if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
      $ext = strtolower(pathinfo($_FILES['comprobante']['name'], PATHINFO_EXTENSION));
      $permit = ['pdf','jpg','jpeg','png'];
      if (!in_array($ext, $permit, true)) throw new Exception('Comprobante no permitido (pdf/jpg/png).');
      $fname = 'PAY-'.$expediente_id.'-'.time().'.'.$ext;
      $dest = $dir.'/'.$fname;
      if (!move_uploaded_file($_FILES['comprobante']['tmp_name'], $dest)) throw new Exception('No se pudo guardar el comprobante.');
      $setFile = ", comprobante_url=?";
      $args = [$fecha, $monto, $moneda, $metodo, $notas, $dest, $id];
    }

    $sql = "UPDATE pagos SET fecha=?, monto=?, moneda=?, metodo=?, notas=? $setFile WHERE id=?";
    $st  = $pdo->prepare($sql);
    $st->execute($args);

    actualizarMontoDepositado($pdo, $expediente_id);
    set_flash('success','Pago actualizado.');
    header("Location: Pagos"); exit;
  }

  if (($_POST['action'] ?? '') === 'delete') {
    if (!csrf_ok($_POST['csrf'] ?? '')) throw new Exception('CSRF inválido.');
    $id = (int)($_POST['id'] ?? 0);
    if ($id<=0) throw new Exception('ID inválido.');

    // expediente_id antes de borrar
    $s = $pdo->prepare("SELECT expediente_id FROM pagos WHERE id=?");
    $s->execute([$id]);
    $expediente_id = (int)$s->fetchColumn();

    $pdo->prepare("DELETE FROM pagos WHERE id=?")->execute([$id]);

    if ($expediente_id>0) actualizarMontoDepositado($pdo, $expediente_id);
    set_flash('success','Pago eliminado.');
    header("Location: Pagos"); exit;
  }

} catch (Throwable $e) {
  set_flash('danger','Error: '.$e->getMessage());
  header("Location: Pagos"); exit;
}

/* --- Filtros de listado --- */
$csrf = csrf();
$q        = trim($_GET['q'] ?? '');
$df       = trim($_GET['df'] ?? ''); // fecha desde
$dt       = trim($_GET['dt'] ?? ''); // fecha hasta
$met      = trim($_GET['met'] ?? ''); // método
$mon      = trim($_GET['mon'] ?? ''); // moneda

$where = [];
$args  = [];

if ($q !== '') {
  $where[] = "(e.codigo LIKE ? OR COALESCE(e.titulo, e.programa) LIKE ? OR CONCAT(c.nombre,' ',c.apellido) LIKE ?)";
  $like = "%{$q}%";
  array_push($args, $like, $like, $like);
}
if ($df !== '') { $where[] = "p.fecha >= ?"; $args[] = $df; }
if ($dt !== '') { $where[] = "p.fecha <= ?"; $args[] = $dt; }
if ($met !== '') { $where[] = "p.metodo = ?"; $args[] = $met; }
if ($mon !== '') { $where[] = "p.moneda = ?"; $args[] = $mon; }

$wsql = $where ? ('WHERE '.implode(' AND ', $where)) : '';

/* --- Listado + resumen --- */
$sql = "
  SELECT p.id, p.fecha, p.monto, p.moneda, p.metodo, p.comprobante_url, p.notas,
         e.codigo, COALESCE(e.titulo, e.programa) AS titulo,
         CONCAT(c.nombre,' ',c.apellido) AS cliente
  FROM pagos p
  JOIN expedientes e ON e.id=p.expediente_id
  LEFT JOIN clientes c ON c.id=e.cliente_id
  $wsql
  ORDER BY p.fecha DESC, p.id DESC
  LIMIT 300
";
$st = $pdo->prepare($sql);
$st->execute($args);
$rows = $st->fetchAll();

$sum = 0.0;
foreach ($rows as $r) { $sum += (float)$r['monto']; }
?>
<section class="content-header">
  <h1>Pagos <small>Registro y control</small></h1>
  <ol class="breadcrumb">
    <li><a href="Dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
    <li class="active">Pagos</li>
  </ol>
</section>

<section class="content">
  <?php flash(); ?>

  <div class="box box-primary">
    <div class="box-header with-border">
      <form class="form-inline" method="get" action="Pagos" style="margin:0;">
        <div class="form-group">
          <input type="text" name="q" class="form-control" placeholder="Buscar (código, título, cliente)"
                 value="<?= htmlspecialchars($q) ?>" style="min-width:260px;">
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
          <label>Método</label>
          <select name="met" class="form-control">
            <option value="">Todos</option>
            <?php foreach ($metodos as $k=>$v): ?>
              <option value="<?= $k ?>" <?= $met===$k?'selected':'' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Moneda</label>
          <select name="mon" class="form-control">
            <option value="">Todas</option>
            <?php foreach ($monedas as $k=>$v): ?>
              <option value="<?= $k ?>" <?= $mon===$k?'selected':'' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="btn btn-default"><i class="fa fa-search"></i></button>

        <button type="button" class="btn btn-success pull-right" data-toggle="modal" data-target="#modalNuevo">
          <i class="fa fa-plus"></i> Registrar pago
        </button>
      </form>
    </div>

    <div class="box-body table-responsive no-padding">
      <table class="table table-hover">
        <thead>
          <tr>
            <th style="width:110px;">Fecha</th>
            <th>Código / Título</th>
            <th>Cliente</th>
            <th style="text-align:right;">Monto</th>
            <th>Moneda</th>
            <th>Método</th>
            <th>Comprobante</th>
            <th style="width:150px;">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="8" class="text-center text-muted">Sin registros</td></tr>
          <?php else: foreach ($rows as $r): ?>
            <tr>
              <td><?= htmlspecialchars($r['fecha']) ?></td>
              <td>
                <strong><?= htmlspecialchars($r['codigo']) ?></strong>
                <div class="text-muted"><?= htmlspecialchars($r['titulo'] ?: '—') ?></div>
              </td>
              <td><?= htmlspecialchars($r['cliente'] ?: '—') ?></td>
              <td style="text-align:right;"><?= number_format((float)$r['monto'], 2) ?></td>
              <td><?= htmlspecialchars($r['moneda']) ?></td>
              <td><?= htmlspecialchars($metodos[$r['metodo']] ?? $r['metodo']) ?></td>
              <td>
                <?php if (!empty($r['comprobante_url'])): ?>
                  <a class="btn btn-xs btn-default" href="<?= htmlspecialchars($r['comprobante_url']) ?>" target="_blank">
                    <i class="fa fa-paperclip"></i> Ver
                  </a>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <td>
                <button class="btn btn-xs btn-warning"
                        data-toggle="modal" data-target="#modalEditar"
                        data-id="<?= (int)$r['id'] ?>"
                        data-fecha="<?= htmlspecialchars($r['fecha']) ?>"
                        data-monto="<?= htmlspecialchars($r['monto']) ?>"
                        data-moneda="<?= htmlspecialchars($r['moneda']) ?>"
                        data-metodo="<?= htmlspecialchars($r['metodo']) ?>"
                        data-notas="<?= htmlspecialchars($r['notas'], ENT_QUOTES) ?>">
                  <i class="fa fa-pencil"></i> Editar
                </button>
                <form method="post" action="Pagos" style="display:inline;"
                      onsubmit="return confirm('¿Eliminar pago?');">
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
      <strong>Total listado:</strong> <?= number_format($sum, 2) ?> <?= htmlspecialchars($mon ?: ' ') ?>
      <small class="text-muted"> (sujeto a filtros)</small>
    </div>
  </div>
</section>

<!-- MODAL: NUEVO PAGO -->
<div class="modal fade" id="modalNuevo">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="Pagos" enctype="multipart/form-data" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="action" value="create">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title"><i class="fa fa-money"></i> Registrar pago</h4>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label>Expediente *</label>
          <select name="expediente_id" class="form-control" required>
            <option value="">-- Seleccione --</option>
            <?php foreach ($expedientes as $e): ?>
              <option value="<?= (int)$e['id'] ?>">
                <?= htmlspecialchars($e['codigo'].' - '.($e['titulo'] ?: '').' / '.($e['cliente'] ?: '')) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="row">
          <div class="col-xs-6">
            <div class="form-group">
              <label>Fecha</label>
              <input type="date" name="fecha" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>
          </div>
          <div class="col-xs-6">
            <div class="form-group">
              <label>Monto *</label>
              <input type="number" step="0.01" min="0.01" name="monto" class="form-control" required>
            </div>
          </div>
        </div>
        <div class="row">
          <div class="col-xs-6">
            <div class="form-group">
              <label>Moneda</label>
              <select name="moneda" class="form-control">
                <?php foreach ($monedas as $k=>$v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="col-xs-6">
            <div class="form-group">
              <label>Método</label>
              <select name="metodo" class="form-control">
                <?php foreach ($metodos as $k=>$v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>
        <div class="form-group">
          <label>Notas</label>
          <textarea name="notas" class="form-control" rows="2"></textarea>
        </div>
        <div class="form-group">
          <label>Comprobante (pdf/jpg/png)</label>
          <input type="file" name="comprobante" class="form-control">
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-default" data-dismiss="modal">Cancelar</button>
        <button class="btn btn-success"><i class="fa fa-save"></i> Guardar</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL: EDITAR PAGO -->
<div class="modal fade" id="modalEditar">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="Pagos" enctype="multipart/form-data" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="id" id="e_id">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title">Editar pago</h4>
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
            <div class="form-group">
              <label>Monto</label>
              <input type="number" step="0.01" min="0.01" name="monto" id="e_monto" class="form-control">
            </div>
          </div>
        </div>
        <div class="row">
          <div class="col-xs-6">
            <div class="form-group">
              <label>Moneda</label>
              <select name="moneda" id="e_moneda" class="form-control">
                <?php foreach ($monedas as $k=>$v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="col-xs-6">
            <div class="form-group">
              <label>Método</label>
              <select name="metodo" id="e_metodo" class="form-control">
                <?php foreach ($metodos as $k=>$v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>
        <div class="form-group">
          <label>Notas</label>
          <textarea name="notas" id="e_notas" class="form-control" rows="2"></textarea>
        </div>
        <div class="form-group">
          <label>Reemplazar comprobante (opcional)</label>
          <input type="file" name="comprobante" class="form-control">
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
// Completar modal editar con data-* del botón
$('#modalEditar').on('show.bs.modal', function (e) {
  var b = $(e.relatedTarget);
  $('#e_id').val(b.data('id'));
  $('#e_fecha').val(b.data('fecha'));
  $('#e_monto').val(b.data('monto'));
  $('#e_moneda').val(b.data('moneda'));
  $('#e_metodo').val(b.data('metodo'));
  $('#e_notas').val(b.data('notas'));
});
</script>
