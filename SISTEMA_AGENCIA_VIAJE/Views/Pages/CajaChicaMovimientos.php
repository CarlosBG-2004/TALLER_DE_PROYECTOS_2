<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once "Config/Database.php";

/* ===== Acceso ===== */
$areas = array_map('strtolower', $_SESSION['areas'] ?? []);
$rol   = $_SESSION['user']['rol'] ?? '';
if (!in_array('contabilidad', $areas, true) && !in_array($rol, ['Admin','Gerencia'], true)) {
?>
<section class="content-header"><h1>Caja chica — Movimientos <small>Acceso restringido</small></h1></section>
<section class="content">
  <div class="callout callout-danger">
    <h4>No autorizado</h4>
    <p>Necesitas permisos de <b>Contabilidad</b> o ser <b>Admin/Gerencia</b>.</p>
  </div>
</section>
<?php
  return;
}

/* ===== Helpers ===== */
function flash($k='flash'){ if(!empty($_SESSION[$k])){ echo $_SESSION[$k]; unset($_SESSION[$k]); } }
function set_flash($type,$msg,$k='flash'){ $_SESSION[$k] = '<div class="alert alert-'.$type.'">'.$msg.'</div>'; }
function csrf(){ if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(16)); return $_SESSION['csrf']; }
function csrf_ok($t){ return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t ?? ''); }

/* ===== DB & Autocuración ===== */
$pdo = Database::getConnection();

/* Tablas (por si aún no existen) */
$pdo->exec("
  CREATE TABLE IF NOT EXISTS caja_chica (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    responsable_id INT NULL,
    moneda ENUM('PEN','USD','EUR') NOT NULL DEFAULT 'PEN',
    saldo_inicial DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_en TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_cc_user FOREIGN KEY (responsable_id) REFERENCES usuarios(id) ON DELETE SET NULL
  ) ENGINE=InnoDB
");

$pdo->exec("
  CREATE TABLE IF NOT EXISTS caja_chica_movimientos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    caja_id INT NOT NULL,
    fecha DATE NOT NULL,
    tipo ENUM('ingreso','gasto') NOT NULL,
    monto DECIMAL(12,2) NOT NULL,
    moneda ENUM('PEN','USD','EUR') NOT NULL DEFAULT 'PEN',
    descripcion TEXT,
    comprobante_url VARCHAR(255),
    creado_en TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ccm_caja FOREIGN KEY (caja_id) REFERENCES caja_chica(id) ON DELETE CASCADE
  ) ENGINE=InnoDB
");

/* Si migras desde esquema antiguo, intenta añadir columnas faltantes (ignora si ya existen) */
try { $pdo->exec("ALTER TABLE caja_chica_movimientos ADD COLUMN IF NOT EXISTS moneda ENUM('PEN','USD','EUR') NOT NULL DEFAULT 'PEN' AFTER monto"); } catch(Throwable $e){}
try { $pdo->exec("ALTER TABLE caja_chica_movimientos ADD COLUMN IF NOT EXISTS comprobante_url VARCHAR(255) NULL"); } catch(Throwable $e){}
try { $pdo->exec("ALTER TABLE caja_chica_movimientos ADD COLUMN IF NOT EXISTS actualizado_en TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"); } catch(Throwable $e){}

/* ===== Caja seleccionada ===== */
$cajaId = isset($_GET['caja_id']) ? (int)$_GET['caja_id'] : 0;
if ($cajaId <= 0) {
?>
<section class="content-header"><h1>Caja chica — Movimientos</h1></section>
<section class="content">
  <div class="callout callout-warning">
    <h4>Seleccione una caja</h4>
    <p>Falta el parámetro <code>caja_id</code>. Vuelve a <a href="CajaChica">Caja Chica</a> y entra por <b>Movimientos</b>.</p>
  </div>
</section>
<?php
  return;
}

$st = $pdo->prepare("SELECT c.*, CONCAT(u.nombre,' ',u.apellido) AS responsable
                     FROM caja_chica c
                     LEFT JOIN usuarios u ON u.id = c.responsable_id
                     WHERE c.id = ? LIMIT 1");
$st->execute([$cajaId]);
$caja = $st->fetch();

if (!$caja) {
?>
<section class="content-header"><h1>Caja chica — Movimientos</h1></section>
<section class="content">
  <div class="callout callout-danger">
    <h4>Caja no encontrada</h4>
    <p>El ID proporcionado no corresponde a ninguna caja. Vuelve a <a href="CajaChica">Caja Chica</a>.</p>
  </div>
</section>
<?php
  return;
}

/* ===== Uploads ===== */
$rootPath   = dirname(__DIR__, 2);          // raíz del proyecto
$uploadDir  = $rootPath . '/Uploads/CajaChica/';
$uploadWeb  = 'Uploads/CajaChica/';
if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0775, true); }

