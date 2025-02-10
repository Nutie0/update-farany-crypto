<?php

namespace App\Security;

use App\Service\UserSyncService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Psr\Log\LoggerInterface;

class ApiTokenAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public function __construct(
        private UserSyncService $userSyncService,
        private UrlGeneratorInterface $urlGenerator,
        private LoggerInterface $logger
    ) {}

    public function supports(Request $request): ?bool
    {
        // Ne pas authentifier les routes publiques
        if ($request->getPathInfo() === '/login' || 
            $request->getPathInfo() === '/api/auth/login' ||
            $request->getPathInfo() === '/' ||
            str_starts_with($request->getPathInfo(), '/assets/') ||
            str_starts_with($request->getPathInfo(), '/bundles/') ||
            str_starts_with($request->getPathInfo(), '/_wdt/') ||
            str_starts_with($request->getPathInfo(), '/api/auth/')) {
            return false;
        }

        return true;
    }

    public function authenticate(Request $request): Passport
    {
        try {
            $authHeader = $request->headers->get('Authorization');
            if (null === $authHeader) {
                throw new CustomUserMessageAuthenticationException('Token manquant');
            }

            if (!preg_match('/Bearer\s+(.+)/i', $authHeader, $matches)) {
                throw new CustomUserMessageAuthenticationException('Format de token invalide');
            }

            $token = $matches[1];
            $this->logger->info('Tentative d\'authentification avec le token: ' . substr($token, 0, 10) . '...');

            return new SelfValidatingPassport(
                new UserBadge($token, function ($token) {
                    $user = $this->userSyncService->validateTokenAndGetUser($token);
                    if (!$user) {
                        $this->logger->error('Token invalide ou utilisateur non trouvé');
                        throw new CustomUserMessageAuthenticationException('Token invalide');
                    }
                    $this->logger->info('Utilisateur authentifié: ' . $user->getEmail());
                    return $user;
                })
            );
        } catch (\Exception $e) {
            $this->logger->error('Erreur d\'authentification: ' . $e->getMessage());
            throw new CustomUserMessageAuthenticationException($e->getMessage());
        }
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Authentification réussie, on continue la requête
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $this->logger->error('Échec de l\'authentification: ' . $exception->getMessage());

        if ($request->isXmlHttpRequest() || str_contains($request->headers->get('Content-Type', ''), 'application/json')) {
            return new JsonResponse([
                'success' => false,
                'message' => $exception->getMessage()
            ], Response::HTTP_UNAUTHORIZED);
        }

        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }

    public function start(Request $request, AuthenticationException $authException = null): Response
    {
        if ($request->isXmlHttpRequest() || str_contains($request->headers->get('Content-Type', ''), 'application/json')) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Authentification requise'
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Rediriger vers la page de login pour les requêtes normales
        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }
}
