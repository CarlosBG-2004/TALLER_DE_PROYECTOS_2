<?php
class Database {
  public static function getConnection(): PDO {
    $host = '127.0.0.1';
    $db   = 'erp_turismo';  // <-- tu BD
    $user = 'root';         // <-- tu usuario
    $pass = '';             // <-- tu password
    $charset = 'utf8mb4';

    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $opts = [
      PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    return new PDO($dsn, $user, $pass, $opts);
  }
}
