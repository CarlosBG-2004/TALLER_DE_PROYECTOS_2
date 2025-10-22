<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once "Config/Database.php";

/* ===== Acceso ===== */
$areas = array_map('strtolower', $_SESSION['areas'] ?? []);
$rol   = $_SESSION['user']['rol'] ?? '';
$allow = in_array('operaciones', $areas, true) || in_array($rol, ['Admin','Gerencia'], true);

if (!$allow) {
?>
<section class="content-header"><h1>Mantenimiento e Historial <small>Acceso restringido</small></h1></section>
<section class="content"><div class="callout callout-danger"><h4>No autorizado</h4><p>Solo <b>Operaciones</b> o <b>Admin/Gerencia</b>.</p></div></section>
<?php
  return;
}

/* ===== Utils ===== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function flash_set($type,$msg,$k='flash'){ $_SESSION[$k] = '<div class="alert alert-'.$type.'">'.$msg.'</div>'; }
function flash_show($k='flash'){ if(!empty($_SESSION[$k])){ echo $_SESSION[$k]; unset($_SESSION[$k]); } }
function csrf(){ if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(16)); return $_SESSION['csrf']; }
function csrf_ok($t){ return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'],$t ?? ''); }

$pdo  = Database::getConnection();
$csrf = csrf();

/* ===== Tabla de mantenimientos (si no existe) ===== */
$pdo->exec("
  CREATE TABLE IF NOT EXISTS inventario_mantenimientos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bien_id INT NOT NULL,
    fecha_programada DATE NOT NULL,
    fecha_realizada DATE NULL,
    tipo ENUM('preventivo','correctivo','garantia','inspeccion') NOT NULL DEFAULT 'preventivo',
    estado ENUM('pendiente','programado','realizado','cancelado') NOT NULL DEFAULT 'pendiente',
    descripcion TEXT NULL,
    responsable_id INT NULL,
    costo DECIMAL(12,2) NULL,
    adjunto_id INT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (bien_id) REFERENCES inventario_bienes(id)
      ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (responsable_id) REFERENCES usuarios(id)
      ON UPDATE CASCADE ON DELETE SET NULL,
    FOREIGN KEY (adjunto_id) REFERENCES adjuntos(id)
      ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_mto_fecha (fecha_programada),
    INDEX idx_mto_bien (bien_id, estado)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

/* ===== Catálogo de bienes ===== */
$bienes = $pdo->query("SELECT id, COALESCE(codigo, CONCAT('B-',id)) AS codigo, nombre FROM inventario_bienes ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

/* ===== Acciones ===== */
try{
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok($_POST['csrf'] ?? '')) { throw new Exception('CSRF inválido.'); }
    $action = $_POST['action'] ?? '';

    if ($action === 'create_schedule') {
      $bien_id = (int)($_POST['bien_id'] ?? 0);
      $fecha   = trim($_POST['fecha_programada'] ?? '');
      $tipo    = $_POST['tipo'] ?? 'preventivo';
      $estado  = $_POST['estado'] ?? 'programado';
      $desc    = trim($_POST['descripcion'] ?? '');
      $resp_id = (int)($_POST['responsable_id'] ?? 0);
      $costo   = $_POST['costo'] !== '' ? (float)$_POST['costo'] : null;

      if (!$bien_id || $fecha === '') throw new Exception('Bien y fecha son obligatorios.');
      $st = $pdo->prepare("
        INSERT INTO inventario_mantenimientos
          (bien_id, fecha_programada, tipo, estado, descripcion, responsable_id, costo)
        VALUES (?,?,?,?,?,?,?)
      ");
      $st->execute([$bien_id, $fecha, $tipo, $estado, $desc ?: null, $resp_id ?: null, $costo]);
      flash_set('success','Mantenimiento programado.');
      header("Location: InventarioMovimientos"); exit;
    }

    if ($action === 'mark_done') {
      $id = (int)($_POST['id'] ?? 0);
      $fecha_real = trim($_POST['fecha_realizada'] ?? '');
      $costo      = $_POST['costo'] !== '' ? (float)$_POST['costo'] : null;
      if (!$id) throw new Exception('ID inválido.');
      if ($fecha_real === '') $fecha_real = date('Y-m-d');
      $st = $pdo->prepare("UPDATE inventario_mantenimientos SET estado='realizado', fecha_realizada=?, costo=? WHERE id=?");
      $st->execute([$fecha_real, $costo, $id]);
      flash_set('success','Mantenimiento marcado como realizado.');
      header("Location: InventarioMovimientos"); exit;
    }

    if ($action === 'cancel') {
      $id = (int)($_POST['id'] ?? 0);
      if (!$id) throw new Exception('ID inválido.');
      $pdo->prepare("UPDATE inventario_mantenimientos SET estado='cancelado' WHERE id=?")->execute([$id]);
      flash_set('success','Mantenimiento cancelado.');
      header("Location: InventarioMovimientos"); exit;
    }

    if ($action === 'delete') {
      $id = (int)($_POST['id'] ?? 0);
      if (!$id) throw new Exception('ID inválido.');
      $pdo->prepare("DELETE FROM inventario_mantenimientos WHERE id=?")->execute([$id]);
      flash_set('success','Registro eliminado.');
      header("Location: InventarioMovimientos"); exit;
    }
  }
} catch(Throwable $e){
  $code = ($e instanceof PDOException) ? $e->getCode() : '';
  if ($code === '23000') flash_set('danger','No se puede eliminar/guardar por conflicto de datos.');
  else flash_set('danger','Error: '.$e->getMessage());
  header("Location: InventarioMovimientos"); exit;
}

/* ===== Filtros e historial ===== */
$bien_f = (int)($_GET['bien'] ?? 0);
$est_f  = trim($_GET['estado'] ?? '');
$df     = trim($_GET['df'] ?? '');
$dt     = trim($_GET['dt'] ?? '');

$where = "1=1";
$args  = [];
if ($bien_f > 0) { $where .= " AND m.bien_id=?"; $args[] = $bien_f; }
if ($est_f !== '') { $where .= " AND m.estado=?"; $args[] = $est_f; }
if ($df !== '') { $where .= " AND m.fecha_programada >= ?"; $args[] = $df; }
if ($dt !== '') { $where .= " AND m.fecha_programada <= ?"; $args[] = $dt; }

/* Paginación */
$perPage = 25;
$p = max(1, (int)($_GET['p'] ?? 1));
$offset = ($p-1)*$perPage;

/* Conteo total */
$stc = $pdo->prepare("SELECT COUNT(*) FROM inventario_mantenimientos m WHERE $where");
$stc->execute($args);
$total = (int)$stc->fetchColumn();
$pages = max(1, (int)ceil($total/$perPage));

/* Historial listado */
$sql = "
  SELECT m.id, m.bien_id, m.fecha_programada, m.fecha_realizada, m.tipo, m.estado,
         m.descripcion, m.costo, ib.nombre AS bien, COALESCE(ib.codigo, CONCAT('B-',ib.id)) AS codigo
  FROM inventario_mantenimientos m
  JOIN inventario_bienes ib ON ib.id = m.bien_id
  WHERE $where
  ORDER BY m.fecha_programada DESC, m.id DESC
  LIMIT $perPage OFFSET $offset
";
$sth = $pdo->prepare($sql);
$sth->execute($args);
$hist = $sth->fetchAll(PDO::FETCH_ASSOC);

/* ===== Alertas ===== */
$today = date('Y-m-d');
$soon  = date('Y-m-d', strtotime('+7 days'));

/* Vencidos: programados pendientes con fecha < hoy */
$st = $pdo->prepare("
  SELECT m.id, m.fecha_programada, ib.nombre AS bien, COALESCE(ib.codigo, CONCAT('B-',ib.id)) AS codigo
  FROM inventario_mantenimientos m
  JOIN inventario_bienes ib ON ib.id = m.bien_id
  WHERE m.estado IN ('pendiente','programado') AND m.fecha_programada < ?
  ORDER BY m.fecha_programada ASC
  LIMIT 100
");
$st->execute([$today]);
$alert_overdue = $st->fetchAll(PDO::FETCH_ASSOC);

/* Próximos 7 días */
$st = $pdo->prepare("
  SELECT m.id, m.fecha_programada, ib.nombre AS bien, COALESCE(ib.codigo, CONCAT('B-',ib.id)) AS codigo
  FROM inventario_mantenimientos m
  JOIN inventario_bienes ib ON ib.id = m.bien_id
  WHERE m.estado IN ('pendiente','programado') AND m.fecha_programada BETWEEN ? AND ?
  ORDER BY m.fecha_programada ASC
  LIMIT 100
");
$st->execute([$today, $soon]);
$alert_soon = $st->fetchAll(PDO::FETCH_ASSOC);

/* ===== Export CSV (historial con filtros) ===== */
if (isset($_GET['export']) && (int)$_GET['export'] === 1) {
  $exportSql = "
    SELECT m.id, ib.codigo, ib.nombre AS bien, m.fecha_programada, m.fecha_realizada,
           m.tipo, m.estado, m.costo, m.descripcion
    FROM inventario_mantenimientos m
    JOIN inventario_bienes ib ON ib.id = m.bien_id
    WHERE $where
    ORDER BY m.fecha_programada DESC, m.id DESC
    LIMIT 5000
  ";
  $ste = $pdo->prepare($exportSql);
  $ste->execute($args);
  $rows = $ste->fetchAll(PDO::FETCH_ASSOC);

  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename=historial_mantenimientos.csv');
  $out = fopen('php://output', 'w');
  fputcsv($out, ['ID','Código','Bien','Fecha Programada','Fecha Realizada','Tipo','Estado','Costo','Descripción']);
  foreach ($rows as $r) {
    fputcsv($out, [
      $r['id'], $r['codigo'], $r['bien'], $r['fecha_programada'], $r['fecha_realizada'],
      $r['tipo'], $r['estado'], $r['costo'], preg_replace('/\s+/', ' ', trim((string)$r['descripcion']))
    ]);
  }
  fclose($out);
  exit;
}

/* Helper QS */
function qs_keep($extra=[]){
  $keep = $_GET;
  foreach($extra as $k=>$v){ if($v===null) unset($keep[$k]); else $keep[$k]=$v; }
  return h(http_build_query(array_filter($keep, fn($v)=>$v!=='' && $v!==null)));
}
?>

<section class="content-header">
  <h1>Inventario — Mantenimientos <small>Calendario, alertas e historial</small></h1>
  <ol class="breadcrumb">
    <li><a href="Dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
    <li class="active">Inventario / Mantenimientos</li>
  </ol>
</section>

<section class="content">
  <?php flash_show(); ?>

  <!-- Programar mantenimiento -->
  <div class="box box-primary">
    <div class="box-header with-border">
      <h3 class="box-title"><i class="fa fa-calendar-plus-o"></i> Programar mantenimiento</h3>
    </div>
    <form method="post" action="InventarioMovimientos" autocomplete="off">
      <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
      <input type="hidden" name="action" value="create_schedule">
      <div class="box-body">
        <div class="row">
          <div class="col-sm-4">
            <div class="form-group">
              <label>Bien *</label>
              <select name="bien_id" class="form-control" required>
                <option value="">-- Seleccione --</option>
                <?php foreach ($bienes as $b): ?>
                  <option value="<?php echo (int)$b['id']; ?>">
                    <?php echo h($b['codigo'].' — '.$b['nombre']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="col-sm-3">
            <div class="form-group">
              <label>Fecha programada *</label>
              <input type="date" name="fecha_programada" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
            </div>
          </div>
          <div class="col-sm-3">
            <div class="form-group">
              <label>Tipo</label>
              <select name="tipo" class="form-control">
                <option value="preventivo">Preventivo</option>
                <option value="correctivo">Correctivo</option>
                <option value="inspeccion">Inspección</option>
                <option value="garantia">Garantía</option>
              </select>
            </div>
          </div>
          <div class="col-sm-2">
            <div class="form-group">
              <label>Estado</label>
              <select name="estado" class="form-control">
                <option value="programado">Programado</option>
                <option value="pendiente">Pendiente</option>
              </select>
            </div>
          </div>
        </div><!-- row -->
        <div class="row">
          <div class="col-sm-8">
            <div class="form-group">
              <label>Descripción</label>
              <input type="text" name="descripcion" class="form-control" placeholder="Detalle breve">
            </div>
          </div>
          <div class="col-sm-2">
            <div class="form-group">
              <label>Responsable (ID)</label>
              <input type="number" name="responsable_id" class="form-control" placeholder="Opcional">
              <small class="text-muted">ID de usuario</small>
            </div>
          </div>
          <div class="col-sm-2">
            <div class="form-group">
              <label>Costo</label>
              <input type="number" step="0.01" name="costo" class="form-control" placeholder="0.00">
            </div>
          </div>
        </div>
      </div>
      <div class="box-footer">
        <button class="btn btn-success"><i class="fa fa-plus"></i> Programar</button>
      </div>
    </form>
  </div>

  <!-- Alertas -->
  <div class="row">
    <div class="col-sm-6">
      <div class="box box-danger">
        <div class="box-header with-border">
          <h3 class="box-title"><i class="fa fa-exclamation-triangle"></i> Vencidos</h3>
        </div>
        <div class="box-body no-padding">
          <table class="table table-condensed">
            <thead><tr><th>Fecha</th><th>Bien</th><th>Acción</th></tr></thead>
            <tbody>
              <?php if (empty($alert_overdue)): ?>
                <tr><td colspan="3" class="text-center text-muted">Sin vencidos</td></tr>
              <?php else: foreach ($alert_overdue as $a): ?>
                <tr>
                  <td><?php echo h($a['fecha_programada']); ?></td>
                  <td><strong><?php echo h($a['codigo'].' — '.$a['bien']); ?></strong></td>
                  <td>
                    <form method="post" action="InventarioMovimientos" class="form-inline" style="display:inline;">
                      <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                      <input type="hidden" name="action" value="mark_done">
                      <input type="hidden" name="id" value="<?php echo (int)$a['id']; ?>">
                      <button class="btn btn-xs btn-success"><i class="fa fa-check"></i> Realizado</button>
                    </form>
                    <form method="post" action="InventarioMovimientos" class="form-inline" style="display:inline;"
                          onsubmit="return confirm('¿Cancelar este mantenimiento?');">
                      <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                      <input type="hidden" name="action" value="cancel">
                      <input type="hidden" name="id" value="<?php echo (int)$a['id']; ?>">
                      <button class="btn btn-xs btn-default"><i class="fa fa-ban"></i> Cancelar</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div><!-- col -->

    <div class="col-sm-6">
      <div class="box box-warning">
        <div class="box-header with-border">
          <h3 class="box-title"><i class="fa fa-bell-o"></i> Próximos 7 días</h3>
        </div>
        <div class="box-body no-padding">
          <table class="table table-condensed">
            <thead><tr><th>Fecha</th><th>Bien</th><th>Acción</th></tr></thead>
            <tbody>
              <?php if (empty($alert_soon)): ?>
                <tr><td colspan="3" class="text-center text-muted">Sin próximos</td></tr>
              <?php else: foreach ($alert_soon as $a): ?>
                <tr>
                  <td><?php echo h($a['fecha_programada']); ?></td>
                  <td><strong><?php echo h($a['codigo'].' — '.$a['bien']); ?></strong></td>
                  <td>
                    <form method="post" action="InventarioMovimientos" class="form-inline" style="display:inline;">
                      <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                      <input type="hidden" name="action" value="mark_done">
                      <input type="hidden" name="id" value="<?php echo (int)$a['id']; ?>">
                      <button class="btn btn-xs btn-success"><i class="fa fa-check"></i> Realizado</button>
                    </form>
                    <form method="post" action="InventarioMovimientos" class="form-inline" style="display:inline;"
                          onsubmit="return confirm('¿Cancelar este mantenimiento?');">
                      <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                      <input type="hidden" name="action" value="cancel">
                      <input type="hidden" name="id" value="<?php echo (int)$a['id']; ?>">
                      <button class="btn btn-xs btn-default"><i class="fa fa-ban"></i> Cancelar</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div><!-- col -->
  </div><!-- row -->

  <!-- Filtros Historial -->
  <div class="box box-default">
    <div class="box-header with-border">
      <h3 class="box-title"><i class="fa fa-filter"></i> Filtros de historial</h3>
      <div class="box-tools">
        <a class="btn btn-sm btn-success" href="?<?php echo qs_keep(['export'=>1,'p'=>null]); ?>">
          <i class="fa fa-file-excel-o"></i> Exportar CSV
        </a>
      </div>
    </div>
    <div class="box-body">
      <form class="form-inline" method="get" action="InventarioMovimientos" style="margin:0;">
        <div class="form-group">
          <label>Bien</label>
          <select name="bien" class="form-control">
            <option value="0">-- Todos --</option>
            <?php foreach ($bienes as $b): ?>
              <option value="<?php echo (int)$b['id']; ?>" <?php echo $bien_f===(int)$b['id']?'selected':''; ?>>
                <?php echo h($b['codigo'].' — '.$b['nombre']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Estado</label>
          <select name="estado" class="form-control">
            <option value="">-- Todos --</option>
            <?php foreach (['pendiente','programado','realizado','cancelado'] as $s): ?>
              <option value="<?php echo $s; ?>" <?php echo $est_f===$s?'selected':''; ?>><?php echo ucfirst($s); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label>Desde</label>
          <input type="date" name="df" class="form-control" value="<?php echo h($df); ?>">
        </div>
        <div class="form-group"><label>Hasta</label>
          <input type="date" name="dt" class="form-control" value="<?php echo h($dt); ?>">
        </div>
        <button class="btn btn-default"><i class="fa fa-search"></i> Filtrar</button>
        <?php if (!empty($_GET)): ?>
          <a class="btn btn-default" href="InventarioMovimientos"><i class="fa fa-times"></i> Limpiar</a>
        <?php endif; ?>
      </form>
    </div>
  </div>

  <!-- Historial -->
  <div class="box box-info">
    <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-history"></i> Historial</h3></div>
    <div class="box-body table-responsive no-padding">
      <table class="table table-hover">
        <thead>
          <tr>
            <th>#</th>
            <th>Bien</th>
            <th>Fecha Prog.</th>
            <th>Fecha Real.</th>
            <th>Tipo</th>
            <th>Estado</th>
            <th>Costo</th>
            <th>Descripción</th>
            <th style="min-width:140px;">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($hist)): ?>
            <tr><td colspan="9" class="text-center text-muted">Sin registros</td></tr>
          <?php else: foreach ($hist as $r): ?>
            <tr>
              <td><?php echo (int)$r['id']; ?></td>
              <td><strong><?php echo h($r['codigo'].' — '.$r['bien']); ?></strong></td>
              <td><?php echo h($r['fecha_programada']); ?></td>
              <td><?php echo h($r['fecha_realizada'] ?: '—'); ?></td>
              <td><span class="label label-default"><?php echo h($r['tipo']); ?></span></td>
              <td>
                <?php
                  $label = 'default';
                  if ($r['estado']==='realizado') $label='success';
                  elseif ($r['estado']==='programado') $label='warning';
                  elseif ($r['estado']==='pendiente') $label='info';
                  elseif ($r['estado']==='cancelado') $label='default';
                ?>
                <span class="label label-<?php echo $label; ?>"><?php echo h($r['estado']); ?></span>
              </td>
              <td><?php echo $r['costo']!==null ? number_format((float)$r['costo'],2) : '—'; ?></td>
              <td><?php echo h($r['descripcion'] ?: ''); ?></td>
              <td>
                <?php if ($r['estado']!=='realizado'): ?>
                  <form method="post" action="InventarioMovimientos" style="display:inline;">
                    <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                    <input type="hidden" name="action" value="mark_done">
                    <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                    <input type="hidden" name="fecha_realizada" value="<?php echo date('Y-m-d'); ?>">
                    <button class="btn btn-xs btn-success"><i class="fa fa-check"></i> Realizado</button>
                  </form>
                  <form method="post" action="InventarioMovimientos" style="display:inline;"
                        onsubmit="return confirm('¿Cancelar mantenimiento #<?php echo (int)$r['id']; ?>?');">
                    <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                    <input type="hidden" name="action" value="cancel">
                    <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                    <button class="btn btn-xs btn-default"><i class="fa fa-ban"></i> Cancelar</button>
                  </form>
                <?php endif; ?>
                <form method="post" action="InventarioMovimientos" style="display:inline;"
                      onsubmit="return confirm('¿Eliminar registro #<?php echo (int)$r['id']; ?>?');">
                  <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                  <button class="btn btn-xs btn-danger"><i class="fa fa-trash"></i></button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <div class="box-footer clearfix">
      <ul class="pagination pagination-sm no-margin pull-right">
        <?php for($i=1;$i<=$pages;$i++): ?>
          <li class="<?php echo $i===$p?'active':''; ?>">
            <a href="?<?php echo qs_keep(['p'=>$i]); ?>"><?php echo $i; ?></a>
          </li>
        <?php endfor; ?>
      </ul>
    </div>
  </div>
</section>
