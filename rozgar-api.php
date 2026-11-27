<?php
/**
 * ZXKAI — Custom Internal REST API
 * ──────────────────────────────────────────
 * Ye PHP khud ka API server hai jo:
 * 1. External teachx.in API ko proxy karta hai
 * 2. Aggressive caching karta hai (429 se protect karta hai)
 * 3. AES decryption server-side karta hai
 * 4. Koi bhi sensitive data browser tak nahi jaane deta
 *
 * Endpoints:
 *   ?action=batches
 *   ?action=subjects&bid=X
 *   ?action=topics&bid=X&sid=Y
 *   ?action=content&bid=X&sid=Y&tid=Z
 *   ?action=video&bid=X&vid=V&q=720p
 *   ?action=pdf&l=ENCODED_LINK
 *   ?action=ping
 */

require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// ── Output JSON always ────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('X-API-Version: 1.0');

// ── Block non-XHR direct browser access ──────────────────────────
$xhr = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
$ref = $_SERVER['HTTP_REFERER'] ?? '';
if (empty($xhr) && empty($ref) && ($_GET['action'] ?? '') !== 'ping') {
    json_exit(403, 'Direct access forbidden');
}

send_security_headers();
auto_login();

$action = preg_replace('/[^a-z]/', '', strtolower($_GET['action'] ?? ''));

// ── Rate Limit (per IP, 200 req/min) ─────────────────────────────
$ip = $_SERVER['REMOTE_ADDR'] ?? '0';
$rk = DATA_DIR . 'rl2_' . md5($ip) . '.json';
$now = time();
if (file_exists($rk)) {
    $rl = json_decode(@file_get_contents($rk), true) ?: ['c'=>0,'t'=>$now];
    if ($now - $rl['t'] < 60) {
        if ($rl['c'] > 200) json_exit(429, 'Too many requests. Please slow down.');
        $rl['c']++;
    } else { $rl = ['c'=>1,'t'=>$now]; }
} else { $rl = ['c'=>1,'t'=>$now]; }
@file_put_contents($rk, json_encode($rl));

// ── Route ─────────────────────────────────────────────────────────
switch ($action) {
    case 'ping':
        echo json_encode(['ok'=>true,'ts'=>time(),'v'=>'1.1']);
        exit;

    case 'clearcache':
        // Admin only: clears all cached API files
        $files = glob(DATA_DIR . 'api_*.json');
        $deleted = 0;
        foreach ($files as $f) { @unlink($f); $deleted++; }
        echo json_encode(['ok'=>true,'deleted'=>$deleted]);
        exit;

    case 'batches':
        $data = cached_api('/get/mycoursev2?', 'batches_' . MASTER_USERID, 600);
        respond($data);
        break;

    case 'subjects':
        $bid = intval($_GET['bid'] ?? 0);
        if (!$bid) json_exit(400, 'bid required');
        $data = cached_api('/get/allsubjectfrmlivecourseclass?courseid='.$bid.'&start=-1', 'subj_'.$bid, 600);
        respond($data);
        break;

    case 'topics':
        $bid = intval($_GET['bid'] ?? 0);
        $sid = intval($_GET['sid'] ?? 0);
        if (!$bid || !$sid) json_exit(400, 'bid and sid required');
        $data = cached_api('/get/alltopicfrmlivecourseclass?courseid='.$bid.'&subjectid='.$sid.'&start=-1', 'topics_'.$bid.'_'.$sid, 600);
        respond($data);
        break;

    case 'content':
        $bid = intval($_GET['bid'] ?? 0);
        $sid = intval($_GET['sid'] ?? 0);
        $tid = intval($_GET['tid'] ?? 0);
        if (!$bid || !$sid || !$tid) json_exit(400, 'bid, sid, tid required');
        $data = cached_api(
            '/get/livecourseclassbycoursesubtopconceptapiv3?courseid='.$bid.'&subjectid='.$sid.'&topicid='.$tid.'&conceptid=&start=-1',
            'content_'.$bid.'_'.$sid.'_'.$tid,
            300
        );
        // Classify and sanitize — AES keys never go to browser
        if (!empty($data['data'])) {
            $data['data'] = array_map(function($item) {
                // Classification:
                // - Has pdf_link only (no video id)  → 'pdf'
                // - Has pdf_link AND video id         → 'both'  (video + PDF note)
                // - No pdf_link                       → 'video'
                $hasPdf  = !empty($item['pdf_link']) || !empty($item['pdf_link2']);
                $hasVideo = !empty($item['id']);  // id field = video lecture id

                // Prefer whichever encrypted PDF link is actually present
                $pdfRaw = !empty($item['pdf_link']) ? $item['pdf_link'] : ($item['pdf_link2'] ?? '');

                if ($hasPdf && $hasVideo) {
                    $item['_type']    = 'both';
                    $item['_pdf_ref'] = urlencode(trim($pdfRaw));
                } elseif ($hasPdf) {
                    $item['_type']    = 'pdf';
                    $item['_pdf_ref'] = urlencode(trim($pdfRaw));
                } else {
                    $item['_type'] = 'video'; // no pdf_link → pure video lecture
                }

                // Strip raw encrypted/sensitive fields
                unset($item['download_link'], $item['encrypted_links'],
                      $item['pdf_link'], $item['pdf_link2'], $item['video_id']);
                return $item;
            }, $data['data']);
        }
        respond($data);
        break;

    case 'video':
        $vid = intval($_GET['vid'] ?? 0);
        $bid = intval($_GET['bid'] ?? 0);
        $q   = preg_replace('/[^a-z0-9]/', '', $_GET['q'] ?? 'auto');
        $sid = intval($_GET['sid'] ?? 0);
        $tid = intval($_GET['tid'] ?? 0);
        if (!$vid || !$bid) json_exit(400, 'vid and bid required');
        handle_video($vid, $bid, $q, $sid, $tid);
        break;

    case 'pdf':
        $l = $_GET['l'] ?? '';
        if (empty($l)) json_exit(400, 'l (link) required');
        handle_pdf($l);
        break;

    default:
        json_exit(404, 'Unknown action: ' . htmlspecialchars($action));
}

