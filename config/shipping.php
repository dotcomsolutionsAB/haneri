<?php

return [

    // Seller info (for invoices / Delhivery)
    'seller_name'    => 'Haneri Electricals LLP',
    'seller_address' => 'Your full warehouse address here',
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
