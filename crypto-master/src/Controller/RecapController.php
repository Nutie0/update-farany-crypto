<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;

#[Route('/recap')]
class RecapController extends AbstractController
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route('/', name: 'app_recap')]
    public function index(Request $request): Response
    {
        // Récupérer la date max du filtre ou utiliser la date actuelle
        $dateMax = $request->query->get('date_max') 
            ? new \DateTime($request->query->get('date_max')) 
            : new \DateTime();

        try {
            // Charger et exécuter la requête SQL
            $sqlPath = __DIR__ . '/../Repository/RecapRepository.sql';
            
            if (!file_exists($sqlPath)) {
                throw new \Exception("Le fichier SQL n'existe pas : $sqlPath");
            }

            $sql = file_get_contents($sqlPath);
            if ($sql === false) {
                throw new \Exception("Impossible de lire le fichier SQL : $sqlPath");
            }

            $stmt = $this->entityManager->getConnection()->prepare($sql);
            
            // Exécuter avec la date max comme paramètre (utilisé trois fois dans la requête)
            $dateFormatted = $dateMax->format('Y-m-d H:i:s');
            $result = $stmt->executeQuery([
                $dateFormatted, // Pour portefeuille_fille
                $dateFormatted, // Pour historique_transactions
                $dateFormatted  // Pour variationcrypto
            ]);

            $users = $result->fetchAllAssociative();

            // Ajouter un message flash si aucune donnée n'est trouvée
            if (empty($users)) {
                $this->addFlash('info', 'Aucune donnée trouvée pour la période sélectionnée.');
            }

        } catch (\Exception $e) {
            // En cas d'erreur, ajouter un message flash détaillé
            $errorMessage = sprintf(
                'Erreur : %s. Trace : %s',
                $e->getMessage(),
                $e->getTraceAsString()
            );
            $this->addFlash('error', $errorMessage);
            error_log($errorMessage);
            $users = [];
        }

        return $this->render('recap/index.html.twig', [
            'users' => $users,
            'date_max' => $dateMax
        ]);
    }
}