// ──────────────────────────────────────────────────────────────────
// HELPER FUNCTIONS
// ──────────────────────────────────────────────────────────────────

function json_exit(int $code, string $msg): void {
    http_response_code($code);
    echo json_encode(['ok'=>false,'error'=>$msg,'code'=>$code]);
    exit;
}

function respond(array $data): void {
    if (isset($data['_cache_hit'])) {
        header('X-Cache: ' . ($data['_cache_hit'] ? 'HIT' : 'MISS'));
        unset($data['_cache_hit']);
    }
    if (!empty($data['_failed']) || (empty($data['data']) && empty($data['status']))) {
        echo json_encode([
            'ok'=>false,
            'error'=>$data['error'] ?? 'No data returned from upstream',
            'data'=>[],
        ]);
        return;
    }
    unset($data['_failed']);
    $data['ok'] = true;
    echo json_encode($data);
}

/**
 * Fetch from external API with local file cache + stale fallback
 */
function cached_api(string $endpoint, string $cache_key, int $ttl = 600): array {
    $cacheFile = DATA_DIR . 'api_' . md5($cache_key) . '.json';
    $now = time();

    // 1. Serve fresh cache if within TTL
    if (file_exists($cacheFile)) {
        $age = $now - filemtime($cacheFile);
        if ($age < $ttl) {
            $raw = @file_get_contents($cacheFile);
            if ($raw) {
                $d = json_decode($raw, true);
                if ($d && !empty($d['data'])) {
                    $d['_cache_hit'] = true;
                    return $d;
                }
            }
        }
    }

    // 2. Fetch from upstream
    $result = upstream_fetch($endpoint);

    // 3. On upstream failure (429/5xx/network), serve stale cache silently
    if ($result['_failed'] || (empty($result['data']) && empty($result['status']))) {
        if (file_exists($cacheFile)) {
            $raw = @file_get_contents($cacheFile);
            if ($raw) {
                $d = json_decode($raw, true);
                if ($d && !empty($d['data'])) {
                    $d['_cache_hit'] = 'stale';
                    return $d;
                }
            }
        }
        // Absolutely no cache — return proper error
        return [
            'ok'=>false,
            'data'=>[],
            'error'=>$result['error'] ?? 'No data returned from upstream',
            '_failed'=>true,
        ];
    }

    // 4. Save valid data to cache
    if (!empty($result['data'])) {
        @file_put_contents($cacheFile, json_encode($result));
    }

    $result['_cache_hit'] = false;
    return $result;
}

/**
 * Perform cURL request to upstream API
 */
