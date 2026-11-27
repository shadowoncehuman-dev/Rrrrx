<?php
require_once 'config.php';
if(session_status()===PHP_SESSION_NONE) session_start();
require_login();
send_security_headers();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<meta name="theme-color" content="#070a16">
<link rel="icon" href="/zxkai-logo.svg" type="image/svg+xml">
<title><?=APP_NAME?> · My Batches</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
*{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent}
:root{
  --brand:#6366f1;--brand-2:#8b5cf6;--accent:#22d3ee;
  --bg:#070a16;--surface:#0e1424;--surface-2:#151c30;
  --line:rgba(148,163,184,.12);--line-strong:rgba(148,163,184,.2);
  --text:#f1f5f9;--text-dim:#94a3b8;--text-mute:#64748b;
  --green:#34d399;--red:#f87171;--amber:#fbbf24;
  --radius:20px;--radius-sm:13px;
}
html,body{height:100%;overflow:hidden;background:var(--bg);color:var(--text);font-family:'Plus Jakarta Sans',system-ui,sans-serif;-webkit-font-smoothing:antialiased}
body{-webkit-user-select:none;user-select:none}
::-webkit-scrollbar{width:3px}::-webkit-scrollbar-thumb{background:var(--line-strong);border-radius:3px}
#app{display:flex;flex-direction:column;height:100dvh;max-width:480px;margin:0 auto;background:var(--bg);position:relative;overflow:hidden}
#app::before{content:'';position:absolute;top:0;left:50%;transform:translateX(-50%);width:240px;height:1px;background:linear-gradient(90deg,transparent,rgba(99,102,241,.7),transparent);z-index:200}
/* Topbar */
.tb{flex-shrink:0;height:64px;display:flex;align-items:center;padding:0 18px;gap:13px;background:rgba(7,10,22,.9);border-bottom:1px solid var(--line);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);z-index:100}
.tb-logo{width:42px;height:42px;border-radius:13px;object-fit:contain;filter:drop-shadow(0 4px 14px rgba(99,102,241,.4))}
.tb-info{flex:1;min-width:0}
.tb-title{font-size:16px;font-weight:800;letter-spacing:-.01em;line-height:1.1}
.tb-sub{font-size:9px;color:var(--text-mute);font-family:'JetBrains Mono',monospace;letter-spacing:.16em;margin-top:2px}
.ib{width:38px;height:38px;border-radius:12px;background:var(--surface-2);border:1px solid var(--line);display:flex;align-items:center;justify-content:center;color:var(--text-dim);font-size:14px;cursor:pointer;transition:all .15s;flex-shrink:0}
.ib:active{background:var(--line);transform:scale(.92)}
/* Scroll */
.scroll{flex:1;overflow-y:auto;overflow-x:hidden;-webkit-overflow-scrolling:touch;overscroll-behavior:contain}
/* Hero */
.hero{padding:24px 18px 0;position:relative}
.hero-glow{position:absolute;top:0;left:50%;transform:translateX(-50%);width:320px;height:160px;background:radial-gradient(ellipse,rgba(99,102,241,.18),transparent 70%);pointer-events:none}
.live-chip{display:inline-flex;align-items:center;gap:7px;background:rgba(52,211,153,.08);border:1px solid rgba(52,211,153,.22);border-radius:20px;padding:6px 14px;margin-bottom:16px}
.live-dot{width:7px;height:7px;border-radius:50%;background:var(--green);box-shadow:0 0 10px var(--green);animation:blink 2s ease-in-out infinite}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.35}}
.live-chip span{font-size:10px;color:#6ee7b7;font-family:'JetBrains Mono',monospace;font-weight:600;letter-spacing:.12em}
.hero-h{font-size:30px;font-weight:800;line-height:1.08;letter-spacing:-.025em;margin-bottom:6px}
.hero-h em{font-style:normal;background:linear-gradient(115deg,#a5b4fc,#22d3ee);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}
.hero-s{font-size:12px;color:var(--text-mute);font-family:'JetBrains Mono',monospace;letter-spacing:.02em}
/* Stats */
.stats{display:flex;gap:10px;padding:20px 18px 0}
.stat{flex:1;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius-sm);padding:14px 13px;display:flex;flex-direction:column;gap:3px}
.stat-val{font-size:22px;font-weight:800;background:linear-gradient(135deg,#a5b4fc,#22d3ee);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}
.stat-lbl{font-size:9px;color:var(--text-mute);font-family:'JetBrains Mono',monospace;letter-spacing:.12em}
/* Search */
.srch-wrap{padding:18px 18px 0}
.srch{position:relative}
.srch-i{position:absolute;left:16px;top:50%;transform:translateY(-50%);color:var(--text-mute);font-size:13px;pointer-events:none}
.srch input{width:100%;background:var(--surface);border:1px solid var(--line-strong);color:var(--text);padding:14px 16px 14px 46px;border-radius:14px;font-size:14px;font-family:inherit;outline:none;transition:border-color .2s,background .2s}
.srch input:focus{border-color:rgba(99,102,241,.6);background:var(--surface-2)}
.srch input::placeholder{color:var(--text-mute)}
/* Section */
.sh{display:flex;align-items:center;padding:22px 18px 13px}
.sh-left{display:flex;align-items:center;gap:10px;flex:1}
.sh-ico{width:32px;height:32px;border-radius:10px;background:rgba(99,102,241,.14);border:1px solid rgba(99,102,241,.24);display:flex;align-items:center;justify-content:center;font-size:13px;color:#a5b4fc}
.sh-txt{font-size:15px;font-weight:700}
.sh-cnt{font-size:9px;font-family:'JetBrains Mono',monospace;background:var(--surface-2);border:1px solid var(--line);color:var(--text-mute);padding:4px 10px;border-radius:8px}
/* Grid */
.bgrid{display:flex;flex-direction:column;gap:14px;padding:0 18px 104px}
.bcard{background:var(--surface);border:1px solid var(--line);border-radius:var(--radius);overflow:hidden;cursor:pointer;transition:transform .12s,border-color .2s,box-shadow .2s;animation:up .35s ease both;box-shadow:0 6px 24px -18px rgba(0,0,0,.8)}
@keyframes up{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
.bcard:active{transform:scale(.98);border-color:rgba(99,102,241,.4)}
.bthumb{position:relative;width:100%;aspect-ratio:16/7;overflow:hidden;background:var(--surface-2)}
.bthumb img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .4s ease}
.bcard:active .bthumb img{transform:scale(1.03)}
.boverlay{position:absolute;inset:0;background:linear-gradient(to top,rgba(7,10,22,.97) 0%,rgba(7,10,22,.55) 45%,transparent 100%)}
.binfo{position:absolute;bottom:0;left:0;right:0;padding:15px 16px}
.btitle{font-size:15px;font-weight:700;color:#fff;line-height:1.3;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;margin-bottom:9px;letter-spacing:-.01em}
.bchips{display:flex;gap:7px;flex-wrap:wrap;align-items:center}
.chip{font-size:9px;padding:4px 9px;border-radius:7px;font-weight:700;font-family:'JetBrains Mono',monospace;display:inline-flex;align-items:center;gap:4px;letter-spacing:.03em}
.c-live{background:rgba(52,211,153,.13);color:#6ee7b7;border:1px solid rgba(52,211,153,.26)}
.c-price{background:rgba(99,102,241,.16);color:#a5b4fc;border:1px solid rgba(99,102,241,.3)}
.c-free{background:rgba(34,211,238,.11);color:var(--accent);border:1px solid rgba(34,211,238,.22)}
/* Fav */
.bfav{position:absolute;top:12px;right:12px;width:38px;height:38px;border-radius:50%;background:rgba(0,0,0,.55);border:1px solid rgba(255,255,255,.1);color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:14px;backdrop-filter:blur(8px);transition:transform .2s;z-index:2}
.bfav:active{transform:scale(1.25)}
.bfav.on{color:var(--red)}
/* CTA */
.bcta{display:flex;align-items:center;justify-content:center;gap:9px;margin:0 15px 15px;background:linear-gradient(135deg,var(--brand),var(--brand-2));border:none;border-radius:13px;padding:14px 16px;color:#fff;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;width:calc(100% - 30px);letter-spacing:.01em;box-shadow:0 10px 26px -12px rgba(99,102,241,.85);transition:opacity .15s}
.bcta:active{opacity:.82}
/* States */
.ld-wrap{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:70px 20px;gap:15px}
.spin{width:38px;height:38px;border:2.5px solid var(--line);border-top-color:var(--brand);border-radius:50%;animation:spin .75s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
.ld-txt{font-size:10px;color:var(--text-mute);font-family:'JetBrains Mono',monospace;letter-spacing:.14em}
.empty-wrap{display:flex;flex-direction:column;align-items:center;padding:70px 20px;gap:13px;text-align:center;color:var(--text-mute)}
.empty-wrap i{font-size:46px;opacity:.3}
.empty-wrap h3{font-size:16px;font-weight:700;color:var(--text-dim)}
.empty-wrap p{font-size:12px;line-height:1.6;max-width:260px}
.retry{margin-top:8px;background:rgba(99,102,241,.15);border:1px solid rgba(99,102,241,.32);color:#a5b4fc;padding:12px 26px;border-radius:12px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit}
.retry:active{background:rgba(99,102,241,.26)}
/* Bottom nav */
.bnav{flex-shrink:0;height:72px;background:rgba(10,14,28,.97);border-top:1px solid var(--line);display:flex;align-items:center;padding-bottom:env(safe-area-inset-bottom);z-index:100}
.ni{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;cursor:pointer;color:var(--text-mute);transition:color .2s;padding:8px}
.ni.on{color:#a5b4fc}
.ni i{font-size:20px;transition:transform .2s}
.ni.on i{transform:scale(1.12)}
.ni span{font-size:9px;font-weight:700;font-family:'JetBrains Mono',monospace;letter-spacing:.1em}
.ni.on::after{content:'';display:block;width:22px;height:2.5px;border-radius:2px;background:#a5b4fc;margin-top:-2px}
/* Toast */
.toast{position:fixed;bottom:90px;left:50%;transform:translateX(-50%) translateY(12px);background:rgba(14,20,36,.98);border:1px solid var(--line-strong);color:var(--text);padding:12px 22px;border-radius:15px;font-size:13px;font-weight:600;display:flex;align-items:center;gap:9px;opacity:0;transition:all .3s;z-index:9998;max-width:88vw;backdrop-filter:blur(16px);white-space:nowrap;pointer-events:none}
.toast.on{opacity:1;transform:translateX(-50%) translateY(0)}
</style>
</head>
<body>
<?php include 'splash.php'; ?>
<div id="app">
  <div class="tb">
    <img class="tb-logo" src="/zxkai-logo.svg" alt="logo">
    <div class="tb-info">
      <div class="tb-title"><?=APP_NAME?></div>
      <div class="tb-sub">MY LEARNING</div>
    </div>
    <div class="ib" onclick="fetchBatches()"><i class="fas fa-rotate"></i></div>
  </div>

  <div class="scroll" id="scr">
    <div class="hero">
      <div class="hero-glow"></div>
      <div class="live-chip"><div class="live-dot"></div><span>ALL COURSES LIVE</span></div>
      <div class="hero-h">Your<br><em>dashboard.</em></div>
      <div class="hero-s" id="hero-sub">Loading your courses…</div>
    </div>
    <div class="stats">
      <div class="stat"><div class="stat-val" id="st-total">—</div><div class="stat-lbl">BATCHES</div></div>
      <div class="stat"><div class="stat-val" id="st-free">—</div><div class="stat-lbl">FREE</div></div>
      <div class="stat"><div class="stat-val" id="st-paid">—</div><div class="stat-lbl">PAID</div></div>
    </div>
    <div class="srch-wrap">
      <div class="srch"><i class="srch-i fas fa-magnifying-glass"></i>
        <input type="text" placeholder="Search batches…" id="srch" oninput="filter(this.value)" autocomplete="off">
      </div>
    </div>
    <div class="sh">
      <div class="sh-left"><div class="sh-ico"><i class="fas fa-layer-group"></i></div><div class="sh-txt">Enrolled Courses</div></div>
      <div class="sh-cnt" id="sh-cnt">…</div>
    </div>
    <div class="bgrid" id="grid">
      <div class="ld-wrap"><div class="spin"></div><div class="ld-txt">LOADING BATCHES…</div></div>
    </div>
  </div>

  <nav class="bnav">
    <div class="ni on" onclick="navTo('batches.php')"><i class="fas fa-house"></i><span>HOME</span></div>
    <div class="ni" onclick="navTo('dashboard.php')"><i class="fas fa-th-large"></i><span>EXPLORE</span></div>
    <div class="ni" onclick="navTo('logout.php')"><i class="fas fa-arrow-right-from-bracket"></i><span>LOGOUT</span></div>
  </nav>
</div>

<div class="toast" id="toast"><i class="fas fa-circle-info" style="color:#a5b4fc"></i><span id="tmsg"></span></div>

<script>
(function(){
  const esc=()=>{try{document.documentElement.innerHTML='';}catch(e){}window.location.replace('about:blank');};
  setInterval(()=>{if((window.outerWidth-window.innerWidth)>180||(window.outerHeight-window.innerHeight)>230)esc();},900);
  document.addEventListener('contextmenu',e=>e.preventDefault());
  document.addEventListener('keydown',e=>{if(e.key==='F12'||(e.ctrlKey&&e.shiftKey&&['I','J','C','U'].includes(e.key))||(e.ctrlKey&&['U','S'].includes(e.key)))e.preventDefault();});
  document.addEventListener('selectstart',e=>e.preventDefault());
  const _d={};Object.defineProperty(_d,'id',{get:()=>esc()});
  setInterval(()=>console.log(_d),2500);
})();

async function rApi(action,params={},tries=4){
  for(let i=0;i<tries;i++){
    try{
      const qs=new URLSearchParams({action,...params}).toString();
      const r=await fetch('rozgar-api.php?'+qs,{headers:{'X-Requested-With':'XMLHttpRequest'},cache:'no-cache'});
      if(!r.ok)throw new Error('HTTP '+r.status);
      const d=await r.json();
      if(!d.ok&&i<tries-1)throw new Error(d.error||'API err');
      return d;
    }catch(e){
      if(i<tries-1)await new Promise(r=>setTimeout(r,1800*(i+1)));
      else return{ok:false,data:[],error:e.message};
    }
  }
}

let _all=[];
const _sv=JSON.parse(localStorage.getItem('du_sv')||'[]');
function H(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}

document.addEventListener('DOMContentLoaded',fetchBatches);

async function fetchBatches(){
  document.getElementById('grid').innerHTML='<div class="ld-wrap"><div class="spin"></div><div class="ld-txt">LOADING BATCHES…</div></div>';
  document.getElementById('sh-cnt').textContent='…';
  const r=await rApi('batches');
  _all=r.data||[];
  if(!_all.length){
    const em=r.error||'Network error aayi';
    document.getElementById('grid').innerHTML=`<div class="empty-wrap"><i class="fas fa-satellite-dish"></i><h3>Batches nahi aaye</h3><p>${H(em)}</p><button class="retry" onclick="fetchBatches()"><i class="fas fa-rotate"></i> Dobara Try Karo</button></div>`;
    document.getElementById('sh-cnt').textContent='ERR';
    document.getElementById('hero-sub').textContent='Load nahi hua';
    upStats(0,0,0);
    return;
  }
  const free=_all.filter(b=>!b.price||b.price==0).length;
  upStats(_all.length,free,_all.length-free);
  document.getElementById('hero-sub').textContent=_all.length+' courses enrolled ✨';
  render(_all);
}

function upStats(t,f,p){
  document.getElementById('st-total').textContent=t||'0';
  document.getElementById('st-free').textContent=f||'0';
  document.getElementById('st-paid').textContent=p||'0';
}

function filter(q){
  const f=q?_all.filter(b=>(b.course_name||'').toLowerCase().includes(q.toLowerCase())):_all;
  render(f);
}

function render(list){
  document.getElementById('sh-cnt').textContent=list.length+' TOTAL';
  const el=document.getElementById('grid');
  if(!list.length){el.innerHTML='<div class="empty-wrap"><i class="fas fa-box-open"></i><h3>Koi batch nahi mila</h3><p>Search query change karo</p></div>';return;}
  el.innerHTML=list.map((b,i)=>{
    const fv=_sv.includes(String(b.id));
    const th=b.course_thumbnail||'https://placehold.co/640x280/0e1424/6366f1?text='+encodeURIComponent(b.course_name||'ZXKAI');
    const pr=b.price&&b.price>0?'₹'+b.price:'FREE';
    return`<div class="bcard" style="animation-delay:${i*.05}s">
      <div class="bthumb">
        <img src="${H(th)}" loading="${i<3?'eager':'lazy'}" onerror="this.src='https://placehold.co/640x280/0e1424/6366f1?text=ZXKAI'">
        <div class="boverlay"></div>
        <button class="bfav${fv?' on':''}" onclick="event.stopPropagation();toggleSave('${b.id}',this)"><i class="${fv?'fas':'far'} fa-heart"></i></button>
        <div class="binfo">
          <div class="btitle">${H(b.course_name)}</div>
          <div class="bchips">
            <span class="chip c-live"><i class="fas fa-circle" style="font-size:5px"></i> LIVE</span>
            <span class="chip ${b.price&&b.price>0?'c-price':'c-free'}">${H(pr)}</span>
          </div>
        </div>
      </div>
      <button class="bcta" onclick="go(${b.id})"><i class="fas fa-circle-play" style="font-size:18px"></i> Let's Study</button>
    </div>`;
  }).join('');
}

function go(id){
  const b=_all.find(x=>String(x.id)===String(id));
  window.location.href='subjects.php?bid='+id+'&bn='+encodeURIComponent(b?.course_name||'Batch');
}

function toggleSave(id,btn){
  const idx=_sv.indexOf(String(id));
  if(idx>-1){_sv.splice(idx,1);btn.classList.remove('on');btn.innerHTML='<i class="far fa-heart"></i>';toast('Saved list se remove kiya');}
  else{_sv.push(String(id));btn.classList.add('on');btn.innerHTML='<i class="fas fa-heart"></i>';toast('Saved ❤️');}
  localStorage.setItem('du_sv',JSON.stringify(_sv));
}

function navTo(u){window.location.href=u;}
let _tt;
function toast(m){const t=document.getElementById('toast');document.getElementById('tmsg').textContent=m;t.classList.add('on');clearTimeout(_tt);_tt=setTimeout(()=>t.classList.remove('on'),2800);}
</script>
</body>
</html>