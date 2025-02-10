<?php

namespace App\Security;

use App\Entity\Utilisateur;
use App\Entity\Portefeuille;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Psr\Log\LoggerInterface;

class JsonLoginAuthenticator extends AbstractAuthenticator
{
    private $entityManager;
    private $urlGenerator;
    private $httpClient;
    private $tokenStorage;
    private $params;
    private $userApiUrl;
    private $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        UrlGeneratorInterface $urlGenerator,
        HttpClientInterface $httpClient,
        TokenStorageInterface $tokenStorage,
        ParameterBagInterface $params,
        LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->urlGenerator = $urlGenerator;
        $this->httpClient = $httpClient;
        $this->tokenStorage = $tokenStorage;
        $this->params = $params;
        $this->userApiUrl = $this->params->get('user_api_url');
        $this->logger = $logger;
    }

    public function supports(Request $request): ?bool
    {
        return $request->getPathInfo() === '/api/auth/login' && 
               $request->isMethod('POST') && 
               $request->headers->get('Content-Type') === 'application/json';
    }

    public function authenticate(Request $request): Passport
    {
        try {
            $content = $request->getContent();
            $data = json_decode($content, true);

            if (!$data) {
                throw new CustomUserMessageAuthenticationException('Données JSON invalides');
            }

            if (empty($data['email']) || empty($data['password'])) {
                throw new CustomUserMessageAuthenticationException('Email et mot de passe requis');
            }

            try {
                $this->logger->info('Tentative de connexion pour l\'email: ' . $data['email']);
                $this->logger->info('URL de l\'API: ' . $this->userApiUrl);
                
                // Appel à l'API UserApi pour l'authentification
                $response = $this->httpClient->request('POST', $this->userApiUrl . '/api/Auth/login', [
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
                $content = $response->getContent();
                
                $this->logger->info('Réponse API - Status: ' . $statusCode);
                $this->logger->info('Réponse API - Contenu: ' . $content);
                
                if ($statusCode !== 200) {
                    throw new CustomUserMessageAuthenticationException('Erreur d\'authentification: ' . $content);
                }

                $responseData = json_decode($content, true);
                if (!$responseData || !isset($responseData['token'])) {
                    throw new CustomUserMessageAuthenticationException('Réponse API invalide');
                }

                // Créer ou mettre à jour l'utilisateur local
                return new SelfValidatingPassport(
                    new UserBadge($data['email'], function($userIdentifier) use ($responseData) {
                        $user = $this->entityManager->getRepository(Utilisateur::class)->findOneBy(['email' => $userIdentifier]);
                        
                        if (!$user) {
                            $user = new Utilisateur();
                            $user->setEmail($userIdentifier);
                            $user->setRoles(['ROLE_USER']);
                        }

                        $user->setToken($responseData['token']);
                        $this->entityManager->persist($user);
                        $this->entityManager->flush();
                        
                        return $user;
                    })
                );

            } catch (\Symfony\Contracts\HttpClient\Exception\TransportException $e) {
                $this->logger->error('Transport Exception: ' . $e->getMessage());
                throw new CustomUserMessageAuthenticationException('Impossible de contacter le serveur d\'authentification');
            } catch (\Symfony\Contracts\HttpClient\Exception\ClientException $e) {
                $this->logger->error('Client Exception: ' . $e->getMessage());
                throw new CustomUserMessageAuthenticationException('Identifiants invalides');
            } catch (\Exception $e) {
                $this->logger->error('Exception générale: ' . $e->getMessage());
                throw new CustomUserMessageAuthenticationException('Erreur lors de l\'authentification: ' . $e->getMessage());
            }
        } catch (\Exception $e) {
            $this->logger->error('Exception principale: ' . $e->getMessage());
            throw new CustomUserMessageAuthenticationException($e->getMessage());
        }
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $user = $token->getUser();
        
        // Créer un portefeuille si l'utilisateur n'en a pas
        if ($user instanceof Utilisateur) {
            $portefeuille = $this->entityManager->getRepository(Portefeuille::class)
                ->findOneBy(['utilisateur' => $user]);

            if (!$portefeuille) {
                $portefeuille = new Portefeuille();
                $portefeuille->setUtilisateur($user);
                $portefeuille->setSoldeUtilisateur(0);
                
                $this->entityManager->persist($portefeuille);
                $this->entityManager->flush();
            }
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'Authentification réussie',
            'token' => $user->getToken()
        ]);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse([
            'success' => false,
            'message' => $exception->getMessage()
        ], Response::HTTP_UNAUTHORIZED);
    }
}
