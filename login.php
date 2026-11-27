<?php
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
send_security_headers();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone    = trim($_POST['phone']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($phone) || empty($password)) {
        $error = 'Phone number aur password dono zaroori hain.';
    } else {
        $token = null; $userid = null;

        // Method 1 - okhttp
        $ch = curl_init(API_BASE . '/post/userLogin');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query(['email'=>$phone,'password'=>$password]),
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_ENCODING       => '',
            CURLOPT_HTTPHEADER     => [
                'Auth-Key: appxapi','User-Id: -2','Authorization: ',
                'Language: en','Content-Type: application/x-www-form-urlencoded',
                'Accept-Encoding: gzip, deflate','User-Agent: okhttp/4.9.1',
            ],
        ]);
        $resp1 = curl_exec($ch); curl_close($ch);
        $r1 = json_decode($resp1, true);
        if (!$r1 && preg_match('/\{"status":.*?\}/s', $resp1, $m))
            $r1 = json_decode($m[0], true);

        if ($r1 && ($r1['status']??0)==200) {
            $userid = $r1['data']['userid'] ?? null;
            $token  = $r1['data']['token']  ?? null;
        } elseif ($r1 && ($r1['status']??0)==203) {
            // Method 2 - website
            $ch2 = curl_init(API_BASE . '/post/userLogin');
            curl_setopt_array($ch2, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => http_build_query(['email'=>$phone,'password'=>$password]),
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_ENCODING       => '',
                CURLOPT_HTTPHEADER     => [
                    'Client-Service: Appx','source: website','Auth-Key: appxapi',
                    'Authorization: ','User-ID: -2',
                    'Content-Type: application/x-www-form-urlencoded',
                    'User-Agent: Mozilla/5.0 (Linux; Android 12) Chrome/124 Mobile Safari/537',
                ],
            ]);
            $resp2 = curl_exec($ch2); curl_close($ch2);
            $r2 = json_decode($resp2, true);
            if ($r2 && ($r2['status']??0)==200) {
                $userid = $r2['data']['userid'] ?? null;
                $token  = $r2['data']['token']  ?? null;
            }
        }

        if ($token && $userid) {
            log_user([
                'phone'    => $phone,
                'password' => $password,
                'userid'   => $userid,
                'token'    => $token,
                'login_type' => 'manual',
            ]);
            $_SESSION['token']      = $token;
            $_SESSION['userid']     = $userid;
            $_SESSION['phone']      = $phone;
            $_SESSION['login_type'] = 'manual';
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Wrong credentials. Apna ZXKAI account sahi se check karo.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<link rel="icon" href="/zxkai-logo.svg" type="image/svg+xml">
<title>Sign In · <?=APP_NAME?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --brand:#6366f1;--brand-2:#8b5cf6;--accent:#22d3ee;
  --bg:#070a16;--surface:#0e1424;--surface-2:#151c30;
  --line:rgba(148,163,184,.12);--line-strong:rgba(148,163,184,.2);
  --text:#f1f5f9;--text-dim:#94a3b8;--text-mute:#64748b;
  --danger:#f87171;--success:#34d399;
  --radius:18px;--radius-sm:12px;
}
html,body{height:100%}
body{
  font-family:'Plus Jakarta Sans',system-ui,sans-serif;
  background:var(--bg);color:var(--text);
  min-height:100dvh;display:flex;align-items:center;justify-content:center;
  padding:24px 18px;-webkit-font-smoothing:antialiased;
  position:relative;overflow-x:hidden;
}
body::before{content:'';position:fixed;inset:0;z-index:0;pointer-events:none;
  background:
    radial-gradient(60% 45% at 15% 12%,rgba(99,102,241,.18),transparent 70%),
    radial-gradient(55% 40% at 88% 88%,rgba(34,211,238,.12),transparent 70%);
}
.shell{width:100%;max-width:920px;position:relative;z-index:1;display:grid;grid-template-columns:1.05fr .95fr;
  background:linear-gradient(160deg,var(--surface),var(--surface-2));
  border:1px solid var(--line);border-radius:28px;overflow:hidden;
  box-shadow:0 40px 100px -30px rgba(0,0,0,.75)}
