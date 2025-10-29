<?php
// islemler.php — TPAO tek enstrüman, TL bakiye, kişiye özel fiyat paneli (Favoriler kaldırıldı, alt menü optimize edildi)
ob_start();
session_start();
if (empty($_SESSION['user_email'])) { header('Location: index.php'); exit; }

function email_key(string $e): string { return strtolower(trim($e)); }
const DATA_DIR = __DIR__ . '/data';
if (!is_dir(DATA_DIR)) { @mkdir(DATA_DIR, 0775, true); }

$U = email_key($_SESSION['user_email']);
$WALLET_FILE   = DATA_DIR . "/wallet_{$U}.json";
$PANEL_FILE    = DATA_DIR . "/panel_{$U}.json";
$PORTF_FILE    = DATA_DIR . "/portfolio_{$U}.json";
$HIST_FILE     = DATA_DIR . "/history_{$U}.json";
$PRICEH_FILE   = DATA_DIR . "/price_history_{$U}.json";

function jload(string $file, $default){ if(!file_exists($file)) return $default; $raw=@file_get_contents($file); $d=json_decode($raw?:'null',true); return ($d===null?$default:$d); }
function jsave(string $file,$data){ return (bool)@file_put_contents($file,json_encode($data,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),LOCK_EX); }
function tl($n){ return number_format((float)$n, 2, ',', '.'); }
function now(){ return date('Y-m-d H:i:s'); }

// İlk kurulum
$wallet = jload($WALLET_FILE, ['balance'=>100000.00]);
$panel  = jload($PANEL_FILE,  ['buy_price'=>35.50,'sell_price'=>35.20,'fee_percent'=>0.10,'min_qty'=>1,'max_qty'=>100000]);
$pf     = jload($PORTF_FILE,  ['TPAO'=>['qty'=>0,'avg_price'=>0]]);
$hist   = jload($HIST_FILE,   []);
$phist  = jload($PRICEH_FILE, []);

$msg=null; $msg_type='ok';

// İşlemler (buy/sell/deposit)
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $act = $_POST['action'] ?? '';
  if ($act==='deposit') {
    $amt = max(0,(float)$_POST['amount']);
    if ($amt>0){ $wallet['balance']+=$amt; jsave($WALLET_FILE,$wallet); $msg = tl($amt)." TL yatırıldı."; } else { $msg="Geçerli tutar girin."; $msg_type='err'; }
  }
  if ($act==='buy') {
    $qty = max(0,(int)$_POST['qty']);
    if ($qty < (int)$panel['min_qty']) { $msg="Minimum lot: ".$panel['min_qty']; $msg_type='err'; }
    elseif ($qty > (int)$panel['max_qty']) { $msg="Maksimum lot: ".$panel['max_qty']; $msg_type='err'; }
    else {
      $gross=$qty*(float)$panel['buy_price']; $fee=$gross*((float)$panel['fee_percent']/100); $total=$gross+$fee;
      if ($total > (float)$wallet['balance']) { $msg="Yetersiz bakiye."; $msg_type='err'; }
      else {
        $wallet['balance'] -= $total;
        $curQty=(int)$pf['TPAO']['qty']; $curAvg=(float)$pf['TPAO']['avg_price'];
        $newQty=$curQty+$qty;
        $newAvg=$newQty? (($curQty*$curAvg)+$gross)/$newQty : (float)$panel['buy_price'];
        $pf['TPAO']=['qty'=>$newQty,'avg_price'=>$newAvg];
        jsave($WALLET_FILE,$wallet); jsave($PORTF_FILE,$pf);
        $hist[]=['ts'=>now(),'side'=>'BUY','qty'=>$qty,'price'=>(float)$panel['buy_price'],'fee'=>$fee,'total'=>$total];
        jsave($HIST_FILE,$hist);
        $msg="{$qty} lot TPAO alındı. Toplam ".tl($total)." TL (komisyon: ".tl($fee).")";
      }
    }
  }
  if ($act==='sell') {
    $qty = max(0,(int)$_POST['qty']); $have=(int)$pf['TPAO']['qty'];
    if ($qty<1){ $msg="Adet girin."; $msg_type='err'; }
    elseif ($qty>$have){ $msg="En fazla {$have} lot satabilirsiniz."; $msg_type='err'; }
    else{
      $gross=$qty*(float)$panel['sell_price']; $fee=$gross*((float)$panel['fee_percent']/100); $total=$gross-$fee;
      $wallet['balance'] += $total;
      $pf['TPAO']['qty']=$have-$qty;
      if($pf['TPAO']['qty']==0){$pf['TPAO']['avg_price']=0;}
      jsave($WALLET_FILE,$wallet); jsave($PORTF_FILE,$pf);
      $hist[]=['ts'=>now(),'side'=>'SELL','qty'=>$qty,'price'=>(float)$panel['sell_price'],'fee'=>$fee,'total'=>$total];
      jsave($HIST_FILE,$hist);
      $msg="{$qty} lot TPAO satıldı. Net ".tl($total)." TL (komisyon: ".tl($fee).")";
    }
  }
}

