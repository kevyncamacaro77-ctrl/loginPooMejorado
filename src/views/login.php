<?php
session_start();
if (isset($_SESSION['user_id'])) {
    // Si el usuario ya está logueado, redirigir al dashboard o página principal
    header("Location: dashboard.php");
    exit;
}

$message = null;

// 1. Manejar el error de Login (si viene del controlador)
if (isset($_SESSION['error_login'])) {
  $message = '<div class="error-message" style="color: red;">' . $_SESSION['error_login'] . '</div>';
  unset($_SESSION['error_login']);
}
// 2. Manejar el mensaje de Éxito de Registro (si viene de la URL)
elseif (isset($_GET['register']) && $_GET['register'] === 'success') {
  $message = '<div class="success-message" style="color: green;">¡Registro exitoso! Por favor, inicie sesión.</div>';
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="../styles/css/globalStyles.css">
  <!-- <link rel="stylesheet" href="../styles/css/formDefaultStyles.css"> -->
  <link rel="stylesheet" href="../styles/css/auth-forms.css">
  <!-- <link rel="stylesheet" href="../styles/css/formBackgrouns.css"> -->
  <title>Login</title>
</head>

<body>
  <div class="form-container">
    <img src="../images/Lubriken-log-o-type.png" alt="Logotipo Lubriken" class="logo">

    <h2>Iniciar Sesión</h2>

    <?php
    // Mostrar mensaje de error si existe
    if ($message) {
      echo $message;
    }
    ?>

    <form action="../php/controllers/UserController.php" method="POST">
      <input type="hidden" name="action" value="login">
      <div class="form-group">
        <label for="email">Correo</label>
        <input type="email" id="email" name="email" required>
      </div>

      <div class="form-group">
        <label for="password">Contraseña</label>
        <input type="password" id="password" name="password" required>
      </div>

      <button type="submit" class="submit-btn">Ingresar</button>
    </form>

    <div class="toggle-link">
      <p>¿No tienes una cuenta? <a href="register.php">Regístrate aquí</a></p>
    </div>
  </div>
</body>

</html>