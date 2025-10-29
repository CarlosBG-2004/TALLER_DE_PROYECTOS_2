<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once "Config/Database.php";

/* ===== Acceso =====
   Permitimos: Ventas, Marketing, Postventa, Admin, Gerencia */
$areas = array_map('strtolower', $_SESSION['areas'] ?? []);
$rol   = $_SESSION['user']['rol'] ?? '';
$allow = in_array('ventas', $areas, true) || in_array('marketing', $areas, true) || in_array('postventa', $areas, true)
         || in_array($rol, ['Admin','Gerencia'], true);

if (!$allow) {
?>
<section class="content-header"><h1>Clientes <small>Acceso restringido</small></h1></section>
<section class="content">
  <div class="callout callout-danger">
    <h4>No autorizado</h4>
    <p>Esta sección es exclusiva para <b>Ventas/Marketing/Postventa</b> o <b>Admin/Gerencia</b>.</p>
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
  $st = $pdo->prepare("SELECT * FROM clientes WHERE id=? LIMIT 1");
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
      $apellido  = trim($_POST['apellido'] ?? '');
      $correo    = trim($_POST['correo'] ?? '');
      $telefono  = trim($_POST['telefono'] ?? '');
      $pais      = trim($_POST['pais'] ?? '');
      $fnac      = trim($_POST['fecha_nacimiento'] ?? '');

      if ($nombre === '' || $apellido === '') {
        throw new Exception('Nombre y apellido son obligatorios.');
      }
      if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('El correo no tiene un formato válido.');
      }
      if ($fnac !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fnac)) {
        throw new Exception('La fecha de nacimiento debe tener formato YYYY-MM-DD.');
      }

      if ($action === 'create') {
        $st = $pdo->prepare("
          INSERT INTO clientes (nombre, apellido, correo, telefono, pais, fecha_nacimiento)
          VALUES (?,?,?,?,?,?)
        ");
        $st->execute([
          $nombre ?: null, $apellido ?: null, ($correo !== '' ? $correo : null),
          $telefono ?: null, $pais ?: null, ($fnac !== '' ? $fnac : null)
        ]);
        flash_set('success','Cliente creado correctamente.');
      } else {
        if ($id <= 0) throw new Exception('ID inválido.');
        $st = $pdo->prepare("
          UPDATE clientes
             SET nombre=?, apellido=?, correo=?, telefono=?, pais=?, fecha_nacimiento=?, actualizado_en=NOW()
           WHERE id=?
        ");
        $st->execute([
          $nombre ?: null, $apellido ?: null, ($correo !== '' ? $correo : null),
          $telefono ?: null, $pais ?: null, ($fnac !== '' ? $fnac : null), $id
        ]);
        flash_set('success','Cliente actualizado correctamente.');
      }
      header("Location: Clientes"); exit;
    }

    if ($action === 'delete') {
      $id = (int)($_POST['id'] ?? 0);
      if ($id <= 0) throw new Exception('ID inválido.');
      $pdo->prepare("DELETE FROM clientes WHERE id=?")->execute([$id]);
      flash_set('success','Cliente eliminado.');
      header("Location: Clientes"); exit;
    }
  }
} catch (Throwable $e) {
  $code = ($e instanceof PDOException) ? $e->getCode() : '';
  if ($code === '23000') {
    // Posible duplicado de correo (unique)
    flash_set('danger','El correo ya existe. Verifica y vuelve a intentar.');
  } else {
    flash_set('danger','Error: '.$e->getMessage());
  }
  header("Location: Clientes".($editId?("?edit=".$editId):"")); exit;
}

/* ===== Filtros de listado ===== */
$q = trim($_GET['q'] ?? '');
$where = "1=1";
$args  = [];
if ($q !== '') {
  $where .= " AND (c.nombre LIKE ? OR c.apellido LIKE ? OR c.correo LIKE ? OR c.telefono LIKE ? OR c.pais LIKE ?)";
  $like = "%$q%";
  array_push($args, $like, $like, $like, $like, $like);
}

/* ===== Listado ===== */
$st = $pdo->prepare("
  SELECT c.id, c.nombre, c.apellido, c.correo, c.telefono, c.pais, c.fecha_nacimiento, c.creado_en
  FROM clientes c
  WHERE $where
  ORDER BY c.id DESC
  LIMIT 500
");
$st->execute($args);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);
?>

<section class="content-header">
  <h1>Clientes <small>Maestro de clientes</small></h1>
  <ol class="breadcrumb">
    <li><a href="Dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
    <li class="active">Clientes</li>
  </ol>
</section>

