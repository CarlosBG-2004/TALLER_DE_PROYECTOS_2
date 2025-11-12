<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once "Config/Database.php";

/* ======= Control de acceso ======= */
$rol = $_SESSION['user']['rol'] ?? '';
if (!in_array($rol, ['Admin', 'Gerencia', 'Postventa'], true)) {
?>
<section class="content-header"><h1>Postventa <small>Acceso restringido</small></h1></section>
<section class="content">
  <div class="callout callout-danger">
    <h4>No autorizado</h4>
    <p>Esta sección es exclusiva para <b>Postventa</b>, <b>Admin</b> y <b>Gerencia</b>.</p>
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
  // Crear tarea de postventa
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    if (!isset($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) {
      throw new Exception('CSRF inválido.');
    }

    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $cliente_id = $_POST['cliente_id'];
    $fecha_limite = $_POST['fecha_limite'];

    if (empty($titulo)) throw new Exception('El título de la tarea es obligatorio.');

    $stmt = $pdo->prepare("INSERT INTO postventa_tareas (titulo, descripcion, cliente_id, fecha_limite) VALUES (?, ?, ?, ?)");
    $stmt->execute([$titulo, $descripcion, $cliente_id, $fecha_limite]);

    set_flash('success', 'Tarea de postventa creada exitosamente.');
    header("Location: PostVenta"); exit;
  }

  // Editar tarea de postventa
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    if (!isset($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) {
      throw new Exception('CSRF inválido.');
    }

    $id = (int)$_POST['id'];
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $estado = $_POST['estado'];
    $fecha_limite = $_POST['fecha_limite'];

    if (empty($titulo)) throw new Exception('El título de la tarea es obligatorio.');

    $stmt = $pdo->prepare("UPDATE postventa_tareas SET titulo = ?, descripcion = ?, estado = ?, fecha_limite = ?, actualizado_en = NOW() WHERE id = ?");
    $stmt->execute([$titulo, $descripcion, $estado, $fecha_limite, $id]);

    set_flash('success', 'Tarea de postventa actualizada exitosamente.');
    header("Location: PostVenta"); exit;
  }

  // Eliminar tarea de postventa
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!isset($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) {
      throw new Exception('CSRF inválido.');
    }

    $id = (int)$_POST['id'];
    $stmt = $pdo->prepare("DELETE FROM postventa_tareas WHERE id = ?");
    $stmt->execute([$id]);

    set_flash('success', 'Tarea de postventa eliminada exitosamente.');
    header("Location: PostVenta"); exit;
  }

  // Enviar mensaje de postventa
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_message') {
    $tarea_id = $_POST['tarea_id'];
    $mensaje = $_POST['mensaje'];

    $stmt = $pdo->prepare("INSERT INTO postventa_mensajes (tarea_id, mensaje) VALUES (?, ?)");
    $stmt->execute([$tarea_id, $mensaje]);

    // Lógica adicional para enviar el mensaje (puede ser por email o SMS)
    set_flash('success', 'Mensaje enviado a cliente.');
    header("Location: PostVenta"); exit;
  }

  // Registrar recomendación de cliente
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_recommendation') {
    $cliente_id = $_POST['cliente_id'];
    $recomendacion = $_POST['recomendacion'];

    $stmt = $pdo->prepare("INSERT INTO postventa_recomendaciones (cliente_id, recomendacion) VALUES (?, ?)");
    $stmt->execute([$cliente_id, $recomendacion]);

    set_flash('success', 'Recomendación registrada.');
    header("Location: PostVenta"); exit;
  }

} catch (Exception $e) {
  set_flash('danger', 'Error: '.$e->getMessage());
  header("Location: PostVenta"); exit;
}

/* ======= Listado de tareas ======= */
$stmt = $pdo->prepare("SELECT * FROM postventa_tareas ORDER BY creado_en DESC");
$stmt->execute();
$tareas = $stmt->fetchAll();

/* ======= Listado de mensajes enviados ======= */
$stmt = $pdo->prepare("SELECT * FROM postventa_mensajes ORDER BY fecha_envio DESC");
$stmt->execute();
$mensajes = $stmt->fetchAll();

/* ======= Listado de recomendaciones ======= */
$stmt = $pdo->prepare("SELECT * FROM postventa_recomendaciones ORDER BY fecha DESC");
$stmt->execute();
$recomendaciones = $stmt->fetchAll();

/* ======= CSRF Token ======= */
$_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf'];

/* ======= Filtros ======= */
$q = $_GET['q'] ?? '';  // Filtro de búsqueda
$estado = $_GET['estado'] ?? '';  // Filtro de estado de tarea
$cliente = $_GET['cliente'] ?? '';  // Filtro de cliente
$fecha_inicio = $_GET['fecha_inicio'] ?? '';  // Filtro por fecha de inicio
$fecha_fin = $_GET['fecha_fin'] ?? '';  // Filtro por fecha de fin
?>

