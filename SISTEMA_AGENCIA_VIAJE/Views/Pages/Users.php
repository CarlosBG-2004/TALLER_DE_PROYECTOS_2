<?php
// --- SIN espacios antes de <?php --- //
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once "Config/Database.php";

// ---- Autorización básica: Admin o Gerencia ----
$rol = $_SESSION['user']['rol'] ?? '';
if (!in_array($rol, ['Admin','Gerencia'], true)) {
  ?>
  <section class="content-header">
    <h1>Usuarios <small>Acceso restringido</small></h1>
  </section>
  <section class="content">
    <div class="callout callout-danger">
      <h4>No autorizado</h4>
      <p>Esta sección es exclusiva para <strong>Gerencia</strong> y <strong>Administradores</strong>.</p>
    </div>
  </section>
  <?php
  return;
}

// ---------- Utilidades ----------
function redirect($url){ header("Location: $url"); exit; }
function flash($key='flash'){ if(!empty($_SESSION[$key])){ echo $_SESSION[$key]; unset($_SESSION[$key]); } }
function set_flash($type,$msg,$key='flash'){ $_SESSION[$key] = '<div class="alert alert-'.$type.'">'.$msg.'</div>'; }
function csrf_get_token(){ if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(16)); return $_SESSION['csrf']; }
function csrf_validate($t){ return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'],$t ?? ''); }

$pdo = Database::getConnection();

// ---------- Cargar Áreas ----------
$areas = $pdo->query("SELECT id, nombre FROM areas ORDER BY nombre ASC")->fetchAll();

// ---------- Acciones (create/update/delete/toggle) ----------
try {
  // Crear
  if (($_POST['action'] ?? '') === 'create') {
    if (!csrf_validate($_POST['csrf'] ?? '')) throw new Exception('CSRF inválido.');

    $nombre   = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $correo   = trim($_POST['correo'] ?? '');
    $pass     = $_POST['contrasena'] ?? '';
    $area_id  = (int)($_POST['area_id'] ?? 0);
    $activo   = isset($_POST['activo']) ? 1 : 0;

    if ($nombre===''||$apellido===''||$correo===''||$pass===''||!$area_id) {
      throw new Exception('Completa los campos obligatorios (*).');
    }

    // Rol NO se asigna aquí. Para cumplir NOT NULL de rol_id:
    $rol_id = null;
    $st = $pdo->prepare("SELECT id FROM roles WHERE area_id = ? ORDER BY id LIMIT 1");
    $st->execute([$area_id]);
    $rol_id = $st->fetchColumn();

    if (!$rol_id) {
      $rol_id = $pdo->query("SELECT id FROM roles WHERE nombre='Postventa' LIMIT 1")->fetchColumn();
    }
    if (!$rol_id) {
      $rol_id = $pdo->query("SELECT id FROM roles ORDER BY id LIMIT 1")->fetchColumn();
    }

    $hash = password_hash($pass, PASSWORD_BCRYPT);
    $st = $pdo->prepare("INSERT INTO usuarios (nombre, apellido, correo, contrasena_hash, rol_id, area_id, activo)
                         VALUES (?,?,?,?,?,?,?)");
    $st->execute([$nombre, $apellido, $correo, $hash, $rol_id, $area_id, $activo]);

    set_flash('success','Colaborador creado correctamente. <br><small>Nota: el <b>Rol</b> se asigna desde la pantalla <b>Roles</b>.</small>');
    redirect('Users');
  }

  // Actualizar (no tocar rol_id)
  if (($_POST['action'] ?? '') === 'update') {
    if (!csrf_validate($_POST['csrf'] ?? '')) throw new Exception('CSRF inválido.');

    $id       = (int)($_POST['id'] ?? 0);
    $nombre   = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $correo   = trim($_POST['correo'] ?? '');
    $pass     = $_POST['contrasena'] ?? '';
    $area_id  = (int)($_POST['area_id'] ?? 0);
    $activo   = isset($_POST['activo']) ? 1 : 0;

    if (!$id || $nombre===''||$apellido===''||$correo===''||!$area_id) {
      throw new Exception('Completa los campos obligatorios (*).');
    }

    if ($pass !== '') {
      $hash = password_hash($pass, PASSWORD_BCRYPT);
      $sql  = "UPDATE usuarios SET nombre=?, apellido=?, correo=?, contrasena_hash=?, area_id=?, activo=?, actualizado_en=NOW() WHERE id=?";
      $args = [$nombre,$apellido,$correo,$hash,$area_id,$activo,$id];
    } else {
      $sql  = "UPDATE usuarios SET nombre=?, apellido=?, correo=?, area_id=?, activo=?, actualizado_en=NOW() WHERE id=?";
      $args = [$nombre,$apellido,$correo,$area_id,$activo,$id];
    }
    $st = $pdo->prepare($sql);
    $st->execute($args);

    set_flash('success','Colaborador actualizado correctamente. <br><small>Nota: el <b>Rol</b> se mantiene sin cambios.</small>');
    redirect('Users');
  }

  // Inactivar (borrado lógico)
  if (($_POST['action'] ?? '') === 'delete') {
    if (!csrf_validate($_POST['csrf'] ?? '')) throw new Exception('CSRF inválido.');
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) throw new Exception('ID inválido.');

    $st = $pdo->prepare("UPDATE usuarios SET activo=0, actualizado_en=NOW() WHERE id=?");
    $st->execute([$id]);

    set_flash('success','Colaborador inactivado.');
    redirect('Users');
  }

  // Toggle activo/inactivo
  if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $st = $pdo->prepare("UPDATE usuarios SET activo = IF(activo=1,0,1), actualizado_en=NOW() WHERE id=?");
    $st->execute([$id]);
    set_flash('info','Estado actualizado.');
    redirect('Users');
  }

} catch (Throwable $e) {
  if ($e instanceof PDOException && $e->getCode()==='23000') {
    set_flash('danger','El correo ya existe. Prueba con otro.');
  } else {
    set_flash('danger','Error: '.$e->getMessage());
  }
  redirect('Users');
}

