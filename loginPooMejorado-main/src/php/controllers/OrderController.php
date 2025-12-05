<?php
// src/php/controllers/OrderController.php
session_start();
require_once '../../../src/php/requires_central.php';
require_once '../models/OrderModel.php';
// Asegúrate de que estos modelos sean accesibles o ya estén cargados por requires_central.php
require_once '../models/CartModel.php'; 
require_once '../models/ProductModel.php'; 

class OrderController
{
    private $orderModel;
    private $cartModel; // NUEVA PROPIEDAD
    private $productModel; // NUEVA PROPIEDAD

    // Constructor actualizado para inyección de dependencias
    public function __construct(OrderModel $orderModel, CartModel $cartModel, ProductModel $productModel)
    {
        $this->orderModel = $orderModel;
        $this->cartModel = $cartModel;
        $this->productModel = $productModel;
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

        // Validación CSRF
        $token = $_POST['csrf_token'] ?? '';
        if (!SecurityHelper::verifyCsrfToken($token)) {
             die("Error de seguridad: Token CSRF inválido.");
        }

        $result = $this->orderModel->createOrderFromCart($_SESSION['user_id']);

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
    // ACCIÓN USUARIO: Actualizar Cantidad del Carrito (NUEVA FUNCIÓN)
    // ----------------------------------------------------
    private function handleUpdateCartQuantity()
    {
        if (!isset($_SESSION['user_id'])) {
            header("Location: ../../views/login.php");
            exit;
        }
        
        $userId = $_SESSION['user_id'];
        $productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
        $newQuantity = filter_input(INPUT_POST, 'new_quantity', FILTER_VALIDATE_INT);
        $token = $_POST['csrf_token'] ?? '';

        if (!SecurityHelper::verifyCsrfToken($token)) {
             die("Error de seguridad: Token CSRF inválido.");
        }
        
        if (!$productId || $newQuantity === false || $newQuantity < 0) {
            $_SESSION['cart_error'] = "Datos de actualización inválidos.";
        } else {
            // Llama a la función segura que definimos en CartModel
            $result = $this->cartModel->updateQuantity($userId, $productId, $newQuantity, $this->productModel); 

            if ($result === true) {
                $_SESSION['cart_success'] = "✅ Cantidad actualizada correctamente.";
            } else {
                $_SESSION['cart_error'] = "❌ Error al actualizar: " . $result;
            }
        }

        // Patrón PRG: Redirige al carrito
        header("Location: ../../views/userdata.php?tab=cart");
        exit;
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

        if ($result === true || (is_numeric($result) && $result > 0)) {
            $_SESSION['admin_msg'] = "Pedido #$orderId cancelado y stock liberado.";
        } else {
            $_SESSION['admin_error'] = is_string($result) ? $result : "No se pudo cancelar el pedido.";
        }
        
        header("Location: ../../views/userdata.php?tab=orders"); 
        exit;
    }

    // ----------------------------------------------------
    // ENRUTAMIENTO (Se añade la nueva acción)
    // ----------------------------------------------------
    public function routeAction($action)
    {
        switch ($action) {
            case 'checkout':
                $this->handleCheckout();
                break;
            case 'update_cart_quantity': // NUEVA ACCIÓN
                $this->handleUpdateCartQuantity();
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

// BOOTSTRAP (Se inician los modelos requeridos)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Asegúrate de que estos archivos estén incluidos
    require_once '../../../src/php/requires_central.php';
    require_once '../models/OrderModel.php'; 
    require_once '../models/CartModel.php'; 
    require_once '../models/ProductModel.php'; 

    $db = new Database();
    $conn = $db->getConnection();
    
    // Inicialización de Modelos
    $orderModel = new OrderModel($conn);
    $cartModel = new CartModel($conn); 
    $productModel = new ProductModel($conn);

    // Se pasan los tres modelos al controlador
    $controller = new OrderController($orderModel, $cartModel, $productModel);
    
    $controller->routeAction($_POST['action'] ?? '');
}