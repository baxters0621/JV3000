<?php

// === CONSTANTES GLOBALES (usadas por init.php y módulos) ===
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'jv3000_db');
define('APP_NAME', 'JV3000 C.A.');
define('VERSION', '3.0.0');
define('BASE_ASSETS', (basename(dirname($_SERVER['SCRIPT_NAME'])) === 'modules') ? '../assets/' : 'assets/');

