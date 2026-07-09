<?php

class Security
{
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
            "SELECT rol, usuario, status, COALESCE(aprobado, 1) as aprobado FROM usuarios WHERE id_usuario = ? LIMIT 1",
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

        if ((int)$user['aprobado'] === 0) {
            session_destroy();
            self::redirectToLogin('cuenta_pendiente');
        }

        if ($user['status'] === 'Inactivo') {
            session_destroy();
            self::redirectToLogin('cuenta_desactivada');
        }

        $_SESSION['usuario'] = $user['usuario'];
        $_SESSION['rol'] = $user['rol'];
    }

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
        $referer = $_SERVER['HTTP_REFERER'] ?? 'index.php';
        header("Location: $referer");
        exit;
    }

    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    public static function esAdmin(): bool
    {
        return isset($_SESSION['rol']) && $_SESSION['rol'] === 'Administrador';
    }

    public static function soloAdmin(): void
    {
        if (!self::esAdmin()) {
            $isModule = basename(dirname($_SERVER['SCRIPT_NAME'])) === 'modules';
            header("Location: " . ($isModule ? '../' : '') . "index.php?error=acceso_prohibido");
            exit;
        }
    }

    public static function puedeCargar(): bool
    {
        return self::esAdmin() || (isset($_SESSION['rol']) && $_SESSION['rol'] === 'Operador de Carga');
    }

    public static function puedeVender(): bool
    {
        return self::esAdmin() || (isset($_SESSION['rol']) && $_SESSION['rol'] === 'Operador de Ventas');
    }

    public static function verificarPermisoCarga(): void
    {
        if (!self::puedeCargar()) {
            $isModule = basename(dirname($_SERVER['SCRIPT_NAME'])) === 'modules';
            header("Location: " . ($isModule ? '../' : '') . "index.php?error=acceso_denegado");
            exit;
        }
    }

    public static function verificarPermisoVenta(): void
    {
        if (!self::puedeVender()) {
            $isModule = basename(dirname($_SERVER['SCRIPT_NAME'])) === 'modules';
            header("Location: " . ($isModule ? '../' : '') . "index.php?error=acceso_denegado");
            exit;
        }
    }

    public static function currentUser(): array
    {
        return [
            'id' => (int)($_SESSION['id_usuario'] ?? 0),
            'usuario' => $_SESSION['usuario'] ?? 'Invitado',
            'rol' => $_SESSION['rol'] ?? 'Sin Rol',
        ];
    }

    public static function checkLoginAttempts(string $ip): bool
    {
        $db = Database::getInstance();
        $row = $db->fetchOne(
            "SELECT intentos, ultimo_intento FROM login_intentos WHERE ip_address = ?",
            [$ip]
        );
        if (!$row) {
            return true;
        }
        if ((int)$row['intentos'] >= 3) {
            $ultimo = strtotime($row['ultimo_intento']);
            if (time() - $ultimo < 45) {
                return false;
            }
            $db->execute("DELETE FROM login_intentos WHERE ip_address = ?", [$ip]);
        }
        return true;
    }

    public static function registerLoginAttempt(string $ip, string $username): void
    {
        $db = Database::getInstance();
        $row = $db->fetchOne(
            "SELECT id, intentos FROM login_intentos WHERE ip_address = ?",
            [$ip]
        );
        if ($row) {
            $db->execute(
                "UPDATE login_intentos SET intentos = intentos + 1, ultimo_intento = NOW() WHERE id = ?",
                [(int)$row['id']]
            );
        } else {
            $db->insert('login_intentos', [
                'ip_address' => $ip,
                'intentos' => 1,
            ]);
        }
    }

    private static function redirectToLogin(string $error = ''): void
    {
        $isModule = basename(dirname($_SERVER['SCRIPT_NAME'])) === 'modules';
        $url = $isModule ? '../login.php' : 'login.php';
        if ($error) {
            $url .= '?error=' . $error;
        }
        header("Location: $url");
        exit;
    }
}
