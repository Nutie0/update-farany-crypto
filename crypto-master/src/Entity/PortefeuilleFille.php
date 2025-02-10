<?php

namespace App\Entity;

use App\Repository\PortefeuilleFilleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PortefeuilleFilleRepository::class)]
class PortefeuilleFille
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: "id_portefeuille_fille")]
    private ?int $id = null;

    #[ORM\Column(name: "id_crypto")]
    private ?int $idCrypto = null;

    #[ORM\Column(name: "id_portefeuille")]
    private ?int $idPortefeuille = null;

    #[ORM\Column(name: "id_action")]
    private ?int $idAction = null;

    #[ORM\Column(name: "date_action", type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateAction = null;

    #[ORM\Column(name: "nbr_crypto")]
    private ?int $nbrCrypto = null;

    #[ORM\Column(name: "prix_total_crypto", length: 50, nullable: true)]
    private ?string $prixTotalCrypto = null;

    #[ORM\Column(name: "prix_achat")]
    private ?float $prixAchat = null;

    #[ORM\Column(name: "montant_commission", type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $montantCommission = null;

    #[ORM\Column(name: "taux_commission", type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $tauxCommission = null;

    #[ORM\Column(name: "prix_total_avec_commission", type: Types::DECIMAL, precision: 20, scale: 2, nullable: true)]
    private ?string $prixTotalAvecCommission = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: "id_crypto", referencedColumnName: "id_crypto", nullable: false)]
    private ?Crypto $crypto = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: "id_portefeuille", referencedColumnName: "id", nullable: false)]
    private ?Portefeuille $portefeuille = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: "id_action", referencedColumnName: "id_action", nullable: false)]
    private ?ActionPortefeuille $actionPortefeuille = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIdCrypto(): ?int
    {
        return $this->idCrypto;
    }

    public function getCrypto(): ?Crypto
    {
        return $this->crypto;
    }

    public function setCrypto(?Crypto $crypto): static
    {
        $this->crypto = $crypto;
        $this->idCrypto = $crypto?->getIdCrypto();
        return $this;
    }

    public function getIdPortefeuille(): ?int
    {
        return $this->idPortefeuille;
    }

    public function getPortefeuille(): ?Portefeuille
    {
        return $this->portefeuille;
    }

    public function setPortefeuille(?Portefeuille $portefeuille): static
    {
        $this->portefeuille = $portefeuille;
        $this->idPortefeuille = $portefeuille?->getId();
        return $this;
    }

    public function getIdAction(): ?int
    {
        return $this->idAction;
    }

    public function setIdAction(int $idAction): static
    {
        $this->idAction = $idAction;
        return $this;
    }

    public function getDateAction(): ?\DateTimeInterface
    {
        return $this->dateAction;
    }

    public function setDateAction(\DateTimeInterface $dateAction): static
    {
        $this->dateAction = $dateAction;
        return $this;
    }

    public function getNbrCrypto(): ?int
    {
        return $this->nbrCrypto;
    }

    public function setNbrCrypto(int $nbrCrypto): static
    {
        $this->nbrCrypto = $nbrCrypto;
        return $this;
    }

    public function getPrixTotalCrypto(): ?string
    {
        return $this->prixTotalCrypto;
    }

    public function setPrixTotalCrypto(?string $prixTotalCrypto): static
    {
        $this->prixTotalCrypto = $prixTotalCrypto;
        return $this;
    }

    public function getPrixAchat(): ?float
    {
        return $this->prixAchat;
    }

    public function setPrixAchat(float $prixAchat): static
    {
        $this->prixAchat = $prixAchat;
        return $this;
    }

    public function getMontantCommission(): ?string
    {
        return $this->montantCommission;
    }

    public function setMontantCommission(?string $montantCommission): static
    {
        $this->montantCommission = $montantCommission;
        return $this;
    }

    public function getTauxCommission(): ?string
    {
        return $this->tauxCommission;
    }

    public function setTauxCommission(?string $tauxCommission): static
    {
        $this->tauxCommission = $tauxCommission;
        return $this;
    }

    public function getPrixTotalAvecCommission(): ?string
    {
        return $this->prixTotalAvecCommission;
    }

    public function setPrixTotalAvecCommission(?string $prixTotalAvecCommission): static
    {
        $this->prixTotalAvecCommission = $prixTotalAvecCommission;
        return $this;
    }

    public function getActionPortefeuille(): ?ActionPortefeuille
    {
        return $this->actionPortefeuille;
    }

    public function setActionPortefeuille(?ActionPortefeuille $actionPortefeuille): static
    {
        $this->actionPortefeuille = $actionPortefeuille;
        $this->idAction = $actionPortefeuille?->getId();
        return $this;
    }
}
