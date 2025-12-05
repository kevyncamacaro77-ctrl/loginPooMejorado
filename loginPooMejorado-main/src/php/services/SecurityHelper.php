<?php
// src/php/services/SecurityHelper.php

class SecurityHelper
{
    /**
     * Genera un token CSRF si no existe y lo guarda en la sesión.
     * @return string El token CSRF.
     */
    public static function getCsrfToken(): string
    {
        // Asegurarse de que la sesión esté iniciada
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Si no existe el token, crear uno nuevo criptográficamente seguro
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    /**
     * Verifica si el token recibido coincide con el de la sesión.
     * @param string $token El token enviado por el formulario (POST).
     * @return bool True si es válido, False si no.
     */
    public static function verifyCsrfToken(?string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }

        // hash_equals es resistente a ataques de tiempo (timing attacks)
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}