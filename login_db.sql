-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 12-12-2025 a las 21:49:57
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `login_db`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `carritos_activos`
--

CREATE TABLE `carritos_activos` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `carritos_activos`
--

INSERT INTO `carritos_activos` (`id`, `user_id`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(47, 17, '2025-12-12 16:42:59', '2025-12-12 16:42:59');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalles_carrito`
--

CREATE TABLE `detalles_carrito` (
  `id` int(11) NOT NULL,
  `carrito_id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `detalles_carrito`
--

INSERT INTO `detalles_carrito` (`id`, `carrito_id`, `producto_id`, `cantidad`) VALUES
(62, 47, 13, 5);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalles_pedido`
--

CREATE TABLE `detalles_pedido` (
  `id` int(11) NOT NULL,
  `pedido_id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `detalles_pedido`
--

INSERT INTO `detalles_pedido` (`id`, `pedido_id`, `producto_id`, `cantidad`, `precio_unitario`) VALUES
(2, 2, 13, 10, 20.00),
(3, 3, 13, 6, 20.00),
(4, 4, 13, 1, 20.00),
(5, 5, 13, 6, 20.00),
(6, 6, 13, 5, 20.00),
(7, 7, 13, 1, 20.00),
(8, 8, 13, 1, 20.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `failing_attempts_ip`
--

CREATE TABLE `failing_attempts_ip` (
  `ip_address` varchar(45) NOT NULL,
  `failed_attempts` int(11) NOT NULL DEFAULT 1,
  `block_time` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pedidos`
--

CREATE TABLE `pedidos` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `fecha_solicitud` datetime NOT NULL DEFAULT current_timestamp(),
  `estado` enum('pendiente','completado','cancelado') NOT NULL DEFAULT 'pendiente',
  `total` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `pedidos`
--

INSERT INTO `pedidos` (`id`, `user_id`, `fecha_solicitud`, `estado`, `total`) VALUES
(2, 17, '2025-12-04 23:24:39', 'cancelado', 200.00),
(3, 17, '2025-12-04 23:35:31', 'cancelado', 120.00),
(4, 17, '2025-12-04 23:38:57', 'cancelado', 20.00),
(5, 17, '2025-12-04 23:47:10', 'cancelado', 120.00),
(6, 17, '2025-12-04 23:50:40', 'cancelado', 100.00),
(7, 17, '2025-12-05 00:00:07', 'cancelado', 20.00),
(8, 17, '2025-12-05 00:01:11', 'pendiente', 20.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos`
--

CREATE TABLE `productos` (
  `id` int(11) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `descripcion` text NOT NULL,
  `precio` decimal(10,2) NOT NULL,
  `stock_actual` int(11) NOT NULL DEFAULT 0,
  `imagen_url` varchar(255) DEFAULT NULL,
  `stock_comprometido` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `productos`
--

INSERT INTO `productos` (`id`, `nombre`, `descripcion`, `precio`, `stock_actual`, `imagen_url`, `stock_comprometido`) VALUES
(13, 'Aceite para carro Inca', 'Lubricante sintético de alto rendimiento, ideal para motores modernos.', 20.00, 45, '../images/productos/aceite para carro inca.jpg', 6),
(14, 'Filtros MHW', 'Filtros de aire de alta eficiencia, protege tu motor del polvo.', 15.00, 20, '../images/productos/filtros-MHW.png', 0),
(15, 'Base Con Bombin Para Filtros', 'Base de metal con bombín manual para purgar el sistema de combustible.', 60.00, 5, '../images/productos/base con bombin para filtros.jpg', 0),
(16, 'Filtros Combustible 3196', 'Filtro estándar de combustible, recomendado para diésel y gasolina.', 10.00, 0, '../images/productos/Filtros Combustible 3196.png', 0),
(17, 'Filtros De Aceite', 'Filtro de aceite de larga duración para mantener limpio el motor.', 30.00, 35, '../images/productos/filtro-de-aceite-30dolares.png', 0),
(18, 'Amortiguador Delantero Chevrolet Spark', 'Amortiguador de suspensión para eje delantero derecho.', 40.00, 0, '../images/productos/Amortiguador Delantero Chevrolet Spark 96424026 Derecho (rh).webp', 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `site_settings`
--

CREATE TABLE `site_settings` (
  `id` int(11) NOT NULL,
  `primary_color` varchar(7) NOT NULL DEFAULT '#007bff',
  `secondary_color` varchar(7) NOT NULL DEFAULT '#2c3e50',
  `text_color` varchar(7) NOT NULL DEFAULT '#333333',
  `bg_color` varchar(7) NOT NULL DEFAULT '#f8f9fa',
  `card_color` varchar(7) NOT NULL DEFAULT '#ffffff'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `site_settings`
--

INSERT INTO `site_settings` (`id`, `primary_color`, `secondary_color`, `text_color`, `bg_color`, `card_color`) VALUES
(1, '#007bff', '#2c3e50', '#333333', '#f8f9fa', '#ffffff');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `rol` enum('usuario','administrador') NOT NULL DEFAULT 'usuario',
  `nombre` varchar(30) DEFAULT NULL,
  `email` varchar(50) DEFAULT NULL,
  `contrasenna` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `rol`, `nombre`, `email`, `contrasenna`) VALUES
(7, 'usuario', 'pepa', 'elpapopepo@gmail.com', '$2y$10$wmK5/HnVNCGY4hf7.qmwGuHqmDv7bmykxnnghp.H2b3brIvDd9E92'),
(9, 'administrador', 'pepe', 'jco@gmai.com', '$2y$10$BX7Yldsqth/LjNGf0jkfiONLtB7qrGQX1VBx0u0yu6c7F4UosyDX2'),
(17, 'usuario', 'Avemaria', 'elpepe@gmail.com', '$2y$10$12f0WVlxlM.iB/w0J4gjqOXtpn1Oq5DHQRam4fXZ/tu5oMW4b7z9a'),
(19, 'usuario', 'bil', 'bilewater@hotmail.com', '$2y$10$yrrLVBEeKtNZY5FCtRq8/u3hD1vRHk5iQQm5oDxB9qJEQHKtWTJO.'),
(20, 'usuario', 'Moltenfriend', 'volcanobuddy@gmail.com', '$2y$10$NRanMQCoWDudmrhIjzMBme0dmsIpp1kDH4p.eMiTegku3SbT/4LXy'),
(21, 'usuario', 'alejandro sans', 'corazonpartio@gmail.com', '$2y$10$S27XEH6nJ9733rb7dSkYIun0eQ5UEE6kmBiF5.1X.sT82jhR25pMi'),
(22, 'usuario', 'avemariasefue', 'algomas@gmail.com', '$2y$10$bXPBO0.Q8Kvha1I6mRdA3.G/91DnZcMkJFnq.GZToYpPg32JfXbBS'),
(23, 'usuario', 'jonathaneselsus', 'elpeposus@gmail.com', '$2y$10$DS5R9sSgQGyptNPsvJb3y.YmDDDP/1dFP4w8pN0FyfYpI6dtyQ2Iq'),
(24, 'usuario', 'etesech', 'pepito@gmail.com', '$2y$10$4NOO9F2jW4J7w9zuropJueF7.9zO0ckQhrN0g6r6LO0bf7BRkDsZe'),
(25, 'usuario', 'etepepon', 'lario@gmail.com', '$2y$10$Er9Eu1utSg74zGdezCNX/OnuYTSA0yP1KO48O8kui68UB75Ylk.6m'),
(26, 'usuario', 'sea', 'e@g.com', '$2y$10$b/QEVWQL6iqkU4/0WbI0fu1hcmcY6Ab7WyxlN3r2yzlsQ8fYnKelO'),
(27, 'usuario', 'pepa', 'pipa@g.com', '$2y$10$yTY4vsxtMuKOAXhdVsKOzu/gcsmgeXS8jGuvxSIbiUARHCYKqrBDO'),
(28, 'usuario', 'ula', 'queesvivir@gmail.com', '$2y$10$17DwgXGmxfdPjjIUTHWGs.0ZBtktUMdGJaXoE3hWUoNTW/yuKZy3K'),
(29, 'usuario', 'amor', 'telodigoyo@gmail.com', '$2y$10$dkgLl36zj40gBB2aIxmhCO32jUuZAZBcDi/CRR8OhfYr3hKWZUni.');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `carritos_activos`
--
ALTER TABLE `carritos_activos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user` (`user_id`);

--
-- Indices de la tabla `detalles_carrito`
--
ALTER TABLE `detalles_carrito`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_item` (`carrito_id`,`producto_id`),
  ADD KEY `producto_id` (`producto_id`);

--
-- Indices de la tabla `detalles_pedido`
--
ALTER TABLE `detalles_pedido`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pedido_id` (`pedido_id`),
  ADD KEY `producto_id` (`producto_id`);

--
-- Indices de la tabla `failing_attempts_ip`
--
ALTER TABLE `failing_attempts_ip`
  ADD PRIMARY KEY (`ip_address`);

--
-- Indices de la tabla `pedidos`
--
ALTER TABLE `pedidos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indices de la tabla `productos`
--
ALTER TABLE `productos`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `site_settings`
--
ALTER TABLE `site_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `carritos_activos`
--
ALTER TABLE `carritos_activos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT de la tabla `detalles_carrito`
--
ALTER TABLE `detalles_carrito`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- AUTO_INCREMENT de la tabla `detalles_pedido`
--
ALTER TABLE `detalles_pedido`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `pedidos`
--
ALTER TABLE `pedidos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `productos`
--
ALTER TABLE `productos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `carritos_activos`
--
ALTER TABLE `carritos_activos`
  ADD CONSTRAINT `carritos_activos_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `detalles_carrito`
--
ALTER TABLE `detalles_carrito`
  ADD CONSTRAINT `detalles_carrito_ibfk_1` FOREIGN KEY (`carrito_id`) REFERENCES `carritos_activos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `detalles_carrito_ibfk_2` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `detalles_pedido`
--
ALTER TABLE `detalles_pedido`
  ADD CONSTRAINT `detalles_pedido_ibfk_1` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `detalles_pedido_ibfk_2` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `pedidos`
--
ALTER TABLE `pedidos`
  ADD CONSTRAINT `pedidos_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`id`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
