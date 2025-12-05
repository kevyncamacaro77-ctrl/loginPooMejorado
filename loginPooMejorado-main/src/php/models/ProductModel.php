<?php
// src/php/models/ProductModel.php

require_once 'DbModel.php';

class ProductModel extends DbModel
{
    public function __construct(mysqli $connection)
    {
        // Llama al constructor del padre (DbModel) para inicializar $this->conn
        parent::__construct($connection);
    }

    // --- MÉTODOS DE LECTURA (Catálogo) ---

    /**
     * Obtiene todos los productos del catálogo, incluyendo el stock disponible (calculado).
     * @return array|string Array de productos o mensaje de error.
     */
    public function getAllProducts(): array|string
    {
        $sql = "
            SELECT 
                id, 
                nombre, 
                descripcion, 
                precio, 
                stock_actual,
                stock_comprometido,
                -- CALCULAR EL STOCK DISPONIBLE (lo que el cliente puede reservar)
                (stock_actual - stock_comprometido) AS stock_disponible, 
                imagen_url 
            FROM 
                productos 
            ORDER BY 
                nombre ASC
        ";

        $result = $this->runSelectStatement($sql, "");

        if (is_string($result)) {
            return "Error al cargar el catálogo: {$result}";
        }

        $products = [];
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $products[] = $row;
            }
        }
        return $products;
    }

    /**
     * Obtiene un producto por su ID para ver detalles o verificar stock.
     * @return array|string|false Datos del producto, error de DB (string), o false si no existe.
     */
    public function getProductById(int $productId): array|string|false
    {
        $sql = "SELECT id, nombre, descripcion, precio, stock_actual, stock_comprometido, imagen_url FROM productos WHERE id = ?";

        $result = $this->runSelectStatement($sql, "i", $productId);

        if (is_string($result)) {
            return "Error de DB al buscar producto: " . $result;
        }

        if ($result && $result->num_rows === 1) {
            return $result->fetch_assoc();
        }

        return false;
    }

    // --- MÉTODOS DE MANIPULACIÓN DE STOCK (Reservas y Administración) ---

    /**
     * Añade o remueve stock del comprometido. Es el método clave de la reserva.
     * @param int $quantity Cantidad a sumar (positiva) o restar (negativa).
     * @return int|string Filas afectadas (1) o mensaje de error (string).
     */
    public function manipulateCompromisedStock(int $productId, int $quantity): int|string
    {
        // Esta lógica maneja la reserva (quantity > 0) y la liberación (quantity < 0).

        $sql = "UPDATE productos SET stock_comprometido = stock_comprometido + ? WHERE id = ?";
        $params = [$quantity, $productId];
        $types = "ii";

        // Cláusula de seguridad: Si intentamos AUMENTAR el stock comprometido (reservar),
        // debemos verificar que el stock actual sea mayor o igual al nuevo stock comprometido.
        if ($quantity > 0) {
            // stock_actual debe ser mayor o igual al stock_comprometido que ya tengo + la nueva cantidad
            $sql .= " AND stock_actual >= (stock_comprometido + ?)";
            $params[] = $quantity; // Agregamos la cantidad que queremos sumar al comprometido
            $types = "iii";
        }

        // Si $quantity es negativa (liberación/devolución), no necesitamos la cláusula AND stock_actual >=.

        $result = $this->runDmlStatement($sql, $types, ...$params);

        if (is_int($result) && $result === 1) {
            return $result; // Éxito
        }

        if (is_string($result)) {
            return "Error de DB al modificar stock comprometido: {$result}";
        }

        // Si devuelve 0 y la cantidad era positiva, significa que la condición WHERE falló (sin stock)
        if ($quantity > 0 && $result === 0) {
            return "No hay suficiente stock disponible para la reserva.";
        }

        // Retorno de 0 o error genérico
        return $result;
    }


    /**
     * Actualiza el stock físico de forma directa (Admin Action: update_stock).
     * @return bool|string True si éxito, o error de DB (string).
     */
    /**
     * Actualiza el stock físico de forma directa (Admin Action: update_stock).
     * @return bool|string True si éxito, o error de DB (string).
     */
    public function updateStockDirect(int $productId, int $newStock): bool|string
    {
        // 1. VALIDACIÓN EN SQL: 
        // El NUEVO stock (?) debe ser mayor o igual al stock_comprometido.
        // Corregimos la consulta para comparar el valor entrante, no el actual.
        $sql = "UPDATE productos SET stock_actual = ? WHERE id = ? AND ? >= stock_comprometido";

        // Pasamos 3 parámetros: nuevo_stock, id, nuevo_stock
        $result = $this->runDmlStatement($sql, "iii", $newStock, $productId, $newStock);

        if (is_string($result)) {
            return "Error de DB al actualizar stock: {$result}";
        }

        // 2. MANEJO DE 0 FILAS AFECTADAS
        if ($result === 0) {
            // Puede ser 0 por dos razones:
            // A) El nuevo stock es inválido (menor al comprometido).
            // B) El stock no cambió (el admin puso el mismo número).

            // Consultamos los valores actuales para saber cuál fue la razón
            $check = $this->runSelectStatement("SELECT stock_actual, stock_comprometido FROM productos WHERE id = ?", "i", $productId);

            if ($check && $check->num_rows > 0) {
                $data = $check->fetch_assoc();

                // CASO B: El valor es el mismo -> ÉXITO
                if ((int) $data['stock_actual'] === $newStock) {
                    return true;
                }

                // CASO A: El valor es inválido -> ERROR
                if ($newStock < $data['stock_comprometido']) {
                    return "Error: No puedes reducir el stock a {$newStock} porque hay {$data['stock_comprometido']} productos reservados.";
                }
            }

            return "No se pudo actualizar (Producto no encontrado).";
        }

        return true;
    }

    /**
     * Método para eliminar un producto (Admin Action: delete_product).
     * @return bool|string True si éxito, o error de DB (string).
     */
    public function deleteProduct(int $productId): bool|string
    {
        // NOTA: Se asume que la FK en detalles_carrito previene la eliminación si hay reservas activas.
        $sql = "DELETE FROM productos WHERE id = ?";
        $result = $this->runDmlStatement($sql, "i", $productId);

        if (is_string($result)) {
            // Capturamos el error de la DB (ej. error 1451: constraint fail)
            return "Error de DB al eliminar: {$result}";
        }

        if ($result === 0) {
            return "El producto no existe o no se pudo eliminar (verifique reservas activas).";
        }

        return true;
    }

    /**
     * Reduce o incrementa (si cantidad es negativa) el stock físico (stock_actual).
     * @return int|string Filas afectadas (1) o mensaje de error (string).
     */
    public function deductStock(int $productId, int $quantity): int|string
    {
        $sql = "UPDATE productos SET stock_actual = stock_actual - ? WHERE id = ?";
        $params = [$quantity, $productId];
        $types = "ii";

        // Añadimos la verificación de stock solo si la operación es una deducción (reserva)
        if ($quantity > 0) {
            // Aseguramos que stock_actual no baje de stock_comprometido
            $sql .= " AND (stock_actual - stock_comprometido) >= ?";
            $params[] = $quantity;
            $types = "iii";
        }

        $result = $this->runDmlStatement($sql, $types, ...$params);

        if (is_int($result) && $result === 1) {
            return $result; // Éxito
        }

        if (is_string($result)) {
            return "Error de DB al modificar stock: {$result}";
        }

        // Si devuelve 0 y la cantidad era positiva, significa que la condición WHERE falló
        if ($quantity > 0 && $result === 0) {
            return "No hay suficiente stock disponible para la reserva.";
        }

        return $result;
    }

    /**
     * Reduce el stock físico (stock_actual) y el comprometido después de una venta final.
     * Este método es llamado por el Administrador al confirmar un pedido.
     * @return bool|string True si éxito, o error de DB (string).
     */
    public function confirmSaleDeductStock(int $productId, int $quantity): bool|string
    {
        // 1. Reducir stock_actual (el inventario real baja porque el producto se vendió)
        // 2. Reducir stock_comprometido (ya no está "reservado", está "vendido")
        // La condición stock_actual >= ? es una seguridad extra.

        $sql = "UPDATE productos 
                SET stock_actual = stock_actual - ?, 
                    stock_comprometido = stock_comprometido - ? 
                WHERE id = ? AND stock_actual >= ?";

        // Tipos: iiii (cantidad, cantidad, id, cantidad)
        $result = $this->runDmlStatement($sql, "iiii", $quantity, $quantity, $productId, $quantity);

        if (is_int($result) && $result === 1) {
            return true;
        }

        if (is_string($result)) {
            return "Error de DB al confirmar venta: {$result}";
        }

        return "Error: Stock insuficiente o producto no encontrado al confirmar.";
    }
}