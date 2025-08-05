<?php

namespace App\Services;

class LoanCalculatorService
{
    /**
     * Calcule les détails du prêt.
     *
     * @param float $amount Montant du prêt
     * @param float $annualRate Taux annuel en pourcentage
     * @param int $years Durée en années
     * @return array
     * @throws \InvalidArgumentException
     */
    public function calculateLoan(float $amount, float $annualRate, int $years): array
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException("Le montant du prêt doit être supérieur à zéro.");
        }
        if ($annualRate < 0) {
            throw new \InvalidArgumentException("Le taux annuel ne peut pas être négatif.");
        }
        if ($years <= 0) {
            throw new \InvalidArgumentException("La durée du prêt doit être supérieure à zéro.");
        }

        $monthlyRate = $annualRate / 12 / 100;
        $months = $years * 12;

        if ($monthlyRate === 0) {
            $monthlyPayment = $amount / $months;
        } else {
            $monthlyPayment = $amount *
                ($monthlyRate * pow(1 + $monthlyRate, $months)) /
                (pow(1 + $monthlyRate, $months) - 1);
        }

        $monthlyPayment = round($monthlyPayment, 2);
        $total = round($monthlyPayment * $months, 2);
        $interest = round($total - $amount, 2);

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
