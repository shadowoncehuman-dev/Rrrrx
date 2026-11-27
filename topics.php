<?php
require_once 'config.php';
if(session_status()===PHP_SESSION_NONE) session_start();
require_login();
send_security_headers();
$bid = intval($_GET['bid'] ?? 0);
$bn  = htmlspecialchars(urldecode($_GET['bn'] ?? 'Batch'), ENT_QUOTES);
$sid = intval($_GET['sid'] ?? 0);
$sn  = htmlspecialchars(urldecode($_GET['sn'] ?? 'Subject'), ENT_QUOTES);
if(!$bid||!$sid){ header('Location: batches.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<meta name="theme-color" content="#070a16">
<link rel="icon" href="/zxkai-logo.svg" type="image/svg+xml">
<title>Topics · <?=APP_NAME?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
*{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent}
:root{--brand:#6366f1;--brand-2:#8b5cf6;--accent:#22d3ee;--bg:#070a16;--surface:#0e1424;--surface-2:#151c30;--line:rgba(148,163,184,.12);--line-strong:rgba(148,163,184,.2);--text:#f1f5f9;--text-dim:#94a3b8;--text-mute:#64748b}
html,body{height:100%;overflow:hidden;background:var(--bg);color:var(--text);font-family:'Plus Jakarta Sans',system-ui,sans-serif;-webkit-font-smoothing:antialiased;-webkit-user-select:none;user-select:none}
::-webkit-scrollbar{width:3px}::-webkit-scrollbar-thumb{background:var(--line-strong);border-radius:3px}
#app{display:flex;flex-direction:column;height:100dvh;max-width:480px;margin:0 auto;background:var(--bg)}
.tb{flex-shrink:0;height:64px;display:flex;align-items:center;padding:0 16px;gap:12px;background:rgba(7,10,22,.92);border-bottom:1px solid var(--line);backdrop-filter:blur(20px);z-index:100}
.back{width:42px;height:42px;border-radius:13px;background:var(--surface-2);border:1px solid var(--line);display:flex;align-items:center;justify-content:center;color:var(--text);font-size:15px;cursor:pointer;flex-shrink:0;transition:all .15s}
.back:active{background:var(--line);transform:scale(.92)}
.tb-info{flex:1;min-width:0}
.tb-title{font-size:15px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;letter-spacing:-.01em}
.tb-sub{font-size:9px;color:var(--text-mute);font-family:'JetBrains Mono',monospace;letter-spacing:.14em;margin-top:2px}
.ib{width:38px;height:38px;border-radius:12px;background:var(--surface-2);border:1px solid var(--line);display:flex;align-items:center;justify-content:center;color:var(--text-dim);font-size:14px;cursor:pointer;flex-shrink:0;transition:all .15s}
.ib:active{background:var(--line);transform:scale(.92)}
.scroll{flex:1;overflow-y:auto;overflow-x:hidden;-webkit-overflow-scrolling:touch;overscroll-behavior:contain}
.crumb{display:flex;align-items:center;gap:7px;padding:14px 18px 0;overflow-x:auto;flex-wrap:nowrap}
.crumb::-webkit-scrollbar{display:none}
.crumb a{font-size:9.5px;color:var(--text-mute);font-family:'JetBrains Mono',monospace;letter-spacing:.08em;white-space:nowrap;cursor:pointer;flex-shrink:0;text-decoration:none}
.crumb a:hover{color:var(--text-dim)}
.crumb a.act{color:#22d3ee;font-weight:600}
.crumb-sep{color:var(--line-strong);font-size:9px;flex-shrink:0}
.hstrip{padding:16px 18px 0}
.hstrip-title{font-size:22px;font-weight:800;letter-spacing:-.025em;margin-bottom:5px}
.hstrip-title em{font-style:normal;background:linear-gradient(115deg,#22d3ee,#a5b4fc);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}
.hstrip-sub{font-size:11px;color:var(--text-mute);font-family:'JetBrains Mono',monospace}
.srch-wrap{padding:16px 18px 0}
.srch{position:relative}
.srch-i{position:absolute;left:15px;top:50%;transform:translateY(-50%);color:var(--text-mute);font-size:13px;pointer-events:none}
.srch input{width:100%;background:var(--surface);border:1px solid var(--line-strong);color:var(--text);padding:13px 15px 13px 44px;border-radius:14px;font-size:14px;font-family:inherit;outline:none;transition:border-color .2s}
.srch input:focus{border-color:rgba(34,211,238,.6);background:var(--surface-2)}
.srch input::placeholder{color:var(--text-mute)}
.sh{display:flex;align-items:center;padding:20px 18px 12px}
.sh-left{display:flex;align-items:center;gap:9px;flex:1}
.sh-ico{width:30px;height:30px;border-radius:9px;background:rgba(34,211,238,.09);border:1px solid rgba(34,211,238,.2);display:flex;align-items:center;justify-content:center;font-size:12px;color:var(--accent)}
.sh-txt{font-size:14px;font-weight:700}
.sh-cnt{font-size:9px;font-family:'JetBrains Mono',monospace;background:var(--surface-2);border:1px solid var(--line);color:var(--text-mute);padding:4px 10px;border-radius:8px}
.list{display:flex;flex-direction:column;gap:10px;padding:0 18px 100px}
.lcard{background:var(--surface);border:1px solid var(--line);border-radius:16px;padding:16px;display:flex;align-items:center;gap:14px;cursor:pointer;transition:all .13s;animation:up .32s ease both;position:relative;overflow:hidden}
@keyframes up{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
.lcard::before{content:'';position:absolute;left:0;top:0;bottom:0;width:3px;background:linear-gradient(to bottom,var(--accent),var(--brand));border-radius:3px 0 0 3px;opacity:0;transition:opacity .2s}
.lcard:active{background:var(--surface-2);border-color:rgba(34,211,238,.32);transform:scale(.985)}
.lcard:active::before{opacity:1}
.lico{width:46px;height:46px;border-radius:13px;background:rgba(34,211,238,.08);border:1px solid rgba(34,211,238,.18);display:flex;align-items:center;justify-content:center;font-size:18px;color:var(--accent);flex-shrink:0}
.ltxt{flex:1;min-width:0}
.lname{font-size:14.5px;font-weight:600;line-height:1.3;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;letter-spacing:-.01em}
.lsub{font-size:10px;color:var(--text-mute);margin-top:4px;font-family:'JetBrains Mono',monospace;letter-spacing:.03em}
.larr{color:var(--text-mute);font-size:12px;flex-shrink:0}
.ld-wrap{display:flex;flex-direction:column;align-items:center;padding:70px 20px;gap:15px}
.spin{width:36px;height:36px;border:2.5px solid var(--line);border-top-color:var(--accent);border-radius:50%;animation:spin .75s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
.ld-txt{font-size:10px;color:var(--text-mute);font-family:'JetBrains Mono',monospace;letter-spacing:.14em}
.empty-wrap{display:flex;flex-direction:column;align-items:center;padding:70px 20px;gap:13px;text-align:center;color:var(--text-mute)}
.empty-wrap i{font-size:44px;opacity:.3}
.empty-wrap h3{font-size:15px;font-weight:700;color:var(--text-dim)}
.empty-wrap p{font-size:12px;line-height:1.6;max-width:240px}
.retry{margin-top:6px;background:rgba(34,211,238,.1);border:1px solid rgba(34,211,238,.28);color:var(--accent);padding:12px 24px;border-radius:12px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit}
.bnav{flex-shrink:0;height:72px;background:rgba(10,14,28,.97);border-top:1px solid var(--line);display:flex;align-items:center;padding-bottom:env(safe-area-inset-bottom)}
.ni{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;cursor:pointer;color:var(--text-mute);padding:8px}
.ni.on{color:var(--accent)}
.ni i{font-size:20px}
.ni span{font-size:9px;font-weight:700;font-family:'JetBrains Mono',monospace;letter-spacing:.1em}
.toast{position:fixed;bottom:90px;left:50%;transform:translateX(-50%) translateY(12px);background:rgba(14,20,36,.98);border:1px solid var(--line-strong);color:var(--text);padding:12px 22px;border-radius:15px;font-size:13px;font-weight:600;display:flex;align-items:center;gap:9px;opacity:0;transition:all .3s;z-index:9998;max-width:88vw;backdrop-filter:blur(16px);white-space:nowrap;pointer-events:none}
.toast.on{opacity:1;transform:translateX(-50%) translateY(0)}
</style>
</head>
<body>
<div id="app">
  <div class="tb">
    <div class="back" onclick="history.back()"><i class="fas fa-chevron-left"></i></div>
    <div class="tb-info">
      <div class="tb-title"><?=htmlspecialchars($sn)?></div>
      <div class="tb-sub">CHOOSE A TOPIC</div>
    </div>
    <div class="ib" onclick="window.location.href='batches.php'"><i class="fas fa-house"></i></div>
  </div>
  <div class="scroll">
    <div class="crumb">
      <a onclick="window.location.href='batches.php'">BATCHES</a>
      <span class="crumb-sep"><i class="fas fa-chevron-right"></i></span>
      <a onclick="history.go(-1)"><?=htmlspecialchars($bn)?></a>
      <span class="crumb-sep"><i class="fas fa-chevron-right"></i></span>
      <a class="act"><?=htmlspecialchars($sn)?></a>
    </div>
    <div class="hstrip">
      <div class="hstrip-title">Choose <em>Topic</em></div>
      <div class="hstrip-sub" id="hs">Loading topics…</div>
    </div>
    <div class="srch-wrap">
      <div class="srch"><i class="srch-i fas fa-magnifying-glass"></i>
        <input type="text" placeholder="Search topics…" id="srch" oninput="filter(this.value)" autocomplete="off">
      </div>
    </div>
    <div class="sh">
      <div class="sh-left"><div class="sh-ico"><i class="fas fa-lightbulb"></i></div><div class="sh-txt">Topics</div></div>
      <div class="sh-cnt" id="cnt">…</div>
    </div>
    <div class="list" id="list">
      <div class="ld-wrap"><div class="spin"></div><div class="ld-txt">LOADING TOPICS…</div></div>
    </div>
  </div>
  <nav class="bnav">
    <div class="ni" onclick="window.location.href='batches.php'"><i class="fas fa-house"></i><span>HOME</span></div>
    <div class="ni on"><i class="fas fa-lightbulb"></i><span>TOPICS</span></div>
    <div class="ni" onclick="history.back()"><i class="fas fa-arrow-left"></i><span>BACK</span></div>
  </nav>
</div>
<div class="toast" id="toast"><i class="fas fa-circle-info" style="color:var(--accent)"></i><span id="tmsg"></span></div>
<script>
(function(){const esc=()=>{try{document.documentElement.innerHTML='';}catch(e){}window.location.replace('about:blank');};setInterval(()=>{if((window.outerWidth-window.innerWidth)>180||(window.outerHeight-window.innerHeight)>230)esc();},900);document.addEventListener('contextmenu',e=>e.preventDefault());document.addEventListener('keydown',e=>{if(e.key==='F12'||(e.ctrlKey&&e.shiftKey&&['I','J','C','U'].includes(e.key))||(e.ctrlKey&&['U','S'].includes(e.key)))e.preventDefault();});document.addEventListener('selectstart',e=>e.preventDefault());const _d={};Object.defineProperty(_d,'id',{get:()=>esc()});setInterval(()=>console.log(_d),2500);})();
const BID=<?=intval($bid)?>,BN=<?=json_encode(urldecode($_GET['bn']??'Batch'))?>,SID=<?=intval($sid)?>,SN=<?=json_encode(urldecode($_GET['sn']??'Subject'))?>;
let _all=[];
function H(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
async function rApi(action,params={},tries=4){for(let i=0;i<tries;i++){try{const qs=new URLSearchParams({action,...params}).toString();const r=await fetch('rozgar-api.php?'+qs,{headers:{'X-Requested-With':'XMLHttpRequest'},cache:'no-cache'});if(!r.ok)throw new Error('HTTP '+r.status);const d=await r.json();if(!d.ok&&i<tries-1)throw new Error(d.error||'err');return d;}catch(e){if(i<tries-1)await new Promise(r=>setTimeout(r,1800*(i+1)));else return{ok:false,data:[],error:e.message};}}
}
document.addEventListener('DOMContentLoaded',load);
async function load(){
  document.getElementById('list').innerHTML='<div class="ld-wrap"><div class="spin"></div><div class="ld-txt">LOADING TOPICS…</div></div>';
  const r=await rApi('topics',{bid:BID,sid:SID});
  _all=(r.data||[]).sort((a,b)=>(a.topicid||0)-(b.topicid||0));
  if(!_all.length){document.getElementById('list').innerHTML=`<div class="empty-wrap"><i class="fas fa-satellite-dish"></i><h3>Topics nahi aaye</h3><p>${H(r.error||'Network error')}</p><button class="retry" onclick="load()"><i class="fas fa-rotate"></i> Retry</button></div>`;document.getElementById('cnt').textContent='ERR';return;}
  document.getElementById('hs').textContent=_all.length+' topics available';
  render(_all);
}
function filter(q){render(q?_all.filter(t=>(t.topic_name||'').toLowerCase().includes(q.toLowerCase())):_all);}
function render(list){
  document.getElementById('cnt').textContent=list.length+' TOTAL';
  const el=document.getElementById('list');
  if(!list.length){el.innerHTML='<div class="empty-wrap"><i class="fas fa-folder-open"></i><h3>Koi topic nahi mila</h3></div>';return;}
  el.innerHTML=list.map((t,i)=>`<div class="lcard" onclick="go(${t.topicid})" style="animation-delay:${i*.04}s">
    <div class="lico"><i class="fas fa-lightbulb"></i></div>
    <div class="ltxt"><div class="lname">${H(t.topic_name)}</div><div class="lsub">${t.content_count?t.content_count+' ITEMS':'TAP TO OPEN'}</div></div>
    <i class="fas fa-chevron-right larr"></i>
  </div>`).join('');
}
function go(tid){const t=_all.find(x=>x.topicid==tid);window.location.href='content.php?bid='+BID+'&bn='+encodeURIComponent(BN)+'&sid='+SID+'&sn='+encodeURIComponent(SN)+'&tid='+tid+'&tn='+encodeURIComponent(t?.topic_name||'Topic');}
let _tt;function toast(m){const t=document.getElementById('toast');document.getElementById('tmsg').textContent=m;t.classList.add('on');clearTimeout(_tt);_tt=setTimeout(()=>t.classList.remove('on'),2800);}
</script>
</body>
</html>