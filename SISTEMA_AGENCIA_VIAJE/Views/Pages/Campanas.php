<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once "Config/Database.php";

/* ======= Control de acceso ======= */
$rol = $_SESSION['user']['rol'] ?? '';
if (!in_array($rol, ['Admin', 'Gerencia', 'Marketing'], true)) {
?>
<section class="content-header"><h1>Marketing — Campañas <small>Acceso restringido</small></h1></section>
<section class="content">
  <div class="callout callout-danger">
    <h4>No autorizado</h4>
    <p>Esta sección es exclusiva para <b>Marketing</b>, <b>Admin</b> y <b>Gerencia</b>.</p>
  </div>
</section>
<?php
  return;
}

/* ======= Funciones de utilidad ======= */
function flash($k='flash'){ if(!empty($_SESSION[$k])){ echo $_SESSION[$k]; unset($_SESSION[$k]); } }
function set_flash($type, $msg, $k='flash'){ $_SESSION[$k] = '<div class="alert alert-'.$type.'">'.$msg.'</div>'; }

/* ======= Conexión a la base de datos ======= */
$pdo = Database::getConnection();

/* ======= Operaciones (crear/editar/eliminar) ======= */
try {
  // Crear campaña
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    if (!isset($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) {
      throw new Exception('CSRF inválido.');
    }

    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $fecha_inicio = $_POST['fecha_inicio'] ?? '';
    $fecha_fin = $_POST['fecha_fin'] ?? '';
    $estado = $_POST['estado'] ?? 'pendiente';

    if (empty($nombre)) throw new Exception('El nombre de la campaña es obligatorio.');

    $stmt = $pdo->prepare("INSERT INTO marketing_campanas (nombre, descripcion, fecha_inicio, fecha_fin, estado, creado_en) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$nombre, $descripcion, $fecha_inicio, $fecha_fin, $estado]);

    set_flash('success', 'Campaña creada exitosamente.');
    header("Location: MarketingCampanas"); exit;
  }

  // Editar campaña
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    if (!isset($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) {
      throw new Exception('CSRF inválido.');
    }

    $id = (int)$_POST['id'];
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $fecha_inicio = $_POST['fecha_inicio'] ?? '';
    $fecha_fin = $_POST['fecha_fin'] ?? '';
    $estado = $_POST['estado'] ?? 'pendiente';

    if (empty($nombre)) throw new Exception('El nombre de la campaña es obligatorio.');

    $stmt = $pdo->prepare("UPDATE marketing_campanas SET nombre = ?, descripcion = ?, fecha_inicio = ?, fecha_fin = ?, estado = ?, actualizado_en = NOW() WHERE id = ?");
    $stmt->execute([$nombre, $descripcion, $fecha_inicio, $fecha_fin, $estado, $id]);

    set_flash('success', 'Campaña actualizada exitosamente.');
    header("Location: MarketingCampanas"); exit;
  }

  // Eliminar campaña
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!isset($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) {
      throw new Exception('CSRF inválido.');
    }

    $id = (int)$_POST['id'];
    if ($id <= 0) throw new Exception('ID de campaña inválido.');

    $stmt = $pdo->prepare("DELETE FROM marketing_campanas WHERE id = ?");
    $stmt->execute([$id]);

    set_flash('success', 'Campaña eliminada exitosamente.');
    header("Location: MarketingCampanas"); exit;
  }

} catch (Exception $e) {
  set_flash('danger', 'Error: '.$e->getMessage());
  header("Location: MarketingCampanas"); exit;
}

/* ======= Listado de campañas ======= */
$stmt = $pdo->prepare("SELECT * FROM marketing_campanas ORDER BY creado_en DESC");
$stmt->execute();
$campanas = $stmt->fetchAll();

/* ======= CSRF Token ======= */
$_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf'];
?>

<!-- Vista de campañas de marketing -->
<section class="content-header">
  <h1>Marketing <small>Campañas de marketing</small></h1>
  <ol class="breadcrumb">
    <li><a href="Dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
    <li class="active">Campañas</li>
  </ol>
</section>

