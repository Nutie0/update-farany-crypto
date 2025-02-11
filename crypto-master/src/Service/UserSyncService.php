<?php

namespace App\Service;

use App\Entity\Utilisateur;
use App\Entity\Portefeuille;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class UserSyncService
{
    private EntityManagerInterface $entityManager;
    private HttpClientInterface $httpClient;
    private string $userApiUrl;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        HttpClientInterface $httpClient,
        LoggerInterface $logger,
        string $userApiUrl = 'http://localhost:5000'
    ) {
        $this->entityManager = $entityManager;
        $this->httpClient = $httpClient;
        $this->userApiUrl = $userApiUrl;
        $this->logger = $logger;
    }

    public function validateTokenAndGetUser(string $token): ?Utilisateur
    {
        try {
            $this->logger->info('Tentative de validation du token auprès de l\'API: ' . substr($token, 0, 10) . '...');

            // Vérifier le token auprès de l'API externe
            $response = $this->httpClient->request('GET', $this->userApiUrl . '/api/Auth/validate', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json'
                ],
                'verify_peer' => false,
                'verify_host' => false
            ]);

            if ($response->getStatusCode() !== 200) {
                $this->logger->error('Token invalide: réponse ' . $response->getStatusCode() . ' de l\'API');
                return null;
            }

            $data = $response->toArray();
            if (!isset($data['email'])) {
                $this->logger->error('Email manquant dans la réponse de l\'API');
                return null;
            }

            // Rechercher l'utilisateur par email
            $user = $this->entityManager->getRepository(Utilisateur::class)->findOneBy(['email' => $data['email']]);

            if (!$user) {
                $this->logger->info('Création d\'un nouvel utilisateur: ' . $data['email']);
                // Créer l'utilisateur s'il n'existe pas
                $user = new Utilisateur();
                $user->setEmail($data['email']);
                $user->setRoles(['ROLE_USER']);
                $this->entityManager->persist($user);
                $this->entityManager->flush();
            }

            $user->setToken($token);
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            return $user;
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la validation du token: ' . $e->getMessage());
            return null;
        }
    }

    public function registerUser(array $userData): array
    {
        try {
            $this->logger->info('Données d\'inscription:', [
                'email' => $userData['email'],
                'nom' => $userData['nom'],
                'hasPassword' => !empty($userData['password'])
            ]);

            // Vérifier que toutes les données requises sont présentes
            if (empty($userData['email']) || empty($userData['password']) || empty($userData['nom'])) {
                return [
                    'success' => false,
                    'message' => 'Tous les champs sont obligatoires.'
                ];
            }

            // Vérifier le format de l'email
            if (!filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
                return [
                    'success' => false,
                    'message' => 'L\'adresse email n\'est pas valide.'
                ];
            }

            // Vérifier si l'utilisateur existe déjà localement
            $existingUser = $this->entityManager->getRepository(Utilisateur::class)
                ->findOneBy(['email' => $userData['email']]);
            
            if ($existingUser) {
                return [
                    'success' => false,
                    'message' => 'Un compte existe déjà avec cette adresse email.'
                ];
            }

            $this->logger->info('Envoi de la requête à l\'API:', [
                'url' => $this->userApiUrl . '/api/Auth/register',
                'method' => 'POST'
            ]);

            // Envoyer la requête à l'API
            $response = $this->httpClient->request('POST', $this->userApiUrl . '/api/Auth/register', [
                'json' => [
                    'email' => $userData['email'],
                    'password' => $userData['password'],
                    'nom' => $userData['nom']
                ],
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ],
                'verify_peer' => false,
                'verify_host' => false
            ]);

            $statusCode = $response->getStatusCode();
            $content = $response->getContent();
            $data = json_decode($content, true);

            $this->logger->info('Réponse de l\'API:', [
                'status' => $statusCode,
                'content' => $content
            ]);

            switch ($statusCode) {
                case 200:
                case 201:
                    return [
                        'success' => true,
                        'message' => 'Inscription réussie ! Un email de confirmation a été envoyé à votre adresse. Veuillez vérifier votre boîte de réception pour activer votre compte.'
                    ];

                case 400:
                    return [
                        'success' => false,
                        'message' => $data['message'] ?? 'Données d\'inscription invalides.'
                    ];

                case 409:
                    return [
                        'success' => false,
                        'message' => 'Un compte existe déjà avec cette adresse email.'
                    ];

                default:
                    return [
                        'success' => false,
                        'message' => 'Une erreur est survenue lors de l\'inscription. Veuillez réessayer plus tard.'
                    ];
            }

        } catch (\Exception $e) {
            $this->logger->error('Exception lors de l\'inscription:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Une erreur technique est survenue. Veuillez réessayer plus tard.'
            ];
        }
    }

    public function verifyEmail(string $token): array
    {
        try {
            $this->logger->info('Tentative de vérification d\'email avec le token: ' . substr($token, 0, 10) . '...');

            $response = $this->httpClient->request('GET', $this->userApiUrl . '/api/Auth/verify-email', [
                'query' => ['token' => $token],
                'verify_peer' => false,
                'verify_host' => false
            ]);

            $statusCode = $response->getStatusCode();
            $content = $response->getContent();
            $data = json_decode($content, true);

            $this->logger->info('Réponse de l\'API de vérification:', [
                'statusCode' => $statusCode,
                'content' => $content
            ]);

            switch ($statusCode) {
                case 200:
                    $this->logger->info('Vérification d\'email réussie');
                    return [
                        'success' => true,
                        'message' => 'Votre compte a été activé avec succès ! Vous pouvez maintenant vous connecter.'
                    ];

                case 400:
                    $message = $data['message'] ?? 'Le lien de vérification est invalide ou a expiré.';
                    return [
                        'success' => false,
                        'message' => $message
                    ];

                default:
                    $this->logger->error('Échec de la vérification d\'email');
                    return [
                        'success' => false,
                        'message' => 'Une erreur est survenue lors de la vérification. Veuillez réessayer.'
                    ];
            }
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la vérification d\'email:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'success' => false,
                'message' => 'Une erreur est survenue lors de la vérification de votre email. Veuillez réessayer.'
            ];
        }
    }
}
