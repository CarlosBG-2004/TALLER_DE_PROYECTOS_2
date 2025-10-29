<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once "Config/Database.php";

/* --- Acceso: área Ventas, Admin o Gerencia --- */
$areas = array_map('strtolower', $_SESSION['areas'] ?? []);
$rol   = $_SESSION['user']['rol'] ?? '';
if (!in_array('ventas', $areas, true) && !in_array($rol, ['Admin','Gerencia'], true)) {
?>
<section class="content-header"><h1>Expedientes <small>Acceso restringido</small></h1></section>
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
function csrf_token(){ if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(16)); return $_SESSION['csrf']; }
function csrf_ok($t){ return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t ?? ''); }

/* --- Catálogos --- */
$pdo = Database::getConnection();
$clientes = $pdo->query("SELECT id, CONCAT(nombre,' ',apellido) AS nombre FROM clientes ORDER BY nombre")->fetchAll();
$agencias = $pdo->query("SELECT id, nombre FROM agencias ORDER BY nombre")->fetchAll();
$responsables = $pdo->query("SELECT id, CONCAT(nombre,' ',apellido) AS nombre FROM usuarios WHERE activo=1 ORDER BY nombre")->fetchAll();

/* --- POST actions --- */
try {
  if (($_POST['action'] ?? '') === 'create') {
    if (!csrf_ok($_POST['csrf'] ?? '')) throw new Exception('CSRF inválido.');

    $responsable_id = (int)($_POST['responsable_id'] ?? 0);
    $cliente_id     = (int)($_POST['cliente_id'] ?? 0);
    $agencia_id     = (int)($_POST['agencia_id'] ?? 0);
    $titulo         = trim($_POST['titulo'] ?? '');
    $programa       = trim($_POST['programa'] ?? '');
    $tour           = trim($_POST['tour'] ?? '');
    $duracion       = (int)($_POST['duracion_dias'] ?? 0);
    $personas       = (int)($_POST['personas'] ?? 1);
    $fecha_venta    = $_POST['fecha_venta'] ?? null;
    $fecha_inicio   = $_POST['fecha_inicio'] ?? null;
    $fecha_fin      = $_POST['fecha_fin'] ?? null;
    $moneda         = strtoupper(trim($_POST['moneda'] ?? 'PEN'));
    $monto_persona  = (float)($_POST['monto_persona'] ?? 0);
    $monto_total    = (float)($_POST['monto_total'] ?? ($personas * $monto_persona));
    $monto_depositado = (float)($_POST['monto_depositado'] ?? 0);
    $medio_pago     = trim($_POST['medio_pago'] ?? '');
    $origen_cliente = trim($_POST['origen_cliente'] ?? '');
    $estado         = trim($_POST['estado'] ?? 'abierto');
    $notas          = trim($_POST['notas'] ?? '');

    if (!$responsable_id || !$cliente_id || $titulo === '') throw new Exception('Responsable, cliente y título son obligatorios.');

    // Generar código único EXP-yyyymm-#####
    $yy = date('Ym');
    $correl = $pdo->query("SELECT LPAD(COALESCE(MAX(id),0)+1,5,'0') AS c FROM expedientes")->fetchColumn();
    $codigo = "EXP-{$yy}-{$correl}";

    // Subida de archivo (opcional)
    $archivo_url = null;
    if (!empty($_FILES['archivo']['name']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
      $dir = "Views/Uploads/Expedientes";
      if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
      $ext = strtolower(pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION));
      $permit = ['pdf','jpg','jpeg','png'];
      if (!in_array($ext, $permit, true)) throw new Exception('Archivo no permitido (pdf/jpg/png).');
      $fname = $codigo . '-' . time() . '.' . $ext;
      $dest = $dir . '/' . $fname;
      if (!move_uploaded_file($_FILES['archivo']['tmp_name'], $dest)) throw new Exception('No se pudo guardar el archivo.');
      $archivo_url = $dest;
    }

    $st = $pdo->prepare("
      INSERT INTO expedientes
      (codigo, responsable_id, cliente_id, agencia_id, titulo, programa, tour, duracion_dias, personas,
       fecha_venta, fecha_inicio, fecha_fin, moneda, monto_persona, monto_total, monto_depositado,
       medio_pago, origen_cliente, notas, archivo_url, estado)
      VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");
    $st->execute([
      $codigo, $responsable_id, $cliente_id, $agencia_id ?: null, $titulo, $programa, $tour, $duracion, $personas,
      $fecha_venta ?: null, $fecha_inicio ?: null, $fecha_fin ?: null, $moneda, $monto_persona, $monto_total, $monto_depositado,
      $medio_pago, $origen_cliente, $notas, $archivo_url, $estado
    ]);

    set_flash('success','Expediente creado correctamente.');
    header("Location: Expedientes"); exit;
  }

  if (($_POST['action'] ?? '') === 'update') {
    if (!csrf_ok($_POST['csrf'] ?? '')) throw new Exception('CSRF inválido.');
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) throw new Exception('ID inválido.');

    $responsable_id = (int)($_POST['responsable_id'] ?? 0);
    $cliente_id     = (int)($_POST['cliente_id'] ?? 0);
    $agencia_id     = (int)($_POST['agencia_id'] ?? 0);
    $titulo         = trim($_POST['titulo'] ?? '');
    $programa       = trim($_POST['programa'] ?? '');
    $tour           = trim($_POST['tour'] ?? '');
    $duracion       = (int)($_POST['duracion_dias'] ?? 0);
    $personas       = (int)($_POST['personas'] ?? 1);
    $fecha_venta    = $_POST['fecha_venta'] ?? null;
    $fecha_inicio   = $_POST['fecha_inicio'] ?? null;
    $fecha_fin      = $_POST['fecha_fin'] ?? null;
    $moneda         = strtoupper(trim($_POST['moneda'] ?? 'PEN'));
    $monto_persona  = (float)($_POST['monto_persona'] ?? 0);
    $monto_total    = (float)($_POST['monto_total'] ?? ($personas * $monto_persona));
    $monto_depositado = (float)($_POST['monto_depositado'] ?? 0);
    $medio_pago     = trim($_POST['medio_pago'] ?? '');
    $origen_cliente = trim($_POST['origen_cliente'] ?? '');
    $estado         = trim($_POST['estado'] ?? 'abierto');
    $notas          = trim($_POST['notas'] ?? '');

    // archivo (opcional, reemplaza)
    $archivo_url = null;
    if (!empty($_FILES['archivo']['name']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
      $dir = "Views/Uploads/Expedientes";
      if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
      $ext = strtolower(pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION));
      $permit = ['pdf','jpg','jpeg','png'];
      if (!in_array($ext, $permit, true)) throw new Exception('Archivo no permitido (pdf/jpg/png).');
      $fname = 'EXP-' . time() . '.' . $ext;
      $dest = $dir . '/' . $fname;
      if (!move_uploaded_file($_FILES['archivo']['tmp_name'], $dest)) throw new Exception('No se pudo guardar el archivo.');
      $archivo_url = $dest;
    }

    $sql = "
      UPDATE expedientes
      SET responsable_id=?, cliente_id=?, agencia_id=?, titulo=?, programa=?, tour=?, duracion_dias=?, personas=?,
          fecha_venta=?, fecha_inicio=?, fecha_fin=?, moneda=?, monto_persona=?, monto_total=?, monto_depositado=?,
          medio_pago=?, origen_cliente=?, notas=?, estado=?, actualizado_en=NOW()";
    $args = [
      $responsable_id, $cliente_id, $agencia_id ?: null, $titulo, $programa, $tour, $duracion, $personas,
      $fecha_venta ?: null, $fecha_inicio ?: null, $fecha_fin ?: null, $moneda, $monto_persona, $monto_total, $monto_depositado,
      $medio_pago, $origen_cliente, $notas, $estado
    ];

    if ($archivo_url) { $sql .= ", archivo_url=?"; $args[] = $archivo_url; }

    $sql .= " WHERE id=?";
    $args[] = $id;

    $st = $pdo->prepare($sql);
    $st->execute($args);

    set_flash('success','Expediente actualizado.');
    header("Location: Expedientes"); exit;
  }

  if (($_POST['action'] ?? '') === 'delete') {
    if (!csrf_ok($_POST['csrf'] ?? '')) throw new Exception('CSRF inválido.');
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) throw new Exception('ID inválido.');
    $pdo->prepare("DELETE FROM expedientes WHERE id=?")->execute([$id]);
    set_flash('success','Expediente eliminado.');
    header("Location: Expedientes"); exit;
  }

} catch (Throwable $e) {
  set_flash('danger','Error: '.$e->getMessage());
  header("Location: Expedientes"); exit;
}

