<?php

return [

    // Seller info (for invoices / Delhivery)
    'seller_name'    => 'Haneri Electricals LLP',
    'seller_address' => 'Your full warehouse address here',
    // Default pickup location (must match Delhivery dashboard)
    'pickup' => [
        'name'    => 'Burhanuddin',   // EXACT pickup name from Delhivery portal
        'address' => 'Your full warehouse address as per Delhivery',
        'pin'     => '713146',        // or your actual pickup pin
        'city'    => 'Memari',        // or your actual pickup city
        'state'   => 'West Bengal',
        'phone'   => 'XXXXXXXXXX',    // registered contact number
        'location_id'   =>  '1',
    ],

];
