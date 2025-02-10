<?php

namespace App\Service;

use App\Entity\VariationCrypto;

class StatisticsService
{
    public function calculateQuartile(array $variations): ?array
    {
        if (empty($variations)) {
            return null;
        }

        $count = count($variations);
        $position = floor(($count + 1) / 4);
        
        return [
            'valeur' => $variations[$position]->getPrixEvoluer(),
            'date' => $variations[$position]->getDateVariation(),
            'nombre_variations' => $count,
            'pourcentage' => $variations[$position]->getPourcentageVariation()
        ];
    }

    public function calculateMax(array $variations): ?array
    {
        if (empty($variations)) {
            return null;
        }

        $maxVariation = end($variations); // Le tableau est déjà trié par prix
        
        return [
            'valeur' => $maxVariation->getPrixEvoluer(),
            'date' => $maxVariation->getDateVariation(),
            'nombre_variations' => count($variations),
            'pourcentage' => $maxVariation->getPourcentageVariation()
        ];
    }

    public function calculateMin(array $variations): ?array
    {
        if (empty($variations)) {
            return null;
        }

        $minVariation = reset($variations); // Le tableau est déjà trié par prix
        
        return [
            'valeur' => $minVariation->getPrixEvoluer(),
            'date' => $minVariation->getDateVariation(),
            'nombre_variations' => count($variations),
            'pourcentage' => $minVariation->getPourcentageVariation()
        ];
    }

    public function calculateMean(array $variations): ?array
    {
        if (empty($variations)) {
            return null;
        }

        $count = count($variations);
        $sum = 0;
        $sumPercentage = 0;
        $medianVariation = $variations[floor($count/2)];

        foreach ($variations as $variation) {
            $sum += floatval($variation->getPrixEvoluer());
            $sumPercentage += floatval($variation->getPourcentageVariation());
        }

        return [
            'valeur' => $sum / $count,
            'date' => $medianVariation->getDateVariation(),
            'nombre_variations' => $count,
            'pourcentage' => $sumPercentage / $count
        ];
    }

    public function calculateStdDev(array $variations): ?array
    {
        if (empty($variations)) {
            return null;
        }

        $count = count($variations);
        $mean = 0;
        $sumSquares = 0;
        $medianVariation = $variations[floor($count/2)];

        // Calculer la moyenne
        foreach ($variations as $variation) {
            $mean += floatval($variation->getPrixEvoluer());
        }
        $mean = $mean / $count;

        // Calculer la somme des carrés des écarts
        foreach ($variations as $variation) {
            $diff = floatval($variation->getPrixEvoluer()) - $mean;
            $sumSquares += $diff * $diff;
        }

        // Calculer l'écart-type
        $stdDev = sqrt($sumSquares / $count);

        return [
            'valeur' => $stdDev,
            'date' => $medianVariation->getDateVariation(),
            'nombre_variations' => $count,
            'pourcentage' => null // Pas pertinent pour l'écart-type
        ];
    }
}
