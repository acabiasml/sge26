<?php

namespace App\Support;

class BrazilianMoney
{
    private const UNITS = ['', 'um', 'dois', 'três', 'quatro', 'cinco', 'seis', 'sete', 'oito', 'nove'];

    private const TEENS = ['dez', 'onze', 'doze', 'treze', 'quatorze', 'quinze', 'dezesseis', 'dezessete', 'dezoito', 'dezenove'];

    private const TENS = ['', '', 'vinte', 'trinta', 'quarenta', 'cinquenta', 'sessenta', 'setenta', 'oitenta', 'noventa'];

    private const HUNDREDS = ['', 'cento', 'duzentos', 'trezentos', 'quatrocentos', 'quinhentos', 'seiscentos', 'setecentos', 'oitocentos', 'novecentos'];

    public static function words(string $amount): string
    {
        [$reais, $centavos] = array_map('intval', explode('.', number_format((float) $amount, 2, '.', '')));
        $parts = [];

        if ($reais > 0) {
            $thousands = intdiv($reais, 1000);
            $remainder = $reais % 1000;
            $number = $thousands ? ($thousands === 1 ? 'mil' : self::hundreds($thousands).' mil') : '';
            if ($remainder) {
                $number .= ($thousands ? ($remainder < 100 || $remainder % 100 === 0 ? ' e ' : ' ') : '').self::hundreds($remainder);
            }
            $parts[] = $number.' '.($reais === 1 ? 'real' : 'reais');
        }
        if ($centavos > 0) {
            $parts[] = self::hundreds($centavos).' '.($centavos === 1 ? 'centavo' : 'centavos');
        }

        return $parts ? implode(' e ', $parts) : 'zero reais';
    }

    private static function hundreds(int $number): string
    {
        if ($number === 100) {
            return 'cem';
        }
        $parts = [];
        if ($number >= 100) {
            $parts[] = self::HUNDREDS[intdiv($number, 100)];
            $number %= 100;
        }
        if ($number >= 20) {
            $parts[] = self::TENS[intdiv($number, 10)];
            $number %= 10;
        } elseif ($number >= 10) {
            $parts[] = self::TEENS[$number - 10];
            $number = 0;
        }
        if ($number > 0) {
            $parts[] = self::UNITS[$number];
        }

        return implode(' e ', $parts);
    }
}
