<?php
// src/views/dashboard.php 
session_start();

// 1. INCLUSIÓN DE DEPENDENCIAS
require_once '../php/requires_central.php';

// 2. SETUP DE CONEXIÓN E INYECCIÓN
$db = new Database();
$connection = $db->getConnection();

$productModel = new ProductModel($connection);
$cartModel = new CartModel($connection);

// 3. OBTENER PRODUCTOS DEL CATÁLOGO
$products = $productModel->getAllProducts();
$product_error_message = is_string($products) ? $products : null;
if (is_string($products))
    $products = [];

// 4. VARIABLES DE SESIÓN Y ROL
$nombre_usuario = $_SESSION['usuario'] ?? 'Invitado';
$user_logged_in = isset($_SESSION['user_id']);
$id_usuario = $_SESSION['user_id'] ?? null;
$user_rol = $_SESSION['user_rol'] ?? 'cliente';

// 5. MANEJO DE MENSAJES DE SESIÓN (Errores/Éxitos)
$update_error_message = $_SESSION['update_error'] ?? null;
$cart_success_message = $_SESSION['cart_success'] ?? null;
$cart_error_message = $_SESSION['cart_error'] ?? null;

// Limpiar mensajes después de leerlos
unset($_SESSION['update_error'], $_SESSION['cart_success'], $_SESSION['cart_error']);

// 6. LÓGICA DE CARRITO (Visualización en el Header)
$total_items_in_cart = 0;
$cart_items = []; // Inicializar array para el dropdown

if ($user_logged_in && $user_rol !== 'administrador') {
    $cart_result = $cartModel->viewCart($id_usuario);
    if (is_array($cart_result)) {
        $cart_items = $cart_result; // Guardamos los ítems para el dropdown
        $total_items_in_cart = array_sum(array_column($cart_result, 'cantidad'));
    }
}

// 7. OBTENER TOKEN CSRF
$csrf_token = SecurityHelper::getCsrfToken();
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="../styles/css/globalStyles.css" />
    <link rel="stylesheet" href="../styles/css/dark-mode.css">
    <link rel="stylesheet" href="../styles/css/userMenuHeader.css">
    <link rel="stylesheet" href="../styles/css/product.css">
    <link rel="stylesheet" href="../styles/css/testimonials.css">
    <link rel="stylesheet" id="sobrenosotros-style" href="../styles/css/section-sobrenosotros.css">
    <link rel="stylesheet" href="../styles/css/preguntas.css">
    <link rel="stylesheet" href="../styles/css/ContactForm.css">
    <title>Dashboard - Lubriken</title>
</head>