function upstream_fetch(string $endpoint): array {
    $token  = $_SESSION['token']  ?? MASTER_TOKEN;
    $userid = $_SESSION['userid'] ?? MASTER_USERID;

    $sep = (strpos($endpoint, '?') !== false) ? '&' : '?';
    $url = API_BASE . $endpoint . $sep . 'userid=' . urlencode($userid);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_ENCODING       => '',
        CURLOPT_HTTPHEADER     => [
            'Client-Service: Appx',
            'source: website',
            'Auth-Key: appxapi',
            'Authorization: ' . $token,
            'User-ID: '       . $userid,
            'User-Agent: Mozilla/5.0 (Linux; Android 12) AppleWebKit/537.36 Chrome/124 Mobile Safari/537.36',
        ],
    ]);
    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = curl_error($ch);
    curl_close($ch);

    // Any non-success response can be transient; let the caller try stale cache.
    if ($http_code < 200 || $http_code >= 300) {
        return [
            '_failed'=>true,
            'data'=>[],
            'error'=>'Upstream returned HTTP '.$http_code.($curl_err?' ('.$curl_err.')':''),
        ];
    }

    if ($response === false || empty($response)) {
        return ['_failed'=>true,'data'=>[],'error'=>'cURL failed: '.$curl_err];
    }

    $data = json_decode($response, true);
    if ($data === null) {
        // Try extract embedded JSON
        if (preg_match('/\{.*\}/s', $response, $m)) {
            $data = json_decode($m[0], true);
        }
        if ($data === null) {
            return ['_failed'=>true,'data'=>[],'error'=>'JSON parse failed'];
        }
    }

    $data['_failed'] = false;
    return $data;
}

/**
 * Handle video playback — decrypt server-side, send redirect URL
 */
function handle_video(int $vid, int $bid, string $q, int $sid = 0, int $tid = 0): void {
    $token  = $_SESSION['token']  ?? MASTER_TOKEN;
    $userid = $_SESSION['userid'] ?? MASTER_USERID;

    $ep = '/get/fetchVideoDetailsById?course_id='.$bid.'&video_id='.$vid.'&ytflag=0&folder_wise_course=0';
    $result = upstream_fetch($ep);

    if ($result['_failed'] || empty($result['data'])) {
        // Try cache
        $cacheFile = DATA_DIR . 'api_' . md5('vid_'.$vid.'_'.$bid) . '.json';
        if (file_exists($cacheFile)) {
            $raw = @file_get_contents($cacheFile);
            $result = $raw ? (json_decode($raw, true) ?: $result) : $result;
        }
    }

    $d = $result['data'] ?? null;

    // Fallback: resolve straight from the topic content list (survives upstream 429s)
    if (!$d && $sid && $tid) {
        $d = find_content_item($vid, $bid, $sid, $tid);
    }

    if (!$d) { json_exit(404, 'Video not found or upstream error'); }

    // Cache video detail
    $cacheFile = DATA_DIR . 'api_' . md5('vid_'.$vid.'_'.$bid) . '.json';
    if (!empty($d)) @file_put_contents($cacheFile, json_encode($result));

    $player = '/player?url=';

    // YouTube
    if (!empty($d['video_id']) && empty($d['download_link']) && empty($d['file_link'])) {
        $yt = strlen($d['video_id']) > 20 ? decrypt_appx($d['video_id']) : $d['video_id'];
        echo json_encode(['ok'=>true,'type'=>'youtube','url'=>'https://www.youtube.com/watch?v='.urlencode($yt)]);
        exit;
    }

    $applyQ = function(string $u) use ($q): string {
        if ($u !== '' && $q !== 'auto') $u = preg_replace('/(1080p|720p|480p|360p|240p|144p)/', $q, $u);
        return $u;
    };

    // Build playable candidates in preference order: [url, keySuffix]
    $cands = [];
    if (!empty($d['download_link'])) {
        $u = $applyQ(decrypt_appx($d['download_link']));
        if ($u) $cands[] = [$u, stream_key_param($d['video_key'] ?? '')];
    }
    foreach (($d['encrypted_links'] ?? []) as $lnk) {
        if (!empty($lnk['path'])) {
            $u = $applyQ(decrypt_appx($lnk['path']));
            if ($u) $cands[] = [$u, stream_key_param($lnk['key'] ?? '')];
        }
    }
    // Direct MP4 file — often the only source still present on the CDN.
    if (!empty($d['file_link'])) {
        $u = $applyQ(decrypt_appx($d['file_link']));
        if ($u) $cands[] = [$u, ''];
    }
    $links = $d['download_links'] ?? [];
    if (is_array($links) && $links) {
        if ($q !== 'auto') {
            foreach ($links as $lnk) {
                if (($lnk['quality'] ?? '') === $q && !empty($lnk['path'])) {
                    $u = $applyQ(decrypt_appx($lnk['path']));
                    if ($u) $cands[] = [$u, ''];
                }
            }
        }
        foreach ($links as $lnk) {
            if (!empty($lnk['path'])) {
                $u = $applyQ(decrypt_appx($lnk['path']));
                if ($u) $cands[] = [$u, ''];
            }
        }
    }

    if (!$cands) { json_exit(404, 'No playable source found for this video'); }

    // Return the first source the CDN actually serves (dead HLS masters are common).
    $chosen = $cands[0];
    foreach ($cands as $cand) {
        if (media_reachable($cand[0])) { $chosen = $cand; break; }
    }

    echo json_encode(['ok'=>true,'type'=>'video','url'=>$player.urlencode($chosen[0]).$chosen[1]]);
    exit;
}

