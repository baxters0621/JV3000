<?php

// ==========================================
// CLASE DE SEGURIDAD
// ==========================================
class Security
{
    // Validar sesión de usuario
    public static function validateSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['id_usuario'])) {
            self::redirectToLogin();
        }

        $db = Database::getInstance();
        $userId = (int)$_SESSION['id_usuario'];
        $user = $db->fetchOne(
            "SELECT id_rol, usuario, status, COALESCE(aprobado, 1) as aprobado FROM usuarios WHERE id_usuario = ? LIMIT 1",
            [$userId]
        );

        if (!$user) {
            session_destroy();
            self::redirectToLogin('sesion_invalida');
        }

        if (!isset($_SESSION['ip_addr']) || $_SESSION['ip_addr'] !== $_SERVER['REMOTE_ADDR']) {
            session_destroy();
            self::redirectToLogin();
        }

        // Timeout de inactividad (30 minutos)
        $idleTimeout = 1800; // 30 * 60 segundos
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $idleTimeout) {
            session_destroy();
            self::redirectToLogin('sesion_expirada');
        }
        $_SESSION['last_activity'] = time();

        if ((int)$user['aprobado'] === 0) {
            session_destroy();
            self::redirectToLogin('cuenta_pendiente');
        }

        if ($user['status'] === 'Inactivo') {
            session_destroy();
            self::redirectToLogin('cuenta_desactivada');
        }

        $_SESSION['usuario'] = $user['usuario'];
        $_SESSION['id_rol'] = (int)$user['id_rol'];
    }

    // Sanitizar variables globales
    public static function sanitizeGlobals(): void
    {
        $sanitize = function (&$value) {
            if (is_string($value)) {
                $value = trim($value);
            }
        };

        array_walk_recursive($_GET, $sanitize);
        array_walk_recursive($_POST, $sanitize);
        array_walk_recursive($_REQUEST, $sanitize);
    }

    // Generar token CSRF
    public static function generateToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    // Validar token CSRF
    public static function validateCSRF(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $token = $_POST['csrf_token'] ?? '';
        if (empty($token) || !isset($_SESSION['csrf_token'])) {
            self::failCSRF();
        }

        if (!hash_equals($_SESSION['csrf_token'], $token)) {
            self::failCSRF();
        }
    }

    // Manejar error de CSRF
    private static function failCSRF(): void
    {
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']);
        if ($isAjax) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Token CSRF inválido.']);
            exit;
        }
        $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'Error de seguridad: token CSRF inválido.'];
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $refHost = $referer ? strtolower((string)parse_url($referer, PHP_URL_HOST)) : '';
        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $appHost = strtolower((string)parse_url($scheme . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST));
        if ($refHost !== '' && $refHost === $appHost) {
            header("Location: $referer");
            exit;
        }
        header("Location: " . BASE_PATH . "dashboard/index.php");
        exit;
    }

    // Escapar HTML
    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    // Verificar si es admin
    public static function esAdmin(): bool
    {
        return isset($_SESSION['id_rol']) && $_SESSION['id_rol'] === 1;
    }

    // Solo admin
    public static function soloAdmin(): void
    {
        if (!self::esAdmin()) {
            header("Location: " . BASE_PATH . "dashboard/index.php?error=acceso_prohibido");
            exit;
        }
    }

    // Verificar permiso de carga
    public static function puedeCargar(): bool
    {
        return self::esAdmin() || (isset($_SESSION['id_rol']) && $_SESSION['id_rol'] === 2);
    }

    // Verificar permiso de venta
    public static function puedeVender(): bool
    {
        return self::esAdmin() || (isset($_SESSION['id_rol']) && $_SESSION['id_rol'] === 3);
    }

    // Redirigir si no tiene permiso de carga
    public static function verificarPermisoCarga(): void
    {
        if (!self::puedeCargar()) {
            header("Location: " . BASE_PATH . "dashboard/index.php?error=acceso_denegado");
            exit;
        }
    }

    // Redirigir si no tiene permiso de venta
    public static function verificarPermisoVenta(): void
    {
        if (!self::puedeVender()) {
            header("Location: " . BASE_PATH . "dashboard/index.php?error=acceso_denegado");
            exit;
        }
    }

    // Datos del usuario actual
    public static function currentUser(): array
    {
        return [
            'id' => (int)($_SESSION['id_usuario'] ?? 0),
            'usuario' => $_SESSION['usuario'] ?? 'Invitado',
            'id_rol' => $_SESSION['id_rol'] ?? 0,
        ];
    }

    // Redirigir al login
    private static function redirectToLogin(string $error = ''): void
    {
        $url = BASE_PATH . 'login/login.php';
        if ($error) {
            $url .= '?error=' . $error;
        }
        header("Location: $url");
        exit;
    }
}
