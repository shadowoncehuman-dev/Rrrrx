<?php
// ── Server-side PDF viewer ───────────────────────────────────────
// Decrypts the PDF link server-side and streams the document with the
// headers the upstream CDN requires. The AES key never reaches the browser.
require_once 'config.php';
if(session_status()===PHP_SESSION_NONE) session_start();
auto_login();
send_security_headers();

$raw = $_GET['url'] ?? $_GET['l'] ?? '';
if(empty($raw)){ http_response_code(400); die('Bad request'); }

$candidates = [$raw];
$dec = urldecode($raw);
if($dec !== $raw) $candidates[] = $dec;

$target = '';
foreach($candidates as $c){
    $d = decrypt_appx($c);
    if($d && strpos($d,'http')===0){ $target=$d; break; }
    if(strpos($c,'http')===0){ $target=$c; break; }
}
if(!$target){ http_response_code(400); die('Invalid link'); }

// Only allow known document hosts (SSRF guard).
$parts = parse_url($target);
$host  = strtolower(rtrim($parts['host'] ?? '', '.'));
$allowed = false;
foreach(['appx.co.in','classx.co.in','teachx.in','kaxa.in','akamai.net.in','akamaized.net','cloudfront.net','amazonaws.com'] as $domain){
    if($host===$domain || str_ends_with($host,'.'.$domain)){ $allowed=true; break; }
}
if(!$allowed || strtolower($parts['scheme'] ?? '') !== 'https'){
    http_response_code(400); die('Invalid link');
}

// Fetch upstream with the Referer/UA the CDN expects, then stream inline.
$ch = curl_init($target);
curl_setopt_array($ch,[
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS      => 5,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_ENCODING       => '',
    CURLOPT_HTTPHEADER     => [
        'User-Agent: Mozilla/5.0 (Linux; Android 12) AppleWebKit/537.36 Chrome/124 Mobile Safari/537.36',
        'Referer: https://www.rozgarapinew.teachx.in/',
        'Accept: application/pdf,*/*',
    ],
]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

if($body !== false && $code >= 200 && $code < 300 && strlen($body) > 0){
    header('Content-Type: '.(str_contains($type ?: '', 'pdf') ? $type : 'application/pdf'));
    header('Content-Disposition: inline; filename="document.pdf"');
    header('Content-Length: '.strlen($body));
    header('Cache-Control: private, max-age=300');
    echo $body;
    exit;
}

// Fallback: hand off to the browser (it may already be authorised).
header('Location: '.$target);
exit;
