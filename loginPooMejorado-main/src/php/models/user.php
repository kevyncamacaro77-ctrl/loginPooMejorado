<?php
require_once 'database.php';

class User
{
    private $conn;

    public function __construct(mysqli $connection)
    {
        // Inicializa la conexión a la DB a través de la clase Database
        $this->conn = $connection;
    }

    /**
     * Funcion que permite ejecutar consultas de lectura (Select).
     * @return mysqli_result|bool El resultado o false en caso de fallo.
     */
    private function runSelectStatement(string $sql, string $types, ...$params): mysqli_result|string|null
    {
        $stmt = $this->conn->prepare($sql);

        // 1. Manejo de error de PREPARACIÓN
        if ($stmt === false) {
            return "Error de preparación de SELECT: " . $this->conn->error;
        }

        // 2. ENLACE Y MANEJO DE ERROR DE BINDING (ÚNICO BLOQUE)
        // Solo se llama a bind_param si hay tipos y parámetros.
        if (!empty($types) && !empty($params)) {
            // Ejecutamos bind_param y verificamos si falló (retorna false)
            if (!$stmt->bind_param($types, ...$params)) {
                $stmt->close();
                // Usamos $this->conn->error si $stmt->error está vacío después de un fallo de bind.
                $error = $stmt->error ?: $this->conn->error;
                return "Error de enlace de parámetros (bind_param): {$error}";
            }
        }

        // 3. Manejo de error de EJECUCIÓN (Lógica correcta, se mantiene)
        if (!$stmt->execute()) {
            $error_message = $stmt->error;
            $stmt->close();
            return "Error de ejecución de SELECT: " . $error_message;
        }

        $result = $stmt->get_result();
        $stmt->close();

        return $result;
    }

    /**
     * Helper para ejecutar modificaciones(INSERT, UPDATE, DELETE).
     * @return bool|int|string True si éxito, el código de error 1062 para duplicados.
     */
    private function runDmlStatement(string $sql, string $types, ...$params): bool|int|string
    {
        $stmt = $this->conn->prepare($sql);

        // Si la preparación de la sentencia falla
        if ($stmt === false) {
            return "Error de preparación: {$this->conn->error}";
        }

        // APLICACIÓN DE LA CLÁUSULA DE GUARDA PARA EVITAR BIND_PARAM EN QUERIES SIN PARÁMETROS
        if (!empty($types) && !empty($params)) {
            // Ejecutamos bind_param y verificamos si falló
            if (!$stmt->bind_param($types, ...$params)) {
                $stmt->close();
                // Usamos $this->conn->error si $stmt->error está vacío
                $error = $stmt->error ?: $this->conn->error;
                return "Error de enlace de parámetros (bind_param): {$error}";
            }
        }

        // Si la ejecución es exitosa
        if ($stmt->execute()) {
            $filas_afectadas = $this->conn->affected_rows; // <-- Capturar filas afectadas
            $stmt->close();
            return $filas_afectadas; // <-- Devolvemos el número de filas (0 o más)
        }

        // ... (El resto del manejo de errores 1062 y genéricos es correcto)
        $error_code = $this->conn->errno;
        $error_message = $this->conn->error;
        $stmt->close();

        if ($error_code === 1062) {
            return 1062;
        }

        return "Error de ejecución: {$error_message}";
    }

    //Saber si un email ya existe en la DB
    public function emailExists($email)
    {
        $sql = "SELECT id FROM usuarios WHERE email = ?";
        $result = $this->runSelectStatement($sql, "s", $email);

        // Si es una cadena, hubo un error de DB (devolver false para la lógica 'exists').
        if (is_string($result)) {
            // Loguear el error $result
            return false;
        }

        // Si es mysqli_result o null, procedemos con la lógica de negocio
        return $result && $result->num_rows > 0;
    }