<!-- Vista de Postventa -->
<section class="content-header">
  <h1>Postventa <small>Gestión de tareas de postventa</small></h1>
  <ol class="breadcrumb">
    <li><a href="Dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
    <li class="active">Postventa</li>
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
      <form class="form-inline" method="get" action="PostVenta" style="margin:0;">
        <div class="form-group">
          <label>Buscar</label>
          <input type="text" name="q" class="form-control" placeholder="Buscar tareas..." value="<?= htmlspecialchars($q) ?>">
        </div>
        <div class="form-group">
          <label>Estado</label>
          <select name="estado" class="form-control">
            <option value="">Todos</option>
            <option value="pendiente" <?= $estado === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
            <option value="completada" <?= $estado === 'completada' ? 'selected' : '' ?>>Completada</option>
            <option value="en proceso" <?= $estado === 'en proceso' ? 'selected' : '' ?>>En proceso</option>
          </select>
        </div>
        <div class="form-group">
          <label>Cliente</label>
          <select name="cliente" class="form-control">
            <option value="">Todos</option>
            <!-- Aquí deberías cargar los clientes de la base de datos -->
            <option value="1">Cliente 1</option>
            <option value="2">Cliente 2</option>
          </select>
        </div>
        <div class="form-group">
          <label>Fecha inicio</label>
          <input type="date" name="fecha_inicio" class="form-control" value="<?= htmlspecialchars($fecha_inicio) ?>">
        </div>
        <div class="form-group">
          <label>Fecha fin</label>
          <input type="date" name="fecha_fin" class="form-control" value="<?= htmlspecialchars($fecha_fin) ?>">
        </div>
        <button class="btn btn-default"><i class="fa fa-search"></i> Filtrar</button>
      </form>
    </div>
  </div>

  <!-- Listado de tareas -->
  <div class="box box-info">
    <div class="box-header with-border">
      <h3 class="box-title"><i class="fa fa-list"></i> Tareas de Postventa</h3>
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
            <th>Cliente</th>
            <th>Fecha límite</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($tareas)): ?>
            <tr><td colspan="6" class="text-center text-muted">No hay tareas de postventa disponibles.</td></tr>
          <?php else: foreach ($tareas as $t): ?>
          <tr>
            <td><?= (int)$t['id'] ?></td>
            <td><?= htmlspecialchars($t['titulo']) ?></td>
            <td><?= htmlspecialchars($t['cliente_id']) ?></td>
            <td><?= htmlspecialchars($t['fecha_limite']) ?></td>
            <td><?= htmlspecialchars($t['estado']) ?></td>
            <td>
              <a href="#modalEditar" class="btn btn-xs btn-warning" data-toggle="modal" data-id="<?= $t['id'] ?>" data-titulo="<?= h($t['titulo']) ?>" data-descripcion="<?= h($t['descripcion']) ?>" data-fecha_limite="<?= h($t['fecha_limite']) ?>" data-estado="<?= h($t['estado']) ?>"><i class="fa fa-pencil"></i> Editar</a>
              <form method="post" action="PostVenta" style="display:inline;" onsubmit="return confirm('¿Eliminar esta tarea?');">
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
      <form method="post" action="PostVenta" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="action" value="create">
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <h4 class="modal-title" id="modalNuevoLabel"><i class="fa fa-plus"></i> Nueva tarea de postventa</h4>
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
              <label>Cliente</label>
              <select name="cliente_id" class="form-control">
                <!-- Aquí debes cargar los clientes de tu base de datos -->
                <option value="1">Cliente 1</option>
                <option value="2">Cliente 2</option>
              </select>
            </div>
            <div class="form-group">
              <label>Fecha límite</label>
              <input type="date" name="fecha_limite" class="form-control" required>
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
      <form method="post" action="PostVenta" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" id="editId">
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <h4 class="modal-title" id="modalEditarLabel"><i class="fa fa-pencil"></i> Editar tarea de postventa</h4>
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
              <label>Fecha límite</label>
              <input type="date" name="fecha_limite" class="form-control" id="editFechaLimite" required>
            </div>
            <div class="form-group">
              <label>Estado</label>
              <select name="estado" class="form-control" id="editEstado">
                <option value="pendiente">Pendiente</option>
                <option value="completada">Completada</option>
                <option value="en proceso">En proceso</option>
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
    $('#editTitulo').val(button.data('titulo'));
    $('#editDescripcion').val(button.data('descripcion'));
    $('#editFechaLimite').val(button.data('fecha_limite'));
    $('#editEstado').val(button.data('estado'));
  });
</script>
