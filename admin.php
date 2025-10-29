<?php
// admin.php — Gelişmiş panel: global IBAN/Hesap, talepler yönetimi, ban, kullanıcı düzenleme
ob_start();
session_start();

const DATA_DIR   = __DIR__ . '/data';
const USERS_FILE = DATA_DIR . '/users.json';
const DEPSET_FILE = DATA_DIR . '/deposit_settings.json';
const BANNED_FILE = DATA_DIR . '/banned.json';

// Basit admin kimlik bilgileri (güncelleyin)
const ADMIN_USER = 'admin';
const ADMIN_PASS = 'admin123';

if (!is_dir(DATA_DIR)) { @mkdir(DATA_DIR, 0775, true); }
if (!file_exists(USERS_FILE)) { file_put_contents(USERS_FILE, json_encode(new stdClass(), JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)); }
if (!file_exists(DEPSET_FILE)) { file_put_contents(DEPSET_FILE, json_encode(['iban'=>'TR00 0000 0000 0000 0000 0000 00','holder'=>'AD SOYAD'], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)); }
if (!file_exists(BANNED_FILE)) { file_put_contents(BANNED_FILE, json_encode([], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)); }

function email_key(string $e): string { return strtolower(trim($e)); }
function tl($n){ return number_format((float)$n, 2, ',', '.'); }
function now(){ return date('Y-m-d H:i:s'); }
function jload($file,$def){ if(!file_exists($file)) return $def; $raw=@file_get_contents($file); $d=json_decode($raw?:'null',true); return ($d===null?$def:$d); }
function jsave($file,$data){ return (bool)@file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), LOCK_EX); }

function wallet_file($k){ return DATA_DIR . "/wallet_{$k}.json"; }
function panel_file($k){  return DATA_DIR . "/panel_{$k}.json"; }
function pf_file($k){     return DATA_DIR . "/portfolio_{$k}.json"; }
function price_hist_file($k){ return DATA_DIR . "/price_history_{$k}.json"; }
function cash_file($k){   return DATA_DIR . "/cash_requests_{$k}.json"; }

// ---- Admin login/logout ----
if (isset($_GET['logout'])) { unset($_SESSION['is_admin']); header('Location: admin.php'); exit; }
$login_error = null;
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='admin_login') {
  $u = trim($_POST['user'] ?? ''); $p = trim($_POST['pass'] ?? '');
  if ($u===ADMIN_USER && $p===ADMIN_PASS) { $_SESSION['is_admin'] = true; header('Location: admin.php'); exit; }
  else { $login_error = 'Hatalı kullanıcı adı veya şifre.'; }
}
$is_admin = !empty($_SESSION['is_admin']);

// ---- İşlemler ----
$flash = null;

// Global IBAN/Hesap Sahibi
if ($is_admin && $_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='save_global') {
  $iban = trim($_POST['iban'] ?? '');
  $holder = trim($_POST['holder'] ?? '');
  if ($iban==='' || $holder==='') { $flash=['err','IBAN ve Hesap Sahibi boş olamaz.']; }
  else {
    $ok = jsave(DEPSET_FILE, ['iban'=>$iban,'holder'=>$holder]);
    $flash = [$ok?'ok':'err', $ok?'Genel yatırım bilgileri güncellendi.':'Kayıt hatası.'];
  }
}

