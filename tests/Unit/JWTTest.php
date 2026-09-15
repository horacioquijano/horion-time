<?php
require_once __DIR__ . '/../bootstrap.php';

use App\Helpers\JWT;

class JWTTest {
    public function testEncodeDecode() {
        $payload = ['sub' => 1, 'email' => 'test@test.com', 'rol' => 'Admin'];
        $token = JWT::encode($payload, 3600);
        
        assert(!empty($token), 'Token no debe estar vacío');
        assert(substr_count($token, '.') === 2, 'Token debe tener 3 partes');
        
        $decoded = JWT::decode($token);
        assert($decoded['sub'] === 1, 'sub debe coincidir');
        assert($decoded['email'] === 'test@test.com', 'email debe coincidir');
        
        echo "✅ testEncodeDecode PASSED\n";
    }

    public function testExpiredToken() {
        $token = JWT::encode(['sub' => 1], -10); // Expirado hace 10 segundos
        
        try {
            JWT::decode($token);
            assert(false, 'Debería lanzar excepción');
        } catch (\Exception $e) {
            assert($e->getMessage() === 'Token expirado', 'Mensaje correcto');
            echo "✅ testExpiredToken PASSED\n";
        }
    }

    public function testInvalidSignature() {
        $token = JWT::encode(['sub' => 1]);
        $parts = explode('.', $token);
        $parts[2] = 'invalidsignature';
        $tampered = implode('.', $parts);
        
        try {
            JWT::decode($tampered);
            assert(false, 'Debería lanzar excepción');
        } catch (\Exception $e) {
            assert($e->getMessage() === 'Firma inválida', 'Mensaje correcto');
            echo "✅ testInvalidSignature PASSED\n";
        }
    }

    public function runAll() {
        $this->testEncodeDecode();
        $this->testExpiredToken();
        $this->testInvalidSignature();
        echo "\n🎉 Todos los tests PASARON\n";
    }
}

$test = new JWTTest();
$test->runAll();