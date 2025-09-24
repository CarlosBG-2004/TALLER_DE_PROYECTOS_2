<?php
$host = "193.203.175.1";          // Dirección IP de tu servidor
$db_name = "u882445550_raptravel"; // Nombre de tu base de datos
$db_user = "u882445550_raptravel"; // Usuario de la base de datos
$db_pass = "Raptravel@2025";       // Contraseña de la base de datos

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // echo "Conexión exitosa"; // Para probar
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
?>