// Kullanıcı değerleri kaydetme (bakiye/fiyat/lot vb.)
if ($is_admin && $_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='save_user') {
  $k = email_key($_POST['email_key'] ?? '');
  if ($k==='') { $flash=['err','Kullanıcı anahtarı boş.']; }
  else {
    $wallet = jload(wallet_file($k), ['balance'=>100000.00]);
    $panel  = jload(panel_file($k),  ['buy_price'=>35.50,'sell_price'=>35.20,'fee_percent'=>0.10,'min_qty'=>1,'max_qty'=>100000]);
    $pf     = jload(pf_file($k),     ['TPAO'=>['qty'=>0,'avg_price'=>0]]);

    $old_buy  = (float)$panel['buy_price'];
    $old_sell = (float)$panel['sell_price'];
    $qty      = (int)($pf['TPAO']['qty'] ?? 0);
    $avg      = (float)($pf['TPAO']['avg_price'] ?? 0);

    $wallet['balance']    = (float)($_POST['balance'] ?? $wallet['balance']);
    $panel['buy_price']   = (float)($_POST['buy_price'] ?? $panel['buy_price']);
    $panel['sell_price']  = (float)($_POST['sell_price'] ?? $panel['sell_price']);
    $panel['fee_percent'] = (float)($_POST['fee_percent'] ?? $panel['fee_percent']);
    $panel['min_qty']     = (int)($_POST['min_qty'] ?? $panel['min_qty']);
    $panel['max_qty']     = max((int)$panel['min_qty'], (int)($_POST['max_qty'] ?? $panel['max_qty']));

    // Lot & ort. maliyet
    $pf['TPAO']['qty']       = (int)($_POST['lot'] ?? $pf['TPAO']['qty']);
    $pf['TPAO']['avg_price'] = (float)($_POST['avg_price'] ?? $pf['TPAO']['avg_price']);

    $ok1 = jsave(wallet_file($k), $wallet);
    $ok2 = jsave(panel_file($k),  $panel);
    $ok3 = jsave(pf_file($k),     $pf);

    // Fiyat değişimi etkisi logu
    if ($old_buy != $panel['buy_price'] || $old_sell != $panel['sell_price']) {
      $new_buy  = (float)$panel['buy_price'];
      $new_sell = (float)$panel['sell_price'];
      $before_value = $qty * $old_sell;
      $after_value  = $qty * $new_sell;
      $delta_value  = $after_value - $before_value;
      $unreal_before = ($old_sell - $avg) * $qty;
      $unreal_after  = ($new_sell - $avg) * $qty;
      $unreal_delta  = $unreal_after - $unreal_before;
      $hist = jload(price_hist_file($k), []);
      $hist[] = [
        'ts'=>now(),
        'qty_at_change'=>$qty, 'avg_price'=>$avg,
        'old_buy'=>$old_buy, 'new_buy'=>$new_buy,
        'old_sell'=>$old_sell,'new_sell'=>$new_sell,
        'portfolio_value_before'=>$before_value,'portfolio_value_after'=>$after_value,'portfolio_value_delta'=>$delta_value,
        'unreal_before'=>$unreal_before,'unreal_after'=>$unreal_after,'unreal_delta'=>$unreal_delta
      ];
      jsave(price_hist_file($k), $hist);
    }

    $flash = ($ok1&&$ok2&&$ok3) ? ['ok','Kullanıcı değerleri güncellendi.'] : ['err','Dosyaya yazılamadı.'];
  }
}

// Talepler işlem (çekim onay/red, yatırım iptal)
if ($is_admin && $_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='req_action') {
  $k   = email_key($_POST['email_key'] ?? '');
  $idx = (int)($_POST['idx'] ?? -1);
  $op  = $_POST['op'] ?? '';
  $cash = jload(cash_file($k), []);
  if (!isset($cash[$idx])) { $flash=['err','Kayıt bulunamadı.']; }
  else {
    $row = $cash[$idx];
    $wallet = jload(wallet_file($k), ['balance'=>100000.00]);

    if ($op==='approve_withdraw' && ($row['type']??'')==='WITHDRAW' && ($row['status']??'')!=='COMPLETED') {
      // Yeterli bakiye kontrolü (normalde user tarafında kilitli, yine de kontrol)
      $amt = (float)$row['amount'];
      if ($wallet['balance'] >= $amt) {
        $wallet['balance'] -= $amt;
        $cash[$idx]['status'] = 'COMPLETED';
        $cash[$idx]['note'] = 'Çekim onaylandı.';
        jsave(wallet_file($k), $wallet);
        jsave(cash_file($k), $cash);
        $flash=['ok','Çekim onaylandı ve bakiye düşüldü.'];
      } else { $flash=['err','Kullanıcı bakiyesi yetersiz görünüyor.']; }
    }
    elseif ($op==='reject_withdraw' && ($row['type']??'')==='WITHDRAW' && ($row['status']??'')!=='REJECTED') {
      $cash[$idx]['status'] = 'REJECTED';
      $cash[$idx]['note'] = 'Çekim talebi reddedildi.';
      jsave(cash_file($k), $cash);
      $flash=['ok','Çekim reddedildi.'];
    }
    elseif ($op==='cancel_deposit' && ($row['type']??'')==='DEPOSIT' && ($row['status']??'')!=='CANCELED') {
      // Yatırımı geri al (bakiyeden düş)
      $amt = (float)$row['amount'];
      $wallet['balance'] = max(0, (float)$wallet['balance'] - $amt);
      $cash[$idx]['status'] = 'CANCELED';
      $cash[$idx]['note'] = 'Yatırım iptal edildi (admin).';
      jsave(wallet_file($k), $wallet);
      jsave(cash_file($k), $cash);
      $flash=['ok','Yatırım iptal edildi ve kullanıcı bakiyesi geri alındı.'];
    } else {
      $flash=['err','Geçersiz işlem veya durum.'];
    }
  }
}

