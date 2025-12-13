<?php
class Database
{
    private $host = "localhost";
    private $user = "root";
    private $pass = "";
    private $dbName = "login_db";
    private $conn;

    public function __construct()
    {
        // Crea la conexión en el constructor
        $this->conn = new mysqli($this->host, $this->user, $this->pass, $this->dbName);

        // Verifica la conexión
        if ($this->conn->connect_error)
            die("Conexión Fallida: " . $this->conn->connect_error);
    }

    public function __destruct()
    {
        if ($this->conn && !$this->conn->connect_errno) {
            $this->conn->close();
        }
    }

    // Método para obtener la conexión y usarla en otras clases
    public function getConnection()
    {
        return $this->conn;
    }
}