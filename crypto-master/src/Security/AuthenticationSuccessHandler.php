<?php

namespace App\Security;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AuthenticationSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    private $httpClient;
    private $entityManager;

    public function __construct(
        HttpClientInterface $httpClient,
        EntityManagerInterface $entityManager
    ) {
        $this->httpClient = $httpClient;
        $this->entityManager = $entityManager;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        try {
            // Récupérer les identifiants de la requête
            $credentials = json_decode($request->getContent(), true);

            // Faire la requête d'authentification à l'API
            $response = $this->httpClient->request('POST', 'http://localhost:5280/api/auth/login', [
                'json' => [
                    'email' => $credentials['email'],
                    'password' => $credentials['password']
                ]
            ]);

            $data = $response->toArray();

            if ($response->getStatusCode() === 200 && isset($data['token'])) {
                // Récupérer ou créer l'utilisateur local
                $user = $this->entityManager->getRepository(Utilisateur::class)->findOneBy(['email' => $credentials['email']]);
                if (!$user) {
                    $user = new Utilisateur();
                    $user->setEmail($credentials['email']);
                    $user->setRoles(['ROLE_USER']);
                }

                // Mettre à jour le token
                $user->setToken($data['token']);
                $this->entityManager->persist($user);
                $this->entityManager->flush();

                return new JsonResponse([
                    'token' => $data['token'],
                    'message' => 'Authentification réussie'
                ]);
            }

            return new JsonResponse([
                'message' => 'Échec de l\'authentification avec l\'API'
            ], Response::HTTP_UNAUTHORIZED);

        } catch (\Exception $e) {
            return new JsonResponse([
                'message' => 'Une erreur est survenue lors de l\'authentification'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
