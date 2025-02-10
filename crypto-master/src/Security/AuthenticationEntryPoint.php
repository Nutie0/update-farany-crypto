<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class AuthenticationEntryPoint implements AuthenticationEntryPointInterface
{
    private $urlGenerator;
    private $tokenStorage;

    public function __construct(
        UrlGeneratorInterface $urlGenerator,
        TokenStorageInterface $tokenStorage
    ) {
        $this->urlGenerator = $urlGenerator;
        $this->tokenStorage = $tokenStorage;
    }

    public function start(Request $request, AuthenticationException $authException = null): Response
    {
        // Forcer la déconnexion
        $this->tokenStorage->setToken(null);
        if ($request->hasSession()) {
            $request->getSession()->invalidate();
        }

        // Si c'est une requête AJAX ou API
        if ($request->isXmlHttpRequest() || $this->isApiRequest($request)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Authentification requise'
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Pour les requêtes normales, rediriger vers la page de connexion
        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }

    private function isApiRequest(Request $request): bool
    {
        return str_starts_with($request->getPathInfo(), '/api/') ||
               $request->headers->get('Accept') === 'application/json';
    }
}
