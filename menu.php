<?php
// menu.php — Orijinal görünümlü menü + modal aksiyonlar (SQL yok)
ob_start();
session_start();
if (empty($_SESSION['user_email'])) { header('Location: index.php'); exit; }

function email_key(string $e): string { return strtolower(trim($e)); }
const DATA_DIR   = __DIR__ . '/data';
const USERS_FILE = DATA_DIR . '/users.json';
if (!is_dir(DATA_DIR)) { @mkdir(DATA_DIR, 0775, true); }
if (!file_exists(USERS_FILE)) { file_put_contents(USERS_FILE, json_encode(new stdClass(), JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)); }

$UKEY = email_key($_SESSION['user_email']);
function jload($file, $def){ if(!file_exists($file)) return $def; $raw=@file_get_contents($file); $d=json_decode($raw?:'null',true); return ($d===null?$def:$d); }

// Kullanıcı bilgileri
$users = jload(USERS_FILE, []);
$user  = $users[$UKEY] ?? ['name'=>'','surname'=>'','email'=>$UKEY,'phone'=>'','country'=>'TR','lang'=>'tr','created_at'=>''];
// Olay günlüğü (giriş kayıtları)
$LOGIN_LOG = DATA_DIR . "/login_log_{$UKEY}.json";
$log = jload($LOGIN_LOG, []); // dizi: ["2025-10-23 19:56:32", ...]
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Menü</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  :root{ --bgTop:#0b203f; --bg:#0c1e34; --card:#0f243a; --line:#19324a; --txt:#cfe0f3; --mut:#9eb3c8; --accent:#3ad0a0; --warn:#ffb74d; --bad:#ff7a7a; --wrap:980px; }
  *{box-sizing:border-box}
  body{margin:0;font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:var(--txt);
       background:linear-gradient(180deg,#0e2240 0%, #0a1b30 65%, #071425 100%); min-height:100vh;}
  header{position:sticky;top:0;z-index:5;background:linear-gradient(180deg,rgba(8,20,35,.95), rgba(8,20,35,.65) 70%, transparent); backdrop-filter:blur(8px)}
  .wrap{max-width:var(--wrap); margin:0 auto; padding:18px}
  h1{margin:6px 0 14px 0; font-size:32px}
  .list{background:rgba(15,36,58,.85); border:1px solid var(--line); border-radius:16px; overflow:hidden}
  .item{display:flex; align-items:center; gap:14px; padding:16px 14px; cursor:pointer}
  .item + .item{border-top:1px solid rgba(255,255,255,.06)}
  .item:hover{background:rgba(255,255,255,.03)}
  .icon{width:44px; height:44px; border-radius:12px; background:#132b47; display:grid; place-items:center; font-size:20px}
  .title{font-weight:600}
  .chev{margin-left:auto; opacity:.6}
  .section{color:#a9bed2; padding:14px 14px 8px}
  /* bottom bar */
  .bottom{position:sticky; bottom:0; background:#0d1f36; box-shadow:0 -10px 30px rgba(0,0,0,.45)}
  .tabs{max-width:var(--wrap); margin:0 auto; display:grid; grid-template-columns:repeat(4,1fr); gap:6px; padding:10px 14px}
  .tab{display:grid; place-items:center; gap:6px; font-size:12px; color:#b8c8db; padding:8px 0; border-radius:12px}
  .tab.active{background:#152a42}
  /* modal */
  .backdrop{position:fixed; inset:0; display:none; align-items:center; justify-content:center; background:rgba(0,0,0,.55); z-index:50; padding:16px}
  .backdrop.open{display:flex}
  .modal{max-width:560px; width:min(92vw,560px); background:#14273d; border:1px solid #21405d; border-radius:14px; padding:16px; box-shadow:0 30px 70px rgba(0,0,0,.6)}
  .modal h3{margin:0 0 10px}
  .mut{color:var(--mut)}
  table{width:100%; border-collapse:collapse; font-size:14px}
  th,td{padding:10px; border-top:1px solid #1f3347; text-align:left}
  .row{display:grid; grid-template-columns:220px 1fr; gap:10px; align-items:center; margin:6px 0}
  input{width:100%; background:#1a314e; border:none; border-radius:10px; padding:10px 12px; color:#dbe7f5}
  input[disabled]{opacity:.7}
  .btn{border:none; border-radius:10px; padding:10px 14px; background:#1e3a52; color:#dbe5f3; font-weight:600; cursor:pointer}
  .ok{background:#144a3a}
  .toast{position:fixed; right:14px; bottom:80px; background:#153a2c; border:1px solid #245e49; color:#b8f2cf; padding:10px 12px; border-radius:10px; box-shadow:0 10px 30px rgba(0,0,0,.45); display:none}
  .toast.show{display:block}
  @media(max-width:720px){ .row{grid-template-columns:1fr} }
</style>
</head>
<body>

<header>
  <div class="wrap">
    <h1>Menü</h1>
  </div>
</header>

<main class="wrap">
  <div class="list">
    <div class="item" onclick="openModal('info')">
      <div class="icon">🧑‍💼</div>
      <div>
        <div class="title">Kişisel veriler</div>
      </div>
      <div class="chev">›</div>
    </div>

    <div class="item" onclick="openModal('log')">
      <div class="icon">🕘</div>
      <div>
        <div class="title">Olay günlüğü</div>
      </div>
      <div class="chev">›</div>
    </div>

    <div class="section">Ayarlar</div>

    <div class="item" onclick="openModal('basic')">
      <div class="icon">⚙️</div>
      <div class="title">Temel ayarlar</div>
      <div class="chev">›</div>
    </div>

    <div class="item" onclick="openModal('trade')">
      <div class="icon">📱</div>
      <div class="title">Ticaret ayarları</div>
      <div class="chev">›</div>
    </div>

    <div class="item" onclick="openModal('graph')">
      <div class="icon">📊</div>
      <div class="title">Grafik Ayarları</div>
      <div class="chev">›</div>
    </div>

    <div class="item" onclick="secureToast()">
      <div class="icon">🔔</div>
      <div class="title">Bildirimler ve onaylar</div>
      <div class="chev">›</div>
    </div>

    <div class="item" onclick="secureToast(true)">
      <div class="icon">🛡️</div>
      <div class="title">Güvenlik</div>
      <div class="chev">›</div>
    </div>
  </div>
</main>

<!-- Bottom Nav (Favoriler YOK, Geçmiş → Yatırım/Çekim) -->
<nav class="bottom">
  <div class="tabs">
    <a class="tab" href="varlik.php">📈<div>Aktif Varlıklar</div></a>
    <a class="tab" href="islemler.php">💼<div>İşlemler</div></a>
    <a class="tab" href="deposit.php">💳<div>Yatırım/Çekim</div></a>
    <a class="tab active" href="menu.php">≡<div>Menü</div></a>
  </div>
</nav>

<!-- Modals -->
<div class="backdrop" id="mdl-info">
  <div class="modal">
    <h3>Kişisel veriler</h3>
    <div class="row"><div class="mut">Ad Soyad</div><div><b><?= htmlspecialchars(($user['name']??'').' '.($user['surname']??'')) ?></b></div></div>
    <div class="row"><div class="mut">E-posta</div><div><?= htmlspecialchars($user['email'] ?? $UKEY) ?></div></div>
    <div class="row"><div class="mut">Telefon</div><div><?= htmlspecialchars($user['phone'] ?? '') ?></div></div>
    <div class="row"><div class="mut">Ülke / Dil</div><div><?= htmlspecialchars(($user['country'] ?? 'TR').' / '.($user['lang'] ?? 'tr')) ?></div></div>
    <div class="row"><div class="mut">Kayıt Tarihi</div><div><?= htmlspecialchars($user['created_at'] ?? '') ?></div></div>
    <div style="margin-top:12px;text-align:right"><button class="btn" onclick="closeAll()">Kapat</button></div>
  </div>
</div>

<div class="backdrop" id="mdl-log">
  <div class="modal">
    <h3>Olay günlüğü (Girişler)</h3>
    <?php if($log): ?>
      <table>
        <thead><tr><th>Tarih / Saat</th></tr></thead>
        <tbody>
        <?php foreach(array_reverse($log) as $t): ?>
          <tr><td><?= htmlspecialchars($t) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div class="mut">Kayıt bulunamadı.</div>
    <?php endif; ?>
    <div style="margin-top:12px;text-align:right"><button class="btn" onclick="closeAll()">Kapat</button></div>
  </div>
</div>

<div class="backdrop" id="mdl-basic">
  <div class="modal">
    <h3>Temel ayarlar</h3>
    <div class="mut" style="margin-bottom:8px">Bu ayarlar bilgilendirme amaçlıdır ve değiştirilemez.</div>
    <div class="row"><div class="mut">Tema</div><div><input value="Koyu" disabled></div></div>
    <div class="row"><div class="mut">Dil</div><div><input value="Türkçe" disabled></div></div>
    <div class="row"><div class="mut">Bölge</div><div><input value="TR" disabled></div></div>
    <div style="margin-top:12px;text-align:right"><button class="btn" onclick="closeAll()">Kapat</button></div>
  </div>
</div>

<div class="backdrop" id="mdl-trade">
  <div class="modal">
    <h3>Ticaret ayarları</h3>
    <div class="mut" style="margin-bottom:8px">Bu ayarlar kilitlidir.</div>
    <div class="row"><div class="mut">Komisyon</div><div><input value="0.1%" disabled></div></div>
    <div class="row"><div class="mut">Min Lot</div><div><input value="1" disabled></div></div>
    <div class="row"><div class="mut">Maks Lot</div><div><input value="100000" disabled></div></div>
    <div style="margin-top:12px;text-align:right"><button class="btn" onclick="closeAll()">Kapat</button></div>
  </div>
</div>

<div class="backdrop" id="mdl-graph">
  <div class="modal">
    <h3>Grafik Ayarları</h3>
    <div class="mut">Zaman aralığı, tema ve kılavuz çizgileri otomatik optimize edilir. Değiştirilemez.</div>
    <div style="margin-top:12px;text-align:right"><button class="btn" onclick="closeAll()">Kapat</button></div>
  </div>
</div>

<div class="toast" id="toast">Güvendesiniz ✅</div>

<script>
  const M = {
    info:   document.getElementById('mdl-info'),
    log:    document.getElementById('mdl-log'),
    basic:  document.getElementById('mdl-basic'),
    trade:  document.getElementById('mdl-trade'),
    graph:  document.getElementById('mdl-graph'),
  };
  function openModal(key){ closeAll(); (M[key]||M.info).classList.add('open'); }
  function closeAll(){ for (const k in M) M[k].classList.remove('open'); }
  // backdrop dışına tıkla kapat
  Object.values(M).forEach(b=>b.addEventListener('click',e=>{ if(e.target===b) closeAll(); }));

  // Güvenlik + Bildirimler: toast
  let tmr=null;
  function secureToast(isSecurity){
    const el = document.getElementById('toast');
    el.textContent = isSecurity ? 'Güvendesiniz ✅' : 'Bildirim ayarları güvende ✅';
    el.classList.add('show');
    clearTimeout(tmr);
    tmr = setTimeout(()=>el.classList.remove('show'), 1800);
  }
</script>

</body>
</html>