<section class="content">
  <?php flash_show(); ?>

  <!-- Formulario Crear/Editar -->
  <div class="box box-primary">
    <div class="box-header with-border">
      <h3 class="box-title">
        <i class="fa fa-user-plus"></i> <?php echo $editRow ? ('Editar cliente #'.(int)$editRow['id']) : 'Nuevo cliente'; ?>
      </h3>
      <?php if ($editRow): ?>
        <div class="box-tools"><a class="btn btn-default btn-sm" href="Clientes"><i class="fa fa-plus"></i> Nuevo</a></div>
      <?php endif; ?>
    </div>

    <form method="post" action="Clientes" autocomplete="off">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
      <input type="hidden" name="action" value="<?php echo $editRow ? 'update':'create'; ?>">
      <?php if ($editRow): ?><input type="hidden" name="id" value="<?php echo (int)$editRow['id']; ?>"><?php endif; ?>

      <div class="box-body">
        <div class="row">
          <div class="col-sm-3">
            <div class="form-group">
              <label>Nombre *</label>
              <input type="text" name="nombre" class="form-control" required
                     value="<?php echo htmlspecialchars($editRow['nombre'] ?? ''); ?>">
            </div>
          </div>
          <div class="col-sm-3">
            <div class="form-group">
              <label>Apellido *</label>
              <input type="text" name="apellido" class="form-control" required
                     value="<?php echo htmlspecialchars($editRow['apellido'] ?? ''); ?>">
            </div>
          </div>
          <div class="col-sm-3">
            <div class="form-group">
              <label>Correo</label>
              <input type="email" name="correo" class="form-control"
                     placeholder="correo@ejemplo.com"
                     value="<?php echo htmlspecialchars($editRow['correo'] ?? ''); ?>">
              <small class="text-muted">Debe ser único (si lo colocas).</small>
            </div>
          </div>
          <div class="col-sm-3">
            <div class="form-group">
              <label>Teléfono</label>
              <input type="text" name="telefono" class="form-control"
                     value="<?php echo htmlspecialchars($editRow['telefono'] ?? ''); ?>">
            </div>
          </div>
        </div><!-- row -->

        <div class="row">
          <div class="col-sm-3">
            <div class="form-group">
              <label>País</label>
              <input type="text" name="pais" class="form-control"
                     value="<?php echo htmlspecialchars($editRow['pais'] ?? ''); ?>">
            </div>
          </div>
          <div class="col-sm-3">
            <div class="form-group">
              <label>Fecha de nacimiento</label>
              <input type="date" name="fecha_nacimiento" class="form-control"
                     value="<?php echo htmlspecialchars($editRow['fecha_nacimiento'] ?? ''); ?>">
            </div>
          </div>
        </div><!-- row -->
      </div>

      <div class="box-footer">
        <?php if ($editRow): ?>
          <a href="Clientes" class="btn btn-default">Cancelar</a>
          <button class="btn btn-primary"><i class="fa fa-save"></i> Guardar cambios</button>
        <?php else: ?>
          <button class="btn btn-success"><i class="fa fa-plus"></i> Crear cliente</button>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- Filtros -->
  <div class="box box-default">
    <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-filter"></i> Buscar</h3></div>
    <div class="box-body">
      <form class="form-inline" method="get" action="Clientes" style="margin:0;">
        <input type="text" class="form-control" name="q" placeholder="Nombre, apellido, correo, teléfono, país"
               value="<?php echo htmlspecialchars($q); ?>" style="min-width:280px;">
        <button class="btn btn-default"><i class="fa fa-search"></i> Buscar</button>
        <?php if ($q!==''): ?><a class="btn btn-default" href="Clientes"><i class="fa fa-times"></i> Limpiar</a><?php endif; ?>
      </form>
    </div>
  </div>

  <!-- Listado -->
  <div class="box box-info">
    <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-table"></i> Últimos 500 clientes</h3></div>
    <div class="box-body table-responsive no-padding">
      <table class="table table-hover">
        <thead>
          <tr>
            <th>#ID</th>
            <th>Cliente</th>
            <th>Correo</th>
            <th>Teléfono</th>
            <th>País</th>
            <th>F. Nac.</th>
            <th>Creado</th>
            <th style="min-width:160px;">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="8" class="text-center text-muted">Sin registros</td></tr>
          <?php else: foreach ($rows as $r): ?>
            <tr>
              <td><?php echo (int)$r['id']; ?></td>
              <td><strong><?php echo htmlspecialchars($r['nombre'].' '.$r['apellido']); ?></strong></td>
              <td><?php echo htmlspecialchars($r['correo'] ?? ''); ?></td>
              <td><?php echo htmlspecialchars($r['telefono'] ?? ''); ?></td>
              <td><?php echo htmlspecialchars($r['pais'] ?? ''); ?></td>
              <td><?php echo htmlspecialchars($r['fecha_nacimiento'] ?? ''); ?></td>
              <td><small><?php echo htmlspecialchars($r['creado_en']); ?></small></td>
              <td>
                <a class="btn btn-xs btn-warning" href="Clientes?edit=<?php echo (int)$r['id']; ?>"><i class="fa fa-pencil"></i> Editar</a>
                <form method="post" action="Clientes" style="display:inline;" onsubmit="return confirm('¿Eliminar cliente #<?php echo (int)$r['id']; ?>?');">
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
    <div class="box-footer"><small class="text-muted">Si necesitas más filtros (por fechas, país, etc.), los añadimos.</small></div>
  </div>
</section>
