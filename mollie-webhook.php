<?php
// Mollie roept dit aan bij elke statuswijziging. We halen de status altijd zelf op bij Mollie.
require __DIR__ . '/_lib.php';
$c = cfg();
$pid = $_POST['id'] ?? '';
if (!preg_match('/^tr_[A-Za-z0-9]+$/', $pid)) { http_response_code(200); exit; }
[$code, $p] = http_json('GET', 'https://api.mollie.com/v2/payments/' . $pid, ['Authorization: Bearer ' . $c['mollie_api_key']]);
$oid = $p['metadata']['order_id'] ?? '';
if ($code !== 200 || !$oid) { http_response_code(200); exit; }
$o = load_order($oid);
if (!$o || $o['payment_id'] !== $pid) { http_response_code(200); exit; }
$was = $o['status'];
$o['status'] = $p['status']; // open, pending, paid, failed, canceled, expired
$o['updated'] = date('c');
save_order($o);
if ($was !== 'paid' && $o['status'] === 'paid') notify_paid($o);
http_response_code(200);
echo '{}';
