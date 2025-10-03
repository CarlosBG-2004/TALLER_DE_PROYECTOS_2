<?php
// Simular la entrada de usuario sin validación real
session_start();

// Datos simulados del usuario
$_SESSION['user_id'] = 1; // ID de usuario simulado
$_SESSION['role_id'] = 2; // Rol simulado (puedes ajustarlo según los roles en tu DB)
$_SESSION['email'] = 'juan.perez@raptravel.com'; // Correo simulado

// Redirigir al panel principal
header('Location: index.php');
exit;
?>
