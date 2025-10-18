<?php
session_start();

$user = $_SESSION['user'] ?? null;
if (!$user) { header('Location: admin.php'); exit; }

$pdo = new PDO('sqlite:' . __DIR__ . '/data/users.db');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* tablo + kolonlar */
$pdo->exec("
  CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'user',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
  )
");
$cols=[]; foreach($pdo->query("PRAGMA table_info(users)") as $c){ $cols[]=$c['name']; }
foreach (['install_locked INTEGER DEFAULT 0','lock_reason TEXT','active_step INTEGER DEFAULT 1','created TEXT'] as $ddl) {
  $name = explode(' ', $ddl)[0];
  if (!in_array($name,$cols)) $pdo->exec("ALTER TABLE users ADD COLUMN $ddl");
}
$pdo->exec("UPDATE users SET created = created_at WHERE created IS NULL");

/* sadece DB'yi kaynak kabul et */
unset($_SESSION['install_locked'], $_SESSION['lock_reason'], $_SESSION['active_step'], $_SESSION['target_phone']);

/* kullanıcı durumu */
$st=$pdo->prepare("SELECT id, created, install_locked, lock_reason, active_step FROM users WHERE username=:u");
$st->execute([':u'=>$user]);
$row=$st->fetch(PDO::FETCH_ASSOC);
if(!$row){ header('Location: logout.php'); exit; }

$uid           = (int)$row['id'];
$createdDate   = $row['created'];
$installLocked = (int)$row['install_locked']===1;
$lockReason    = $row['lock_reason'] ?: null;
$activeStep    = (int)($row['active_step'] ?? 1);

/* normalize */
if ($installLocked) {
  if ($activeStep !== 2) { $activeStep = 2; $pdo->prepare("UPDATE users SET active_step=2 WHERE id=:id")->execute([':id'=>$uid]); }
} else {
  if ($activeStep < 1 || $activeStep > 3) { $activeStep = 1; $pdo->prepare("UPDATE users SET active_step=1 WHERE id=:id")->execute([':id'=>$uid]); }
}

/* geçişler */
if (!$installLocked && isset($_POST['go_step2'])) {
  $activeStep = 2; $pdo->prepare("UPDATE users SET active_step=2 WHERE id=:id")->execute([':id'=>$uid]);
}

if (!$installLocked && (($_POST['action'] ?? null) === 'phone')) {
  $phone = trim($_POST['phone'] ?? '');
  if ($phone !== '' && preg_match('/^\s*0/', $phone)) {
    $installLocked = true; $lockReason='5001'; $activeStep=2;
    $pdo->prepare("UPDATE users SET install_locked=1, lock_reason='5001', active_step=2 WHERE id=:id")->execute([':id'=>$uid]);
  } else {
    $activeStep = 3; $pdo->prepare("UPDATE users SET active_step=3 WHERE id=:id")->execute([':id'=>$uid]);
  }
}

/* %92'de çift koruma kilidi (Adım 3) */
if (!$installLocked && (($_POST['action'] ?? null) === 'lock_double')) {
  $installLocked = true; $lockReason='double'; $activeStep=2;
  $pdo->prepare("UPDATE users SET install_locked=1, lock_reason='double', active_step=2 WHERE id=:id")->execute([':id'=>$uid]);
  http_response_code(204); exit;
}

