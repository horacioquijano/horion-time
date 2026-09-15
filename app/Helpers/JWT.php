<?php
namespace App\Helpers;

class JWT {
    private static $secret = 'HORION_TIME_SECRET_2026_CHANGE_IN_PRODUCTION';
    private static $algorithm = 'HS256';

    /**
     * Generar token JWT
     */
    public static function encode($payload, $expiraEn = 3600) {
        $header = [
            'typ' => 'JWT',
            'alg' => self::$algorithm
        ];

        $now = time();
        $payload['iat'] = $now;
        $payload['exp'] = $now + $expiraEn;

        $headerB64 = self::base64UrlEncode(json_encode($header));
        $payloadB64 = self::base64UrlEncode(json_encode($payload));

        $signature = hash_hmac('sha256', "$headerB64.$payloadB64", self::$secret, true);
        $signatureB64 = self::base64UrlEncode($signature);

        return "$headerB64.$payloadB64.$signatureB64";
    }

    /**
     * Decodificar y validar token JWT
     */
    public static function decode($token) {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new \Exception('Token malformado');
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;

        // Verificar firma
        $signature = self::base64UrlDecode($signatureB64);
        $expectedSignature = hash_hmac('sha256', "$headerB64.$payloadB64", self::$secret, true);

        if (!hash_equals($expectedSignature, $signature)) {
            throw new \Exception('Firma inválida');
        }

        $payload = json_decode(self::base64UrlDecode($payloadB64), true);
        if (!$payload) {
            throw new \Exception('Payload inválido');
        }

        // Verificar expiración
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            throw new \Exception('Token expirado');
        }

        return $payload;
    }

    /**
     * Generar token para API externa (más largo, sin expiración corta)
     */
    public static function generateApiToken($empresa_id, $usuario_id, $nombre, $permisos = []) {
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        
        return [
            'raw' => $rawToken, // Mostrar solo una vez al crear
            'hash' => $tokenHash,
            'visible' => substr($rawToken, 0, 32) . '...'
        ];
    }

    private static function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode($data) {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    public static function setSecret($secret) {
        self::$secret = $secret;
    }
}