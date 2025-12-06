<?php
// src/php/models/DbModel.php

class DbModel
{
    protected $conn;

    public function __construct(mysqli $connection)
    {
        $this->conn = $connection;
    }

    protected function runSelectStatement(string $sql, string $types, ...$params): mysqli_result|string|null
    {
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) return "Error prepare: " . $this->conn->error;

        if (!empty($types) && !empty($params)) {
            if (!$stmt->bind_param($types, ...$params)) {
                $stmt->close();
                return "Error bind: " . $stmt->error;
            }
        }
        
        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            return "Error execute: " . $err;
        }

        $result = $stmt->get_result();
        $stmt->close();
        return $result;
    }

    // En src/php/models/DbModel.php

    protected function runDmlStatement(string $sql, string $types, ...$params): int|string
    {
    $stmt = $this->conn->prepare($sql);
    if ($stmt === false) return "Error prepare: " . $this->conn->error;

    if (!empty($types) && !empty($params)) {
        if (!$stmt->bind_param($types, ...$params)) {
            $stmt->close();
            return "Error bind: " . $stmt->error;
        }
    }

      if ($stmt->execute()) {
        // *** ESTA ES LA CORRECCIÓN CLAVE ***
        // 1. Verificar si fue una INSERCIÓN
        if (str_starts_with(strtoupper(trim($sql)), 'INSERT')) {
            // Devolver el ID generado por MySQL
            $insertId = $this->conn->insert_id;
            $stmt->close();
            // Si $insertId es 0, algo salió mal, pero normalmente será el ID
            return $insertId; 
        }

        // 2. Si es UPDATE o DELETE, devolver las filas afectadas
        $filas = $stmt->affected_rows; // Usamos $stmt->affected_rows para operaciones DML
        $stmt->close();
        return $filas; // Devolver INT
    }

    // Manejo de errores si execute falla
    $err = $this->conn->error;
    $code = $this->conn->errno;
    $stmt->close();

    if ($code === 1062) return 1062;
    return "Error execute: " . $err;
}

}