<section class="content">
  <?php flash(); ?>

  <!-- Filtros -->
  <div class="box box-default">
    <div class="box-header with-border">
      <h3 class="box-title"><i class="fa fa-filter"></i> Filtros</h3>
    </div>
    <div class="box-body">
      <form class="form-inline" method="get" action="MarketingCampanas" style="margin:0;">
        <div class="form-group">
          <label>Buscar</label>
          <input type="text" name="q" class="form-control" placeholder="Buscar campañas..." value="<?= htmlspecialchars($searchTerm ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Estado</label>
          <select name="estado" class="form-control">
            <option value="">Todos</option>
            <option value="pendiente" <?= $estadoFilter === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
            <option value="activo" <?= $estadoFilter === 'activo' ? 'selected' : '' ?>>Activo</option>
            <option value="finalizado" <?= $estadoFilter === 'finalizado' ? 'selected' : '' ?>>Finalizado</option>
          </select>
        </div>
        <button class="btn btn-default"><i class="fa fa-search"></i> Filtrar</button>
      </form>
    </div>
  </div>

  <!-- Lista de campañas -->
  <div class="box box-info">
    <div class="box-header with-border">
      <h3 class="box-title"><i class="fa fa-list"></i> Campañas</h3>
      <div class="box-tools pull-right">
        <a href="#modalNuevo" class="btn btn-sm btn-success" data-toggle="modal"><i class="fa fa-plus"></i> Nueva campaña</a>
      </div>
    </div>

    <div class="box-body table-responsive no-padding">
      <table class="table table-hover">
        <thead>
          <tr>
            <th>ID</th>
            <th>Nombre</th>
            <th>Fecha inicio</th>
            <th>Fecha fin</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($campanas)): ?>
            <tr><td colspan="6" class="text-center text-muted">No hay campañas disponibles.</td></tr>
          <?php else: foreach ($campanas as $c): ?>
          <tr>
            <td><?= (int)$c['id'] ?></td>
            <td><?= htmlspecialchars($c['nombre']) ?></td>
            <td><?= htmlspecialchars($c['fecha_inicio']) ?></td>
            <td><?= htmlspecialchars($c['fecha_fin']) ?></td>
            <td><?= htmlspecialchars($c['estado']) ?></td>
            <td>
              <a href="#modalEditar" class="btn btn-xs btn-warning" data-toggle="modal" data-id="<?= $c['id'] ?>" data-nombre="<?= h($c['nombre']) ?>" data-descripcion="<?= h($c['descripcion']) ?>" data-fecha_inicio="<?= h($c['fecha_inicio']) ?>" data-fecha_fin="<?= h($c['fecha_fin']) ?>" data-estado="<?= h($c['estado']) ?>"><i class="fa fa-pencil"></i> Editar</a>
              <form method="post" action="MarketingCampanas" style="display:inline;" onsubmit="return confirm('¿Eliminar esta campaña?');">
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

  <!-- Modal Nuevo -->
  <div class="modal fade" id="modalNuevo" tabindex="-1" role="dialog" aria-labelledby="modalNuevoLabel">
    <div class="modal-dialog" role="document">
      <form method="post" action="MarketingCampanas" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="action" value="create">
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <h4 class="modal-title" id="modalNuevoLabel"><i class="fa fa-plus"></i> Nueva campaña</h4>
          </div>
          <div class="modal-body">
            <div class="form-group">
              <label>Nombre *</label>
              <input type="text" name="nombre" class="form-control" required>
            </div>
            <div class="form-group">
              <label>Descripción</label>
              <textarea name="descripcion" class="form-control"></textarea>
            </div>
            <div class="form-group">
              <label>Fecha inicio *</label>
              <input type="date" name="fecha_inicio" class="form-control" required>
            </div>
            <div class="form-group">
              <label>Fecha fin *</label>
              <input type="date" name="fecha_fin" class="form-control" required>
            </div>
            <div class="form-group">
              <label>Estado</label>
              <select name="estado" class="form-control">
                <option value="pendiente">Pendiente</option>
                <option value="activo">Activo</option>
                <option value="finalizado">Finalizado</option>
              </select>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Guardar</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- Modal Editar -->
  <div class="modal fade" id="modalEditar" tabindex="-1" role="dialog" aria-labelledby="modalEditarLabel">
    <div class="modal-dialog" role="document">
      <form method="post" action="MarketingCampanas" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" id="editId">
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <h4 class="modal-title" id="modalEditarLabel"><i class="fa fa-pencil"></i> Editar campaña</h4>
          </div>
          <div class="modal-body">
            <div class="form-group">
              <label>Nombre *</label>
              <input type="text" name="nombre" id="editNombre" class="form-control" required>
            </div>
            <div class="form-group">
              <label>Descripción</label>
              <textarea name="descripcion" id="editDescripcion" class="form-control"></textarea>
            </div>
            <div class="form-group">
              <label>Fecha inicio *</label>
              <input type="date" name="fecha_inicio" id="editFechaInicio" class="form-control" required>
            </div>
            <div class="form-group">
              <label>Fecha fin *</label>
              <input type="date" name="fecha_fin" id="editFechaFin" class="form-control" required>
            </div>
            <div class="form-group">
              <label>Estado</label>
              <select name="estado" id="editEstado" class="form-control">
                <option value="pendiente">Pendiente</option>
                <option value="activo">Activo</option>
                <option value="finalizado">Finalizado</option>
              </select>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Guardar cambios</button>
          </div>
        </div>
      </form>
    </div>
  </div>

</section>

<script>
  // Cargar datos en modal de editar
  $('#modalEditar').on('show.bs.modal', function (e) {
    var button = $(e.relatedTarget);
    $('#editId').val(button.data('id'));
    $('#editNombre').val(button.data('nombre'));
    $('#editDescripcion').val(button.data('descripcion'));
    $('#editFechaInicio').val(button.data('fecha_inicio'));
    $('#editFechaFin').val(button.data('fecha_fin'));
    $('#editEstado').val(button.data('estado'));
  });
</script>
