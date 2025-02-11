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

    #[Route('/register', name: 'register', methods: ['GET', 'POST'])]
    public function register(Request $request): Response
    {
        // Si c'est une requête GET, afficher le formulaire
        if ($request->isMethod('GET')) {
            return $this->render('registration/register.html.twig');
        }

        $userData = [
            'nom' => $request->request->get('nom'),
            'email' => $request->request->get('email'),
            'password' => $request->request->get('password')
        ];

        // Validation côté serveur
        if (empty($userData['nom']) || empty($userData['email']) || empty($userData['password'])) {
            return $this->render('registration/register.html.twig', [
                'error' => 'Tous les champs sont obligatoires',
                'last_email' => $userData['email'],
                'last_nom' => $userData['nom']
            ]);
        }

        $result = $this->userSyncService->registerUser($userData);

        if ($result['success']) {
            // Rediriger vers la page de login avec un message de succès
            $this->addFlash('success', $result['message']);
            return $this->redirectToRoute('app_login');
        }

        // En cas d'erreur, retourner au formulaire avec le message d'erreur
        return $this->render('registration/register.html.twig', [
            'error' => $result['message'],
            'last_email' => $userData['email'],
            'last_nom' => $userData['nom']
        ]);
    }

    // src/Controller/Api/AuthController.php
    // #[Route('/verify-email', name: 'verify_email', methods: ['GET'])]
    // public function verifyEmail(Request $request): Response
    // {
    //     $token = $request->query->get('token');

    //     if (!$token) {
    //         $this->addFlash('error', 'Le lien de vérification est invalide.');
    //         return $this->redirectToRoute('app_login');
    //     }

    //     try {
    //         $result = $this->userSyncService->verifyEmail($token);

    //         if ($result['success']) {
    //             // Si la vérification est réussie, on met à jour l'utilisateur local si nécessaire
    //             $this->addFlash('success', $result['message']);
    //         } else {
    //             $this->addFlash('error', $result['message']);
    //         }
    //     } catch (\Exception $e) {
    //         $this->addFlash('error', 'Une erreur est survenue lors de la vérification de votre email.');
    //     }

    //     return $this->redirectToRoute('app_login');
    // }


    #[Route('/logout', name: 'logout', methods: ['POST'])]
    public function logout(): Response
    {
        // Cette méthode peut rester vide car Symfony gère la déconnexion
        throw new \Exception('Cette méthode ne devrait jamais être appelée. Vérifiez votre configuration de sécurité.');
    }
}
