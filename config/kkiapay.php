<?php

return [
    'public_key'  => env('KKIAPAY_PUBLIC_KEY'),
    'private_key' => env('KKIAPAY_PRIVATE_KEY'),
    'secret_key'  => env('KKIAPAY_SECRET_KEY'),
    'sandbox'     => env('KKIAPAY_SANDBOX', true),
    'base_url'    => env('KKIAPAY_BASE_URL', 'https://api.kkiapay.me'),
    'deblocage_prix' => env('DEBLOCAGE_CONTACT_PRIX', 500),
];