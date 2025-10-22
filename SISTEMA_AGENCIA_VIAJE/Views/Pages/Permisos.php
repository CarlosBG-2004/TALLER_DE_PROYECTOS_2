<?php
// --- SIN espacios antes de <?php --- //
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once "Config/Database.php";

// ---- Sólo Admin o Gerencia ----
$rolSesion = $_SESSION['user']['rol'] ?? '';
if (!in_array($rolSesion, ['Admin','Gerencia'], true)) {
  ?>
  <section class="content-header">
    <h1>Permisos <small>Acceso restringido</small></h1>
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
function redirect($url){ header("Location: $url"); exit; }
function flash($key='flash'){ if(!empty($_SESSION[$key])){ echo $_SESSION[$key]; unset($_SESSION[$key]); } }
function set_flash($type,$msg,$key='flash'){ $_SESSION[$key] = '<div class="alert alert-'.$type.'">'.$msg.'</div>'; }
function csrf_get_token(){ if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(16)); return $_SESSION['csrf']; }
function csrf_validate($t){ return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'],$t ?? ''); }

$pdo = Database::getConnection();

// ---- Asegurar tabla de permisos directos por usuario ----
$pdo->exec("
  CREATE TABLE IF NOT EXISTS usuario_permisos (
    usuario_id INT NOT NULL,
    permiso_id INT NOT NULL,
    PRIMARY KEY (usuario_id, permiso_id),
    CONSTRAINT fk_up_user FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
      ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_up_perm FOREIGN KEY (permiso_id) REFERENCES permisos(id)
      ON UPDATE CASCADE ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ---- Cargar catálogos ----
$roles = $pdo->query("SELECT id, nombre FROM roles ORDER BY nombre")->fetchAll();
$users = $pdo->query("SELECT id, CONCAT(nombre,' ',apellido) AS nom, correo FROM usuarios ORDER BY id DESC")->fetchAll();

$permisos = $pdo->query("SELECT id, nombre, descripcion FROM permisos ORDER BY nombre")->fetchAll();
$permGroups = [];
foreach ($permisos as $p) {
  $parts = explode('.', $p['nombre'], 2);
  $group = $parts[0] ?: 'otros';
  $permGroups[$group][] = $p;
}

// ---- Acciones POST ----
try {

  // Sembrar permisos básicos (coinciden con el menú que usamos)
  if (($_POST['action'] ?? '') === 'seed_basic') {
    if(!csrf_validate($_POST['csrf'] ?? '')) throw new Exception('CSRF inválido.');
    $base = [
      // ÁREAS PRINCIPALES (para mostrar módulos del menú)
      ['dashboard.ver',        'Acceso al panel principal'],
      ['ventas.ver',           'Ver módulo de Ventas'],
      ['operaciones.ver',      'Ver módulo de Operaciones'],
      ['inventario.ver',       'Ver módulo de Inventario'],
      ['contabilidad.ver',     'Ver módulo de Contabilidad'],
      ['marketing.ver',        'Ver módulo de Marketing'],
      ['postventa.ver',        'Ver módulo de Postventa'],
      ['maestros.ver',         'Ver Maestros (Clientes, Agencias, Proveedores)'],
      ['gerencia.ver',         'Ver Panel Gerencial'],
      ['auditoria.ver',        'Ver Auditoría'],
      ['sistema.ver',          'Ver Usuarios/Roles/Permisos'],

      // Permisos comunes por módulo (opcional, por si luego refinan)
      ['ventas.crear',         'Crear expediente / venta'],
      ['ventas.editar',        'Editar expediente / venta'],
      ['ventas.pagos',         'Gestionar pagos'],
      ['ventas.calendario',    'Acceso al calendario de ventas'],

      ['operaciones.servicios','Gestionar servicios y reservas'],
      ['operaciones.programar','Programación diaria de tours'],

      ['inventario.bienes',    'Gestionar bienes'],
      ['inventario.movimientos','Gestionar movimientos de inventario'],
      ['inventario.mantenimiento','Gestionar mantenimiento / alertas'],

      ['contabilidad.movimientos','Gestionar movimientos contables'],
      ['contabilidad.categorias','Gestionar categorías contables'],
      ['contabilidad.caja',      'Gestionar caja chica'],
      ['contabilidad.reportes',  'Ver reportes contables'],

      ['marketing.tareas',     'Gestionar tareas'],
      ['marketing.plantillas', 'Gestionar plantillas'],
      ['marketing.campanas',   'Gestionar campañas'],
      ['marketing.calendario', 'Ver calendario marketing'],

      ['postventa.interacciones','Gestionar interacciones'],

      ['maestros.clientes',    'Gestionar clientes'],
      ['maestros.agencias',    'Gestionar agencias'],
      ['maestros.proveedores', 'Gestionar proveedores'],

      ['gerencia.panel',       'Acceso a panel gerencial'],
      ['auditoria.registros',  'Ver registros de auditoría'],

      ['sistema.usuarios',     'Gestionar usuarios'],
      ['sistema.roles',        'Gestionar roles'],
      ['sistema.permisos',     'Gestionar permisos'],
    ];

    $ins = $pdo->prepare("INSERT IGNORE INTO permisos (nombre, descripcion) VALUES (?,?)");
    foreach ($base as $row) { $ins->execute($row); }

    set_flash('success','Permisos básicos cargados correctamente.');
    redirect('Permisos');
  }

  // Crear permiso
  if (($_POST['action'] ?? '') === 'create_perm') {
    if(!csrf_validate($_POST['csrf'] ?? '')) throw new Exception('CSRF inválido.');
    $nombre = trim($_POST['nombre'] ?? '');
    $desc   = trim($_POST['descripcion'] ?? '');
    if ($nombre === '') throw new Exception('El nombre es obligatorio.');
    // Validación simple: prefijo.modulo
    if (strpos($nombre, '.') === false) throw new Exception('Usa el formato modulo.accion (ej: ventas.ver)');
    $st = $pdo->prepare("INSERT INTO permisos (nombre, descripcion) VALUES (?,?)");
    $st->execute([$nombre, $desc]);
    set_flash('success','Permiso creado.');
    redirect('Permisos#catalogo');
  }

  // Actualizar permiso (solo descripción, opcional nombre)
  if (($_POST['action'] ?? '') === 'update_perm') {
    if(!csrf_validate($_POST['csrf'] ?? '')) throw new Exception('CSRF inválido.');
    $id     = (int)($_POST['id'] ?? 0);
    $nombre = trim($_POST['nombre'] ?? '');
    $desc   = trim($_POST['descripcion'] ?? '');
    if (!$id || $nombre==='') throw new Exception('Datos incompletos.');
    if (strpos($nombre, '.') === false) throw new Exception('Formato inválido de permiso.');
    $st = $pdo->prepare("UPDATE permisos SET nombre=?, descripcion=? WHERE id=?");
    $st->execute([$nombre, $desc, $id]);
    set_flash('success','Permiso actualizado.');
    redirect('Permisos#catalogo');
  }

  // Eliminar permiso (cascade borra en roles/usuarios)
  if (($_POST['action'] ?? '') === 'delete_perm') {
    if(!csrf_validate($_POST['csrf'] ?? '')) throw new Exception('CSRF inválido.');
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) throw new Exception('ID inválido.');
    $st = $pdo->prepare("DELETE FROM permisos WHERE id=?");
    $st->execute([$id]);
    set_flash('success','Permiso eliminado.');
    redirect('Permisos#catalogo');
  }

  // Guardar permisos de un rol
  if (($_POST['action'] ?? '') === 'save_role_perms') {
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
    redirect('Permisos#roles');
  }

  // Guardar permisos directos de un usuario
  if (($_POST['action'] ?? '') === 'save_user_perms') {
    if(!csrf_validate($_POST['csrf'] ?? '')) throw new Exception('CSRF inválido.');
    $uid = (int)($_POST['usuario_id'] ?? 0);
    $sel = $_POST['perms'] ?? [];
    if (!$uid) throw new Exception('Usuario inválido.');
    $pdo->beginTransaction();
    $pdo->prepare("DELETE FROM usuario_permisos WHERE usuario_id=?")->execute([$uid]);
    if (!empty($sel)) {
      $ins = $pdo->prepare("INSERT INTO usuario_permisos (usuario_id, permiso_id) VALUES (?,?)");
      foreach ($sel as $pid) { $ins->execute([$uid, (int)$pid]); }
    }
    $pdo->commit();
    set_flash('success','Permisos del usuario actualizados.');
    redirect('Permisos#usuarios');
  }

} catch (Throwable $e) {
  $code = ($e instanceof PDOException) ? $e->getCode() : '';
  if ($code === '23000') set_flash('danger','Conflicto o duplicado.');
  else set_flash('danger','Error: '.$e->getMessage());
  redirect('Permisos');
}

// ---- Parámetros de selección para pintar matrices ----
$csrf = csrf_get_token();

$rolSel = (int)($_GET['rol'] ?? 0);
if (!$rolSel && !empty($roles)) $rolSel = (int)$roles[0]['id'];

$uidSel = (int)($_GET['uid'] ?? 0);
if (!$uidSel && !empty($users)) $uidSel = (int)$users[0]['id'];

// Permisos marcados por rol seleccionado
$permCheckedRole = [];
if ($rolSel) {
  $st = $pdo->prepare("SELECT permiso_id FROM rol_permisos WHERE rol_id=?");
  $st->execute([$rolSel]);
  $permCheckedRole = array_map('intval', array_column($st->fetchAll(), 'permiso_id'));
}

// Permisos del usuario seleccionado (directos + efectivos)
$userDirectPerms = $userRolePerms = $userEffective = [];
if ($uidSel) {
  // rol del usuario
  $st = $pdo->prepare("SELECT rol_id FROM usuarios WHERE id=?");
  $st->execute([$uidSel]);
  $rid = (int)$st->fetchColumn();

  if ($rid) {
    $st = $pdo->prepare("SELECT permiso_id FROM rol_permisos WHERE rol_id=?");
    $st->execute([$rid]);
    $userRolePerms = array_map('intval', array_column($st->fetchAll(), 'permiso_id'));
  }

  $st = $pdo->prepare("SELECT permiso_id FROM usuario_permisos WHERE usuario_id=?");
  $st->execute([$uidSel]);
  $userDirectPerms = array_map('intval', array_column($st->fetchAll(), 'permiso_id'));

  $userEffective = array_values(array_unique(array_merge($userRolePerms, $userDirectPerms)));
}

?>
<section class="content-header">
  <h1>Permisos <small>Catálogo, por rol y por usuario</small></h1>
  <ol class="breadcrumb">
    <li><a href="Dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
    <li class="active">Permisos</li>
  </ol>
</section>

<section class="content">
  <?php flash(); ?>

  <div class="row">
    <!-- Catálogo de permisos -->
    <div class="col-md-4">
      <div class="box box-primary box-solid" id="catalogo">
        <div class="box-header with-border">
          <h3 class="box-title"><i class="fa fa-key"></i> Catálogo</h3>
          <div class="box-tools">
            <form method="post" action="Permisos" style="display:inline;">
              <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
              <input type="hidden" name="action" value="seed_basic">
              <button class="btn btn-xs btn-default" onclick="return confirm('¿Cargar permisos básicos?');">
                <i class="fa fa-bolt"></i> Cargar básicos
              </button>
            </form>
          </div>
        </div>
        <div class="box-body">
          <form method="post" action="Permisos" autocomplete="off">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
            <input type="hidden" name="action" value="create_perm">
            <div class="form-group">
              <label>Nombre (modulo.accion) *</label>
              <input type="text" name="nombre" class="form-control" placeholder="ej: ventas.ver" required>
            </div>
            <div class="form-group">
              <label>Descripción</label>
              <input type="text" name="descripcion" class="form-control" placeholder="Descripción del permiso">
            </div>
            <button class="btn btn-success"><i class="fa fa-plus"></i> Crear permiso</button>
          </form>

          <hr>
          <?php if (empty($permisos)): ?>
            <p class="text-muted">Sin permisos. Usa “Cargar básicos” o crea manualmente.</p>
          <?php else: ?>
            <ul class="list-unstyled">
              <?php foreach ($permisos as $p): ?>
              <li style="margin-bottom:8px;">
                <form method="post" action="Permisos" class="form-inline" style="display:inline;">
                  <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                  <input type="hidden" name="action" value="update_perm">
                  <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                  <input type="text" name="nombre" class="form-control input-sm" value="<?php echo htmlspecialchars($p['nombre']); ?>" style="min-width:180px;">
                  <input type="text" name="descripcion" class="form-control input-sm" value="<?php echo htmlspecialchars($p['descripcion'] ?? ''); ?>" style="min-width:220px;">
                  <button class="btn btn-xs btn-primary"><i class="fa fa-save"></i></button>
                </form>
                <form method="post" action="Permisos" style="display:inline;" onsubmit="return confirm('¿Eliminar permiso? Se quitará de roles y usuarios.');">
                  <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                  <input type="hidden" name="action" value="delete_perm">
                  <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                  <button class="btn btn-xs btn-danger"><i class="fa fa-trash"></i></button>
                </form>
              </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Permisos por ROL -->
    <div class="col-md-4">
      <div class="box box-warning" id="roles">
        <div class="box-header with-border">
          <h3 class="box-title"><i class="fa fa-id-badge"></i> Permisos por rol</h3>
          <div class="box-tools">
            <form method="get" action="Permisos" class="form-inline">
              <input type="hidden" name="Pages" value="Permisos">
              <label>Rol:</label>
              <select class="form-control input-sm" name="rol" onchange="this.form.submit()">
                <?php foreach ($roles as $r): ?>
                <option value="<?php echo (int)$r['id']; ?>" <?php echo $rolSel===(int)$r['id']?'selected':''; ?>>
                  <?php echo htmlspecialchars($r['nombre']); ?>
                </option>
                <?php endforeach; ?>
              </select>
            </form>
          </div>
        </div>
        <form method="post" action="Permisos">
          <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
          <input type="hidden" name="action" value="save_role_perms">
          <input type="hidden" name="rol_id" value="<?php echo (int)$rolSel; ?>">

          <div class="box-body" style="max-height:430px; overflow:auto;">
            <?php if (empty($permGroups)): ?>
              <p class="text-muted">No hay permisos. Carga básicos desde el panel de catálogo.</p>
            <?php else: ?>
              <?php foreach ($permGroups as $group => $items): $gh = 'g_rol_'.md5($group); ?>
              <div class="panel panel-default">
                <div class="panel-heading">
                  <strong><?php echo strtoupper(htmlspecialchars($group)); ?></strong>
                  <div class="pull-right">
                    <a href="#" onclick="checkGroup('<?php echo $gh; ?>', true); return false;"><small>Marcar</small></a> |
                    <a href="#" onclick="checkGroup('<?php echo $gh; ?>', false); return false;"><small>Desmarcar</small></a>
                  </div>
                </div>
                <div class="panel-body">
                  <?php foreach ($items as $p): ?>
                  <div class="checkbox">
                    <label class="<?php echo $gh; ?>">
                      <input type="checkbox" name="perms[]" value="<?php echo (int)$p['id']; ?>"
                        <?php echo in_array((int)$p['id'],$permCheckedRole,true)?'checked':''; ?>>
                      <code><?php echo htmlspecialchars($p['nombre']); ?></code>
                      <small class="text-muted"><?php echo htmlspecialchars($p['descripcion'] ?? ''); ?></small>
                    </label>
                  </div>
                  <?php endforeach; ?>
                </div>
              </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
          <div class="box-footer">
            <button class="btn btn-warning"><i class="fa fa-save"></i> Guardar permisos del rol</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Permisos por USUARIO -->
    <div class="col-md-4">
      <div class="box box-success" id="usuarios">
        <div class="box-header with-border">
          <h3 class="box-title"><i class="fa fa-user-secret"></i> Permisos por usuario</h3>
          <div class="box-tools">
            <form method="get" action="Permisos" class="form-inline">
              <input type="hidden" name="Pages" value="Permisos">
              <label>Usuario:</label>
              <select class="form-control input-sm" name="uid" onchange="this.form.submit()">
                <?php foreach ($users as $u): ?>
                <option value="<?php echo (int)$u['id']; ?>" <?php echo $uidSel===(int)$u['id']?'selected':''; ?>>
                  <?php echo htmlspecialchars(($u['nom'] ?? 'Usuario').' ('.$u['correo'].')'); ?>
                </option>
                <?php endforeach; ?>
              </select>
            </form>
          </div>
        </div>

        <form method="post" action="Permisos">
          <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
          <input type="hidden" name="action" value="save_user_perms">
          <input type="hidden" name="usuario_id" value="<?php echo (int)$uidSel; ?>">

          <div class="box-body" style="max-height:430px; overflow:auto;">
            <?php if (empty($permGroups)): ?>
              <p class="text-muted">No hay permisos. Carga básicos desde el panel de catálogo.</p>
            <?php else: ?>
              <p class="text-muted" style="margin-top:-4px;">
                Los permisos marcados se guardan como sobrescritura del usuario y se combinan con los de su rol.
              </p>
              <?php foreach ($permGroups as $group => $items): $gh = 'g_user_'.md5($group); ?>
              <div class="panel panel-default">
                <div class="panel-heading">
                  <strong><?php echo strtoupper(htmlspecialchars($group)); ?></strong>
                  <div class="pull-right">
                    <a href="#" onclick="checkGroup('<?php echo $gh; ?>', true); return false;"><small>Marcar</small></a> |
                    <a href="#" onclick="checkGroup('<?php echo $gh; ?>', false); return false;"><small>Desmarcar</small></a>
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
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
          <div class="box-footer">
            <button class="btn btn-success"><i class="fa fa-save"></i> Guardar permisos del usuario</button>
          </div>
        </form>
      </div>
    </div>

  </div>
</section>

<script>
function checkGroup(groupHash, checked){
  var inputs = document.querySelectorAll('label.'+groupHash+' input[type=checkbox]');
  for (var i=0; i<inputs.length; i++) { inputs[i].checked = !!checked; }
}
</script>
