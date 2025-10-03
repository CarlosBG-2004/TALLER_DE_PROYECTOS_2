<?php
$host = "localhost";          // Servidor local
$db_name = "proyectos2";      // Nombre de la BD que creaste en phpMyAdmin
$db_user = "root";            // Usuario por defecto en XAMPP/Laragon
$db_pass = "";                // Contraseña (vacía si no le has puesto una)

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Conexión exitosa a la BD en localhost";
} catch (PDOException $e) {
    die("❌ Error de conexión: " . $e->getMessage());
}
?>