/* ===== Acciones ===== */
try {
  /* Crear */
  if (($_POST['action'] ?? '') === 'create') {
    if (!csrf_ok($_POST['csrf'] ?? '')) throw new Exception('CSRF inválido.');

    $fecha = $_POST['fecha'] ?? date('Y-m-d');
    $tipo  = ($_POST['tipo'] ?? 'ingreso') === 'gasto' ? 'gasto' : 'ingreso';
    $monto = (float)($_POST['monto'] ?? 0);
    $desc  = trim($_POST['descripcion'] ?? '');

    if (!$fecha || $monto <= 0) throw new Exception('Fecha y monto válidos son obligatorios.');

    $moneda = $caja['moneda']; // usar moneda de la caja
    $compUrl = null;

    if (!empty($_FILES['comprobante']['name'])) {
      $okExt = ['pdf','jpg','jpeg','png'];
      $maxMB = 8;
      $tmp   = $_FILES['comprobante']['tmp_name'];
      $name  = $_FILES['comprobante']['name'];
      $size  = (int)$_FILES['comprobante']['size'];
      $ext   = strtolower(pathinfo($name, PATHINFO_EXTENSION));
      if (!in_array($ext, $okExt, true)) throw new Exception('Comprobante inválido (pdf/jpg/jpeg/png).');
      if ($size > $maxMB*1024*1024) throw new Exception('Comprobante supera '.$maxMB.'MB.');
      $newName = 'cc_'.$cajaId.'_'.date('Ymd_His').'_'.bin2hex(random_bytes(4)).'.'.$ext;
      if (!move_uploaded_file($tmp, $uploadDir.$newName)) throw new Exception('No se pudo guardar el comprobante.');
      $compUrl = $uploadWeb.$newName;
    }

    $st = $pdo->prepare("INSERT INTO caja_chica_movimientos (caja_id, fecha, tipo, monto, moneda, descripcion, comprobante_url)
                         VALUES (?,?,?,?,?,?,?)");
    $st->execute([$cajaId, $fecha, $tipo, $monto, $moneda, $desc ?: null, $compUrl]);

    set_flash('success','Movimiento registrado.');
    header("Location: CajaChicaMovimientos?caja_id=".$cajaId); exit;
  }

  /* Actualizar */
  if (($_POST['action'] ?? '') === 'update') {
    if (!csrf_ok($_POST['csrf'] ?? '')) throw new Exception('CSRF inválido.');
    $id    = (int)($_POST['id'] ?? 0);
    $fecha = $_POST['fecha'] ?? date('Y-m-d');
    $tipo  = ($_POST['tipo'] ?? 'ingreso') === 'gasto' ? 'gasto' : 'ingreso';
    $monto = (float)($_POST['monto'] ?? 0);
    $desc  = trim($_POST['descripcion'] ?? '');

    if ($id<=0) throw new Exception('ID inválido.');
    if (!$fecha || $monto <= 0) throw new Exception('Fecha y monto válidos son obligatorios.');

    $compSet = '';
    $params  = [$fecha, $tipo, $monto, $desc ?: null, $id, $cajaId];

    if (!empty($_FILES['comprobante']['name'])) {
      $okExt = ['pdf','jpg','jpeg','png'];
      $maxMB = 8;
      $tmp   = $_FILES['comprobante']['tmp_name'];
      $name  = $_FILES['comprobante']['name'];
      $size  = (int)$_FILES['comprobante']['size'];
      $ext   = strtolower(pathinfo($name, PATHINFO_EXTENSION));
      if (!in_array($ext, $okExt, true)) throw new Exception('Comprobante inválido (pdf/jpg/jpeg/png).');
      if ($size > $maxMB*1024*1024) throw new Exception('Comprobante supera '.$maxMB.'MB.');

      // Borrar anterior si existía
      $old = $pdo->prepare("SELECT comprobante_url FROM caja_chica_movimientos WHERE id=? AND caja_id=?");
      $old->execute([$id, $cajaId]);
      $oldRow = $old->fetch();
      if ($oldRow && !empty($oldRow['comprobante_url'])) {
        $oldFs = $rootPath . '/' . ltrim($oldRow['comprobante_url'], '/');
        if (is_file($oldFs)) { @unlink($oldFs); }
      }

      $newName = 'cc_'.$cajaId.'_'.date('Ymd_His').'_'.bin2hex(random_bytes(4)).'.'.$ext;
      if (!move_uploaded_file($tmp, $uploadDir.$newName)) throw new Exception('No se pudo guardar el comprobante.');
      $compUrl = $uploadWeb.$newName;

      $compSet = ", comprobante_url = ?";
      $params  = [$fecha, $tipo, $monto, $desc ?: null, $compUrl, $id, $cajaId];
    }

    $sql = "UPDATE caja_chica_movimientos
            SET fecha=?, tipo=?, monto=?, descripcion=? $compSet, actualizado_en=NOW()
            WHERE id=? AND caja_id=?";
    $st = $pdo->prepare($sql);
    $st->execute($params);

    set_flash('success','Movimiento actualizado.');
    header("Location: CajaChicaMovimientos?caja_id=".$cajaId); exit;
  }

  /* Eliminar */
  if (($_POST['action'] ?? '') === 'delete') {
    if (!csrf_ok($_POST['csrf'] ?? '')) throw new Exception('CSRF inválido.');
    $id = (int)($_POST['id'] ?? 0);
    if ($id<=0) throw new Exception('ID inválido.');

    // borrar archivo si existe
    $old = $pdo->prepare("SELECT comprobante_url FROM caja_chica_movimientos WHERE id=? AND caja_id=?");
    $old->execute([$id, $cajaId]);
    $oldRow = $old->fetch();
    if ($oldRow && !empty($oldRow['comprobante_url'])) {
      $oldFs = $rootPath . '/' . ltrim($oldRow['comprobante_url'], '/');
      if (is_file($oldFs)) { @unlink($oldFs); }
    }

    $pdo->prepare("DELETE FROM caja_chica_movimientos WHERE id=? AND caja_id=?")->execute([$id, $cajaId]);
    set_flash('success','Movimiento eliminado.');
    header("Location: CajaChicaMovimientos?caja_id=".$cajaId); exit;
  }

} catch (Throwable $e) {
  $code = ($e instanceof PDOException) ? $e->getCode() : '';
  set_flash('danger', 'Error: '.$e->getMessage().' '.($code==='23000'?'(integridad de datos)':'' ));
  header("Location: CajaChicaMovimientos?caja_id=".$cajaId); exit;
}

/* ===== Filtros ===== */
$csrf  = csrf();
$from  = $_GET['from'] ?? date('Y-m-01');
$to    = $_GET['to']   ?? date('Y-m-t');
$tipoF = $_GET['tipo'] ?? ''; // '', ingreso, gasto
$q     = trim($_GET['q'] ?? '');

$where = ["m.caja_id = ?"];
$args  = [$cajaId];

if ($from !== '') { $where[] = "m.fecha >= ?"; $args[] = $from; }
if ($to   !== '') { $where[] = "m.fecha <= ?"; $args[] = $to; }
if ($tipoF === 'ingreso' || $tipoF === 'gasto') { $where[] = "m.tipo = ?"; $args[] = $tipoF; }
if ($q !== '') { $where[] = "(m.descripcion LIKE ?)"; $args[] = '%'.$q.'%'; }

$wsql = 'WHERE '.implode(' AND ', $where);

/* ===== Totales / Saldos ===== */
$sum = $pdo->prepare("
  SELECT
    SUM(CASE WHEN m.tipo='ingreso' THEN m.monto ELSE 0 END) AS ingresos,
    SUM(CASE WHEN m.tipo='gasto'   THEN m.monto ELSE 0 END) AS gastos
  FROM caja_chica_movimientos m
  $wsql
");
$sum->execute($args);
$tot = $sum->fetch();
$ingresos = (float)($tot['ingresos'] ?? 0);
$gastos   = (float)($tot['gastos']   ?? 0);

/* Saldo histórico completo (sin filtros) */
$hist = $pdo->prepare("
  SELECT
    SUM(CASE WHEN tipo='ingreso' THEN monto ELSE 0 END) AS in_hist,
    SUM(CASE WHEN tipo='gasto'   THEN monto ELSE 0 END) AS ga_hist
  FROM caja_chica_movimientos
  WHERE caja_id = ?
");
$hist->execute([$cajaId]);
$h = $hist->fetch();
$saldoActual = (float)$caja['saldo_inicial'] + (float)($h['in_hist'] ?? 0) - (float)($h['ga_hist'] ?? 0);

/* Listado */
$st = $pdo->prepare("
  SELECT m.id, m.fecha, m.tipo, m.monto, m.moneda, m.descripcion, m.comprobante_url, m.creado_en
  FROM caja_chica_movimientos m
  $wsql
  ORDER BY m.fecha DESC, m.id DESC
  LIMIT 1000
");
$st->execute($args);
$movs = $st->fetchAll();

/* Etiqueta moneda */
function mlabel($m){
  if ($m === 'PEN') return 'S/.';
  if ($m === 'USD') return '$';
  if ($m === 'EUR') return '€';
  return $m;
}
?>
<section class="content-header">
  <h1>Caja chica — Movimientos <small><?= htmlspecialchars($caja['nombre']) ?> (<?= htmlspecialchars($caja['moneda']) ?>)</small></h1>
  <ol class="breadcrumb">
    <li><a href="Dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
    <li><a href="CajaChica"><i class="fa fa-briefcase"></i> Caja chica</a></li>
    <li class="active">Movimientos</li>
  </ol>
</section>

<section class="content">
  <?php flash(); ?>

  <div class="row">
    <div class="col-sm-4">
      <div class="small-box bg-green">
        <div class="inner">
          <h3><?= mlabel($caja['moneda']).' '.number_format($ingresos,2) ?></h3>
          <p>Ingresos (rango)</p>
        </div>
        <div class="icon"><i class="fa fa-arrow-circle-up"></i></div>
      </div>
    </div>
    <div class="col-sm-4">
      <div class="small-box bg-red">
        <div class="inner">
          <h3><?= mlabel($caja['moneda']).' '.number_format($gastos,2) ?></h3>
          <p>Gastos (rango)</p>
        </div>
        <div class="icon"><i class="fa fa-arrow-circle-down"></i></div>
      </div>
    </div>
    <div class="col-sm-4">
      <div class="small-box bg-aqua">
        <div class="inner">
          <h3><?= mlabel($caja['moneda']).' '.number_format($saldoActual,2) ?></h3>
          <p>Saldo actual (histórico)</p>
        </div>
        <div class="icon"><i class="fa fa-balance-scale"></i></div>
      </div>
    </div>
  </div>

  <div class="row">
    <!-- Nuevo movimiento -->
    <div class="col-md-4">
      <div class="box box-success">
        <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-plus"></i> Nuevo movimiento</h3></div>
        <form method="post" action="CajaChicaMovimientos?caja_id=<?= (int)$cajaId ?>" enctype="multipart/form-data" autocomplete="off">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
          <input type="hidden" name="action" value="create">
          <div class="box-body">
            <div class="form-group">
              <label>Fecha *</label>
              <input type="date" name="fecha" class="form-control" required value="<?= htmlspecialchars(date('Y-m-d')) ?>">
            </div>
            <div class="form-group">
              <label>Tipo *</label>
              <select name="tipo" class="form-control" required>
                <option value="ingreso">Ingreso</option>
                <option value="gasto">Gasto</option>
              </select>
            </div>
            <div class="form-group">
              <label>Monto (<?= htmlspecialchars(mlabel($caja['moneda'])) ?>) *</label>
              <input type="number" step="0.01" min="0.01" name="monto" class="form-control" required>
            </div>
            <div class="form-group">
              <label>Descripción</label>
              <textarea name="descripcion" class="form-control" rows="2" placeholder="Opcional"></textarea>
            </div>
            <div class="form-group">
              <label>Comprobante (pdf/jpg/png) máx 8MB</label>
              <input type="file" name="comprobante" class="form-control">
            </div>
          </div>
          <div class="box-footer">
            <button class="btn btn-success"><i class="fa fa-save"></i> Guardar</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Listado -->
    <div class="col-md-8">
      <div class="box box-primary">
        <div class="box-header with-border">
          <h3 class="box-title"><i class="fa fa-list-ul"></i> Movimientos</h3>
          <div class="box-tools">
            <form class="form-inline" method="get" action="CajaChicaMovimientos" style="margin:0;">
              <input type="hidden" name="caja_id" value="<?= (int)$cajaId ?>">
              <input type="date" class="form-control" name="from" value="<?= htmlspecialchars($from) ?>">
              <input type="date" class="form-control" name="to"   value="<?= htmlspecialchars($to) ?>">
              <select name="tipo" class="form-control">
                <option value="">Tipo: Todos</option>
                <option value="ingreso" <?= $tipoF==='ingreso'?'selected':'' ?>>Ingreso</option>
                <option value="gasto"   <?= $tipoF==='gasto'  ?'selected':'' ?>>Gasto</option>
              </select>
              <input type="text" class="form-control" name="q" placeholder="Buscar descripción…" value="<?= htmlspecialchars($q) ?>">
              <button class="btn btn-default"><i class="fa fa-search"></i></button>
            </form>
          </div>
        </div>

        <div class="box-body table-responsive no-padding">
          <table class="table table-hover">
            <thead>
              <tr>
                <th style="width:70px;">ID</th>
                <th>Fecha</th>
                <th>Tipo</th>
                <th>Monto</th>
                <th>Descripción</th>
                <th>Comprobante</th>
                <th style="width:210px;">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $st = $pdo->prepare("
                SELECT m.id, m.fecha, m.tipo, m.monto, m.moneda, m.descripcion, m.comprobante_url, m.creado_en
                FROM caja_chica_movimientos m
                $wsql
                ORDER BY m.fecha DESC, m.id DESC
                LIMIT 1000
              ");
              $st->execute($args);
              $rows = $st->fetchAll();

              if (empty($rows)): ?>
                <tr><td colspan="7" class="text-center text-muted">Sin movimientos</td></tr>
              <?php else: foreach ($rows as $m): ?>
              <tr>
                <td><?= (int)$m['id'] ?></td>
                <td><?= htmlspecialchars($m['fecha']) ?></td>
                <td>
                  <?php if ($m['tipo']==='ingreso'): ?>
                    <span class="label label-success">Ingreso</span>
                  <?php else: ?>
                    <span class="label label-danger">Gasto</span>
                  <?php endif; ?>
                </td>
                <td><strong><?= mlabel($m['moneda']).' '.number_format((float)$m['monto'], 2) ?></strong></td>
                <td><?= $m['descripcion'] ? htmlspecialchars($m['descripcion']) : '<span class="text-muted">—</span>' ?></td>
                <td>
                  <?php if (!empty($m['comprobante_url'])): ?>
                    <a class="btn btn-xs btn-default" href="<?= htmlspecialchars($m['comprobante_url']) ?>" target="_blank">
                      <i class="fa fa-paperclip"></i> Ver
                    </a>
                  <?php else: ?>
                    <span class="text-muted">—</span>
                  <?php endif; ?>
                </td>
                <td>
                  <button class="btn btn-xs btn-warning"
                          data-toggle="modal" data-target="#modalEditar"
                          data-id="<?= (int)$m['id'] ?>"
                          data-fecha="<?= htmlspecialchars($m['fecha']) ?>"
                          data-tipo="<?= htmlspecialchars($m['tipo']) ?>"
                          data-monto="<?= htmlspecialchars(number_format((float)$m['monto'], 2, '.', '')) ?>"
                          data-desc="<?= htmlspecialchars($m['descripcion'] ?? '', ENT_QUOTES) ?>">
                    <i class="fa fa-pencil"></i> Editar
                  </button>

                  <form method="post" action="CajaChicaMovimientos?caja_id=<?= (int)$cajaId ?>" style="display:inline;" onsubmit="return confirm('¿Eliminar movimiento?');">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                    <button class="btn btn-xs btn-danger"><i class="fa fa-trash"></i> Eliminar</button>
                  </form>
                </td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>

      </div>
    </div>
  </div>
</section>

<!-- MODAL EDITAR -->
<div class="modal fade" id="modalEditar">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="CajaChicaMovimientos?caja_id=<?= (int)$cajaId ?>" enctype="multipart/form-data" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="id" id="e_id">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title"><i class="fa fa-pencil"></i> Editar movimiento</h4>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label>Fecha *</label>
          <input type="date" name="fecha" id="e_fecha" class="form-control" required>
        </div>
        <div class="form-group">
          <label>Tipo *</label>
          <select name="tipo" id="e_tipo" class="form-control" required>
            <option value="ingreso">Ingreso</option>
            <option value="gasto">Gasto</option>
          </select>
        </div>
        <div class="form-group">
          <label>Monto (<?= htmlspecialchars(mlabel($caja['moneda'])) ?>) *</label>
          <input type="number" step="0.01" min="0.01" name="monto" id="e_monto" class="form-control" required>
        </div>
        <div class="form-group">
          <label>Descripción</label>
          <textarea name="descripcion" id="e_desc" class="form-control" rows="2"></textarea>
        </div>
        <div class="form-group">
          <label>Reemplazar comprobante (pdf/jpg/png) máx 8MB</label>
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
// Completar modal editar
$('#modalEditar').on('show.bs.modal', function (e) {
  var b = $(e.relatedTarget);
  $('#e_id').val(b.data('id'));
  $('#e_fecha').val(b.data('fecha'));
  $('#e_tipo').val(b.data('tipo'));
  $('#e_monto').val(b.data('monto'));
  $('#e_desc').val(b.data('desc') || '');
});
</script>
