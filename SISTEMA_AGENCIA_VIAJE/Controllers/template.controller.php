<?php
class ControllerTemplate
{
    public function controllerTemplate()
    {
        // Iniciar sesión si no existe
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        // Controlador de autenticación
        require_once "Controllers/Auth.controller.php";

        // Parámetro de routing (?Pages=...)
        $page = isset($_GET['Pages']) ? $_GET['Pages'] : null;

        // Logout directo
        if ($page === 'Logout') {
            AuthController::logout();
            return;
        }

        // Login: GET muestra formulario, POST procesa credenciales
        if ($page === 'Login') {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                AuthController::login(); // redirige internamente
                return;
            }
            include "Views/Pages/Login.php";
            return;
        }

        // Si no está autenticado, enviar a Login
        if (!AuthController::isAuthenticated()) {
            header("Location: Login");
            exit;
        }

        // Autenticado: cargar layout principal
        include "Views/Template.php";
    }
}
