<?php
require __DIR__ . '/_lib.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Alleen POST', 405);
$c = cfg(); $in = body();
$customer = clean_customer($in['customer'] ?? []);
$pricing = price_order($in['items'] ?? [], $customer['country']);
$methods = ['ideal' => 'ideal', 'bancontact' => 'bancontact', 'creditcard' => 'creditcard'];
$method = $methods[$in['method'] ?? ''] ?? null;

$id = new_order_id();
$payload = [
    'amount'      => ['currency' => 'EUR', 'value' => number_format($pricing['total'], 2, '.', '')],
    'description' => "PiForge bestelling $id",
    'redirectUrl' => $c['site_url'] . '/#/bedankt?order=' . $id,
    'webhookUrl'  => $c['site_url'] . '/api/mollie-webhook.php',
    'metadata'    => ['order_id' => $id],
    'locale'      => 'nl_NL',
];
if ($method) $payload['method'] = $method;
// Webhooks werken niet op localhost; laat dan de webhook weg
if (preg_match('#^https?://(localhost|127\.)#', $c['site_url'])) unset($payload['webhookUrl']);

[$code, $res] = http_json('POST', 'https://api.mollie.com/v2/payments',
    ['Authorization: Bearer ' . $c['mollie_api_key'], 'Content-Type: application/json'], json_encode($payload));
if ($code >= 300 || empty($res['_links']['checkout']['href'])) fail('Mollie: ' . ($res['detail'] ?? 'betaling kon niet worden aangemaakt'), 502);

save_order(['id' => $id, 'created' => date('c'), 'status' => 'open', 'provider' => 'mollie', 'payment_id' => $res['id'],
    'customer' => $customer, 'items' => $in['items'], 'pricing' => $pricing]);
ok(['checkoutUrl' => $res['_links']['checkout']['href'], 'orderId' => $id]);
