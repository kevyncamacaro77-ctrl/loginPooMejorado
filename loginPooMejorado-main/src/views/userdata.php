<?php
// src/views/userdata.php
session_start();

// 1. CARGA DE DEPENDENCIAS
require_once '../php/requires_central.php';
require_once '../php/models/SettingsModel.php';
require_once '../php/services/ThemeHelper.php';

// 2. GUARDIA DE SEGURIDAD
if (!isset($_SESSION['user_id'])) {
    header("Location: ../views/login.php");
    exit;
}

$id_usuario = $_SESSION['user_id'];
$user_rol = $_SESSION['user_rol'] ?? 'cliente';

// 3. SETUP Y OBTENCIÓN DE DATOS
$db = new Database();
$connection = $db->getConnection();

$userModel = new UserModel($connection);
$orderModel = new OrderModel($connection);
$cartModel = new CartModel($connection);

// --- LÓGICA DE TEMA (Optimizado) ---
$currentColors = []; // Valor por defecto seguro
if ($user_rol === 'administrador') {
    $settingsModel = new SettingsModel($connection);
    $currentColors = $settingsModel->getThemeColors();
}

// A) Datos de Perfil
$datos_usuario = $userModel->getUserDataById($id_usuario);
if (!$datos_usuario) {
    session_destroy();
    header("Location: ../views/login.php?error=data_load");
    exit;
}
$nombre_actual = htmlspecialchars($datos_usuario['nombre']);
$email_actual = htmlspecialchars($datos_usuario['email']);

// B) Datos de Pedidos
$orders = [];
if ($user_rol === 'administrador') {
    $orders = $orderModel->getPendingOrders();
} else {
    if (method_exists($orderModel, 'getOrdersByUserId')) {
        $orders = $orderModel->getOrdersByUserId($id_usuario);
    }
}
if (is_string($orders)) $orders = [];

// C) Datos del Carrito
$cart_items = [];
$cart_total = 0;
if ($user_rol !== 'administrador') {
    $cart_result = $cartModel->viewCart($id_usuario);
    if (is_array($cart_result)) {
        $cart_items = $cart_result;
        foreach ($cart_items as $item) {
            $cart_total += $item['subtotal'];
        }
    }
}

// 4. SEGURIDAD CSRF
$csrf_token = SecurityHelper::getCsrfToken();

// 5. MENSAJES FLASH
$update_error_message = $_SESSION['update_error'] ?? null;
$success_message = isset($_GET['update']) && $_GET['update'] == 'success' ? "¡Perfil actualizado!" : ($_SESSION['cart_success'] ?? null);
$cart_error = $_SESSION['cart_error'] ?? null;
$admin_msg = $_SESSION['admin_msg'] ?? null;
$admin_error = $_SESSION['admin_error'] ?? null;

unset($_SESSION['update_error'], $_SESSION['cart_success'], $_SESSION['cart_error'], $_SESSION['admin_msg'], $_SESSION['admin_error']);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Usuario - Lubriken</title>

    <link rel="stylesheet" href="../styles/css/globalStyles.css">
    <link rel="stylesheet" href="../styles/css/userdatastyles.css"> 
    
    <?php ThemeHelper::renderThemeStyles($connection); ?>
</head>

