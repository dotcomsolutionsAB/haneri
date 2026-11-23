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

    public static function formatHsn(?string $hsn, ?string $fallback = null): string
    {
        $raw = self::normalizeHsn($hsn, $fallback);
        if ($raw === '') {
            return '—';
        }
        if (preg_match('/^\d{8}$/', $raw)) {
            return substr($raw, 0, 4).' '.substr($raw, 4, 2).' '.substr($raw, 6, 2);
        }
        if (preg_match('/^\d{6}$/', $raw)) {
            return substr($raw, 0, 4).' '.substr($raw, 4, 2);
        }

        return $raw;
    }

    public static function normalizeHsn(?string $hsn, ?string $fallback = null): string
    {
        $raw = strtoupper(preg_replace('/\s+/', '', (string) $hsn) ?? '');
        if ($raw === '' || !preg_match('/^\d{4,8}$/', $raw)) {
            $fallbackRaw = strtoupper(preg_replace(
                '/\s+/',
                '',
                (string) ($fallback ?? config('shipping.default_hsn', '841451'))
            ) ?? '');
            $raw = preg_match('/^\d{4,8}$/', $fallbackRaw) ? $fallbackRaw : '';
        }

        return $raw;
    }

    /**
     * Build an HSN-wise taxable / tax breakdown (GST-inclusive line prices).
     *
     * @param  iterable<int, object>  $items
     * @return array<int, array{
     *   hsn: string,
     *   hsn_label: string,
     *   qty: int,
     *   taxable: float,
     *   cgst: float,
     *   sgst: float,
     *   igst: float,
     *   tax: float
     * }>
     */
    public static function hsnSummary(
        iterable $items,
        float $itemsSubtotal,
        float $discount,
        bool $intra,
        ?string $defaultHsn = null
    ): array {
        $itemsSubtotal = max(0, round($itemsSubtotal, 2));
        $discount = max(0, round($discount, 2));
        $merchandiseIncl = max(0, round($itemsSubtotal - $discount, 2));
        $taxRate = 0.18;
        $defaultHsn = $defaultHsn ?? (string) config('shipping.default_hsn', '841451');

        $buckets = [];
        foreach ($items as $item) {
            $lineIncl = round(((float) ($item->price ?? 0)) * max(0, (int) ($item->quantity ?? 0)), 2);
            if ($lineIncl <= 0) {
                continue;
            }
            $share = $itemsSubtotal > 0
                ? round($merchandiseIncl * ($lineIncl / $itemsSubtotal), 2)
                : 0.0;
            $taxable = $share > 0 ? round($share / (1 + $taxRate), 2) : 0.0;
            $tax = round($share - $taxable, 2);
            $variantHsn = null;
            if (is_object($item) && isset($item->variant) && is_object($item->variant)) {
                $variantHsn = $item->variant->hsn ?? null;
            }
            $hsn = self::normalizeHsn(is_string($variantHsn) ? $variantHsn : null, $defaultHsn);
            if ($hsn === '') {
                $hsn = '0000';
            }
            if (!isset($buckets[$hsn])) {
                $buckets[$hsn] = [
                    'hsn' => $hsn,
                    'hsn_label' => self::formatHsn($hsn, $defaultHsn),
                    'qty' => 0,
                    'taxable' => 0.0,
                    'tax' => 0.0,
                ];
            }
            $buckets[$hsn]['qty'] += max(0, (int) ($item->quantity ?? 0));
            $buckets[$hsn]['taxable'] = round($buckets[$hsn]['taxable'] + $taxable, 2);
            $buckets[$hsn]['tax'] = round($buckets[$hsn]['tax'] + $tax, 2);
        }

        if ($buckets === []) {
            return [];
        }

        // Fix rounding so HSN rows match invoice taxable / tax totals.
        $expectedTaxable = $merchandiseIncl > 0
            ? round($merchandiseIncl / (1 + $taxRate), 2)
            : 0.0;
        $expectedTax = round($merchandiseIncl - $expectedTaxable, 2);
        $keys = array_keys($buckets);
        $last = $keys[count($keys) - 1];
        $sumTaxable = round(array_sum(array_column($buckets, 'taxable')), 2);
        $sumTax = round(array_sum(array_column($buckets, 'tax')), 2);
        $buckets[$last]['taxable'] = round(
            $buckets[$last]['taxable'] + ($expectedTaxable - $sumTaxable),
            2
        );
        $buckets[$last]['tax'] = round(
            $buckets[$last]['tax'] + ($expectedTax - $sumTax),
            2
        );

        $rows = [];
        foreach ($buckets as $row) {
            $tax = max(0, round((float) $row['tax'], 2));
            if ($intra) {
                $half = round($tax / 2, 2);
                $cgst = $half;
                $sgst = round($tax - $half, 2);
                $igst = 0.0;
            } else {
                $cgst = 0.0;
                $sgst = 0.0;
                $igst = $tax;
            }
            $rows[] = [
                'hsn' => $row['hsn'],
                'hsn_label' => $row['hsn_label'],
                'qty' => (int) $row['qty'],
                'taxable' => max(0, round((float) $row['taxable'], 2)),
                'cgst' => $cgst,
                'sgst' => $sgst,
                'igst' => $igst,
                'tax' => $tax,
            ];
        }

        return $rows;
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
