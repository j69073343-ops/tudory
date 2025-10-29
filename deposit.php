<?php
// yatirim.php — Yatırım & Çekim sayfası (dosya tabanlı, SQL yok) + Yatırım IBAN bilgilendirme modali (merkezlenmiş)
// === Tam sürüm (Favoriler kaldırıldı, alt menü 4 sekme) ===
ob_start();
session_start();
if (empty($_SESSION['user_email'])) { header('Location: index.php'); exit; }

function email_key(string $e): string { return strtolower(trim($e)); }
function tl($n){ return number_format((float)$n, 2, ',', '.'); }
function now(){ return date('Y-m-d H:i:s'); }

const DATA_DIR = __DIR__ . '/data';
if (!is_dir(DATA_DIR)) { @mkdir(DATA_DIR, 0775, true); }

// 🔹 Yatırım için gösterilecek IBAN ve Hesap Sahibi (dilediğiniz gibi değiştirin)
const DEPOSIT_IBAN   = 'TR12 3456 7890 1234 5678 9012 34';
const DEPOSIT_HOLDER = 'AD SOYAD';

$U = email_key($_SESSION['user_email']);
$WALLET_FILE   = DATA_DIR . "/wallet_{$U}.json";
$PORTF_FILE    = DATA_DIR . "/portfolio_{$U}.json";
$PANEL_FILE    = DATA_DIR . "/panel_{$U}.json";
$CASHREQ_FILE  = DATA_DIR . "/cash_requests_{$U}.json"; // yatırımlar ve çekim talepleri

function jload(string $file, $default){
  if(!file_exists($file)) return $default;
  $raw=@file_get_contents($file);
  $d=json_decode($raw?:'null',true);
  return ($d===null?$default:$d);
}
function jsave(string $file,$data){
  return (bool)@file_put_contents($file,json_encode($data,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),LOCK_EX);
}

// Varsayılan kayıtlar
$wallet = jload($WALLET_FILE, ['balance'=>100000.00]);
$pf     = jload($PORTF_FILE,  ['TPAO'=>['qty'=>0,'avg_price'=>0]]);
$panel  = jload($PANEL_FILE,  ['buy_price'=>35.50,'sell_price'=>35.20,'fee_percent'=>0.10]);
$cashq  = jload($CASHREQ_FILE, []); // [{ts,type,amount,status,note}]

$bal    = (float)($wallet['balance'] ?? 0);
$qty    = (int)($pf['TPAO']['qty'] ?? 0);
$avg    = (float)($pf['TPAO']['avg_price'] ?? 0);
$sellP  = (float)($panel['sell_price'] ?? 0);
$portfolio_value = $qty * $sellP;

// Bildirimler
$flash = null;
$openPendingModal = false;

// İşlemler
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $act = $_POST['action'] ?? '';

  // PARA YATIR (DEPOSIT) — (Bilgi modali sonrasında submit olur)
  if ($act==='deposit') {
    $amount = (float)($_POST['amount'] ?? 0);
    if ($amount <= 0) {
      $flash = ['err', 'Geçerli bir tutar girin.'];
    } else {
      $wallet['balance'] = (float)$wallet['balance'] + $amount;
      jsave($WALLET_FILE, $wallet);

      $cashq[] = [
        'ts'     => now(),
        'type'   => 'DEPOSIT',
        'amount' => $amount,
        'status' => 'COMPLETED',
        'note'   => 'Yatırım işlemi (havale/EFT) bildirimi.'
      ];
      jsave($CASHREQ_FILE, $cashq);
      $bal = (float)$wallet['balance'];
      $flash = ['ok', tl($amount).' ₺ yatırıldı.'];
    }
  }

  // ÇEKİM TALEBİ (WITHDRAW REQUEST) — PENDING
  if ($act==='withdraw') {
    $amount = (float)($_POST['amount'] ?? 0);
    // Çekilebilir tutar = Nakit bakiye
    $withdrawable = (float)$wallet['balance'];

    if ($amount <= 0) {
      $flash = ['err', 'Geçerli bir tutar girin.'];
    } elseif ($amount > $withdrawable) {
      $flash = ['err', 'Çekilebilir tutarı aşıyor. (Maks: '.tl($withdrawable).' ₺)'];
    } else {
      // Bakiye şimdilik düşmez; talep PENDING kalır (değerlendirmede)
      $cashq[] = [
        'ts'     => now(),
        'type'   => 'WITHDRAW',
        'amount' => $amount,
        'status' => 'PENDING',
        'note'   => 'Çekim talebi alındı, değerlendirmede.'
      ];
      jsave($CASHREQ_FILE, $cashq);
      $openPendingModal = true;
      $flash = ['ok', 'Çekim talebiniz alındı, değerlendirmede.'];
    }
  }
}

