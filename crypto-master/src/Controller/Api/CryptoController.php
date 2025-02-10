<?php

namespace App\Controller\Api;

use App\Entity\Crypto;
use App\Service\ApiResponseFormatter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/crypto', name: 'api_crypto_')]
class CryptoController extends AbstractController
{
    private $entityManager;
    private $responseFormatter;

    public function __construct(
        EntityManagerInterface $entityManager,
        ApiResponseFormatter $responseFormatter
    ) {
        $this->entityManager = $entityManager;
        $this->responseFormatter = $responseFormatter;
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): Response
    {
        $cryptos = $this->entityManager->getRepository(Crypto::class)->findAllSorted();
        return $this->responseFormatter->formatResponse($cryptos);
    }

    #[Route('/{id}', name: 'get', methods: ['GET'])]
    public function get(int $id): Response
    {
        $crypto = $this->entityManager->getRepository(Crypto::class)->find($id);
        
        if (!$crypto) {
            return $this->responseFormatter->formatError('Crypto not found', 404);
        }

        return $this->responseFormatter->formatResponse($crypto);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['nomCrypto']) || !isset($data['prixInitialeCrypto'])) {
            return $this->responseFormatter->formatError('Missing required fields', 400);
        }

        $crypto = new Crypto();
        $crypto->setNomCrypto($data['nomCrypto']);
        $crypto->setPrixInitialeCrypto($data['prixInitialeCrypto']);
        
        $this->entityManager->persist($crypto);
        $this->entityManager->flush();

        return $this->responseFormatter->formatResponse($crypto, 'success', 201);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(int $id, Request $request): Response
    {
        $crypto = $this->entityManager->getRepository(Crypto::class)->find($id);
        
        if (!$crypto) {
            return $this->responseFormatter->formatError('Crypto not found', 404);
        }

        $data = json_decode($request->getContent(), true);
        
        if (isset($data['nomCrypto'])) {
            $crypto->setNomCrypto($data['nomCrypto']);
        }
        if (isset($data['prixInitialeCrypto'])) {
            $crypto->setPrixInitialeCrypto($data['prixInitialeCrypto']);
        }

        $this->entityManager->flush();

        return $this->responseFormatter->formatResponse($crypto);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): Response
    {
        $crypto = $this->entityManager->getRepository(Crypto::class)->find($id);
        
        if (!$crypto) {
            return $this->responseFormatter->formatError('Crypto not found', 404);
        }

        $this->entityManager->remove($crypto);
        $this->entityManager->flush();

        return $this->responseFormatter->formatResponse(null, 'success', 204);
    }
}