    public function checkAndProcessIpBlock(string $ip, int $maxAttempts): bool|string
    {
        // 1. Eliminar entradas antiguas expiradas (Mantenimiento)
        $sql_delete = "DELETE FROM failing_attempts_ip WHERE block_time < NOW() AND block_time IS NOT NULL";
        $this->runDmlStatement($sql_delete, "");

        // 2. Obtener registro de la IP actual
        $sql_select = "SELECT failed_attempts, block_time FROM failing_attempts_ip WHERE ip_address = ?";
        $result = $this->runSelectStatement($sql_select, "s", $ip);

        if (is_string($result) || $result->num_rows === 0) {
            return true;
        }

        $row = $result->fetch_assoc();

        // 3. CHEQUEAR BLOQUEO ACTIVO
        if ($row['block_time'] && strtotime($row['block_time']) > time()) {
            $tiempo_restante = strtotime($row['block_time']) - time();
            $minutos = ceil($tiempo_restante / 60);
            return "Tu IP está bloqueada. Intenta de nuevo en aproximadamente {$minutos} minutos.";
        }

        // 4. RESETEAR CONTADOR si el bloqueo expiró (Doble chequeo, aunque el DELETE ya lo maneja)
        if (
            $row['failed_attempts'] > 0 &&
            $row['block_time'] && strtotime($row['block_time']) < time()
        ) {

            $sql_reset = "UPDATE failing_attempts_ip SET failed_attempts = 0, block_time = NULL WHERE ip_address = ?";
            $this->runDmlStatement($sql_reset, "s", $ip);
        }

        return true;
    }


    public function incrementIpAttempt(string $ip, int $lockoutMinutes)
    {
        $MAX_ATTEMPTS = 3;

        // 1. Intentar actualizar el contador (si ya existe)
        $sql_update = "UPDATE failing_attempts_ip SET failed_attempts = failed_attempts + 1 
                   WHERE ip_address = ?";
        $affected_rows = $this->runDmlStatement($sql_update, "s", $ip); // <-- Retorna INT (0 o 1)

        // 2. Si affected_rows es CERO, significa que el registro NO existía (primer fallo)
        if ($affected_rows === 0) {
            // Intentar insertar la nueva IP (primer fallo)
            $sql_insert = "INSERT INTO failing_attempts_ip (ip_address, failed_attempts) VALUES (?, 1)";
            // No necesitamos el resultado aquí, solo lo ejecutamos
            $this->runDmlStatement($sql_insert, "s", $ip);
        }
        // Si affected_rows es una cadena (error de DB), la verificación 
        // en el paso 3 se encargará de esto.

        // 3. Revisar el estado y aplicar el bloqueo si se excede el límite
        $sql_check = "SELECT failed_attempts FROM failing_attempts_ip WHERE ip_address = ?";
        $result_check = $this->runSelectStatement($sql_check, "s", $ip);

        if (!is_string($result_check) && $result_check->num_rows > 0) {
            $attempts = $result_check->fetch_assoc()['failed_attempts'];

            if ($attempts >= $MAX_ATTEMPTS) {
                // Aplicar el bloqueo por la duración especificada
                $lockout_time = date('Y-m-d H:i:s', strtotime("+{$lockoutMinutes} minutes"));
                $sql_lock = "UPDATE failing_attempts_ip SET block_time = ? WHERE ip_address = ?";
                $this->runDmlStatement($sql_lock, "ss", $lockout_time, $ip);
            }
        }
    }

    public function clearIpAttempts(string $ip)
    {
        // Usa DELETE para eliminar el registro de intentos fallidos de la IP, reseteando el contador.
        $sql = "DELETE FROM failing_attempts_ip WHERE ip_address = ?";
        $this->runDmlStatement($sql, "s", $ip);
    }
    // FUNCIONES DE CRUD DE USUARIO

    /**
     * Obtiene los datos de un usuario por su ID.
     * @return array|bool Array con los datos del usuario, false si no existe.
     */
    public function getUserDataById($id)
    {
        $sql = "SELECT nombre, email FROM usuarios WHERE id = ?";
        $result = $this->runSelectStatement($sql, "i", $id);

        // 1. Manejar Error de DB
        if (is_string($result)) {
            // Loguear el error. Retornar false (no se encontraron datos).
            return false;
        }

        // 2. Lógica de negocio
        if ($result && $result->num_rows === 1)
            return $result->fetch_assoc();

        return false;
    }