<body>
    <header id="navigation-bar">
        <section id="desktop-navbar">
            <img src="../images/lubriken-log-o-type.png" alt="logotype" />
            <nav class="desktop-menu">
                <ul>
                    <li><a href="dashboard.php#">Inicio</a></li>
                    <li><a href="dashboard.php#productos">Nuestros Productos</a></li>
                    <li><a href="dashboard.php#testimonios">testimonial de pasantias</a></li>
                    <li><a href="dashboard.php#preguntas">FAQs</a></li>
                    <li><a href="dashboard.php#formulario-contacto">Contacto</a></li>

                    <?php if ($user_logged_in && $user_rol !== 'administrador'): ?>
                        <li class="cart-icon-container">
                            <a href="userdata.php?tab=cart" class="cart-link-main">
                                <span>🛒 Carrito</span>
                                <?php if ($total_items_in_cart > 0): ?>
                                    <span class="cart-badge"><?php echo $total_items_in_cart; ?></span>
                                <?php endif; ?>
                            </a>

                            <?php if (!empty($cart_items)): ?>
                                <div class="shein-dropdown">
                                    <ul class="shein-list">
                                        <?php foreach ($cart_items as $item): ?>
                                            <li class="shein-item">
                                                <div class="shein-img-wrapper">
                                                    <img src="<?php echo htmlspecialchars($item['imagen_url']); ?>" alt="Producto"
                                                        style="width: 70px; height: 90px; object-fit: cover; border-radius: 4px; display: block;">
                                                </div>
                                                <div class="shein-info">
                                                    <span class="shein-name"><?php echo htmlspecialchars($item['nombre']); ?></span>
                                                    <span style="font-size: 0.8rem; color: #888;">Cant: <?php echo $item['cantidad']; ?></span>
                                                    <span class="shein-price">$<?php echo number_format($item['precio'], 2); ?></span>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <div class="shein-footer">
                                        <div class="shein-total-row">
                                            <span>Total:</span>
                                            <span style="color: #fa6338;">
                                                $<?php echo number_format(array_sum(array_column($cart_items, 'subtotal')), 2); ?>
                                            </span>
                                        </div>
                                        <a href="userdata.php?tab=cart" class="shein-btn-checkout">VER BOLSA</a>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </li>
                    <?php endif; ?>

                    <?php if ($user_logged_in): ?>
                        <li class="user-menu-item">
                            <a href="userdata.php?tab=profile" id="user-name-link"><?php echo $nombre_usuario; ?>
                                (<?php echo ucfirst($user_rol); ?>)</a>
                        </li>
                    <?php else: ?>
                        <li><a href="./login.php">Iniciar Sesión</a></li>
                    <?php endif; ?>
                    <li>
                        <button id="theme-toggle-desktop" class="theme-btn" title="Cambiar tema">🌙</button>
                    </li>
                </ul>
            </nav>
        </section>

        <nav id="mobile-menu">
            <ul>
                <li><a href="dashboard.php#">Inicio</a></li>
                <li><a href="dashboard.php#productos">Nuestros Productos</a></li>
                <li><a href="dashboard.php#testimonios">testimonial de pasantias</a></li>
                <li><a href="dashboard.php#preguntas">FAQs</a></li>
                <li><a href="dashboard.php#formulario-contacto">Contacto</a></li>

                <?php if ($user_logged_in && $user_rol !== 'administrador'): ?>
                    <li class="cart-icon-container mobile-cart-trigger">
                        <a href="userdata.php?tab=cart" class="cart-link-main">
                            <span>🛒 Carrito</span>
                            <?php if ($total_items_in_cart > 0): ?>
                                <span class="cart-badge"><?php echo $total_items_in_cart; ?></span>
                            <?php endif; ?>
                        </a>

                        <?php if (!empty($cart_items)): ?>
                            <div class="shein-dropdown mobile-dropdown-content">
                                <ul class="shein-list">
                                    <?php foreach ($cart_items as $item): ?>
                                        <li class="shein-item">
                                            <div class="shein-img-wrapper">
                                                <img src="<?php echo htmlspecialchars($item['imagen_url']); ?>" alt="Producto"
                                                    style="width: 70px; height: 90px; object-fit: cover; border-radius: 4px; display: block;">
                                            </div>
                                            <div class="shein-info">
                                                <span class="shein-name"><?php echo htmlspecialchars($item['nombre']); ?></span>
                                                <span style="font-size: 0.8rem; color: #888;">Cant: <?php echo $item['cantidad']; ?></span>
                                                <span class="shein-price">$<?php echo number_format($item['precio'], 2); ?></span>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                                <div class="shein-footer">
                                    <div class="shein-total-row">
                                        <span>Total:</span>
                                        <span style="color: #fa6338;">
                                            $<?php echo number_format(array_sum(array_column($cart_items, 'subtotal')), 2); ?>
                                        </span>
                                    </div>
                                    <a href="userdata.php?tab=cart" class="shein-btn-checkout">VER BOLSA</a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </li>
                <?php endif; ?>

                <?php if (isset($_SESSION['usuario'])): ?>
                    <li class="user-menu-item">
                        <a href="userdata.php?tab=profile" id="user-name-link"><?php echo $nombre_usuario; ?></a>
                    </li>
                <?php else: ?>
                    <li><a href="./login.php">Iniciar Sesión</a></li>
                <?php endif; ?>
                <li>
                    <button id="theme-toggle-mobile" class="theme-btn" title="Cambiar tema">🌙</button>
                </li>
            </ul>
        </nav>

        <button id="mobile-menu-btn">☰</button>
    </header>
    <main>
        <section id="sobrenosotros-view" class="content-view content-container sobrenosotros" style="display:none;">
            <h1 class="page-title">Conoce a Lubriken</h1>
            <hr>
            <section class="container-information">
                <section class="mission-history">
                    <section class="mission">
                        <h2>Nuestra Misión </h2>
                        <p>En Lubriken, nuestra misión es simplificar el mantenimiento y la protección de tus activos, ofreciendo
                            lubricantes y productos químicos de la más alta calidad.</p>
                    </section>

                    <section class="history">
                        <h2>Nuestra Historia </h2>
                        <p>Fundada en 2020, Lubriken nació de la necesidad de un servicio especializado y una entrega eficiente en el
                            sector industrial.</p>
                    </section>
                </section><!--.mission-history-->

                <section class="values">
                    <h2>Nuestros Valores </h2>
                    <ul>
                        <li><strong>Calidad:</strong> Productos certificados y probados.</li>
                        <li><strong>Compromiso:</strong> Entrega rápida y atención al cliente.</li>
                        <li><strong>Innovación:</strong> Soluciones constantes.</li>
                    </ul>
                </section>
            </section>

        </section><!--Final del de Sobre Nosotros-->
    </main>
    <footer>
        <section class="footer-content">
            <p>&copy; 2025 Lubriken. Todos los derechos reservados.</p>
            <p>Barquisimeto, Edo. Lara, Venezuela | Contacto: XXXX-XXXXXXX</p>
        </section>
    </footer>

    <script src="../js/header-component.js"></script>
    <script src="../js/theme.js"></script>
</body>

</html>