<?php
// src/php/services/StatisticsHelper.php

class StatisticsHelper
{
    /**
     * Calcula la media (promedio) de un conjunto de datos.
     */
    public static function calculateMean(array $data): float
    {
        if (empty($data)) {
            return 0.0;
        }
        return array_sum($data) / count($data);
    }

    /**
     * Calcula la mediana (valor central) de un conjunto de datos.
     */
    public static function calculateMedian(array $data): float
    {
        if (empty($data)) {
            return 0.0;
        }
        sort($data);
        $count = count($data);
        $middle = floor(($count - 1) / 2);

        if ($count % 2) {
            // Impar
            return (float) $data[$middle];
        } else {
            // Par: promedio de los dos centrales
            return ($data[$middle] + $data[$middle + 1]) / 2.0;
        }
    }

    /**
     * Calcula la moda (valor más frecuente) de un conjunto de datos.
     * Devuelve un float si es unimodal, o un array si es multimodal.
     */
    public static function calculateMode(array $data): float|array
    {
        if (empty($data)) {
            return 0.0;
        }
        $counts = array_count_values($data);
        $maxCount = max($counts);
        $modes = [];

        foreach ($counts as $value => $count) {
            if ($count === $maxCount) {
                $modes[] = (float) $value;
            }
        }

        return (count($modes) === 1) ? $modes[0] : $modes;
    }
}