    /**
     * Registra un nuevo usuario.
     * @return bool|string True si es exitoso, un mensaje de error si falla.
     */
    public function register($nombre, $email, $password)
    {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // 1. Usar el método DML auxiliar para ejecutar la inserción
        $sql = "INSERT INTO usuarios (nombre, email, contrasenna) VALUES (?, ?, ?)";
        $result = $this->runDmlStatement($sql, "sss", $nombre, $email, $hashedPassword);

        // 2. Salida Anticipada si es exitoso
        if ($result === true)
            return true;

        // 3. Manejo de error específico (1062 - Duplicado)
        if ($result === 1062)
            return "Error: El correo electrónico ya está registrado.";

        // 4. Fallo genérico (cubre cualquier otro error, incluyendo el 'false' de la preparación)
        return "Error al registrar usuario. Inténtelo de nuevo más tarde.";
    }

    /** @return array|bool arreglo con el id del usuario y usuario si hubo éxito, un false si falla */
    public function login($email, $password)
    {
        // Se usa el método modularizado para SELECT
        $sql = "SELECT id, nombre, contrasenna, rol FROM usuarios WHERE email = ?";
        $result = $this->runSelectStatement($sql, "s", $email);

        // 1. Manejo de Error de DB
        // Si $result es una cadena, significa que runSelectStatement() devolvió un error interno de la DB.
        if (is_string($result)) {
            // En un entorno real, aquí se debe LOGUEAR el contenido de $result.
            // Se devuelve false para indicar que el login falló debido a un error del sistema.
            return false;
        }

        // 2. Cláusula de Guarda: Si la consulta falló (ej. $result es null) O no se encontró 1 fila.
        // Esta línea solo se ejecuta si $result es un objeto mysqli_result o null.
        if (!$result || $result->num_rows !== 1)
            return false;

        // 3. Si el código llega aquí, tenemos 1 fila.
        $row = $result->fetch_assoc();

        // 4. Verificación de Contraseña
        if (password_verify($password, $row['contrasenna']))
            return [
                'user_id' => $row['id'],
                'usuario' => $row['nombre'],
                'rol' => $row['rol']
            ]; // Salida de Éxito

        // 5. Salida de Fracaso: Si la contraseña no es válida.
        return false;
    }

    /** @return bool|string si fue exitoso, string si es error */
    /* Se encarga de actualizar el usuario */
    public function update($id, $nombre, $email)
    {
        // 1. Verificar si el email ya existe para OTRO usuario
        $sql_check = "SELECT id FROM usuarios WHERE email = ? AND id != ?";
        $result_check = $this->runSelectStatement($sql_check, "si", $email, $id);

        // 2. MANEJO DE ERRORES DEL SELECT DE VERIFICACIÓN
        // Si $result_check es una cadena, significa que runSelectStatement() devolvió un error interno (preparación, ejecución, etc.).
        if (is_string($result_check)) {
            // Loguear el error $result_check. 
            // Devolvemos un mensaje genérico al usuario en lugar del error detallado de SQL.
            return "Error interno del sistema al verificar el correo electrónico. Intente más tarde.";
        }

        // 3. CLÁUSULA DE GUARDA de LÓGICA DE NEGOCIO (Duplicado)
        // Se ejecuta solo si $result_check es un objeto mysqli_result o null.
        if ($result_check && $result_check->num_rows > 0) {
            return "Error: El correo electrónico ya está registrado por otra cuenta.";
        }

        // 4. Ejecutar la actualización (DML)
        $sql = "UPDATE usuarios SET nombre=?, email=? WHERE id=?";
        $result = $this->runDmlStatement($sql, "ssi", $nombre, $email, $id);

        // 5. Manejo de resultados de DML
        if ($result === true)
            return true;

        if ($result === 1062)
            return "Error: El nuevo correo electrónico ya está en uso por otro usuario.";

        // Si $result es una cadena (error de preparación/ejecución de DML)
        if (is_string($result))
            return "Error interno del sistema al actualizar datos. Detalle: {$result}";

        // Fallo genérico
        return "Error al actualizar usuario. Intente de nuevo.";
    }
}