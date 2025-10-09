<?php
class AuthController {

  public static function isAuthenticated(): bool {
    return !empty($_SESSION['user']);
  }

  public static function login(): void {
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

    if (!$user || !$user['activo'] || !password_verify($pass, $user['contrasena_hash'])) {
      $_SESSION['flash_error'] = 'Credenciales inválidas o usuario inactivo.';
      self::audit($user['id'] ?? null, 'login', 'usuarios', $user['id'] ?? 0, null, null);
      header("Location: Login");
      exit;
    }

    // OK - sesión
    $_SESSION['user'] = [
      'id'       => $user['id'],
      'nombre'   => $user['nombre'],
      'apellido' => $user['apellido'],
      'correo'   => $user['correo'],
      'rol'      => $user['rol'],
      'rol_id'   => $user['rol_id'],
      'area'     => $user['area'],
      'area_id'  => $user['area_id'],
    ];

    self::audit($user['id'], 'login', 'usuarios', $user['id'], null, null);
    header("Location: Dashboard");
    exit;
  }

  public static function logout(): void {
    self::audit($_SESSION['user']['id'] ?? null, 'logout', 'usuarios', $_SESSION['user']['id'] ?? 0, null, null);

    // Limpiar sesión
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
        $usuarioId ?: 0, $tabla, $registroId ?: 0, $accion, $oldValues, $newValues, $ip, $ua
      ]);
    } catch (Throwable $e) {
      // no interrumpir flujo por auditoría
    }
  }
}
