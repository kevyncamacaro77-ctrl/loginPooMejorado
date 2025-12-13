-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Dec 13, 2025 at 02:56 AM
-- Server version: 8.0.30
-- PHP Version: 8.1.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `login_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `carritos_activos`
--

CREATE TABLE `carritos_activos` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `detalles_carrito`
--

CREATE TABLE `detalles_carrito` (
  `id` int NOT NULL,
  `carrito_id` int NOT NULL,
  `producto_id` int NOT NULL,
  `cantidad` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `detalles_carrito`
--

INSERT INTO `detalles_carrito` (`id`, `carrito_id`, `producto_id`, `cantidad`) VALUES
(9, 4, 13, 4),
(10, 4, 18, 1),
(11, 4, 15, 1),
(12, 5, 13, 1),
(13, 6, 13, 2),
(18, 7, 18, 2),
(19, 7, 15, 2),
(20, 7, 17, 2),
(21, 7, 14, 2),
(22, 8, 18, 2),
(23, 8, 13, 2),
(24, 9, 18, 5),
(25, 9, 15, 1),
(26, 9, 17, 5),
(27, 9, 14, 5),
(28, 10, 13, 2),
(29, 10, 18, 2),
(30, 11, 13, 4),
(31, 11, 18, 4),
(32, 11, 15, 4),
(33, 11, 16, 4),
(34, 11, 17, 4),
(35, 11, 14, 4);

-- --------------------------------------------------------

--
-- Table structure for table `detalles_pedido`
--

CREATE TABLE `detalles_pedido` (
  `id` int NOT NULL,
  `pedido_id` int NOT NULL,
  `producto_id` int NOT NULL,
  `cantidad` int NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `detalles_pedido`
--

INSERT INTO `detalles_pedido` (`id`, `pedido_id`, `producto_id`, `cantidad`, `precio_unitario`) VALUES
(54, 38, 13, 6, '20.00'),
(55, 38, 18, 5, '40.00'),
(56, 38, 15, 5, '60.00');

-- --------------------------------------------------------

--
-- Table structure for table `failing_attempts_ip`
--

CREATE TABLE `failing_attempts_ip` (
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `failed_attempts` int NOT NULL DEFAULT '1',
  `block_time` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pedidos`
--

CREATE TABLE `pedidos` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `fecha_pedido` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `estado` enum('pendiente','procesando','enviado','entregado','cancelado','completado') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'pendiente',
  `fecha_confirmacion` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `pedidos`
--

