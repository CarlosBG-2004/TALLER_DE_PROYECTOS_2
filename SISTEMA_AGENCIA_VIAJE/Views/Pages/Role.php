<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once "Config/Database.php";

/* ---- Solo Admin o Gerencia ---- */
$rolSesion = $_SESSION['user']['rol'] ?? '';
if (!in_array($rolSesion, ['Admin','Gerencia'], true)) {
?>
<section class="content-header">
  <h1>Asignar áreas <small>Acceso restringido</small></h1>
</section>
<section class="content">
  <div class="callout callout-danger">
    <h4>No autorizado</h4>
    <p>Solo <b>Gerencia</b> y <b>Administradores</b> pueden administrar accesos.</p>
  </div>
</section>
<?php
  return;
}

/* ---- Helpers ---- */
function flash($k='flash'){ if(!empty($_SESSION[$k])){ echo $_SESSION[$k]; unset($_SESSION[$k]); } }
function set_flash($type,$msg,$k='flash'){ $_SESSION[$k] = '<div class="alert alert-'.$type.'">'.$msg.'</div>'; }
function csrf(){ if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(16)); return $_SESSION['csrf']; }
function csrf_ok($t){ return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t ?? ''); }

$pdo = Database::getConnection();

/* ---- Pivot (usuario_areas) si no existe ---- */
$pdo->exec("
  CREATE TABLE IF NOT EXISTS usuario_areas (
    usuario_id INT NOT NULL,
    area_id    INT NOT NULL,
    PRIMARY KEY (usuario_id, area_id),
    CONSTRAINT fk_ua_user FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_ua_area FOREIGN KEY (area_id) REFERENCES areas(id) ON DELETE CASCADE
  ) ENGINE=InnoDB
");

/* ---- POST: guardar asignaciones ---- */
try {
  if (($_POST['action'] ?? '') === 'save_all') {
    if (!csrf_ok($_POST['csrf'] ?? '')) throw new Exception('CSRF inválido.');

    $allUsers = array_map('intval', $_POST['all_users'] ?? []);
    $areasSel = $_POST['areas'] ?? []; // areas[usuario_id][] = area_id

    $pdo->beginTransaction();

    if (!empty($allUsers)) {
      $del = $pdo->prepare("DELETE FROM usuario_areas WHERE usuario_id=?");
      $ins = $pdo->prepare("INSERT INTO usuario_areas (usuario_id, area_id) VALUES (?,?)");

      foreach ($allUsers as $uid) {
        $del->execute([$uid]);
        if (!empty($areasSel[$uid]) && is_array($areasSel[$uid])) {
          foreach ($areasSel[$uid] as $aid) {
            $aid = (int)$aid;
            if ($aid > 0) { $ins->execute([$uid, $aid]); }
          }
        }
      }
    }

    $pdo->commit();
    set_flash('success', 'Áreas actualizadas correctamente.');
    header('Location: Role'); exit;
  }
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  set_flash('danger', 'Error al guardar: '.$e->getMessage());
  header('Location: Role'); exit;
}

/* ---- Datos para la vista ---- */
$csrf = csrf();
$areas = $pdo->query("SELECT id, nombre FROM areas ORDER BY nombre")->fetchAll();

$usuarios = $pdo->query("
  SELECT u.id, u.nombre, u.apellido, u.correo, u.activo
  FROM usuarios u
  ORDER BY u.id DESC
")->fetchAll();

/* Mapa de áreas actuales por usuario */
$rows = $pdo->query("SELECT usuario_id, area_id FROM usuario_areas")->fetchAll();
$uaMap = [];
foreach ($rows as $r) { $uaMap[(int)$r['usuario_id']][] = (int)$r['area_id']; }
?>

<section class="content-header">
  <h1>Asignar áreas a usuarios <small>define qué módulos verá cada usuario</small></h1>
  <ol class="breadcrumb">
    <li><a href="Dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
    <li class="active">Asignar áreas</li>
  </ol>
</section>

<section class="content">
  <?php flash(); ?>

  <div class="box box-primary">
    <div class="box-header with-border">
      <h3 class="box-title"><i class="fa fa-users"></i> Usuarios</h3>
      <div class="box-tools">
        <small class="text-muted">Marca varias áreas por usuario y guarda.</small>
      </div>
    </div>

    <form method="post" action="Role" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="action" value="save_all">

      <div class="box-body table-responsive no-padding">
        <table class="table table-hover">
          <thead>
            <tr>
              <th style="width:60px">ID</th>
              <th>Colaborador</th>
              <th>Correo</th>
              <th style="width:120px">Estado</th>
              <th>Áreas habilitadas</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($usuarios)): ?>
              <tr><td colspan="5" class="text-center text-muted">No hay usuarios</td></tr>
            <?php else: foreach ($usuarios as $u): 
              $uid = (int)$u['id'];
              $checked = $uaMap[$uid] ?? [];
            ?>
              <tr>
                <td>
                  <?= $uid ?>
                  <input type="hidden" name="all_users[]" value="<?= $uid ?>">
                </td>
                <td><?= htmlspecialchars($u['nombre'].' '.$u['apellido']) ?></td>
                <td><?= htmlspecialchars($u['correo']) ?></td>
                <td>
                  <?php if ((int)$u['activo']===1): ?>
                    <span class="label label-success">Activo</span>
                  <?php else: ?>
                    <span class="label label-default">Inactivo</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php foreach ($areas as $a): 
                    $aid = (int)$a['id'];
                    $isOn = in_array($aid, $checked, true);
                  ?>
                    <label class="checkbox-inline" style="margin-right:12px;">
                      <input type="checkbox" name="areas[<?= $uid ?>][]" value="<?= $aid ?>" <?= $isOn?'checked':'' ?>>
                      <?= htmlspecialchars(ucfirst($a['nombre'])) ?>
                    </label>
                  <?php endforeach; ?>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

      <div class="box-footer">
        <button class="btn btn-primary"><i class="fa fa-save"></i> Guardar cambios</button>
      </div>
    </form>
  </div>
</section>
