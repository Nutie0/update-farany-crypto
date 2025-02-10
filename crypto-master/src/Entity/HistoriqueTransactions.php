<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'historique_transactions')]
class HistoriqueTransactions
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'id', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'id_portefeuille', type: 'integer')]
    private int $idPortefeuille;

    #[ORM\Column(name: 'id_crypto', type: 'integer')]
    private int $idCrypto;

    #[ORM\Column(name: 'nom_crypto', type: 'string', length: 50)]
    private string $nomCrypto;

    #[ORM\Column(name: 'type_action', type: 'string', length: 50)]
    private string $typeAction;

    #[ORM\Column(name: 'nbrcrypto', type: 'decimal', precision: 15, scale: 8)]
    private string $nbrcrypto;

    #[ORM\Column(name: 'prix', type: 'decimal', precision: 15, scale: 2)]
    private string $prix;

    #[ORM\Column(name: 'prixtotal', type: 'decimal', precision: 15, scale: 2)]
    private string $prixtotal;

    #[ORM\Column(name: 'taux_commission', type: 'decimal', precision: 4, scale: 2, nullable: true)]
    private ?string $tauxCommission = null;

    #[ORM\Column(name: 'montant_commission', type: 'decimal', precision: 15, scale: 2, nullable: true)]
    private ?string $montantCommission = null;

    #[ORM\Column(name: 'prix_total_avec_commission', type: 'decimal', precision: 15, scale: 2, nullable: true)]
    private ?string $prixTotalAvecCommission = null;

    #[ORM\Column(name: 'date_action', type: 'datetime')]
    private \DateTime $dateAction;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIdPortefeuille(): int
    {
        return $this->idPortefeuille;
    }

    public function setIdPortefeuille(int $idPortefeuille): self
    {
        $this->idPortefeuille = $idPortefeuille;
        return $this;
    }

    public function getIdCrypto(): int
    {
        return $this->idCrypto;
    }

    public function setIdCrypto(int $idCrypto): self
    {
        $this->idCrypto = $idCrypto;
        return $this;
    }

    public function getNomCrypto(): string
    {
        return $this->nomCrypto;
    }

    public function setNomCrypto(string $nomCrypto): self
    {
        $this->nomCrypto = $nomCrypto;
        return $this;
    }

    public function getTypeAction(): string
    {
        return $this->typeAction;
    }

    public function setTypeAction(string $typeAction): self
    {
        $this->typeAction = $typeAction;
        return $this;
    }

    public function getNbrcrypto(): string
    {
        return $this->nbrcrypto;
    }

    public function setNbrcrypto(string $nbrcrypto): self
    {
        $this->nbrcrypto = $nbrcrypto;
        return $this;
    }

    public function getPrix(): string
    {
        return $this->prix;
    }

    public function setPrix(string $prix): self
    {
        $this->prix = $prix;
        return $this;
    }

    public function getPrixtotal(): string
    {
        return $this->prixtotal;
    }

    public function setPrixtotal(string $prixtotal): self
    {
        $this->prixtotal = $prixtotal;
        return $this;
    }

    public function getTauxCommission(): ?string
    {
        return $this->tauxCommission;
    }

    public function setTauxCommission(?string $tauxCommission): self
    {
        $this->tauxCommission = $tauxCommission;
        return $this;
    }

    public function getMontantCommission(): ?string
    {
        return $this->montantCommission;
    }

    public function setMontantCommission(?string $montantCommission): self
    {
        $this->montantCommission = $montantCommission;
        return $this;
    }

    public function getPrixTotalAvecCommission(): ?string
    {
        return $this->prixTotalAvecCommission;
    }

    public function setPrixTotalAvecCommission(?string $prixTotalAvecCommission): self
    {
        $this->prixTotalAvecCommission = $prixTotalAvecCommission;
        return $this;
    }

    public function getDateAction(): \DateTime
    {
        return $this->dateAction;
    }

    public function setDateAction(\DateTime $dateAction): self
    {
        $this->dateAction = $dateAction;
        return $this;
    }
}
