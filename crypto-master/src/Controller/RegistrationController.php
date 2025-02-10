<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Service\UserSyncService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class RegistrationController extends AbstractController
{
    private $httpClient;
    private $entityManager;
    private $userApiUrl;
    private $userSyncService;

    public function __construct(
        HttpClientInterface $httpClient,
        EntityManagerInterface $entityManager,
        ParameterBagInterface $params,
        UserSyncService $userSyncService
    ) {
        $this->httpClient = $httpClient;
        $this->entityManager = $entityManager;
        $this->userApiUrl = $params->get('user_api_url');
        $this->userSyncService = $userSyncService;
    }

    #[Route('/register', name: 'app_register')]
    public function register(Request $request): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $error = null;

        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');
            $password = $request->request->get('password');

            try {
                // Appel à l'API UserApi pour l'inscription
                $response = $this->httpClient->request('POST', $this->userApiUrl . '/api/Auth/register', [
                    'json' => [
                        'email' => $email,
                        'password' => $password
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

                if ($statusCode === 200 && isset($content['token'])) {
                    // Créer l'utilisateur local
                    $user = new Utilisateur();
                    $user->setEmail($email);
                    $user->setRoles(['ROLE_USER']);
                    $user->setToken($content['token']);

                    $this->entityManager->persist($user);
                    $this->entityManager->flush();

                    // Rediriger vers la page de connexion
                    $this->addFlash('success', 'Inscription réussie ! Vous pouvez maintenant vous connecter.');
                    return $this->redirectToRoute('app_login');
                } else {
                    $error = $content['message'] ?? 'Une erreur est survenue lors de l\'inscription.';
                }
            } catch (\Exception $e) {
                $error = 'Une erreur est survenue lors de l\'inscription.';
            }
        }

        return $this->render('registration/register.html.twig', [
            'error' => $error
        ]);
    }
}