// ---------- Cargar usuario a editar (si viene ?edit=ID) ----------
$editUser = null;
if (!empty($_GET['edit'])) {
  $id = (int)$_GET['edit'];
  $st = $pdo->prepare("SELECT * FROM usuarios WHERE id=? LIMIT 1");
  $st->execute([$id]);
  $editUser = $st->fetch();
}

// ---------- Listado con filtros ----------
$q       = trim($_GET['q'] ?? '');
$areaF   = (int)($_GET['area'] ?? 0);
$showAll = isset($_GET['all']) ? 1 : 0;

$params = [];
$where  = [];
if (!$showAll) { $where[] = "u.activo=1"; }
if ($q !== '') {
  $where[] = "(u.nombre LIKE ? OR u.apellido LIKE ? OR u.correo LIKE ?)";
  $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
}
if ($areaF) {
  $where[] = "u.area_id = ?"; $params[] = $areaF;
}

$sqlList = "
  SELECT u.id, u.nombre, u.apellido, u.correo, u.activo, u.creado_en, u.actualizado_en,
         a.nombre AS area
  FROM usuarios u
  LEFT JOIN areas a ON a.id = u.area_id
";
if ($where) $sqlList .= " WHERE ".implode(" AND ", $where);
$sqlList .= " ORDER BY u.id DESC";
$st = $pdo->prepare($sqlList);
$st->execute($params);
$users = $st->fetchAll();

$csrf = csrf_get_token();
?>

<!-- Encabezado -->
<section class="content-header">
  <h1>Colaboradores <small>Gestión de personal por área</small></h1>
  <ol class="breadcrumb">
    <li><a href="Dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
    <li class="active">Usuarios</li>
  </ol>
</section>

