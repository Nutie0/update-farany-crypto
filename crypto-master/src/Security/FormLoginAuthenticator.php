<?php

namespace App\Security;

use App\Entity\Utilisateur;
use App\Entity\Portefeuille;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Psr\Log\LoggerInterface;

class FormLoginAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';

    private $entityManager;
    private $urlGenerator;
    private $httpClient;
    private $params;
    private $userApiUrl;
    private $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        UrlGeneratorInterface $urlGenerator,
        HttpClientInterface $httpClient,
        ParameterBagInterface $params,
        LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->urlGenerator = $urlGenerator;
        $this->httpClient = $httpClient;
        $this->params = $params;
        $this->userApiUrl = $this->params->get('user_api_url');
        $this->logger = $logger;
    }

    public function authenticate(Request $request): Passport
    {
        $username = $request->request->get('_username', '');
        $password = $request->request->get('_password', '');
        $csrfToken = $request->request->get('_csrf_token');

        $this->logger->info('Tentative d\'authentification', [
            'username' => $username,
            'csrf_token_present' => !empty($csrfToken)
        ]);

        if (empty($username) || empty($password)) {
            $this->logger->error('Identifiants manquants');
            throw new CustomUserMessageAuthenticationException('Email et mot de passe requis');
        }

        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $username);

        try {
            $this->logger->info('Appel API pour authentification', [
                'url' => $this->userApiUrl . '/api/Auth/login'
            ]);
            
            // Appel à l'API UserApi pour l'authentification
            $response = $this->httpClient->request('POST', $this->userApiUrl . '/api/Auth/login', [
                'json' => [
                    'email' => $username,
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
            $content = $response->getContent();
            
            $this->logger->info('Réponse API reçue', [
                'status_code' => $statusCode,
                'content' => $content
            ]);
            
            if ($statusCode !== 200) {
                $this->logger->error('Échec de l\'authentification API', [
                    'status_code' => $statusCode,
                    'response' => $content
                ]);
                throw new CustomUserMessageAuthenticationException('Identifiants invalides');
            }

            $responseData = json_decode($content, true);
            if (!$responseData || !isset($responseData['token'])) {
                $this->logger->error('Réponse API invalide', [
                    'response_data' => $responseData
                ]);
                throw new CustomUserMessageAuthenticationException('Réponse API invalide');
            }

            return new SelfValidatingPassport(
                new UserBadge($username, function($userIdentifier) use ($responseData) {
                    $this->logger->info('Recherche/création de l\'utilisateur local', [
                        'email' => $userIdentifier
                    ]);

                    $user = $this->entityManager->getRepository(Utilisateur::class)
                        ->findOneBy(['email' => $userIdentifier]);
                    
                    if (!$user) {
                        $this->logger->info('Création d\'un nouvel utilisateur');
                        $user = new Utilisateur();
                        $user->setEmail($userIdentifier);
                        $user->setRoles(['ROLE_USER']);
                        
                        // Créer un portefeuille pour le nouvel utilisateur
                        $portefeuille = new Portefeuille();
                        $portefeuille->setUtilisateur($user);
                        $portefeuille->setSoldeUtilisateur(0);
                        $this->entityManager->persist($portefeuille);
                    }
                    
                    $user->setToken($responseData['token']);
                    $this->entityManager->persist($user);
                    $this->entityManager->flush();
                    
                    return $user;
                }),
                [
                    new CsrfTokenBadge('authenticate', $csrfToken),
                    new RememberMeBadge()
                ]
            );

        } catch (CustomUserMessageAuthenticationException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Erreur inattendue lors de l\'authentification', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new CustomUserMessageAuthenticationException('Une erreur est survenue lors de l\'authentification');
        }
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $this->logger->info('Authentification réussie, redirection vers la page d\'accueil', [
            'user' => $token->getUserIdentifier(),
            'roles' => $token->getRoleNames()
        ]);
        
        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            $this->logger->info('Redirection vers le chemin cible', [
                'target_path' => $targetPath
            ]);
            return new RedirectResponse($targetPath);
        }

        return new RedirectResponse($this->urlGenerator->generate('app_home'));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}
