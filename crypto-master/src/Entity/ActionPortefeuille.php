<?php

namespace App\Entity;

use App\Repository\ActionPortefeuilleRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ActionPortefeuilleRepository::class)]
#[ORM\Table(name: 'action_portefeuille')]
class ActionPortefeuille
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: "id_action")]
    private ?int $id = null;

    #[ORM\Column(name: "type_action", length: 50)]
    private ?string $typeAction = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTypeAction(): ?string
    {
        return $this->typeAction;
    }

    public function setTypeAction(string $typeAction): static
    {
        $this->typeAction = $typeAction;
        return $this;
    }
}
