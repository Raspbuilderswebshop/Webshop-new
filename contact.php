<?php
require __DIR__ . '/_lib.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Alleen POST', 405);
$c = cfg(); $in = body();
if (!empty($in['website'])) ok(['ok' => true]); // honeypot tegen spam
$name = trim(mb_substr(strip_tags((string)($in['name'] ?? '')), 0, 100));
$email = trim((string)($in['email'] ?? ''));
$subject = trim(mb_substr(strip_tags((string)($in['subject'] ?? 'Contact')), 0, 100));
$msg = trim(mb_substr(strip_tags((string)($in['message'] ?? '')), 0, 5000));
if (!$name || !$msg || !filter_var($email, FILTER_VALIDATE_EMAIL)) fail('Vul alle velden correct in');
$headers = "From: {$c['shop_name']} <{$c['from_email']}>\r\nReply-To: $name <$email>\r\nContent-Type: text/plain; charset=UTF-8";
$sent = @mail($c['shop_email'], '=?UTF-8?B?' . base64_encode("[PiForge] $subject") . '?=', "Van: $name <$email>\n\n$msg", $headers);
if (!$sent) fail('Mail kon niet worden verstuurd', 500);
ok(['ok' => true]);
