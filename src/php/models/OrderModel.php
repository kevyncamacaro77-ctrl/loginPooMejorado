<?php
require_once 'DbModel.php';
require_once 'ProductModel.php';
require_once 'CartModel.php';

class OrderModel extends DbModel
{
    public function __construct(mysqli $connection)
    {
        parent::__construct($connection);
    }

    /**
     * Convierte el carrito activo en un pedido pendiente.
     */
    public function createOrderFromCart(int $userId): bool|string
    {
        $cartModel = new CartModel($this->conn);
        $cartItems = $cartModel->viewCart($userId);

        if (is_string($cartItems))
            return $cartItems;
        if (empty($cartItems))
            return "El carrito está vacío.";

        // 1. Calcular Total
        $total = 0;
        foreach ($cartItems as $item) {
            $total += $item['subtotal'];
        }

        // 2. Crear el Pedido (Cabecera)
        $sql_order = "INSERT INTO pedidos (user_id, total, estado) VALUES (?, ?, 'pendiente')";
        $orderId = $this->runDmlStatement($sql_order, "id", $userId, $total); // id = int, decimal

        if (is_string($orderId))
            return "Error al crear orden: " . $orderId;
        $orderId = $this->conn->insert_id;

        // 3. Mover ítems de Detalles Carrito a Detalles Pedido
        foreach ($cartItems as $item) {
            $sql_detail = "INSERT INTO detalles_pedido (pedido_id, producto_id, cantidad, precio_unitario) VALUES (?, ?, ?, ?)";
            $this->runDmlStatement($sql_detail, "iiid", $orderId, $item['producto_id'], $item['cantidad'], $item['precio']);
        }

        // 4. Vaciar el carrito PERO SIN devolver el stock (porque ahora pertenece al pedido)
        // Eliminamos directamente las filas sin llamar a 'removeItem' para mantener el stock_comprometido.
        $cartId = $cartModel->getOrCreateCartId($userId);
        if (is_int($cartId)) {
            $this->runDmlStatement("DELETE FROM detalles_carrito WHERE carrito_id = ?", "i", $cartId);
            $this->runDmlStatement("DELETE FROM carritos_activos WHERE id = ?", "i", $cartId);
        }

        return true;
    }

    /**
     * ADMIN: Obtiene todos los pedidos pendientes.
     */
    public function getPendingOrders(): array|string
    {
        // AGREGAMOS p.estado A LA LISTA DE SELECCIÓN
        $sql = "SELECT p.id, p.fecha_pedido, p.total, p.estado, u.nombre as usuario, u.email 
                FROM pedidos p 
                JOIN usuarios u ON p.user_id = u.id 
                WHERE p.estado = 'pendiente' 
                ORDER BY p.fecha_pedido ASC";

        $result = $this->runSelectStatement($sql, "");

        if (is_string($result))
            return $result;

        $orders = [];
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
        return $orders;
    }

    /**
     * ADMIN: Confirma un pedido, reduce stock real y libera comprometido.
     */
    public function confirmOrder(int $orderId): bool|string
    {
        $productModel = new ProductModel($this->conn);

        // 1. Obtener items del pedido
        $sql_items = "SELECT producto_id, cantidad FROM detalles_pedido WHERE pedido_id = ?";
        $result = $this->runSelectStatement($sql_items, "i", $orderId);

        if (is_string($result))
            return $result;

        // 2. Procesar Stock Final
        while ($item = $result->fetch_assoc()) {
            // Llamamos al método que creamos antes en ProductModel
            // Reduce stock_actual Y reduce stock_comprometido
            $res = $productModel->confirmSaleDeductStock($item['producto_id'], $item['cantidad']);
            if (is_string($res))
                return "Error en producto ID {$item['producto_id']}: $res";
        }

        // 3. Marcar pedido como completado
        $sql_update = "UPDATE pedidos SET estado = 'completado' WHERE id = ?";
        return $this->runDmlStatement($sql_update, "i", $orderId);
    }

    public function getOrdersByUserId(int $userId): array|string
    {
        $sql = "SELECT * FROM pedidos WHERE user_id = ? ORDER BY fecha_pedido DESC";
        $result = $this->runSelectStatement($sql, "i", $userId);

        if (is_string($result))
            return $result;

        $orders = [];
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
        return $orders;
    }

    /**
     * Cancela un pedido y LIBERA el stock comprometido para que otros puedan comprarlo.
     */
    public function cancelOrder(int $orderId): bool|string
    {
        // 1. Verificar estado actual (solo cancelar si está pendiente)
        $checkSql = "SELECT estado FROM pedidos WHERE id = ?";
        $checkRes = $this->runSelectStatement($checkSql, "i", $orderId);
        if (is_string($checkRes) || $checkRes->num_rows === 0)
            return "Pedido no encontrado.";

        $estado = $checkRes->fetch_assoc()['estado'];
        if ($estado !== 'pendiente') {
            return "Solo se pueden cancelar pedidos pendientes.";
        }

        $productModel = new ProductModel($this->conn);

        // 2. Obtener productos del pedido para devolverlos al inventario disponible
        $sql_items = "SELECT producto_id, cantidad FROM detalles_pedido WHERE pedido_id = ?";
        $result = $this->runSelectStatement($sql_items, "i", $orderId);

        if (is_string($result))
            return $result;

        // 3. Liberar el Stock Comprometido (Restarlo del comprometido)
        while ($item = $result->fetch_assoc()) {
            // Usamos cantidad negativa para LIBERAR
            // manipulateCompromisedStock(-cantidad) reduce la reserva
            $res = $productModel->manipulateCompromisedStock($item['producto_id'], -$item['cantidad']);
            if (is_string($res))
                return "Error al liberar stock: $res";
        }

        // 4. Cambiar estado a Cancelado
        $sql_update = "UPDATE pedidos SET estado = 'cancelado' WHERE id = ?";
        return $this->runDmlStatement($sql_update, "i", $orderId);
    }
}