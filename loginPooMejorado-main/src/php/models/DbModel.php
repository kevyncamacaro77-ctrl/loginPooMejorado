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
            // CORRECCIÓN CRÍTICA AQUÍ:
            $filas = $this->conn->affected_rows; // Capturar número de filas
            $stmt->close();
            return $filas; // Devolver INT, no TRUE
        }

        $err = $this->conn->error;
        $code = $this->conn->errno;
        $stmt->close();

        if ($code === 1062) return 1062;
        return "Error execute: " . $err;
    }
}