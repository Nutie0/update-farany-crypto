<?php

namespace App\Controller\Api;

use App\Service\ApiResponseFormatter;
use App\Service\UserSyncService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

#[Route('/api/auth', name: 'api_auth_')]
class AuthController extends AbstractController
{
    private $httpClient;
    private $responseFormatter;
    private $userApiUrl;
    private $userSyncService;

    public function __construct(
        HttpClientInterface $httpClient,
        ApiResponseFormatter $responseFormatter,
        string $userApiUrl,
        UserSyncService $userSyncService
    ) {
        $this->httpClient = $httpClient;
        $this->responseFormatter = $responseFormatter;
        $this->userApiUrl = $userApiUrl;
        $this->userSyncService = $userSyncService;
    }

    #[Route('/login', name: 'login', methods: ['GET', 'POST'])]
    public function login(Request $request, AuthenticationUtils $authenticationUtils): Response
    {
        // Si c'est une requête GET, afficher le formulaire de connexion
        if ($request->isMethod('GET')) {
            return $this->render('security/login.html.twig', [
                'error' => $authenticationUtils->getLastAuthenticationError(),
                'last_username' => $authenticationUtils->getLastUsername(),
            ]);
        }

        // Si c'est une requête POST, laisser le JsonLoginAuthenticator gérer l'authentification
        return $this->json(['message' => 'Cette route ne devrait pas être appelée directement.'], Response::HTTP_BAD_REQUEST);
    }

    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(Request $request): Response
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!isset($data['email']) || !isset($data['password'])) {
                return $this->responseFormatter->formatError('Email and password are required', Response::HTTP_BAD_REQUEST);
            }

            // Appel à l'API UserApi pour l'inscription
            $response = $this->httpClient->request('POST', $this->userApiUrl . '/api/Auth/register', [
                'json' => [
                    'email' => $data['email'],
                    'password' => $data['password']
                ],
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ],
                'verify_peer' => false,
                'verify_host' => false
            ]);

            $statusCode = $response->getStatusCode();
            $content = json_decode($response->getContent(), true);

            if ($statusCode === 200) {
                // Créer l'utilisateur local après l'inscription réussie
                $this->userSyncService->createOrUpdateLocalUser($data['email'], $content['token'] ?? null);
                
                return $this->responseFormatter->formatSuccess([
                    'message' => 'Registration successful',
                    'token' => $content['token'] ?? null
                ]);
            }

            return $this->responseFormatter->formatError('Registration failed: ' . ($content['message'] ?? 'Unknown error'), $statusCode);

        } catch (\Exception $e) {
            return $this->responseFormatter->formatError('Registration failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/logout', name: 'logout', methods: ['POST'])]
    public function logout(): Response
    {
        // Cette méthode peut rester vide car Symfony gère la déconnexion
        throw new \Exception('Cette méthode ne devrait jamais être appelée. Vérifiez votre configuration de sécurité.');
    }
}
