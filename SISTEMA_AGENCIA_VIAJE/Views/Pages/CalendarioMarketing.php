<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once "Config/Database.php";

/* ======= Control de acceso ======= */
$rol = $_SESSION['user']['rol'] ?? '';
if (!in_array($rol, ['Admin', 'Gerencia', 'Marketing'], true)) {
?>
<section class="content-header">
  <h1>Calendario Marketing <small>Acceso restringido</small></h1>
</section>
<section class="content">
  <div class="callout callout-danger">
    <h4>No autorizado</h4>
    <p>Necesitas permisos de <b>Marketing</b> o ser <b>Admin/Gerencia</b>.</p>
  </div>
</section>
<?php
  return;
}

/* ======= Conexión a la base de datos ======= */
$pdo = Database::getConnection();

/* ======= Obtener las tareas del calendario ======= */
$sql = "SELECT * FROM marketing_calendario ORDER BY fecha_inicio ASC";
$st = $pdo->prepare($sql);
$st->execute();
$calendarioEventos = $st->fetchAll(PDO::FETCH_ASSOC);

/* ======= Agregar nueva tarea ======= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    if (isset($_POST['action']) && $_POST['action'] === 'create') {
      $nombre = trim($_POST['nombre'] ?? '');
      $descripcion = trim($_POST['descripcion'] ?? '');
      $fecha_inicio = $_POST['fecha_inicio'] ?? '';
      $fecha_fin = $_POST['fecha_fin'] ?? '';
      $estado = $_POST['estado'] ?? 'activo';

      if (empty($nombre) || empty($fecha_inicio) || empty($fecha_fin)) {
        throw new Exception('Faltan campos obligatorios.');
      }

      // Insertar nuevo evento
      $sql = "INSERT INTO marketing_calendario (nombre, descripcion, fecha_inicio, fecha_fin, estado) 
              VALUES (?, ?, ?, ?, ?)";
      $st = $pdo->prepare($sql);
      $st->execute([$nombre, $descripcion, $fecha_inicio, $fecha_fin, $estado]);

      set_flash('success', 'Tarea agregada exitosamente.');
      header("Location: CalendarioMarketing.php"); exit;
    }
  } catch (Exception $e) {
    set_flash('danger', 'Error: '.$e->getMessage());
  }
}

/* ======= Flash message functions ======= */
function set_flash($type, $msg) {
  $_SESSION['flash'] = ['type' => $type, 'message' => $msg];
}

function flash() {
  if (isset($_SESSION['flash'])) {
    echo '<div class="alert alert-' . $_SESSION['flash']['type'] . '">' . $_SESSION['flash']['message'] . '</div>';
    unset($_SESSION['flash']);
  }
}

?>

<!-- Header de la página -->
<section class="content-header">
  <h1>Calendario de Marketing <small>Gestión de tareas y campañas</small></h1>
  <ol class="breadcrumb">
    <li><a href="Dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
    <li class="active">Calendario Marketing</li>
  </ol>
</section>

<section class="content">
  <?php flash(); ?>
  
  <!-- Formulario para agregar nueva tarea -->
  <div class="row">
    <div class="col-md-4">
      <div class="box box-primary">
        <div class="box-header with-border">
          <h3 class="box-title"><i class="fa fa-plus"></i> Agregar Tarea</h3>
        </div>
        <form method="POST" action="CalendarioMarketing.php">
          <div class="box-body">
            <input type="hidden" name="action" value="create">
            <div class="form-group">
              <label for="nombre">Nombre de la tarea</label>
              <input type="text" class="form-control" name="nombre" required>
            </div>
            <div class="form-group">
              <label for="descripcion">Descripción</label>
              <textarea class="form-control" name="descripcion" rows="3"></textarea>
            </div>
            <div class="form-group">
              <label for="fecha_inicio">Fecha de inicio</label>
              <input type="date" class="form-control" name="fecha_inicio" required>
            </div>
            <div class="form-group">
              <label for="fecha_fin">Fecha de finalización</label>
              <input type="date" class="form-control" name="fecha_fin" required>
            </div>
            <div class="form-group">
              <label for="estado">Estado</label>
              <select class="form-control" name="estado">
                <option value="activo">Activo</option>
                <option value="pendiente">Pendiente</option>
                <option value="finalizado">Finalizado</option>
              </select>
            </div>
            <button type="submit" class="btn btn-primary">Agregar tarea</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Mostrar las tareas existentes en un calendario o lista -->
    <div class="col-md-8">
      <div class="box box-success">
        <div class="box-header with-border">
          <h3 class="box-title"><i class="fa fa-calendar"></i> Calendario de Tareas</h3>
        </div>
        <div class="box-body">
          <table class="table table-striped">
            <thead>
              <tr>
                <th>Nombre</th>
                <th>Fecha de inicio</th>
                <th>Fecha de fin</th>
                <th>Estado</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($calendarioEventos as $evento): ?>
              <tr>
                <td><?php echo htmlspecialchars($evento['nombre']); ?></td>
                <td><?php echo htmlspecialchars($evento['fecha_inicio']); ?></td>
                <td><?php echo htmlspecialchars($evento['fecha_fin']); ?></td>
                <td>
                  <span class="label label-<?php echo $evento['estado'] === 'activo' ? 'success' : ($evento['estado'] === 'pendiente' ? 'warning' : 'danger'); ?>">
                    <?php echo ucfirst($evento['estado']); ?>
                  </span>
                </td>
                <td>
                  <a href="EditarCalendario.php?id=<?php echo $evento['id']; ?>" class="btn btn-xs btn-primary">
                    <i class="fa fa-pencil"></i> Editar
                  </a>
                  <a href="EliminarCalendario.php?id=<?php echo $evento['id']; ?>" class="btn btn-xs btn-danger" onclick="return confirm('¿Seguro que deseas eliminar esta tarea?');">
                    <i class="fa fa-trash"></i> Eliminar
                  </a>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

</section>

