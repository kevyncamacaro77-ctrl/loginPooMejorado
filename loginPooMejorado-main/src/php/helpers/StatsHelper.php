<?php
// src/php/helpers/StatsHelper.php

/**
 * Clase auxiliar para realizar cálculos estadísticos (Media, Mediana, Moda).
 * Usada para mantener la lógica de estadística modular y reutilizable.
 */
class StatsHelper {

    /**
     * Calcula la Media (Promedio) de un array de números.
     * @param array $data Array de números (cantidades vendidas).
     * @return float La media calculada.
     */
    public static function calculateMean(array $data): float {
        if (empty($data)) {
            return 0.0;
        }
        return array_sum($data) / count($data);
    }

    /**
     * Calcula la Mediana (Valor Central) de un array de números.
     * @param array $data Array de números (cantidades vendidas).
     * @return float La mediana calculada.
     */
    public static function calculateMedian(array $data): float {
        if (empty($data)) {
            return 0.0;
        }
        // Ordenar los datos es el primer paso para calcular la mediana.
        $sortedData = $data;
        sort($sortedData);
        $count = count($sortedData);
        $middle = floor($count / 2);

        if ($count % 2 === 0) {
            // Si el número de elementos es par, la mediana es el promedio de los dos centrales.
            return ($sortedData[$middle - 1] + $sortedData[$middle]) / 2;
        } else {
            // Si es impar, la mediana es el elemento central.
            return (float) $sortedData[$middle];
        }
    }

    /**
     * Calcula la Moda (Valor más frecuente) de un array de números.
     * @param array $data Array de números (cantidades vendidas).
     * @return array|int El valor (o valores) de la moda. Retorna un array si es multimodal.
     */
    public static function calculateMode(array $data): array|int {
        if (empty($data)) {
            return 0;
        }
        // Contar la frecuencia de cada valor.
        $counts = array_count_values($data);
        $maxFrequency = max($counts);
        $modes = [];

        // Encontrar todos los valores que tienen la máxima frecuencia.
        foreach ($counts as $value => $frequency) {
            if ($frequency === $maxFrequency) {
                // Convertir el valor de vuelta a int, ya que array_count_values lo trata como string.
                $modes[] = (int) $value; 
            }
        }

        // Si solo hay una moda, devolver el entero, si hay varias, devolver el array.
        return (count($modes) === 1) ? $modes[0] : $modes;
    }
}