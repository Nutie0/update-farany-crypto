<?php

namespace App\Controller;

use App\Entity\Crypto;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api', name: 'api_')]
class ApiController extends AbstractController
{
    private $security;
    private EntityManagerInterface $entityManager;

    public function __construct(Security $security, EntityManagerInterface $entityManager)
    {
        $this->security = $security;
        $this->entityManager = $entityManager;
    }

    #[Route('/hello', name: 'hello', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function hello(): JsonResponse
    {
        $user = $this->security->getUser();
        $name = $user->getNom() ?? $user->getEmail();

        return $this->json([
            'message' => 'Hello ' . $name . '! You are successfully authenticated.'
        ]);
    }

    #[Route('/cryptos', name: 'api_cryptos', methods: ['GET'])]
    public function getCryptos(): JsonResponse
    {
        $cryptoRepository = $this->entityManager->getRepository(Crypto::class);
        $cryptos = $cryptoRepository->findAll();

        $cryptoData = [];
        foreach ($cryptos as $crypto) {
            $lastVariation = $crypto->lastVariation;
            $cryptoData[] = [
                'idCrypto' => $crypto->getIdCrypto(),
                'nomCrypto' => $crypto->getNomCrypto(),
                'prixInitialeCrypto' => $crypto->getPrixInitialeCrypto(),
                'prix_actuel' => $lastVariation ? $lastVariation->getPrixActuel() : $crypto->getPrixInitialeCrypto(),
                'derniere_maj' => $lastVariation ? $lastVariation->getDateVariation()->format('Y-m-d H:i:s') : null,
                'variation' => $lastVariation ? $lastVariation->getVariation() : null
            ];
        }

        return new JsonResponse($cryptoData);
    }
}
