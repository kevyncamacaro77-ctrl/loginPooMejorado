<?php
// src/php/controllers/ProductController.php
session_start();
require_once '../../../src/php/requires_central.php';

class ProductController
{
    private $productModel;
    private $cartModel;

    public function __construct(ProductModel $productModel, CartModel $cartModel)
    {
        $this->productModel = $productModel;
        $this->cartModel = $cartModel;
    }

    // ----------------------------------------------------
    // ACCIONES DE CLIENTE
    // ----------------------------------------------------

    /**
     * Maneja la adición de un producto al carrito, actualizando el stock comprometido.
     */
    private function handleAddToCart()
    {
        // 1. Guardia de seguridad: Debe estar logueado y no ser administrador
        if (!isset($_SESSION['user_id']) || ($_SESSION['user_rol'] ?? 'cliente') === 'administrador') {
            $_SESSION['error_login'] = "Acceso denegado. Debes iniciar sesión como cliente.";
            header("Location: ../../views/login.php");
            exit;
        }

        $userId = $_SESSION['user_id'];
        $productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
        $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);

        // 2. Validación Básica de Entrada
        if (!$productId || $productId <= 0 || !$quantity || $quantity <= 0) {
            $_SESSION['cart_error'] = "Cantidad o producto inválido.";
            header("Location: ../../views/dashboard.php#productos");
            exit;
        }

        // 3. Validación avanzada de Stock (Controlador)
        // El stock disponible es stock_actual - stock_comprometido
        $product_data = $this->productModel->getProductById($productId);

        if (is_string($product_data)) {
            $_SESSION['cart_error'] = "Error de sistema al verificar producto.";
            header("Location: ../../views/dashboard.php#productos");
            exit;
        }

        // Calcular el stock disponible
        $stock_disponible = $product_data['stock_actual'] - $product_data['stock_comprometido'];

        if (!$product_data || $stock_disponible < $quantity) {
            $_SESSION['cart_error'] = "Stock insuficiente para la reserva solicitada.";
            header("Location: ../../views/dashboard.php#productos");
            exit;
        }

        // 4. Llamar a la lógica de reserva del Modelo
        // El método addItem ahora incrementa stock_comprometido en lugar de deducir stock_actual.
        $result = $this->cartModel->addItem($userId, $productId, $quantity, $this->productModel);

        if ($result === true) {
            $_SESSION['cart_success'] = "¡Producto añadido a la reserva! Stock apartado.";
            header("Location: ../../views/dashboard.php");
            exit;
        }

