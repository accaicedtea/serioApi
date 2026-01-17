<?php
// Semplice handler JWT; i valori tra __PLACEHOLDER__ saranno sostituiti dal builder.
class JWTHandler {
    private $secret_key = '__JWT_SECRET__';
    private $algorithm  = '__JWT_ALGO__'; // HS256, HS384, HS512
    private $ttl        = __JWT_TTL__;    // in secondi

    private function b64url($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public function generateToken(array $user) {
        $header = ['typ' => 'JWT', 'alg' => $this->algorithm];
        $now = time();
        $payload = [
            'sub' => (string)($user['id'] ?? ''),
            'email' => $user['email'] ?? '',
            'role' => $user['role'] ?? 'user',
            'name' => $user['name'] ?? '',
            // __CUSTOM_FIELDS__ - Campi personalizzati verranno inseriti qui dal builder
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $this->ttl,
        ];
        $h = $this->b64url(json_encode($header));
        $p = $this->b64url(json_encode($payload));
        $s = $this->b64url(hash_hmac('sha256', "$h.$p", $this->secret_key, true));
        return "$h.$p.$s";
    }

    public function validateToken(string $token) {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return false;
        [$h, $p, $s] = $parts;
        $expected = $this->b64url(hash_hmac('sha256', "$h.$p", $this->secret_key, true));
        if (!hash_equals($expected, $s)) return false;

        $payload = json_decode(base64_decode(strtr($p, '-_', '+/')), true);
        if (!is_array($payload)) return false;
        if (($payload['exp'] ?? 0) < time()) return false;
        // opzionale: verifica iss/aud
        return $payload;
    }

    public function getTokenFromHeader(): ?string {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        if (preg_match('/Bearer\s+(\S+)/', $auth, $m)) return $m[1];
        return null;
    }
}