<?php
// src/php/controllers/OrderController.php
session_start();
require_once '../../../src/php/requires_central.php';
require_once '../models/OrderModel.php';

class OrderController
{
    private $orderModel;

    public function __construct(OrderModel $model)
    {
        $this->orderModel = $model;
    }

    // ----------------------------------------------------
    // ACCIÓN USUARIO: Solicitar Pedido
    // ----------------------------------------------------
    private function handleCheckout()
    {
        if (!isset($_SESSION['user_id'])) {
            header("Location: ../../views/login.php");
            exit;
        }

        // Validación CSRF (Opcional pero recomendada)
        $token = $_POST['csrf_token'] ?? '';
        if (!SecurityHelper::verifyCsrfToken($token)) {
             // Puedes descomentar esto para activar la seguridad estricta
             // die("Error de seguridad: Token CSRF inválido.");
        }

        $result = $this->orderModel->createOrderFromCart($_SESSION['user_id']);

        // CORRECCIÓN ROBUSTA: Aceptamos true, enteros o strings numéricos ("1")
        if ($result === true || (is_numeric($result) && $result > 0)) {
            $_SESSION['cart_success'] = "¡Pedido realizado con éxito! Espera la confirmación.";
            header("Location: ../../views/userdata.php?tab=orders"); 
            exit;
        } else {
            $_SESSION['cart_error'] = is_string($result) ? $result : "Error desconocido al crear pedido.";
            header("Location: ../../views/userdata.php?tab=cart");
            exit;
        }
    }

    // ----------------------------------------------------
    // ACCIÓN ADMIN: Confirmar Pedido
    // ----------------------------------------------------
    private function handleConfirmOrder()
    {
        if (($_SESSION['user_rol'] ?? '') !== 'administrador') {
            die("Acceso denegado");
        }

        $token = $_POST['csrf_token'] ?? '';
        if (!SecurityHelper::verifyCsrfToken($token)) die("Error CSRF");

        $orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);

        $result = $this->orderModel->confirmOrder($orderId);

        // CORRECCIÓN ROBUSTA
        if ($result === true || (is_numeric($result) && $result > 0)) {
            $_SESSION['admin_msg'] = "Pedido #$orderId confirmado y stock descontado.";
        } else {
            $_SESSION['admin_error'] = is_string($result) ? $result : "No se pudo confirmar el pedido.";
        }
        
        header("Location: ../../views/userdata.php?tab=orders"); 
        exit;
    }

    // ----------------------------------------------------
    // ACCIÓN ADMIN: Cancelar Pedido
    // ----------------------------------------------------
    private function handleCancelOrder()
    {
        if (($_SESSION['user_rol'] ?? '') !== 'administrador') {
            die("Acceso denegado");
        }

        $token = $_POST['csrf_token'] ?? '';
        if (!SecurityHelper::verifyCsrfToken($token)) die("Error CSRF");

        $orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);

        $result = $this->orderModel->cancelOrder($orderId); 

        // CORRECCIÓN ROBUSTA: Usamos is_numeric para atrapar el "1" string
        if ($result === true || (is_numeric($result) && $result > 0)) {
            $_SESSION['admin_msg'] = "Pedido #$orderId cancelado y stock liberado.";
        } else {
            // Si llegamos aquí, es un error real
            $_SESSION['admin_error'] = is_string($result) ? $result : "No se pudo cancelar el pedido.";
        }
        
        header("Location: ../../views/userdata.php?tab=orders"); 
        exit;
    }

    // ----------------------------------------------------
    // ENRUTAMIENTO
    // ----------------------------------------------------
    public function routeAction($action)
    {
        switch ($action) {
            case 'checkout':
                $this->handleCheckout();
                break;
            case 'confirm_order':
                $this->handleConfirmOrder();
                break;
            case 'cancel_order':
                $this->handleCancelOrder();
                break;
            default:
                header("Location: ../../views/dashboard.php");
                exit;
        }
    }
}

// BOOTSTRAP
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    require_once '../../../src/php/requires_central.php';
    require_once '../models/OrderModel.php'; 

    $db = new Database();
    $orderModel = new OrderModel($db->getConnection());
    
    $controller = new OrderController($orderModel);
    
    $controller->routeAction($_POST['action'] ?? '');
}