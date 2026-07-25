<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Форматирование реквизитов акта для документов РФ.
 */
final class ActDocumentFormatter
{
    private const MONTHS_GENITIVE = [
        1 => 'января',
        2 => 'февраля',
        3 => 'марта',
        4 => 'апреля',
        5 => 'мая',
        6 => 'июня',
        7 => 'июля',
        8 => 'августа',
        9 => 'сентября',
        10 => 'октября',
        11 => 'ноября',
        12 => 'декабря',
    ];

    public static function dateLong(null|string|CarbonInterface $date): string
    {
        $d = $date instanceof CarbonInterface
            ? $date
            : Carbon::parse($date ?: now()->toDateString());

        $day = $d->format('d');
        $month = self::MONTHS_GENITIVE[(int) $d->format('n')] ?? $d->format('m');
        $year = $d->format('Y');

        return '«' . $day . '» ' . $month . ' ' . $year . ' г.';
    }

    public static function hoursNumeric(float $hours): string
    {
        return number_format($hours, 2, ',', ' ');
    }

    /** Пример: 12,50 (двенадцать целых пятьдесят сотых) */
    public static function hoursWithWords(float $hours): string
    {
        $hours = round(max(0, $hours), 2);
        $int = (int) floor($hours + 1e-8);
        $frac = (int) round(($hours - $int) * 100);

        if ($frac === 100) {
            $int++;
            $frac = 0;
        }

        $words = self::integerToWordsFem($int) . ' цел' . self::plural($int, 'ая', 'ых', 'ых');
        if ($frac > 0) {
            $words .= ' ' . self::integerToWordsFem($frac) . ' сот'
                . self::plural($frac, 'ая', 'ых', 'ых');
        } else {
            $words .= ' ноль сотых';
        }

        return self::hoursNumeric($hours) . ' (' . $words . ')';
    }

    public static function hoursUnit(float $hours): string
    {
        $int = (int) round($hours);
        // для подписи единицы при дробных — «часа»
        if (abs($hours - $int) > 0.001) {
            return 'часа';
        }

        return self::plural($int, 'час', 'часа', 'часов');
    }

    /**
     * Убирает лишнее «ООО «…»», если пользователь уже указал полное наименование.
     */
    public static function partyName(string $name): string
    {
        return trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
    }

    private static function plural(int $n, string $one, string $few, string $many): string
    {
        $n = abs($n) % 100;
        $n1 = $n % 10;
        if ($n > 10 && $n < 20) {
            return $many;
        }
        if ($n1 === 1) {
            return $one;
        }
        if ($n1 >= 2 && $n1 <= 4) {
            return $few;
        }

        return $many;
    }

    private static function integerToWords(int $number): string
    {
        return self::integerToWordsGender($number, false);
    }

    private static function integerToWordsFem(int $number): string
    {
        return self::integerToWordsGender($number, true);
    }

    private static function integerToWordsGender(int $number, bool $feminine): string
    {
        $number = abs($number);
        if ($number === 0) {
            return 'ноль';
        }

        $units = ['', 'один', 'два', 'три', 'четыре', 'пять', 'шесть', 'семь', 'восемь', 'девять'];
        $unitsFem = ['', 'одна', 'две', 'три', 'четыре', 'пять', 'шесть', 'семь', 'восемь', 'девять'];
        $unitForms = $feminine ? $unitsFem : $units;
        $teens = [
            10 => 'десять', 11 => 'одиннадцать', 12 => 'двенадцать', 13 => 'тринадцать',
            14 => 'четырнадцать', 15 => 'пятнадцать', 16 => 'шестнадцать', 17 => 'семнадцать',
            18 => 'восемнадцать', 19 => 'девятнадцать',
        ];
        $tens = [
            '', '', 'двадцать', 'тридцать', 'сорок', 'пятьдесят',
            'шестьдесят', 'семьдесят', 'восемьдесят', 'девяносто',
        ];
        $hundreds = [
            '', 'сто', 'двести', 'триста', 'четыреста', 'пятьсот',
            'шестьсот', 'семьсот', 'восемьсот', 'девятьсот',
        ];

        $parts = [];

        $thousands = intdiv($number, 1000);
        $rest = $number % 1000;

        if ($thousands > 0) {
            $parts[] = self::triadToWords($thousands, $hundreds, $tens, $teens, $unitsFem);
            $parts[] = 'тысяч' . self::plural($thousands, 'а', 'и', '');
        }

        if ($rest > 0 || $thousands === 0) {
            $parts[] = self::triadToWords($rest, $hundreds, $tens, $teens, $unitForms);
        }

        return trim(preg_replace('/\s+/u', ' ', implode(' ', $parts)) ?? '');
    }

    /**
     * @param  array<int, string>  $hundreds
     * @param  array<int, string>  $tens
     * @param  array<int, string>  $teens
     * @param  array<int, string>  $units
     */
    private static function triadToWords(
        int $n,
        array $hundreds,
        array $tens,
        array $teens,
        array $units
    ): string {
        $h = intdiv($n, 100);
        $t = intdiv($n % 100, 10);
        $u = $n % 10;
        $out = [];

        if ($h > 0) {
            $out[] = $hundreds[$h];
        }

        if ($t === 1) {
            $out[] = $teens[10 + $u];
        } else {
            if ($t > 1) {
                $out[] = $tens[$t];
            }
            if ($u > 0) {
                $out[] = $units[$u];
            }
        }

        return implode(' ', $out);
    }
}
