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

    // En src/php/models/ProductModel.php

/**
 * Obtiene las estadísticas de ventas (labels, data, media, mediana, moda).
 * Se basa en pedidos con estado 'completado'.
 * @return array
 */
public function getSalesStatistics(): array
{
    // ** 1. CONSULTA SQL **
    // OBJETIVO: Obtener el valor de 'cantidad' de CADA LÍNEA DE DETALLE de CADA PEDIDO 'completado'.
    // Esto crea el conjunto de datos necesario para calcular la Media, Mediana y Moda.
    $sql = "
        SELECT 
            dp.cantidad
        FROM 
            detalles_pedido dp
        JOIN 
            pedidos o ON dp.pedido_id = o.id
        WHERE
            o.estado = 'completado'
    ";

    $result = $this->runSelectStatement($sql, "");

    // Manejo de errores de DB
    if (is_string($result)) {
        error_log("Error de DB al obtener estadísticas: " . $result);
        return ['labels' => [], 'data' => [], 'media' => 0, 'mediana' => 0, 'moda' => 0, 'total_items' => 0];
    }
    
    $salesData = []; // Array simple de TODAS las cantidades vendidas (e.g., [4, 4, 4, 11, 11, 11, 11])

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Convertimos la cantidad a entero para el cálculo 
            $salesData[] = (int) $row['cantidad'];
        }
    }

    // Si no hay datos (no hay ventas completadas)
    if (empty($salesData)) {
        return ['labels' => ['Sin Ventas'], 'data' => [0], 'media' => 0, 'mediana' => 0, 'moda' => 0, 'total_items' => 0];
    }
    
    // ** 2. CARGAR HELPER **
    // Usamos la ruta relativa correcta desde ProductModel
    require_once __DIR__ . '/../services/StatisticsHelper.php';

    // ** 3. Cálculo de Estadísticas **
    $media = StatisticsHelper::calculateMean($salesData);
    $mediana = StatisticsHelper::calculateMedian($salesData);
    $moda = StatisticsHelper::calculateMode($salesData);

    // ** 4. Preparación para el gráfico (Gráfico de torta/barras de Unidades Vendidas por Producto)**
    // Esto requiere otra consulta, la hacemos aquí mismo para obtener la data completa.
    $sql_chart = "
        SELECT 
            p.nombre, 
            SUM(dp.cantidad) AS total_vendido
        FROM 
            detalles_pedido dp
        JOIN 
            productos p ON dp.producto_id = p.id
        JOIN 
            pedidos o ON dp.pedido_id = o.id
        WHERE
            o.estado = 'completado'
        GROUP BY 
            p.nombre
        ORDER BY 
            total_vendido DESC
    ";
    
    $chartResult = $this->runSelectStatement($sql_chart, "");
    $chartLabels = [];
    $chartData = [];
    
    if (!is_string($chartResult) && $chartResult->num_rows > 0) {
        while ($row = $chartResult->fetch_assoc()) {
            $chartLabels[] = $row['nombre'];
            $chartData[] = (int) $row['total_vendido'];
        }
    }
    // ** 5. Retorno del resultado **
    return [
        // Datos para el gráfico
        'labels' => $chartLabels,
        'data' => $chartData,
        // Datos estadísticos (calculados sobre salesData)
        'media' => $media,
        'mediana' => $mediana,
        'moda' => (is_array($moda) ? implode(', ', $moda) : $moda), // Manejar moda como array o float
        'total_items' => array_sum($salesData) // Total de unidades vendidas en general
    ];
}



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
    
     $sql = "UPDATE productos 
            SET stock_actual = stock_actual - ?, 
                stock_comprometido = stock_comprometido - ?, 
                unidades_vendidas = unidades_vendidas + ? 
            WHERE id = ? AND stock_actual >= ?"; // La condición stock_actual >= ? es una seguridad extra.


    $result = $this->runDmlStatement($sql, "iiiii", $quantity, $quantity, $quantity, $productId, $quantity); 

    if (is_int($result) && $result === 1) {
        return true;
    }

    if (is_string($result)) {
        return "Error de DB al confirmar venta: {$result}";
    }

    return "Error: Stock insuficiente o producto no encontrado al confirmar.";
    }
}