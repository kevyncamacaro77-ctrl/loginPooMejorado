<?php
// src/php/models/SettingsModel.php
require_once 'DbModel.php';

class SettingsModel extends DbModel
{
    public function __construct(mysqli $connection)
    {
        parent::__construct($connection);
    }

    public function getThemeColors(): array
    {
        $sql = "SELECT primary_color, secondary_color, text_color, bg_color, card_color FROM site_settings WHERE id = 1";
        $result = $this->runSelectStatement($sql, "");
        
        if (is_string($result) || $result->num_rows === 0) {
            return [
                'primary_color' => '#007bff',
                'secondary_color' => '#2c3e50',
                'text_color' => '#333333',
                'bg_color' => '#f8f9fa',
                'card_color' => '#ffffff' 
            ];
        }
        return $result->fetch_assoc();
    }

    public function updateThemeColors($primary, $secondary, $text, $bg, $card): bool|string
    {
        // 1. Primero intentamos ACTUALIZAR
        $sql = "UPDATE site_settings SET primary_color = ?, secondary_color = ?, text_color = ?, bg_color = ?, card_color = ? WHERE id = 1";
        $result = $this->runDmlStatement($sql, "sssss", $primary, $secondary, $text, $bg, $card);
        
        if (is_string($result)) {
            return "Error al guardar colores: " . $result;
        }

        // 2. Si affected_rows es 0, puede ser que no cambiamos nada O que la fila NO EXISTE.
        // Verificamos si existe la fila id=1
        $check = $this->runSelectStatement("SELECT id FROM site_settings WHERE id = 1", "");
        
        if ($check && $check->num_rows === 0) {
            // LA FILA NO EXISTE: Insertamos una nueva
            $sql_insert = "INSERT INTO site_settings (id, primary_color, secondary_color, text_color, bg_color, card_color) VALUES (1, ?, ?, ?, ?, ?)";
            $insert_res = $this->runDmlStatement($sql_insert, "sssss", $primary, $secondary, $text, $bg, $card);
            
            if (is_string($insert_res)) return "Error al crear configuración: " . $insert_res;
        }
        
        return true;
    }
}