// Özet
$qty  = (int)$pf['TPAO']['qty'];
$avg  = (float)$pf['TPAO']['avg_price'];
$bal  = (float)$wallet['balance'];
$buyP = (float)$panel['buy_price'];
$sellP= (float)$panel['sell_price'];

$portfolio_value = $qty * $sellP;
$unreal          = $qty>0 ? ($sellP - $avg) * $qty : 0;
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>İşlemler • TPAO</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  :root{ --bg1:#0b203f; --bg2:#08192d; --card:#132437; --line:#26384b; --txt:#cfdae6; --muted:#93a5ba; --good:#35d69c; --bad:#ff7a7a; --wrap:980px; }
  *{box-sizing:border-box}
  body{margin:0;font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:var(--txt);
       background:radial-gradient(1200px 900px at 70% -20%,#12345b 0%,var(--bg1) 40%,var(--bg2) 100%);min-height:100vh;}
  header{position:sticky;top:0;z-index:10;background:linear-gradient(180deg,rgba(10,20,35,.9),rgba(10,20,35,.6) 60%,transparent);backdrop-filter:blur(8px)}
  .wrap{max-width:var(--wrap);margin:0 auto;padding:18px 18px 0;}
  .h1{font-size:26px;font-weight:700;margin:10px 0 14px}
  .summary{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:14px}
  .sum{background:#102538;border:1px solid #19324a;border-radius:14px;padding:12px}
  .sum .k{color:#9fb2c8;font-size:12px}
  .sum .v{font-weight:700;font-size:18px;margin-top:6px}
  .green{color:var(--good)} .red{color:var(--bad)}
  .card{background:var(--card);border-radius:18px;box-shadow:0 16px 40px rgba(0,0,0,.35);padding:16px;margin-bottom:16px}

  /* --- Enstrüman satırı düzeni --- */
  .row{ display:grid; grid-template-columns:auto 1fr auto; gap:16px; align-items:center; }
  .logo{ width:56px;height:56px;border-radius:12px;background:#0f233b;display:grid;place-items:center;overflow:hidden; }
  .logo img{ width:100%; height:100%; object-fit:contain; object-position:center; display:block; }
  .inst .name{font-weight:700;font-size:18px}
  .inst .mut{color:#8fa3b8;font-size:13px;margin-top:4px}
  .prices{ margin-left:auto; display:flex; gap:22px; align-items:flex-end; }
  .price{ text-align:right; min-width:170px; }
  .price .mut{color:#8fa3b8;font-size:12px}
  .price .val{font-weight:700;font-size:18px}

  .grid2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
  label.small{display:block;font-size:13px;color:#a9b6c6;margin:8px 0 6px}
  input{width:100%;background:#1d2d44;border:none;border-radius:8px;padding:10px 12px;color:#cfd8e3}
  .btn{border:none;border-radius:10px;padding:12px 14px;background:#1e3a52;color:#dbe5f3;font-weight:600;cursor:pointer}
  .btn.buy{background:#144a3a} .btn.sell{background:#5a2430}
  .msg{margin:10px 0;padding:10px 12px;border-radius:10px}
  .ok{background:#234932;border:1px solid #2f6b4c;color:#b9f2b9}
  .err{background:#4b2b2b;border:1px solid #7b3a3a;color:#f7c1c1}

  /* --- Alt sekme: Favoriler kaldırıldı; 4 kolon, güvenli alan, büyük dokunma alanı --- */
  .bottom{
    position:sticky;bottom:0;background:#0e1f34;box-shadow:0 -6px 20px rgba(0,0,0,.4);
    padding-bottom: max(8px, env(safe-area-inset-bottom)); /* iOS güvenli alan */
  }
  .tabs{
    max-width:var(--wrap);margin:0 auto;
    display:grid;grid-template-columns:repeat(4,1fr);
    gap:6px;padding:10px 18px;
  }
  .tab{
    display:flex;flex-direction:column;align-items:center;justify-content:center;
    color:#b8c8db;font-size:12px;gap:6px;padding:10px 0;border-radius:12px;text-decoration:none;
    min-height:48px; /* dokunma alanı */
  }
  .tab.active{background:#152a42}

  @media(max-width:900px){
    .summary{grid-template-columns:repeat(2,1fr)}
    .grid2{grid-template-columns:1fr}
    .prices{ margin-left:0; width:100%; justify-content:center; text-align:center; }
    .price{ min-width:auto; }
  }
</style>
</head>
<body>

<header>
  <div class="wrap">
    <div class="h1">İşlemler · Türkiye Petrolleri A.Ş.</div>

    <div class="summary">
      <div class="sum"><div class="k">Bakiye (TL)</div><div class="v"><?= tl($bal) ?> ₺</div></div>
      <div class="sum"><div class="k">Eldeki Lot</div><div class="v"><?= (int)$qty ?></div></div>
      <div class="sum"><div class="k">Portföy Değeri (Satış)</div><div class="v"><?= tl($portfolio_value) ?> ₺</div></div>
      <div class="sum">
        <div class="k">Gerç. Olmayan K/Z</div>
        <?php $c = $unreal>=0?'green':'red'; ?>
        <div class="v <?= $c ?>"><?= ($unreal>=0?'+':'').tl($unreal) ?> ₺</div>
      </div>
    </div>
  </div>
</header>

<main class="wrap">

  <!-- Enstrüman kartı -->
  <div class="card">
    <div class="row">
      <div class="logo">
        <img src="assets/tpo.png" alt="TPAO" onerror="this.style.display='none'">
      </div>

      <div class="inst">
        <div class="name">Türkiye Petrolleri A.Ş. (TPAO)</div>
        <div class="mut">Para Birimi: TL · Komisyon: <?= rtrim(rtrim(number_format((float)$panel['fee_percent'],2,'.',''), '0'), '.') ?>%</div>
      </div>

      <div class="prices">
        <div class="price">
          <div class="mut">Güncel Alış Fiyatı</div>
          <div class="val"><?= tl($buyP) ?> ₺</div>
        </div>
        <div class="price">
          <div class="mut">Güncel Satış Fiyatı</div>
          <div class="val"><?= tl($sellP) ?> ₺</div>
        </div>
      </div>
    </div>
  </div>

  <!-- İşlem formları -->
  <div class="grid2">
    <div class="card">
      <form method="post">
        <input type="hidden" name="action" value="buy">
        <label class="small">Alım Adedi (lot)</label>
        <input type="number" name="qty" min="<?= (int)$panel['min_qty'] ?>" max="<?= (int)$panel['max_qty'] ?>" step="1" value="<?= (int)$panel['min_qty'] ?>" oninput="calcBuy()">
        <div class="mut" style="margin:6px 0">Min: <?= (int)$panel['min_qty'] ?> · Maks: <?= (int)$panel['max_qty'] ?></div>
        <div id="buyCost" class="mut" style="margin:6px 0"></div>
        <button class="btn buy" type="submit">Satın Al</button>
      </form>
    </div>

    <div class="card">
      <form method="post">
        <input type="hidden" name="action" value="sell">
        <label class="small">Satış Adedi (lot)</label>
        <input type="number" name="qty" min="1" max="<?= (int)$qty ?>" step="1" value="<?= min(1,(int)$qty) ?>" oninput="calcSell()">
        <div class="mut" style="margin:6px 0">Eldeki: <?= (int)$qty ?> lot</div>
        <div id="sellProceeds" class="mut" style="margin:6px 0"></div>
        <button class="btn sell" type="submit">Sat</button>
      </form>
    </div>
  </div>

  <!-- Kişisel alım bilgisi -->
  <div class="card">
    <div class="mut">Kişisel Alım Bilgisi:</div>
    <div style="margin-top:6px;font-size:14px">
      <?php if($qty>0): ?>
        <b><?= (int)$qty ?></b> lotu <b><?= tl($avg) ?> ₺</b> fiyatından aldınız.<br>
        Güncel satış fiyatı: <b><?= tl($sellP) ?> ₺</b>.<br>
        Şu anki durumda <?= ($unreal>=0?'<span class="green">kâr</span>':'<span class="red">zarar</span>') ?>:
        <b><?= ($unreal>=0?'+':'').tl($unreal) ?> ₺</b>.
      <?php else: ?>
        Henüz TPAO alım işleminiz bulunmuyor.
      <?php endif; ?>
    </div>
  </div>

  <div style="height:18px"></div>
</main>

<!-- bottom nav — Favoriler kaldırıldı; Geçmiş -> Yatırım/Çekim (deposit.php) -->
<nav class="bottom">
  <div class="tabs">
    <a class="tab" href="varlik.php">📈<div>Aktif Varlıklar</div></a>
    <a class="tab active" href="islemler.php">💼<div>İşlemler</div></a>
    <a class="tab" href="deposit.php">💳<div>Yatırım/Çekim</div></a>
    <a class="tab" href="menu.php">≡<div>Menü</div></a>
  </div>
</nav>

<script>
  const buyP  = <?= json_encode($buyP) ?>;
  const sellP = <?= json_encode($sellP) ?>;
  const fee   = <?= json_encode((float)$panel['fee_percent']) ?>; // %

  function tljs(n){ return new Intl.NumberFormat('tr-TR',{minimumFractionDigits:2, maximumFractionDigits:2}).format(n); }

  function calcBuy(){
    const inp = document.querySelector('form [name="action"][value="buy"]').form.querySelector('[name="qty"]');
    const qty = parseInt(inp.value||0,10);
    const gross = qty*buyP, feeAmt = gross*(fee/100), total = gross+feeAmt;
    document.getElementById('buyCost').textContent = qty>0 ? `Toplam: ${tljs(total)} ₺ (komisyon: ${tljs(feeAmt)} ₺)` : '';
  }
  function calcSell(){
    const inp = document.querySelector('form [name="action"][value="sell"]').form.querySelector('[name="qty"]');
    const qty = parseInt(inp.value||0,10);
    const gross = qty*sellP, feeAmt = gross*(fee/100), net = gross-feeAmt;
    document.getElementById('sellProceeds').textContent = qty>0 ? `Net: ${tljs(net)} ₺ (komisyon: ${tljs(feeAmt)} ₺)` : '';
  }
  calcBuy(); calcSell();
</script>

</body>
</html>
