<?php
// src/php/controllers/UserController.php
session_start();

// Carga centralizada de dependencias (Database, DbModel, UserModel, UserValidator, SecurityHelper)
require_once '../../../src/php/requires_central.php';

class UserController
{
    private $userModel;
    private $validator;

    // Inyección de Dependencia: Recibe UserModel
    public function __construct(UserModel $userModel)
    {
        $this->userModel = $userModel;
        $this->validator = new UserValidator($userModel);
    }

    // ----------------------------------------------------
    // 1. REGISTRO DE USUARIO
    // ----------------------------------------------------
    // ----------------------------------------------------
    // 1. REGISTRO DE USUARIO (CORREGIDO CON PROTECCIÓN)
    // ----------------------------------------------------
    // En src/php/controllers/UserController.php

    // ----------------------------------------------------
    // 1. REGISTRO DE USUARIO
    // ----------------------------------------------------
    private function handleRegister()
    {
        // --- 🛡️ 1. PROTECCIÓN ANTI-SPAM / BLOQUEO POR IP ---
        $ip = $_SERVER['REMOTE_ADDR'];
        // Tiempo de castigo para registro (ej. 60 minutos para evitar spam masivo)
        $LOCKOUT_MINUTES = 5;

        // Verificamos si la IP ya está bloqueada antes de procesar nada
        $lock_status = $this->userModel->checkAndProcessIpBlock($ip);

        if (is_string($lock_status)) {
            // Si está bloqueado, guardamos el mensaje y redirigimos
            $_SESSION['errors'] = [$lock_status];
            header("Location: ../../views/register.php");
            exit;
        }

        // --- 2. VALIDACIÓN DE DATOS ---
        // Delegamos la validación al servicio UserValidator
        $errors = $this->validator->validateRegistration($_POST);

        // Recolectar datos sanitizados
        $nombre = filter_input(INPUT_POST, 'nombre', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $password_original = $_POST['password'] ?? '';

        // --- 3. SI HAY ERRORES DE VALIDACIÓN ---
        if (!empty($errors)) {
            // 🛡️ CASTIGO: Incrementamos el contador de fallos de la IP.
            // Si un bot envía datos inválidos muchas veces, será bloqueado.
            $this->userModel->incrementIpAttempt($ip, $LOCKOUT_MINUTES);

            $_SESSION['errors'] = $errors;
            $_SESSION['form_data'] = ['nombre' => $nombre, 'email' => $email];
            header("Location: ../../views/register.php");
            exit;
        }

        // --- 4. INTENTO DE REGISTRO EN BASE DE DATOS ---
        $result = $this->userModel->register($nombre, $email, $password_original);

        if ($result === true) {
            // ✅ ÉXITO: Limpiamos el historial de fallos de esta IP
            $this->userModel->clearIpAttempts($ip);

            header("Location: ../../views/login.php?register=success");
            exit;
        }

        // --- 5. SI FALLA LA DB (Ej. Email duplicado) ---
        // 🛡️ CASTIGO: También contamos esto como un intento fallido.
        // Esto evita que alguien use el registro para enumerar correos existentes masivamente.
        $this->userModel->incrementIpAttempt($ip, $LOCKOUT_MINUTES);

        $errors[] = $result;
        $_SESSION['errors'] = $errors;
        $_SESSION['form_data'] = ['nombre' => $nombre, 'email' => $email];
        header("Location: ../../views/register.php");
        exit;
    }

    // ----------------------------------------------------
    // 2. INICIO DE SESIÓN (LOGIN)
    // ----------------------------------------------------
    private function handleLogin()
    {
        $ip = $_SERVER['REMOTE_ADDR'];
        $LOCKOUT_MINUTES = 5;

        // 1. Verificar Bloqueo por IP (Anti-Fuerza Bruta)
        $lock_status = $this->userModel->checkAndProcessIpBlock($ip);

        if (is_string($lock_status)) {
            $_SESSION['error_login'] = $lock_status;
            header("Location: ../../views/login.php");
            exit;
        }

        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        // 2. Intentar Login
        $loginData = $this->userModel->login($email, $password);

        if ($loginData !== false) {
            // ÉXITO: Limpiar intentos fallidos y crear sesión
            $this->userModel->clearIpAttempts($ip);

            // Regenerar ID de sesión para prevenir Session Fixation
            session_regenerate_id(true);

            $_SESSION['usuario'] = $loginData['usuario'];
            $_SESSION['user_id'] = $loginData['user_id'];
            $_SESSION['user_rol'] = $loginData['rol'];

            header("Location: ../../views/dashboard.php");
            exit;
        }

        // FRACASO: Registrar intento fallido
        $this->userModel->incrementIpAttempt($ip, $LOCKOUT_MINUTES);

        $_SESSION['error_login'] = "Correo o contraseña incorrectos.";
        header("Location: ../../views/login.php");
        exit;
    }

    // ----------------------------------------------------
    // 3. CERRAR SESIÓN (LOGOUT)
    // ----------------------------------------------------
    private function handleLogout()
    {
        // Verificación CSRF recomendada para Logout
        $token = $_POST['csrf_token'] ?? '';
        if (!SecurityHelper::verifyCsrfToken($token)) {
            // Si el token falla, igual cerramos sesión por seguridad, 
            // pero podríamos loguear el incidente.
        }

        unset($_SESSION['user_id']);
        unset($_SESSION['usuario']);
        unset($_SESSION['user_rol']);
        $_SESSION = [];

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        session_destroy();
        header("Location: ../../views/login.php");
        exit;
    }

    // ----------------------------------------------------
    // 4. ACTUALIZAR PERFIL (NOMBRE Y CONTRASEÑA)
    // ----------------------------------------------------
    private function handleUpdate()
    {
        // 1. Guardia de sesión
        if (!isset($_SESSION['user_id'])) {
            $_SESSION['error_login'] = "Acceso denegado.";
            header("Location: ../../views/login.php");
            exit;
        }

        $ip = $_SERVER['REMOTE_ADDR'];
        $LOCKOUT_MINUTES = 5;

        // --- 🛡️ NUEVO: VERIFICAR BLOQUEO POR IP ---
        // Si esta IP ya está castigada (por intentos fallidos de login o perfil), no la dejamos pasar.
        $lock_status = $this->userModel->checkAndProcessIpBlock($ip);
        if (is_string($lock_status)) {
            // Cerramos sesión por seguridad si está bloqueado y redirigimos
            session_destroy();
            session_start();
            $_SESSION['error_login'] = $lock_status; // "Tu IP está bloqueada..."
            header("Location: ../../views/login.php");
            exit;
        }

        // 2. Guardia CSRF
        $token = $_POST['csrf_token'] ?? '';
        if (!SecurityHelper::verifyCsrfToken($token)) {
            $_SESSION['update_error'] = "Error de seguridad: Token inválido (CSRF).";
            header("Location: ../../views/userdata.php");
            exit;
        }

        $id = $_SESSION['user_id'];

        // 3. Validación de campos
        $errors = $this->validator->validateUpdate($_POST);

        $nombre = $_POST['nombre'] ?? '';
        $currentPassword = $_POST['currentPassword'] ?? '';
        $password_new = $_POST['password'] ?? '';

        // 4. VERIFICACIÓN DE SEGURIDAD: Contraseña Actual
        if (empty($errors)) {
            if (!$this->userModel->verifyCurrentPassword($id, $currentPassword)) {
                $errors[] = "Error: La contraseña actual es incorrecta.";

                // --- 🛡️ NUEVO: PENALIZAR IP POR FALLO DE SEGURIDAD ---
                // Si fallan la contraseña actual, cuenta como intento de fuerza bruta.
                $this->userModel->incrementIpAttempt($ip, $LOCKOUT_MINUTES);
            } else {
                // Si la contraseña es correcta, limpiamos los "pecados" de la IP
                // (Opcional: puedes no limpiarlo para ser más estricto, pero limpiarlo es más amigable)
                $this->userModel->clearIpAttempts($ip);
            }
        }

        // 5. Manejo de Errores
        if (!empty($errors)) {
            $_SESSION['update_error'] = implode(' ', $errors);
            header("Location: ../../views/userdata.php");
            exit;
        }

        // 6. Ejecutar Actualización
        $result = $this->userModel->updateProfile(
            $id,
            $nombre,
            empty($password_new) ? null : $password_new
        );

        if ($result === true) {
            $_SESSION['usuario'] = $nombre;
            header("Location: ../../views/userdata.php?update=success");
            exit;
        }

        $_SESSION['update_error'] = $result;
        header("Location: ../../views/userdata.php");
        exit;
    }

    // ----------------------------------------------------
    // ENRUTADOR
    // ----------------------------------------------------
    public function routeAction($action)
    {
        switch ($action) {
            case "register":
                $this->handleRegister();
                break;
            case "login":
                $this->handleLogin();
                break;
            case "logout":
                $this->handleLogout();
                break;
            case "update":
                $this->handleUpdate();
                break;
            default:
                $_SESSION['error_login'] = "Acción no reconocida.";
                header("Location: ../../views/login.php");
                exit;
        }
    }
}

// ----------------------------------------------------
// BOOTSTRAP DEL CONTROLADOR
// ----------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    require_once '../../../src/php/requires_central.php';

    $db = new Database();
    $connection = $db->getConnection();

    // Instanciar UserModel (renombrado)
    $userModel = new UserModel($connection);

    $controller = new UserController($userModel);

    $action = $_POST["action"] ?? '';
    $controller->routeAction($action);
}