// Ban ekle/çıkar
if ($is_admin && $_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='ban_toggle') {
  $k = email_key($_POST['email_key'] ?? '');
  $banned = jload(BANNED_FILE, []);
  if ($_POST['mode']==='ban') {
    if (!in_array($k,$banned,true)) { $banned[]=$k; jsave(BANNED_FILE,$banned); $flash=['ok','Kullanıcı banlandı.']; }
    else { $flash=['ok','Kullanıcı zaten banlı.']; }
  } else {
    $banned = array_values(array_filter($banned, fn($x)=>$x!==$k));
    jsave(BANNED_FILE,$banned);
    $flash=['ok','Ban kaldırıldı.'];
  }
}

// ---- UI verileri ----
$users = $is_admin ? jload(USERS_FILE, []) : [];
$filter = email_key($_GET['q'] ?? '');
$global = jload(DEPSET_FILE, ['iban'=>'','holder'=>'']);
$banned_list = jload(BANNED_FILE, []);
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Admin Paneli</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  :root{ --bg1:#0b203f; --bg2:#08192d; --card:#132437; --line:#26384b; --txt:#cfdae6; --muted:#93a5ba; --good:#35d69c; --bad:#ff7a7a; --wrap:1100px; }
  *{box-sizing:border-box}
  body{margin:0;font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:var(--txt);
       background:radial-gradient(1200px 900px at 70% -20%,#12345b 0%,var(--bg1) 40%,var(--bg2) 100%);min-height:100vh;}
  header{position:sticky;top:0;z-index:10;background:linear-gradient(180deg,rgba(10,20,35,.9),rgba(10,20,35,.6) 60%,transparent);backdrop-filter:blur(8px)}
  .wrap{max-width:var(--wrap);margin:0 auto;padding:18px}
  .h1{font-size:26px;font-weight:700;margin:10px 0 14px}
  .card{background:var(--card);border-radius:16px;box-shadow:0 16px 40px rgba(0,0,0,.35);padding:16px;margin:16px 0}
  .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  .row{display:grid;grid-template-columns:220px 1fr;gap:10px;align-items:center}
  input,textarea{width:100%;background:#1d2d44;border:none;border-radius:8px;padding:10px 12px;color:#cfd8e3}
  .btn{border:none;border-radius:10px;padding:10px 14px;background:#1e3a52;color:#fff;font-weight:600;cursor:pointer}
  .btn.red{background:#5a2430}
  .btn.green{background:#144a3a}
  .msg{padding:10px;border-radius:10px;margin:10px 0}
  .ok{background:#234932;color:#b9f2b9}
  .err{background:#4b2b2b;color:#f7c1c1}
  .search{display:flex;gap:8px;align-items:center;background:#102538;border:1px solid #19324a;border-radius:12px;padding:10px 12px}
  .search input{background:transparent;border:none;color:#cfe0f3;outline:none}
  table{width:100%;border-collapse:collapse;font-size:13px;color:#cfe0f3}
  th,td{padding:10px;border-top:1px solid #1f3347;text-align:left}
  .status{padding:3px 8px;border-radius:999px;font-weight:600;font-size:12px;display:inline-block}
  .PENDING{background:rgba(255, 183, 77,.15);color:#ffb74d}
  .COMPLETED{background:rgba(53,214,156,.15);color:#35d69c}
  .REJECTED{background:rgba(255,122,122,.15);color:#ff7a7a}
  .CANCELED{background:rgba(160,176,196,.15);color:#cfd7e5}
  .mini{color:#a9bed2;font-size:12px;margin-top:8px}
  @media(max-width:980px){ .grid{grid-template-columns:1fr} .row{grid-template-columns:1fr} }
</style>
</head>
<body>

<header>
  <div class="wrap">
    <div class="h1">Admin Paneli</div>
  </div>
</header>

<div class="wrap">
<?php if(!$is_admin): ?>
  <div class="card" style="max-width:420px">
    <div style="font-weight:700;margin-bottom:8px">Giriş</div>
    <?php if($login_error): ?><div class="msg err"><?= htmlspecialchars($login_error) ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="action" value="admin_login">
      <div class="row"><label>Kullanıcı adı</label><input name="user" required></div>
      <div class="row"><label>Şifre</label><input type="password" name="pass" required></div>
      <div style="margin-top:10px"><button class="btn" type="submit">Giriş</button></div>
    </form>
  </div>
<?php else: ?>

  <?php if($flash): ?><div class="msg <?= $flash[0]==='ok'?'ok':'err' ?>"><?= htmlspecialchars($flash[1]) ?></div><?php endif; ?>

  <!-- Top çubuk -->
  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap">
      <div class="search" style="flex:1;min-width:260px">🔎
        <input placeholder="E-posta ile ara..." value="<?= htmlspecialchars($filter) ?>" onkeydown="if(event.key==='Enter'){window.location='admin.php?q='+this.value.trim();}">
      </div>
      <a class="btn red" href="admin.php?logout=1">Çıkış</a>
    </div>
  </div>

  <!-- GLOBAL: IBAN / Hesap Sahibi -->
  <div class="card">
    <div style="font-weight:700;margin-bottom:8px">Genel Yatırım Bilgileri</div>
    <form method="post">
      <input type="hidden" name="action" value="save_global">
      <div class="grid">
        <div class="row"><label>IBAN</label><input name="iban" value="<?= htmlspecialchars($global['iban']) ?>"></div>
        <div class="row"><label>Hesap Sahibi</label><input name="holder" value="<?= htmlspecialchars($global['holder']) ?>"></div>
      </div>
      <div style="margin-top:12px"><button class="btn green" type="submit">Kaydet</button></div>
      <div class="mini">Bu bilgiler tüm kullanıcıların <b>deposit.php</b> (Yatırım) ekranında gösterilir.</div>
    </form>
  </div>

  <!-- TALEPLER: tüm kullanıcılar -->
  <div class="card">
    <div style="font-weight:700;margin-bottom:8px">Yatırım & Çekim Talepleri</div>
    <table>
      <thead>
        <tr>
          <th>Kullanıcı</th><th>Tarih</th><th>Tür</th><th>Tutar</th><th>Durum</th><th>İşlem</th>
        </tr>
      </thead>
      <tbody>
      <?php
        foreach ($users as $k => $info) {
          if ($filter && strpos($k,$filter)===false) continue;
          $cash = jload(cash_file($k), []);
          foreach (array_reverse($cash, true) as $idx => $r) {
            $type = ($r['type']??'')==='WITHDRAW' ? 'Çekim' : 'Yatırım';
            $st   = $r['status'] ?? 'PENDING';
            echo '<tr>';
            echo '<td>'.htmlspecialchars($k).'</td>';
            echo '<td>'.htmlspecialchars($r['ts']??'').'</td>';
            echo '<td>'.$type.'</td>';
            echo '<td>'.tl($r['amount']??0).' ₺</td>';
            echo '<td><span class="status '.$st.'">'.$st.'</span></td>';
            echo '<td style="display:flex;gap:6px;flex-wrap:wrap">';
            echo '<form method="post"><input type="hidden" name="action" value="req_action"><input type="hidden" name="email_key" value="'.htmlspecialchars($k).'"><input type="hidden" name="idx" value="'.$idx.'">';
            if (($r['type']??'')==='WITHDRAW' && $st!=='COMPLETED' && $st!=='REJECTED') {
              echo '<button class="btn green" name="op" value="approve_withdraw">Onayla</button>';
              echo '<button class="btn red"   name="op" value="reject_withdraw" type="submit">Reddet</button>';
            }
            if (($r['type']??'')==='DEPOSIT' && $st!=='CANCELED') {
              echo '<button class="btn red" name="op" value="cancel_deposit" type="submit">Yatırımı İptal</button>';
            }
            echo '</form>';
            echo '</td>';
            echo '</tr>';
          }
        }
      ?>
      </tbody>
    </table>
  </div>

  <!-- KULLANICI KARTLARI -->
  <?php
    foreach ($users as $k => $info) {
      if ($filter && strpos($k, $filter) === false) continue;
      $wallet = jload(wallet_file($k), ['balance'=>100000.00]);
      $panel  = jload(panel_file($k),  ['buy_price'=>35.50,'sell_price'=>35.20,'fee_percent'=>0.10,'min_qty'=>1,'max_qty'=>100000]);
      $pf     = jload(pf_file($k),     ['TPAO'=>['qty'=>0,'avg_price'=>0]]);
      $qty  = (int)($pf['TPAO']['qty'] ?? 0);
      $avg  = (float)($pf['TPAO']['avg_price'] ?? 0);
      $isBanned = in_array($k, $banned_list, true);
  ?>
    <div class="card">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap">
        <div style="font-weight:700"><?= htmlspecialchars(($info['name']??'').' '.($info['surname']??'')) ?> · <span class="mini"><?= htmlspecialchars($k) ?></span></div>
        <form method="post" onsubmit="return confirm('Emin misiniz?');" style="margin:0">
          <input type="hidden" name="action" value="ban_toggle">
          <input type="hidden" name="email_key" value="<?= htmlspecialchars($k) ?>">
          <?php if($isBanned): ?>
            <input type="hidden" name="mode" value="unban">
            <button class="btn" type="submit">Banı Kaldır</button>
          <?php else: ?>
            <input type="hidden" name="mode" value="ban">
            <button class="btn red" type="submit">Banla</button>
          <?php endif; ?>
        </form>
      </div>

      <form method="post">
        <input type="hidden" name="action" value="save_user">
        <input type="hidden" name="email_key" value="<?= htmlspecialchars($k) ?>">
        <div class="grid">
          <div>
            <div class="row"><label>Bakiye (₺)</label><input name="balance" type="text" value="<?= htmlspecialchars(number_format((float)$wallet['balance'],2,'.','')) ?>"></div>
            <div class="row"><label>Alış fiyatı (₺)</label><input name="buy_price" type="text" value="<?= htmlspecialchars(number_format((float)$panel['buy_price'],2,'.','')) ?>"></div>
            <div class="row"><label>Satış fiyatı (₺)</label><input name="sell_price" type="text" value="<?= htmlspecialchars(number_format((float)$panel['sell_price'],2,'.','')) ?>"></div>
            <div class="row"><label>Komisyon (%)</label><input name="fee_percent" type="text" value="<?= htmlspecialchars(number_format((float)$panel['fee_percent'],2,'.','')) ?>"></div>
          </div>
          <div>
            <div class="row"><label>Min lot</label><input name="min_qty" type="number" step="1" value="<?= (int)$panel['min_qty'] ?>"></div>
            <div class="row"><label>Maks lot</label><input name="max_qty" type="number" step="1" value="<?= (int)$panel['max_qty'] ?>"></div>
            <div class="row"><label>Eldeki Lot</label><input name="lot" type="number" step="1" value="<?= $qty ?>"></div>
            <div class="row"><label>Ortalama Maliyet (₺)</label><input name="avg_price" type="text" value="<?= htmlspecialchars(number_format($avg,2,'.','')) ?>"></div>
          </div>
        </div>
        <div style="margin-top:12px"><button class="btn" type="submit">Kaydet</button></div>
      </form>
    </div>
  <?php } ?>

  <?php if(!$users): ?><div class="card">Kayıtlı kullanıcı bulunamadı.</div><?php endif; ?>

<?php endif; ?>
</div>
</body>
</html>