/* banner helper */
function lockBanner($reason){
  if ($reason==='double') return ['title'=>'WARNING','text'=>'SIZILAN HEDEF CİHAZDA ÇİFT KORUMA TESPİT EDİLDİ! Sistem güvenlik gereği kilitlendi.'];
  if ($reason==='5001')   return ['title'=>'5001','text'=>'The phone number registered in the system is not valid. Installation is blocked.'];
  return null;
}
?>
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Gösterge Paneli — Griffon</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    /* ---- HACKER THEME ---- */
    body.hacker {
      --bg: #0b0f14;
      --panel: #0f1621;
      --panel-2: #0c131b;
      --text: #c7e1ff;
      --muted: #8aa0b6;
      --neon: #00ff95;
      --neon-2: #00e1ff;
      --danger: #ff3b3b;
      background:
        radial-gradient(1200px 600px at 10% -10%, rgba(0,255,149,.07), transparent 60%),
        radial-gradient(900px 500px at 110% 10%, rgba(0,225,255,.05), transparent 50%),
        var(--bg);
      color: var(--text);
    }
    .hacker header { background: linear-gradient(180deg, #0f1621 0%, #0b1119 100%) !important; }
    .card-dark { background: linear-gradient(180deg, var(--panel), var(--panel-2)); border: 1px solid #172236; box-shadow: 0 10px 30px rgba(0,0,0,.35); }
    .glow { position: relative; border: 1px solid rgba(0,255,149,.25); }
    .glow:before { content:""; position:absolute; inset:-1px; background: linear-gradient(90deg, rgba(0,255,149,.15), rgba(0,225,255,.08), transparent 40% 60%, rgba(0,225,255,.08), rgba(0,255,149,.15)); filter: blur(12px); opacity:.7; pointer-events:none; }
    .tab { font-size:.875rem; color:#7aa0b8 !important; }
    .tab-active { color:var(--neon) !important; font-weight:600; text-shadow: 0 0 8px rgba(0,255,149,.6); }
    .btn-neon { background: linear-gradient(90deg, rgba(0,255,149,.12), rgba(0,225,255,.12)); border:1px solid rgba(0,255,149,.35); color:var(--text); transition:.25s ease; box-shadow: inset 0 0 12px rgba(0,255,149,.15); }
    .btn-neon:hover { transform: translateY(-1px); box-shadow: 0 0 16px rgba(0,255,149,.35); }
    .bar-wrap { background:#0b1320; border:1px solid #19314a; }
    .bar { background: linear-gradient(90deg, var(--neon), var(--neon-2)); box-shadow: 0 0 16px rgba(0,255,149,.45), 0 0 24px rgba(0,225,255,.25); }
    #term { background:#071018 !important; color:#b8ffd9 !important; border:1px solid #143046; }
    #tasks { background:#0a1220 !important; border:1px solid #172c40; color:#cfe8ff; }
    .code-rain { position:absolute; inset:0; overflow:hidden; pointer-events:none; opacity:.12; mix-blend-mode:screen; background: repeating-linear-gradient(180deg,#000,#000 2px,transparent 2px,transparent 4px); }
    .code-stream { position:absolute; top:-120%; width:1.5px; height:220%; background: linear-gradient(to bottom, rgba(0,255,149,0), rgba(0,255,149,.85)); opacity:.8; filter:blur(.2px); animation:fall var(--t) linear infinite; left:var(--x) }
    @keyframes fall{ to { transform: translateY(120%)} }
    .scanlines{ position:fixed; inset:0; pointer-events:none; z-index:60; background: linear-gradient(rgba(0,0,0,0) 50%, rgba(0,0,0,.15) 51%), linear-gradient(90deg, rgba(0,0,0,.06), rgba(0,0,0,0), rgba(0,0,0,.06)); background-size:100% 3px, 3px 100%; animation: flicker 2.4s infinite steps(2,start); }
    @keyframes flicker { 0%{opacity:.25} 50%{opacity:.35} 100%{opacity:.25} }
    .glitch { position:relative; display:inline-block; text-shadow:0 0 8px rgba(0,255,149,.6); }
    .glitch:before, .glitch:after { content: attr(data-text); position:absolute; left:0; top:0; }
    .glitch:before { color:#00e1ff; transform: translate(1px,0); mix-blend-mode:screen; }
    .glitch:after  { color:#00ff95; transform: translate(-1px,0); mix-blend-mode:screen; }

    /* ---- 5) Alert Mode ---- */
    body.alert-mode { --bg: #12090a; }
    body.alert-mode .card-dark { border-color:#3b0b10; box-shadow: 0 0 20px rgba(255,59,59,.15); }
    body.alert-mode .tab-active { color:#ff7b7b !important; text-shadow:0 0 10px rgba(255,59,59,.7); }
    .alert-banner { animation: alertPulse 1.2s infinite; }
    @keyframes alertPulse { 0%{box-shadow:0 0 0 rgba(255,59,59,.0)} 50%{box-shadow:0 0 24px rgba(255,59,59,.35)} 100%{box-shadow:0 0 0 rgba(255,59,59,0)} }
    .scanline-red { position:fixed; inset:0; pointer-events:none; z-index:70; background: repeating-linear-gradient(180deg, rgba(255,0,0,.06) 0 3px, transparent 3px 6px); mix-blend-mode:screen; opacity:.4; display:none; }
    body.alert-mode .scanline-red{ display:block; }

    /* ---- 1) Boot Sequence Overlay ---- */
    #boot {
      position:fixed; inset:0; z-index:100; background:#05080c;
      color:#b8ffd9; display:flex; align-items:center; justify-content:center;
      font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    }
    #boot .box { width:min(900px,92vw); height:min(420px,70vh); border:1px solid #143046; background:#071018; box-shadow:0 0 40px rgba(0,0,0,.6); padding:16px; overflow:hidden; position:relative; }
    #boot pre { margin:0; font-size:13px; line-height:1.35; white-space:pre-wrap; max-height:100%; overflow:auto; }
    #boot .cursor { display:inline-block; width:10px; background:#22c55e; animation:blink .9s steps(1,end) infinite; vertical-align:baseline; }
    @keyframes blink { 50%{opacity:0} }

    /* ---- 7) Network Trace Canvas ---- */
    .trace-wrap { position:absolute; inset:0; pointer-events:none; z-index:10; opacity:.0; transition:opacity .6s ease; }
    .trace-wrap.on { opacity:.35; }
    #traceCanvas { width:100%; height:100%; display:block; }
    .ai-chip { font-size:10px; letter-spacing:.12em; border:1px solid rgba(0,255,149,.35); padding:2px 6px; border-radius:9999px; background:rgba(0,255,149,.08); color:#bfffe3; }
    .scan-hover:hover { position:relative; }
    .scan-hover:hover:after{
      content:""; position:absolute; left:0; right:0; top:0; height:2px; background:linear-gradient(90deg, transparent, rgba(0,255,149,.8), transparent);
      animation: sweep 1.6s linear infinite;
    }
    @keyframes sweep { 0%{transform:translateY(-2px)} 100%{transform:translateY(200px)} }
  </style>
</head>

<?php
// body class: alert-mode kilitliyken aktif
$bodyClass = "hacker min-h-screen";
if ($installLocked) $bodyClass .= " alert-mode";
?>
<body class="<?= $bodyClass ?>">
  <!-- 1) Boot Sequence Overlay -->
  <div id="boot" aria-hidden="true">
    <div class="box">
      <pre id="bootlog"></pre>
    </div>
  </div>

  <header class="shadow">
    <div class="container mx-auto px-4 py-3 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <img src="assets/logo.png" class="h-10 w-auto" alt="Logo" onerror="this.style.display='none'">
        <div>
          <div class="text-gray-200 font-semibold glitch" data-text="Griffon Yönetim Paneli">Griffon Yönetim Paneli</div>
          <div class="text-xs text-gray-400 flex items-center gap-2">
            <span>Security AI:</span>
            <span class="ai-chip"><?= $installLocked ? 'ALERT MODE' : 'ONLINE' ?></span>
          </div>
        </div>
      </div>
      <div class="flex items-center gap-3">
        <span class="text-sm text-gray-300">Merhaba, <strong><?= htmlspecialchars($user,ENT_QUOTES,'UTF-8') ?></strong></span>
        <a class="px-3 py-2 btn-neon rounded" href="logout.php">Çıkış</a>
      </div>
    </div>
  </header>

  <main class="container mx-auto px-4 py-6">
    <?php if ($installLocked): $b=lockBanner($lockReason); ?>
      <div class="mb-6 p-4 rounded border-l-4 border-red-500 bg-red-50 text-red-700 shadow alert-banner">
        <strong><?= htmlspecialchars($b['title']) ?>:</strong> <?= htmlspecialchars($b['text']) ?>
      </div>
    <?php endif; ?>

    <section class="mb-6">
      <div class="card-dark glow rounded-lg p-6 scan-hover">
        <h2 class="text-lg font-semibold mb-4 glitch" data-text="Üyelik Bilgileri">Üyelik Bilgileri</h2>
        <div class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
          <div class="text-gray-400">Durum</div>
          <div class="font-medium">
            <?php
              if ($installLocked) echo 'Kurulum Başarısız';
              else if ($activeStep>=3) echo 'Kurulum Yapılıyor';
              else echo 'Kurulum Bekleniyor';
            ?>
          </div>
          <div class="text-gray-400">Paket</div><div>Silver</div>
          <div class="text-gray-400">Lisans Kodunuz</div><div class="font-mono">AB38EC13E4</div>
          <div class="text-gray-400">Kalan Gün</div><div class="font-medium">90</div>
          <div class="text-gray-400">Üyelik Tarihi</div>
          <div><?= $createdDate ? date('d.m.Y', strtotime($createdDate)) : 'Bilinmiyor' ?></div>
        </div>
      </div>
    </section>

    <section class="mb-8 grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="lg:col-span-2 card-dark glow rounded-lg p-6 relative overflow-hidden">
        <!-- 7) Network Trace Canvas (Adım 3 sırasında açılır) -->
        <div id="trace" class="trace-wrap"><canvas id="traceCanvas"></canvas></div>

        <div class="mb-4 flex gap-4">
          <span class="<?= $activeStep===1 ? 'tab-active':'tab' ?>">Adım 1</span>
          <span class="<?= $activeStep===2 ? 'tab-active':'tab' ?>">Adım 2</span>
          <span class="<?= $activeStep===3 ? 'tab-active':'tab' ?>">Adım 3</span>
          <span class="tab">Adım 4</span><span class="tab">Adım 5</span>
        </div>

        <?php if ($activeStep===1 && !$installLocked): ?>
          <h3 class="text-lg font-semibold mb-2">Özellik Seçimi</h3>
          <p class="text-sm text-gray-400 mb-4">Kullanmak istediğiniz özellikleri açın.</p>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <?php $features=['Çağrı Dinleme','SMS','Whatsapp','Konum','Fotoğraflar','Videolar','Ortam Dinleme','Sosyal Medya','Tarayıcı Geçmişi','Rehber']; $i=0; foreach($features as $f): $i++; ?>
              <button type="button" class="feat-btn w-full flex items-center justify-between border-2 border-gray-700 rounded-xl p-3 bg-transparent hover:bg-white/5 transition" data-on="0">
                <div class="flex items-center gap-3">
                  <div class="w-10 text-gray-400 font-semibold"><?= $i ?></div>
                  <div><?= htmlspecialchars($f) ?></div>
                </div>
                <span class="feat-state inline-flex items-center justify-center text-xs px-2 py-1 rounded-full bg-gray-700 text-gray-200">Kapalı</span>
              </button>
            <?php endforeach; ?>
          </div>
          <form method="post" class="mt-6 flex items-center gap-3 justify-end">
            <input type="hidden" name="go_step2" value="1">
            <div id="step-status" class="mr-auto text-sm text-gray-400">Durum: Bekleniyor</div>
            <button class="px-4 py-2 btn-neon rounded">Devam Et</button>
          </form>

        <?php elseif ($activeStep===2): ?>
          <h3 class="text-lg font-semibold mb-2">Hedef Telefon Numarası</h3>
          <p class="text-sm text-gray-400 mb-4">Hedef Numarayı <strong>Girin</strong></p>
          <div class="p-4 border border-gray-700 rounded-lg bg-transparent max-w-md">
            <form method="post" class="space-y-3" <?= $installLocked ? 'onsubmit="return false;"':'' ?>>
              <input type="hidden" name="action" value="phone">
              <label class="block text-sm text-gray-300 font-medium">Telefon Numarası</label>
              <input name="phone" type="tel" inputmode="numeric" placeholder="XXXXXXXXXX"
                class="mt-1 block w-full rounded-md border-gray-700 bg-black/30 text-gray-100 px-3 py-2 <?= $installLocked?'opacity-60 pointer-events-none':'' ?>" <?= $installLocked?'disabled':'' ?> required>
              <button class="px-4 py-2 btn-neon rounded <?= $installLocked?'opacity-60 pointer-events-none':'' ?>" <?= $installLocked?'disabled':'' ?>>Devam</button>
            </form>

            <?php if ($installLocked): $b=lockBanner($lockReason); ?>
              <div class="mt-4 p-3 rounded border-l-4 border-red-500 bg-red-50 text-red-700">
                <strong><?= htmlspecialchars($b['title']) ?>:</strong> <?= htmlspecialchars($b['text']) ?>
                <div class="mt-2 text-sm text-gray-700">WhatsApp üzerinden destek alın.</div>
                <a href="https://wa.me/905011891645" target="_blank" rel="noopener" class="inline-block mt-3 px-3 py-2 bg-green-600 text-white rounded">WhatsApp Destek</a>
              </div>
            <?php endif; ?>
          </div>
          <div class="mt-4 text-sm text-gray-400">Durum: <?= $installLocked ? 'Kurulum Bozuldu' : 'Adım 2' ?></div>

        <?php elseif ($activeStep===3): ?>
          <div class="relative">
            <h3 class="text-lg font-semibold mb-2">Kurulum Hazırlanıyor</h3>
            <p class="text-sm text-gray-400 mb-4">Bağlantı kuruldu. Dosyalar hazırlanıyor, günlükler akıyor…</p>
            <div id="warn" class="hidden mb-4 p-4 rounded border-l-4 border-red-500 bg-red-50 text-red-700">
              <strong>WARNING:</strong> SIZILAN HEDEF CİHAZDA ÇİFT KORUMA TESPİT EDİLDİ! Sistem güvenlik gereği kilitlenecek.
            </div>
            <div class="w-full h-3 bar-wrap rounded-full overflow-hidden">
              <div id="bar" class="h-3 bar rounded-full" style="width:0%"></div>
            </div>
            <div class="mt-1 text-right text-xs text-gray-400"><span id="pct">0%</span></div>
            <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
              <div id="term" class="p-3 rounded-lg mono text-xs h-48 overflow-auto"></div>
              <div id="tasks" class="p-3 rounded-lg text-sm h-48 overflow-auto"><ul id="taskList" class="list-disc pl-5 space-y-1"></ul></div>
            </div>
            <div class="code-rain" aria-hidden="true" id="rain"></div>
            <div class="mt-4 text-sm text-gray-500"></div>
          </div>
        <?php endif; ?>
      </div>

      <aside class="card-dark glow rounded-lg p-6">
        <h4 class="font-semibold mb-3">Telefon Bilgileri</h4>
        <dl class="text-sm space-y-2">
          <div class="flex justify-between text-gray-300"><dt class="text-gray-400">Model</dt><dd>Bilinmiyor</dd></div>
          <div class="flex justify-between text-gray-300"><dt class="text-gray-400">OS</dt><dd>Bilinmiyor</dd></div>
          <div class="flex justify-between"><dt class="text-gray-400">Durum</dt>
            <dd class="font-semibold <?= $installLocked ? 'text-red-400' : ($activeStep>=3 ? 'text-emerald-400' : 'text-yellow-300') ?>">
              <?= $installLocked ? 'Kurulum Başarısız' : ($activeStep>=3 ? 'Kurulum Yapılıyor' : 'Kurulum Bekleniyor') ?>
            </dd>
          </div>
        </dl>
      </aside>
    </section>
  </main>

  <!-- Scanline overlay -->
  <div class="scanlines" aria-hidden="true"></div>
  <div class="scanline-red" aria-hidden="true"></div>

  <?php if (!$installLocked && $activeStep===1): ?>
  <script>
    document.querySelectorAll('.feat-btn').forEach(btn=>{
      btn.addEventListener('click',()=>{
        const on=btn.getAttribute('data-on')==='1';
        if(on){
          btn.setAttribute('data-on','0');btn.classList.remove('bg-white/5','border-emerald-400');btn.classList.add('border-gray-700');
          const s=btn.querySelector('.feat-state');s.textContent='Kapalı';s.className='feat-state inline-flex items-center justify-center text-xs px-2 py-1 rounded-full bg-gray-700 text-gray-200';
        }else{
          btn.setAttribute('data-on','1');btn.classList.remove('border-gray-700');btn.classList.add('bg-white/5','border-emerald-400');
          const s=btn.querySelector('.feat-state');s.textContent='Açık';s.className='feat-state inline-flex items-center justify-center text-xs px-2 py-1 rounded-full bg-emerald-500/90 text-white';
        }
      });
    });
  </script>
  <?php endif; ?>

  <?php if ($activeStep===3 && !$installLocked): ?>
  <script>
    /* ---- 7) Network Trace: canvas animasyonu ---- */
    const traceWrap = document.getElementById('trace');
    const cv = document.getElementById('traceCanvas');
    const ctx = cv.getContext('2d');
    function resizeCanvas(){ cv.width = traceWrap.clientWidth; cv.height = traceWrap.clientHeight; }
    resizeCanvas(); addEventListener('resize', resizeCanvas);

    // Rastgele nodlar + aralarına neon hatlar
    const nodes = Array.from({length: 16}, ()=>({
      x: Math.random()*cv.width*0.9+cv.width*0.05,
      y: Math.random()*cv.height*0.8+cv.height*0.1,
      r: Math.random()*2+1
    }));
    const links = Array.from({length: 28}, ()=>({
      a: nodes[Math.floor(Math.random()*nodes.length)],
      b: nodes[Math.floor(Math.random()*nodes.length)],
      p: Math.random()
    }));

    function drawTrace(t){
      ctx.clearRect(0,0,cv.width,cv.height);
      // glow layer
      ctx.globalCompositeOperation='lighter';
      links.forEach(l=>{
        const pulse = (Math.sin(t*0.002 + l.p*6.28)*0.5+0.5);
        ctx.lineWidth = 1 + pulse*1.5;
        const g = ctx.createLinearGradient(l.a.x,l.a.y,l.b.x,l.b.y);
        g.addColorStop(0, 'rgba(0,255,149,0.0)');
        g.addColorStop(0.5, 'rgba(0,255,149,'+(0.15+0.35*pulse)+')');
        g.addColorStop(1, 'rgba(0,225,255,0.0)');
        ctx.strokeStyle = g;
        ctx.beginPath(); ctx.moveTo(l.a.x,l.a.y); ctx.lineTo(l.b.x,l.b.y); ctx.stroke();
      });
      nodes.forEach(n=>{
        ctx.fillStyle='rgba(0,255,149,.6)';
        ctx.beginPath(); ctx.arc(n.x,n.y,n.r,0,Math.PI*2); ctx.fill();
        ctx.strokeStyle='rgba(0,225,255,.35)'; ctx.lineWidth=0.6;
        ctx.beginPath(); ctx.arc(n.x,n.y,n.r+1.2,0,Math.PI*2); ctx.stroke();
      });
    }
    let animId; function loop(ts){ drawTrace(ts); animId=requestAnimationFrame(loop); }

    // 3. adım ilerlerken: %40-%90 arası görünür
    const bar=document.getElementById('bar'); const pct=document.getElementById('pct'); const warn=document.getElementById('warn');
    let p=0, halted=false;
    function haltAndLock(){
      if(halted) return; halted=true;
      warn.classList.remove('hidden');
      // 5) Alert Mode’a geç
      document.body.classList.add('alert-mode');
      fetch(location.href,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=lock_double'});
    }
    (function tick(){
      if(halted) return;
      const step=Math.max(1,Math.round(Math.random()*4));
      p=Math.min(92,p+step);
      bar.style.width=p+'%'; pct.textContent=p+'%';

      if(p>=40 && p<=90){ traceWrap.classList.add('on'); if(!animId) animId=requestAnimationFrame(loop); }
      else { traceWrap.classList.remove('on'); if(animId){ cancelAnimationFrame(animId); animId=null; } }

      if(p>=92){ traceWrap.classList.remove('on'); if(animId){ cancelAnimationFrame(animId); animId=null; } haltAndLock(); return; }
      setTimeout(tick, 300+Math.random()*500);
    })();

    // Terminal log ve görev listesi
    const term=document.getElementById('term');
    const logs=['[core] bağlantı doğrulandı…','[extract] dosyalar çıkarılıyor…','[scan] medya dizinleri indeksleniyor…','[parser] SMS veritabanı parse ediliyor…','[hooks] bildirim yakalama modülleri yükleniyor…','[bridge] whatsapp oturumu hazırlanıyor…','[geo] konum servisleri test ediliyor…','[perm] erişim izinleri doğrulanıyor…','[cloud] arşiv kuyruğu başlatıldı…','[collect] mesaj akışı izleniyor…','[stream] canlı akış başlatılıyor…','[finalize] önbellek ısındırılıyor…'];
    let i=0; (function pushLog(){ if(halted) return; const ts=new Date().toISOString().split('T')[1].substring(0,8); const color=Math.random()>0.2?'#22c55e':'#ef4444'; const line=`<span style="color:${color}">${ts} ${logs[i%logs.length]}</span><br>`; term.insertAdjacentHTML('beforeend',line); term.scrollTop=term.scrollHeight; i++; setTimeout(pushLog,250+Math.random()*350); })();

    const tasks=['Dosyalar çıkarılıyor','Mesaj arşivi taranıyor','Multimedya indeksleniyor','WhatsApp oturumu kuruluyor','GPS senkronizasyonu','Uygulama izinleri test ediliyor','Tarayıcı geçmişi hazırlanıyor','Rehber yedekleniyor','Bildirim köprüsü başlatılıyor'];
    const taskList=document.getElementById('taskList'); const tasksBox=document.getElementById('tasks'); let j=0;
    (function nextTask(){ if(halted) return; const li=document.createElement('li'); li.textContent=tasks[j%tasks.length]+'…'; taskList.appendChild(li); tasksBox.scrollTop=tasksBox.scrollHeight; j++; if(j<200) setTimeout(nextTask,400+Math.random()*500); })();
    
    // Matrix yağmuru
    const rain=document.getElementById('rain'); for(let k=0;k<30;k++){ const s=document.createElement('div'); s.className='code-stream'; s.style.setProperty('--x',(Math.random()*100)+'%'); s.style.setProperty('--t',(2+Math.random()*4)+'s'); rain.appendChild(s); }
  </script>
  <?php endif; ?>

 <?php
// ... (sayfanın en altına, </body>’den hemen önce ekle)
if (empty($_SESSION['boot_shown'])):
  $_SESSION['boot_shown'] = true;
?>
<script>
  /* ---- 1) Boot Sequence (only once per session) ---- */
  (function bootSeq(){
    const boot = document.getElementById('boot');
    const out  = document.getElementById('bootlog');
    const lines = [
      "Griffon OS v3.4 booting...",
      "Loading core modules........................... OK",
      "Decrypting user data........................... OK",
      "Initializing capture bridges................... OK",
      "Checking kernel hooks.......................... OK",
      "Access level: ADMIN",
      "Mounting secure volumes........................ OK",
      "Starting services: geo, sms, whatsapp.......... OK",
      ">>> SYSTEM ONLINE"
    ];
    let idx=0;
    function typeLine(){
      if(idx<lines.length){
        out.innerHTML += lines[idx] + "<br>";
        out.scrollTop = out.scrollHeight;
        idx++;
        setTimeout(typeLine, 100);
      } else {
        // otomatik fade-out
        setTimeout(()=>{
          boot.style.opacity='0';
          setTimeout(()=>boot.remove(), 500);
        }, 800);
      }
    }
    setTimeout(typeLine, 200);
  })();
</script>
<?php else: ?>
<script>document.getElementById('boot').remove();</script>
<?php endif; ?>
</body>
</html>
