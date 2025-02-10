<?php

namespace App\Service;

use App\Entity\Utilisateur;
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
        string $userApiUrl = 'http://localhost:5280',
        LoggerInterface $logger
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
}