INSERT INTO `pedidos` (`id`, `user_id`, `fecha_pedido`, `total`, `estado`, `fecha_confirmacion`) VALUES
(31, 2, '2025-12-06 21:58:18', '40.00', 'completado', NULL),
(32, 2, '2025-12-06 22:02:13', '290.00', 'completado', NULL),
(33, 2, '2025-12-06 22:15:24', '120.00', 'completado', NULL),
(34, 2, '2025-12-08 20:55:36', '485.00', 'completado', NULL),
(35, 2, '2025-12-12 12:17:41', '120.00', 'completado', NULL),
(36, 2, '2025-12-12 12:57:34', '700.00', 'completado', '2025-12-12 15:13:23'),
(37, 2, '2025-12-12 19:35:58', '480.00', 'completado', NULL),
(38, 2, '2025-12-12 20:24:50', '620.00', 'completado', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `productos`
--

CREATE TABLE `productos` (
  `id` int NOT NULL,
  `nombre` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_general_ci NOT NULL,
  `precio` decimal(10,2) NOT NULL,
  `stock_actual` int NOT NULL DEFAULT '0',
  `imagen_url` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `stock_comprometido` int NOT NULL DEFAULT '0',
  `unidades_vendidas` int NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `productos`
--

INSERT INTO `productos` (`id`, `nombre`, `descripcion`, `precio`, `stock_actual`, `imagen_url`, `stock_comprometido`, `unidades_vendidas`) VALUES
(13, 'Aceite para carro Inca', 'Lubricante sintético de alto rendimiento, ideal para motores modernos.', '20.00', 24, '../images/productos/aceite para carro inca.jpg', 0, 6),
(14, 'Filtros MHW', 'Filtros de aire de alta eficiencia, protege tu motor del polvo.', '15.00', 17, '../images/productos/filtros-MHW.png', 0, 0),
(15, 'Base Con Bombin Para Filtros', 'Base de metal con bombín manual para purgar el sistema de combustible.', '60.00', 7, '../images/productos/base con bombin para filtros.jpg', 0, 5),
(16, 'Filtros Combustible 3196', 'Filtro estándar de combustible, recomendado para diésel y gasolina.', '10.00', 16, '../images/productos/Filtros Combustible 3196.png', 0, 0),
(17, 'Filtros De Aceite', 'Filtro de aceite de larga duración para mantener limpio el motor.', '30.00', 24, '../images/productos/filtro-de-aceite-30dolares.png', 0, 0),
(18, 'Amortiguador Delantero Chevrolet Spark', 'Amortiguador de suspensión para eje delantero derecho.', '40.00', 5, '../images/productos/Amortiguador Delantero Chevrolet Spark 96424026 Derecho (rh).webp', 0, 5);

-- --------------------------------------------------------

--
-- Table structure for table `site_settings`
--

CREATE TABLE `site_settings` (
  `id` int NOT NULL,
  `primary_color` varchar(7) COLLATE utf8mb4_general_ci NOT NULL,
  `secondary_color` varchar(7) COLLATE utf8mb4_general_ci NOT NULL,
  `text_color` varchar(7) COLLATE utf8mb4_general_ci NOT NULL,
  `bg_color` varchar(7) COLLATE utf8mb4_general_ci NOT NULL,
  `card_color` varchar(7) COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `site_settings`
--

INSERT INTO `site_settings` (`id`, `primary_color`, `secondary_color`, `text_color`, `bg_color`, `card_color`) VALUES
(1, '#007bff', '#2c3e50', '#333333', '#f8f9fa', '#ffffff');

-- --------------------------------------------------------

--
-- Table structure for table `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int NOT NULL,
  `rol` enum('usuario','administrador') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'usuario',
  `nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `contrasenna` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `usuarios`
--

INSERT INTO `usuarios` (`id`, `rol`, `nombre`, `email`, `contrasenna`) VALUES
(1, 'administrador', 'Kevyn', 'kevyncamacaro1@gmail.com', '$2y$10$2gIl5yF049412wHHhqgOguZJ.f2TwfAd82ja2XKSOn6LHfozN.UwO'),
(2, 'usuario', 'pepe', 'pepe@gmail.com', '$2y$10$nHZw/lm3pRiqjGEXilIqUeWo3Po0OGruhmNobTUDr9yjsBV1LRXf.');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `carritos_activos`
--
ALTER TABLE `carritos_activos`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `detalles_carrito`
--
ALTER TABLE `detalles_carrito`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `detalles_pedido`
--
ALTER TABLE `detalles_pedido`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_pedido_id` (`pedido_id`),
  ADD KEY `fk_producto_id` (`producto_id`);

--
-- Indexes for table `pedidos`
--
ALTER TABLE `pedidos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `productos`
--
ALTER TABLE `productos`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `site_settings`
--
ALTER TABLE `site_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `carritos_activos`
--
ALTER TABLE `carritos_activos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `detalles_carrito`
--
ALTER TABLE `detalles_carrito`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `detalles_pedido`
--
ALTER TABLE `detalles_pedido`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT for table `pedidos`
--
ALTER TABLE `pedidos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `detalles_pedido`
--
ALTER TABLE `detalles_pedido`
  ADD CONSTRAINT `fk_pedido_id` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`),
  ADD CONSTRAINT `fk_producto_id` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`);

--
-- Constraints for table `pedidos`
--
ALTER TABLE `pedidos`
  ADD CONSTRAINT `pedidos_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
