<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once "Config/Database.php";

/* ===== Acceso =====
   Permitimos: Operaciones, Contabilidad o Admin/Gerencia */
$areas = array_map('strtolower', $_SESSION['areas'] ?? []);
$rol   = $_SESSION['user']['rol'] ?? '';
$allow = in_array('operaciones', $areas, true) || in_array('contabilidad', $areas, true)
         || in_array($rol, ['Admin','Gerencia'], true);

if (!$allow) {
?>
<section class="content-header"><h1>Proveedores <small>Acceso restringido</small></h1></section>
<section class="content">
  <div class="callout callout-danger">
    <h4>No autorizado</h4>
    <p>Esta sección es exclusiva para <b>Operaciones/Contabilidad</b> o <b>Admin/Gerencia</b>.</p>
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

$pdo  = Database::getConnection();
$csrf = csrf_token();

/* ===== Modo edición ===== */
$editId  = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editRow = null;
if ($editId > 0) {
  $st = $pdo->prepare("SELECT * FROM proveedores WHERE id=? LIMIT 1");
  $st->execute([$editId]);
  $editRow = $st->fetch(PDO::FETCH_ASSOC);
}

/* ===== Acciones ===== */
try {
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok($_POST['csrf'] ?? '')) { throw new Exception('CSRF inválido.'); }
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
      $id        = (int)($_POST['id'] ?? 0);
      $nombre    = trim($_POST['nombre'] ?? '');
      $ruc       = trim($_POST['ruc'] ?? '');
      $contacto  = trim($_POST['contacto'] ?? '');
      $telefono  = trim($_POST['telefono'] ?? '');
      $correo    = trim($_POST['correo'] ?? '');
      $direccion = trim($_POST['direccion'] ?? '');

      if ($nombre === '') throw new Exception('El nombre del proveedor es obligatorio.');
      if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('El correo no tiene un formato válido.');
      }
      // (Opcional) Validación simple de RUC (Perú: 11 dígitos)
      if ($ruc !== '' && !preg_match('/^\d{8,14}$/', $ruc)) {
        throw new Exception('El RUC debe contener sólo dígitos (8-14).');
      }

      if ($action === 'create') {
        $st = $pdo->prepare("
          INSERT INTO proveedores (nombre, ruc, contacto, telefono, correo, direccion)
          VALUES (?,?,?,?,?,?)
        ");
        $st->execute([
          $nombre,
          ($ruc!==''?$ruc:null),
          ($contacto!==''?$contacto:null),
          ($telefono!==''?$telefono:null),
          ($correo!==''?$correo:null),
          ($direccion!==''?$direccion:null)
        ]);
        flash_set('success','Proveedor creado correctamente.');
      } else {
        if ($id <= 0) throw new Exception('ID inválido.');
        $st = $pdo->prepare("
          UPDATE proveedores
             SET nombre=?, ruc=?, contacto=?, telefono=?, correo=?, direccion=?
           WHERE id=?
        ");
        $st->execute([
          $nombre,
          ($ruc!==''?$ruc:null),
          ($contacto!==''?$contacto:null),
          ($telefono!==''?$telefono:null),
          ($correo!==''?$correo:null),
          ($direccion!==''?$direccion:null),
          $id
        ]);
        flash_set('success','Proveedor actualizado correctamente.');
      }
      header("Location: Proveedores"); exit;
    }

    if ($action === 'delete') {
      $id = (int)($_POST['id'] ?? 0);
      if ($id <= 0) throw new Exception('ID inválido.');
      // Ojo: servicios_operaciones.proveedor_id tiene ON DELETE RESTRICT
      // Si el proveedor está usado en operaciones, no se podrá eliminar.
      $pdo->prepare("DELETE FROM proveedores WHERE id=?")->execute([$id]);
      flash_set('success','Proveedor eliminado.');
      header("Location: Proveedores"); exit;
    }
  }
} catch (Throwable $e) {
  $code = ($e instanceof PDOException) ? $e->getCode() : '';
  if ($code === '23000') {
    flash_set('danger','No se puede eliminar/guardar por conflicto de datos o relaciones (¿está usado en Operaciones/Contabilidad?).');
  } else {
    flash_set('danger','Error: '.$e->getMessage());
  }
  header("Location: Proveedores".($editId?("?edit=".$editId):"")); exit;
}

/* ===== Filtros de listado ===== */
$q = trim($_GET['q'] ?? '');
$where = "1=1";
$args  = [];
if ($q !== '') {
  $where .= " AND (p.nombre LIKE ? OR p.ruc LIKE ? OR p.contacto LIKE ? OR p.telefono LIKE ? OR p.correo LIKE ?)";
  $like = "%$q%";
  array_push($args, $like, $like, $like, $like, $like);
}

/* ===== Listado ===== */
$st = $pdo->prepare("
  SELECT p.id, p.nombre, p.ruc, p.contacto, p.telefono, p.correo, p.direccion, p.creado_en
  FROM proveedores p
  WHERE $where
  ORDER BY p.id DESC
  LIMIT 500
");
$st->execute($args);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);
?>

<section class="content-header">
  <h1>Proveedores <small>Maestro de proveedores</small></h1>
  <ol class="breadcrumb">
    <li><a href="Dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
    <li class="active">Proveedores</li>
  </ol>
</section>