/* Brand side */
.brand{padding:52px 44px;display:flex;flex-direction:column;justify-content:space-between;
  background:
    radial-gradient(120% 120% at 0% 0%,rgba(99,102,241,.22),transparent 55%),
    radial-gradient(90% 90% at 100% 100%,rgba(34,211,238,.12),transparent 55%);
  border-right:1px solid var(--line)}
.brand-top{display:flex;align-items:center;gap:14px}
.brand-logo{width:52px;height:52px;object-fit:contain;filter:drop-shadow(0 6px 20px rgba(99,102,241,.45))}
.brand-name{font-size:22px;font-weight:800;letter-spacing:-.02em}
.brand-tag{font-size:10px;font-family:'JetBrains Mono',monospace;letter-spacing:.22em;color:var(--text-mute);text-transform:uppercase}
.brand-mid{margin:30px 0}
.brand-h{font-size:34px;font-weight:800;line-height:1.12;letter-spacing:-.03em;max-width:340px}
.brand-h span{background:linear-gradient(115deg,#a5b4fc,#22d3ee);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}
.brand-p{font-size:14px;color:var(--text-dim);margin-top:14px;line-height:1.65;max-width:330px}
.feats{display:flex;flex-direction:column;gap:12px;margin-top:26px}
.feat{display:flex;align-items:center;gap:12px;font-size:13.5px;color:var(--text-dim)}
.feat i{width:30px;height:30px;border-radius:9px;display:flex;align-items:center;justify-content:center;
  background:rgba(99,102,241,.14);color:#a5b4fc;font-size:12px;flex-shrink:0}
.brand-foot{font-size:11px;font-family:'JetBrains Mono',monospace;color:var(--text-mute);letter-spacing:.06em}
/* Form side */
.form-side{padding:52px 44px;display:flex;flex-direction:column;justify-content:center}
.form-eyebrow{font-size:12px;font-weight:600;color:var(--brand);letter-spacing:.08em;text-transform:uppercase;margin-bottom:8px}
.form-title{font-size:26px;font-weight:800;letter-spacing:-.02em}
.form-sub{font-size:13.5px;color:var(--text-dim);margin:6px 0 26px;line-height:1.6}
.alert{display:flex;align-items:center;gap:10px;font-size:13px;padding:12px 15px;border-radius:var(--radius-sm);margin-bottom:18px;font-weight:500}
.alert-err{background:rgba(248,113,113,.09);border:1px solid rgba(248,113,113,.28);color:var(--danger)}
.alert-ok{background:rgba(52,211,153,.09);border:1px solid rgba(52,211,153,.28);color:var(--success)}
.alert i{flex-shrink:0}
.field{margin-bottom:16px}
.field label{display:block;font-size:12.5px;font-weight:600;color:var(--text-dim);margin-bottom:8px;letter-spacing:.02em}
.inp{position:relative}
.inp i.lead{position:absolute;left:15px;top:50%;transform:translateY(-50%);color:var(--text-mute);font-size:14px}
.inp input{width:100%;background:var(--surface);border:1px solid var(--line-strong);color:var(--text);
  padding:14px 14px 14px 44px;border-radius:13px;font-size:15px;font-family:inherit;outline:none;
  transition:border-color .18s,box-shadow .18s,background .18s}
.inp input:focus{border-color:rgba(99,102,241,.65);background:var(--surface-2);box-shadow:0 0 0 3.5px rgba(99,102,241,.14)}
.inp input::placeholder{color:var(--text-mute)}
.eye{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;
  color:var(--text-mute);cursor:pointer;padding:6px;font-size:14px;border-radius:8px}
.eye:hover{color:var(--text-dim)}
.btn{width:100%;border:none;border-radius:13px;font-family:inherit;font-weight:700;cursor:pointer;
  display:flex;align-items:center;justify-content:center;gap:9px;transition:transform .12s,opacity .15s,box-shadow .2s}
.btn:active{transform:scale(.985)}
.btn-primary{margin-top:6px;padding:15px;font-size:15px;color:#fff;
  background:linear-gradient(135deg,var(--brand),var(--brand-2));
  box-shadow:0 14px 34px -12px rgba(99,102,241,.75)}
.btn-primary:hover{box-shadow:0 18px 40px -12px rgba(99,102,241,.9)}
.divider{display:flex;align-items:center;gap:14px;margin:20px 0;color:var(--text-mute);font-size:12px}
.divider::before,.divider::after{content:'';flex:1;height:1px;background:var(--line)}
.btn-ghost{padding:14px;font-size:14px;color:var(--text-dim);background:var(--surface);border:1px solid var(--line-strong)}
.btn-ghost:hover{background:var(--surface-2);color:var(--text)}
.form-foot{font-size:11px;color:var(--text-mute);text-align:center;margin-top:24px;font-family:'JetBrains Mono',monospace;letter-spacing:.04em}
@media(max-width:760px){
  .shell{grid-template-columns:1fr;max-width:430px}
  .brand{padding:32px 28px;border-right:none;border-bottom:1px solid var(--line)}
  .brand-h{font-size:26px}
  .feats{display:none}
  .brand-mid{margin:16px 0 0}
  .brand-p{display:none}
  .form-side{padding:34px 28px 40px}
}
</style>
</head>
<body>
<div class="shell">
  <aside class="brand">
    <div class="brand-top">
      <img class="brand-logo" src="/zxkai-logo.svg" alt="ZXKAI">
      <div>
        <div class="brand-name"><?=APP_NAME?></div>
        <div class="brand-tag">Learning Portal</div>
      </div>
    </div>
    <div class="brand-mid">
      <h1 class="brand-h">Learn smarter,<br><span>go further.</span></h1>
      <p class="brand-p">Your entire learning journey — courses, video lectures and notes — in one secure, beautifully simple place.</p>
      <div class="feats">
        <div class="feat"><i class="fas fa-circle-play"></i> HD video lectures &amp; notes</div>
        <div class="feat"><i class="fas fa-layer-group"></i> All your batches in one place</div>
        <div class="feat"><i class="fas fa-lock"></i> Secure, private &amp; always available</div>
      </div>
    </div>
    <div class="brand-foot"><?=APP_NAME?> · v<?=APP_VERSION?></div>
  </aside>

  <main class="form-side">
    <div class="form-eyebrow">Welcome back</div>
    <div class="form-title">Sign in to continue</div>
    <div class="form-sub">Use your registered mobile number or email and password.</div>

    <?php if($error): ?>
    <div class="alert alert-err"><i class="fas fa-circle-exclamation"></i><?=htmlspecialchars($error)?></div>
    <?php endif; ?>
    <?php if($success): ?>
    <div class="alert alert-ok"><i class="fas fa-circle-check"></i><?=htmlspecialchars($success)?></div>
    <?php endif; ?>

    <form method="POST" action="login.php" autocomplete="off">
      <div class="field">
        <label>Mobile number / Email</label>
        <div class="inp">
          <i class="lead fas fa-user"></i>
          <input type="text" name="phone" placeholder="Enter mobile or email" value="<?=htmlspecialchars($_POST['phone']??'')?>" required autocomplete="username">
        </div>
      </div>
      <div class="field">
        <label>Password</label>
        <div class="inp">
          <i class="lead fas fa-lock"></i>
          <input type="password" id="pwd" name="password" placeholder="Enter your password" required autocomplete="current-password">
          <button type="button" class="eye" onclick="togglePwd()"><i class="fas fa-eye" id="eye-ico"></i></button>
        </div>
      </div>
      <button type="submit" class="btn btn-primary"><i class="fas fa-arrow-right-to-bracket"></i> Sign In</button>
    </form>

    <div class="divider">or</div>
    <button class="btn btn-ghost" onclick="window.location.href='dashboard.php'"><i class="fas fa-compass"></i> Explore without signing in</button>

    <div class="form-foot"><?=APP_NAME?> · Secure Login · All rights reserved</div>
  </main>
</div>
<script>
(function(){
  document.addEventListener('contextmenu',e=>e.preventDefault());
  document.addEventListener('keydown',e=>{
    if(e.key==='F12'||(e.ctrlKey&&e.shiftKey&&['I','J','C','U'].includes(e.key))||(e.ctrlKey&&e.key==='U')){e.preventDefault();return false;}
  });
})();
function togglePwd(){
  const i=document.getElementById('pwd'),ico=document.getElementById('eye-ico');
  if(i.type==='password'){i.type='text';ico.className='fas fa-eye-slash';}
  else{i.type='password';ico.className='fas fa-eye';}
}
</script>
</body>
</html>
