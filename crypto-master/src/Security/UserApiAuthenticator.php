<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CustomCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class UserApiAuthenticator extends AbstractAuthenticator
{
    private HttpClientInterface $client;
    private string $userApiUrl;

    public function __construct(HttpClientInterface $client, string $userApiUrl)
    {
        $this->client = $client;
        $this->userApiUrl = $userApiUrl;
    }

    public function supports(Request $request): ?bool
    {
        return $request->headers->has('Authorization');
    }

    public function authenticate(Request $request): Passport
    {
        $apiToken = $request->headers->get('Authorization');
        if (null === $apiToken) {
            throw new CustomUserMessageAuthenticationException('No API token provided');
        }

        return new Passport(
            new UserBadge($apiToken),
            new CustomCredentials(
                function ($credentials, UserInterface $user) {
                    try {
                        $response = $this->client->request(
                            'GET',
                            $this->userApiUrl . '/api/auth/validate',
                            [
                                'headers' => [
                                    'Authorization' => 'Bearer ' . $credentials,
                                ],
                            ]
                        );

                        return $response->getStatusCode() === 200;
                    } catch (\Exception $e) {
                        return false;
                    }
                },
                $apiToken
            )
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $data = [
            'message' => strtr($exception->getMessageKey(), $exception->getMessageData())
        ];

        return new JsonResponse($data, Response::HTTP_UNAUTHORIZED);
    }
}
