<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use App\Repository\UtilisateurRepository;
use App\Entity\Utilisateur;

class UserApiService
{
    private $httpClient;
    private $apiUrl;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
        $this->apiUrl = 'http://localhost:5280'; // URL de l'API
    }

    public function getUserInfo(string $token): array
    {
        try {
            $response = $this->httpClient->request('GET', $this->apiUrl . '/api/Protected/user', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token
                ]
            ]);

            if ($response->getStatusCode() === 200) {
                return $response->toArray();
            }

            throw new CustomUserMessageAuthenticationException('Impossible de récupérer les informations utilisateur');
        } catch (\Exception $e) {
            throw new CustomUserMessageAuthenticationException('Erreur lors de la récupération des informations utilisateur: ' . $e->getMessage());
        }
    }

    public function storeUserInfo(array $userInfo, UtilisateurRepository $utilisateurRepository): void
    {
        $utilisateur = new Utilisateur();
        $utilisateur->setEmail($userInfo['email']);
        $utilisateur->setNom($userInfo['nom']);
        // Add other fields as necessary

        $utilisateurRepository->save($utilisateur, true);
    }
}