// Görünüm verileri tekrar oku
$bal    = (float)($wallet['balance'] ?? 0);
$qty    = (int)($pf['TPAO']['qty'] ?? 0);
$avg    = (float)($pf['TPAO']['avg_price'] ?? 0);
$sellP  = (float)($panel['sell_price'] ?? 0);
$portfolio_value = $qty * $sellP;
$withdrawable = $bal; // sadece nakit çekilebilir
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Yatırım & Çekim</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  :root{ --bg1:#0b203f; --bg2:#08192d; --card:#132437; --line:#26384b; --txt:#cfdae6; --muted:#93a5ba; --good:#35d69c; --bad:#ff7a7a; --wrap:980px; }
  *{box-sizing:border-box}
  body{margin:0;font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:var(--txt);
       background:radial-gradient(1200px 900px at 70% -20%,#12345b 0%,var(--bg1) 40%,var(--bg2) 100%);min-height:100vh;}
  body.no-scroll{overflow:hidden;} /* modal açıkken kaydırmayı kilitle */

  header{position:sticky;top:0;z-index:10;background:linear-gradient(180deg,rgba(10,20,35,.9),rgba(10,20,35,.6) 60%,transparent);backdrop-filter:blur(8px)}
  .wrap{max-width:var(--wrap);margin:0 auto;padding:18px 18px 0;}
  .title{font-size:26px;font-weight:700;margin:10px 0 14px}
  .summary{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:14px}
  .sum{background:#102538;border:1px solid #19324a;border-radius:14px;padding:12px}
  .sum .k{color:#9fb2c8;font-size:12px}
  .sum .v{font-weight:700;font-size:18px;margin-top:6px}
  .card{background:var(--card);border-radius:18px;box-shadow:0 16px 40px rgba(0,0,0,.35);padding:16px;margin-bottom:16px}
  .grid2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
  .row{display:grid;grid-template-columns:220px 1fr;gap:10px;align-items:center}
  label.small{font-size:13px;color:#a9b6c6}
  input{width:100%;background:#1d2d44;border:none;border-radius:8px;padding:10px 12px;color:#cfd8e3}
  .btn{border:none;border-radius:10px;padding:12px 14px;background:#1e3a52;color:#dbe5f3;font-weight:600;cursor:pointer}
  .btn.green{background:#144a3a} .btn.red{background:#5a2430}
  .msg{margin:10px 0;padding:10px 12px;border-radius:10px}
  .ok{background:#234932;border:1px solid #2f6b4c;color:#b9f2b9}
  .err{background:#4b2b2b;border:1px solid #7b3a3a;color:#f7c1c1}
  table{width:100%;border-collapse:collapse;font-size:13px;color:#cfe0f3}
  th,td{padding:10px;border-top:1px solid #1f3347;text-align:left}
  .status{padding:3px 8px;border-radius:999px;font-weight:600;font-size:12px;display:inline-block}
  .PENDING{background:rgba(255, 183, 77,.15);color:#ffb74d}
  .COMPLETED{background:rgba(53,214,156,.15);color:#35d69c}
  .REJECTED{background:rgba(255,122,122,.15);color:#ff7a7a}

  /* Bottom nav — Favoriler kaldırıldı, 4 sekme */
  .bottom{position:sticky;bottom:0;background:#0e1f34;box-shadow:0 -6px 20px rgba(0,0,0,.4)}
  .tabs{max-width:var(--wrap);margin:0 auto;display:grid;grid-template-columns:repeat(4,1fr);gap:6px;padding:10px 18px}
  .tab{display:grid;place-items:center;color:#b8c8db;font-size:12px;gap:6px;padding:8px 0;border-radius:12px;text-decoration:none}
  .tab.active{background:#152a42}

  /* Modal (genel) — tam ortalama, yüksek z-index, animasyon */
  .modalBack{
    position:fixed; inset:0; display:none;
    align-items:center; justify-content:center;
    background:rgba(0,0,0,.55);
    z-index:999; /* header üstü */
    padding:16px; /* dar ekranlarda nefes payı */
  }
  .modalBack.open{ display:flex; }
  .modal{
    background:#14273d; border:1px solid #21405d; border-radius:14px;
    max-width:520px; width:min(92vw, 520px);
    padding:16px; box-shadow:0 20px 60px rgba(0,0,0,.6);
    transform:translateY(8px) scale(.98); opacity:0;
    animation: pop .18s ease-out forwards;
  }
  @keyframes pop{
    from{ transform:translateY(8px) scale(.98); opacity:0; }
    to{ transform:translateY(0) scale(1); opacity:1; }
  }
  .modal h3{margin:0 0 8px 0}
  .modal p{color:#9fb2c8;margin:0 0 12px 0;font-size:14px}
  .modal .actions{display:flex;justify-content:flex-end;gap:10px}
  .ibanBox{display:grid;grid-template-columns:110px 1fr auto;gap:8px;align-items:center;margin:8px 0}
  .ibanLabel{color:#9fb2c8;font-size:13px}
  .copyBtn{border:none;border-radius:8px;padding:8px 10px;background:#1e3a52;color:#dbe5f3;font-weight:600;cursor:pointer}

  @media(max-width:900px){
    .summary{grid-template-columns:1fr}
    .grid2{grid-template-columns:1fr}
    .row{grid-template-columns:1fr}
    .ibanBox{grid-template-columns:1fr auto}
  }
</style>
</head>
<body>

<header>
  <div class="wrap">
    <div class="title">Yatırım & Çekim</div>

    <div class="summary">
      <div class="sum"><div class="k">Nakit Bakiye (Çekilebilir)</div><div class="v"><?= tl($withdrawable) ?> ₺</div></div>
      <div class="sum"><div class="k">Portföy Değeri (Satış)</div><div class="v"><?= tl($portfolio_value) ?> ₺</div></div>
      <div class="sum"><div class="k">Eldeki Lot · Ortalama Maliyet</div><div class="v"><?= (int)$qty ?> · <?= tl($avg) ?> ₺</div></div>
    </div>

    <?php if($flash): ?>
      <div class="msg <?= $flash[0]==='ok'?'ok':'err' ?>"><?= htmlspecialchars($flash[1]) ?></div>
    <?php endif; ?>
  </div>
</header>

<main class="wrap">

  <!-- IBAN Bilgisi kutusunu istersen üstte göstermek için şu kartı açabilirsin -->
  <div class="card" style="display:flex;justify-content:center;padding:0">
    <div style="max-width:680px;width:100%;padding:18px">
      <div style="font-weight:700;margin-bottom:8px">Yatırım Bilgilendirme</div>
      <div class="ibanBox" style="display:grid;grid-template-columns:160px 1fr;gap:8px;background:#0f233b;border:1px dashed #1b3957;border-radius:14px;padding:14px">
        <div class="ibanLabel">IBAN</div>
        <div><b><?= htmlspecialchars(DEPOSIT_IBAN) ?></b></div>
        <div class="ibanLabel">Hesap Sahibi</div>
        <div><b><?= htmlspecialchars(DEPOSIT_HOLDER) ?></b></div>
        <div class="ibanLabel">Not</div>
        <div><span style="color:#9fb2c8">Açıklama kısmına e-posta anahtarınızı (<b><?= htmlspecialchars($U) ?></b>) yazmanız önerilir.</span></div>
      </div>
    </div>
  </div>

  <div class="grid2">
    <!-- YATIRIM -->
    <div class="card">
      <div style="font-weight:700;margin-bottom:8px">Para Yatır</div>
      <form method="post" id="depositForm" onsubmit="return openDepositInfo(event)">
        <input type="hidden" name="action" value="deposit">
        <div class="row">
          <label class="small" for="dep_amount">Tutar (₺)</label>
          <input id="dep_amount" name="amount" type="number" step="0.01" min="0.01" placeholder="Örn. 1000.00" required>
        </div>
        <button class="btn green" type="submit">Yatır</button>
      </form>
    </div>

    <!-- ÇEKİM TALEBİ -->
    <div class="card">
      <div style="font-weight:700;margin-bottom:8px">Çekim Talebi</div>
      <form method="post" onsubmit="return openConfirmWithdraw(event)">
        <input type="hidden" name="action" value="withdraw">
        <div class="row">
          <label class="small" for="wd_amount">Tutar (₺)</label>
          <input id="wd_amount" name="amount" type="number" step="0.01" min="0.01"
                 max="<?= htmlspecialchars(number_format($withdrawable,2,'.','')) ?>"
                 placeholder="Maks: <?= tl($withdrawable) ?>" required>
        </div>
        <div style="color:#9fb2c8;font-size:12px;margin-bottom:10px">
          Not: Çekilebilir tutar yalnızca <b>nakit bakiyedir</b> (eldeki lotların değeri hariç).
        </div>
        <button class="btn red" type="submit">Çekim Talebi Oluştur</button>
      </form>
    </div>
  </div>

  <!-- Geçmiş / Talepler -->
  <div class="card">
    <div style="font-weight:700;margin-bottom:8px">Yatırım & Çekim Geçmişi</div>
    <?php if($cashq): ?>
      <table>
        <thead>
          <tr>
            <th>Tarih / Saat</th>
            <th>İşlem</th>
            <th>Tutar (₺)</th>
            <th>Durum</th>
            <th>Açıklama</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach(array_reverse($cashq) as $r): ?>
            <tr>
              <td><?= htmlspecialchars($r['ts'] ?? '') ?></td>
              <td><?= ($r['type'] ?? '') === 'WITHDRAW' ? 'Çekim' : 'Yatırım' ?></td>
              <td><?= tl($r['amount'] ?? 0) ?> ₺</td>
              <?php $st = $r['status'] ?? 'PENDING'; ?>
              <td><span class="status <?= htmlspecialchars($st) ?>"><?= htmlspecialchars($st) ?></span></td>
              <td><?= htmlspecialchars($r['note'] ?? '') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div class="msg" style="background:#102538;border:1px solid #19324a;color:#9fb2c8">Henüz kayıt yok.</div>
    <?php endif; ?>
  </div>

</main>

<!-- bottom nav — Favoriler kaldırıldı, 4 sekme -->
<nav class="bottom">
  <div class="tabs">
    <a class="tab" href="varlik.php">📈<div>Aktif Varlıklar</div></a>
    <a class="tab" href="islemler.php">💼<div>İşlemler</div></a>
    <a class="tab active" href="yatirim.php">💳<div>Yatırım/Çekim</div></a>
    <a class="tab" href="menu.php">≡<div>Menü</div></a>
  </div>
</nav>

<!-- Modal: Yatırım IBAN Bilgilendirme -->
<div class="modalBack" id="depositModal" aria-hidden="true">
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="depositTitle">
    <h3 id="depositTitle">Yatırım Bilgileri</h3>
    <p>Seçtiğiniz tutarı aşağıdaki <b>IBAN</b> ve <b>Hesap Sahibi</b> bilgilerine havale/EFT ile gönderebilirsiniz.</p>

    <div class="ibanBox">
      <div class="ibanLabel">Tutar</div>
      <div><b id="depAmtView">₺ 0,00</b></div>
      <button class="copyBtn" type="button" onclick="copyText(document.getElementById('depAmtView').innerText)">Kopyala</button>
    </div>

    <div class="ibanBox">
      <div class="ibanLabel">IBAN</div>
      <div><b id="ibanText"><?= htmlspecialchars(DEPOSIT_IBAN) ?></b></div>
      <button class="copyBtn" type="button" onclick="copyText('<?= htmlspecialchars(DEPOSIT_IBAN) ?>')">Kopyala</button>
    </div>

    <div class="ibanBox">
      <div class="ibanLabel">Hesap Sahibi</div>
      <div><b id="holderText"><?= htmlspecialchars(DEPOSIT_HOLDER) ?></b></div>
      <button class="copyBtn" type="button" onclick="copyText('<?= htmlspecialchars(DEPOSIT_HOLDER) ?>')">Kopyala</button>
    </div>

    <p>Not: Açıklama kısmına <b><?= htmlspecialchars($U) ?></b> (kayıtlı e-posta anahtarınız) yazmanız işlemin eşleşmesine yardımcı olur.</p>

    <div class="actions">
      <button class="btn" type="button" onclick="closeDepositModal()">İptal</button>
      <button class="btn green" type="button" onclick="confirmDeposit()">Havale/EFT yaptım, onayla</button>
    </div>
  </div>
</div>

<!-- Modal: Çekim talebi alındı -->
<div class="modalBack" id="pendingModal" aria-hidden="true">
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="withdrawTitle">
    <h3 id="withdrawTitle">Çekim Talebi Alındı</h3>
    <p>Çekim talebiniz <b>değerlendirmede</b>. Onaylandığında bakiyenizden düşülecek ve durum <b>COMPLETED</b> olacaktır.</p>
    <div class="actions">
      <button class="btn" onclick="closeModal()">Tamam</button>
    </div>
  </div>
</div>

<script>
  // ---- Yardımcılar ----
  function copyText(t){ navigator.clipboard.writeText(t).catch(()=>{}); }

  // ---- Yatırım (Deposit) Bilgilendirme Modali ----
  const depositModal = document.getElementById('depositModal');
  const depAmtView   = document.getElementById('depAmtView');
  let depositSubmitLock = false;

  function openDepositInfo(e){
    if (depositSubmitLock) { return true; } // ikinci adımda gerçek submit
    e.preventDefault();
    const amt = parseFloat(document.getElementById('dep_amount').value || '0');
    if(!(amt>0)){ alert('Geçerli bir tutar girin.'); return false; }
    depAmtView.textContent = '₺ ' + amt.toLocaleString('tr-TR',{minimumFractionDigits:2, maximumFractionDigits:2});
    openBackdrop(depositModal);
    return false;
  }
  function closeDepositModal(){ closeBackdrop(depositModal); }
  function confirmDeposit(){
    // Modal onayı sonrası gerçek form submit
    depositSubmitLock = true;
    closeDepositModal();
    document.getElementById('depositForm').submit();
  }

  // ---- Çekim talebi onay/uyarı modali ----
  const modalBack = document.getElementById('pendingModal');
  function openModal(){ openBackdrop(modalBack); }
  function closeModal(){ closeBackdrop(modalBack); }
  function openConfirmWithdraw(e){
    const amt = parseFloat(document.getElementById('wd_amount').value || '0');
    if(!(amt>0)){ alert('Geçerli bir tutar girin.'); e.preventDefault(); return false; }
    // Form post edilsin; sunucu pending kaydeder, sonra geri gelince modal açılacak
    return true;
  }
  <?php if($openPendingModal): ?>
    window.addEventListener('DOMContentLoaded', openModal);
  <?php endif; ?>

  // ---- Ortalanmış modal yardımcıları + dış tık & Esc kapanışı + scroll kilidi ----
  function openBackdrop(backdropEl){
    backdropEl.classList.add('open');
    backdropEl.setAttribute('aria-hidden','false');
    document.body.classList.add('no-scroll');
  }
  function closeBackdrop(backdropEl){
    backdropEl.classList.remove('open');
    backdropEl.setAttribute('aria-hidden','true');
    document.body.classList.remove('no-scroll');
  }

  // Dış alana tıklayınca kapat
  [depositModal, modalBack].forEach(backdrop=>{
    backdrop.addEventListener('click', (e)=>{
      if (e.target === backdrop) { backdrop.classList.contains('open') && closeBackdrop(backdrop); }
    });
  });

  // Esc ile kapat
  document.addEventListener('keydown', (e)=>{
    if (e.key === 'Escape') {
      if (depositModal.classList.contains('open')) closeBackdrop(depositModal);
      if (modalBack.classList.contains('open')) closeBackdrop(modalBack);
    }
  });
</script>

</body>
</html>
