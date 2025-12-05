<?php
class UserValidator
{
    private $userModel;

    // Inyección de Dependencia: Recibe el Modelo User para validaciones de DB (ej. emailExists)
    public function __construct(UserModel $userModel)
    {
        $this->userModel = $userModel;
    }

    /**
     * Valida los datos de un nuevo registro.
     * @param array $data (asumimos que viene de $_POST)
     * @return array Array de errores. Vacío si es válido.
     */
    public function validateRegistration(array $data): array
    {
        $errors = [];

        // 1. Recolección de datos
        $nombre = filter_input(INPUT_POST, 'nombre', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $password_original = $data['password'] ?? '';
        $password_confirm = $data['confirmPassword'] ?? '';

        // 2. Validaciones de NOMBRE
        if (empty($nombre))
            $errors[] = "Error: El nombre no puede estar vacío.";
        if (strlen($nombre) < 3 || strlen($nombre) > 64)
            $errors[] = "Error: El nombre debe tener entre 3 y 64 caracteres.";

        // 3. Validaciones de EMAIL
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Error: Formato de email inválido.";
        } else {
            if (strlen($email) > 254)
                $errors[] = "Error: El correo electrónico es demasiado largo.";
            // Validación de existencia con el modelo inyectado
            if ($this->userModel->emailExists($email))
                $errors[] = "Error: El correo electrónico ya está registrado.";
        }

        // 4. Validaciones de CONTRASEÑA
        if (strlen($password_original) < 6)
            $errors[] = "Error: La contraseña debe tener al menos 6 caracteres.";
        if ($password_original !== $password_confirm)
            $errors[] = "Error: Las contraseñas no coinciden.";

        return $errors;
    }

    /**
     * Valida los datos para la actualización de un perfil (solo email y nombre).
     * @param string $id ID del usuario actual.
     * @param array $data (asumimos que viene de $_POST)
     * @return array Array de errores. Vacío si es válido.
     */
    // En src/php/services/UserValidator.php

    // MANTENEMOS la estructura de la función, pero la hacemos más estricta con la validación de contraseña.
    public function validateUpdate(array $data): array
    {
        $errors = [];

        $nombre = $data['nombre'] ?? '';
        $currentPassword = $data['currentPassword'] ?? '';
        $password_original = $data['password'] ?? '';
        $password_confirm = $data['confirmPassword'] ?? '';

        // 1. Validar que se envió la contraseña actual
        if (empty($currentPassword)) {
            $errors[] = "Error: Debes ingresar tu contraseña actual para guardar cambios.";
        }

        // 2. Validación de nombre
        if (empty($nombre)) {
            $errors[] = "Error: El nombre no puede estar vacío.";
        }
        if (strlen($nombre) < 3 || strlen($nombre) > 64)
            $errors[] = "Error: El nombre debe tener entre 3 y 64 caracteres.";

        // 3. Validación de nueva contraseña (Opcional)
        if (!empty($password_original)) {
            if (strlen($password_original) < 6)
                $errors[] = "Error: La nueva contraseña debe tener al menos 6 caracteres.";

            if ($password_original !== $password_confirm)
                $errors[] = "Error: Las contraseñas nuevas no coinciden.";
        }

        return $errors;
    }
}