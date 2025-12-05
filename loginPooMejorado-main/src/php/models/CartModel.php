<?php
// src/php/models/CartModel.php

require_once 'DbModel.php';
require_once 'ProductModel.php';

class CartModel extends DbModel
{
    public const MAX_PER_ITEM = 5;

    public function __construct(mysqli $connection)
    {
        parent::__construct($connection);
    }

    // ----------------------------------------------------
    // 1. MÉTODOS DE LECTURA (Vista)
    // ----------------------------------------------------

    /**
     * Obtiene todos los ítems en el carrito del usuario.
     * @return array|string Array de ítems o mensaje de error de DB (string).
     */
    public function viewCart(int $userId): array|string
    {
        $sql = "
            SELECT
                d.producto_id,
                p.nombre,
                p.precio,
                p.imagen_url,
                d.cantidad,
                (p.stock_actual - p.stock_comprometido) AS stock_disponible,
                (d.cantidad * p.precio) AS subtotal
            FROM carritos_activos c
            JOIN detalles_carrito d ON c.id = d.carrito_id
            JOIN productos p ON d.producto_id = p.id
            WHERE c.user_id = ?
        ";

        $result = $this->runSelectStatement($sql, "i", $userId);

        if (is_string($result)) {
            return "Error al cargar el carrito: {$result}";
        }

        $items = [];
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $items[] = $row;
            }
        }
        return $items;
    }

    // ----------------------------------------------------
    // 2. MÉTODOS DE GESTIÓN (Añadir, Quitar, Actualizar)
    // ----------------------------------------------------

    public function getOrCreateCartId(int $userId): int|string
    {
        $sql_select = "SELECT id FROM carritos_activos WHERE user_id = ?";
        $result = $this->runSelectStatement($sql_select, "i", $userId);

        if (is_string($result))
            return "Error al buscar carrito: {$result}";

        if ($result && $result->num_rows > 0) {
            return (int) $result->fetch_assoc()['id'];
        }

        $sql_insert = "INSERT INTO carritos_activos (user_id) VALUES (?)";
        $result_insert = $this->runDmlStatement($sql_insert, "i", $userId);

        if (is_string($result_insert))
            return "Error al crear carrito: {$result_insert}";

        return (int) $this->conn->insert_id;
    }

  public function addItem(int $userId, int $productId, int $quantity, ProductModel $productModel): bool|string
    {
        // 1. Obtener ID del carrito o crearlo
        $cartId = $this->getOrCreateCartId($userId);
        if (is_string($cartId))
            return $cartId;

        // INICIO DE LA TRANSACCIÓN (Es crucial que las operaciones sean atómicas)
        $this->conn->begin_transaction();

        try {
            // 2. OBTENER STOCK DISPONIBLE (CON BLOQUEO DE FILA: FOR UPDATE)
            $sqlStock = "SELECT (stock_actual - stock_comprometido) AS stock_disponible FROM productos WHERE id = ? FOR UPDATE";
            $resultStock = $this->runSelectStatement($sqlStock, "i", $productId);

            if (is_string($resultStock)) {
                $this->conn->rollback();
                return "Error al verificar stock: {$resultStock}";
            }
            if ($resultStock->num_rows === 0) {
                $this->conn->rollback();
                return "Producto no encontrado.";
            }
            
            $availableStock = (int) $resultStock->fetch_assoc()['stock_disponible'];

            // 2.1 OBTENER CANTIDAD ACTUAL EN CARRITO (BLOQUEO DE FILA)
            $sqlCurrentQty = "SELECT d.cantidad FROM detalles_carrito d JOIN carritos_activos c ON d.carrito_id = c.id WHERE c.user_id = ? AND d.producto_id = ? FOR UPDATE";
            $resultCurrentQty = $this->runSelectStatement($sqlCurrentQty, "ii", $userId, $productId);
            
            $currentQuantity = 0;
            if (!is_string($resultCurrentQty) && $resultCurrentQty->num_rows > 0) {
                 $currentQuantity = (int) $resultCurrentQty->fetch_assoc()['cantidad'];
            }
            
            $newTotalQuantity = $currentQuantity + $quantity;

            // 2.2 VALIDACIÓN DE LÍMITE DE CANTIDAD (NUEVO)
            if ($newTotalQuantity > self::MAX_PER_ITEM) {
                $this->conn->rollback();
                return "Límite excedido. Solo puedes tener " . self::MAX_PER_ITEM . " unidades de este producto en tu carrito. (Actualmente tienes " . $currentQuantity . ")";
            }
            
            // 2.3 VALIDACIÓN CRÍTICA: Se comprueba que el stock disponible cubra la cantidad solicitada
            if ($availableStock < $quantity) {
                $this->conn->rollback();
                return "Stock insuficiente. Solo quedan " . $availableStock . " unidades disponibles.";
            }
            
            // 3. AUMENTAR STOCK COMPROMETIDO
            $stock_result = $productModel->manipulateCompromisedStock($productId, $quantity);
            if (is_string($stock_result)) {
                $this->conn->rollback();
                return $stock_result;
            }

            // 4. AÑADIR O ACTUALIZAR DETALLE
            $sql_update = "
                UPDATE detalles_carrito d JOIN carritos_activos c ON d.carrito_id = c.id
                SET d.cantidad = d.cantidad + ?
                WHERE c.user_id = ? AND d.producto_id = ?
            ";
            $affected = $this->runDmlStatement($sql_update, "iii", $quantity, $userId, $productId);

            if (is_string($affected)) {
                $this->conn->rollback();
                return "Error al actualizar carrito: {$affected}";
            }

            if ($affected === 0) {
                $sql_insert = "INSERT INTO detalles_carrito (carrito_id, producto_id, cantidad) VALUES (?, ?, ?)";
                $insert_res = $this->runDmlStatement($sql_insert, "iii", $cartId, $productId, $quantity);

                if (is_string($insert_res)) {
                    $this->conn->rollback();
                    return "Error al añadir ítem: {$insert_res}";
                }
            }

            // 5. ACTUALIZAR FECHA Y CONFIRMAR TRANSACCIÓN
            $this->touchCartDate($cartId);
            $this->conn->commit();
            return true;

        } catch (\Throwable $e) {
            $this->conn->rollback();
            error_log("CartModel addItem transaction error: " . $e->getMessage());
            return "Error interno del sistema al procesar la reserva.";
        }
    }

    public function removeItem(int $userId, int $productId, ProductModel $productModel): bool|string
    {
        // 1. Buscar cantidad actual
        $sql_find = "SELECT d.cantidad, c.id FROM detalles_carrito d JOIN carritos_activos c ON d.carrito_id = c.id WHERE c.user_id = ? AND d.producto_id = ?";
        $result = $this->runSelectStatement($sql_find, "ii", $userId, $productId);

        if (is_string($result))
            return "Error de DB al buscar ítem.";
        if ($result->num_rows === 0)
            return true; // Ya no existe

        $row = $result->fetch_assoc();
        $quantity = (int) $row['cantidad'];
        $cartId = (int) $row['id'];

        // 2. Liberar stock comprometido
        $stock_res = $productModel->manipulateCompromisedStock($productId, -$quantity);
        if (is_string($stock_res))
            return "Error al devolver stock: {$stock_res}";

        // 3. Eliminar ítem
        $sql_del = "DELETE FROM detalles_carrito WHERE carrito_id = ? AND producto_id = ?";
        $del_res = $this->runDmlStatement($sql_del, "ii", $cartId, $productId);

        if (is_string($del_res))
            return "Error al eliminar ítem.";

        $this->touchCartDate($cartId);
        return true;
    }
