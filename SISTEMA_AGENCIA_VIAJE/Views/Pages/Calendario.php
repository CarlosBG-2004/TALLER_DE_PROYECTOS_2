<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once "Config/Database.php";

/* ---------------- Acceso ---------------- */
$areas = array_map('strtolower', $_SESSION['areas'] ?? []);
$rol   = $_SESSION['user']['rol'] ?? '';
if (!in_array('ventas', $areas, true) && !in_array($rol, ['Admin','Gerencia'], true)) {
?>
<section class="content-header"><h1>Calendario de Ventas <small>Acceso restringido</small></h1></section>
<section class="content">
  <div class="callout callout-danger">
    <h4>No autorizado</h4>
    <p>Necesitas permisos de <b>Ventas</b> o ser <b>Admin/Gerencia</b>.</p>
  </div>
</section>
<?php
  return;
}

/* ---------------- Helpers ---------------- */
function flash($k='flash'){ if(!empty($_SESSION[$k])){ echo $_SESSION[$k]; unset($_SESSION[$k]); } }
function set_flash($type,$msg,$k='flash'){ $_SESSION[$k]='<div class="alert alert-'.$type.'">'.$msg.'</div>'; }
function csrf(){ if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(16)); return $_SESSION['csrf']; }
function csrf_ok($t){ return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t ?? ''); }
function to_mysql_dt(?string $v): ?string { // de datetime-local a DATETIME
  if(!$v) return null;
  // esperado: YYYY-MM-DDTHH:MM
  $v = str_replace('T', ' ', substr($v,0,16));
  return $v . ':00';
}
function to_input_dt(?string $v): ?string { // de DATETIME a datetime-local
  if(!$v) return null;
  return str_replace(' ', 'T', substr($v,0,16));
}

/* Detectar el nombre de columna FK a expedientes en calendario (archivo_id o expediente_id) */
$pdo = Database::getConnection();
$dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();
$expCol = 'archivo_id';
$chk = $pdo->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='calendario' AND COLUMN_NAME='archivo_id'");
$chk->execute([$dbName]);
if (!$chk->fetch()) {
  $chk = $pdo->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='calendario' AND COLUMN_NAME='expediente_id'");
  $chk->execute([$dbName]);
  if ($chk->fetch()) { $expCol = 'expediente_id'; } else { $expCol = null; }
}

