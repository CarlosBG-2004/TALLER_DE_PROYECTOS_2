<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once "Config/Database.php";

/* ===== Acceso ===== */
$areas = array_map('strtolower', $_SESSION['areas'] ?? []);
$rol   = $_SESSION['user']['rol'] ?? '';
if (!in_array('contabilidad', $areas, true) && !in_array($rol, ['Admin','Gerencia'], true)) {
?>
<section class="content-header"><h1>Caja chica <small>Acceso restringido</small></h1></section>
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

/* ===== DB / Ensure tables ===== */
$pdo = Database::getConnection();

/* Crea tabla de cajas si no existe */
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

/* Crea tabla de movimientos si no existe (usada para calcular saldo actual) */
$pdo->exec("
  CREATE TABLE IF NOT EXISTS caja_chica_movimientos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    caja_id INT NOT NULL,
    fecha DATE NOT NULL,
    tipo ENUM('ingreso','gasto') NOT NULL,
    monto DECIMAL(12,2) NOT NULL,
    moneda ENUM('PEN','USD','EUR') NOT NULL,
    descripcion TEXT,
    comprobante_url VARCHAR(255),
    creado_en TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ccm_caja FOREIGN KEY (caja_id) REFERENCES caja_chica(id) ON DELETE CASCADE
  ) ENGINE=InnoDB
");

/* ===== Usuarios (responsables) ===== */
$usuarios = $pdo->query("
  SELECT id, CONCAT(nombre,' ',apellido) AS nombre
  FROM usuarios
  WHERE activo=1
  ORDER BY nombre, apellido
")->fetchAll();

/* ===== Acciones ===== */
try {
  /* Crear caja */
  if (($_POST['action'] ?? '') === 'create') {
    if (!csrf_ok($_POST['csrf'] ?? '')) throw new Exception('CSRF inválido.');
    $nombre   = trim($_POST['nombre'] ?? '');
    $respId   = $_POST['responsable_id'] !== '' ? (int)$_POST['responsable_id'] : null;
    $moneda   = $_POST['moneda'] ?? 'PEN';
    $saldoIni = (float)($_POST['saldo_inicial'] ?? 0);

    if ($nombre === '') throw new Exception('El nombre de la caja es obligatorio.');
    if (!in_array($moneda, ['PEN','USD','EUR'], true)) $moneda = 'PEN';

    $st = $pdo->prepare("INSERT INTO caja_chica (nombre, responsable_id, moneda, saldo_inicial) VALUES (?,?,?,?)");
    $st->execute([$nombre, $respId, $moneda, $saldoIni]);

    set_flash('success','Caja creada correctamente.');
    header("Location: CajaChica"); exit;
  }

  /* Actualizar caja */
  if (($_POST['action'] ?? '') === 'update') {
    if (!csrf_ok($_POST['csrf'] ?? '')) throw new Exception('CSRF inválido.');
    $id       = (int)($_POST['id'] ?? 0);
    $nombre   = trim($_POST['nombre'] ?? '');
    $respId   = $_POST['responsable_id'] !== '' ? (int)$_POST['responsable_id'] : null;
    $moneda   = $_POST['moneda'] ?? 'PEN';
    $saldoIni = (float)($_POST['saldo_inicial'] ?? 0);

    if ($id<=0) throw new Exception('ID inválido.');
    if ($nombre === '') throw new Exception('El nombre de la caja es obligatorio.');
    if (!in_array($moneda, ['PEN','USD','EUR'], true)) $moneda = 'PEN';

    $st = $pdo->prepare("
      UPDATE caja_chica
      SET nombre=?, responsable_id=?, moneda=?, saldo_inicial=?, actualizado_en=NOW()
      WHERE id=?
    ");
    $st->execute([$nombre, $respId, $moneda, $saldoIni, $id]);

    set_flash('success','Caja actualizada.');
    header("Location: CajaChica"); exit;
  }

  /* Activar/Desactivar */
  if (($_POST['action'] ?? '') === 'toggle') {
    if (!csrf_ok($_POST['csrf'] ?? '')) throw new Exception('CSRF inválido.');
    $id     = (int)($_POST['id'] ?? 0);
    $activo = (int)($_POST['activo'] ?? 0) ? 1 : 0;
    if ($id<=0) throw new Exception('ID inválido.');
    $pdo->prepare("UPDATE caja_chica SET activo=?, actualizado_en=NOW() WHERE id=?")->execute([$activo, $id]);
    set_flash('success', $activo ? 'Caja activada.' : 'Caja desactivada.');
    header("Location: CajaChica"); exit;
  }

  /* Eliminar (si no tiene movimientos) */
  if (($_POST['action'] ?? '') === 'delete') {
    if (!csrf_ok($_POST['csrf'] ?? '')) throw new Exception('CSRF inválido.');
    $id = (int)($_POST['id'] ?? 0);
    if ($id<=0) throw new Exception('ID inválido.');

    $cnt = $pdo->prepare("SELECT COUNT(*) FROM caja_chica_movimientos WHERE caja_id=?");
    $cnt->execute([$id]);
    if ((int)$cnt->fetchColumn() > 0) {
      throw new Exception('No se puede eliminar: la caja tiene movimientos.');
    }

    $pdo->prepare("DELETE FROM caja_chica WHERE id=?")->execute([$id]);
    set_flash('success','Caja eliminada.');
    header("Location: CajaChica"); exit;
  }

} catch (Throwable $e) {
  $code = ($e instanceof PDOException) ? $e->getCode() : '';
  if ($code === '23000') set_flash('danger','Conflicto de datos (posible duplicado).');
  else set_flash('danger','Error: '.$e->getMessage());
  header("Location: CajaChica"); exit;
}

/* ===== Filtros & Listado ===== */
$csrf = csrf();

$q       = trim($_GET['q'] ?? '');
$mon     = $_GET['moneda'] ?? '';
$estado  = $_GET['estado'] ?? ''; // '', 1, 0

$where = [];
$args  = [];

if ($q !== '') {
  $where[] = "(c.nombre LIKE ? OR u.nombre LIKE ? OR u.apellido LIKE ?)";
  $like = "%{$q}%";
  array_push($args, $like, $like, $like);
}
if (in_array($mon, ['PEN','USD','EUR'], true)) {
  $where[] = "c.moneda = ?";
  $args[]  = $mon;
}
if ($estado === '1' || $estado === '0') {
  $where[] = "c.activo = ?";
  $args[]  = (int)$estado;
}
$wsql = $where ? 'WHERE '.implode(' AND ', $where) : '';

/* Saldos por movimientos (por caja) */
$movs = $pdo->query("
  SELECT caja_id, SUM(CASE WHEN tipo='ingreso' THEN monto ELSE -monto END) AS mov
  FROM caja_chica_movimientos
  GROUP BY caja_id
")->fetchAll();
$sumMov = [];
foreach ($movs as $m) { $sumMov[(int)$m['caja_id']] = (float)$m['mov']; }

/* Cajas */
$st = $pdo->prepare("
  SELECT c.id, c.nombre, c.responsable_id, c.moneda, c.saldo_inicial, c.activo,
         c.creado_en, c.actualizado_en,
         CONCAT(u.nombre,' ',u.apellido) AS responsable
  FROM caja_chica c
  LEFT JOIN usuarios u ON u.id = c.responsable_id
  $wsql
  ORDER BY c.activo DESC, c.nombre ASC
");
$st->execute($args);
$cajas = $st->fetchAll();

/* Estadísticas */
$tot = count($cajas);
$act = 0; $ina = 0;
foreach ($cajas as $c) { if ((int)$c['activo']===1) $act++; else $ina++; }
?>
<section class="content-header">
  <h1>Contabilidad <small>Caja chica</small></h1>
  <ol class="breadcrumb">
    <li><a href="Dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
    <li class="active">Caja chica</li>
  </ol>
</section>

<section class="content">
  <?php flash(); ?>

  <div class="row">
    <div class="col-sm-4">
      <div class="small-box bg-aqua">
        <div class="inner">
          <h3><?= (int)$tot ?></h3>
          <p>Total de Cajas</p>
        </div>
        <div class="icon"><i class="fa fa-briefcase"></i></div>
      </div>
    </div>
    <div class="col-sm-4">
      <div class="small-box bg-green">
        <div class="inner">
          <h3><?= (int)$act ?></h3>
          <p>Activas</p>
        </div>
        <div class="icon"><i class="fa fa-toggle-on"></i></div>
      </div>
    </div>
    <div class="col-sm-4">
      <div class="small-box bg-gray">
        <div class="inner">
          <h3><?= (int)$ina ?></h3>
          <p>Inactivas</p>
        </div>
        <div class="icon"><i class="fa fa-toggle-off"></i></div>
      </div>
    </div>
  </div>

  <div class="row">
    <!-- Nueva caja -->
    <div class="col-md-4">
      <div class="box box-success">
        <div class="box-header with-border">
          <h3 class="box-title"><i class="fa fa-plus"></i> Nueva caja</h3>
        </div>
        <form method="post" action="CajaChica" autocomplete="off">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
          <input type="hidden" name="action" value="create">
          <div class="box-body">
            <div class="form-group">
              <label>Nombre *</label>
              <input type="text" name="nombre" class="form-control" required placeholder="Ej. Caja Oficina Principal">
            </div>
            <div class="form-group">
              <label>Responsable</label>
              <select name="responsable_id" class="form-control">
                <option value="">-- Sin asignar --</option>
                <?php foreach ($usuarios as $u): ?>
                <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['nombre']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label>Moneda *</label>
              <select name="moneda" class="form-control" required>
                <option value="PEN">PEN (S/.)</option>
                <option value="USD">USD ($)</option>
                <option value="EUR">EUR (€)</option>
              </select>
            </div>
            <div class="form-group">
              <label>Saldo inicial *</label>
              <input type="number" step="0.01" min="0" name="saldo_inicial" class="form-control" required value="0.00">
            </div>
          </div>
          <div class="box-footer">
            <button class="btn btn-success"><i class="fa fa-save"></i> Crear</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Listado -->
    <div class="col-md-8">
      <div class="box box-primary">
        <div class="box-header with-border">
          <h3 class="box-title"><i class="fa fa-briefcase"></i> Cajas</h3>
          <div class="box-tools">
            <form class="form-inline" method="get" action="CajaChica" style="margin:0;">
              <input type="text" class="form-control" name="q" placeholder="Buscar caja/responsable…" value="<?= htmlspecialchars($q) ?>">
              <select name="moneda" class="form-control">
                <option value="">Moneda: Todas</option>
                <option value="PEN" <?= $mon==='PEN'?'selected':'' ?>>PEN</option>
                <option value="USD" <?= $mon==='USD'?'selected':'' ?>>USD</option>
                <option value="EUR" <?= $mon==='EUR'?'selected':'' ?>>EUR</option>
              </select>
              <select name="estado" class="form-control">
                <option value="">Estado: Todos</option>
                <option value="1" <?= $estado==='1'?'selected':'' ?>>Activas</option>
                <option value="0" <?= $estado==='0'?'selected':'' ?>>Inactivas</option>
              </select>
              <button class="btn btn-default"><i class="fa fa-search"></i></button>
            </form>
          </div>
        </div>

        <div class="box-body table-responsive no-padding">
          <table class="table table-hover">
            <thead>
              <tr>
                <th style="width:70px;">ID</th>
                <th>Caja</th>
                <th>Responsable</th>
                <th>Moneda</th>
                <th>Saldo inicial</th>
                <th>Saldo actual</th>
                <th>Estado</th>
                <th style="width:290px;">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($cajas)): ?>
                <tr><td colspan="8" class="text-center text-muted">Sin cajas</td></tr>
              <?php else: foreach ($cajas as $c):
                $mid = (int)$c['id'];
                $mov = $sumMov[$mid] ?? 0.0;
                $saldoActual = (float)$c['saldo_inicial'] + (float)$mov;
              ?>
              <tr>
                <td><?= $mid ?></td>
                <td><?= htmlspecialchars($c['nombre']) ?></td>
                <td><?= $c['responsable'] ? htmlspecialchars($c['responsable']) : '<span class="text-muted">—</span>' ?></td>
                <td><span class="label label-default"><?= htmlspecialchars($c['moneda']) ?></span></td>
                <td><?= number_format((float)$c['saldo_inicial'], 2) ?></td>
                <td><strong><?= number_format($saldoActual, 2) ?></strong></td>
                <td>
                  <?php if ((int)$c['activo']===1): ?>
                    <span class="label label-primary">Activa</span>
                  <?php else: ?>
                    <span class="label label-default">Inactiva</span>
                  <?php endif; ?>
                </td>
                <td>
                  <a class="btn btn-xs btn-info" href="CajaChicaMovimientos?caja_id=<?= $mid ?>">
                    <i class="fa fa-list-ul"></i> Movimientos
                  </a>

                  <button class="btn btn-xs btn-warning"
                          data-toggle="modal" data-target="#modalEditar"
                          data-id="<?= $mid ?>"
                          data-nombre="<?= htmlspecialchars($c['nombre'], ENT_QUOTES) ?>"
                          data-resp="<?= (int)($c['responsable_id'] ?? 0) ?>"
                          data-moneda="<?= htmlspecialchars($c['moneda']) ?>"
                          data-saldo="<?= htmlspecialchars(number_format((float)$c['saldo_inicial'], 2, '.', '')) ?>">
                    <i class="fa fa-pencil"></i> Editar
                  </button>

                  <form method="post" action="CajaChica" style="display:inline;">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?= $mid ?>">
                    <input type="hidden" name="activo" value="<?= (int)!$c['activo'] ?>">
                    <?php if ((int)$c['activo']===1): ?>
                      <button class="btn btn-xs btn-default"><i class="fa fa-toggle-off"></i> Desactivar</button>
                    <?php else: ?>
                      <button class="btn btn-xs btn-primary"><i class="fa fa-toggle-on"></i> Activar</button>
                    <?php endif; ?>
                  </form>

                  <form method="post" action="CajaChica" style="display:inline;" onsubmit="return confirm('¿Eliminar la caja? Debe no tener movimientos.');">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $mid ?>">
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
    <form class="modal-content" method="post" action="CajaChica" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="id" id="e_id">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title"><i class="fa fa-pencil"></i> Editar caja</h4>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label>Nombre *</label>
          <input type="text" name="nombre" id="e_nombre" class="form-control" required>
        </div>
        <div class="form-group">
          <label>Responsable</label>
          <select name="responsable_id" id="e_responsable" class="form-control">
            <option value="">-- Sin asignar --</option>
            <?php foreach ($usuarios as $u): ?>
              <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Moneda *</label>
          <select name="moneda" id="e_moneda" class="form-control" required>
            <option value="PEN">PEN (S/.)</option>
            <option value="USD">USD ($)</option>
            <option value="EUR">EUR (€)</option>
          </select>
        </div>
        <div class="form-group">
          <label>Saldo inicial *</label>
          <input type="number" step="0.01" min="0" name="saldo_inicial" id="e_saldo" class="form-control" required>
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
  $('#e_nombre').val(b.data('nombre'));
  $('#e_responsable').val(b.data('resp') || '');
  $('#e_moneda').val(b.data('moneda'));
  $('#e_saldo').val(b.data('saldo'));
});
</script>
