<?php
// src/php/controllers/SettingsController.php
session_start();

// Ajusta esta ruta si es necesario según tu estructura de carpetas
require_once '../../../src/php/requires_central.php'; 
require_once '../models/SettingsModel.php';

class SettingsController
{
    private $settingsModel;

    public function __construct(SettingsModel $model)
    {
        $this->settingsModel = $model;
    }

    private function handleUpdateColors()
    {
        // 1. Seguridad: Verificar Rol de Administrador
        if (($_SESSION['user_rol'] ?? '') !== 'administrador') {
            die("Acceso denegado.");
        }
        
        // 2. Seguridad: Verificar Token CSRF
        $token = $_POST['csrf_token'] ?? '';
        if (!SecurityHelper::verifyCsrfToken($token)) {
            $_SESSION['update_error'] = "Error de seguridad (Token CSRF inválido). Recarga e intenta de nuevo.";
            header("Location: ../../views/userdata.php");
            exit;
        }

        // 3. Recibir datos (Blindaje contra valores vacíos)
        // Si el navegador no envía el color, usamos valores por defecto seguros.
        $primary = !empty($_POST['primary_color']) ? $_POST['primary_color'] : '#007bff';
        $secondary = !empty($_POST['secondary_color']) ? $_POST['secondary_color'] : '#2c3e50';
        $text = !empty($_POST['text_color']) ? $_POST['text_color'] : '#333333';
        $bg = !empty($_POST['bg_color']) ? $_POST['bg_color'] : '#f8f9fa';
        
        // Blindaje específico para el color de tarjetas (El problema que tenías)
        $card = !empty($_POST['card_color']) ? $_POST['card_color'] : '#ffffff'; 

        // 4. Llamar al Modelo para Actualizar
        // IMPORTANTE: SettingsModel debe tener la lógica para crear la fila id=1 si no existe.
        $result = $this->settingsModel->updateThemeColors($primary, $secondary, $text, $bg, $card);

        if ($result === true) {
            $_SESSION['admin_msg'] = "¡Tema actualizado correctamente!";
        } else {
            $_SESSION['update_error'] = $result;
        }
        
        // 5. Redirigir a la pestaña de personalización
        header("Location: ../../views/userdata.php?tab=theme");
        exit;
    }

    // Enrutador de acciones
    public function routeAction($action)
    {
        if ($action === 'update_theme') {
            $this->handleUpdateColors();
        } else {
            // Si la acción no existe, redirigir al dashboard
            header("Location: ../../views/dashboard.php");
            exit;
        }
    }
}

// Bootstrap del Controlador
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Asegurarse de que la conexión a DB se creó en requires_central o crearla aquí
    $db = new Database();
    $connection = $db->getConnection();
    
    $model = new SettingsModel($connection);
    $controller = new SettingsController($model);
    
    $controller->routeAction($_POST['action'] ?? '');
}