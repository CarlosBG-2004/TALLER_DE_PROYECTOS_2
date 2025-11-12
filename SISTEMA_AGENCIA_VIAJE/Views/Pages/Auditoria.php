<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once "Config/Database.php";

/* ======= Control de acceso ======= */
$rol = $_SESSION['user']['rol'] ?? '';
if (!in_array($rol, ['Admin', 'Gerencia'], true)) {
?>
<section class="content-header"><h1>Auditoría <small>Acceso restringido</small></h1></section>
<section class="content">
  <div class="callout callout-danger">
    <h4>No autorizado</h4>
    <p>Esta sección es exclusiva para <b>Gerencia</b> y <b>Administradores</b>.</p>
  </div>
</section>
<?php
  return;
}

/* ======= Funciones útiles ======= */
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function try_pretty_json($txt) {
  $trim = trim((string)$txt);
  if ($trim === '') return '';
  $data = json_decode($trim, true);
  if (json_last_error() === JSON_ERROR_NONE) {
    return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  }
  return $trim;
}

$pdo = Database::getConnection();

/* ======= Filtros ======= */
$df   = trim($_GET['df'] ?? '');       // date from (YYYY-MM-DD)
$dt   = trim($_GET['dt'] ?? '');       // date to
$uid  = (int)($_GET['uid'] ?? 0);      // usuario
$acc  = trim($_GET['accion'] ?? '');   // crear/editar/eliminar/login/logout
$tab  = trim($_GET['tabla'] ?? '');    // LIKE
$q    = trim($_GET['q'] ?? '');        // keyword en valores/ip/ua

$where = "1=1";
$args  = [];

if ($df !== '') { $where .= " AND a.creado_en >= ?"; $args[] = $df." 00:00:00"; }
if ($dt !== '') { $where .= " AND a.creado_en <= ?"; $args[] = $dt." 23:59:59"; }
if ($uid > 0)   { $where .= " AND a.usuario_id = ?"; $args[] = $uid; }
if ($acc !== ''){ $where .= " AND a.accion = ?";     $args[] = $acc; }
if ($tab !== ''){ $where .= " AND a.tabla LIKE ?";   $args[] = "%$tab%"; }
if ($q !== '')  {
  $where .= " AND (a.valores_antiguos LIKE ? OR a.valores_nuevos LIKE ? OR a.user_agent LIKE ? OR a.ip LIKE ?)";
  $like = "%$q%";
  array_push($args, $like, $like, $like, $like);
}

/* ======= Export CSV (opcional) ======= */
if (isset($_GET['export']) && (int)$_GET['export'] === 1) {
  $maxExport = 5000;
  $sql = "
    SELECT a.id, a.creado_en, a.accion, a.tabla, a.registro_id, a.ip, a.user_agent,
           IFNULL(CONCAT(u.nombre, ' ', u.apellido), '') AS usuario,
           a.valores_antiguos, a.valores_nuevos
    FROM auditoria a
    LEFT JOIN usuarios u ON u.id = a.usuario_id
    WHERE $where
    ORDER BY a.id DESC
    LIMIT $maxExport
  ";
  $st = $pdo->prepare($sql);
  $st->execute($args);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename=auditoria_export.csv');
  $out = fopen('php://output', 'w');
  fputcsv($out, ['ID', 'Fecha', 'Acción', 'Tabla', 'RegistroID', 'Usuario', 'IP', 'UserAgent', 'ValoresAntiguos', 'ValoresNuevos']);
  foreach ($rows as $r) {
    fputcsv($out, [
      $r['id'], $r['creado_en'], $r['accion'], $r['tabla'], $r['registro_id'],
      $r['usuario'], $r['ip'], $r['user_agent'],
      try_pretty_json($r['valores_antiguos']),
      try_pretty_json($r['valores_nuevos'])
    ]);
  }
  fclose($out);
  exit;
}

/* ======= Paginación ======= */
$perPage = 50;
$p = max(1, (int)($_GET['p'] ?? 1));
$offset = ($p - 1) * $perPage;

/* Conteo total */
$stc = $pdo->prepare("SELECT COUNT(*) FROM auditoria a WHERE $where");
$stc->execute($args);
$total = (int)$stc->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));

/* Consulta paginada */
$sql = "
  SELECT a.id, a.usuario_id, a.tabla, a.registro_id, a.accion,
         a.valores_antiguos, a.valores_nuevos, a.ip, a.user_agent, a.creado_en,
         u.nombre, u.apellido, u.correo
  FROM auditoria a
  LEFT JOIN usuarios u ON u.id = a.usuario_id
  WHERE $where
  ORDER BY a.id DESC
  LIMIT $perPage OFFSET $offset
";
$st = $pdo->prepare($sql);
$st->execute($args);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

/* Usuarios para filtro */
$users = $pdo->query("SELECT id, CONCAT(nombre, ' ', apellido) AS nom FROM usuarios ORDER BY nombre, apellido")->fetchAll(PDO::FETCH_ASSOC);

/* Helper para mantener querystring en la paginación */
function qs_keep($extra = []) {
  $keep = $_GET;
  foreach ($extra as $k => $v) $keep[$k] = $v;
  return h(http_build_query(array_filter($keep, fn($v) => $v !== '' && $v !== null)));
}
?>

<section class="content-header">
  <h1>Auditoría <small>Registro de actividades</small></h1>
  <ol class="breadcrumb">
    <li><a href="Dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
    <li class="active">Auditoría</li>
  </ol>
</section>

