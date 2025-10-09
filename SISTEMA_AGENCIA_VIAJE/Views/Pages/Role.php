<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once "Config/Database.php";

// ---- Sólo Admin o Gerencia ----
$rolSesion = $_SESSION['user']['rol'] ?? '';
if (!in_array($rolSesion, ['Admin','Gerencia'], true)) {
?>
<section class="content-header">
  <h1>Roles y permisos <small>Acceso restringido</small></h1>
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

// ---- Utils ----
function flash($key='flash'){ if(!empty($_SESSION[$key])){ echo $_SESSION[$key]; unset($_SESSION[$key]); } }
function set_flash($type,$msg,$key='flash'){ $_SESSION[$key] = '<div class="alert alert-'.$type.'">'.$msg.'</div>'; }
function csrf_get_token(){ if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(16)); return $_SESSION['csrf']; }
function csrf_validate($t){ return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'],$t ?? ''); }

$pdo = Database::getConnection();

// ---- Crear tabla de sobrescritura por usuario si no existe ----
$pdo->exec("
  CREATE TABLE IF NOT EXISTS usuario_permisos (
    usuario_id INT NOT NULL,
    permiso_id INT NOT NULL,
    PRIMARY KEY (usuario_id, permiso_id),
    CONSTRAINT fk_up_user FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_up_perm FOREIGN KEY (permiso_id) REFERENCES permisos(id) ON DELETE CASCADE
  ) ENGINE=InnoDB
");

// ---- Catálogos ----
$areas  = $pdo->query("SELECT id, nombre FROM areas ORDER BY nombre")->fetchAll();
$roles  = $pdo->query("SELECT id, nombre, descripcion, area_id FROM roles ORDER BY nombre")->fetchAll();
$permisos = $pdo->query("SELECT id, nombre, descripcion FROM permisos ORDER BY nombre")->fetchAll();

// Agrupar permisos por prefijo (módulo) antes del punto: ventas.*, operaciones.*, etc.
$permGroups = [];
foreach ($permisos as $p) {
  $parts = explode('.', $p['nombre'], 2);
  $group = $parts[0] ?: 'otros';
  $permGroups[$group][] = $p;
}

// Usuarios para pestañas 2 y 3
$usuarios = $pdo->query("
  SELECT u.id, u.nombre, u.apellido, u.correo, u.activo,
         a.nombre AS area, r.nombre AS rol
  FROM usuarios u
  LEFT JOIN areas a ON a.id=u.area_id
  LEFT JOIN roles r ON r.id=u.rol_id
  ORDER BY u.id DESC
")->fetchAll();

// ---- Acciones POST ----
try {
  // Crear rol
  if (($_POST['action'] ?? '') === 'create_role') {
    if(!csrf_validate($_POST['csrf'] ?? '')) throw new Exception('CSRF inválido.');
    $nombre = trim($_POST['nombre'] ?? '');
    $desc   = trim($_POST['descripcion'] ?? '');
    $areaId = (int)($_POST['area_id'] ?? 0);
    if ($nombre === '' || !$areaId) throw new Exception('Nombre y área son obligatorios.');
    $st = $pdo->prepare("INSERT INTO roles (nombre, descripcion, area_id) VALUES (?,?,?)");
    $st->execute([$nombre, $desc, $areaId]);
    set_flash('success','Rol creado correctamente.');
    header("Location: Role"); exit;
  }

  // Actualizar rol
  if (($_POST['action'] ?? '') === 'update_role') {
    if(!csrf_validate($_POST['csrf'] ?? '')) throw new Exception('CSRF inválido.');
    $id     = (int)($_POST['id'] ?? 0);
    $nombre = trim($_POST['nombre'] ?? '');
    $desc   = trim($_POST['descripcion'] ?? '');
    $areaId = (int)($_POST['area_id'] ?? 0);
    if (!$id || $nombre === '' || !$areaId) throw new Exception('Datos incompletos.');
    $st = $pdo->prepare("UPDATE roles SET nombre=?, descripcion=?, area_id=?, actualizado_en=NOW() WHERE id=?");
    $st->execute([$nombre, $desc, $areaId, $id]);
    set_flash('success','Rol actualizado.');
    header("Location: Role?edit=".$id); exit;
  }

  // Guardar permisos de un rol
  if (($_POST['action'] ?? '') === 'save_perms') {
    if(!csrf_validate($_POST['csrf'] ?? '')) throw new Exception('CSRF inválido.');
    $roleId = (int)($_POST['rol_id'] ?? 0);
    $sel    = $_POST['perms'] ?? [];
    if (!$roleId) throw new Exception('Rol inválido.');
    $pdo->beginTransaction();
    $pdo->prepare("DELETE FROM rol_permisos WHERE rol_id=?")->execute([$roleId]);
    if (!empty($sel)) {
      $ins = $pdo->prepare("INSERT INTO rol_permisos (rol_id, permiso_id) VALUES (?,?)");
      foreach ($sel as $pid) { $ins->execute([$roleId, (int)$pid]); }
    }
    $pdo->commit();
    set_flash('success','Permisos del rol actualizados.');
    header("Location: Role?rol=".$roleId); exit;
  }

  // Asignar rol a usuario
  if (($_POST['action'] ?? '') === 'assign_role') {
    if(!csrf_validate($_POST['csrf'] ?? '')) throw new Exception('CSRF inválido.');
    $userId = (int)($_POST['usuario_id'] ?? 0);
    $roleId = (int)($_POST['rol_id'] ?? 0);
    if (!$userId || !$roleId) throw new Exception('Datos inválidos.');
    $pdo->prepare("UPDATE usuarios SET rol_id=?, actualizado_en=NOW() WHERE id=?")->execute([$roleId, $userId]);
    set_flash('success','Rol asignado al colaborador.');
    header("Location: Role#tab_users"); exit;
  }

  // Guardar permisos por USUARIO (sobrescritura)
  if (($_POST['action'] ?? '') === 'save_user_perms') {
    if(!csrf_validate($_POST['csrf'] ?? '')) throw new Exception('CSRF inválido.');
    $userId = (int)($_POST['usuario_id'] ?? 0);
    $sel    = $_POST['perms'] ?? []; // array de permiso_id
    if (!$userId) throw new Exception('Usuario inválido.');

    $pdo->beginTransaction();
    $pdo->prepare("DELETE FROM usuario_permisos WHERE usuario_id=?")->execute([$userId]);
    if (!empty($sel)) {
      $ins = $pdo->prepare("INSERT INTO usuario_permisos (usuario_id, permiso_id) VALUES (?,?)");
      foreach ($sel as $pid) { $ins->execute([$userId, (int)$pid]); }
    }
    $pdo->commit();

    set_flash('success','Permisos del usuario actualizados (sobrescritura sobre su rol).');
    header("Location: Role?uid=".$userId."#tab_userperms"); exit;
  }

} catch (Throwable $e) {
  $code = ($e instanceof PDOException) ? $e->getCode() : '';
  if ($code === '23000') set_flash('danger','Conflicto de datos (posible duplicado).');
  else set_flash('danger','Error: '.$e->getMessage());
  header("Location: Role"); exit;
}

// ---- Datos de apoyo para vistas ----
$csrf = csrf_get_token();

// Rol seleccionado para matriz
$rolSel = (int)($_GET['rol'] ?? 0);
if (!$rolSel && !empty($roles)) $rolSel = (int)$roles[0]['id'];

// Permisos del rol seleccionado
$permChecked = [];
if ($rolSel) {
  $st = $pdo->prepare("SELECT permiso_id FROM rol_permisos WHERE rol_id=?");
  $st->execute([$rolSel]);
  $permChecked = array_map('intval', array_column($st->fetchAll(), 'permiso_id'));
}

// Cargar rol a editar si viene ?edit
$rolEdit = null;
if (!empty($_GET['edit'])) {
  $id = (int)$_GET['edit'];
  $st = $pdo->prepare("SELECT * FROM roles WHERE id=? LIMIT 1");
  $st->execute([$id]);
  $rolEdit = $st->fetch();
}

// Usuario seleccionado para pestaña 3 (?uid=)
$uidSel = (int)($_GET['uid'] ?? 0);
$userSel = null;
$userRolePerms = [];
$userDirectPerms = [];
$userEffective = []; // union para mostrar “efectivo”

if ($uidSel > 0) {
  $st = $pdo->prepare("SELECT u.*, r.nombre AS rol_nombre FROM usuarios u LEFT JOIN roles r ON r.id=u.rol_id WHERE u.id=?");
  $st->execute([$uidSel]);
  $userSel = $st->fetch();

  if ($userSel) {
    // permisos por rol
    if (!empty($userSel['rol_id'])) {
      $st = $pdo->prepare("SELECT permiso_id FROM rol_permisos WHERE rol_id=?");
      $st->execute([(int)$userSel['rol_id']]);
      $userRolePerms = array_map('intval', array_column($st->fetchAll(), 'permiso_id'));
    }
    // permisos directos por usuario (sobrescritura)
    $st = $pdo->prepare("SELECT permiso_id FROM usuario_permisos WHERE usuario_id=?");
    $st->execute([(int)$userSel['id']]);
    $userDirectPerms = array_map('intval', array_column($st->fetchAll(), 'permiso_id'));

    // Unión para pintar “efectivos”
    $userEffective = array_values(array_unique(array_merge($userRolePerms, $userDirectPerms)));
  }
}
?>

<section class="content-header">
  <h1>Roles y permisos <small>Control de accesos por módulo</small></h1>
  <ol class="breadcrumb">
    <li><a href="Dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
    <li class="active">Roles</li>
  </ol>
</section>

<section class="content">
  <?php flash(); ?>

  <div class="nav-tabs-custom">
    <ul class="nav nav-tabs">
      <li class="active"><a href="#tab_roles" data-toggle="tab"><i class="fa fa-lock"></i> Roles & Permisos</a></li>
      <li><a href="#tab_users" data-toggle="tab"><i class="fa fa-users"></i> Asignar rol a usuarios</a></li>
      <li><a href="#tab_userperms" data-toggle="tab"><i class="fa fa-user-secret"></i> Permisos por usuario</a></li>
    </ul>

    <div class="tab-content">

      <!-- TAB 1: Roles & Permisos -->
      <div class="tab-pane active" id="tab_roles">
        <div class="row">
          <!-- Crear/Editar Rol -->
          <div class="col-md-4">
            <div class="box box-primary box-solid">
              <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-id-card"></i> <?php echo $rolEdit ? 'Editar rol' : 'Nuevo rol'; ?></h3>
              </div>
              <form method="post" action="Role" autocomplete="off">
                <div class="box-body">
                  <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                  <?php if ($rolEdit): ?>
                    <input type="hidden" name="action" value="update_role">
                    <input type="hidden" name="id" value="<?php echo (int)$rolEdit['id']; ?>">
                  <?php else: ?>
                    <input type="hidden" name="action" value="create_role">
                  <?php endif; ?>

                  <div class="form-group">
                    <label>Nombre *</label>
                    <input type="text" class="form-control" name="nombre" required
                           value="<?php echo htmlspecialchars($rolEdit['nombre'] ?? ''); ?>">
                  </div>

                  <div class="form-group">
                    <label>Área *</label>
                    <select class="form-control" name="area_id" required>
                      <option value="">-- Seleccione --</option>
                      <?php foreach ($areas as $a): ?>
                        <option value="<?php echo (int)$a['id']; ?>"
                          <?php echo (isset($rolEdit['area_id']) && (int)$rolEdit['area_id']===(int)$a['id']) ? 'selected' : ''; ?>>
                          <?php echo htmlspecialchars(ucfirst($a['nombre'])); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div class="form-group">
                    <label>Descripción</label>
                    <textarea class="form-control" name="descripcion" rows="3"
                              placeholder="Ej: Acceso total a Contabilidad, lectura en Ventas..."><?php
                      echo htmlspecialchars($rolEdit['descripcion'] ?? '');
                    ?></textarea>
                  </div>
                </div>
                <div class="box-footer">
                  <?php if ($rolEdit): ?>
                    <a href="Role" class="btn btn-default">Cancelar</a>
                    <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Guardar</button>
                  <?php else: ?>
                    <button type="submit" class="btn btn-success"><i class="fa fa-plus"></i> Crear</button>
                  <?php endif; ?>
                </div>
              </form>

              <div class="box box-default" style="margin-top:10px;">
                <div class="box-header with-border"><h3 class="box-title">Roles existentes</h3></div>
                <div class="box-body">
                  <ul class="list-unstyled">
                    <?php foreach ($roles as $r): ?>
                    <li style="margin-bottom:6px;">
                      <i class="fa fa-tag"></i>
                      <a href="Role?rol=<?php echo (int)$r['id']; ?>">
                        <strong><?php echo htmlspecialchars($r['nombre']); ?></strong>
                      </a>
                      <small class="text-muted"> — Área: <?php echo (int)$r['area_id']; ?></small>
                      <a class="btn btn-xs btn-default pull-right" href="Role?edit=<?php echo (int)$r['id']; ?>">
                        <i class="fa fa-pencil"></i> Editar
                      </a>
                    </li>
                    <?php endforeach; ?>
                  </ul>
                </div>
              </div>
            </div>
          </div>

          <!-- Matriz de permisos del rol -->
          <div class="col-md-8">
            <div class="box box-warning">
              <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-key"></i> Permisos del rol</h3>
                <div class="box-tools">
                  <form method="get" action="Role" class="form-inline">
                    <input type="hidden" name="Pages" value="Role">
                    <label>Rol:</label>
                    <select class="form-control" name="rol" onchange="this.form.submit()">
                      <?php foreach ($roles as $r): ?>
                        <option value="<?php echo (int)$r['id']; ?>" <?php echo $rolSel===(int)$r['id']?'selected':''; ?>>
                          <?php echo htmlspecialchars($r['nombre']); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </form>
                </div>
              </div>

              <form method="post" action="Role">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                <input type="hidden" name="action" value="save_perms">
                <input type="hidden" name="rol_id" value="<?php echo (int)$rolSel; ?>">

                <div class="box-body">
                  <?php if (empty($permisos)): ?>
                    <p class="text-muted">No hay permisos definidos. Carga tu seed de permisos.</p>
                  <?php else: ?>
                    <div class="row">
                      <?php foreach ($permGroups as $group => $items): $gh=md5($group); ?>
                        <div class="col-sm-6">
                          <div class="panel panel-default">
                            <div class="panel-heading">
                              <strong><?php echo strtoupper(htmlspecialchars($group)); ?></strong>
                              <div class="pull-right">
                                <a href="#" onclick="checkGroup('<?php echo $gh; ?>',true); return false;"><small>Marcar</small></a> |
                                <a href="#" onclick="checkGroup('<?php echo $gh; ?>',false); return false;"><small>Desmarcar</small></a>
                              </div>
                            </div>
                            <div class="panel-body">
                              <?php foreach ($items as $p): ?>
                              <div class="checkbox">
                                <label class="<?php echo $gh; ?>">
                                  <input type="checkbox" name="perms[]" value="<?php echo (int)$p['id']; ?>"
                                    <?php echo in_array((int)$p['id'],$permChecked,true)?'checked':''; ?>>
                                  <code><?php echo htmlspecialchars($p['nombre']); ?></code>
                                  <small class="text-muted"><?php echo htmlspecialchars($p['descripcion'] ?? ''); ?></small>
                                </label>
                              </div>
                              <?php endforeach; ?>
                            </div>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                </div>

                <div class="box-footer">
                  <button class="btn btn-warning"><i class="fa fa-save"></i> Guardar permisos</button>
                </div>
              </form>
            </div>
          </div>

        </div>
      </div>

      <!-- TAB 2: Asignar rol a usuarios -->
      <div class="tab-pane" id="tab_users">
        <div class="box box-info">
          <div class="box-header with-border">
            <h3 class="box-title"><i class="fa fa-user-plus"></i> Asignación de roles</h3>
          </div>
          <div class="box-body table-responsive no-padding">
            <table class="table table-hover">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Colaborador</th>
                  <th>Correo</th>
                  <th>Área</th>
                  <th>Rol actual</th>
                  <th>Asignar nuevo rol</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($usuarios)): ?>
                  <tr><td colspan="6" class="text-center text-muted">Sin usuarios</td></tr>
                <?php else: foreach ($usuarios as $u): ?>
                <tr>
                  <td><?php echo (int)$u['id']; ?></td>
                  <td><?php echo htmlspecialchars($u['nombre'].' '.$u['apellido']); ?></td>
                  <td><?php echo htmlspecialchars($u['correo']); ?></td>
                  <td><?php echo htmlspecialchars($u['area'] ?: '—'); ?></td>
                  <td><span class="label label-default"><?php echo htmlspecialchars($u['rol'] ?: '—'); ?></span></td>
                  <td>
                    <form class="form-inline" method="post" action="Role" style="margin:0;">
                      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                      <input type="hidden" name="action" value="assign_role">
                      <input type="hidden" name="usuario_id" value="<?php echo (int)$u['id']; ?>">
                      <select name="rol_id" class="form-control input-sm" required>
                        <option value="">-- Seleccione --</option>
                        <?php foreach ($roles as $r): ?>
                          <option value="<?php echo (int)$r['id']; ?>"><?php echo htmlspecialchars($r['nombre']); ?></option>
                        <?php endforeach; ?>
                      </select>
                      <button class="btn btn-sm btn-primary"><i class="fa fa-check"></i></button>
                      <a class="btn btn-sm btn-default" href="Role?uid=<?php echo (int)$u['id']; ?>#tab_userperms">Permisos…</a>
                    </form>
                  </td>
                </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
          <div class="box-footer"><small class="text-muted">Sólo Admin/Gerencia pueden asignar roles.</small></div>
        </div>
      </div>

      <!-- TAB 3: Permisos por USUARIO (nuevo) -->
      <div class="tab-pane" id="tab_userperms">
        <div class="box box-success">
          <div class="box-header with-border">
            <h3 class="box-title"><i class="fa fa-user-secret"></i> Permisos por usuario (sobrescritura)</h3>
            <div class="box-tools">
              <form method="get" action="Role" class="form-inline">
                <input type="hidden" name="Pages" value="Role">
                <label>Usuario:</label>
                <select class="form-control" name="uid" onchange="this.form.submit()">
                  <option value="0">-- Seleccione --</option>
                  <?php foreach ($usuarios as $u): ?>
                    <option value="<?php echo (int)$u['id']; ?>" <?php echo $uidSel===(int)$u['id']?'selected':''; ?>>
                      <?php echo htmlspecialchars($u['nombre'].' '.$u['apellido'].' ('.$u['correo'].')'); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </form>
            </div>
          </div>

          <div class="box-body">
            <?php if (!$userSel): ?>
              <p class="text-muted">Elige un usuario para editar sus permisos.</p>
            <?php else: ?>
              <p>
                <strong><?php echo htmlspecialchars($userSel['nombre'].' '.$userSel['apellido']); ?></strong>
                — Rol actual: <span class="label label-default"><?php echo htmlspecialchars($userSel['rol_nombre'] ?: '—'); ?></span>
              </p>
              <p class="text-muted">Los permisos marcados se guardan como <b>sobrescritura</b> del usuario y se combinan con los permisos de su rol (unión).</p>

              <form method="post" action="Role">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                <input type="hidden" name="action" value="save_user_perms">
                <input type="hidden" name="usuario_id" value="<?php echo (int)$userSel['id']; ?>">

                <div class="row">
                  <?php foreach ($permGroups as $group => $items): $gh=md5('u_'.$group); ?>
                    <div class="col-sm-6">
                      <div class="panel panel-default">
                        <div class="panel-heading">
                          <strong><?php echo strtoupper(htmlspecialchars($group)); ?></strong>
                          <div class="pull-right">
                            <a href="#" onclick="checkGroup('<?php echo $gh; ?>',true); return false;"><small>Marcar</small></a> |
                            <a href="#" onclick="checkGroup('<?php echo $gh; ?>',false); return false;"><small>Desmarcar</small></a>
                          </div>
                        </div>
                        <div class="panel-body">
                          <?php foreach ($items as $p):
                            $pid = (int)$p['id'];
                            $checkedDirect = in_array($pid, $userDirectPerms, true);
                            $effective     = in_array($pid, $userEffective, true);
                          ?>
                          <div class="checkbox">
                            <label class="<?php echo $gh; ?>">
                              <input type="checkbox" name="perms[]" value="<?php echo $pid; ?>" <?php echo $checkedDirect?'checked':''; ?>>
                              <code><?php echo htmlspecialchars($p['nombre']); ?></code>
                              <?php if ($effective && !$checkedDirect): ?>
                                <small class="text-success">(via rol)</small>
                              <?php endif; ?>
                              <small class="text-muted"><?php echo htmlspecialchars($p['descripcion'] ?? ''); ?></small>
                            </label>
                          </div>
                          <?php endforeach; ?>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>

                <div class="box-footer">
                  <button class="btn btn-success"><i class="fa fa-save"></i> Guardar permisos del usuario</button>
                </div>
              </form>
            <?php endif; ?>
          </div>
        </div>
      </div>

    </div>
  </div>
</section>

<script>
function checkGroup(groupHash, checked){
  var inputs = document.querySelectorAll('label.'+groupHash+' input[type=checkbox]');
  for (var i=0;i<inputs.length;i++){ inputs[i].checked = !!checked; }
}
</script>
