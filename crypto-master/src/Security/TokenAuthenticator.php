<?php

namespace App\Security;

use App\Service\UserSyncService;
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
use Psr\Log\LoggerInterface;

class TokenAuthenticator extends AbstractAuthenticator
{
    private $userSyncService;
    private $logger;

    public function __construct(
        UserSyncService $userSyncService,
        LoggerInterface $logger
    ) {
        $this->userSyncService = $userSyncService;
        $this->logger = $logger;
    }

    public function supports(Request $request): ?bool
    {
        return $request->headers->has('Authorization');
    }

    public function authenticate(Request $request): Passport
    {
        try {
            $authHeader = $request->headers->get('Authorization');
            if (null === $authHeader) {
                throw new CustomUserMessageAuthenticationException('No token provided');
            }

            $token = str_replace('Bearer ', '', $authHeader);
            
            $this->logger->info('Tentative de validation du token');
            
            $user = $this->userSyncService->validateTokenAndGetUser($token);
            
            if (!$user) {
                throw new CustomUserMessageAuthenticationException('Invalid token');
            }

            return new SelfValidatingPassport(
                new UserBadge($user->getEmail())
            );

        } catch (\Exception $e) {
            $this->logger->error('Erreur d\'authentification: ' . $e->getMessage());
            throw new CustomUserMessageAuthenticationException($e->getMessage());
        }
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse([
            'status' => 'error',
            'message' => $exception->getMessage()
        ], Response::HTTP_UNAUTHORIZED);
    }
}