/* --- Listado + búsqueda --- */
$csrf = csrf_token();
$q = trim($_GET['q'] ?? '');
$where = '';
$params = [];

if ($q !== '') {
  $where = "WHERE (e.codigo LIKE ? OR e.titulo LIKE ? OR c.nombre LIKE ? OR c.apellido LIKE ? OR ag.nombre LIKE ?)";
  $like = "%{$q}%";
  $params = [$like, $like, $like, $like, $like];
}

$sqlList = "
  SELECT e.id, e.codigo, e.titulo, e.programa, e.tour, e.fecha_venta, e.monto_total, e.estado,
         CONCAT(c.nombre,' ',c.apellido) AS cliente,
         ag.nombre AS agencia
  FROM expedientes e
  LEFT JOIN clientes c ON c.id = e.cliente_id
  LEFT JOIN agencias ag ON ag.id = e.agencia_id
  $where
  ORDER BY e.id DESC
  LIMIT 200
";
$st = $pdo->prepare($sqlList);
$st->execute($params);
$rows = $st->fetchAll();

/* --- listas para selects --- */
$monedas = ['PEN'=>'PEN','USD'=>'USD','EUR'=>'EUR'];
$medios  = ['Efectivo','Tarjeta','Transferencia','Depósito','Yape/Plin'];
$estados = ['abierto'=>'Abierto','confirmado'=>'Confirmado','programado'=>'Programado','cerrado'=>'Cerrado','cancelado'=>'Cancelado'];
?>

