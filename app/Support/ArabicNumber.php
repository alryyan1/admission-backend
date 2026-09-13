<?php

namespace App\Support;

class ArabicNumber
{
    /** @var array<int, string> */
    private const ONES = ['', 'واحد', 'اثنان', 'ثلاثة', 'أربعة', 'خمسة', 'ستة', 'سبعة', 'ثمانية', 'تسعة'];

    /** @var array<int, string> */
    private const TEENS = [
        'عشرة', 'أحد عشر', 'اثنا عشر', 'ثلاثة عشر', 'أربعة عشر',
        'خمسة عشر', 'ستة عشر', 'سبعة عشر', 'ثمانية عشر', 'تسعة عشر',
    ];

    /** @var array<int, string> */
    private const TENS = ['', '', 'عشرون', 'ثلاثون', 'أربعون', 'خمسون', 'ستون', 'سبعون', 'ثمانون', 'تسعون'];

    /** @var array<int, string> */
    private const HUNDREDS = ['', 'مائة', 'مئتان', 'ثلاثمائة', 'أربعمائة', 'خمسمائة', 'ستمائة', 'سبعمائة', 'ثمانمائة', 'تسعمائة'];

    /** @var array<int, array{singular: string, dual: string, plural: string}> */
    private const SCALES = [
        ['singular' => '', 'dual' => '', 'plural' => ''],
        ['singular' => 'ألف', 'dual' => 'ألفان', 'plural' => 'آلاف'],
        ['singular' => 'مليون', 'dual' => 'مليونان', 'plural' => 'ملايين'],
        ['singular' => 'مليار', 'dual' => 'ملياران', 'plural' => 'مليارات'],
        ['singular' => 'تريليون', 'dual' => 'تريليونان', 'plural' => 'تريليونات'],
    ];

    /**
     * Convert a non-negative integer to Arabic cardinal words, e.g. 3000 => "ثلاثة آلاف".
     */
    public static function integerToWords(int $value): string
    {
        $n = max(0, $value);

        if ($n === 0) {
            return 'صفر';
        }

        $groups = [];
        $remaining = $n;
        while ($remaining > 0) {
            $groups[] = $remaining % 1000;
            $remaining = intdiv($remaining, 1000);
        }

        $parts = [];
        for ($i = count($groups) - 1; $i >= 0; $i--) {
            $group = $groups[$i];
            if ($group === 0) {
                continue;
            }
            $parts[] = $i === 0 ? self::convertUnder1000($group) : self::convertGroup($group, self::SCALES[$i]);
        }

        return implode(' و', $parts);
    }

    /**
     * Convert a monetary amount to an Arabic words phrase,
     * e.g. 3000 => "ثلاثة آلاف جنيه فقط لا غير".
     */
    public static function amountToWords(float $value, ?string $currency = null, ?string $subCurrency = null): string
    {
        $currency ??= config('pdf.currency');
        $subCurrency ??= config('pdf.sub_currency');

        $rounded = round(abs($value), 2);
        $integerPart = (int) floor($rounded);
        $fractionPart = (int) round(($rounded - $integerPart) * 100);

        $result = self::integerToWords($integerPart).' '.$currency;
        if ($fractionPart > 0) {
            $result .= ' و'.self::integerToWords($fractionPart).' '.$subCurrency;
        }

        return $result.' فقط لا غير';
    }

    private static function convertUnder100(int $n): string
    {
        if ($n < 10) {
            return self::ONES[$n];
        }
        if ($n < 20) {
            return self::TEENS[$n - 10];
        }

        $tens = intdiv($n, 10);
        $ones = $n % 10;

        if ($ones === 0) {
            return self::TENS[$tens];
        }

        return self::ONES[$ones].' و'.self::TENS[$tens];
    }

    private static function convertUnder1000(int $n): string
    {
        if ($n === 0) {
            return '';
        }

        $hundreds = intdiv($n, 100);
        $rest = $n % 100;

        $parts = [];
        if ($hundreds > 0) {
            $parts[] = self::HUNDREDS[$hundreds];
        }
        if ($rest > 0) {
            $parts[] = self::convertUnder100($rest);
        }

        return implode(' و', $parts);
    }

    /**
     * @param  array{singular: string, dual: string, plural: string}  $scale
     */
    private static function convertGroup(int $n, array $scale): string
    {
        if ($n === 1) {
            return $scale['singular'];
        }
        if ($n === 2) {
            return $scale['dual'];
        }
        if ($n >= 3 && $n <= 10) {
            return self::convertUnder1000($n).' '.$scale['plural'];
        }

        return self::convertUnder1000($n).' '.$scale['singular'];
    }
}
