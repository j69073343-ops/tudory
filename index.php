<?php
session_start();
/* ---------------------------------------------
   Basit dosya tabanlı kullanıcı sistemi (SQL YOK)
---------------------------------------------- */
const DATA_DIR   = __DIR__ . '/data';
const USERS_FILE = DATA_DIR . '/users.json';

if (!is_dir(DATA_DIR)) { @mkdir(DATA_DIR, 0775, true); }
if (!file_exists(USERS_FILE)) {
  file_put_contents(USERS_FILE, json_encode(new stdClass(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function load_users(): array {
  $raw = @file_get_contents(USERS_FILE);
  $data = json_decode($raw ?: '{}', true);
  return is_array($data) ? $data : [];
}
function save_users(array $users): bool {
  $json = json_encode($users, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  return (bool) @file_put_contents(USERS_FILE, $json, LOCK_EX);
}
function email_key(string $email): string { return strtolower(trim($email)); }

$errors = []; $reg_errors = [];

/* ---------------------------------------------
   POST İşlemleri
---------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? 'login';

  /* ------ GİRİŞ ------ */
  if ($action === 'login') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    $users = load_users();
    $k = email_key($email);
    if (isset($users[$k]) && password_verify($pass, $users[$k]['password'])) {
      $_SESSION['user_email'] = $k;
      header("Location: islemler.php");
      exit;
    } else {
      $errors[] = 'E-posta veya şifre hatalı. (Kayıtlı hesapla deneyin)';
    }
  }

  /* ------ KAYIT ------ */
  else if ($action === 'register') {
    $name    = trim($_POST['name'] ?? '');
    $surname = trim($_POST['surname'] ?? '');
    $email   = trim($_POST['reg_email'] ?? '');
    // Telefon: gizli "phone" alanında +90xxxxxxxxxx biçimiyle gelir
    $phone   = trim($_POST['phone'] ?? '');
    $pass1   = $_POST['reg_password']  ?? '';
    $pass2   = $_POST['reg_password2'] ?? '';
    $country = $_POST['country'] ?? 'TR';
    $lang    = $_POST['lang']    ?? 'tr';
    $agree   = isset($_POST['agree']);

    // Zorunlu alanlar + kurallar
    if ($name === '')      $reg_errors[] = 'İsim gerekli.';
    if ($surname === '')   $reg_errors[] = 'Soyisim gerekli.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strpos($email,'@')===false)
      $reg_errors[] = 'Geçerli bir e-posta girin ("@" içermeli).';
    if (strlen($pass1) < 6)  $reg_errors[] = 'Şifre en az 6 karakter olmalı.';
    if ($pass1 !== $pass2)   $reg_errors[] = 'Şifreler eşleşmiyor.';
    if (!$agree)             $reg_errors[] = 'Gizlilik ve koşulları kabul etmelisiniz.';

    // Telefon doğrulaması: +90 ve ardından 10 rakam
    if ($phone === '' || !preg_match('/^\+90\d{10}$/', $phone)) {
      $reg_errors[] = 'Telefon +90 ile başlamalı ve 10 rakam içermeli (örn. +905xxxxxxxxx).';
    }

    // Kilitli alanlar
    if ($country !== 'TR') { $reg_errors[] = 'Ülke sadece Türkiye olabilir.'; }
    if ($lang !== 'tr')    { $reg_errors[] = 'Destek dili sadece Turkish olabilir.'; }

    // Kullanıcı çakışması
    $users = load_users();
    $k = email_key($email);
    if (isset($users[$k])) { $reg_errors[] = 'Bu e-posta ile zaten bir hesap var.'; }

    if (!$reg_errors) {
      $users[$k] = [
        'name'       => $name,
        'surname'    => $surname,
        'email'      => $k,
        'phone'      => $phone,     // +90xxxxxxxxxx
        'country'    => $country,   // TR
        'lang'       => $lang,      // tr
        'password'   => password_hash($pass1, PASSWORD_DEFAULT),
        'created_at' => date('c')
      ];
	  // Giriş doğrulaması yapılmadan hemen önce/sonra ekleyin:
$banned = json_decode(@file_get_contents(__DIR__.'/data/banned.json') ?: '[]', true);
$k = strtolower(trim($email)); // girişte kullanılan e-posta anahtarı
if (in_array($k, $banned, true)) {
  // İsteğe bağlı: cihaz tanımlayıcıyı cookie ile tutabilirsiniz
  die('Hesabınız/sürümünüz engellenmiştir.');
}
      if (save_users($users)) {
        // ✅ Kayıt başarılı → OTOMATİK GİRİŞ + YÖNLENDİRME
        $_SESSION['user_email'] = $k;
        header('Location: islemler.php');
        exit;
      } else {
        $reg_errors[] = 'Kayıt dosyası yazılamadı. (data/ yazma izni gerekli)';
      }
    }
  }
}
?>
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Trading</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#cdd5df;
      background:radial-gradient(1300px 900px at 70% -20%,#12345b 0%,#0b203f 40%,#08192d 100%);min-height:100vh;display:flex;flex-direction:column;align-items:center}
    header{width:100%;max-width:1600px;display:flex;justify-content:space-between;align-items:center;padding:22px 36px 0}
    .brand{display:flex;align-items:center;gap:12px}
    .logo{width:40px;height:40px;border-radius:10px;background:#007AFF;display:grid;place-items:center;box-shadow:0 4px 14px rgba(0,0,0,.25)}
    .logo svg{width:20px;height:20px;fill:#fff}
    .langbar{display:flex;align-items:center;gap:10px;font-size:14px;color:#d0deee}
    .flag{width:22px;border-radius:3px}
    main{flex:1;width:100%;display:grid;place-items:center}
    .card{width:820px;max-width:94vw;background:#162233;border-radius:18px;box-shadow:0 20px 40px rgba(0,0,0,.4);padding:30px}
    h2{font-size:20px;margin-bottom:18px}
    .tabs{display:flex;border-radius:8px;background:#1b2a3d;margin-bottom:20px;overflow:hidden}
    .tabs button{flex:1;background:transparent;border:none;color:#cdd5df;padding:8px 0;font-weight:500;cursor:pointer}
    .tabs button.active{background:#22344a}
    label.small{display:block;font-size:13px;color:#a9b6c6;margin:8px 0 6px}
    input,select{width:100%;background:#1d2d44;border:none;border-radius:6px;padding:10px 12px;color:#cfd8e3;margin-bottom:14px}
    .grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
    .row{display:flex;justify-content:space-between;align-items:center;margin-top:-4px;margin-bottom:10px;font-size:13px;color:#aab5c4}
    .btn{width:100%;border:none;border-radius:6px;padding:10px;font-weight:500;background:#2a3a4a;color:#d8e0eb;cursor:pointer}
    .divider{text-align:center;color:#94a7bb;margin:12px 0;font-size:13px}
    .btn-alt{width:100%;border:none;border-radius:6px;padding:10px;background:#30445c;color:#dbe5f3;cursor:pointer}
    .foot{margin-top:12px;font-size:10.5px;color:#8195ad;display:flex;justify-content:space-between}
    .footnote{font-size:12px;color:#7f94ac;margin-top:22px;text-align:center}
    .eye{position:absolute;right:10px;top:50%;transform:translateY(-50%);opacity:.7;cursor:pointer}
    .relative{position:relative}
    .checkrow{display:flex;align-items:center;gap:10px;margin:8px 0;color:#9fb0c2;font-size:13px}
    .muted{color:#8aa0b6}
    .flagchip{display:flex;align-items:center;gap:8px}
    .disabled{opacity:.6}
    /* Telefon girdisi için kapsayıcı stilleri */
    .phonewrap{display:flex;align-items:center;gap:6px}
    .phonewrap .prefix{background:#1d2d44;padding:10px 12px;border-radius:6px;color:#cfd8e3;flex:0 0 auto}
    .phonewrap input{margin-bottom:0;border-radius:6px}
    small.note{color:#8fa3b8;font-size:12px;display:block;margin-top:6px}
  </style>
</head>
<body>
  <header>
    <div class="brand">
      <div class="logo"><svg viewBox="0 0 24 24"><path d="M10 17L5 12l1.5-1.5 3.5 3.5 8-8L19 7z"/></svg></div>
      <strong>Trading</strong>
    </div>
    <div class="langbar"><span>🌙</span><img class="flag" src="https://flagcdn.com/w20/tr.png" alt="TR"><span>TR</span></div>
  </header>

  <main>
    <div class="card">
      <h2>Hoş geldiniz</h2>
      <div class="tabs">
        <button class="tabBtn" data-tab="login">Giriş</button>
        <button class="tabBtn" data-tab="register">Kayıt</button>
      </div>

      <!-- LOGIN -->
      <section id="tab-login">
        <?php if($errors): ?>
          <div style="background:#452b2b;padding:8px 10px;border-radius:6px;color:#f3bebe;font-size:13px;margin-bottom:10px;">
            <?php foreach($errors as $e) echo htmlspecialchars($e).'<br>'; ?>
          </div>
        <?php endif; ?>
        <form method="post">
          <input type="hidden" name="action" value="login">
          <label class="small" for="email">E-posta</label>
          <input type="email" name="email" id="email" required>
          <label class="small" for="password">Şifre</label>
          <div class="relative">
            <input type="password" name="password" id="password" required>
            <span class="eye" onclick="togglePass('password')">👁️</span>
          </div>
          <div class="row"><label><input type="checkbox" name="remember"> Beni hatırla</label><a href="#" style="color:#aab5c4;text-decoration:none;">Şifrenizi mi unuttunuz?</a></div>
          <button class="btn" type="submit">Giriş</button>
          <div class="divider">veya</div>
          <button class="btn-alt" type="button" onclick="alert('Bu bir simülasyondur.')">API anahtarıyla giriş yapın</button>
          <div class="foot"><span>App version: v6.17.2</span><a href="#" style="color:#8fa7c0;text-decoration:none;">Geri bildirim</a></div>
        </form>
      </section>

      <!-- REGISTER -->
      <section id="tab-register" style="display:none;">
        <?php if($reg_errors): ?>
          <div style="background:#452b2b;padding:8px 10px;border-radius:6px;color:#f3bebe;font-size:13px;margin-bottom:10px;">
            <?php foreach($reg_errors as $e) echo htmlspecialchars($e).'<br>'; ?>
          </div>
        <?php endif; ?>

        <form method="post" id="regForm" onsubmit="combinePhoneForSubmit(event)">
          <input type="hidden" name="action" value="register">
          <!-- gizli gerçek telefon alanı (+90xxxxxxxxxx olarak set edilecek) -->
          <input type="hidden" name="phone" id="phone_full">

          <div class="grid">
            <div>
              <label class="small" for="name">İsim</label>
              <input id="name" name="name" placeholder="İsim" required>
            </div>
            <div>
              <label class="small" for="surname">Soyisim</label>
              <input id="surname" name="surname" placeholder="Soyisim" required>
            </div>
          </div>

          <div class="grid">
            <div>
              <label class="small" for="reg_email">E-posta</label>
              <input id="reg_email" name="reg_email" type="email" placeholder="E-posta" required pattern=".+@.+" title="E-posta adresi '@' içermelidir">
            </div>
            <div>
              <label class="small" for="phone_local">Telefon Numarası</label>
              <div class="phonewrap">
                <span class="prefix">+90</span>
                <input id="phone_local" type="tel" inputmode="numeric" placeholder="5XXXXXXXXX"
                       maxlength="10" pattern="[0-9]{10}" required
                       oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,10)">
              </div>
              <small class="note">Başında 0 olmadan 10 haneli numara girin (örn: 5XXXXXXXXX). Form gönderildiğinde otomatik olarak +90 eklenir.</small>
            </div>
          </div>

          <div class="grid">
            <div>
              <label class="small" for="reg_password">Şifre</label>
              <div class="relative"><input id="reg_password" name="reg_password" type="password" placeholder="Şifre" required><span class="eye" onclick="togglePass('reg_password')">👁️</span></div>
            </div>
            <div>
              <label class="small" for="reg_password2">Şifreyi doğrulayın</label>
              <div class="relative"><input id="reg_password2" name="reg_password2" type="password" placeholder="Şifreyi tekrar girin" required><span class="eye" onclick="togglePass('reg_password2')">👁️</span></div>
            </div>
          </div>

          <div class="grid">
            <div>
              <label class="small" for="country">Ülke</label>
              <!-- Kilitli: Türkiye -->
              <select id="country_locked" disabled>
                <option value="TR" selected>Türkiye</option>
              </select>
              <input type="hidden" id="country" name="country" value="TR">
            </div>
            <div>
              <label class="small" for="lang">Destek Dili</label>
              <!-- Kilitli: Turkish -->
              <div class="flagchip">
                <img src="https://flagcdn.com/w20/tr.png" width="20" height="14" alt="TR">
                <select id="lang_locked" disabled>
                  <option value="tr" selected>Turkish</option>
                </select>
                <input type="hidden" id="lang" name="lang" value="tr">
              </div>
            </div>
          </div>

          <label class="checkrow"><input type="checkbox" id="agree" name="agree"> <span>Gizlilik Politikası'nı ve <a href="#" class="muted">Kullanım Koşulları</a>'nı okudum ve kabul ediyorum</span></label>

          <button id="regSubmit" class="btn disabled" type="submit" disabled>Devam etmek</button>

          <div class="foot"><span>App version: v6.17.2</span><a href="#" style="color:#8fa7c0;text-decoration:none;">Geri bildirim</a></div>
        </form>
      </section>
    </div>

    <div class="footnote">"data/users.json" dosyasına yazan dosya-tabanlı hesap sistemi (simülasyon).</div>
  </main>

  <script>
    // Tab kontrol
    const tabButtons = document.querySelectorAll('.tabBtn');
    const loginSec = document.getElementById('tab-login');
    const regSec = document.getElementById('tab-register');
    function activate(tab){
      tabButtons.forEach(btn=>btn.classList.remove('active'));
      document.querySelector(`.tabBtn[data-tab="${tab}"]`).classList.add('active');
      if(tab==='login'){ loginSec.style.display='block'; regSec.style.display='none'; }
      else { regSec.style.display='block'; loginSec.style.display='none'; }
    }
    // Varsayılan: giriş sekmesi (kayıtta zaten redirect ediliyor)
    activate('login');
    tabButtons.forEach(b=>b.addEventListener('click',()=>activate(b.dataset.tab)));

    // Şifre göster/gizle
    function togglePass(id){ const el=document.getElementById(id); el.type = el.type==='password'?'text':'password'; }
    window.togglePass = togglePass;

    // Kayıt: "kabul" olmadan buton pasif
    const agree=document.getElementById('agree');
    const regSubmit=document.getElementById('regSubmit');
    if(agree && regSubmit){
      function syncAgree(){ regSubmit.disabled=!agree.checked; regSubmit.classList.toggle('disabled', !agree.checked); }
      agree.addEventListener('change',syncAgree); syncAgree();
    }

    // Telefonu submit öncesi +90 ile birleştirip gizli inputa yaz
    function combinePhoneForSubmit(e){
      const local = document.getElementById('phone_local').value.trim();
      if(!/^[0-9]{10}$/.test(local)){
        e.preventDefault();
        alert('Telefon numarası 10 haneli olmalı (örn. 5XXXXXXXXX).');
        return false;
      }
      document.getElementById('phone_full').value = '+90' + local;
      return true;
    }
    window.combinePhoneForSubmit = combinePhoneForSubmit;
  </script>
</body>
</html>
