<?php
// Configuración de la base de datos
$host = "193.203.175.1";          // Dirección IP del servidor de base de datos
$db_name = "u882445550_raptravel"; // Nombre de la base de datos
$db_user = "u882445550_raptravel"; // Usuario de la base de datos
$db_pass = "Raptravel@2025";       // Contraseña de la base de datos

try {
    // Intentar establecer la conexión
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);  // Habilitar excepciones

    echo "¡Conexión exitosa a la base de datos!"; // Mensaje si la conexión es exitosa
} catch (PDOException $e) {
    // Si hay un error de conexión, mostrar el mensaje de error
    die("Error de conexión: " . $e->getMessage());
}
?>
