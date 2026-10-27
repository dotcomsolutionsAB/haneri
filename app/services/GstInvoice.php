<?php

namespace App\services;

class GstInvoice
{
    /** @var array<string, string> */
    private const STATES = [
        '01' => 'Jammu and Kashmir',
        '02' => 'Himachal Pradesh',
        '03' => 'Punjab',
        '04' => 'Chandigarh',
        '05' => 'Uttarakhand',
        '06' => 'Haryana',
        '07' => 'Delhi',
        '08' => 'Rajasthan',
        '09' => 'Uttar Pradesh',
        '10' => 'Bihar',
        '11' => 'Sikkim',
        '12' => 'Arunachal Pradesh',
        '13' => 'Nagaland',
        '14' => 'Manipur',
        '15' => 'Mizoram',
        '16' => 'Tripura',
        '17' => 'Meghalaya',
        '18' => 'Assam',
        '19' => 'West Bengal',
        '20' => 'Jharkhand',
        '21' => 'Odisha',
        '22' => 'Chhattisgarh',
        '23' => 'Madhya Pradesh',
        '24' => 'Gujarat',
        '26' => 'Dadra and Nagar Haveli and Daman and Diu',
        '27' => 'Maharashtra',
        '29' => 'Karnataka',
        '30' => 'Goa',
        '31' => 'Lakshadweep',
        '32' => 'Kerala',
        '33' => 'Tamil Nadu',
        '34' => 'Puducherry',
        '35' => 'Andaman and Nicobar Islands',
        '36' => 'Telangana',
        '37' => 'Andhra Pradesh',
        '38' => 'Ladakh',
    ];

    /** @var array<string, string> */
    private const ALIASES = [
        'uttaranchal' => '05',
        'orissa' => '21',
        'pondicherry' => '34',
        'nct of delhi' => '07',
        'jammu & kashmir' => '01',
        'daman and diu' => '26',
        'daman & diu' => '26',
        'dadra and nagar haveli' => '26',
        'dadra & nagar haveli' => '26',
    ];

    /**
     * @return array{code: string, name: string}|null
     */
    public static function findState(string $name): ?array
    {
        $key = strtolower(trim(preg_replace('/\s+/', ' ', $name) ?? ''));
        if ($key === '') {
            return null;
        }
        if (isset(self::ALIASES[$key])) {
            return self::byCode(self::ALIASES[$key]);
        }
        foreach (self::STATES as $code => $label) {
            if (strtolower($label) === $key) {
                return ['code' => $code, 'name' => $label];
            }
        }

        return null;
    }

    /**
     * @return array{code: string, name: string}|null
     */
    public static function stateFromText(string $text): ?array
    {
        $haystack = strtolower($text);
        $names = self::STATES;
        uasort($names, fn ($a, $b) => strlen($b) <=> strlen($a));
        foreach ($names as $code => $label) {
            $pattern = '/\b'.preg_quote(strtolower($label), '/').'\b/';
            if (preg_match($pattern, $haystack)) {
                return ['code' => $code, 'name' => $label];
            }
        }
        $aliases = self::ALIASES;
        uksort($aliases, fn ($a, $b) => strlen($b) <=> strlen($a));
        foreach ($aliases as $alias => $code) {
            $pattern = '/\b'.preg_quote($alias, '/').'\b/';
            if (preg_match($pattern, $haystack)) {
                return self::byCode($code);
            }
        }

        return null;
    }

    /**
     * GSTIN's first two digits win. Otherwise the saved state, then the address.
     * The registered office is in Uttar Pradesh when nothing else is known.
     *
     * @return array{code: string, name: string}
     */
    public static function sellerState(string $gstin, string $stateName, string $address): array
    {
        $gstin = strtoupper(preg_replace('/\s+/', '', $gstin) ?? '');
        if (preg_match('/^(\d{2})/', $gstin, $match)) {
            $fromGstin = self::byCode($match[1]);
            if ($fromGstin) {
                return $fromGstin;
            }
        }

        return self::findState($stateName)
            ?? self::stateFromText($address)
            ?? self::byCode('09');
    }

    /**
     * @param  array{code: string, name: string}|null  $seller
     * @param  array{code: string, name: string}|null  $buyer
     * @return array{intra: bool, cgst: float, sgst: float, igst: float}
     */
    public static function taxSplit(float $tax, ?array $seller, ?array $buyer): array
    {
        $tax = round($tax, 2);
        $intra = $seller && $buyer && $seller['code'] === $buyer['code'];
        if ($intra) {
            $cgst = round($tax / 2, 2);

            return [
                'intra' => true,
                'cgst' => $cgst,
                'sgst' => round($tax - $cgst, 2),
                'igst' => 0.0,
            ];
        }

        return [
            'intra' => false,
            'cgst' => 0.0,
            'sgst' => 0.0,
            'igst' => $tax,
        ];
    }