public function updateQuantity(int $userId, int $productId, int $newQuantity, ProductModel $productModel): bool|string
    // ^^^^^^^^^^^^^^^ ASEGÚRATE DE USAR ESTE NOMBRE
    {
        // 1. Validaciones básicas de límite de negocio
        if ($newQuantity < 1) {
            // El usuario debería usar el botón de eliminar para quitar un ítem.
            return "La cantidad mínima es 1.";
        }
        
        // VALIDACIÓN CRÍTICA DEL LÍMITE DE 5 UNIDADES
        // (Asume que self::MAX_PER_ITEM = 5 está definido en la clase)
        if ($newQuantity > self::MAX_PER_ITEM) {
            return "Límite excedido. Solo puedes tener " . self::MAX_PER_ITEM . " unidades de este producto en total.";
        }
        
        $this->conn->begin_transaction();
        
        try {
            // 2. Obtener datos del carrito y producto (CON BLOQUEO FOR UPDATE)
            $sqlData = "
                SELECT 
                    d.cantidad AS old_quantity, 
                    p.stock_actual, 
                    p.stock_comprometido
                FROM detalles_carrito d
                JOIN carritos_activos c ON d.carrito_id = c.id
                JOIN productos p ON d.producto_id = p.id
                WHERE c.user_id = ? AND d.producto_id = ? 
                FOR UPDATE
            ";
            $resultData = $this->runSelectStatement($sqlData, "ii", $userId, $productId);
            
            if (is_string($resultData) || $resultData->num_rows === 0) {
                $this->conn->rollback();
                return "Producto no encontrado en el carrito o error de DB.";
            }

            $data = $resultData->fetch_assoc();
            $oldQuantity = (int) $data['old_quantity'];
            $stockActual = (int) $data['stock_actual'];
            $stockComprometido = (int) $data['stock_comprometido'];
            
            $stockChange = $newQuantity - $oldQuantity; // Diferencia de stock a comprometer/liberar

            // 3. Chequeo de Stock si hay aumento ($stockChange > 0)
            if ($stockChange > 0) {
                $availableStock = $stockActual - $stockComprometido;
                if ($availableStock < $stockChange) {
                    $this->conn->rollback();
                    return "Stock insuficiente. Solo puedes aumentar la cantidad en " . $availableStock . " unidad(es) más.";
                }
            }
            
            // 4. Actualizar Compromiso de Stock (si hubo cambio)
            if ($stockChange !== 0) {
                $stock_result = $productModel->manipulateCompromisedStock($productId, $stockChange);
                if (is_string($stock_result)) {
                    $this->conn->rollback();
                    return $stock_result;
                }
            }

            // 5. Actualizar Detalle del Carrito
            $sql_update_detail = "
                UPDATE detalles_carrito d
                JOIN carritos_activos c ON d.carrito_id = c.id
                SET d.cantidad = ?
                WHERE c.user_id = ? AND d.producto_id = ?
            ";
            $update_res = $this->runDmlStatement($sql_update_detail, "iii", $newQuantity, $userId, $productId);
            
            if (is_string($update_res)) {
                $this->conn->rollback();
                return "Error al actualizar la cantidad en el carrito: {$update_res}";
            }
            
            // 6. Finalizar
            $cartId = $this->getOrCreateCartId($userId); 
            $this->touchCartDate($cartId);
            $this->conn->commit();
            return true;

        } catch (\Throwable $e) {
            $this->conn->rollback();
            error_log("CartModel updateQuantity transaction error: " . $e->getMessage());
            return "Error interno del sistema al procesar la actualización.";
        }
    }

    // ----------------------------------------------------
    // 3. MÉTODOS DE LIMPIEZA Y UTILIDAD
    // ----------------------------------------------------

    private function touchCartDate(int $cartId)
    {
        $sql = "UPDATE carritos_activos SET fecha_actualizacion = NOW() WHERE id = ?";
        $this->runDmlStatement($sql, "i", $cartId);
    }

    // En src/php/models/CartModel.php

    public function clearExpiredCarts(int $expirationMinutes = 30): bool
    {
        // CAMBIO IMPORTANTE: Usamos DATE_SUB(NOW()...) de MySQL en lugar de calcularlo en PHP.
        // Esto evita problemas si la hora de PHP y MySQL no están sincronizadas.

        // 1. Encontrar carritos expirados
        $sql_expired_carts = "SELECT c.id, d.producto_id, d.cantidad 
                              FROM carritos_activos c
                              JOIN detalles_carrito d ON c.id = d.carrito_id
                              WHERE c.fecha_actualizacion < DATE_SUB(NOW(), INTERVAL ? MINUTE)";

        $result = $this->runSelectStatement($sql_expired_carts, "i", $expirationMinutes);

        if (is_string($result)) {
            error_log("Error de DB al buscar carritos expirados: " . $result);
            return false;
        }

        if ($result->num_rows > 0) {
            $productModel = new ProductModel($this->conn);

            while ($row = $result->fetch_assoc()) {
                // Liberar Stock
                $productModel->manipulateCompromisedStock($row['producto_id'], -$row['cantidad']);
            }

            // 2. Eliminar los carritos expirados
            $sql_delete_carts = "DELETE FROM carritos_activos WHERE fecha_actualizacion < DATE_SUB(NOW(), INTERVAL ? MINUTE)";
            $this->runDmlStatement($sql_delete_carts, "i", $expirationMinutes);
        }

        return true;
    }
}