<section class="content">
  <?php flash(); ?>

  <div class="row">
    <!-- Formulario Crear/Editar -->
    <div class="col-md-4">
      <div class="box box-primary box-solid">
        <div class="box-header with-border">
          <h3 class="box-title">
            <i class="fa fa-id-badge"></i>
            <?php echo $editUser ? ' Editar colaborador' : ' Nuevo colaborador'; ?>
          </h3>
        </div>
        <form method="post" action="Users" autocomplete="off">
          <div class="box-body">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
            <?php if ($editUser): ?>
              <input type="hidden" name="action" value="update">
              <input type="hidden" name="id" value="<?php echo (int)$editUser['id']; ?>">
            <?php else: ?>
              <input type="hidden" name="action" value="create">
            <?php endif; ?>

            <div class="form-group">
              <label><i class="fa fa-user"></i> Nombre *</label>
              <input type="text" class="form-control" name="nombre" required
                     value="<?php echo htmlspecialchars($editUser['nombre'] ?? ''); ?>">
            </div>

            <div class="form-group">
              <label><i class="fa fa-user"></i> Apellido *</label>
              <input type="text" class="form-control" name="apellido" required
                     value="<?php echo htmlspecialchars($editUser['apellido'] ?? ''); ?>">
            </div>

            <div class="form-group">
              <label><i class="fa fa-envelope"></i> Correo *</label>
              <input type="email" class="form-control" name="correo" required
                     value="<?php echo htmlspecialchars($editUser['correo'] ?? ''); ?>">
            </div>

            <div class="form-group">
              <label><i class="fa fa-lock"></i>
                <?php echo $editUser ? 'Contraseña (dejar vacío para no cambiar)' : 'Contraseña *'; ?>
              </label>
              <input type="password" class="form-control" name="contrasena" <?php echo $editUser ? '' : 'required'; ?>>
            </div>

            <div class="form-group">
              <label><i class="fa fa-sitemap"></i> Área *</label>
              <select class="form-control" name="area_id" required>
                <option value="">-- Seleccione --</option>
                <?php foreach ($areas as $a): ?>
                  <option value="<?php echo (int)$a['id']; ?>"
                    <?php echo (isset($editUser['area_id']) && (int)$editUser['area_id']===(int)$a['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars(ucfirst($a['nombre'])); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <small class="text-muted">El <b>Rol</b> se asigna desde la pantalla <b>Roles</b>.</small>
            </div>

            <div class="checkbox">
              <label>
                <input type="checkbox" name="activo" <?php
                  $checked = $editUser ? ((int)$editUser['activo']===1) : true;
                  echo $checked ? 'checked' : '';
                ?>> Activo
              </label>
            </div>
          </div>
          <div class="box-footer">
            <?php if ($editUser): ?>
              <a href="Users" class="btn btn-default"><i class="fa fa-times"></i> Cancelar</a>
              <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Guardar</button>
            <?php else: ?>
              <button type="submit" class="btn btn-success"><i class="fa fa-plus"></i> Crear</button>
            <?php endif; ?>
          </div>
        </form>
      </div>

      <?php if ($editUser): ?>
      <div class="box box-danger box-solid">
        <div class="box-header with-border">
          <h3 class="box-title"><i class="fa fa-ban"></i> Inactivar colaborador</h3>
        </div>
        <form method="post" action="Users" onsubmit="return confirm('¿Inactivar este usuario?');">
          <div class="box-body">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?php echo (int)$editUser['id']; ?>">
            <p class="text-muted">La inactivación es un borrado lógico para no romper referencias.</p>
          </div>
          <div class="box-footer">
            <button type="submit" class="btn btn-danger"><i class="fa fa-trash"></i> Inactivar</button>
          </div>
        </form>
      </div>
      <?php endif; ?>
    </div>

    <!-- Listado -->
    <div class="col-md-8">
      <div class="box box-default">
        <div class="box-header with-border">
          <h3 class="box-title"><i class="fa fa-users"></i> Colaboradores</h3>
          <div class="box-tools">
            <?php if ($showAll): ?>
              <a class="btn btn-xs btn-default" href="Users"><i class="fa fa-filter"></i> Solo activos</a>
            <?php else: ?>
              <a class="btn btn-xs btn-default" href="Users?all=1"><i class="fa fa-list"></i> Ver todos</a>
            <?php endif; ?>
          </div>
        </div>
        <div class="box-body">
          <form class="form-inline" method="get" action="Users" style="margin-bottom:10px;">
            <div class="form-group">
              <label class="sr-only">Buscar</label>
              <input type="hidden" name="Pages" value="Users">
              <input type="text" class="form-control" name="q" placeholder="Nombre o correo" value="<?php echo htmlspecialchars($q); ?>">
            </div>
            <div class="form-group" style="margin-left:8px;">
              <label class="sr-only">Área</label>
              <select class="form-control" name="area">
                <option value="0">Todas las áreas</option>
                <?php foreach ($areas as $a): ?>
                  <option value="<?php echo (int)$a['id']; ?>" <?php echo $areaF===(int)$a['id']?'selected':''; ?>>
                    <?php echo htmlspecialchars(ucfirst($a['nombre'])); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="checkbox" style="margin-left:8px;">
              <label><input type="checkbox" name="all" value="1" <?php echo $showAll?'checked':''; ?>> Ver inactivos</label>
            </div>
            <button class="btn btn-default" style="margin-left:8px;"><i class="fa fa-search"></i> Buscar</button>
          </form>

          <div class="table-responsive">
            <table class="table table-hover">
              <thead>
                <tr>
                  <th style="width:60px;">ID</th>
                  <th>Colaborador</th>
                  <th>Correo</th>
                  <th>Área</th>
                  <th>Estado</th>
                  <th style="width:170px;">Acciones</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($users)): ?>
                  <tr><td colspan="6" class="text-center text-muted">Sin usuarios</td></tr>
                <?php else: foreach ($users as $u): ?>
                <tr>
                  <td><?php echo (int)$u['id']; ?></td>
                  <td><?php echo htmlspecialchars($u['nombre'].' '.$u['apellido']); ?></td>
                  <td><?php echo htmlspecialchars($u['correo']); ?></td>
                  <td><?php echo htmlspecialchars($u['area'] ?: '—'); ?></td>
                  <td>
                    <?php if ((int)$u['activo']===1): ?>
                      <span class="label label-success">Activo</span>
                    <?php else: ?>
                      <span class="label label-default">Inactivo</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <a class="btn btn-xs btn-primary" href="Users?edit=<?php echo (int)$u['id']; ?>">
                      <i class="fa fa-pencil"></i> Editar
                    </a>
                    <a class="btn btn-xs btn-warning" href="Users?toggle=<?php echo (int)$u['id']; ?>"
                       onclick="return confirm('¿Cambiar estado activo/inactivo?');">
                      <i class="fa fa-toggle-on"></i> Estado
                    </a>
                    <form action="Users" method="post" style="display:inline;" onsubmit="return confirm('¿Inactivar usuario?');">
                      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>">
                      <button class="btn btn-xs btn-danger"><i class="fa fa-trash"></i> Inactivar</button>
                    </form>
                  </td>
                </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
        <div class="box-footer">
          <small class="text-muted">
            El <b>Rol</b> se gestiona en la pantalla <a href="Role"><b>Roles</b></a>.  
            Aquí sólo definimos el <b>Área</b> del colaborador.
          </small>
        </div>
      </div>
    </div>
  </div>
</section>
