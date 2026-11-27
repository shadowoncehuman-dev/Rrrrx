<?php
// ── Video player and server-side video lookup ────────────────────
// AES key NEVER touches the browser.
require_once 'config.php';
if(session_status()===PHP_SESSION_NONE) session_start();
auto_login();
send_security_headers();

function supported_media_url(string $url): bool {
    $parts = parse_url($url);
    if (!$parts || strtolower($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])
        || isset($parts['user']) || isset($parts['pass'])
        || (isset($parts['port']) && $parts['port'] !== 443)) {
        return false;
    }

    $host = strtolower(rtrim($parts['host'], '.'));
    foreach ([
        'appx.co.in', 'classx.co.in', 'teachx.in', 'kaxa.in',
        'akamai.net.in', 'akamaized.net', 'cloudfront.net',
        'amazonaws.com', 'googleapis.com', 'googlevideo.com',
    ] as $domain) {
        if ($host === $domain || str_ends_with($host, '.'.$domain)) {
            return true;
        }
    }
    return false;
}

function resolve_media_redirect(string $base, string $location): string {
    if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $location)) {
        return $location;
    }

    $parts = parse_url($base);
    if (!$parts || empty($parts['host'])) {
        return '';
    }
    $scheme = $parts['scheme'] ?? 'https';
    $origin = $scheme.'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');

    if (str_starts_with($location, '//')) {
        return $scheme.':'.$location;
    }

    $reference = parse_url($location);
    $path = $reference['path'] ?? '';
    if ($path === '') {
        $path = $parts['path'] ?? '/';
    } elseif (!str_starts_with($path, '/')) {
        $path = rtrim(dirname($parts['path'] ?? '/'), '/').'/'.$path;
    }

    $segments = [];
    foreach (explode('/', $path) as $segment) {
        if ($segment === '..') {
            array_pop($segments);
        } elseif ($segment !== '.' && !($segment === '' && !$segments)) {
            $segments[] = $segment;
        }
    }
    $path = '/'.implode('/', $segments);
    if (str_ends_with($path, '/') && !str_ends_with($path, '//')) {
        $path = rtrim($path, '/').'/';
    }

    $query = array_key_exists('query', $reference) ? '?'.$reference['query'] : '';
    $fragment = array_key_exists('fragment', $reference) ? '#'.$reference['fragment'] : '';
    return $origin.$path.$query.$fragment;
}

function proxy_playlist_uri(string $playlistUrl, string $uri): string {
    $resolved = resolve_media_redirect($playlistUrl, $uri);
    if (!supported_media_url($resolved)) {
        return $uri;
    }

    $endpoint = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if (!is_string($endpoint) || $endpoint === '') {
        $endpoint = $_SERVER['SCRIPT_NAME'] ?? '/play.php';
    }
    $url = $endpoint.'?stream=1&url='.rawurlencode($resolved);
    $keyB64 = current_stream_key_b64();
    if ($keyB64 !== '') {
        $url .= '&key='.rawurlencode($keyB64);
    }
    return $url;
}

/**
 * Stream key (AES-128) supplied by the API, base64-encoded in the query.
 */
function current_stream_key_b64(): string {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $cached = '';
    $raw = $_GET['key'] ?? '';
    if (is_string($raw) && $raw !== '') {
        $bin = base64_decode(strtr($raw, '-_', '+/'), true);
        if ($bin !== false && strlen($bin) === 16) {
            $cached = base64_encode($bin);
        }
    }
    return $cached;
}

function stream_key_endpoint(): string {
    $endpoint = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if (!is_string($endpoint) || $endpoint === '') {
        $endpoint = $_SERVER['SCRIPT_NAME'] ?? '/play.php';
    }
    return $endpoint.'?key=1&k='.rawurlencode(current_stream_key_b64());
}