<section class="content">
  <?php flash_show(); ?>

  <!-- Formulario Crear/Editar -->
  <div class="box box-primary">
    <div class="box-header with-border">
      <h3 class="box-title">
        <i class="fa fa-truck"></i> <?php echo $editRow ? ('Editar proveedor #'.(int)$editRow['id']) : 'Nuevo proveedor'; ?>
      </h3>
      <?php if ($editRow): ?>
        <div class="box-tools"><a class="btn btn-default btn-sm" href="Proveedores"><i class="fa fa-plus"></i> Nuevo</a></div>
      <?php endif; ?>
    </div>

    <form method="post" action="Proveedores" autocomplete="off">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
      <input type="hidden" name="action" value="<?php echo $editRow ? 'update':'create'; ?>">
      <?php if ($editRow): ?><input type="hidden" name="id" value="<?php echo (int)$editRow['id']; ?>"><?php endif; ?>

      <div class="box-body">
        <div class="row">
          <div class="col-sm-5">
            <div class="form-group">
              <label>Nombre del proveedor *</label>
              <input type="text" name="nombre" class="form-control" required
                     value="<?php echo htmlspecialchars($editRow['nombre'] ?? ''); ?>">
            </div>
          </div>
          <div class="col-sm-3">
            <div class="form-group">
              <label>RUC</label>
              <input type="text" name="ruc" class="form-control"
                     value="<?php echo htmlspecialchars($editRow['ruc'] ?? ''); ?>">
              <small class="text-muted">Sólo dígitos (ej. 11 en Perú)</small>
            </div>
          </div>
          <div class="col-sm-4">
            <div class="form-group">
              <label>Contacto</label>
              <input type="text" name="contacto" class="form-control"
                     value="<?php echo htmlspecialchars($editRow['contacto'] ?? ''); ?>">
            </div>
          </div>
        </div><!-- row -->

        <div class="row">
          <div class="col-sm-3">
            <div class="form-group">
              <label>Teléfono</label>
              <input type="text" name="telefono" class="form-control"
                     value="<?php echo htmlspecialchars($editRow['telefono'] ?? ''); ?>">
            </div>
          </div>
          <div class="col-sm-4">
            <div class="form-group">
              <label>Correo</label>
              <input type="email" name="correo" class="form-control" placeholder="correo@proveedor.com"
                     value="<?php echo htmlspecialchars($editRow['correo'] ?? ''); ?>">
            </div>
          </div>
          <div class="col-sm-5">
            <div class="form-group">
              <label>Dirección</label>
              <input type="text" name="direccion" class="form-control"
                     value="<?php echo htmlspecialchars($editRow['direccion'] ?? ''); ?>">
            </div>
          </div>
        </div><!-- row -->
      </div>

      <div class="box-footer">
        <?php if ($editRow): ?>
          <a href="Proveedores" class="btn btn-default">Cancelar</a>
          <button class="btn btn-primary"><i class="fa fa-save"></i> Guardar cambios</button>
        <?php else: ?>
          <button class="btn btn-success"><i class="fa fa-plus"></i> Crear proveedor</button>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- Filtros -->
  <div class="box box-default">
    <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-filter"></i> Buscar</h3></div>
    <div class="box-body">
      <form class="form-inline" method="get" action="Proveedores" style="margin:0;">
        <input type="text" class="form-control" name="q"
               placeholder="Nombre, RUC, contacto, teléfono o correo"
               value="<?php echo htmlspecialchars($q); ?>" style="min-width:320px;">
        <button class="btn btn-default"><i class="fa fa-search"></i> Buscar</button>
        <?php if ($q!==''): ?><a class="btn btn-default" href="Proveedores"><i class="fa fa-times"></i> Limpiar</a><?php endif; ?>
      </form>
    </div>
  </div>

  <!-- Listado -->
  <div class="box box-info">
    <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-table"></i> Últimos 500 proveedores</h3></div>
    <div class="box-body table-responsive no-padding">
      <table class="table table-hover">
        <thead>
          <tr>
            <th>#ID</th>
            <th>Nombre</th>
            <th>RUC</th>
            <th>Contacto</th>
            <th>Teléfono</th>
            <th>Correo</th>
            <th>Dirección</th>
            <th>Creado</th>
            <th style="min-width:160px;">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="9" class="text-center text-muted">Sin registros</td></tr>
          <?php else: foreach ($rows as $r): ?>
            <tr>
              <td><?php echo (int)$r['id']; ?></td>
              <td><strong><?php echo htmlspecialchars($r['nombre']); ?></strong></td>
              <td><?php echo htmlspecialchars($r['ruc'] ?? ''); ?></td>
              <td><?php echo htmlspecialchars($r['contacto'] ?? ''); ?></td>
              <td><?php echo htmlspecialchars($r['telefono'] ?? ''); ?></td>
              <td><?php echo htmlspecialchars($r['correo'] ?? ''); ?></td>
              <td><?php echo htmlspecialchars($r['direccion'] ?? ''); ?></td>
              <td><small><?php echo htmlspecialchars($r['creado_en']); ?></small></td>
              <td>
                <a class="btn btn-xs btn-warning" href="Proveedores?edit=<?php echo (int)$r['id']; ?>">
                  <i class="fa fa-pencil"></i> Editar
                </a>
                <form method="post" action="Proveedores" style="display:inline;"
                      onsubmit="return confirm('¿Eliminar el proveedor #<?php echo (int)$r['id']; ?>?');">
                  <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                  <button class="btn btn-xs btn-danger"><i class="fa fa-trash"></i> Eliminar</button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <div class="box-footer">
      <small class="text-muted">Si necesitas exportar a Excel/CSV o más filtros (por fecha, etc.), lo añadimos.</small>
    </div>
  </div>
</section>
