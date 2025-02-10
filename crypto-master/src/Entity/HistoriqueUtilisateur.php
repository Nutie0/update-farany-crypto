<?php

namespace App\Entity;

use App\Repository\HistoriqueUtilisateurRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: HistoriqueUtilisateurRepository::class)]
#[ORM\Table(name: 'historique_utilisateur')]
class HistoriqueUtilisateur
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'id_historique_utilisateur', type: Types::INTEGER)]
    private ?int $idHistoriqueUtilisateur = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2)]
    private ?string $somme = null;

    #[ORM\Column(name: 'id', nullable: false)]
    private ?int $portefeuilleId = null;

    #[ORM\ManyToOne(targetEntity: Portefeuille::class)]
    #[ORM\JoinColumn(name: 'id', referencedColumnName: 'id', nullable: false)]
    private ?Portefeuille $portefeuille = null;

    #[ORM\ManyToOne(targetEntity: ActionPortefeuille::class)]
    #[ORM\JoinColumn(name: 'id_action', referencedColumnName: 'id_action', nullable: false)]
    private ?ActionPortefeuille $action = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateHistorique = null;

    #[ORM\Column(type: Types::STRING, length: 20)]
    private ?string $statut = 'en_attente';

    public function getIdHistoriqueUtilisateur(): ?int
    {
        return $this->idHistoriqueUtilisateur;
    }

    public function getSomme(): ?string
    {
        return $this->somme;
    }

    public function setSomme(string $somme): self
    {
        $this->somme = $somme;
        return $this;
    }

    public function getPortefeuilleId(): ?int
    {
        return $this->portefeuilleId;
    }

    public function setPortefeuilleId(int $portefeuilleId): self
    {
        $this->portefeuilleId = $portefeuilleId;
        return $this;
    }

    public function getPortefeuille(): ?Portefeuille
    {
        return $this->portefeuille;
    }

    public function setPortefeuille(?Portefeuille $portefeuille): self
    {
        $this->portefeuille = $portefeuille;
        $this->portefeuilleId = $portefeuille ? $portefeuille->getId() : null;
        return $this;
    }

    public function getAction(): ?ActionPortefeuille
    {
        return $this->action;
    }

    public function setAction(?ActionPortefeuille $action): self
    {
        $this->action = $action;
        return $this;
    }

    public function getDateHistorique(): ?\DateTimeInterface
    {
        return $this->dateHistorique;
    }

    public function setDateHistorique(\DateTimeInterface $dateHistorique): self
    {
        $this->dateHistorique = $dateHistorique;
        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): self
    {
        $this->statut = $statut;
        return $this;
    }
}