function rewrite_media_playlist(string $playlist, string $playlistUrl): string {
    $lines = preg_split('/(\r\n|\n|\r)/', $playlist, -1, PREG_SPLIT_DELIM_CAPTURE);
    if ($lines === false) {
        return $playlist;
    }

    $keyB64 = current_stream_key_b64();
    foreach ($lines as $index => $line) {
        if ($index % 2 === 1 || $line === '') {
            continue;
        }
        if (str_starts_with($line, '#')) {
            if ($keyB64 !== '' && stripos($line, '#EXT-X-KEY') === 0 && stripos($line, 'METHOD=NONE') === false) {
                $lines[$index] = preg_replace(
                    '/(\bURI\s*=\s*")[^"]*(")/i',
                    '${1}'.stream_key_endpoint().'${2}',
                    $line
                ) ?? $line;
                continue;
            }
            $lines[$index] = preg_replace_callback(
                '/(\bURI\s*=\s*")([^"]+)(")/i',
                static fn(array $match): string => $match[1].proxy_playlist_uri($playlistUrl, $match[2]).$match[3],
                $line
            ) ?? $line;
        } else {
            $lines[$index] = proxy_playlist_uri($playlistUrl, trim($line));
        }
    }
    return implode('', $lines);
}

function send_media_headers(array $headers, int $status, string $url): void {
    http_response_code($status ?: 502);
    foreach (['content-length', 'content-range', 'accept-ranges', 'last-modified', 'etag'] as $name) {
        if (isset($headers[$name])) {
            header($name.': '.$headers[$name]);
        }
    }

    $contentType = $headers['content-type'] ?? '';
    if ($contentType === '' || strtolower(trim(explode(';', $contentType, 2)[0])) === 'application/octet-stream') {
        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));
        if ($extension === 'mp4') {
            $contentType = 'video/mp4';
        }
    }
    if ($contentType !== '') {
        header('Content-Type: '.$contentType);
    }
    header('Cache-Control: no-store');
    header('X-Accel-Buffering: no');
}

function proxy_media(string $url): void {
    set_time_limit(0);
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if (!in_array($method, ['GET', 'HEAD'], true)) {
        http_response_code(405);
        header('Allow: GET, HEAD');
        exit;
    }

    $requestHeaders = [
        'Accept: */*',
        'Referer: https://www.rozgarapinew.teachx.in/',
        'Origin: https://www.rozgarapinew.teachx.in',
    ];
    $range = $_SERVER['HTTP_RANGE'] ?? '';
    if (is_string($range) && preg_match('/^bytes=(?:\d+-\d*|-\d+)(?:,(?:\d+-\d*|-\d+))*$/D', $range)) {
        $requestHeaders[] = 'Range: '.$range;
    }
    $ifRange = $_SERVER['HTTP_IF_RANGE'] ?? '';
    if (is_string($ifRange) && $ifRange !== '' && !preg_match('/[\r\n]/', $ifRange)) {
        $requestHeaders[] = 'If-Range: '.$ifRange;
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    while (ob_get_level() > 0 && ob_end_flush()) {}
    ob_implicit_flush(true);

    for ($redirect = 0; $redirect <= 4; $redirect++) {
        if (!supported_media_url($url)) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Unsupported media host';
            exit;
        }

        $responseHeaders = [];
        $responseStatus = 0;
        $headersSent = false;
        $playlistBody = '';
        $isPlaylist = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION)) === 'm3u8';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_NOBODY => $method === 'HEAD',
            CURLOPT_HTTPHEADER => $requestHeaders,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Linux; Android 12) AppleWebKit/537.36 Chrome/124 Mobile Safari/537.36',
            CURLOPT_HEADERFUNCTION => static function($handle, string $line) use (&$responseHeaders, &$responseStatus): int {
                if (preg_match('/^HTTP\/\S+\s+(\d{3})/i', $line, $match)) {
                    $responseStatus = (int)$match[1];
                    $responseHeaders = [];
                } elseif (($separator = strpos($line, ':')) !== false) {
                    $name = strtolower(trim(substr($line, 0, $separator)));
                    $responseHeaders[$name] = trim(substr($line, $separator + 1));
                }
                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION => static function($handle, string $chunk) use (&$responseHeaders, &$headersSent, &$playlistBody, &$isPlaylist, $url): int {
                $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
                if ($status >= 300 && $status < 400) {
                    return 0;
                }
                $contentType = strtolower($responseHeaders['content-type'] ?? '');
                $isPlaylist = $isPlaylist || str_contains($contentType, 'mpegurl');
                if ($isPlaylist && $status >= 200 && $status < 300) {
                    $playlistBody .= $chunk;
                    return strlen($chunk);
                }
                if (!$headersSent) {
                    send_media_headers($responseHeaders, $status, $url);
                    $headersSent = true;
                }
                echo $chunk;
                flush();
                return strlen($chunk);
            },
        ]);

        $success = curl_exec($ch);
        $curlError = curl_error($ch);
        $responseStatus = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE) ?: $responseStatus;
        curl_close($ch);

        if ($isPlaylist && $responseStatus >= 200 && $responseStatus < 300 && $success !== false) {
            unset($responseHeaders['content-length'], $responseHeaders['content-range'], $responseHeaders['accept-ranges']);
            $responseHeaders['content-type'] = 'application/vnd.apple.mpegurl';
            send_media_headers($responseHeaders, $responseStatus, $url);
            echo rewrite_media_playlist($playlistBody, $url);
            exit;
        }

        if ($responseStatus >= 300 && $responseStatus < 400 && !$headersSent) {
            $location = $responseHeaders['location'] ?? '';
            if ($location !== '' && $redirect < 4) {
                $url = resolve_media_redirect($url, $location);
                continue;
            }
        }

        if (!$headersSent) {
            if ($success === false && $responseStatus === 0) {
                http_response_code(502);
                header('Content-Type: text/plain; charset=utf-8');
                echo 'Unable to retrieve media'.($curlError ? ': '.$curlError : '');
                exit;
            }
            send_media_headers($responseHeaders, $responseStatus, $url);
        }
        exit;
    }
}

