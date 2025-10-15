<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once "Config/Database.php";

/* ===== Acceso: Operaciones, Admin o Gerencia ===== */
$areas = array_map('strtolower', $_SESSION['areas'] ?? []);
$rol   = $_SESSION['user']['rol'] ?? '';
if (!in_array('operaciones', $areas, true) && !in_array($rol, ['Admin','Gerencia'], true)) {
?>
<section class="content-header"><h1>Servicios de Operaciones <small>Acceso restringido</small></h1></section>
<section class="content">
  <div class="callout callout-danger">
    <h4>No autorizado</h4>
    <p>Requiere permisos de <b>Operaciones</b> o ser <b>Admin/Gerencia</b>.</p>
  </div>
</section>
<?php
  return;
}

/* ===== Utils ===== */
function flash_show($k='flash'){ if(!empty($_SESSION[$k])){ echo $_SESSION[$k]; unset($_SESSION[$k]); } }
function flash_set($type,$msg,$k='flash'){ $_SESSION[$k] = '<div class="alert alert-'.$type.'">'.$msg.'</div>'; }
function csrf_token(){ if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(16)); return $_SESSION['csrf']; }
function csrf_ok($t){ return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'],$t ?? ''); }

$pdo = Database::getConnection();

/* ===== Catálogos ===== */
$tipos = [
  'tren'       => 'Tren',
  'hotel'      => 'Hotel',
  'entrada'    => 'Entrada',
  'transporte' => 'Transporte',
  'guia'       => 'Guía',
  'tour'       => 'Tour',
  'otro'       => 'Otro'
];

$proveedores = $pdo->query("SELECT id, nombre FROM proveedores ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);

/* Expedientes (ventas) para el selector */
$expedientes = $pdo->query("
  SELECT e.id,
         CONCAT(c.nombre,' ',c.apellido) AS cliente,
         e.programa, e.tour, e.fecha_tour_inicio
  FROM expedientes e
  JOIN clientes c ON c.id = e.cliente_id
  ORDER BY e.id DESC
  LIMIT 300
")->fetchAll(PDO::FETCH_ASSOC);

/* ===== Modo edición ===== */
$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editRow = null;
if ($editId > 0) {
  $st = $pdo->prepare("SELECT * FROM servicios_operaciones WHERE id=? LIMIT 1");
  $st->execute([$editId]);
  $editRow = $st->fetch(PDO::FETCH_ASSOC);
}

/* ===== Acciones ===== */
try {
  if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!csrf_ok($_POST['csrf'] ?? '')) { throw new Exception('CSRF inválido.'); }
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
      $id              = (int)($_POST['id'] ?? 0);
      $expediente_id   = (int)($_POST['expediente_id'] ?? 0);
      $tipo            = $_POST['tipo'] ?? '';
      $proveedor_id    = (int)($_POST['proveedor_id'] ?? 0);
      $descripcion     = trim($_POST['descripcion'] ?? '');
      $costo_acordado  = (float)($_POST['costo_acordado'] ?? 0);
      $monto_pagado    = (float)($_POST['monto_pagado'] ?? 0);
      $monto_depositado= (float)($_POST['monto_depositado'] ?? 0);
      $reservado       = isset($_POST['reservado']) ? 1 : 0;
      $voucher_url     = trim($_POST['voucher_url'] ?? '');
      $conf_codigo     = trim($_POST['confirmacion_codigo'] ?? '');

      if (!$expediente_id || !$proveedor_id || !isset($tipos[$tipo])) throw new Exception('Datos incompletos.');
      if ($costo_acordado < 0 || $monto_pagado < 0 || $monto_depositado < 0) throw new Exception('Montos inválidos.');

      if ($action==='create') {
        $st = $pdo->prepare("
          INSERT INTO servicios_operaciones
            (expediente_id, tipo, proveedor_id, descripcion,
             costo_acordado, monto_depositado, monto_pagado,
             reservado, reservado_en, confirmacion_codigo, voucher_url)
          VALUES (?,?,?,?,?,?,?,?,?,?,?)
        ");
        $st->execute([
          $expediente_id, $tipo, $proveedor_id, $descripcion ?: null,
          $costo_acordado, $monto_depositado, $monto_pagado,
          $reservado, $reservado ? date('Y-m-d H:i:s') : null, $conf_codigo ?: null, $voucher_url ?: null
        ]);
        flash_set('success','Servicio creado correctamente.');
      } else {
        if ($id<=0) throw new Exception('ID inválido.');
        $reservado_en = null;
        if ($reservado) {
          // Si marcamos reservado, setea fecha si no la tiene
          $chk = $pdo->prepare("SELECT reservado, reservado_en FROM servicios_operaciones WHERE id=?");
          $chk->execute([$id]);
          $prev = $chk->fetch(PDO::FETCH_ASSOC);
          $reservado_en = !empty($prev['reservado_en']) ? $prev['reservado_en'] : date('Y-m-d H:i:s');
        }
        $st = $pdo->prepare("
          UPDATE servicios_operaciones
             SET expediente_id=?, tipo=?, proveedor_id=?, descripcion=?,
                 costo_acordado=?, monto_depositado=?, monto_pagado=?,
                 reservado=?, reservado_en=?, confirmacion_codigo=?, voucher_url=?
           WHERE id=?
        ");
        $st->execute([
          $expediente_id, $tipo, $proveedor_id, $descripcion ?: null,
          $costo_acordado, $monto_depositado, $monto_pagado,
          $reservado, $reservado ? $reservado_en : null, $conf_codigo ?: null, $voucher_url ?: null,
          $id
        ]);
        flash_set('success','Servicio actualizado.');
      }
      header("Location: ServiciosOperaciones"); exit;
    }

    if ($action === 'abonar') {
      $id    = (int)($_POST['id'] ?? 0);
      $abono = (float)($_POST['abono'] ?? 0);
      if ($id<=0 || $abono<=0) throw new Exception('Datos inválidos.');
      $st = $pdo->prepare("UPDATE servicios_operaciones SET monto_pagado = monto_pagado + ? WHERE id=?");
      $st->execute([$abono, $id]);
      flash_set('success','Abono registrado.');
      header("Location: ServiciosOperaciones"); exit;
    }

    if ($action === 'toggle_reservado') {
      $id = (int)($_POST['id'] ?? 0);
      if ($id<=0) throw new Exception('ID inválido.');
      // Alternar y setear reservado_en si pasa a 1
      $pdo->beginTransaction();
      $row = $pdo->prepare("SELECT reservado FROM servicios_operaciones WHERE id=? FOR UPDATE");
      $row->execute([$id]);
      $cur = $row->fetch(PDO::FETCH_ASSOC);
      if (!$cur) { $pdo->rollBack(); throw new Exception('Registro no encontrado'); }
      if ((int)$cur['reservado'] === 1) {
        $pdo->prepare("UPDATE servicios_operaciones SET reservado=0, reservado_en=NULL WHERE id=?")->execute([$id]);
      } else {
        $pdo->prepare("UPDATE servicios_operaciones SET reservado=1, reservado_en=? WHERE id=?")
            ->execute([date('Y-m-d H:i:s'), $id]);
      }
      $pdo->commit();
      flash_set('success','Estado de reserva actualizado.');
      header("Location: ServiciosOperaciones"); exit;
    }

    if ($action === 'delete') {
      $id = (int)($_POST['id'] ?? 0);
      if ($id<=0) throw new Exception('ID inválido.');
      $pdo->prepare("DELETE FROM servicios_operaciones WHERE id=?")->execute([$id]);
      flash_set('success','Servicio eliminado.');
      header("Location: ServiciosOperaciones"); exit;
    }
  }
} catch (Throwable $e) {
  flash_set('danger','Error: '.$e->getMessage());
  header("Location: ServiciosOperaciones".($editId?("?edit=".$editId):"")); exit;
}

/* ===== Filtros ===== */
$ftipo      = $_GET['ftipo']      ?? '';
$freservado = $_GET['freservado'] ?? '';
$fprov      = (int)($_GET['fprov'] ?? 0);
$fexp       = (int)($_GET['fexp']  ?? 0);
$fq         = trim($_GET['q'] ?? '');

$where = ["1=1"];
$args  = [];
if (isset($tipos[$ftipo])) { $where[] = "s.tipo=?"; $args[] = $ftipo; }
if ($freservado==='1' || $freservado==='0') { $where[] = "s.reservado=?"; $args[] = (int)$freservado; }
if ($fprov>0) { $where[] = "s.proveedor_id=?"; $args[] = $fprov; }
if ($fexp>0)  { $where[] = "s.expediente_id=?"; $args[] = $fexp; }
if ($fq!=='') {
  $where[] = "(c.nombre LIKE ? OR c.apellido LIKE ? OR e.programa LIKE ? OR e.tour LIKE ?)";
  $args[] = "%$fq%"; $args[] = "%$fq%"; $args[] = "%$fq%"; $args[] = "%$fq%";
}
$wsql = 'WHERE '.implode(' AND ', $where);

/* ===== Listado ===== */
$st = $pdo->prepare("
  SELECT s.id, s.expediente_id, s.tipo, s.proveedor_id, p.nombre AS proveedor,
         s.descripcion, s.costo_acordado, s.monto_depositado, s.monto_pagado,
         s.saldo, s.reservado, s.reservado_en, s.confirmacion_codigo, s.voucher_url, s.creado_en,
         e.programa, e.tour, e.fecha_tour_inicio,
         CONCAT(c.nombre,' ',c.apellido) AS cliente
  FROM servicios_operaciones s
  JOIN proveedores p ON p.id = s.proveedor_id
  JOIN expedientes   e ON e.id = s.expediente_id
  JOIN clientes      c ON c.id = e.cliente_id
  $wsql
  ORDER BY s.id DESC
  LIMIT 500
");
$st->execute($args);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

$csrf = csrf_token();
?>
<section class="content-header">
  <h1>Servicios de Operaciones <small>Reservas y costos</small></h1>
  <ol class="breadcrumb">
    <li><a href="Dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
    <li class="active">Servicios / Reservas</li>
  </ol>
</section>

<section class="content">
  <?php flash_show(); ?>

  <!-- Formulario crear/editar -->
  <div class="box box-primary">
    <div class="box-header with-border">
      <h3 class="box-title">
        <i class="fa fa-list-alt"></i> <?php echo $editRow ? 'Editar servicio #'.(int)$editRow['id'] : 'Nuevo servicio'; ?>
      </h3>
      <?php if ($editRow): ?>
        <div class="box-tools"><a class="btn btn-default btn-sm" href="ServiciosOperaciones"><i class="fa fa-plus"></i> Nuevo</a></div>
      <?php endif; ?>
    </div>

    <form method="post" action="ServiciosOperaciones" autocomplete="off">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
      <input type="hidden" name="action" value="<?php echo $editRow ? 'update':'create'; ?>">
      <?php if ($editRow): ?><input type="hidden" name="id" value="<?php echo (int)$editRow['id']; ?>"><?php endif; ?>

      <div class="box-body">
        <div class="row">
          <div class="col-md-4">
            <div class="form-group">
              <label>Expediente *</label>
              <select name="expediente_id" class="form-control" required>
                <option value="">-- Seleccione --</option>
                <?php foreach($expedientes as $e): ?>
                <option value="<?php echo (int)$e['id']; ?>"
                  <?php echo ($editRow && (int)$editRow['expediente_id']===(int)$e['id'])?'selected':''; ?>>
                  #<?php echo (int)$e['id']; ?> — <?php echo htmlspecialchars($e['cliente']); ?> — <?php echo htmlspecialchars($e['programa'] ?: $e['tour'] ?: ''); ?> (<?php echo htmlspecialchars($e['fecha_tour_inicio']); ?>)
                </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="col-md-2">
            <div class="form-group">
              <label>Tipo *</label>
              <select name="tipo" class="form-control" required>
                <?php foreach($tipos as $k=>$v): ?>
                  <option value="<?php echo $k; ?>" <?php echo ($editRow && $editRow['tipo']===$k)?'selected':''; ?>><?php echo $v; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="col-md-3">
            <div class="form-group">
              <label>Proveedor *</label>
              <select name="proveedor_id" class="form-control" required>
                <option value="">-- Seleccione --</option>
                <?php foreach($proveedores as $p): ?>
                  <option value="<?php echo (int)$p['id']; ?>"
                    <?php echo ($editRow && (int)$editRow['proveedor_id']===(int)$p['id'])?'selected':''; ?>>
                    <?php echo htmlspecialchars($p['nombre']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="col-md-3">
            <label>&nbsp;</label>
            <div class="checkbox">
              <label>
                <input type="checkbox" name="reservado" value="1" <?php echo ($editRow && (int)$editRow['reservado']===1)?'checked':''; ?>>
                Marcar como reservado
              </label>
            </div>
          </div>
        </div><!-- row -->

        <div class="row">
          <div class="col-md-3">
            <div class="form-group">
              <label>Costo acordado *</label>
              <input type="number" step="0.01" min="0" class="form-control" name="costo_acordado" required
                     value="<?php echo htmlspecialchars($editRow['costo_acordado'] ?? '0'); ?>">
            </div>
          </div>
          <div class="col-md-3">
            <div class="form-group">
              <label>Monto depositado</label>
              <input type="number" step="0.01" min="0" class="form-control" name="monto_depositado"
                     value="<?php echo htmlspecialchars($editRow['monto_depositado'] ?? '0'); ?>">
            </div>
          </div>
          <div class="col-md-3">
            <div class="form-group">
              <label>Monto pagado</label>
              <input type="number" step="0.01" min="0" class="form-control" name="monto_pagado"
                     value="<?php echo htmlspecialchars($editRow['monto_pagado'] ?? '0'); ?>">
            </div>
          </div>
          <div class="col-md-3">
            <div class="form-group">
              <label>Saldo (auto)</label>
              <input type="text" class="form-control" disabled
                     value="<?php
                      $s = $editRow ? (float)$editRow['saldo'] : 0;
                      echo number_format($s,2);
                     ?>">
            </div>
          </div>
        </div><!-- row -->

        <div class="row">
          <div class="col-md-4">
            <div class="form-group">
              <label>Confirmación/Código</label>
              <input type="text" class="form-control" name="confirmacion_codigo"
                     value="<?php echo htmlspecialchars($editRow['confirmacion_codigo'] ?? ''); ?>">
            </div>
          </div>
          <div class="col-md-8">
            <div class="form-group">
              <label>Voucher URL</label>
              <input type="url" class="form-control" name="voucher_url"
                     placeholder="https://enlace-a-voucher.pdf"
                     value="<?php echo htmlspecialchars($editRow['voucher_url'] ?? ''); ?>">
            </div>
          </div>
        </div><!-- row -->

        <div class="form-group">
          <label>Descripción</label>
          <textarea name="descripcion" rows="2" class="form-control"
            placeholder="Notas del servicio (p. ej. tramo, horarios, etc.)"><?php
              echo htmlspecialchars($editRow['descripcion'] ?? '');
            ?></textarea>
        </div>
      </div>

      <div class="box-footer">
        <?php if ($editRow): ?>
          <a href="ServiciosOperaciones" class="btn btn-default">Cancelar</a>
          <button class="btn btn-primary"><i class="fa fa-save"></i> Guardar cambios</button>
        <?php else: ?>
          <button class="btn btn-success"><i class="fa fa-plus"></i> Crear servicio</button>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- Filtros -->
  <div class="box box-default">
    <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-filter"></i> Filtros</h3></div>
    <div class="box-body">
      <form class="form-inline" method="get" action="ServiciosOperaciones" style="margin:0;">
        <label>Tipo</label>
        <select name="ftipo" class="form-control">
          <option value="">Todos</option>
          <?php foreach($tipos as $k=>$v): ?>
            <option value="<?php echo $k; ?>" <?php echo ($ftipo===$k)?'selected':''; ?>><?php echo $v; ?></option>
          <?php endforeach; ?>
        </select>

        <label>Reservado</label>
        <select name="freservado" class="form-control">
          <option value="">Todos</option>
          <option value="1" <?php echo $freservado==='1'?'selected':''; ?>>Sí</option>
          <option value="0" <?php echo $freservado==='0'?'selected':''; ?>>No</option>
        </select>

        <label>Proveedor</label>
        <select name="fprov" class="form-control">
          <option value="0">Todos</option>
          <?php foreach($proveedores as $p): ?>
            <option value="<?php echo (int)$p['id']; ?>" <?php echo ($fprov===(int)$p['id'])?'selected':''; ?>>
              <?php echo htmlspecialchars($p['nombre']); ?>
            </option>
          <?php endforeach; ?>
        </select>

        <label>Expediente</label>
        <select name="fexp" class="form-control">
          <option value="0">Todos</option>
          <?php foreach($expedientes as $e): ?>
            <option value="<?php echo (int)$e['id']; ?>" <?php echo ($fexp===(int)$e['id'])?'selected':''; ?>>
              #<?php echo (int)$e['id']; ?> — <?php echo htmlspecialchars($e['cliente']); ?>
            </option>
          <?php endforeach; ?>
        </select>

        <input type="text" class="form-control" name="q" placeholder="Buscar cliente/programa/tour…" value="<?php echo htmlspecialchars($fq); ?>">
        <button class="btn btn-default"><i class="fa fa-search"></i> Filtrar</button>
      </form>
    </div>
  </div>

  <!-- Listado -->
  <div class="box box-info">
    <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-table"></i> Servicios (máx. 500)</h3></div>
    <div class="box-body table-responsive no-padding">
      <table class="table table-hover">
        <thead>
          <tr>
            <th>ID</th>
            <th>Expediente</th>
            <th>Cliente</th>
            <th>Programa / Tour</th>
            <th>Tipo</th>
            <th>Proveedor</th>
            <th>Costo</th>
            <th>Depositado</th>
            <th>Pagado</th>
            <th>Saldo</th>
            <th>Reservado</th>
            <th>Voucher</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="13" class="text-center text-muted">Sin registros</td></tr>
          <?php else: foreach($rows as $r): ?>
            <tr>
              <td>#<?php echo (int)$r['id']; ?></td>
              <td><span class="label label-default">#<?php echo (int)$r['expediente_id']; ?></span></td>
              <td><?php echo htmlspecialchars($r['cliente']); ?></td>
              <td>
                <?php echo htmlspecialchars($r['programa'] ?: $r['tour'] ?: '—'); ?>
                <br><small class="text-muted"><?php echo htmlspecialchars($r['fecha_tour_inicio']); ?></small>
              </td>
              <td><?php echo htmlspecialchars(ucfirst($r['tipo'])); ?></td>
              <td><?php echo htmlspecialchars($r['proveedor']); ?></td>
              <td><b><?php echo number_format((float)$r['costo_acordado'],2); ?></b></td>
              <td><?php echo number_format((float)$r['monto_depositado'],2); ?></td>
              <td><?php echo number_format((float)$r['monto_pagado'],2); ?></td>
              <td>
                <?php $saldo = (float)$r['saldo']; ?>
                <?php if ($saldo > 0): ?>
                  <span class="label label-warning"><?php echo number_format($saldo,2); ?></span>
                <?php else: ?>
                  <span class="label label-success">0.00</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ((int)$r['reservado'] === 1): ?>
                  <span class="label label-success">Sí</span>
                <?php else: ?>
                  <span class="label label-default">No</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if (!empty($r['voucher_url'])): ?>
                  <a class="btn btn-xs btn-default" href="<?php echo htmlspecialchars($r['voucher_url']); ?>" target="_blank">
                    <i class="fa fa-paperclip"></i> Ver
                  </a>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <td style="min-width:260px;">
                <!-- Abono -->
                <form method="post" action="ServiciosOperaciones" class="form-inline" style="display:inline;">
                  <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                  <input type="hidden" name="action" value="abonar">
                  <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                  <div class="input-group input-group-sm" style="width:130px;">
                    <input type="number" step="0.01" min="0" class="form-control" name="abono" placeholder="Abonar">
                    <span class="input-group-btn">
                      <button class="btn btn-primary btn-sm" title="Registrar abono"><i class="fa fa-check"></i></button>
                    </span>
                  </div>
                </form>

                <!-- Reservado -->
                <form method="post" action="ServiciosOperaciones" style="display:inline;">
                  <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                  <input type="hidden" name="action" value="toggle_reservado">
                  <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                  <button class="btn btn-default btn-sm" title="Cambiar reservado">
                    <i class="fa fa-flag"></i>
                  </button>
                </form>

                <!-- Editar -->
                <a class="btn btn-sm btn-warning" href="ServiciosOperaciones?edit=<?php echo (int)$r['id']; ?>" title="Editar">
                  <i class="fa fa-pencil"></i>
                </a>

                <!-- Eliminar -->
                <form method="post" action="ServiciosOperaciones" style="display:inline;" onsubmit="return confirm('¿Eliminar servicio #<?php echo (int)$r['id']; ?>?');">
                  <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                  <button class="btn btn-sm btn-danger" title="Eliminar"><i class="fa fa-trash"></i></button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <div class="box-footer">
      <small class="text-muted">Máximo 500 resultados. Usa los filtros para refinar.</small>
    </div>
  </div>
</section>