    public static function formatHsn(?string $hsn): string
    {
        $raw = strtoupper(preg_replace('/\s+/', '', (string) $hsn) ?? '');
        if ($raw === '') {
            return '—';
        }
        if (preg_match('/^\d{8}$/', $raw)) {
            return substr($raw, 0, 4).' '.substr($raw, 4, 2).' '.substr($raw, 6, 2);
        }
        if (preg_match('/^\d{6}$/', $raw)) {
            return substr($raw, 0, 4).' '.substr($raw, 4, 2);
        }

        return trim((string) $hsn) !== '' ? trim((string) $hsn) : '—';
    }

    public static function statusLabel(?string $orderStatus, ?string $deliveryStatus): string
    {
        $order = strtolower(trim((string) $orderStatus));
        $delivery = strtolower(trim((string) $deliveryStatus));
        if (in_array($order, ['cancelled', 'canceled', 'refunded'], true)
            || in_array($delivery, ['cancelled', 'canceled'], true)) {
            return 'Cancelled';
        }
        if (in_array($delivery, ['completed', 'delivered'], true)) {
            return 'Delivered';
        }
        if (in_array($delivery, ['shipped', 'in_transit', 'in transit', 'out_for_delivery'], true)) {
            return 'Shipped';
        }

        return 'Confirmed';
    }

    public static function amountInWords(float $amount): string
    {
        $amount = round($amount, 2);
        $rupees = (int) floor($amount);
        $paise = (int) round(($amount - $rupees) * 100);
        if ($paise === 100) {
            $rupees++;
            $paise = 0;
        }
        $words = 'Rupees '.($rupees === 0 ? 'Zero' : self::indianWords($rupees));
        if ($paise > 0) {
            $words .= ' and '.self::indianWords($paise).' Paise';
        }

        return $words.' Only';
    }

    /**
     * Checkout stores "name, phone, city, state, country, pin, street...".
     *
     * @return array{name: string, phone: string, lines: array<int, string>, state: array{code: string, name: string}|null}
     */
    public static function shippingBlock(string $raw): array
    {
        $parts = array_values(array_filter(array_map('trim', explode(',', $raw)), fn ($v) => $v !== ''));
        if (count($parts) < 6) {
            return [
                'name' => '',
                'phone' => '',
                'lines' => $raw !== '' ? [$raw] : [],
                'state' => self::stateFromText($raw),
            ];
        }

        $state = self::findState($parts[3]) ?? self::stateFromText($parts[3]);
        $street = trim(implode(', ', array_slice($parts, 6)));
        $cityLine = trim($parts[2].', '.($state['name'] ?? $parts[3]).($parts[5] !== '' ? ' '.$parts[5] : ''));

        return [
            'name' => $parts[0],
            'phone' => $parts[1],
            'lines' => array_values(array_filter([$street, $cityLine, $parts[4]])),
            'state' => $state,
        ];
    }

    /**
     * @return array{code: string, name: string}|null
     */
    private static function byCode(string $code): ?array
    {
        if (!isset(self::STATES[$code])) {
            return null;
        }

        return ['code' => $code, 'name' => self::STATES[$code]];
    }

    private static function indianWords(int $n): string
    {
        $parts = [];
        foreach ([10000000 => 'Crore', 100000 => 'Lakh', 1000 => 'Thousand', 100 => 'Hundred'] as $div => $label) {
            if ($n >= $div) {
                $count = intdiv($n, $div);
                $n %= $div;
                $parts[] = self::belowHundred($count).' '.$label;
            }
        }
        if ($n > 0) {
            $parts[] = self::belowHundred($n);
        }

        return implode(' ', $parts);
    }

    private static function belowHundred(int $n): string
    {
        $ones = [
            0 => '', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five',
            6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine', 10 => 'Ten',
            11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen',
            15 => 'Fifteen', 16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen', 19 => 'Nineteen',
        ];
        $tens = [
            2 => 'Twenty', 3 => 'Thirty', 4 => 'Forty', 5 => 'Fifty',
            6 => 'Sixty', 7 => 'Seventy', 8 => 'Eighty', 9 => 'Ninety',
        ];
        if ($n < 20) {
            return $ones[$n];
        }
        $ten = intdiv($n, 10);
        $one = $n % 10;

        return $one === 0 ? $tens[$ten] : $tens[$ten].'-'.$ones[$one];
    }
}
