<?php
// src/php/requires_central.php

// 1. INFRAESTRUCTURA DE BASE DE DATOS (¡ESTO FALTABA!)
// Ajusta la ruta si database.php está en otro lado, pero por tu estructura parece estar en models.
require_once __DIR__ . '/models/database.php';

// 2. MODELO BASE (DbModel debe cargarse ANTES que cualquier clase hija)
require_once __DIR__ . '/models/DbModel.php';

// 3. MODELOS DE NEGOCIO
require_once __DIR__ . '/models/UserModel.php';
require_once __DIR__ . '/models/ProductModel.php';
require_once __DIR__ . '/models/CartModel.php';
require_once __DIR__ . '/models/OrderModel.php'; // (Si ya creaste OrderModel)

// 4. SERVICIOS Y HELPERS
require_once __DIR__ . '/services/UserValidator.php';
require_once __DIR__ . '/services/SecurityHelper.php';
require_once __DIR__ . '/services/ThemeHelper.php';