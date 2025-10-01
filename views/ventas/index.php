<?php
session_start();
require_once('../config/db_config.php');

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Verificar si el usuario tiene el rol adecuado para acceder a ventas
if ($_SESSION['role_id'] != 2) {  // El rol de ventas es 2
    header('Location: no_access.php');
    exit;
}

// Aquí va el contenido de la página de ventas
?>
