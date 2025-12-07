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

private function getCartId(int $userId): ?int
{
    // Consulta la tabla que rastrea qué carrito está activo para el usuario
    $sql = "SELECT id FROM carritos_activos WHERE user_id = ?";
    
    // Asumiendo que runSelectStatement devuelve un resultado mysqli_result o un string de error
    $result = $this->runSelectStatement($sql, "i", $userId);

    if (is_string($result) || $result === false) {
        // Error de base de datos
        error_log("DB Error in getCartId: " . $result);
        return null; 
    }

    if ($result->num_rows === 1) {
        // Carrito encontrado
        $row = $result->fetch_assoc();
        return (int) $row['id'];
    }

    // Carrito no encontrado o el usuario no tiene carrito activo
    return null;
}

    /**
     * Convierte el carrito activo en un pedido pendiente.
     */
    public function createOrderFromCart(int $userId): bool|string
{
   // 1. OBTENER ID DEL CARRITO
    // Asegúrate de que esta función exista y devuelva el ID del carrito activo.
    $cartId = $this->getCartId($userId); 
    
    // 🚨 LINEA DE DEPURACIÓN TEMPORAL:
    echo "";
    
    if (!$cartId) {
    return "Error: Carrito no encontrado o vacío.";
    }

    // BLOQUE DE VALIDACIÓN: LÍMITE DE 5 UNIDADES
    // Detiene el proceso si el carrito contiene más de 5 artículos de un mismo producto (Fix de negocio)
    $sql_check_limit = "
        SELECT d.cantidad, p.nombre 
        FROM detalles_carrito d
        JOIN productos p ON d.producto_id = p.id
        WHERE d.carrito_id = ? AND d.cantidad > 5
    ";
    $limit_result = $this->runSelectStatement($sql_check_limit, "i", $cartId);

    if (is_string($limit_result)) {
        // En caso de error de BD, devolvemos un mensaje genérico.
        return "Error de base de datos al verificar límites.";
    }

    if ($limit_result->num_rows > 0) {
        // Si hay algún producto que excede el límite
        $products_over_limit = $limit_result->fetch_assoc();
        $product_name = htmlspecialchars($products_over_limit['nombre']);
        $quantity = $products_over_limit['cantidad'];

        // Devolver un error específico y claro al usuario.
        return "❌ Error: No puedes solicitar este pedido. El producto '{$product_name}' excede el límite de 5 unidades por artículo. (Actualmente tienes {$quantity} unidades). Por favor, reduce la cantidad en tu carrito.";
    }
    // FIN DEL BLOQUE DE VALIDACIÓN DE LÍMITE


    // 2. INICIO DE LA TRANSACCIÓN (Es crucial para la consistencia del stock)
    $this->conn->begin_transaction();

    try {
        // 3. OBTENER DETALLES DEL CARRITO CON BLOQUEO (FOR UPDATE)
        // Aseguramos que nadie más pueda modificar el stock/carrito mientras creamos la orden.
        $sql_cart = "
            SELECT 
                d.producto_id, 
                d.cantidad, 
                p.precio,
                (p.stock_actual - p.stock_comprometido) AS stock_disponible,
                p.stock_actual,
                p.stock_comprometido
            FROM detalles_carrito d
            JOIN productos p ON d.producto_id = p.id
            WHERE d.carrito_id = ?
            FOR UPDATE
        ";
        $cart_details_result = $this->runSelectStatement($sql_cart, "i", $cartId);
        
        if (is_string($cart_details_result)) {
            $this->conn->rollback();
            return "Error al leer carrito: {$cart_details_result}";
        }
        
        if ($cart_details_result->num_rows === 0) {
            $this->conn->rollback();
            return "El carrito está vacío o ya ha sido procesado.";
        }

        $cart_details = $cart_details_result->fetch_all(MYSQLI_ASSOC);
        $total_amount = 0;
        $products_to_revert = []; // Para revertir stock si falla el pedido final

        // 4. VERIFICAR STOCK Y CALCULAR TOTAL
        foreach ($cart_details as $item) {
            if ($item['stock_disponible'] < $item['cantidad']) {
                $this->conn->rollback();
                return "Stock insuficiente para el producto ID {$item['producto_id']}. Solo quedan {$item['stock_disponible']} unidades disponibles.";
            }
            $total_amount += $item['cantidad'] * $item['precio'];
            
            // Guardamos el compromiso para revertir si la inserción de pedidos falla
            $products_to_revert[$item['producto_id']] = $item['cantidad']; 
        }

        // 5. INSERTAR PEDIDO PRINCIPAL
        $sql_order = "INSERT INTO pedidos (user_id, total, estado, fecha_pedido) VALUES (?, ?, 'Pendiente', NOW())";
        $orderId = $this->runDmlStatement($sql_order, "id", $userId, $total_amount);

        echo "";

        // 🚨 CORRECCIÓN: Aseguramos que $orderId sea un número positivo (el Last Insert ID)
        if (is_string($orderId) || !is_numeric($orderId) || $orderId <= 0) {
         $this->conn->rollback();
         // Mensaje de error más claro en caso de fallo numérico
          $error_message = is_string($orderId) ? $orderId : "Fallo al obtener ID del pedido principal. Revisar runDmlStatement.";
         return "Error al crear el pedido: {$error_message}";
        }

        // 6. INSERTAR DETALLES DEL PEDIDO
        foreach ($cart_details as $item) {
            $sql_detail = "INSERT INTO detalles_pedido (pedido_id, producto_id, cantidad, precio_unitario) VALUES (?, ?, ?, ?)";
            // CUIDADO: Usar el $orderId devuelto por runDmlStatement
            $detail_res = $this->runDmlStatement($sql_detail, "iiid", $orderId, $item['producto_id'], $item['cantidad'], $item['precio']);

            if (is_string($detail_res)) {
                $this->conn->rollback();
                // Opcional: Implementar aquí la liberación de stock comprometido para ser 100% seguro, aunque el rollback es suficiente si no hubo commit.
                return "Error al insertar detalle del pedido: {$detail_res}";
            }
        }
        
        // 7. VACIAR CARRITO Y LIBERAR COMPROMISO DE STOCK (Se hace en el método confirmOrder si se mantiene la lógica)
        // Ya que la lógica es "confirmar pedido" y luego descontar stock, por ahora solo eliminamos el carrito activo.
        
        // El stock comprometido se liberará/descontará cuando el administrador confirme/cancele la orden.
        // Por ahora, solo eliminamos el carrito activo ya que la información se pasó a 'pedidos'.
        $sql_delete_cart = "DELETE FROM carritos_activos WHERE id = ?";
        $delete_res = $this->runDmlStatement($sql_delete_cart, "i", $cartId);

        if (is_string($delete_res)) {
            // Este error es menor, pero debe revertir la orden si falla.
            $this->conn->rollback();
            return "Error al vaciar carrito: {$delete_res}";
        }

        // 8. FINALIZAR
        $this->conn->commit();
        return true; 

   } catch (\Throwable $e) {
        $this->conn->rollback();
        // Muestra el mensaje de la excepción y la línea donde falló para depuración
        return "❌ ERROR NO CAPTURADO: " . $e->getMessage() . " (Línea: " . $e->getLine() . ")"; 
    }

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
            ORDER BY p.fecha_pedido ASC"; // <-- CORREGIDO aquí

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
       // Reemplaza la línea anterior por esta:
        $sql = 'SELECT * FROM pedidos WHERE user_id = ? ORDER BY fecha_pedido DESC';
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