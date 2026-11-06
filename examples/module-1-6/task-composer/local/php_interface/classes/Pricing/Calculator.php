<?php
/**
 * MyCompany\Pricing\Calculator
 *
 * Задача 4: класс, который находит Composer по PSR-4.
 * Файл — php_interface/classes/Pricing/Calculator.php, имя с заглавной буквы.
 * Loader::registerNamespace() такой путь не нашёл бы: он приводит его к
 * нижнему регистру (php_interface/classes/pricing/calculator.php).
 */

namespace MyCompany\Pricing;

class Calculator
{
    /** @var float Ставка НДС по умолчанию, 20% */
    public const DEFAULT_VAT_RATE = 0.20;

    public static function withVat(float $net, float $rate = self::DEFAULT_VAT_RATE): float
    {
        if ($net < 0) {
            throw new \InvalidArgumentException('Сумма не может быть отрицательной');
        }
        if ($rate < 0 || $rate >= 1) {
            throw new \InvalidArgumentException('Ставка задаётся долей: 0.2 = 20%');
        }

        return round($net * (1 + $rate), 2);
    }

    public static function vatAmount(float $net, float $rate = self::DEFAULT_VAT_RATE): float
    {
        return round(self::withVat($net, $rate) - $net, 2);
    }
}