/* ---------------- Catálogos ---------------- */
$expedientes = $pdo->query("
  SELECT e.id, e.codigo, COALESCE(e.titulo, e.programa) AS titulo,
         CONCAT(c.nombre,' ',c.apellido) AS cliente
  FROM expedientes e
  LEFT JOIN clientes c ON c.id=e.cliente_id
  ORDER BY e.id DESC
  LIMIT 500
")->fetchAll();

/* ---------------- POST: Crear/Editar/Eliminar ---------------- */
try {
  if (($_POST['action'] ?? '') === 'create') {
    if (!csrf_ok($_POST['csrf'] ?? '')) throw new Exception('CSRF inválido.');

    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $inicio = to_mysql_dt($_POST['inicio'] ?? null);
    $fin    = to_mysql_dt($_POST['fin'] ?? null);
    $expediente_id = (int)($_POST['expediente_id'] ?? 0);

    if ($titulo === '' || !$inicio || !$fin) throw new Exception('Título e intervalos de tiempo son obligatorios.');
    if ($fin < $inicio) throw new Exception('La fecha/hora fin no puede ser anterior al inicio.');

    if ($expCol) {
      $st = $pdo->prepare("INSERT INTO calendario (modulo, titulo, descripcion, inicio, fin, {$expCol}) VALUES ('ventas',?,?,?,?,?)");
      $st->execute([$titulo, $descripcion ?: null, $inicio, $fin, $expediente_id ?: null]);
    } else {
      $st = $pdo->prepare("INSERT INTO calendario (modulo, titulo, descripcion, inicio, fin) VALUES ('ventas',?,?,?,?)");
      $st->execute([$titulo, $descripcion ?: null, $inicio, $fin]);
    }

    set_flash('success','Evento creado en calendario.');
    header("Location: Calendario"); exit;
  }

  if (($_POST['action'] ?? '') === 'update') {
    if (!csrf_ok($_POST['csrf'] ?? '')) throw new Exception('CSRF inválido.');
    $id = (int)($_POST['id'] ?? 0);
    if ($id<=0) throw new Exception('ID inválido.');

    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $inicio = to_mysql_dt($_POST['inicio'] ?? null);
    $fin    = to_mysql_dt($_POST['fin'] ?? null);
    $expediente_id = (int)($_POST['expediente_id'] ?? 0);
    if ($titulo === '' || !$inicio || !$fin) throw new Exception('Título e intervalos de tiempo son obligatorios.');
    if ($fin < $inicio) throw new Exception('La fecha/hora fin no puede ser anterior al inicio.');

    if ($expCol) {
      $sql = "UPDATE calendario SET titulo=?, descripcion=?, inicio=?, fin=?, {$expCol}=?, actualizado_en=NOW() WHERE id=?";
      $args = [$titulo, $descripcion ?: null, $inicio, $fin, $expediente_id ?: null, $id];
    } else {
      $sql = "UPDATE calendario SET titulo=?, descripcion=?, inicio=?, fin=?, actualizado_en=NOW() WHERE id=?";
      $args = [$titulo, $descripcion ?: null, $inicio, $fin, $id];
    }
    $st = $pdo->prepare($sql);
    $st->execute($args);

    set_flash('success','Evento actualizado.');
    header("Location: Calendario"); exit;
  }

  if (($_POST['action'] ?? '') === 'delete') {
    if (!csrf_ok($_POST['csrf'] ?? '')) throw new Exception('CSRF inválido.');
    $id = (int)($_POST['id'] ?? 0);
    if ($id<=0) throw new Exception('ID inválido.');
    $pdo->prepare("DELETE FROM calendario WHERE id=?")->execute([$id]);
    set_flash('success','Evento eliminado.');
    header("Location: Calendario"); exit;
  }

} catch (Throwable $e) {
  set_flash('danger','Error: '.$e->getMessage());
  header("Location: Calendario"); exit;
}

/* ---------------- Listado + Filtros ---------------- */
$csrf = csrf();
$q  = trim($_GET['q'] ?? '');
$df = trim($_GET['df'] ?? date('Y-m-01')); // default inicio mes
$dt = trim($_GET['dt'] ?? date('Y-m-t'));  // default fin mes
$expFilter = (int)($_GET['ex'] ?? 0);

$where = ["c.modulo='ventas'"];
$args  = [];

if ($q !== '') {
  $where[] = "(c.titulo LIKE ? OR c.descripcion LIKE ? OR e.codigo LIKE ? OR COALESCE(e.titulo, e.programa) LIKE ?)";
  $like = "%{$q}%";
  array_push($args, $like, $like, $like, $like);
}
if ($df !== '') { $where[] = "DATE(c.inicio) >= ?"; $args[] = $df; }
if ($dt !== '') { $where[] = "DATE(c.fin) <= ?";    $args[] = $dt; }
if ($expFilter>0 && $expCol) { $where[] = "c.{$expCol} = ?"; $args[] = $expFilter; }

$wsql = $where ? ('WHERE '.implode(' AND ', $where)) : '';

$joinOn = $expCol ? "ON e.id=c.{$expCol}" : "ON 1=0"; // si no hay FK, join vacío
$sql = "
  SELECT c.id, c.titulo, c.descripcion, c.inicio, c.fin, " . ($expCol ? "c.{$expCol} AS expediente_id," : "NULL AS expediente_id,") . "
         e.codigo, COALESCE(e.titulo, e.programa) AS expediente_titulo,
         CONCAT(cl.nombre,' ',cl.apellido) AS cliente
  FROM calendario c
  LEFT JOIN expedientes e $joinOn
  LEFT JOIN clientes cl ON cl.id = e.cliente_id
  $wsql
  ORDER BY c.inicio ASC, c.id ASC
  LIMIT 500
";
$st = $pdo->prepare($sql);
$st->execute($args);
$rows = $st->fetchAll();

/* Agrupar por día para presentación */
$grouped = [];
foreach ($rows as $r) {
  $key = substr($r['inicio'], 0, 10); // YYYY-MM-DD
  $grouped[$key][] = $r;
}
?>

<section class="content-header">
  <h1>Calendario de Ventas <small>Agenda de expedientes / tours</small></h1>
  <ol class="breadcrumb">
    <li><a href="Dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
    <li class="active">Calendario</li>
  </ol>
</section>

<section class="content">
  <?php flash(); ?>

  <div class="box box-primary">
    <div class="box-header with-border">
      <form class="form-inline" method="get" action="Calendario" style="margin:0;">
        <div class="form-group">
          <input type="text" name="q" class="form-control" placeholder="Buscar (evento, expediente, cliente)"
                 value="<?= htmlspecialchars($q) ?>" style="min-width:260px;">
        </div>
        <div class="form-group">
          <label>Desde</label>
          <input type="date" name="df" class="form-control" value="<?= htmlspecialchars($df) ?>">
        </div>
        <div class="form-group">
          <label>Hasta</label>
          <input type="date" name="dt" class="form-control" value="<?= htmlspecialchars($dt) ?>">
        </div>
        <?php if ($expCol): ?>
        <div class="form-group">
          <label>Expediente</label>
          <select name="ex" class="form-control">
            <option value="0">Todos</option>
            <?php foreach ($expedientes as $e): ?>
              <option value="<?= (int)$e['id'] ?>" <?= $expFilter===(int)$e['id']?'selected':'' ?>>
                <?= htmlspecialchars($e['codigo'].' - '.($e['titulo'] ?: '').' / '.($e['cliente'] ?: '')) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
        <button class="btn btn-default"><i class="fa fa-search"></i></button>

        <div class="pull-right">
          <a class="btn btn-default" href="Calendario?df=<?= date('Y-m-01') ?>&dt=<?= date('Y-m-t') ?>"><i class="fa fa-calendar"></i> Mes actual</a>
          <button type="button" class="btn btn-success" data-toggle="modal" data-target="#modalNuevo">
            <i class="fa fa-plus"></i> Nuevo evento
          </button>
        </div>
      </form>
    </div>

    <div class="box-body">
      <?php if (empty($rows)): ?>
        <p class="text-muted">No hay eventos en el rango seleccionado.</p>
      <?php else: ?>
        <?php foreach ($grouped as $day => $items): ?>
          <h4 style="margin-top:20px;">
            <i class="fa fa-calendar-o"></i>
            <?= date('d/m/Y', strtotime($day)) ?>
          </h4>
          <div class="table-responsive">
            <table class="table table-striped table-hover">
              <thead>
                <tr>
                  <th style="width:120px;">Inicio</th>
                  <th style="width:120px;">Fin</th>
                  <th>Evento</th>
                  <th>Expediente</th>
                  <th>Cliente</th>
                  <th style="width:160px;">Acciones</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($items as $ev): ?>
                <tr>
                  <td><?= date('H:i', strtotime($ev['inicio'])) ?></td>
                  <td><?= date('H:i', strtotime($ev['fin'])) ?></td>
                  <td>
                    <strong><?= htmlspecialchars($ev['titulo']) ?></strong>
                    <?php if (!empty($ev['descripcion'])): ?>
                      <div class="text-muted"><?= nl2br(htmlspecialchars($ev['descripcion'])) ?></div>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if (!empty($ev['codigo'])): ?>
                      <span class="label label-primary"><?= htmlspecialchars($ev['codigo']) ?></span>
                      <div class="text-muted"><?= htmlspecialchars($ev['expediente_titulo'] ?: '—') ?></div>
                    <?php else: ?>
                      <span class="text-muted">—</span>
                    <?php endif; ?>
                  </td>
                  <td><?= htmlspecialchars($ev['cliente'] ?: '—') ?></td>
                  <td>
                    <button class="btn btn-xs btn-warning"
                            data-toggle="modal" data-target="#modalEditar"
                            data-id="<?= (int)$ev['id'] ?>"
                            data-titulo="<?= htmlspecialchars($ev['titulo'], ENT_QUOTES) ?>"
                            data-desc="<?= htmlspecialchars($ev['descripcion'], ENT_QUOTES) ?>"
                            data-inicio="<?= htmlspecialchars($ev['inicio']) ?>"
                            data-fin="<?= htmlspecialchars($ev['fin']) ?>"
                            data-exp="<?= (int)$ev['expediente_id'] ?>">
                      <i class="fa fa-pencil"></i> Editar
                    </button>
                    <form method="post" action="Calendario" style="display:inline;"
                          onsubmit="return confirm('¿Eliminar evento?');">
                      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?= (int)$ev['id'] ?>">
                      <button class="btn btn-xs btn-danger"><i class="fa fa-trash"></i></button>
                    </form>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <div class="box-footer">
      <small class="text-muted">Mostrando hasta 500 eventos. Ajusta el rango de fechas para refinar.</small>
    </div>
  </div>
</section>

<!-- MODAL: NUEVO -->
<div class="modal fade" id="modalNuevo">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="Calendario" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="action" value="create">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title"><i class="fa fa-calendar-plus-o"></i> Nuevo evento</h4>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label>Título *</label>
          <input type="text" name="titulo" class="form-control" required>
        </div>
        <div class="form-group">
          <label>Descripción</label>
          <textarea name="descripcion" class="form-control" rows="2"></textarea>
        </div>
        <div class="row">
          <div class="col-xs-6">
            <div class="form-group">
              <label>Inicio *</label>
              <input type="datetime-local" name="inicio" class="form-control" required value="<?= date('Y-m-d\T09:00') ?>">
            </div>
          </div>
          <div class="col-xs-6">
            <div class="form-group">
              <label>Fin *</label>
              <input type="datetime-local" name="fin" class="form-control" required value="<?= date('Y-m-d\T10:00') ?>">
            </div>
          </div>
        </div>
        <?php if ($expCol): ?>
        <div class="form-group">
          <label>Expediente (opcional)</label>
          <select name="expediente_id" class="form-control">
            <option value="">(Ninguno)</option>
            <?php foreach ($expedientes as $e): ?>
              <option value="<?= (int)$e['id'] ?>">
                <?= htmlspecialchars($e['codigo'].' - '.($e['titulo'] ?: '').' / '.($e['cliente'] ?: '')) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <button class="btn btn-default" data-dismiss="modal">Cancelar</button>
        <button class="btn btn-success"><i class="fa fa-save"></i> Guardar</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL: EDITAR -->
<div class="modal fade" id="modalEditar">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="Calendario" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="id" id="e_id">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title">Editar evento</h4>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label>Título *</label>
          <input type="text" name="titulo" id="e_titulo" class="form-control" required>
        </div>
        <div class="form-group">
          <label>Descripción</label>
          <textarea name="descripcion" id="e_desc" class="form-control" rows="2"></textarea>
        </div>
        <div class="row">
          <div class="col-xs-6">
            <div class="form-group">
              <label>Inicio *</label>
              <input type="datetime-local" name="inicio" id="e_inicio" class="form-control" required>
            </div>
          </div>
          <div class="col-xs-6">
            <div class="form-group">
              <label>Fin *</label>
              <input type="datetime-local" name="fin" id="e_fin" class="form-control" required>
            </div>
          </div>
        </div>
        <?php if ($expCol): ?>
        <div class="form-group">
          <label>Expediente (opcional)</label>
          <select name="expediente_id" id="e_exp" class="form-control">
            <option value="">(Ninguno)</option>
            <?php foreach ($expedientes as $e): ?>
              <option value="<?= (int)$e['id'] ?>">
                <?= htmlspecialchars($e['codigo'].' - '.($e['titulo'] ?: '').' / '.($e['cliente'] ?: '')) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <button class="btn btn-default" data-dismiss="modal">Cancelar</button>
        <button class="btn btn-warning"><i class="fa fa-save"></i> Guardar cambios</button>
      </div>
    </form>
  </div>
</div>

<script>
// Rellenar modal editar
$('#modalEditar').on('show.bs.modal', function (e) {
  var b = $(e.relatedTarget);
  $('#e_id').val(b.data('id'));
  $('#e_titulo').val(b.data('titulo'));
  $('#e_desc').val(b.data('desc') || '');
  // convertir "YYYY-MM-DD HH:MM:SS" -> "YYYY-MM-DDTHH:MM"
  var di = (b.data('inicio')||'').replace(' ', 'T').slice(0,16);
  var df = (b.data('fin')||'').replace(' ', 'T').slice(0,16);
  $('#e_inicio').val(di);
  $('#e_fin').val(df);
  <?php if ($expCol): ?> $('#e_exp').val(b.data('exp')||''); <?php endif; ?>
});
</script>
