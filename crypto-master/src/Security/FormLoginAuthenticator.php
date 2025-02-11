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

    public function supports(Request $request): bool
    {
        $this->logger->info('Vérification du support de la requête', [
            'path' => $request->getPathInfo(),
            'method' => $request->getMethod(),
            'is_xhr' => $request->isXmlHttpRequest(),
            'content_type' => $request->headers->get('Content-Type')
        ]);

        return $request->isMethod('POST') && 
               ($request->getPathInfo() === '/login' || $request->getPathInfo() === '/api/auth/login');
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
                    'content' => $content
                ]);

                $errorData = json_decode($content, true);
                $errorMessage = $errorData['message'] ?? 'Erreur d\'authentification';
                
                // Vérifier si l'erreur est liée à la vérification de l'email
                if (strpos($errorMessage, 'verify your email') !== false) {
                    throw new CustomUserMessageAuthenticationException('Veuillez vérifier votre adresse email avant de vous connecter.');
                }
                
                throw new CustomUserMessageAuthenticationException($errorMessage);
            }

            $responseData = json_decode($content, true);
            if (!isset($responseData['token'])) {
                $this->logger->error('Token manquant dans la réponse API');
                throw new CustomUserMessageAuthenticationException('Une erreur technique est survenue.');
            }

            // Mettre à jour ou créer l'utilisateur local
            $user = $this->entityManager->getRepository(Utilisateur::class)->findOneBy(['email' => $username]);
            if (!$user) {
                $user = new Utilisateur();
                $user->setEmail($username);
                $user->setRoles(['ROLE_USER']);
            }
            $user->setToken($responseData['token']);
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            return new SelfValidatingPassport(
                new UserBadge($username),
                [
                    new CsrfTokenBadge('authenticate', $csrfToken),
                    new RememberMeBadge()
                ]
            );

        } catch (CustomUserMessageAuthenticationException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de l\'authentification', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new CustomUserMessageAuthenticationException('Une erreur technique est survenue. Veuillez réessayer plus tard.');
        }
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $this->logger->info('Authentification réussie', [
            'user' => $token->getUserIdentifier(),
            'firewall' => $firewallName
        ]);

        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        return new RedirectResponse($this->urlGenerator->generate('app_home'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $this->logger->error('Échec de l\'authentification', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);

        if ($request->hasSession()) {
            $request->getSession()->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, $exception);
        }

        $url = $this->getLoginUrl($request);

        return new RedirectResponse($url);
    }

    public function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}
