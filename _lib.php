<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function cfg(): array {
    static $c = null;
    if ($c === null) {
        $file = __DIR__ . '/config.php';
        if (!is_file($file)) { fail('Serverconfiguratie ontbreekt: kopieer api/config.example.php naar api/config.php', 500); }
        $c = require $file;
    }
    return $c;
}
function fail(string $msg, int $code = 400): void { http_response_code($code); echo json_encode(['error' => $msg]); exit; }
function ok(array $data): void { echo json_encode($data); exit; }
function body(): array {
    $raw = file_get_contents('php://input') ?: '';
    $d = json_decode($raw, true);
    return is_array($d) ? $d : [];
}
function catalog(): array {
    $json = file_get_contents(__DIR__ . '/../assets/data/products.json');
    return json_decode($json, true);
}
/** Prijs altijd op de server herberekenen; nooit vertrouwen op bedragen uit de browser */
function price_order(array $items, string $country): array {
    $cat = catalog();
    $lines = []; $sub = 0.0;
    if (!$items || count($items) > 30) fail('Ongeldige winkelwagen');
    foreach ($items as $it) {
        $p = null;
        foreach ($cat['products'] as $x) if ($x['id'] === ($it['id'] ?? '')) $p = $x;
        if (!$p) fail('Onbekend product');
        $qty = max(1, min(10, (int)($it['qty'] ?? 1)));
        $price = (float)$p['price']; $desc = [];
        $ram = null; foreach ($p['ram'] as $r) if ($r['id'] === ($it['ram'] ?? '')) $ram = $r;
        $sto = null; foreach ($p['storage'] as $s) if ($s['id'] === ($it['storage'] ?? '')) $sto = $s;
        if (!$ram || !$sto) fail('Ongeldige configuratie');
        if (!in_array($it['os'] ?? '', $p['os'], true)) fail('Ongeldig besturingssysteem');
        $price += $ram['add'] + $sto['add'];
        $desc[] = $ram['label'] . ' RAM'; $desc[] = $sto['label'];
        foreach ($cat['os'] as $o) if ($o['id'] === $it['os']) $desc[] = $o['name'];
        foreach (($it['extras'] ?? []) as $eid) {
            foreach (($p['extras'] ?? []) as $e) if ($e['id'] === $eid) { $price += $e['add']; $desc[] = $e['label']; }
        }
        $lines[] = ['name' => $p['name'], 'config' => implode(' · ', $desc), 'qty' => $qty, 'unit' => $price, 'total' => $price * $qty];
        $sub += $price * $qty;
    }
    $zone = $cat['shipping'][$country] ?? null;
    if (!$zone) fail('We verzenden niet naar dit land');
    $ship = $sub >= $zone['freeFrom'] ? 0.0 : (float)$zone['cost'];
    return ['lines' => $lines, 'subtotal' => round($sub, 2), 'shipping' => $ship, 'total' => round($sub + $ship, 2), 'country' => $zone['name']];
}
function clean_customer(array $c): array {
    $out = [];
    foreach (['email','phone','firstName','lastName','street','postal','city','country'] as $k) {
        $out[$k] = trim(mb_substr(strip_tags((string)($c[$k] ?? '')), 0, 120));
    }
    if (!filter_var($out['email'], FILTER_VALIDATE_EMAIL)) fail('Ongeldig e-mailadres');
    foreach (['firstName','lastName','street','postal','city'] as $k) if ($out[$k] === '') fail('Vul alle verplichte velden in');
    return $out;
}
function order_path(string $id): string {
    if (!preg_match('/^PF-[0-9A-Z]{6,20}$/', $id)) fail('Ongeldig bestelnummer');
    return __DIR__ . '/orders/' . $id . '.json';
}
function save_order(array $o): void { file_put_contents(order_path($o['id']), json_encode($o, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX); }
function load_order(string $id): ?array { $f = order_path($id); return is_file($f) ? json_decode(file_get_contents($f), true) : null; }
function new_order_id(): string { return 'PF-' . date('ymd') . strtoupper(bin2hex(random_bytes(3))); }

function send_mail(string $to, string $subject, string $text): void {
    $c = cfg();
    $headers = "From: {$c['shop_name']} <{$c['from_email']}>\r\nReply-To: {$c['shop_email']}\r\nContent-Type: text/plain; charset=UTF-8";
    @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $text, $headers);
}
function order_mail_text(array $o): string {
    $t = "Bestelnummer: {$o['id']}\nStatus: {$o['status']}\n\n";
    foreach ($o['pricing']['lines'] as $l) $t .= "{$l['qty']}x {$l['name']} ({$l['config']}) — € " . number_format($l['total'], 2, ',', '.') . "\n";
    $t .= "\nVerzending: € " . number_format($o['pricing']['shipping'], 2, ',', '.') . "\nTotaal: € " . number_format($o['pricing']['total'], 2, ',', '.') . " (incl. btw)\n\n";
    $c = $o['customer'];
    $t .= "Bezorgadres:\n{$c['firstName']} {$c['lastName']}\n{$c['street']}\n{$c['postal']} {$c['city']}\n{$o['pricing']['country']}\n";
    return $t;
}
function notify_paid(array $o): void {
    $c = cfg();
    send_mail($o['customer']['email'], "Bevestiging bestelling {$o['id']} — PiForge",
        "Hoi {$o['customer']['firstName']},\n\nBedankt voor je bestelling bij PiForge. Je betaling is ontvangen; we bouwen, flashen en testen je set en sturen je een trackinglink zodra hij onderweg is.\n\n" . order_mail_text($o) . "\nVragen? Mail naar {$c['shop_email']}.\n\nGroet,\nTeam PiForge (RaspBuilders)");
    send_mail($c['shop_email'], "Nieuwe betaalde bestelling {$o['id']}", order_mail_text($o) . "\nKlant: {$o['customer']['email']} {$o['customer']['phone']}\nBetaald via: {$o['provider']}");
}
function http_json(string $method, string $url, array $headers, ?string $body = null): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 20]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    $res = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return [$code, json_decode((string)$res, true) ?: []];
}