<section class="content-header">
  <h1>Expedientes <small>Ventas</small></h1>
  <ol class="breadcrumb">
    <li><a href="Dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
    <li class="active">Expedientes</li>
  </ol>
</section>

<section class="content">
  <?php flash(); ?>

  <div class="box box-primary">
    <div class="box-header with-border">
      <form class="form-inline" method="get" action="Expedientes" style="margin:0;">
        <div class="form-group">
          <input type="text" name="q" class="form-control" placeholder="Buscar (código, cliente, título, agencia)" value="<?= htmlspecialchars($q) ?>" style="min-width:320px;">
        </div>
        <button class="btn btn-default"><i class="fa fa-search"></i></button>
        <button type="button" class="btn btn-success pull-right" data-toggle="modal" data-target="#modalNuevo">
          <i class="fa fa-plus"></i> Nuevo expediente
        </button>
      </form>
    </div>

    <div class="box-body table-responsive no-padding">
      <table class="table table-hover">
        <thead>
          <tr>
            <th style="width:110px;">Código</th>
            <th>Título / Programa</th>
            <th>Cliente</th>
            <th>Agencia</th>
            <th>Fecha venta</th>
            <th style="text-align:right;">Monto total</th>
            <th>Estado</th>
            <th style="width:150px;">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="8" class="text-center text-muted">Sin registros</td></tr>
          <?php else: foreach ($rows as $r): ?>
            <tr>
              <td><span class="label label-primary"><?= htmlspecialchars($r['codigo']) ?></span></td>
              <td>
                <strong><?= htmlspecialchars($r['titulo']) ?></strong>
                <div class="text-muted"><?= htmlspecialchars($r['programa'].' '.$r['tour']) ?></div>
              </td>
              <td><?= htmlspecialchars($r['cliente'] ?: '—') ?></td>
              <td><?= htmlspecialchars($r['agencia'] ?: '—') ?></td>
              <td><?= htmlspecialchars($r['fecha_venta']) ?></td>
              <td style="text-align:right;">S/ <?= number_format((float)$r['monto_total'], 2) ?></td>
              <td>
                <?php
                  $stClass = [
                    'abierto'     => 'default',
                    'confirmado'  => 'success',
                    'programado'  => 'info',
                    'cerrado'     => 'primary',
                    'cancelado'   => 'danger'
                  ];
                  $lbl = strtolower($r['estado'] ?? 'abierto');
                  $cl  = $stClass[$lbl] ?? 'default';
                ?>
                <span class="label label-<?= $cl ?>"><?= ucfirst($lbl) ?></span>
              </td>
              <td>
                <button class="btn btn-xs btn-warning" data-toggle="modal"
                        data-target="#modalEditar"
                        data-id="<?= (int)$r['id'] ?>"
                        data-titulo="<?= htmlspecialchars($r['titulo'], ENT_QUOTES) ?>">
                  <i class="fa fa-pencil"></i> Editar
                </button>

                <form method="post" action="Expedientes" style="display:inline"
                      onsubmit="return confirm('¿Eliminar expediente <?= htmlspecialchars($r['codigo']) ?>?');">
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
    <div class="box-footer"><small class="text-muted">Últimos 200 expedientes.</small></div>
  </div>
</section>

