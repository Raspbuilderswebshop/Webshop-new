<?php
/*
 * PiForge — server-instellingen
 * 1. Kopieer dit bestand naar config.php
 * 2. Vul je eigen sleutels in
 * config.php staat in .gitignore en wordt nooit naar de browser gestuurd.
 */
return [
    // Mollie: Dashboard > Ontwikkelaars > API-sleutels. Begin met test_..., later live_...
    'mollie_api_key'   => 'test_XXXXXXXXXXXXXXXXXXXXXXXXXXXXXX',

    // Volledige URL van je site, zonder slash aan het eind, bijv. https://piforge.nl
    'site_url'         => 'https://www.jouwdomein.nl',

    // PayPal (voor server-side controle van betalingen). Mode: 'sandbox' of 'live'
    'paypal_mode'      => 'sandbox',
    'paypal_client_id' => '',
    'paypal_secret'    => '',

    // E-mail
    'shop_email'       => 'raspbuilders@astermail.org',
    'from_email'       => 'noreply@jouwdomein.nl', // adres op je eigen domein voor betere bezorging
    'shop_name'        => 'PiForge',
];