/**
 * Quick reachability probe so we can skip dead CDN sources.
 */
function media_reachable(string $url): bool {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT        => 6,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_RANGE          => '0-1',
        CURLOPT_ENCODING       => '',
        CURLOPT_HTTPHEADER     => [
            'User-Agent: Mozilla/5.0 (Linux; Android 12) AppleWebKit/537.36 Chrome/124 Mobile Safari/537.36',
            'Referer: https://www.rozgarapinew.teachx.in/',
        ],
    ]);
    curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code >= 200 && $code < 400;
}

/**
 * Build the `&key=` suffix for the player from an encrypted stream key.
 * key -> decrypt (AES) -> base64-decode -> 16 raw bytes -> base64 for transport.
 */
function stream_key_param(string $encKey): string {
    if ($encKey === '') return '';
    $k1 = decrypt_appx($encKey);
    if ($k1 === '') return '';
    $bin = base64_decode($k1, true);
    if ($bin === false || strlen($bin) !== 16) return '';
    return '&key=' . urlencode(base64_encode($bin));
}

/**
 * Pull a single content item from the cached/upstream topic content list.
 */
function find_content_item(int $vid, int $bid, int $sid, int $tid): ?array {
    $data = cached_api(
        '/get/livecourseclassbycoursesubtopconceptapiv3?courseid='.$bid.'&subjectid='.$sid.'&topicid='.$tid.'&conceptid=&start=-1',
        'content_'.$bid.'_'.$sid.'_'.$tid,
        300
    );
    foreach (($data['data'] ?? []) as $item) {
        if ((string)($item['id'] ?? '') === (string)$vid) return $item;
    }
    return null;
}

/**
 * Decrypt file_link / download_links from a content item.
 */
function resolve_content_source(array $d, string $q): string {
    $pick = function(string $enc) use ($q): string {
        if ($enc === '') return '';
        $u = decrypt_appx($enc);
        if ($u === '' || strpos($u, 'http') !== 0) return '';
        if ($q !== 'auto') $u = preg_replace('/(1080p|720p|480p|360p|240p|144p)/', $q, $u);
        return $u;
    };

    if (!empty($d['file_link'])) {
        $u = $pick($d['file_link']);
        if ($u !== '') return $u;
    }

    $links = $d['download_links'] ?? [];
    if (is_array($links) && $links) {
        if ($q !== 'auto') {
            foreach ($links as $lnk) {
                if (($lnk['quality'] ?? '') === $q && !empty($lnk['path'])) {
                    $u = $pick($lnk['path']);
                    if ($u !== '') return $u;
                }
            }
        }
        foreach ($links as $lnk) {
            if (!empty($lnk['path'])) {
                $u = $pick($lnk['path']);
                if ($u !== '') return $u;
            }
        }
    }

    return '';
}

/**
 * Handle PDF — decrypt server-side, return viewer URL
 */
function handle_pdf(string $enc): void {
    $viewer = '/pdf-viewer?url=';

    // The link may arrive raw, url-encoded, or double-encoded depending on caller.
    $candidates = [urldecode($enc), $enc];
    foreach ($candidates as $raw) {
        $raw = trim($raw);
        if ($raw === '') continue;

        if (strpos($raw, 'http') === 0) {
            echo json_encode(['ok'=>true,'type'=>'pdf','url'=>$viewer.urlencode($raw)]);
            exit;
        }

        $u = decrypt_appx($raw);
        if (!empty($u) && strpos($u, 'http') === 0) {
            echo json_encode(['ok'=>true,'type'=>'pdf','url'=>$viewer.urlencode($u)]);
            exit;
        }
    }

    json_exit(400, 'Could not decrypt PDF link');
}
