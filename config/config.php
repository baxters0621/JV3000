<?php

// ==========================================
// CONFIG MVC — Reutiliza la configuración base
// ==========================================
require_once dirname(__DIR__) . '/includes/config.php';

// Constantes de rutas de la aplicación MVC
define('APP_ROOT', dirname(__DIR__));
define('APP_CONFIG', APP_ROOT . '/config');
define('APP_CORE', APP_ROOT . '/core');
define('APP_CONTROLLERS', APP_ROOT . '/controllers');
define('APP_MODELS', APP_ROOT . '/models');
define('APP_VIEWS', APP_ROOT . '/views');

// URL del front controller para construir enlaces (index.php?url=...)
define('APP_URL_BASE', BASE_PATH);