<section class="content">

  <!-- Filtros -->
  <div class="box box-default">
    <div class="box-header with-border">
      <h3 class="box-title"><i class="fa fa-filter"></i> Filtros</h3>
      <div class="box-tools">
        <a class="btn btn-sm btn-success" href="?<?php echo qs_keep(['export' => 1, 'p' => null]); ?>">
          <i class="fa fa-file-excel-o"></i> Exportar CSV
        </a>
      </div>
    </div>
    <div class="box-body">
      <form class="form-inline" method="get" action="Auditoria" style="margin:0;">
        <div class="form-group">
          <label>Desde</label>
          <input type="date" name="df" class="form-control" value="<?php echo h($df); ?>">
        </div>
        <div class="form-group">
          <label>Hasta</label>
          <input type="date" name="dt" class="form-control" value="<?php echo h($dt); ?>">
        </div>
        <div class="form-group">
          <label>Usuario</label>
          <select name="uid" class="form-control">
            <option value="0">-- Todos --</option>
            <?php foreach ($users as $u): ?>
              <option value="<?php echo (int)$u['id']; ?>" <?php echo $uid === (int)$u['id'] ? 'selected' : ''; ?>>
                <?php echo h($u['nom']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Acción</label>
          <select name="accion" class="form-control">
            <option value="">-- Todas --</option>
            <?php foreach (['crear', 'editar', 'eliminar', 'login', 'logout'] as $opt): ?>
              <option value="<?php echo $opt; ?>" <?php echo $acc === $opt ? 'selected' : ''; ?>><?php echo ucfirst($opt); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Tabla</label>
          <input type="text" name="tabla" class="form-control" placeholder="usuarios, expedientes..." value="<?php echo h($tab); ?>">
        </div>
        <div class="form-group" style="min-width:320px;">
          <label>Buscar</label>
          <input type="text" name="q" class="form-control" style="width:100%;" placeholder="Valores antiguos/nuevos, IP, UserAgent" value="<?php echo h($q); ?>">
        </div>
        <button class="btn btn-default"><i class="fa fa-search"></i> Filtrar</button>
        <?php if (!empty($_GET)): ?>
          <a class="btn btn-default" href="Auditoria"><i class="fa fa-times"></i> Limpiar</a>
        <?php endif; ?>
      </form>
    </div>
  </div>

  <!-- Resumen -->
  <div class="box box-solid">
    <div class="box-body">
      <strong>Total:</strong> <?php echo number_format($total); ?>
      <span class="text-muted">| Página <?php echo (int)$p; ?> de <?php echo (int)$pages; ?></span>
    </div>
  </div>

  <!-- Listado -->
  <div class="box box-info">
    <div class="box-header with-border">
      <h3 class="box-title"><i class="fa fa-list-ul"></i> Registros</h3>
    </div>

    <div class="box-body table-responsive no-padding">
      <table class="table table-hover">
        <thead>
          <tr>
            <th>#</th>
            <th>Fecha</th>
            <th>Acción</th>
            <th>Tabla / ID</th>
            <th>Usuario</th>
            <th>IP</th>
            <th>Agente</th>
            <th>Detalle</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="8" class="text-center text-muted">Sin registros</td></tr>
          <?php else: foreach ($rows as $r): 
            $id   = (int)$r['id'];
            $usr  = trim(($r['nombre'] ?? '').' '.($r['apellido'] ?? ''));
            $usr  = $usr !== '' ? $usr : ($r['correo'] ?? '—');
            $collapseId = "det_$id";
          ?>
            <tr>
              <td><?php echo $id; ?></td>
              <td><small><?php echo h($r['creado_en']); ?></small></td>
              <td><span class="label label-<?php
                switch ($r['accion']) {
                  case 'crear':    echo 'success'; break;
                  case 'editar':   echo 'warning'; break;
                  case 'eliminar': echo 'danger';  break;
                  default:         echo 'default';
                }
              ?>"><?php echo h($r['accion']); ?></span></td>
              <td><code><?php echo h($r['tabla']); ?></code> #<?php echo (int)$r['registro_id']; ?></td>
              <td><?php echo h($usr ?: '—'); ?></td>
              <td><?php echo h($r['ip'] ?: '—'); ?></td>
              <td title="<?php echo h($r['user_agent']); ?>">
                <small><?php echo h(mb_strimwidth($r['user_agent'] ?? '—', 0, 35, '…','UTF-8')); ?></small>
              </td>
              <td>
                <button class="btn btn-xs btn-info" data-toggle="collapse" data-target="#<?php echo $collapseId; ?>">
                  <i class="fa fa-search-plus"></i> Ver
                </button>
              </td>
            </tr>
            <tr id="<?php echo $collapseId; ?>" class="collapse">
              <td colspan="8">
                <div class="row">
                  <div class="col-sm-6">
                    <h5><i class="fa fa-history"></i> Valores antiguos</h5>
                    <pre style="max-height:260px; overflow:auto;"><?php echo h(try_pretty_json($r['valores_antiguos'])); ?></pre>
                  </div>
                  <div class="col-sm-6">
                    <h5><i class="fa fa-exchange"></i> Valores nuevos</h5>
                    <pre style="max-height:260px; overflow:auto;"><?php echo h(try_pretty_json($r['valores_nuevos'] ?? '')); ?></pre>
                  </div>
                </div>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Paginación -->
    <div class="box-footer clearfix">
      <ul class="pagination pagination-sm no-margin pull-right">
        <?php
          // construir url base manteniendo filtros
          for ($i=1; $i <= $pages; $i++):
            $active = ($i === $p) ? ' class="active"' : '';
            $qs = qs_keep(['p'=>$i]);
        ?>
          <li<?php echo $active; ?>><a href="?<?php echo $qs; ?>"><?php echo $i; ?></a></li>
        <?php endfor; ?>
      </ul>
    </div>
  </div>
</section>
