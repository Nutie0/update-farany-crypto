<?php

namespace App\Form;

use App\Entity\Crypto;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CryptoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nomCrypto', TextType::class, [
                'label' => 'Nom de la crypto',
                'attr' => ['class' => 'form-control']
            ])
            ->add('nbrCrypto', NumberType::class, [
                'label' => 'Nombre de crypto',
                'attr' => ['class' => 'form-control']
            ])
            ->add('prixInitialeCrypto', TextType::class, [
                'label' => 'Prix initial',
                'required' => false,
                'attr' => ['class' => 'form-control']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Crypto::class,
        ]);
    }
}