<body>

    <div class="dashboard-container">

        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h3>Panel de <?php echo ucfirst($user_rol); ?></h3>
                <button class="menu-toggle" id="sidebar-toggle">☰</button>
            </div>

            <nav>
                <ul>
                    <li><a href="dashboard.php">🏠 Volver al Catálogo</a></li>
                    <li><a href="#" class="tab-link active" data-tab="profile" id="link-profile">⚙️ Configuración</a></li>

                    <?php if ($user_rol === 'administrador'): ?>
                        <li><a href="#" class="tab-link" data-tab="orders" id="link-orders">📋 Manejar Pedidos</a></li>
                        <li><a href="#" class="tab-link" data-tab="theme" id="link-theme">🎨 Personalizar Tema</a></li>
                    <?php else: ?>
                        <li>
                            <a href="#" class="tab-link" data-tab="cart" id="link-cart">
                                🛒 Tu Carrito <?php echo (count($cart_items) > 0) ? '(' . count($cart_items) . ')' : ''; ?>
                            </a>
                        </li>
                        <li><a href="#" class="tab-link" data-tab="orders" id="link-orders">📦 Historial Pedidos</a></li>
                    <?php endif; ?>
                </ul>
            </nav>

            <form action="../php/controllers/UserController.php" method="POST" class="logout-form">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="logout">
                <button type="submit" class="logout-btn">Cerrar Sesión</button>
            </form>
        </aside>

        <main class="main-content">

            <?php if ($cart_error): ?><div class="alert alert-error"><?php echo htmlspecialchars($cart_error); ?></div><?php endif; ?>
            <?php if ($success_message): ?><div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div><?php endif; ?>
            <?php if ($admin_msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($admin_msg); ?></div><?php endif; ?>
            <?php if ($admin_error): ?><div class="alert alert-error"><?php echo htmlspecialchars($admin_error); ?></div><?php endif; ?>

            <div id="section-profile" class="tab-section active">
                <div class="profile-card">
                    <h2 style="margin-top:0; color:var(--secondary-color);">Mi Perfil</h2>
                    <hr style="border:0; border-top:1px solid #ddd; margin-bottom:20px;">

                    <?php if ($update_error_message): ?>
                        <div class="alert alert-error"><strong>Error:</strong> <?php echo htmlspecialchars($update_error_message); ?></div>
                    <?php endif; ?>

                    <form action="../php/controllers/UserController.php" method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="action" value="update">

                        <div class="form-group">
                            <label>Correo Electrónico</label>
                            <input type="text" class="form-control" value="<?php echo $email_actual; ?>" disabled>
                            <small style="color:#95a5a6; display:block; margin-top:5px;">El correo no se puede modificar.</small>
                        </div>
                        <div class="form-group">
                            <label for="nombre">Nombre de Usuario</label>
                            <input type="text" id="nombre" name="nombre" class="form-control" value="<?php echo $nombre_actual; ?>" required>
                        </div>
                        <div style="background:#fff3cd; padding:15px; border-radius:5px; border:1px solid #ffeeba; margin-bottom:20px;">
                            <label style="display:block; font-weight:600; color:#856404; margin-bottom:5px;">Contraseña Actual (Requerido)</label>
                            <input type="password" name="currentPassword" class="form-control" required>
                        </div>
                        <div style="margin-bottom:15px;">
                            <label style="display:block; font-weight:600;">Nueva Contraseña (Opcional)</label>
                            <input type="password" name="password" class="form-control">
                        </div>
                        <div style="margin-bottom:15px;">
                            <label style="display:block; font-weight:600;">Confirmar Nueva Contraseña</label>
                            <input type="password" name="confirmPassword" class="form-control">
                        </div>
                        <button type="submit" class="btn-save">Guardar Cambios</button>
                    </form>
                </div>
            </div>

            <?php if ($user_rol !== 'administrador'): ?>
                <div id="section-cart" class="tab-section">
                    <h2 style="color:var(--secondary-color);">🛒 Tu Carrito de Reservas</h2>
                    <hr style="border:0; border-top:1px solid #ddd; margin-bottom:30px;">

                    <?php if (empty($cart_items)): ?>
                        <div style="text-align:center; padding:40px; background:white; border-radius:8px;">
                            <p style="font-size:1.2rem; color:#7f8c8d;">Tu carrito está vacío.</p>
                            <a href="dashboard.php#productos" class="btn-action btn-primary" style="text-decoration:none; display:inline-block; margin-top:10px; padding:10px 20px;">Ir al Catálogo</a>
                        </div>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Precio</th>
                                    <th>Cantidad</th>
                                    <th>Subtotal</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cart_items as $item): ?>
                                    <tr>
                                        <td>
                                            <div style="display:flex; align-items:center; gap:10px;">
                                                <img src="<?php echo htmlspecialchars($item['imagen_url']); ?>" style="width:50px; height:50px; object-fit:cover; border-radius:4px;">
                                                <strong><?php echo htmlspecialchars($item['nombre']); ?></strong>
                                            </div>
                                        </td>
                                        <td>$<?php echo number_format($item['precio'], 2); ?></td>
                                        <td>
                                            <form action="../php/controllers/ProductController.php" method="POST" style="display:flex; gap:5px;">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                <input type="hidden" name="action" value="update_quantity">
                                                <input type="hidden" name="product_id" value="<?php echo $item['producto_id']; ?>">
                                                <?php $max_qty = $item['cantidad'] + $item['stock_disponible']; ?>
                                                <input type="number" name="new_quantity" value="<?php echo $item['cantidad']; ?>" min="1" max="<?php echo $max_qty; ?>" style="width:60px; padding:5px;">
                                                <button type="submit" class="btn-action btn-primary" title="Actualizar">↻</button>
                                            </form>
                                        </td>
                                        <td>$<?php echo number_format($item['subtotal'], 2); ?></td>
                                        <td>
                                            <form action="../php/controllers/ProductController.php" method="POST">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                <input type="hidden" name="action" value="remove_item">
                                                <input type="hidden" name="product_id" value="<?php echo $item['producto_id']; ?>">
                                                <button type="submit" class="btn-action btn-danger">🗑️</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <div style="text-align:right; margin-top:20px;">
                            <h3 style="margin-bottom:20px;">Total a Pagar: $<?php echo number_format($cart_total, 2); ?></h3>
                            <form action="../php/controllers/OrderController.php" method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="hidden" name="action" value="checkout">
                                <button type="submit" class="btn-action btn-success" style="font-size:1.2rem; padding:12px 30px;">✅ Solicitar Pedido</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div id="section-orders" class="tab-section">
                <h2 style="color:var(--secondary-color); margin-bottom: 20px;">
                    <?php echo ($user_rol === 'administrador') ? 'Gestión de Pedidos Pendientes' : 'Historial de Solicitudes'; ?>
                </h2>
                <hr style="border:0; border-top:1px solid #ddd; margin-bottom:30px;">

                <?php if (empty($orders)): ?>
                    <div style="text-align:center; padding:40px; background:white; border-radius:8px;">
                        <p style="font-size:1.2rem; color:#7f8c8d;">No hay pedidos registrados.</p>
                    </div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>#ID</th>
                                <th>Fecha</th>
                                <?php if ($user_rol === 'administrador'): ?><th>Cliente</th><?php endif; ?>
                                <th>Total</th>
                                <th>Estado</th>
                                <?php if ($user_rol === 'administrador'): ?><th>Acción</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td>#<?php echo $order['id']; ?></td>
                                    <td><?php echo $order['fecha_solicitud']; ?></td>
                                    <?php if ($user_rol === 'administrador'): ?>
                                        <td>
                                            <strong><?php echo htmlspecialchars($order['usuario'] ?? 'N/A'); ?></strong><br>
                                            <small><?php echo htmlspecialchars($order['email'] ?? ''); ?></small>
                                        </td>
                                    <?php endif; ?>
                                    <td>$<?php echo number_format($order['total'], 2); ?></td>
                                    <td>
                                        <span style="color:#f1c40f; font-weight:bold;">
                                            <?php echo isset($order['estado']) ? ucfirst($order['estado']) : 'Desconocido'; ?>
                                        </span>
                                    </td>
                                    <?php if ($user_rol === 'administrador'): ?>
                                        <td style="display:flex; gap:5px;">
                                            <form action="../php/controllers/OrderController.php" method="POST" onsubmit="return confirm('¿Confirmar pago?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                <input type="hidden" name="action" value="confirm_order">
                                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                <button type="submit" class="btn-action btn-success">✅</button>
                                            </form>
                                            <form action="../php/controllers/OrderController.php" method="POST" onsubmit="return confirm('¿Rechazar pedido?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                <input type="hidden" name="action" value="cancel_order">
                                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                <button type="submit" class="btn-action btn-danger">❌</button>
                                            </form>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <?php if ($user_rol === 'administrador'): ?>
                <div id="section-theme" class="tab-section">
                    <div class="profile-card">
                        <h2 style="color: var(--secondary-color);">Personalizar Apariencia</h2>
                        <p>Cambia los colores del sitio en tiempo real.</p>
                        <hr style="border:0; border-top:1px solid #ddd; margin-bottom:20px;">

                        <form action="../php/controllers/SettingsController.php" method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="update_theme">

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                                <div class="form-group">
                                    <label>Color Primario (Botones/Acciones)</label>
                                    <div style="display:flex; align-items:center; gap:10px;">
                                        <input type="color" name="primary_color" value="<?php echo $currentColors['primary_color']; ?>" style="width:50px; height:50px; cursor:pointer; border:none;">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Color Secundario (Sidebar/Títulos)</label>
                                    <div style="display:flex; align-items:center; gap:10px;">
                                        <input type="color" name="secondary_color" value="<?php echo $currentColors['secondary_color']; ?>" style="width:50px; height:50px; cursor:pointer; border:none;">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Color de Texto Principal</label>
                                    <div style="display:flex; align-items:center; gap:10px;">
                                        <input type="color" name="text_color" value="<?php echo $currentColors['text_color']; ?>" style="width:50px; height:50px; cursor:pointer; border:none;">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Color de Fondo (Página)</label>
                                    <div style="display:flex; align-items:center; gap:10px;">
                                        <input type="color" name="bg_color" value="<?php echo $currentColors['bg_color']; ?>" style="width:50px; height:50px; cursor:pointer; border:none;">
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label>Color de Tarjetas (Productos/Cajas)</label>
                                    <div style="display:flex; align-items:center; gap:10px;">
                                        <input type="color" name="card_color" value="<?php echo $currentColors['card_color']; ?>" style="width:50px; height:50px; cursor:pointer; border:none;">
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn-save">Guardar Apariencia</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

        </main>
    </div>

    <script src="../js/userDataControl.js"></script>

</body>
</html>