<!-- MODAL: NUEVO -->
<div class="modal fade" id="modalNuevo">
  <div class="modal-dialog modal-lg">
    <form class="modal-content" method="post" action="Expedientes" enctype="multipart/form-data" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="action" value="create">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title"><i class="fa fa-file-text-o"></i> Nuevo expediente</h4>
      </div>
      <div class="modal-body">
        <div class="row">
          <div class="col-sm-6">
            <div class="form-group">
              <label>Título *</label>
              <input type="text" name="titulo" class="form-control" required>
            </div>
            <div class="form-group">
              <label>Programa</label>
              <input type="text" name="programa" class="form-control">
            </div>
            <div class="form-group">
              <label>Tour</label>
              <input type="text" name="tour" class="form-control">
            </div>
            <div class="form-group">
              <label>Duración (días)</label>
              <input type="number" name="duracion_dias" class="form-control" min="0" value="0">
            </div>
            <div class="form-group">
              <label>Personas</label>
              <input type="number" name="personas" class="form-control" min="1" value="1">
            </div>
            <div class="form-group">
              <label>Moneda</label>
              <select name="moneda" class="form-control">
                <?php foreach ($monedas as $k=>$v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label>Monto por persona</label>
              <input type="number" step="0.01" name="monto_persona" class="form-control" value="0">
            </div>
            <div class="form-group">
              <label>Monto total (si lo dejas vacío se calcula personas * monto)</label>
              <input type="number" step="0.01" name="monto_total" class="form-control">
            </div>
            <div class="form-group">
              <label>Monto depositado</label>
              <input type="number" step="0.01" name="monto_depositado" class="form-control" value="0">
            </div>
          </div>

          <div class="col-sm-6">
            <div class="form-group">
              <label>Responsable *</label>
              <select name="responsable_id" class="form-control" required>
                <option value="">-- Seleccione --</option>
                <?php foreach ($responsables as $r): ?>
                <option value="<?= (int)$r['id'] ?>"><?= htmlspecialchars($r['nombre']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label>Cliente *</label>
              <select name="cliente_id" class="form-control" required>
                <option value="">-- Seleccione --</option>
                <?php foreach ($clientes as $c): ?>
                <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label>Agencia</label>
              <select name="agencia_id" class="form-control">
                <option value="">(Ninguna)</option>
                <?php foreach ($agencias as $a): ?>
                <option value="<?= (int)$a['id'] ?>"><?= htmlspecialchars($a['nombre']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label>Fecha de venta</label>
              <input type="date" name="fecha_venta" class="form-control">
            </div>
            <div class="form-group">
              <label>Fecha inicio / fin</label>
              <div class="row">
                <div class="col-xs-6"><input type="date" name="fecha_inicio" class="form-control"></div>
                <div class="col-xs-6"><input type="date" name="fecha_fin" class="form-control"></div>
              </div>
            </div>
            <div class="form-group">
              <label>Medio de pago</label>
              <select name="medio_pago" class="form-control">
                <option value="">-- Seleccione --</option>
                <?php foreach ($medios as $m): ?><option value="<?= $m ?>"><?= $m ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label>Origen del cliente</label>
              <input type="text" name="origen_cliente" class="form-control" placeholder="Facebook, Web, Referido...">
            </div>
            <div class="form-group">
              <label>Estado</label>
              <select name="estado" class="form-control">
                <?php foreach ($estados as $k=>$v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label>Notas</label>
              <textarea name="notas" class="form-control" rows="2"></textarea>
            </div>
            <div class="form-group">
              <label>Adjuntar archivo (pdf/jpg/png)</label>
              <input type="file" name="archivo" class="form-control">
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-default" data-dismiss="modal">Cancelar</button>
        <button class="btn btn-success"><i class="fa fa-save"></i> Guardar</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL: EDITAR (se completa por JS mínimo) -->
<div class="modal fade" id="modalEditar">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="Expedientes" enctype="multipart/form-data" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="id" id="e_id">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title">Editar expediente</h4>
      </div>
      <div class="modal-body">
        <p class="text-muted">Para una edición rápida, se expone solo campos clave. (Amplía según necesites.)</p>
        <div class="form-group">
          <label>Título *</label>
          <input type="text" name="titulo" id="e_titulo" class="form-control" required>
        </div>
        <div class="form-group">
          <label>Estado</label>
          <select name="estado" class="form-control">
            <?php foreach ($estados as $k=>$v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Monto total</label>
          <input type="number" step="0.01" name="monto_total" class="form-control">
        </div>
        <div class="form-group">
          <label>Adjuntar archivo (opcional)</label>
          <input type="file" name="archivo" class="form-control">
        </div>
        <hr>
        <div class="form-group">
          <label>Responsable</label>
          <select name="responsable_id" class="form-control">
            <?php foreach ($responsables as $r): ?>
              <option value="<?= (int)$r['id'] ?>"><?= htmlspecialchars($r['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Cliente</label>
          <select name="cliente_id" class="form-control">
            <?php foreach ($clientes as $c): ?>
              <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Agencia</label>
          <select name="agencia_id" class="form-control">
            <option value="">(Ninguna)</option>
            <?php foreach ($agencias as $a): ?>
              <option value="<?= (int)$a['id'] ?>"><?= htmlspecialchars($a['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Notas</label>
          <textarea name="notas" class="form-control" rows="2"></textarea>
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
// Rellenar modal editar (solo titulo + id; agrega más si deseas)
$('#modalEditar').on('show.bs.modal', function (e) {
  var btn = $(e.relatedTarget);
  $('#e_id').val(btn.data('id'));
  $('#e_titulo').val(btn.data('titulo'));
});
</script>
