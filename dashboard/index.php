<?php
/**
 * Puente de compatibilidad para el antiguo dashboard directo.
 * El panel operativo se sirve exclusivamente mediante el front controller MVC.
 */
require_once __DIR__ . '/../init.php';
header('Location: ../index.php?url=dashboard', true, 302);
exit;