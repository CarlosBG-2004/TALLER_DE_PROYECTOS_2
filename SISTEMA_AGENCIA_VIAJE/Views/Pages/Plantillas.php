<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once "Config/Database.php";

/* ======= Control de acceso ======= */
$rol = $_SESSION['user']['rol'] ?? '';
if (!in_array($rol, ['Admin', 'Gerencia', 'Marketing'], true)) {
?>
<section class="content-header"><h1>Marketing — Plantillas <small>Acceso restringido</small></h1></section>
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
  // Crear plantilla
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    if (!isset($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) {
      throw new Exception('CSRF inválido.');
    }

    $nombre = trim($_POST['nombre'] ?? '');
    $contenido = trim($_POST['contenido'] ?? '');
    $tipo = $_POST['tipo'] ?? 'promocional';

    if (empty($nombre)) throw new Exception('El nombre de la plantilla es obligatorio.');

    // Consulta de inserción a la base de datos
    $stmt = $pdo->prepare("INSERT INTO marketing_plantillas (nombre, contenido, tipo, creado_en) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$nombre, $contenido, $tipo]);

    set_flash('success', 'Plantilla creada exitosamente.');
    header("Location: MarketingPlantillas"); exit;
  }

  // Editar plantilla
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    if (!isset($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) {
      throw new Exception('CSRF inválido.');
    }

    $id = (int)$_POST['id'];
    $nombre = trim($_POST['nombre'] ?? '');
    $contenido = trim($_POST['contenido'] ?? '');
    $tipo = $_POST['tipo'] ?? 'promocional';

    if (empty($nombre)) throw new Exception('El nombre de la plantilla es obligatorio.');

    // Consulta de actualización a la base de datos
    $stmt = $pdo->prepare("UPDATE marketing_plantillas SET nombre = ?, contenido = ?, tipo = ?, actualizado_en = NOW() WHERE id = ?");
    $stmt->execute([$nombre, $contenido, $tipo, $id]);

    set_flash('success', 'Plantilla actualizada exitosamente.');
    header("Location: MarketingPlantillas"); exit;
  }

  // Eliminar plantilla
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!isset($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) {
      throw new Exception('CSRF inválido.');
    }

    $id = (int)$_POST['id'];
    if ($id <= 0) throw new Exception('ID de plantilla inválido.');

    // Consulta de eliminación de plantilla
    $stmt = $pdo->prepare("DELETE FROM marketing_plantillas WHERE id = ?");
    $stmt->execute([$id]);

    set_flash('success', 'Plantilla eliminada exitosamente.');
    header("Location: MarketingPlantillas"); exit;
  }

} catch (Exception $e) {
  set_flash('danger', 'Error: '.$e->getMessage());
  header("Location: MarketingPlantillas"); exit;
}

/* ======= Listado de plantillas ======= */
$stmt = $pdo->prepare("SELECT * FROM marketing_plantillas ORDER BY creado_en DESC");
$stmt->execute();
$plantillas = $stmt->fetchAll();

/* ======= CSRF Token ======= */
$_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf'];
?>

<!-- Vista de plantillas de marketing -->
<section class="content-header">
  <h1>Marketing <small>Plantillas de marketing</small></h1>
  <ol class="breadcrumb">
    <li><a href="Dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
    <li class="active">Plantillas</li>
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
      <form class="form-inline" method="get" action="MarketingPlantillas" style="margin:0;">
        <div class="form-group">
          <label>Buscar</label>
          <input type="text" name="q" class="form-control" placeholder="Buscar plantillas..." value="<?= htmlspecialchars($searchTerm ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Tipo</label>
          <select name="tipo" class="form-control">
            <option value="">Todos</option>
            <option value="promocional" <?= $tipoFilter === 'promocional' ? 'selected' : '' ?>>Promocional</option>
            <option value="informativo" <?= $tipoFilter === 'informativo' ? 'selected' : '' ?>>Informativo</option>
          </select>
        </div>
        <button class="btn btn-default"><i class="fa fa-search"></i> Filtrar</button>
      </form>
    </div>
  </div>

  <!-- Lista de plantillas -->
  <div class="box box-info">
    <div class="box-header with-border">
      <h3 class="box-title"><i class="fa fa-list"></i> Plantillas</h3>
      <div class="box-tools pull-right">
        <a href="#modalNuevo" class="btn btn-sm btn-success" data-toggle="modal"><i class="fa fa-plus"></i> Nueva plantilla</a>
      </div>
    </div>

    <div class="box-body table-responsive no-padding">
      <table class="table table-hover">
        <thead>
          <tr>
            <th>ID</th>
            <th>Nombre</th>
            <th>Tipo</th>
            <th>Fecha de creación</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($plantillas)): ?>
            <tr><td colspan="5" class="text-center text-muted">No hay plantillas disponibles.</td></tr>
          <?php else: foreach ($plantillas as $p): ?>
          <tr>
            <td><?= (int)$p['id'] ?></td>
            <td><?= htmlspecialchars($p['nombre']) ?></td>
            <td><?= htmlspecialchars($p['tipo']) ?></td>
            <td><?= htmlspecialchars($p['creado_en']) ?></td>
            <td>
              <a href="#modalEditar" class="btn btn-xs btn-warning" data-toggle="modal" data-id="<?= $p['id'] ?>" data-nombre="<?= h($p['nombre']) ?>" data-contenido="<?= h($p['contenido']) ?>" data-tipo="<?= h($p['tipo']) ?>"><i class="fa fa-pencil"></i> Editar</a>
              <form method="post" action="MarketingPlantillas" style="display:inline;" onsubmit="return confirm('¿Eliminar esta plantilla?');">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
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
      <form method="post" action="MarketingPlantillas" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="action" value="create">
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <h4 class="modal-title" id="modalNuevoLabel"><i class="fa fa-plus"></i> Nueva plantilla</h4>
          </div>
          <div class="modal-body">
            <div class="form-group">
              <label>Nombre *</label>
              <input type="text" name="nombre" class="form-control" required>
            </div>
            <div class="form-group">
              <label>Contenido</label>
              <textarea name="contenido" class="form-control"></textarea>
            </div>
            <div class="form-group">
              <label>Tipo</label>
              <select name="tipo" class="form-control">
                <option value="promocional">Promocional</option>
                <option value="informativo">Informativo</option>
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
      <form method="post" action="MarketingPlantillas" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" id="editId">
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <h4 class="modal-title" id="modalEditarLabel"><i class="fa fa-pencil"></i> Editar plantilla</h4>
          </div>
          <div class="modal-body">
            <div class="form-group">
              <label>Nombre *</label>
              <input type="text" name="nombre" class="form-control" id="editNombre" required>
            </div>
            <div class="form-group">
              <label>Contenido</label>
              <textarea name="contenido" class="form-control" id="editContenido"></textarea>
            </div>
            <div class="form-group">
              <label>Tipo</label>
              <select name="tipo" class="form-control" id="editTipo">
                <option value="promocional">Promocional</option>
                <option value="informativo">Informativo</option>
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
    $('#editContenido').val(button.data('contenido'));
    $('#editTipo').val(button.data('tipo'));
  });
</script>