// Serve the AES-128 stream key to the local player (same-origin).
if (($_GET['key'] ?? '') === '1') {
    $bin = base64_decode(strtr((string)($_GET['k'] ?? ''), '-_', '+/'), true);
    if ($bin === false || strlen($bin) !== 16) {
        http_response_code(400);
        exit;
    }
    header('Content-Type: application/octet-stream');
    header('Content-Length: 16');
    header('Cache-Control: private, max-age=300');
    echo $bin;
    exit;
}

$mediaUrl = $_GET['url'] ?? '';
if(!is_string($mediaUrl)){
    http_response_code(400);
    die('Invalid video URL');
}
$mediaUrl = trim($mediaUrl);
if($mediaUrl !== '' || ($_GET['stream'] ?? '') === '1'){
    $mediaParts = parse_url($mediaUrl);
    if(!supported_media_url($mediaUrl) || !$mediaParts){
        http_response_code(400);
        die('Invalid video URL');
    }

    if(($_GET['stream'] ?? '') === '1'){
        proxy_media($mediaUrl);
    }

    $sourceJson = json_encode($mediaUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    if($sourceJson === false){
        http_response_code(400);
        die('Invalid video URL');
    }

    $isHls = strtolower(pathinfo($mediaParts['path'] ?? '', PATHINFO_EXTENSION)) === 'm3u8';
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="referrer" content="no-referrer">
<title>Video Player</title>
<style>
*{box-sizing:border-box}
:root{--brand:#6366f1;--brand-2:#8b5cf6;--bg:#070a16}
html,body{width:100%;height:100%;margin:0;background:var(--bg);color:#f1f5f9;font-family:'Plus Jakarta Sans',system-ui,sans-serif}
body{display:grid;place-items:center}
main{width:min(100%,900px);padding:16px}
video{display:block;width:100%;max-height:calc(100dvh - 32px);background:#000;border-radius:16px;box-shadow:0 20px 60px -30px rgba(0,0,0,.9)}
#error{color:#f87171;text-align:center;padding:14px;font-size:13px}
</style>
</head>
<body>
<main>
<video id="video" controls playsinline preload="metadata"></video>
<p id="error" hidden></p>
</main>
<?php if($isHls): ?>
<script src="https://cdn.jsdelivr.net/npm/hls.js@1.5.17/dist/hls.min.js"></script>
<?php endif; ?>
<script>
const source=<?=$sourceJson?>;
const video=document.getElementById('video');
const error=document.getElementById('error');
function showError(message){error.textContent=message;error.hidden=false;}
video.addEventListener('error',()=>showError('Unable to load this video. Check your connection and try again.'));
function proxiedSource(url){
  const endpoint=new URL(window.location.href);
  endpoint.searchParams.set('stream','1');
  endpoint.searchParams.set('url',url);
  return endpoint.toString();
}
<?php if($isHls): ?>
if(video.canPlayType('application/vnd.apple.mpegurl')){
  video.src=proxiedSource(source);
}else if(typeof Hls!=='undefined'&&Hls.isSupported()){
  const hls=new Hls();
  hls.loadSource(proxiedSource(source));
  hls.attachMedia(video);
  hls.on(Hls.Events.ERROR,(_,data)=>{
    if(data.fatal){
      hls.destroy();
      showError('Unable to load this video. Check your connection and try again.');
    }
  });
}else{
  showError('HLS playback is not supported by this browser.');
}
<?php else: ?>
video.src=proxiedSource(source);
<?php endif; ?>
</script>
</body>
</html>
    <?php
    exit;
}

$vid = intval($_GET['vid'] ?? 0);
$bid = intval($_GET['bid'] ?? 0);
$q   = preg_replace('/[^a-z0-9]/', '', $_GET['q'] ?? 'auto');

if(!$vid || !$bid){ http_response_code(400); die('Bad request'); }

$token  = $_SESSION['token'];
$userid = $_SESSION['userid'];

$url = API_BASE.'/get/fetchVideoDetailsById?course_id='.$bid.'&video_id='.$vid.'&ytflag=0&folder_wise_course=0&userid='.urlencode($userid);

$ch=curl_init($url);
curl_setopt_array($ch,[
    CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,CURLOPT_CONNECTTIMEOUT=>8,
    CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_ENCODING=>'',
    CURLOPT_HTTPHEADER=>[
        'Client-Service: Appx','Auth-Key: appxapi',
        'Authorization: '.$token,'User-ID: '.$userid,
        'User-Agent: Mozilla/5.0 (Linux; Android 12) AppleWebKit/537.36',
    ],
]);
$resp=curl_exec($ch);curl_close($ch);

$data=json_decode($resp,true);
$d=$data['data']??null;
if(!$d){
    if(preg_match('/\{"status":[\s\S]*?\}/U',$resp??'',$m)){
        $temp=json_decode($m[0],true);$d=$temp['data']??null;
    }
}
if(!$d){ http_response_code(404); die('Video not found'); }

$player='/player?url=';

// YouTube
if(!empty($d['video_id'])&&empty($d['download_link'])){
    $yt=strlen($d['video_id'])>20?decrypt_appx($d['video_id']):$d['video_id'];
    header('Location: https://www.youtube.com/watch?v='.urlencode($yt));exit;
}
// Direct download link
if(!empty($d['download_link'])){
    $u=decrypt_appx($d['download_link']);
    if($q!=='auto') $u=preg_replace('/(1080p|720p|480p|360p|240p)/',$q,$u);
    header('Location: '.$player.urlencode($u));exit;
}
// Encrypted links array
$lnks=$d['encrypted_links']??[];
foreach($lnks as $lnk){
    if(!empty($lnk['path'])){
        $u=decrypt_appx($lnk['path']);
        if($q!=='auto') $u=preg_replace('/(1080p|720p|480p|360p|240p)/',$q,$u);
        if($u){header('Location: '.$player.urlencode($u));exit;}
    }
}
// Content-list sources (file_link / download_links)
foreach([$d['file_link']??''] as $enc){
    if($enc){$u=decrypt_appx($enc); if($u){header('Location: '.$player.urlencode($u));exit;}}
}
foreach(($d['download_links']??[]) as $lnk){
    if(!empty($lnk['path'])){$u=decrypt_appx($lnk['path']); if($u){header('Location: '.$player.urlencode($u));exit;}}
}
http_response_code(404); die('No playable source');
