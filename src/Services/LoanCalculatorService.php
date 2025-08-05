<?php

namespace App\Services;

/**
 * Service de calcul de prêt immobilier
 * 
 * Implémente les algorithmes financiers pour calculer les mensualités,
 * le coût total et les intérêts des prêts immobiliers.
 * 
 * @author Ibtissam Jennate
 */
class LoanCalculatorService
{
    /**
     * Calcule les détails complets d'un prêt immobilier
     *
     * Calcule la mensualité selon la formule:
     * M = P × [r(1 + r)^n] / [(1 + r)^n - 1]
     * Où:
     * - P est le montant emprunté
     * - r est le taux mensuel (taux annuel / 12 / 100)
     * - n est le nombre de mois (années × 12)
     * 
     * Pour un taux à 0%, utilise une formule simplifiée: P / n
     *
     * @param float $amount Montant du prêt en euros
     * @param float $annualRate Taux annuel en pourcentage (ex: 3.5 pour 3.5%)
     * @param int $years Durée en années
     * @return array Tableau contenant les détails du prêt:
     *               - monthly_payment: Mensualité en euros
     *               - total_cost: Coût total (principal + intérêts)
     *               - total_interest: Montant total des intérêts
     *               - total_months: Durée totale en mois
     *               - principal: Montant emprunté
     *               - rate: Taux annuel
     *               - duration: Durée en années
     * @throws \InvalidArgumentException Si les paramètres sont invalides
     */
    public function calculateLoan(float $amount, float $annualRate, int $years): array
    {
        // Validation des paramètres d'entrée
        if ($amount <= 0) {
            throw new \InvalidArgumentException("Le montant du prêt doit être supérieur à zéro.");
        }
        if ($annualRate < 0) {
            throw new \InvalidArgumentException("Le taux annuel ne peut pas être négatif.");
        }
        if ($years <= 0) {
            throw new \InvalidArgumentException("La durée du prêt doit être supérieure à zéro.");
        }

        // Conversion du taux annuel en taux mensuel (en décimal)
        $monthlyRate = $annualRate / 12 / 100;
        $months = $years * 12;

        // Calcul de la mensualité (cas particulier si taux = 0%)
        if ($monthlyRate === 0) {
            // Formule simplifiée pour un prêt sans intérêts
            $monthlyPayment = $amount / $months;
        } else {
            // Formule standard pour un prêt avec intérêts
            $monthlyPayment = $amount *
                ($monthlyRate * pow(1 + $monthlyRate, $months)) /
                (pow(1 + $monthlyRate, $months) - 1);
        }

        // Arrondi des valeurs monétaires à 2 décimales
        $monthlyPayment = round($monthlyPayment, 2);
        $total = round($monthlyPayment * $months, 2);
        $interest = round($total - $amount, 2);

        // Construction du tableau de résultats
        return [
            'monthly_payment' => $monthlyPayment,
            'total_cost' => $total,
            'total_interest' => $interest,
            'total_months' => $months,
            'principal' => round($amount, 2),
            'rate' => round($annualRate, 2),
            'duration' => $years,
            'timestamp' => time()
        ];
    }
}
