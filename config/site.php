<?php

$mode = env('SITE_MODE', 'shopping');

if (! in_array($mode, ['shopping', 'enquiry'], true)) {
    $mode = 'shopping';
}

return [
    'mode' => $mode,
    'google_sheets_enquiry_url' => env('GOOGLE_SHEETS_ENQUIRY_URL', ''),
];
