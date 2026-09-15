<?php
require_once __DIR__ . '/../bootstrap.php';

use App\Helpers\Security;

class SecurityTest {
    public function testCsrfTokenGeneration() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        
        $token1 = Security::generateCsrfToken();
        assert(!empty($token1), 'Token no debe estar vacío');
        assert(strlen($token1) === 64, 'Token debe tener 64 caracteres');
        
        echo "✅ testCsrfTokenGeneration PASSED\n";
    }

    public function testSanitizeInput() {
        $input = '<script>alert("xss")</script>';
        $clean = Security::sanitize($input);
        
        assert(strpos($clean, '<script>') === false, 'Script debe ser removido');
        echo "✅ testSanitizeInput PASSED\n";
    }

    public function testPasswordHash() {
        $password = 'TestPassword123!';
        $hash = Security::hashPassword($password);
        
        assert(password_verify($password, $hash), 'Verificación debe pasar');
        assert(!password_verify('wrong', $hash), 'Contraseña incorrecta debe fallar');
        
        echo "✅ testPasswordHash PASSED\n";
    }

    public function runAll() {
        $this->testCsrfTokenGeneration();
        $this->testSanitizeInput();
        $this->testPasswordHash();
        echo "\n🎉 Todos los tests de seguridad PASARON\n";
    }
}

$test = new SecurityTest();
$test->runAll();