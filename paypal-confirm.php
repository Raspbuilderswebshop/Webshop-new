<?php
// Controleert een PayPal-betaling server-side en slaat de bestelling op.
require __DIR__ . '/_lib.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Alleen POST', 405);
$c = cfg(); $in = body();
$customer = clean_customer($in['customer'] ?? []);
$pricing = price_order($in['items'] ?? [], $customer['country']);
$ppId = preg_replace('/[^A-Z0-9]/', '', strtoupper((string)($in['paypalOrderId'] ?? '')));
if (!$ppId) fail('PayPal-order ontbreekt');

$status = 'unverified';
if ($c['paypal_client_id'] && $c['paypal_secret']) {
    $base = $c['paypal_mode'] === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
    $ch = curl_init("$base/v1/oauth2/token");
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
        CURLOPT_USERPWD => $c['paypal_client_id'] . ':' . $c['paypal_secret'], CURLOPT_TIMEOUT => 20]);
    $tok = json_decode((string)curl_exec($ch), true)['access_token'] ?? ''; curl_close($ch);
    if ($tok) {
        [$code, $ord] = http_json('GET', "$base/v2/checkout/orders/$ppId", ['Authorization: Bearer ' . $tok]);
        $paid = (float)($ord['purchase_units'][0]['amount']['value'] ?? 0);
        $status = ($code === 200 && ($ord['status'] ?? '') === 'COMPLETED' && abs($paid - $pricing['total']) < 0.01) ? 'paid' : 'check';
    }
}
$id = new_order_id();
$o = ['id' => $id, 'created' => date('c'), 'status' => $status, 'provider' => 'paypal', 'payment_id' => $ppId,
      'customer' => $customer, 'items' => $in['items'], 'pricing' => $pricing];
save_order($o);
if ($status === 'paid') notify_paid($o);
else send_mail($c['shop_email'], "PayPal-bestelling $id handmatig controleren", order_mail_text($o) . "\nPayPal order: $ppId\nStatus: $status");
ok(['orderId' => $id, 'status' => $status]);
