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
          <li><a href="#">Inicio</a></li>
          <li><a href="nosotros.php">Sobre Nosotros</a></li>
          <li><a href="#productos">Nuestros Productos</a></li>
          <li><a href="#testimonios">Testimonios</a></li>
          <li><a href="#formulario-contacto">Contacto</a></li>

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
        <li><a href="#">Inicio</a></li>
        <li><a href="#sobrenosotros-view">Sobre Nosotros</a></li>
        <li><a href="#productos">Nuestros Productos</a></li>
        <li><a href="#testimonios">Testimonios</a></li>
        <li><a href="#preguntas">FAQs</a></li>
        <li><a href="#formulario-contacto">Contacto</a></li>

        <?php if ($user_logged_in && $user_rol !== 'administrador'): ?>
          <li class="cart-icon-container mobile-cart-trigger">
            <a href="userdata.php?tab=cart" class="cart-link-main">
              <span>🛒 Carrito</span>
              <?php if ($total_items_in_cart > 0): ?>
                <span class="cart-badge"><?php echo $total_items_in_cart; ?></span>
              <?php endif; ?>
            </a>
            
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
    <?php if ($user_logged_in): ?>
      <form action="../php/controllers/UserController.php" method="POST" style="text-align: right; padding: 10px;">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <input type="hidden" name="action" value="logout">
        <button type="submit" class="logout-btn">Cerrar Sesión</button>
      </form>
    <?php endif; ?>

    <?php if ($update_error_message): ?>
      <div class="errorMsg" style="color: red; padding: 10px; text-align:center; border: 1px solid red; margin: 10px;">
        <?php echo htmlspecialchars($update_error_message); ?>
      </div>
    <?php endif; ?>

    <?php if ($cart_success_message): ?>
      <div class="successMsg" style="color: green; padding: 10px; text-align:center; border: 1px solid green; margin: 10px;">
        <?php echo htmlspecialchars($cart_success_message); ?>
      </div>
    <?php endif; ?>

    <?php if ($cart_error_message): ?>
      <div class="errorMsg" style="color: red; padding: 10px; text-align:center; border: 1px solid red; margin: 10px;">
        <?php echo htmlspecialchars($cart_error_message); ?>
      </div>
    <?php endif; ?>


    <section id="productos" class="productos">
      <h2 class="productos-title">Nuestros Productos</h2>

      <?php if ($product_error_message): ?>
        <p class="errorMsg" style="color: red; grid-column: 1 / -1;">
          **Error al cargar los datos del catálogo:** <?php echo htmlspecialchars($product_error_message); ?>
        </p>
      <?php elseif (empty($products)): ?>
        <p style="grid-column: 1 / -1;">Actualmente no hay productos disponibles en el catálogo.</p>
      <?php endif; ?>

      <?php foreach ($products as $product):
        // Usamos stock_disponible calculado en SQL (stock_actual - stock_comprometido)
        $stock = $product['stock_disponible'];
        $status_class = ($stock > 0) ? 'available' : 'sold-out';
        $status_text = ($stock > 0) ? 'DISPONIBLE (' . $stock . ' en stock)' : 'AGOTADO';
      ?>
        <figure>
          <img src="<?php echo htmlspecialchars($product['imagen_url']); ?>"
            alt="<?php echo htmlspecialchars($product['nombre']); ?>" />

          <figcaption><?php echo htmlspecialchars($product['nombre']); ?></figcaption>
          <p><?php echo htmlspecialchars($product['descripcion'] ?? 'Sin descripción.'); ?></p>
          <p>Precio: <strong><?php echo htmlspecialchars($product['precio']); ?>$</strong></p>

         <?php if ($user_rol === 'administrador'): ?>

            <form action="../php/controllers/ProductController.php" method="POST" class="admin-controls">
              <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

              <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">

              <label style="font-size: 0.8rem;">Stock Físico Total:</label>
              <input type="number" name="new_stock" value="<?php echo $product['stock_actual']; ?>" min="0" style="width: 60px;">

              <div style="margin-top: 5px;">
                <button type="submit" name="action" value="update_stock" class="btn admin-btn">Actualizar</button>
                <button type="submit" name="action" value="delete_product" class="btn admin-btn delete-btn" onclick="return confirm('¿Seguro que deseas eliminar este producto?');">Eliminar</button>
              </div>
            </form>

          <?php else: ?>

            <form action="../php/controllers/ProductController.php" method="POST">
              <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

              <input type="hidden" name="action" value="add_to_cart">
              <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">

              <?php if ($user_logged_in): ?>
                <input type="number" name="quantity" value="1" min="1" max="<?php echo $stock; ?>"
                  <?php echo ($stock <= 0) ? 'disabled' : ''; ?> style="width: 50px; text-align: center;">

                <button type="submit" class="btn" <?php echo ($stock <= 0) ? 'disabled' : ''; ?>>
                  Reservar
                </button>
              <?php else: ?>
                <a href="./login.php" class="btn">Reservar</a>
              <?php endif; ?>
            </form>

          <?php endif; ?>
          <h3 class="status <?php echo $status_class; ?>">
            <?php echo $status_text; ?>
          </h3>
        </figure>
      <?php endforeach; ?>

    </section>

    <h2 id="testimonio-pepon">Testimonios de Pasantía</h2>
    <section id="testimonios" class="testimonial-container">

      <section class="testimonial-card">
        <blockquote>
          "Mi tiempo aquí fue una experiencia de aprendizaje increíble. Pude aplicar mis conocimientos de desarrollo web
          en un proyecto real y el equipo siempre estuvo dispuesto a ayudar."
        </blockquote>
        <section class="testimonial-author">
          <p class="author-name">Jose Correa</p>
          <p class="author-role">Pasante de Desarrollo Web</p>
        </section>
      </section>

      <section class="testimonial-card">
        <blockquote>
          "El ambiente de trabajo en Lubriken C.A. es excelente. Aprendí no solo sobre bases de datos y PHP, sino
          también sobre metodologías de trabajo y buenas prácticas en la industria."
        </blockquote>
        <section class="testimonial-author">
          <p class="author-name">Jonathan Campos</p>
          <p class="author-role">Pasante de Ingeniería de Software</p>
        </section>
      </section>

      <section class="testimonial-card">
        <blockquote>
          "Una pasantía muy completa. Pude participar en el análisis de requerimientos, diseño de la base de datos y
          desarrollo del backend. 100% recomendada."
        </blockquote>
        <section class="testimonial-author">
          <p class="author-name">Andres Jatar</p>
          <p class="author-role">Pasante de Backend</p>
        </section>
      </section>

      <section class="testimonial-card">
        <blockquote>
          "Fue una gran oportunidad para aplicar lo aprendido en la universidad. Participé activamente en el desarrollo
          de un nuevo módulo, desde el diseño de la interfaz con HTML y CSS hasta la implementación de la lógica del
          negocio en el backend. Aprendí muchísimo sobre control de versiones."
        </blockquote>
        <section class="testimonial-author">
          <p class="author-name">Kevyn Camacaro (The special one)</p>
          <p class="author-role">Pasante de Backend</p>
        </section>
      </section>

      <section class="testimonial-card">
        <blockquote>
          ""La pasantía superó mis expectativas. Pude trabajar directamente con PHP y MySQL en el sistema principal,
          optimizando consultas y aprendiendo sobre seguridad web. El equipo siempre estuvo dispuesto a guiarme y
          resolvió todas mis dudas."
        </blockquote>
        <section class="testimonial-author">
          <p class="author-name">Juan Pereira</p>
          <p class="author-role">Pasante de Backend</p>
        </section>
      </section>

    </section>
    <!--Preguntas frecuentes-->
    <h3 class="faq-title">Preguntas Frecuentes</h3>
    <section id="preguntas" class="preguntas">
      <article class="pregunta-card">
        <h4>¿Donde estan ubicados? C.A</h4>
        <p>Urbanización Los Crepusculos, Barquisimeto 3001, Lara</p>
      </article>

      <article class="pregunta-card">
        <h4>¿Cuales son sus horarios de atencion? C.A</h4>
        <p>Lunes a Viernes de 8:00 am a 5:00 pm</p>
      </article>

      <article class="pregunta-card">
        <h4>¿Realizan envios a domicilio? C.A</h4>
        <p>Por ahora no realizamos envio a domicilio.</p>
      </article>

      <article class="pregunta-card">
        <h4>¿Cuales son sus metodos de pago? C.A</h4>
        <p>Aceptamos pagos en efectivo y transferencias bancarias.</p>
      </article>

    </section>
    <!--Preguntas frecuentes-->
    <section id="formulario-contacto" class="container-form">
      <h2 class="container-form__title">Formulario de contacto</h2>
      <form class="container-form__form" action="" method="POST">

        <div class="container-form__div">
          <label for="nombre_contacto">Nombre</label>
          <input class="container-form__campo" type="text" id="nombre_contacto" placeholder="Nombre">
        </div>

        <div class="container-form__div">
          <label for="numero_contacto">Numero</label>
          <input class="container-form__campo" type="number" id="numero_contacto" placeholder="Numero" min="1">
        </div>

        <div class="container-form__div">
          <label for="correo_contacto">Correo</label>
          <input class="container-form__campo" type="email" id="correo_contacto" placeholder="Correo">
        </div>

        <div class="container-form__div">
          <label for="mensaje_contacto">Mensaje</label>
          <textarea class="container-form__campo" name="mensaje_contacto" id="mensaje_contacto" placeholder="Deja un mensaje"></textarea>
        </div>

        <div class="container-form__div container-form__submit alinear-derecha">
          <button type="submit">Enviar</button>
        </div>
      </form>

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