<?php

$sandbox = (bool) env('FEDAPAY_SANDBOX', true);

return [
    'public_key'     => env('FEDAPAY_PUBLIC_KEY'),
    'secret_key'     => env('FEDAPAY_SECRET_KEY'),
    'webhook_secret' => env('FEDAPAY_WEBHOOK_SECRET'),
    'sandbox'        => $sandbox,
    'base_url'       => env('FEDAPAY_BASE_URL', $sandbox ? 'https://sandbox-api.fedapay.com' : 'https://api.fedapay.com'),
    'deblocage_prix' => env('DEBLOCAGE_CONTACT_PRIX', 500),
];