        // 5. Fracaso (Error de Stock o DB devuelto por el Modelo)
        $_SESSION['cart_error'] = $result;
        header("Location: ../../views/dashboard.php");
        exit;
    }

    private function handleRemoveItem()
    {
        // 1. Guardia de Seguridad
        if (!isset($_SESSION['user_id'])) {
            header("Location: ../../views/login.php");
            exit;
        }

        // 2. Protección CSRF (Recomendado)
        // if (!SecurityHelper::verifyCsrfToken($_POST['csrf_token'] ?? '')) { ... }

        $userId = $_SESSION['user_id'];
        $productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);

        if (!$productId) {
            $_SESSION['cart_error'] = "Producto inválido para eliminar.";
            // Redirigir de vuelta a la pestaña del carrito
            header("Location: ../../views/userdata.php?tab=cart");
            exit;
        }

        // 3. Llamar al Modelo
        $result = $this->cartModel->removeItem($userId, $productId, $this->productModel);

        if ($result === true) {
            $_SESSION['cart_success'] = "Producto eliminado de la reserva.";
        } else {
            $_SESSION['cart_error'] = $result;
        }

        // 4. Redirigir siempre a la pestaña del carrito
        header("Location: ../../views/userdata.php?tab=cart");
        exit;
    }

    private function handleUpdateQuantity()
    {
        if (!isset($_SESSION['user_id'])) {
            header("Location: ../../views/login.php");
            exit;
        }

        // Validar CSRF
        $token = $_POST['csrf_token'] ?? '';
        if (!SecurityHelper::verifyCsrfToken($token))
            die("Error CSRF en UpdateQuantity");

        $userId = $_SESSION['user_id'];
        $productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
        $newQuantity = filter_input(INPUT_POST, 'new_quantity', FILTER_VALIDATE_INT);

        // Validación estricta
        if (!$productId || $newQuantity === false || $newQuantity < 1) {
            $_SESSION['cart_error'] = "Cantidad inválida (debe ser mayor a 0).";
            header("Location: ../../views/userdata.php?tab=cart");
            exit;
        }

        // Llamada al modelo
        $result = $this->cartModel->updateQuantity($userId, $productId, $newQuantity, $this->productModel);

        if ($result === true) {
            $_SESSION['cart_success'] = "Cantidad actualizada correctamente.";
        } else {
            $_SESSION['cart_error'] = $result; // Muestra el error del modelo (ej. stock insuficiente)
        }
        header("Location: ../../views/userdata.php?tab=cart");
        exit;
    }


    // ----------------------------------------------------
    // ACCIONES DE ADMINISTRADOR
    // ----------------------------------------------------
    private function checkAdminAccess()
    {
        if (($_SESSION['user_rol'] ?? 'cliente') !== 'administrador') {
            $_SESSION['update_error'] = "Acceso denegado: Se requiere rol de administrador.";
            header("Location: ../../views/dashboard.php");
            exit;
        }
    }

    private function handleUpdateStock()
    {
        $this->checkAdminAccess();

        $productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
        $newStock = filter_input(INPUT_POST, 'new_stock', FILTER_VALIDATE_INT);

        if (!$productId || $newStock === false || $newStock < 0) {
            $_SESSION['update_error'] = "Datos de stock inválidos.";
            header("Location: ../../views/dashboard.php");
            exit;
        }

        // NUEVA REGLA: El administrador actualiza el stock_actual (físico).
        $result = $this->productModel->updateStockDirect($productId, $newStock);

        if ($result === true) {
            $_SESSION['cart_success'] = "Stock actualizado con éxito.";
        } else {
            $_SESSION['update_error'] = $result;
        }
        header("Location: ../../views/dashboard.php");
        exit;
    }

    // En src/php/controllers/ProductController.php

    private function handleDeleteProduct()
    {
        $this->checkAdminAccess();

        // Verificación CSRF
        $token = $_POST['csrf_token'] ?? '';
        if (!SecurityHelper::verifyCsrfToken($token)) {
            die("Error de seguridad: Token CSRF inválido.");
        }

        $productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);

        if (!$productId) {
            $_SESSION['update_error'] = "ID de producto inválido.";
            header("Location: ../../views/dashboard.php#productos");
            exit;
        }

        $result = $this->productModel->deleteProduct($productId);

        // CORRECCIÓN AQUÍ: Aceptamos true O un número mayor a 0
        if ($result === true || (is_int($result) && $result > 0)) {
            $_SESSION['cart_success'] = "Producto eliminado correctamente.";
        } else {
            // Si es string, es el error. Si es 0, es que no se pudo borrar.
            $_SESSION['update_error'] = is_string($result) ? $result : "No se pudo eliminar el producto (quizás tiene reservas activas).";
        }

        header("Location: ../../views/dashboard.php#productos");
        exit;
    }
    // ----------------------------------------------------
    // ENRUTAMIENTO FINAL
    // ----------------------------------------------------
    public function routeAction($action)
    {
        // Limpiar carritos expirados antes de cualquier acción
        $this->cartModel->clearExpiredCarts(20);
        switch ($action) {
            case "add_to_cart":
                $this->handleAddToCart();
                break;
            case "remove_item":
                $this->handleRemoveItem();
                break;
            case "update_quantity":
                $this->handleUpdateQuantity();
                break;
            // Acciones de Admin
            case "update_stock":
                $this->handleUpdateStock();
                break;
            case "delete_product":
                $this->handleDeleteProduct();
                break;
            default:
                $_SESSION['cart_error'] = "Acción de producto no reconocida.";
                header("Location: ../../views/dashboard.php");
                exit;
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // 1. Conexión a la base de datos
    require_once '../../../src/php/requires_central.php';

    $db = new Database();
    $connection = $db->getConnection();

    // 2. Instanciación de Modelos e Inyección de Dependencia
    $productModel = new ProductModel($connection);
    $cartModel = new CartModel($connection);

    // 3. Pasamos los Modelos al Controlador
    $controller = new ProductController($productModel, $cartModel);

    $action = $_POST["action"] ?? '';
    $controller->routeAction($action);
}