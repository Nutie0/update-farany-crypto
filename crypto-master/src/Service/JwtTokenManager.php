<?php

namespace App\Service;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class JwtTokenManager
{
    private string $secretKey;
    private int $tokenTtl;

    public function __construct(ParameterBagInterface $params)
    {
        $this->secretKey = $params->get('app.jwt_secret');
        $this->tokenTtl = 3600; // 1 heure
    }

    public function createToken(array $payload): string
    {
        $issuedAt = time();
        $expire = $issuedAt + $this->tokenTtl;

        $tokenPayload = array_merge($payload, [
            'iat' => $issuedAt,
            'exp' => $expire
        ]);

        return JWT::encode($tokenPayload, $this->secretKey, 'HS256');
    }

    public function validateToken(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secretKey, 'HS256'));
            return (array) $decoded;
        } catch (\Exception $e) {
            return null;
        }
    }
}
