<?php

return [

    // Seller info (for invoices / Delhivery) — defaults match Haneri's Tally tax invoice.
    'seller_name'    => 'Haneri Electricals LLP',
    'seller_address' => env(
        'SELLER_ADDRESS',
        'First Floor, A 48, Sector 57, Noida, Gautambuddha Nagar, Uttar Pradesh - 201301'
    ),
    // Empty .env values must not wipe Tally defaults (env('X','default') returns '' when X=).
    'seller_gstin'   => (($g = trim((string) env('SELLER_GSTIN', ''))) !== '' ? strtoupper($g) : '09ABZFA2370D1Z8'),
    'seller_state'   => (($s = trim((string) env('SELLER_STATE', ''))) !== '' ? $s : 'Uttar Pradesh'),
    // Default HSN for ceiling fans when a variant has no HSN set (Tally: 841451).
    'default_hsn'    => (($h = trim((string) env('SELLER_DEFAULT_HSN', ''))) !== '' ? $h : '841451'),
    // Soft defaults only — pickup is resolved from Delhivery client warehouses at book time.
    'pickup' => [
        'name'    => env('DELHIVERY_PICKUP_NAME', 'HANERI HO'),
        'address' => '',
        'pin'     => '',
        'city'    => '',
        'state'   => '',
        'phone'   => '',
    ],
    // Registered Delhivery client name (Bulk Waybill `cl=`). Required for multi-box MPS/Cargo.
    'delhivery_client' => env('DELHIVERY_CLIENT_NAME', ''),

];
