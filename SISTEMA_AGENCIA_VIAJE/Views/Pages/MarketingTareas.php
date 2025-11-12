<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once "Config/Database.php";

/* ======= Control de acceso ======= */
$rol = $_SESSION['user']['rol'] ?? '';
if (!in_array($rol, ['Admin', 'Gerencia', 'Marketing'], true)) {
?>
<section class="content-header"><h1>Marketing — Tareas <small>Acceso restringido</small></h1></section>
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

/* ======= Verificar si la columna `fecha_limite` existe en la base de datos ======= */
$checkColumn = $pdo->query("SHOW COLUMNS FROM marketing_tareas LIKE 'fecha_limite'");
if (!$checkColumn->fetch()) {
  // Si no existe la columna, la añadimos a la base de datos
  $pdo->exec("ALTER TABLE marketing_tareas ADD COLUMN fecha_limite DATE NOT NULL DEFAULT CURRENT_DATE AFTER estado");
}

/* ======= Operaciones (crear/editar/eliminar) ======= */
try {
  // Crear tarea
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    if (!isset($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) {
      throw new Exception('CSRF inválido.');
    }

    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $estado = $_POST['estado'] ?? 'pendiente';
    $fecha_limite = $_POST['fecha_limite'] ?? date('Y-m-d'); // Fecha límite por defecto

    if (empty($titulo)) throw new Exception('El título de la tarea es obligatorio.');

    $stmt = $pdo->prepare("INSERT INTO marketing_tareas (titulo, descripcion, estado, fecha_limite, creado_en) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$titulo, $descripcion, $estado, $fecha_limite]);

    set_flash('success', 'Tarea creada exitosamente.');
    header("Location: MarketingTareas"); exit;
  }

  // Editar tarea
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    if (!isset($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) {
      throw new Exception('CSRF inválido.');
    }

    $id = (int)$_POST['id'];
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $estado = $_POST['estado'] ?? 'pendiente';
    $fecha_limite = $_POST['fecha_limite'] ?? date('Y-m-d'); // Fecha límite por defecto

    if (empty($titulo)) throw new Exception('El título de la tarea es obligatorio.');

    $stmt = $pdo->prepare("UPDATE marketing_tareas SET titulo = ?, descripcion = ?, estado = ?, fecha_limite = ?, actualizado_en = NOW() WHERE id = ?");
    $stmt->execute([$titulo, $descripcion, $estado, $fecha_limite, $id]);

    set_flash('success', 'Tarea actualizada exitosamente.');
    header("Location: MarketingTareas"); exit;
  }

  // Eliminar tarea
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!isset($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) {
      throw new Exception('CSRF inválido.');
    }

    $id = (int)$_POST['id'];
    if ($id <= 0) throw new Exception('ID de tarea inválido.');

    $stmt = $pdo->prepare("DELETE FROM marketing_tareas WHERE id = ?");
    $stmt->execute([$id]);

    set_flash('success', 'Tarea eliminada exitosamente.');
    header("Location: MarketingTareas"); exit;
  }

} catch (Exception $e) {
  set_flash('danger', 'Error: '.$e->getMessage());
  header("Location: MarketingTareas"); exit;
}

/* ======= Listado de tareas ======= */
$stmt = $pdo->prepare("SELECT * FROM marketing_tareas t WHERE 1=1 ORDER BY t.fecha_limite ASC");
$stmt->execute();
$tareas = $stmt->fetchAll();

/* ======= CSRF Token ======= */
$_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf'];
?>

<!-- Vista de tareas de marketing -->
<section class="content-header">
  <h1>Marketing <small>Tareas de marketing</small></h1>
  <ol class="breadcrumb">
    <li><a href="Dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
    <li class="active">Tareas</li>
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
      <form class="form-inline" method="get" action="MarketingTareas" style="margin:0;">
        <div class="form-group">
          <label>Buscar</label>
          <input type="text" name="q" class="form-control" placeholder="Buscar tareas..." value="<?= htmlspecialchars($searchTerm) ?>">
        </div>
        <div class="form-group">
          <label>Estado</label>
          <select name="status" class="form-control">
            <option value="">Todos</option>
            <option value="pendiente" <?= $statusFilter === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
            <option value="en progreso" <?= $statusFilter === 'en progreso' ? 'selected' : '' ?>>En progreso</option>
            <option value="completada" <?= $statusFilter === 'completada' ? 'selected' : '' ?>>Completada</option>
          </select>
        </div>
        <button class="btn btn-default"><i class="fa fa-search"></i> Filtrar</button>
      </form>
    </div>
  </div>

  <!-- Lista de tareas -->
  <div class="box box-info">
    <div class="box-header with-border">
      <h3 class="box-title"><i class="fa fa-list"></i> Tareas</h3>
      <div class="box-tools pull-right">
        <a href="#modalNuevo" class="btn btn-sm btn-success" data-toggle="modal"><i class="fa fa-plus"></i> Nueva tarea</a>
      </div>
    </div>

    <div class="box-body table-responsive no-padding">
      <table class="table table-hover">
        <thead>
          <tr>
            <th>ID</th>
            <th>Título</th>
            <th>Descripción</th>
            <th>Estado</th>
            <th>Fecha límite</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($tareas)): ?>
            <tr><td colspan="6" class="text-center text-muted">No hay tareas disponibles.</td></tr>
          <?php else: foreach ($tareas as $t): ?>
          <tr>
            <td><?= (int)$t['id'] ?></td>
            <td><?= htmlspecialchars($t['titulo']) ?></td>
            <td><?= htmlspecialchars($t['descripcion']) ?></td>
            <td><?= ucfirst($t['estado']) ?></td>
            <td><?= htmlspecialchars($t['fecha_limite']) ?></td>
            <td>
              <a href="#modalEditar" class="btn btn-xs btn-warning" data-toggle="modal" data-id="<?= $t['id'] ?>" data-titulo="<?= h($t['titulo']) ?>" data-descripcion="<?= h($t['descripcion']) ?>" data-estado="<?= h($t['estado']) ?>" data-fecha_limite="<?= h($t['fecha_limite']) ?>"><i class="fa fa-pencil"></i> Editar</a>
              <form method="post" action="MarketingTareas" style="display:inline;" onsubmit="return confirm('¿Eliminar esta tarea?');">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
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
      <form method="post" action="MarketingTareas" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="action" value="create">
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <h4 class="modal-title" id="modalNuevoLabel"><i class="fa fa-plus"></i> Nueva tarea</h4>
          </div>
          <div class="modal-body">
            <div class="form-group">
              <label>Título *</label>
              <input type="text" name="titulo" class="form-control" required>
            </div>
            <div class="form-group">
              <label>Descripción</label>
              <textarea name="descripcion" class="form-control"></textarea>
            </div>
            <div class="form-group">
              <label>Estado</label>
              <select name="estado" class="form-control">
                <option value="pendiente">Pendiente</option>
                <option value="en progreso">En progreso</option>
                <option value="completada">Completada</option>
              </select>
            </div>
            <div class="form-group">
              <label>Fecha límite</label>
              <input type="date" name="fecha_limite" class="form-control" value="<?= date('Y-m-d') ?>">
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
      <form method="post" action="MarketingTareas" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" id="editId">
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <h4 class="modal-title" id="modalEditarLabel"><i class="fa fa-pencil"></i> Editar tarea</h4>
          </div>
          <div class="modal-body">
            <div class="form-group">
              <label>Título *</label>
              <input type="text" name="titulo" class="form-control" id="editTitulo" required>
            </div>
            <div class="form-group">
              <label>Descripción</label>
              <textarea name="descripcion" class="form-control" id="editDescripcion"></textarea>
            </div>
            <div class="form-group">
              <label>Estado</label>
              <select name="estado" class="form-control" id="editEstado">
                <option value="pendiente">Pendiente</option>
                <option value="en progreso">En progreso</option>
                <option value="completada">Completada</option>
              </select>
            </div>
            <div class="form-group">
              <label>Fecha límite</label>
              <input type="date" name="fecha_limite" class="form-control" id="editFechaLimite" value="<?= date('Y-m-d') ?>">
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
    $('#editTitulo').val(button.data('titulo'));
    $('#editDescripcion').val(button.data('descripcion'));
    $('#editEstado').val(button.data('estado'));
    $('#editFechaLimite').val(button.data('fecha_limite'));
  });
</script>
