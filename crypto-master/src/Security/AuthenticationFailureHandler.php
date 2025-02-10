<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Psr\Log\LoggerInterface;

class AuthenticationFailureHandler implements AuthenticationFailureHandlerInterface
{
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        // Log l'erreur pour le débogage
        $this->logger->error('Échec de l\'authentification', [
            'exception' => $exception->getMessage()
        ]);

        return new JsonResponse([
            'error' => true,
            'message' => 'Identifiants invalides. Veuillez vérifier votre email et mot de passe.'
        ], Response::HTTP_UNAUTHORIZED);
    }
}
