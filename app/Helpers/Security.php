<?php
namespace App\Helpers;

class Security {
    public static function generateCsrfToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function validateCsrfToken($token) {
        if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
            error_log("CSRF Validation Failed for IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            throw new \Exception("Error de seguridad: Token CSRF inválido o expirado.");
        }
        return true;
    }
}