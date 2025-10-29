<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once "Config/Database.php";

/* -------- Acceso: Contabilidad, Admin o Gerencia -------- */
$areas = array_map('strtolower', $_SESSION['areas'] ?? []);
$rol   = $_SESSION['user']['rol'] ?? '';
if (!in_array('contabilidad', $areas, true) && !in_array($rol, ['Admin','Gerencia'], true)) {
?>
<section class="content-header"><h1>Contabilidad — Categorías <small>Acceso restringido</small></h1></section>
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

/* -------- DB / Ensure table -------- */
$pdo = Database::getConnection();
$pdo->exec("
  CREATE TABLE IF NOT EXISTS contabilidad_categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    tipo ENUM('ingreso','gasto') NOT NULL,
    descripcion TEXT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_en TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB
");

/* -------- Acciones -------- */
try {
  if (($_POST['action'] ?? '') === 'create') {
    if (!csrf_ok($_POST['csrf'] ?? '')) throw new Exception('CSRF inválido.');
    $nombre = trim($_POST['nombre'] ?? '');
    $tipo   = ($_POST['tipo'] ?? '') === 'gasto' ? 'gasto' : 'ingreso';
    $desc   = trim($_POST['descripcion'] ?? '');

    if ($nombre === '') throw new Exception('El nombre es obligatorio.');

    $st = $pdo->prepare("INSERT INTO contabilidad_categorias (nombre, tipo, descripcion) VALUES (?,?,?)");
    $st->execute([$nombre, $tipo, $desc ?: null]);

    set_flash('success','Categoría creada.');
    header("Location: ContabilidadCategorias"); exit;
  }

  if (($_POST['action'] ?? '') === 'update') {
    if (!csrf_ok($_POST['csrf'] ?? '')) throw new Exception('CSRF inválido.');
    $id     = (int)($_POST['id'] ?? 0);
    $nombre = trim($_POST['nombre'] ?? '');
    $tipo   = ($_POST['tipo'] ?? '') === 'gasto' ? 'gasto' : 'ingreso';
    $desc   = trim($_POST['descripcion'] ?? '');

    if ($id<=0) throw new Exception('ID inválido.');
    if ($nombre === '') throw new Exception('El nombre es obligatorio.');

    $st = $pdo->prepare("UPDATE contabilidad_categorias SET nombre=?, tipo=?, descripcion=?, actualizado_en=NOW() WHERE id=?");
    $st->execute([$nombre, $tipo, $desc ?: null, $id]);

    set_flash('success','Categoría actualizada.');
    header("Location: ContabilidadCategorias"); exit;
  }

  if (($_POST['action'] ?? '') === 'toggle') {
    if (!csrf_ok($_POST['csrf'] ?? '')) throw new Exception('CSRF inválido.');
    $id     = (int)($_POST['id'] ?? 0);
    $activo = (int)($_POST['activo'] ?? 0) ? 1 : 0;
    if ($id<=0) throw new Exception('ID inválido.');
    $pdo->prepare("UPDATE contabilidad_categorias SET activo=?, actualizado_en=NOW() WHERE id=?")->execute([$activo, $id]);
    set_flash('success', $activo ? 'Categoría activada.' : 'Categoría desactivada.');
    header("Location: ContabilidadCategorias"); exit;
  }

  if (($_POST['action'] ?? '') === 'delete') {
    if (!csrf_ok($_POST['csrf'] ?? '')) throw new Exception('CSRF inválido.');
    $id = (int)($_POST['id'] ?? 0);
    if ($id<=0) throw new Exception('ID inválido.');
    $pdo->prepare("DELETE FROM contabilidad_categorias WHERE id=?")->execute([$id]);
    set_flash('success','Categoría eliminada.');
    header("Location: ContabilidadCategorias"); exit;
  }

} catch (Throwable $e) {
  $code = ($e instanceof PDOException) ? $e->getCode() : '';
  if ($code === '23000') {
    set_flash('danger','No se puede eliminar/crear por conflicto de datos o la categoría está en uso.');
  } else {
    set_flash('danger','Error: '.$e->getMessage());
  }
  header("Location: ContabilidadCategorias"); exit;
}

/* -------- Listado / Filtros -------- */
$csrf = csrf();
$q    = trim($_GET['q'] ?? '');
$t    = trim($_GET['tipo'] ?? ''); // '', ingreso, gasto
$est  = trim($_GET['estado'] ?? ''); // '', 1, 0

$where = [];
$args  = [];
if ($q!==''){
  $where[] = "(nombre LIKE ? OR descripcion LIKE ?)";
  $like = "%{$q}%"; array_push($args, $like, $like);
}
if ($t==='ingreso' || $t==='gasto'){ $where[] = "tipo = ?"; $args[] = $t; }
if ($est==='1' || $est==='0'){ $where[] = "activo = ?"; $args[] = (int)$est; }

$wsql = $where ? "WHERE ".implode(" AND ", $where) : "";

$rows = $pdo->prepare("SELECT id, nombre, tipo, descripcion, activo FROM contabilidad_categorias $wsql ORDER BY tipo, nombre");
$rows->execute($args);
$cats = $rows->fetchAll();

$totI = 0; $totG = 0; $activos = 0; $inactivos = 0;
foreach ($cats as $c) {
  if ($c['tipo']==='ingreso') $totI++; else $totG++;
  if ((int)$c['activo'] === 1) $activos++; else $inactivos++;
}
?>
<section class="content-header">
  <h1>Contabilidad <small>Categorías</small></h1>
  <ol class="breadcrumb">
    <li><a href="Dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
    <li class="active">Contabilidad &raquo; Categorías</li>
  </ol>
</section>

<section class="content">
  <?php flash(); ?>

  <div class="row">
    <div class="col-sm-3">
      <div class="small-box bg-green">
        <div class="inner"><h3><?= (int)$totI ?></h3><p>Categorías de Ingresos</p></div>
        <div class="icon"><i class="fa fa-arrow-circle-up"></i></div>
      </div>
    </div>
    <div class="col-sm-3">
      <div class="small-box bg-red">
        <div class="inner"><h3><?= (int)$totG ?></h3><p>Categorías de Gastos</p></div>
        <div class="icon"><i class="fa fa-arrow-circle-down"></i></div>
      </div>
    </div>
    <div class="col-sm-3">
      <div class="small-box bg-aqua">
        <div class="inner"><h3><?= (int)$activos ?></h3><p>Activas</p></div>
        <div class="icon"><i class="fa fa-toggle-on"></i></div>
      </div>
    </div>
    <div class="col-sm-3">
      <div class="small-box bg-gray">
        <div class="inner"><h3><?= (int)$inactivos ?></h3><p>Inactivas</p></div>
        <div class="icon"><i class="fa fa-toggle-off"></i></div>
      </div>
    </div>
  </div>

  <div class="row">
    <!-- Nueva categoría -->
    <div class="col-md-4">
      <div class="box box-success">
        <div class="box-header with-border">
          <h3 class="box-title"><i class="fa fa-plus"></i> Nueva categoría</h3>
        </div>
        <form method="post" action="ContabilidadCategorias" autocomplete="off">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
          <input type="hidden" name="action" value="create">
          <div class="box-body">
            <div class="form-group">
              <label>Nombre *</label>
              <input type="text" name="nombre" class="form-control" required placeholder="Ej. Comisiones, Insumos, etc.">
            </div>
            <div class="form-group">
              <label>Tipo *</label>
              <select name="tipo" class="form-control" required>
                <option value="ingreso">Ingreso</option>
                <option value="gasto">Gasto</option>
              </select>
            </div>
            <div class="form-group">
              <label>Descripción</label>
              <textarea name="descripcion" class="form-control" rows="3" placeholder="Opcional"></textarea>
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
          <h3 class="box-title"><i class="fa fa-tags"></i> Categorías</h3>
          <div class="box-tools">
            <form class="form-inline" method="get" action="ContabilidadCategorias" style="margin:0;">
              <input type="text" class="form-control" name="q" placeholder="Buscar nombre/desc…" value="<?= htmlspecialchars($q) ?>">
              <select name="tipo" class="form-control">
                <option value="">Tipo: Todos</option>
                <option value="ingreso" <?= $t==='ingreso'?'selected':'' ?>>Ingreso</option>
                <option value="gasto"   <?= $t==='gasto'?'selected':'' ?>>Gasto</option>
              </select>
              <select name="estado" class="form-control">
                <option value="">Estado: Todos</option>
                <option value="1" <?= $est==='1'?'selected':'' ?>>Activas</option>
                <option value="0" <?= $est==='0'?'selected':'' ?>>Inactivas</option>
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
                <th>Nombre</th>
                <th>Tipo</th>
                <th>Descripción</th>
                <th>Estado</th>
                <th style="width:230px;">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($cats)): ?>
                <tr><td colspan="6" class="text-center text-muted">Sin categorías</td></tr>
              <?php else: foreach ($cats as $c): ?>
              <tr>
                <td><?= (int)$c['id'] ?></td>
                <td><?= htmlspecialchars($c['nombre']) ?></td>
                <td>
                  <?php if ($c['tipo']==='ingreso'): ?>
                    <span class="label label-success">Ingreso</span>
                  <?php else: ?>
                    <span class="label label-danger">Gasto</span>
                  <?php endif; ?>
                </td>
                <td><?= $c['descripcion'] ? htmlspecialchars($c['descripcion']) : '<span class="text-muted">—</span>' ?></td>
                <td>
                  <?php if ((int)$c['activo']===1): ?>
                    <span class="label label-primary">Activa</span>
                  <?php else: ?>
                    <span class="label label-default">Inactiva</span>
                  <?php endif; ?>
                </td>
                <td>
                  <button class="btn btn-xs btn-warning"
                          data-toggle="modal" data-target="#modalEditar"
                          data-id="<?= (int)$c['id'] ?>"
                          data-nombre="<?= htmlspecialchars($c['nombre'], ENT_QUOTES) ?>"
                          data-tipo="<?= htmlspecialchars($c['tipo']) ?>"
                          data-desc="<?= htmlspecialchars($c['descripcion'] ?? '', ENT_QUOTES) ?>">
                    <i class="fa fa-pencil"></i> Editar
                  </button>

                  <form method="post" action="ContabilidadCategorias" style="display:inline;">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                    <input type="hidden" name="activo" value="<?= (int)!$c['activo'] ?>">
                    <?php if ((int)$c['activo']===1): ?>
                      <button class="btn btn-xs btn-default"><i class="fa fa-toggle-off"></i> Desactivar</button>
                    <?php else: ?>
                      <button class="btn btn-xs btn-primary"><i class="fa fa-toggle-on"></i> Activar</button>
                    <?php endif; ?>
                  </form>

                  <form method="post" action="ContabilidadCategorias" style="display:inline;" onsubmit="return confirm('¿Eliminar categoría? Esta acción no se puede deshacer.');">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
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
    <form class="modal-content" method="post" action="ContabilidadCategorias" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="id" id="e_id">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title"><i class="fa fa-pencil"></i> Editar categoría</h4>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label>Nombre *</label>
          <input type="text" name="nombre" id="e_nombre" class="form-control" required>
        </div>
        <div class="form-group">
          <label>Tipo *</label>
          <select name="tipo" id="e_tipo" class="form-control" required>
            <option value="ingreso">Ingreso</option>
            <option value="gasto">Gasto</option>
          </select>
        </div>
        <div class="form-group">
          <label>Descripción</label>
          <textarea name="descripcion" id="e_desc" class="form-control" rows="3"></textarea>
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
  $('#e_tipo').val(b.data('tipo'));
  $('#e_desc').val(b.data('desc') || '');
});
</script>
