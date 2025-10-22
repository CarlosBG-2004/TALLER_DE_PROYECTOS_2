<?php
// Activa buffering para evitar warnings de headers si algún include imprime antes del redirect
ob_start();

// Inicia sesión lo más temprano posible
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

include "Controllers/template.Controller.php";

$template = new ControllerTemplate;
$template->controllerTemplate();

// Vacía el buffer al final
ob_end_flush();
