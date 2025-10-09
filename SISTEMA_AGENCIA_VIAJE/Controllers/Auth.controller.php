<?php
class AuthController {

  public static function isAuthenticated(): bool {
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
    return !empty($_SESSION['user']);
  }

  public static function login(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
    require_once "Config/Database.php";

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      header("Location: Login");
      exit;
    }

    $email = trim($_POST['correo'] ?? '');
    $pass  = $_POST['contrasena'] ?? '';

    if ($email === '' || $pass === '') {
      $_SESSION['flash_error'] = 'Correo y contraseña son obligatorios.';
      header("Location: Login");
      exit;
    }

    try {
      $pdo = Database::getConnection();

      $sql = "SELECT u.id, u.nombre, u.apellido, u.correo, u.contrasena_hash, u.activo,
                     u.rol_id, u.area_id, r.nombre AS rol, a.nombre AS area
              FROM usuarios u
              LEFT JOIN roles r ON r.id = u.rol_id
              LEFT JOIN areas a ON a.id = u.area_id
              WHERE u.correo = ? LIMIT 1";
      $st = $pdo->prepare($sql);
      $st->execute([$email]);
      $user = $st->fetch();

      if (!$user || (int)$user['activo'] !== 1 || !password_verify($pass, $user['contrasena_hash'])) {
        $_SESSION['flash_error'] = 'Credenciales inválidas o usuario inactivo.';
        if ($user && !empty($user['id'])) {
          self::audit((int)$user['id'], 'login_fail', 'usuarios', (int)$user['id'], null, null);
        }
        header("Location: Login");
        exit;
      }

      // Rehash si aplica
      if (password_needs_rehash($user['contrasena_hash'], PASSWORD_BCRYPT)) {
        $newHash = password_hash($pass, PASSWORD_BCRYPT);
        $upd = $pdo->prepare("UPDATE usuarios SET contrasena_hash=?, actualizado_en=NOW() WHERE id=?");
        $upd->execute([$newHash, (int)$user['id']]);
      }

      // Regenerar ID de sesión
      session_regenerate_id(true);

      // Datos de usuario en sesión
      $_SESSION['user'] = [
        'id'       => (int)$user['id'],
        'nombre'   => $user['nombre'],
        'apellido' => $user['apellido'],
        'correo'   => $user['correo'],
        'rol'      => $user['rol'],
        'rol_id'   => $user['rol_id'],
        'area'     => $user['area'],
        'area_id'  => $user['area_id'],
      ];

      // ---- Permisos por ROL
      $permsRol = [];
      if (!empty($user['rol_id'])) {
        $stp = $pdo->prepare("
          SELECT p.nombre
          FROM rol_permisos rp
          JOIN permisos p ON p.id = rp.permiso_id
          WHERE rp.rol_id = ?
        ");
        $stp->execute([(int)$user['rol_id']]);
        $permsRol = array_column($stp->fetchAll(), 'nombre');
      }

      // ---- Permisos DIRECTOS de USUARIO (sobrescritura)
      $stU = $pdo->prepare("
        SELECT p.nombre
        FROM usuario_permisos up
        JOIN permisos p ON p.id = up.permiso_id
        WHERE up.usuario_id = ?
      ");
      $stU->execute([(int)$user['id']]);
      $userPerms = array_column($stU->fetchAll(), 'nombre');

      // Unión de permisos
      $_SESSION['perms'] = array_values(array_unique(array_merge($permsRol, $userPerms)));

      // Auditoría OK
      self::audit((int)$user['id'], 'login_ok', 'usuarios', (int)$user['id'], null, null);

      header("Location: Dashboard");
      exit;

    } catch (Throwable $e) {
      $_SESSION['flash_error'] = 'Error al iniciar sesión. Intenta nuevamente.';
      header("Location: Login");
      exit;
    }
  }

  public static function logout(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
    $uid = $_SESSION['user']['id'] ?? null;
    if (!empty($uid)) {
      self::audit((int)$uid, 'logout', 'usuarios', (int)$uid, null, null);
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
      $p = session_get_cookie_params();
      setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    header("Location: Login");
    exit;
  }

  private static function audit($usuarioId, $accion, $tabla, $registroId, $oldValues, $newValues): void {
    if (empty($usuarioId)) { return; }
    try {
      require_once "Config/Database.php";
      $pdo = Database::getConnection();
      $st = $pdo->prepare(
        "INSERT INTO auditoria (usuario_id, tabla, registro_id, accion, valores_antiguos, valores_nuevos, ip, user_agent)
         VALUES (?,?,?,?,?,?,?,?)"
      );
      $ip = $_SERVER['REMOTE_ADDR']     ?? null;
      $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
      $st->execute([
        (int)$usuarioId, (string)$tabla, (int)($registroId ?: 0), (string)$accion,
        $oldValues, $newValues, $ip, $ua
      ]);
    } catch (Throwable $e) {
      // No interrumpir flujo por auditoría
    }
  }
}
