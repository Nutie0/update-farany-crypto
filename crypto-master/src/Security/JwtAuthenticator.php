<?php

namespace App\Security;

use App\Service\JwtTokenManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class JwtAuthenticator extends AbstractAuthenticator
{
    private $jwtManager;

    public function __construct(JwtTokenManager $jwtManager)
    {
        $this->jwtManager = $jwtManager;
    }

    public function supports(Request $request): ?bool
    {
        return $request->headers->has('Authorization');
    }

    public function authenticate(Request $request): Passport
    {
        $authHeader = $request->headers->get('Authorization');
        if (null === $authHeader) {
            throw new CustomUserMessageAuthenticationException('No API token provided');
        }

        $token = str_replace('Bearer ', '', $authHeader);
        $payload = $this->jwtManager->validateToken($token);

        if (null === $payload) {
            throw new CustomUserMessageAuthenticationException('Invalid JWT token');
        }

        if (!isset($payload['email'])) {
            throw new CustomUserMessageAuthenticationException('Invalid token payload');
        }

        return new SelfValidatingPassport(
            new UserBadge($payload['email'])
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $data = [
            'status' => 'error',
            'message' => $exception->getMessage()
        ];

        return new JsonResponse($data, Response::HTTP_UNAUTHORIZED);
    }
}
