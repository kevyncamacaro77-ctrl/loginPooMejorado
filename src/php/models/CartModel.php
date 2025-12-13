<?php
// src/php/models/CartModel.php

require_once 'DbModel.php';
require_once 'ProductModel.php';

class CartModel extends DbModel
{
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
        $cartId = $this->getOrCreateCartId($userId);
        if (is_string($cartId))
            return $cartId;

        // 1. AUMENTAR STOCK COMPROMETIDO
        $stock_result = $productModel->manipulateCompromisedStock($productId, $quantity);
        if (is_string($stock_result))
            return $stock_result;

        // 2. AÑADIR O ACTUALIZAR DETALLE
        $sql_update = "
            UPDATE detalles_carrito d JOIN carritos_activos c ON d.carrito_id = c.id
            SET d.cantidad = d.cantidad + ?
            WHERE c.user_id = ? AND d.producto_id = ?
        ";
        $affected = $this->runDmlStatement($sql_update, "iii", $quantity, $userId, $productId);

        if (is_string($affected)) {
            $productModel->manipulateCompromisedStock($productId, -$quantity); // Revertir
            return "Error al actualizar carrito: {$affected}";
        }

        if ($affected === 0) {
            $sql_insert = "INSERT INTO detalles_carrito (carrito_id, producto_id, cantidad) VALUES (?, ?, ?)";
            $insert_res = $this->runDmlStatement($sql_insert, "iii", $cartId, $productId, $quantity);

            if (is_string($insert_res)) {
                $productModel->manipulateCompromisedStock($productId, -$quantity); // Revertir
                return "Error al añadir ítem: {$insert_res}";
            }
        }

        // 3. ACTUALIZAR FECHA
        $this->touchCartDate($cartId);
        return true;
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
    {
        $sql_find = "SELECT d.cantidad, c.id FROM detalles_carrito d JOIN carritos_activos c ON d.carrito_id = c.id WHERE c.user_id = ? AND d.producto_id = ?";
        $result = $this->runSelectStatement($sql_find, "ii", $userId, $productId);

        if (is_string($result) || $result->num_rows === 0)
            return "Error: Ítem no encontrado.";

        $row = $result->fetch_assoc();
        $oldQuantity = (int) $row['cantidad'];
        $cartId = (int) $row['id'];
        $difference = $newQuantity - $oldQuantity;

        // Ajustar stock comprometido
        $stock_res = $productModel->manipulateCompromisedStock($productId, $difference);
        if (is_string($stock_res))
            return $stock_res;

        // Actualizar cantidad
        $sql_upd = "UPDATE detalles_carrito SET cantidad = ? WHERE carrito_id = ? AND producto_id = ?";
        $upd_res = $this->runDmlStatement($sql_upd, "iii", $newQuantity, $cartId, $productId);

        if (is_string($upd_res)) {
            $productModel->manipulateCompromisedStock($productId, -$difference); // Revertir
            return "Error al actualizar cantidad.";
        }

        $this->touchCartDate($cartId);
        return true;
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