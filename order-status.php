<?php
require __DIR__ . '/_lib.php';
$c = cfg();
$o = load_order((string)($_GET['order'] ?? ''));
if (!$o) fail('Niet gevonden', 404);
// Extra controle bij Mollie als de webhook nog niet binnen is (bijv. lokaal testen)
if ($o['provider'] === 'mollie' && in_array($o['status'], ['open', 'pending'], true)) {
    [$code, $p] = http_json('GET', 'https://api.mollie.com/v2/payments/' . $o['payment_id'], ['Authorization: Bearer ' . $c['mollie_api_key']]);
    if ($code === 200 && !empty($p['status']) && $p['status'] !== $o['status']) {
        $was = $o['status']; $o['status'] = $p['status']; save_order($o);
        if ($was !== 'paid' && $o['status'] === 'paid') notify_paid($o);
    }
}
ok(['status' => $o['status'], 'total' => $o['pricing']['total']]);
