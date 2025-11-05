<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once "Config/Database.php";

/* ===== Acceso: Operaciones, Admin o Gerencia ===== */
$areas = array_map('strtolower', $_SESSION['areas'] ?? []);
$rol   = $_SESSION['user']['rol'] ?? '';
if (!in_array('operaciones', $areas, true) && !in_array($rol, ['Admin','Gerencia'], true)) {
?>
<section class="content-header"><h1>Programación <small>Acceso restringido</small></h1></section>
<section class="content">
  <div class="callout callout-danger">
    <h4>No autorizado</h4>
    <p>Requiere permisos de <b>Operaciones</b> o ser <b>Admin/Gerencia</b>.</p>
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

$pdo = Database::getConnection();

/* ===== Catálogos ===== */
$estados = [
  'pendiente'  => 'Pendiente',
  'confirmado' => 'Confirmado',
  'realizado'  => 'Realizado',
  'cancelado'  => 'Cancelado',
];

/* Expedientes para selectors */
$expedientes = $pdo->query("
  SELECT e.id,
         CONCAT(c.nombre,' ',c.apellido) AS cliente,
         e.programa, e.tour, e.fecha_tour_inicio
  FROM expedientes e
  JOIN clientes c ON c.id = e.cliente_id
  ORDER BY e.id DESC
  LIMIT 400
")->fetchAll(PDO::FETCH_ASSOC);

/* ===== Modo edición ===== */
$editId  = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editRow = null;
if ($editId > 0) {
  $st = $pdo->prepare("SELECT * FROM programacion_operaciones WHERE id=? LIMIT 1");
  $st->execute([$editId]);
  $editRow = $st->fetch(PDO::FETCH_ASSOC);
}

/* Para cargar servicios por expediente en el formulario (cuando se edita o se elige) */
$expSel = $editRow ? (int)$editRow['expediente_id'] : (int)($_GET['exp'] ?? 0);
$servicios = [];
if ($expSel > 0) {
  $st = $pdo->prepare("
    SELECT s.id, s.tipo, s.descripcion, p.nombre AS proveedor
    FROM servicios_operaciones s
    JOIN proveedores p ON p.id = s.proveedor_id
    WHERE s.expediente_id = ?
    ORDER BY s.id DESC
  ");
  $st->execute([$expSel]);
  $servicios = $st->fetchAll(PDO::FETCH_ASSOC);
}

/* ===== Acciones ===== */
try {
  if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!csrf_ok($_POST['csrf'] ?? '')) { throw new Exception('CSRF inválido.'); }
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
      $id         = (int)($_POST['id'] ?? 0);
      $fecha      = $_POST['fecha'] ?? '';
      $expediente_id = (int)($_POST['expediente_id'] ?? 0);
      $serv_id    = (int)($_POST['servicio_operacion_id'] ?? 0);
      $titulo     = trim($_POST['titulo'] ?? '');
      $descripcion= trim($_POST['descripcion'] ?? '');
      $estado     = $_POST['estado'] ?? 'pendiente';

      if ($fecha==='' || !$expediente_id || $titulo==='' || !isset($estados[$estado])) {
        throw new Exception('Datos incompletos o inválidos.');
      }

      if ($action==='create') {
        $st = $pdo->prepare("
          INSERT INTO programacion_operaciones
            (fecha, expediente_id, servicio_operacion_id, titulo, descripcion, estado)
          VALUES (?,?,?,?,?,?)
        ");
        $st->execute([$fecha, $expediente_id, $serv_id ?: null, $titulo, $descripcion ?: null, $estado]);
        flash_set('success','Actividad programada creada.');
      } else {
        if ($id<=0) throw new Exception('ID inválido.');
        $st = $pdo->prepare("
          UPDATE programacion_operaciones
             SET fecha=?, expediente_id=?, servicio_operacion_id=?, titulo=?, descripcion=?, estado=?
           WHERE id=?
        ");
        $st->execute([$fecha, $expediente_id, $serv_id ?: null, $titulo, $descripcion ?: null, $estado, $id]);
        flash_set('success','Actividad programada actualizada.');
      }
      header("Location: Programacion"); exit;
    }

    if ($action === 'set_estado') {
      $id = (int)($_POST['id'] ?? 0);
      $estado = $_POST['estado'] ?? '';
      if ($id<=0 || !isset($estados[$estado])) throw new Exception('Parámetros inválidos.');
      $st = $pdo->prepare("UPDATE programacion_operaciones SET estado=? WHERE id=?");
      $st->execute([$estado, $id]);
      flash_set('success','Estado actualizado.');
      header("Location: Programacion"); exit;
    }

    if ($action === 'delete') {
      $id = (int)($_POST['id'] ?? 0);
      if ($id<=0) throw new Exception('ID inválido.');
      $pdo->prepare("DELETE FROM programacion_operaciones WHERE id=?")->execute([$id]);
      flash_set('success','Actividad eliminada.');
      header("Location: Programacion"); exit;
    }
  }
} catch (Throwable $e) {
  flash_set('danger','Error: '.$e->getMessage());
  header("Location: Programacion".($editId?("?edit=".$editId):"")); exit;
}

/* ===== Filtros ===== */
$fdesde = $_GET['desde'] ?? date('Y-m-01');
$fhasta = $_GET['hasta'] ?? date('Y-m-d');
$fest   = $_GET['fest']  ?? '';
$fexp   = (int)($_GET['fexp'] ?? 0);
$fq     = trim($_GET['q'] ?? '');

$where = ["1=1"];
$args  = [];
if ($fdesde !== '') { $where[]="po.fecha >= ?"; $args[]=$fdesde; }
if ($fhasta !== '') { $where[]="po.fecha <= ?"; $args[]=$fhasta; }
if ($fest !== '' && isset($estados[$fest])) { $where[]="po.estado=?"; $args[]=$fest; }
if ($fexp > 0) { $where[]="po.expediente_id=?"; $args[]=$fexp; }
if ($fq!=='') {
  $where[]="(po.titulo LIKE ? OR c.nombre LIKE ? OR c.apellido LIKE ? OR e.programa LIKE ? OR e.tour LIKE ?)";
  $args[]="%$fq%"; $args[]="%$fq%"; $args[]="%$fq%"; $args[]="%$fq%"; $args[]="%$fq%";
}
$wsql = 'WHERE '.implode(' AND ', $where);

/* ===== Listado ===== */
$st = $pdo->prepare("
  SELECT po.id, po.fecha, po.titulo, po.descripcion, po.estado,
         po.expediente_id, po.servicio_operacion_id,
         CONCAT(cli.nombre,' ',cli.apellido) AS cliente,
         e.programa, e.tour,
         so.tipo AS serv_tipo, so.descripcion AS serv_desc, prov.nombre AS proveedor
  FROM programacion_operaciones po
  JOIN expedientes e         ON e.id = po.expediente_id
  JOIN clientes   cli        ON cli.id = e.cliente_id
  LEFT JOIN servicios_operaciones so ON so.id = po.servicio_operacion_id
  LEFT JOIN proveedores prov  ON prov.id = so.proveedor_id
  $wsql
  ORDER BY po.fecha ASC, po.id DESC
  LIMIT 800
");
$st->execute($args);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

$csrf = csrf_token();
?>
<section class="content-header">
  <h1>Programación de Operaciones <small>Agenda diaria de tours/servicios</small></h1>
  <ol class="breadcrumb">
    <li><a href="Dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
    <li class="active">Programación</li>
  </ol>
</section>

<section class="content">
  <?php flash_show(); ?>

  <!-- Formulario crear/editar -->
  <div class="box box-primary">
    <div class="box-header with-border">
      <h3 class="box-title">
        <i class="fa fa-calendar-check-o"></i> <?php echo $editRow ? 'Editar actividad #'.(int)$editRow['id'] : 'Nueva actividad'; ?>
      </h3>
      <?php if ($editRow): ?>
        <div class="box-tools"><a class="btn btn-default btn-sm" href="Programacion"><i class="fa fa-plus"></i> Nuevo</a></div>
      <?php endif; ?>
    </div>

    <form method="post" action="Programacion" autocomplete="off">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
      <input type="hidden" name="action" value="<?php echo $editRow ? 'update':'create'; ?>">
      <?php if ($editRow): ?><input type="hidden" name="id" value="<?php echo (int)$editRow['id']; ?>"><?php endif; ?>

      <div class="box-body">
        <div class="row">
          <div class="col-sm-3">
            <div class="form-group">
              <label>Fecha *</label>
              <input type="date" name="fecha" class="form-control" required
                     value="<?php echo htmlspecialchars($editRow['fecha'] ?? date('Y-m-d')); ?>">
            </div>
          </div>

          <div class="col-sm-5">
            <div class="form-group">
              <label>Expediente *</label>
              <select name="expediente_id" id="expediente_id" class="form-control" required
                      onchange="location.href='Programacion?exp='+this.value<?php echo $editRow ? " + '&edit=".$editId."'" : ""; ?>;">
                <option value="">-- Seleccione --</option>
                <?php foreach($expedientes as $e): ?>
                  <option value="<?php echo (int)$e['id']; ?>"
                    <?php
                      $sel = $editRow ? (int)$editRow['expediente_id'] : $expSel;
                      echo ($sel===(int)$e['id'])?'selected':'';
                    ?>>
                    #<?php echo (int)$e['id']; ?> — <?php echo htmlspecialchars($e['cliente']); ?> — <?php echo htmlspecialchars($e['programa'] ?: $e['tour'] ?: ''); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <small class="text-muted">Al cambiar, se recarga para mostrar servicios del expediente.</small>
            </div>
          </div>

          <div class="col-sm-4">
            <div class="form-group">
              <label>Servicio (opcional)</label>
              <select name="servicio_operacion_id" id="servicio_operacion_id" class="form-control">
                <option value="">— Sin servicio asociado —</option>
                <?php foreach($servicios as $s): ?>
                  <option value="<?php echo (int)$s['id']; ?>"
                    <?php echo ($editRow && (int)$editRow['servicio_operacion_id']===(int)$s['id'])?'selected':''; ?>>
                    #<?php echo (int)$s['id']; ?> — <?php echo htmlspecialchars(strtoupper($s['tipo']).' / '.$s['proveedor'].' — '.$s['descripcion']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div><!-- row -->

        <div class="row">
          <div class="col-sm-8">
            <div class="form-group">
              <label>Título *</label>
              <input type="text" name="titulo" class="form-control" required placeholder="Ej: City Tour Mañana / Traslado hotel → estación"
                     value="<?php echo htmlspecialchars($editRow['titulo'] ?? ''); ?>">
            </div>
          </div>
          <div class="col-sm-4">
            <div class="form-group">
              <label>Estado *</label>
              <select name="estado" class="form-control" required>
                <?php foreach($estados as $k=>$v): ?>
                  <option value="<?php echo $k; ?>" <?php echo ($editRow && $editRow['estado']===$k)?'selected':''; ?>>
                    <?php echo $v; ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div><!-- row -->

        <div class="form-group">
          <label>Descripción / notas</label>
          <textarea name="descripcion" rows="2" class="form-control" placeholder="Observaciones, hora de recojo, guía, placa, etc."><?php
            echo htmlspecialchars($editRow['descripcion'] ?? '');
          ?></textarea>
        </div>
      </div>

      <div class="box-footer">
        <?php if ($editRow): ?>
          <a href="Programacion" class="btn btn-default">Cancelar</a>
          <button class="btn btn-primary"><i class="fa fa-save"></i> Guardar cambios</button>
        <?php else: ?>
          <button class="btn btn-success"><i class="fa fa-plus"></i> Crear actividad</button>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- Filtros -->
  <div class="box box-default">
    <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-filter"></i> Filtros</h3></div>
    <div class="box-body">
      <form class="form-inline" method="get" action="Programacion" style="margin:0;">
        <label>Desde</label>
        <input type="date" name="desde" class="form-control" value="<?php echo htmlspecialchars($fdesde); ?>">
        <label>Hasta</label>
        <input type="date" name="hasta" class="form-control" value="<?php echo htmlspecialchars($fhasta); ?>">

        <label>Estado</label>
        <select name="fest" class="form-control">
          <option value="">Todos</option>
          <?php foreach($estados as $k=>$v): ?>
            <option value="<?php echo $k; ?>" <?php echo ($fest===$k)?'selected':''; ?>><?php echo $v; ?></option>
          <?php endforeach; ?>
        </select>

        <label>Expediente</label>
        <select name="fexp" class="form-control">
          <option value="0">Todos</option>
          <?php foreach($expedientes as $e): ?>
            <option value="<?php echo (int)$e['id']; ?>" <?php echo ($fexp===(int)$e['id'])?'selected':''; ?>>
              #<?php echo (int)$e['id']; ?> — <?php echo htmlspecialchars($e['cliente']); ?>
            </option>
          <?php endforeach; ?>
        </select>

        <input type="text" class="form-control" name="q" placeholder="Buscar título/cliente/programa…" value="<?php echo htmlspecialchars($fq); ?>">
        <button class="btn btn-default"><i class="fa fa-search"></i> Filtrar</button>
      </form>
    </div>
  </div>

  <!-- Listado -->
  <div class="box box-info">
    <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-table"></i> Agenda (máx. 800)</h3></div>
    <div class="box-body table-responsive no-padding">
      <table class="table table-hover">
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Expediente</th>
            <th>Cliente</th>
            <th>Título</th>
            <th>Servicio</th>
            <th>Estado</th>
            <th style="min-width:220px;">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="7" class="text-center text-muted">Sin actividades</td></tr>
          <?php else: foreach ($rows as $r): ?>
            <tr>
              <td><?php echo htmlspecialchars($r['fecha']); ?></td>
              <td><span class="label label-default">#<?php echo (int)$r['expediente_id']; ?></span></td>
              <td><?php echo htmlspecialchars($r['cliente']); ?></td>
              <td>
                <strong><?php echo htmlspecialchars($r['titulo']); ?></strong>
                <?php if (!empty($r['descripcion'])): ?>
                  <br><small class="text-muted"><?php echo htmlspecialchars($r['descripcion']); ?></small>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($r['servicio_operacion_id']): ?>
                  <small>
                    #<?php echo (int)$r['servicio_operacion_id']; ?> —
                    <?php echo htmlspecialchars(strtoupper($r['serv_tipo']).' / '.($r['proveedor'] ?? '')); ?>
                    <?php if (!empty($r['serv_desc'])): ?>
                      <br><span class="text-muted"><?php echo htmlspecialchars($r['serv_desc']); ?></span>
                    <?php endif; ?>
                  </small>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <td>
                <?php
                  $lbl = ['pendiente'=>'default','confirmado'=>'info','realizado'=>'success','cancelado'=>'danger'];
                  $lc  = $lbl[$r['estado']] ?? 'default';
                ?>
                <span class="label label-<?php echo $lc; ?>"><?php echo $estados[$r['estado']] ?? $r['estado']; ?></span>
              </td>
              <td>
                <!-- Cambiar estado rápido -->
                <form method="post" action="Programacion" class="form-inline" style="display:inline;">
                  <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                  <input type="hidden" name="action" value="set_estado">
                  <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                  <select name="estado" class="form-control input-sm">
                    <?php foreach($estados as $k=>$v): ?>
                      <option value="<?php echo $k; ?>" <?php echo ($r['estado']===$k)?'selected':''; ?>><?php echo $v; ?></option>
                    <?php endforeach; ?>
                  </select>
                  <button class="btn btn-primary btn-sm" title="Actualizar estado"><i class="fa fa-check"></i></button>
                </form>

                <!-- Editar -->
                <a class="btn btn-sm btn-warning" href="Programacion?edit=<?php echo (int)$r['id']; ?>" title="Editar">
                  <i class="fa fa-pencil"></i>
                </a>

                <!-- Eliminar -->
                <form method="post" action="Programacion" style="display:inline;" onsubmit="return confirm('¿Eliminar actividad #<?php echo (int)$r['id']; ?>?');">
                  <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                  <button class="btn btn-sm btn-danger" title="Eliminar"><i class="fa fa-trash"></i></button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <div class="box-footer">
      <small class="text-muted">Usa filtros por rango de fechas y estado para tu panel operativo diario.</small>
    </div>
  </div>
</section>
