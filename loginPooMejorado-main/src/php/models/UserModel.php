<?php
// src/php/models/UserModel.php

require_once 'DbModel.php';

class UserModel extends DbModel
{
    public function __construct(mysqli $connection)
    {
        parent::__construct($connection);
    }

    // ----------------------------------------------------
    // MÉTODOS DE AUTENTICACIÓN
    // ----------------------------------------------------

    /**
     * Verifica credenciales de login.
     * @return array|bool Array con datos del usuario si éxito, false si falla.
     */
    public function login($email, $password)
    {
        $sql = "SELECT id, nombre, contrasenna, rol FROM usuarios WHERE email = ?";
        $result = $this->runSelectStatement($sql, "s", $email);

        if (is_string($result)) return false; // Error DB
        if (!$result || $result->num_rows !== 1) return false;

        $row = $result->fetch_assoc();

        if (password_verify($password, $row['contrasenna'])) {
            return [
                'user_id' => $row['id'],
                'usuario' => $row['nombre'],
                'rol' => $row['rol']
            ];
        }
        return false;
    }

    /**
     * Registra un nuevo usuario.
     */
    public function register($nombre, $email, $password)
    {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $sql = "INSERT INTO usuarios (nombre, email, contrasenna) VALUES (?, ?, ?)";
        
        $result = $this->runDmlStatement($sql, "sss", $nombre, $email, $hashedPassword);

        if (is_int($result) && $result > 0) return true;
        if ($result === 1062) return "Error: El correo electrónico ya está registrado.";
        
        return "Error al registrar usuario.";
    }

    // ----------------------------------------------------
    // MÉTODOS DE SEGURIDAD Y PERFIL (AQUÍ FALTABA EL MÉTODO)
    // ----------------------------------------------------

    /**
     * Verifica si la contraseña proporcionada coincide con la del usuario actual.
     * ESENCIAL PARA EL CAMBIO DE PERFIL SEGURO.
     */
    public function verifyCurrentPassword(int $id, string $inputPassword): bool
    {
        $sql = "SELECT contrasenna FROM usuarios WHERE id = ?";
        $result = $this->runSelectStatement($sql, "i", $id);

        if (is_string($result) || $result->num_rows === 0) {
            return false;
        }

        $storedHash = $result->fetch_assoc()['contrasenna'];
        return password_verify($inputPassword, $storedHash);
    }

    /**
     * Actualiza el perfil (nombre y opcionalmente contraseña).
     */
    public function updateProfile($id, $nombre, $password_new = null)
    {
        $sql = "UPDATE usuarios SET nombre=? ";
        $types = "si";
        $params = [$nombre, $id];

        if ($password_new !== null && !empty($password_new)) {
            $hashedPassword = password_hash($password_new, PASSWORD_DEFAULT);
            $sql .= ", contrasenna=? "; 
            $types = "ssi"; 
            array_splice($params, count($params) - 1, 0, $hashedPassword);
        }
        
        $sql .= "WHERE id=?";
        $result = $this->runDmlStatement($sql, $types, ...$params);

        if (is_int($result) && $result >= 0) return true;
        if (is_string($result)) return "Error de sistema: {$result}";
        
        return "No se realizaron cambios.";
    }

    // ----------------------------------------------------
    // MÉTODOS DE UTILIDAD (Validación y Datos)
    // ----------------------------------------------------

    public function emailExists($email)
    {
        $sql = "SELECT id FROM usuarios WHERE email = ?";
        $result = $this->runSelectStatement($sql, "s", $email);
        if (is_string($result)) return false;
        return $result && $result->num_rows > 0;
    }

    public function getUserDataById($id)
    {
        $sql = "SELECT nombre, email FROM usuarios WHERE id = ?";
        $result = $this->runSelectStatement($sql, "i", $id);
        if (is_string($result)) return false;
        if ($result && $result->num_rows === 1) return $result->fetch_assoc();
        return false;
    }

    // ----------------------------------------------------
    // MÉTODOS DE BLOQUEO POR IP (Anti-Brute Force)
    // ----------------------------------------------------

    public function checkAndProcessIpBlock(string $ip): bool|string
    {
        $this->runDmlStatement("DELETE FROM failing_attempts_ip WHERE block_time < NOW()", "");

        $sql = "SELECT failed_attempts, block_time FROM failing_attempts_ip WHERE ip_address = ?";
        $result = $this->runSelectStatement($sql, "s", $ip);

        if (is_string($result) || $result->num_rows === 0) return true;

        $row = $result->fetch_assoc();

        if ($row['block_time'] && strtotime($row['block_time']) > time()) {
            $minutos = ceil((strtotime($row['block_time']) - time()) / 60);
            return "Tu IP está bloqueada. Intenta de nuevo en {$minutos} minutos.";
        }
        return true;
    }

    public function incrementIpAttempt(string $ip, int $lockoutMinutes)
    {
        $MAX_ATTEMPTS = 3;
        $sql_upd = "UPDATE failing_attempts_ip SET failed_attempts = failed_attempts + 1 WHERE ip_address = ?";
        $affected = $this->runDmlStatement($sql_upd, "s", $ip);

        if ($affected === 0) {
            $this->runDmlStatement("INSERT INTO failing_attempts_ip (ip_address, failed_attempts) VALUES (?, 1)", "s", $ip);
        }

        $sql_check = "SELECT failed_attempts FROM failing_attempts_ip WHERE ip_address = ?";
        $res = $this->runSelectStatement($sql_check, "s", $ip);
        
        if (!is_string($res) && $res->num_rows > 0) {
            $attempts = $res->fetch_assoc()['failed_attempts'];
            if ($attempts >= $MAX_ATTEMPTS) {
                $lockout = date('Y-m-d H:i:s', strtotime("+{$lockoutMinutes} minutes"));
                $this->runDmlStatement("UPDATE failing_attempts_ip SET block_time = ? WHERE ip_address = ?", "ss", $lockout, $ip);
            }
        }
    }

    public function clearIpAttempts(string $ip)
    {
        $this->runDmlStatement("DELETE FROM failing_attempts_ip WHERE ip_address = ?", "s", $ip);
    }
}